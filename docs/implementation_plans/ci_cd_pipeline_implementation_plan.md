# CI/CD Pipeline Implementation Plan for Gravitycar Framework

## Feature Overview

This plan outlines the implementation of a Continuous Integration/Continuous Deployment (CI/CD) pipeline for the Gravitycar Framework. The pipeline will automate the entire process from code commit to production deployment across two subdomains: `api.gravitycar.com` (backend) and `react.gravitycar.com` (frontend).

The CI/CD system will provide automated testing, building, deployment, and health verification to ensure reliable, consistent deployments with minimal manual intervention.

## Requirements

### Functional Requirements

1. **Source Control Integration**
   - Manual deployment triggers initially for safety and control
   - Comprehensive testing automation on all commits
   - Branch protection rules to prevent direct commits without tests
   - Future migration path to automatic deployments

2. **Frontend Build Process**
   - Automated npm dependency installation
   - TypeScript compilation and build process
   - Frontend asset optimization and bundling
   - Environment-specific configuration injection

3. **Backend Preparation**
   - Composer dependency management
   - PHP autoloader optimization
   - Configuration validation

4. **Testing Automation**
   - Execute PHPUnit test suites (Unit, Integration, Feature)
   - Generate test coverage reports
   - Frontend linting and type checking
   - Fail deployment on any test failures

5. **Deployment Process**
   - Secure file transfer to production servers
   - Atomic deployments with rollback capability
   - Database schema updates via setup.php
   - Cache rebuilding and optimization

6. **Health Verification**
   - API endpoint connectivity tests
   - Authentication flow validation
   - Frontend accessibility checks
   - Database connectivity verification

7. **Notification System**
   - Email alerts for failures (remote execution)
   - Console output for local execution
   - Deployment status reporting

### Non-Functional Requirements

1. **Security**
   - Secure credential management for production servers
   - Environment variable isolation
   - Access logging and audit trails

2. **Performance**
   - Pipeline execution time under 10 minutes for typical deployments
   - Parallel execution where possible
   - Incremental builds when appropriate

3. **Reliability**
   - Atomic deployments (all-or-nothing)
   - Rollback mechanisms
   - Zero-downtime deployments

4. **Maintainability**
   - Clear pipeline configuration
   - Modular script architecture
   - Comprehensive logging

## Design

### Architecture Overview

The CI/CD pipeline will be implemented using GitHub Actions as the primary automation platform, with support for local execution via shell scripts. The architecture follows a multi-stage approach:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Git Trigger   │───▶│   Build Stage    │───▶│   Test Stage        │
│  (Push/Manual)  │    │  - Frontend npm  │    │  - PHPUnit Tests    │
│                 │    │  - Backend deps  │    │  - Frontend linting │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
                                                          │
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│ Health Checks   │◀───│ Deploy Stage     │◀───│   Package Stage     │
│ - API Tests     │    │ - File Transfer  │    │ - Production Build  │
│ - UI Tests      │    │ - Database Setup │    │ - Asset Bundling    │
│ - Notifications │    │ - Service Config │    │                     │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
```

### Component Design

#### 1. GitHub Actions Workflow
- **Main workflow file**: `.github/workflows/deploy.yml`
- **Reusable workflows**: Separate workflows for build, test, and deploy stages
- **Environment configurations**: Development and production environments

#### 2. Local Script System
- **Master script**: `scripts/deploy.sh` - Main orchestration script
- **Stage scripts**: Individual scripts for each pipeline stage
- **Configuration management**: Environment-specific configuration files

#### 3. Testing Infrastructure
- **PHPUnit configuration**: Enhanced phpunit.xml with proper test organization
- **Test data management**: Fixtures and test database management
- **Coverage reporting**: HTML and text coverage reports

#### 4. Deployment Mechanism
- **Package builder**: Scripts to create deployment packages
- **Transfer system**: Secure file transfer to production servers
- **Health checker**: Post-deployment verification scripts
- **Manual trigger system**: Safe, controlled deployment initiation

### Deployment Trigger Strategy

#### Phase 1-3: Manual-Only Deployment (Recommended Start)

**Philosophy**: Start with full control and manual verification before moving to automation.

**Manual Deployment Methods:**

1. **GitHub Actions Web Interface (Primary Method)**
   ```
   1. Navigate to: https://github.com/gravitycar/gravitycar_no_code/actions
   2. Select "Deploy to Production" workflow
   3. Click "Run workflow" button
   4. Select target environment: "production"
   5. Enter deployment confirmation: "DEPLOY"
   6. Click "Run workflow" to start deployment
   ```

2. **GitHub CLI (Command Line)**
   ```bash
   # Install GitHub CLI if not already installed
   # https://cli.github.com/

   # Trigger deployment from command line
   gh workflow run deploy.yml \
     --ref main \
     -f environment=production \
     -f confirm_deployment=DEPLOY

   # Monitor deployment progress
   gh run list --workflow=deploy.yml --limit=1
   gh run view --log  # View logs of latest run
   ```

3. **GitHub API (Programmatic)**
   ```bash
   # Using curl with GitHub token
   curl -X POST \
     -H "Authorization: token $GITHUB_TOKEN" \
     -H "Accept: application/vnd.github.v3+json" \
     https://api.github.com/repos/gravitycar/gravitycar_no_code/actions/workflows/deploy.yml/dispatches \
     -d '{"ref":"main","inputs":{"environment":"production","confirm_deployment":"DEPLOY"}}'
   ```

4. **Local Script Execution (Development/Testing)**
   ```bash
   # For development and testing - bypass GitHub Actions
   ./scripts/deploy.sh --environment=production --confirm
   ```

**Manual Deployment Workflow Configuration:**
```yaml
# .github/workflows/deploy.yml (Phase 1-3 Configuration)
name: Deploy to Production
on:
  workflow_dispatch:  # Manual trigger only
    inputs:
      environment:
        description: 'Target environment'
        required: true
        type: choice
        options:
          - 'production'
        default: 'production'
      confirm_deployment:
        description: 'Type "DEPLOY" to confirm deployment'
        required: true
        type: string
      git_ref:
        description: 'Git reference to deploy (default: main)'
        required: false
        type: string
        default: 'main'

jobs:
  validate-input:
    runs-on: ubuntu-latest
    steps:
      - name: Validate Deployment Confirmation
        run: |
          if [ "${{ github.event.inputs.confirm_deployment }}" != "DEPLOY" ]; then
            echo "❌ Deployment confirmation failed. Must type 'DEPLOY' exactly."
            exit 1
          fi
          echo "✅ Deployment confirmed by: ${{ github.actor }}"

  deploy:
    needs: validate-input
    runs-on: ubuntu-latest
    environment: production
    steps:
      # Deployment steps here...
```

**Safety Features for Manual Deployment:**
- **Confirmation Required**: Must type "DEPLOY" to proceed
- **Environment Protection**: GitHub environment approval rules
- **Actor Logging**: Track who initiated each deployment
- **Reference Selection**: Choose specific git commit if needed

#### Phase 4+: Migration to Automatic Deployment (Future Enhancement)

**When to Consider Automatic Deployment:**
- CI/CD pipeline has been stable for 4+ weeks
- Test coverage exceeds 90%
- Team is comfortable with the process
- Rollback procedures have been tested successfully

**Automatic Deployment Options (Future):**

1. **Conditional Automatic (Recommended Next Step)**
   ```yaml
   on:
     push:
       branches: [ main ]
       paths-ignore:
         - 'docs/**'
         - 'README.md'
         - '.gitignore'
     workflow_dispatch:  # Keep manual option

   jobs:
     deploy:
       if: |
         github.event_name == 'workflow_dispatch' ||
         (github.event_name == 'push' && 
          !contains(github.event.head_commit.message, '[skip-deploy]'))
   ```

2. **Commit Message Triggered**
   ```bash
   # Trigger deployment
   git commit -m "Fix authentication bug [deploy]"
   
   # Skip deployment
   git commit -m "Update documentation [skip-deploy]"
   ```

3. **Scheduled Batch Deployment**
   ```yaml
   on:
     schedule:
       - cron: '0 14 * * 1-5'  # 2 PM on weekdays
     workflow_dispatch:
   ```

4. **Fully Automatic**
   ```yaml
   on:
     push:
       branches: [ main ]
     workflow_dispatch:
   ```

**Migration Process to Automatic:**
1. Update workflow file to include push triggers
2. Test with non-critical changes first
3. Monitor deployment success rates
4. Gradually reduce manual oversight
5. Maintain manual override capability

#### Deployment Decision Framework

**Use Manual Deployment For:**
- ✅ Critical bug fixes
- ✅ Major feature releases
- ✅ Database schema changes
- ✅ Security updates
- ✅ First-time deployments
- ✅ Emergency situations

**Future Automatic Deployment For:**
- 🔄 Minor bug fixes
- 🔄 Documentation updates
- 🔄 Configuration changes
- 🔄 Regular feature updates
- 🔄 Dependency updates

**Always Manual (Never Automatic):**
- 🚫 Breaking changes
- 🚫 Major version updates
- 🚫 Infrastructure changes
- 🚫 Security-sensitive updates

### File Structure

```
.github/
├── workflows/
│   ├── deploy.yml                    # Main deployment workflow
│   ├── test.yml                      # Testing workflow
│   └── build.yml                     # Build workflow
└── scripts/
    ├── health-check.sh               # Production health verification
    └── notify.sh                     # Notification system

scripts/
├── deploy.sh                         # Main deployment orchestrator
├── build/
│   ├── build-frontend.sh            # Frontend build process
│   ├── build-backend.sh             # Backend preparation
│   └── package.sh                   # Package creation
├── test/
│   ├── run-tests.sh                 # Test execution
│   ├── test-frontend.sh             # Frontend testing
│   └── test-backend.sh              # Backend testing
├── deploy/
│   ├── transfer.sh                  # File transfer to production
│   ├── setup-production.sh          # Production configuration
│   └── rollback.sh                  # Rollback mechanism
└── config/
    ├── environments.conf             # Environment configurations
    └── credentials.conf.example      # Credential template

config/
├── phpunit.xml                       # PHPUnit configuration
├── deployment.json                   # Deployment configuration
└── environments/
    ├── development.json
    └── production.json
```

## Implementation Steps

### Responsibility Matrix

This plan involves two types of tasks that require different approaches:

#### **[AI AGENT]** - Implementable by AI Assistant
- **File Creation**: Scripts, workflows, configuration files
- **Code Updates**: Modifying existing PHP/TypeScript/YAML files  
- **Documentation**: Creating guides, runbooks, and procedures
- **Logic Implementation**: Build processes, testing automation, deployment scripts

#### **[USER ACTION]** - Requires Manual Action
- **GitHub Settings**: Repository configuration, secrets, branch protection
- **System Administration**: Installing software, SSH key setup
- **External Services**: Email configuration, production server access
- **Testing/Validation**: Running commands, verifying functionality
- **Decision Making**: Reviewing implementations, approving changes

#### **Collaboration Workflow**
1. **AI Agent implements** all code, scripts, and configuration files
2. **User performs** GitHub settings, system setup, and validation
3. **Both review** at each phase checkpoint before proceeding
4. **User provides feedback** and AI Agent adjusts implementation

### Phase 1: Foundation Setup (Week 1)

#### 1.1 Repository Configuration
- [ ] **[USER ACTION]** Create `.github/workflows/` directory structure
- [ ] **[USER ACTION]** Set up branch protection rules on main branch via GitHub settings
- [ ] **[USER ACTION]** Configure GitHub repository secrets for production credentials
- [ ] **[USER ACTION]** Create deployment environments in GitHub repository settings

#### 1.2 Local Script Infrastructure
- [ ] **[AI AGENT]** Create `scripts/` directory structure
- [ ] **[AI AGENT]** Implement `scripts/deploy.sh` main orchestrator
- [ ] **[AI AGENT]** Create configuration management system
- [ ] **[AI AGENT]** Set up logging infrastructure

#### 1.3 Testing Enhancement
- [ ] **[USER ACTION]** Install SQLite for lightweight testing database (`sudo apt-get install php-sqlite3` or equivalent)
- [ ] **[AI AGENT]** Enhance existing `phpunit.xml` with CI/CD features (coverage, logging, cache)
- [ ] **[AI AGENT]** Configure test suite exclusions (exclude Demo tests from CI/CD runs)
- [ ] **[AI AGENT]** Add coverage and cache directories to `.gitignore`
- [ ] **[AI AGENT]** Update `Tests/Unit/DatabaseTestCase.php` to support environment-aware database configuration
- [ ] **[AI AGENT]** Update `Tests/Integration/IntegrationTestCase.php` for SQLite compatibility
- [ ] **[AI AGENT]** Validate test categorization and execution order
- [ ] **[AI AGENT]** Set up test database configuration
- [ ] **[AI AGENT]** Implement test coverage reporting
- [ ] **[USER ACTION]** Validate enhanced PHPUnit configuration locally
- [ ] **[AI AGENT]** Document any additional test compatibility issues discovered during implementation

#### Phase 1 Deliverables
- Basic directory structure in place
- Core script templates created
- Enhanced phpunit.xml with CI/CD features
- SQLite testing environment configured
- Two test base classes updated for database compatibility
- Coverage and logging directories configured
- Documentation for Phase 1 components
- Addendum documentation for any additional test compatibility issues discovered

**📋 REVIEW CHECKPOINT**: After completing Phase 1, commit changes and request review before proceeding to Phase 2.

```bash
git add .
git commit -m "Phase 1: Foundation setup - directory structure, scripts, testing config"
git push origin feature/ci-cd-pipeline
```

#### User Action Guide for Phase 1

**Required User Actions:**
1. **Install SQLite** (if not already installed):
   ```bash
   # Ubuntu/Debian
   sudo apt-get update && sudo apt-get install php-sqlite3
   
   # Verify installation
   php -m | grep -i sqlite
   ```

2. **Create GitHub Workflows Directory**:
   ```bash
   mkdir -p .github/workflows
   ```

3. **Set Up Branch Protection Rules**:
   - Go to: `https://github.com/gravitycar/gravitycar_no_code/settings/branches`
   - Click "Add rule" for `main` branch
   - Enable: "Require pull request reviews before merging"
   - Enable: "Require status checks to pass before merging"
   - Enable: "Require branches to be up to date before merging"

4. **Create Production Environment**:
   - Go to: `https://github.com/gravitycar/gravitycar_no_code/settings/environments`
   - Click "New environment"
   - Name: `production`
   - Enable "Required reviewers" and add yourself
   - Click "Create environment"

5. **Validate Local Testing**:
   ```bash
   # Test current setup
   vendor/bin/phpunit --version
   
   # Test with SQLite (after AI Agent updates files)
   export DB_CONNECTION=sqlite
   export DB_DATABASE=":memory:"
   vendor/bin/phpunit --testsuite=Unit
   ```

### Phase 2: Build and Test Automation (Week 2)

#### 2.1 Frontend Build System
- [ ] **[AI AGENT]** Implement `scripts/build/build-frontend.sh`
- [ ] **[AI AGENT]** Create environment-specific build configurations
- [ ] **[AI AGENT]** Set up frontend testing with npm scripts
- [ ] **[AI AGENT]** Implement frontend linting automation

#### 2.2 Backend Build System
- [ ] **[AI AGENT]** Implement `scripts/build/build-backend.sh`
- [ ] **[AI AGENT]** Create composer optimization scripts
- [ ] **[AI AGENT]** Set up PHP configuration validation
- [ ] **[AI AGENT]** Implement autoloader optimization

#### 2.3 Test Automation
- [ ] **[AI AGENT]** Create `scripts/test/run-tests.sh` comprehensive test runner
- [ ] **[AI AGENT]** Implement parallel test execution
- [ ] **[AI AGENT]** Set up test result reporting
- [ ] **[AI AGENT]** Create test failure notification system

#### Phase 2 Deliverables
- Working build scripts for both frontend and backend
- Automated test execution system
- Build and test validation locally

**📋 REVIEW CHECKPOINT**: After completing Phase 2, commit changes and request review before proceeding to Phase 3.

```bash
git add .
git commit -m "Phase 2: Build and test automation - frontend/backend builds, test runner"
git push origin feature/ci-cd-pipeline
```

### Phase 3: GitHub Actions Implementation (Week 3)

#### 3.1 Workflow Creation
- [ ] **[AI AGENT]** Implement `.github/workflows/deploy.yml` with manual-only triggers
- [ ] **[AI AGENT]** Create reusable workflow components
- [ ] **[AI AGENT]** Set up environment-specific workflows
- [ ] **[AI AGENT]** Configure manual deployment confirmation requirements
- [ ] **[AI AGENT]** Implement deployment trigger options (GitHub UI, CLI, API)
- [ ] **[AI AGENT]** Design automatic deployment migration path for future enhancement

#### 3.2 GitHub Actions Integration
- [ ] **[AI AGENT]** Implement artifact management between jobs
- [ ] **[AI AGENT]** Set up secure credential handling
- [ ] **[AI AGENT]** Configure notification actions
- [ ] **[AI AGENT]** Implement workflow status reporting

#### 3.3 Security and Secrets Management
- [ ] **[USER ACTION]** Configure GitHub repository secrets in GitHub settings
- [ ] **[USER ACTION]** Set up production server SSH keys and credentials
- [ ] **[AI AGENT]** Implement secure credential injection in workflows
- [ ] **[AI AGENT]** Set up environment variable management
- [ ] **[AI AGENT]** Create access control mechanisms
- [ ] **[USER ACTION]** Configure GitHub environment protection rules

#### Phase 3 Deliverables
- Complete GitHub Actions workflows with manual-only triggers
- Secure secrets management
- Manual deployment confirmation system
- Multiple deployment trigger methods (UI, CLI, API)
- Automated CI pipeline (testing and building)
- Documentation for manual deployment procedures

**📋 REVIEW CHECKPOINT**: After completing Phase 3, commit changes and request review before proceeding to Phase 4.

```bash
git add .
git commit -m "Phase 3: GitHub Actions implementation - manual deployment workflows, security, CI pipeline"
git push origin feature/ci-cd-pipeline
```

#### User Action Guide for Phase 3

**Required User Actions:**
1. **Configure GitHub Repository Secrets**:
   - Go to: `https://github.com/gravitycar/gravitycar_no_code/settings/secrets/actions`
   - Add the following secrets:
   ```
   PRODUCTION_SSH_HOST=api.gravitycar.com
   PRODUCTION_SSH_USER=your_username
   PRODUCTION_SSH_KEY=[your private SSH key content]
   PRODUCTION_DB_HOST=your_mysql_host
   PRODUCTION_DB_NAME=gravitycar_production
   PRODUCTION_DB_USER=production_user
   PRODUCTION_DB_PASSWORD=secure_password
   TMDB_API_KEY=your_tmdb_api_key
   NOTIFICATION_EMAIL_HOST=smtp.gmail.com
   NOTIFICATION_EMAIL_USER=notifications@gravitycar.com
   NOTIFICATION_EMAIL_PASSWORD=app_specific_password
   ```

2. **Set Up Production Server SSH Keys**:
   ```bash
   # Generate deployment key pair (if needed)
   ssh-keygen -t ed25519 -f ~/.ssh/gravitycar_deploy -N ""
   
   # Copy public key to production server
   ssh-copy-id -i ~/.ssh/gravitycar_deploy.pub user@api.gravitycar.com
   
   # Test connection
   ssh -i ~/.ssh/gravitycar_deploy user@api.gravitycar.com "echo 'Connection successful'"
   ```

3. **Configure Environment Protection Rules**:
   - Go to: `https://github.com/gravitycar/gravitycar_no_code/settings/environments`
   - Click on "production" environment
   - Under "Environment protection rules":
     - Enable "Required reviewers" and add yourself
     - Set "Wait timer" to 0 minutes (or desired delay)
   - Click "Save protection rules"

4. **Test Manual Deployment Trigger**:
   ```bash
   # Install GitHub CLI if not already installed
   # Then test workflow trigger (after AI Agent creates workflow)
   gh workflow list
   gh workflow run deploy.yml -f environment=production -f confirm_deployment=DEPLOY
   ```

### Phase 4: Deployment Automation (Week 4)

#### 4.1 Package Creation
- [ ] **[AI AGENT]** Implement `scripts/build/package.sh`
- [ ] **[AI AGENT]** Create production-ready build artifacts
- [ ] **[AI AGENT]** Set up package versioning
- [ ] **[AI AGENT]** Implement package validation

#### 4.2 Transfer and Deployment
- [ ] **[AI AGENT]** Implement `scripts/deploy/transfer.sh`
- [ ] **[AI AGENT]** Create atomic deployment mechanism
- [ ] **[AI AGENT]** Set up production configuration management
- [ ] **[AI AGENT]** Implement database schema updates

#### 4.3 Health Verification
- [ ] **[AI AGENT]** Create `scripts/health-check.sh`
- [ ] **[AI AGENT]** Implement API endpoint testing
- [ ] **[AI AGENT]** Set up frontend accessibility checks
- [ ] **[AI AGENT]** Create database connectivity verification

#### Phase 4 Deliverables
- Complete deployment automation
- Production-ready package creation
- Health verification system

**📋 REVIEW CHECKPOINT**: After completing Phase 4, commit changes and request review before proceeding to Phase 5.

```bash
git add .
git commit -m "Phase 4: Deployment automation - packaging, transfer, health checks"
git push origin feature/ci-cd-pipeline
```

### Phase 5: Notification and Monitoring (Week 5)

#### 5.1 Notification System
- [ ] **[USER ACTION]** Set up email credentials for notifications (SMTP settings)
- [ ] **[AI AGENT]** Implement email notification for failures
- [ ] **[AI AGENT]** Create deployment status reporting
- [ ] **[AI AGENT]** Set up success/failure notifications
- [ ] **[AI AGENT]** Configure notification preferences

#### 5.2 Rollback Mechanism
- [ ] **[AI AGENT]** Implement `scripts/deploy/rollback.sh`
- [ ] **[AI AGENT]** Create backup and restore procedures
- [ ] **[AI AGENT]** Set up emergency rollback triggers
- [ ] **[USER ACTION]** Test rollback scenarios in development environment

#### 5.3 Documentation and Training
- [ ] **[AI AGENT]** Create deployment runbooks
- [ ] **[AI AGENT]** Document troubleshooting procedures
- [ ] **[AI AGENT]** Create user training materials
- [ ] **[USER ACTION]** Set up monitoring dashboards (if desired)
- [ ] **[USER ACTION]** Review and validate all documentation

#### Phase 5 Deliverables
- Complete notification system
- Rollback and recovery procedures
- Comprehensive documentation

**📋 REVIEW CHECKPOINT**: After completing Phase 5, commit changes and request final review before merging.

```bash
git add .
git commit -m "Phase 5: Notifications and monitoring - alerts, rollback, documentation"
git push origin feature/ci-cd-pipeline
```

#### User Action Guide for Phase 5

**Required User Actions:**
1. **Set Up Email Credentials for Notifications**:
   ```bash
   # Add to GitHub secrets (if not already done in Phase 3)
   NOTIFICATION_EMAIL_HOST=smtp.gmail.com
   NOTIFICATION_EMAIL_USER=notifications@gravitycar.com
   NOTIFICATION_EMAIL_PASSWORD=app_specific_password
   DEPLOYMENT_NOTIFICATION_RECIPIENTS=admin@gravitycar.com,dev@gravitycar.com
   ```

2. **Test Rollback Scenarios**:
   ```bash
   # Test rollback script locally (after AI Agent creates it)
   ./scripts/deploy/rollback.sh --dry-run
   
   # Test rollback with development environment
   ./scripts/deploy/rollback.sh --environment=development --test
   ```

3. **Set Up Monitoring Dashboards** (Optional):
   - Configure your preferred monitoring solution (e.g., New Relic, DataDog)
   - Set up alerts for deployment failures
   - Configure uptime monitoring for api.gravitycar.com and react.gravitycar.com

4. **Review and Validate Documentation**:
   - Read through all generated documentation
   - Test deployment procedures
   - Verify troubleshooting guides are accurate
   - Confirm emergency contact information is correct

**🎯 FINAL REVIEW**: Create pull request for complete CI/CD pipeline implementation

```bash
# Create pull request via GitHub CLI or web interface
gh pr create --title "CI/CD Pipeline Implementation" \
  --body "Complete CI/CD pipeline with all 5 phases implemented. Ready for review and testing."
```

## Testing Strategy

### Test Categories

1. **Unit Tests**
   - Model class functionality
   - Field validation logic
   - Core framework components
   - Target: >90% code coverage

2. **Integration Tests**
   - Database integration
   - API endpoint functionality
   - Relationship handling
   - Service integration

3. **Feature Tests**
   - End-to-end user workflows
   - Authentication flows
   - CRUD operations
   - UI component functionality

4. **Deployment Tests**
   - Pipeline execution validation
   - Health check verification
   - Rollback procedure testing
   - Performance regression tests

### Testing Infrastructure

1. **Local Testing Environment**
   - SQLite for lightweight, fast test database
   - Test database with fixtures
   - Mock external services (TMDB API)
   - Automated test data management

#### SQLite Installation Requirements

**Ubuntu/Debian Systems:**
```bash
sudo apt-get update
sudo apt-get install php-sqlite3
```

**CentOS/RHEL Systems:**
```bash
sudo yum install php-sqlite3
# or for newer versions:
sudo dnf install php-sqlite3
```

**Verify Installation:**
```bash
php -m | grep -i sqlite
# Should show: pdo_sqlite, sqlite3
```

#### Enhanced phpunit.xml Configuration

The existing `phpunit.xml` will be enhanced with CI/CD-specific features:

**Coverage Reporting:**
- HTML output for browsable coverage reports
- Text output for console coverage summaries
- XML output for machine-readable CI integration

**CI/CD Integration:**
- JUnit XML output for GitHub Actions test result display
- TestDox HTML for human-readable test documentation
- Cache directory configuration for performance

**Enhanced phpunit.xml Structure:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         failOnRisky="true"
         failOnWarning="false"
         stopOnFailure="false">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>Tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>Tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>Tests/Feature</directory>
        </testsuite>
        <!-- Demo tests - excluded from default runs -->
        <testsuite name="Demo">
            <directory>Tests/Demo</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>src/Contracts</directory>
            <!-- Exclude demo test from default test runs -->
            <file>Tests/Demo/Phase3DemonstrationTest.php</file>
        </exclude>
    </source>

    <!-- Coverage reporting for CI/CD -->
    <coverage>
        <report>
            <html outputDirectory="coverage/html"/>
            <text outputFile="coverage/coverage.txt"/>
            <xml outputDirectory="coverage/xml"/>
        </report>
    </coverage>

    <!-- Logging for CI/CD integration -->
    <logging>
        <junit outputFile="coverage/junit.xml"/>
        <testdoxHtml outputFile="coverage/testdox.html"/>
    </logging>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

**Required .gitignore Additions:**
```gitignore
# PHPUnit output and cache
/coverage/
/.phpunit.cache/
.phpunit.result.cache
```

#### Test Organization Strategy

**Current Test Structure Analysis:**
The existing test structure is well-organized with proper separation of Unit, Integration, and Feature tests. The main organizational task is optimizing for CI/CD execution:

**Planned Test File Updates:**
Phase 1 will update only two specific files to support SQLite:
1. **`Tests/Unit/DatabaseTestCase.php`** - Add environment-aware database configuration
2. **`Tests/Integration/IntegrationTestCase.php`** - Ensure SQLite compatibility

**SQLite Integration Approach:**
- Minimal changes to existing test logic
- Environment-based database selection (SQLite for CI, MySQL optional for local)
- Doctrine DBAL handles database compatibility automatically
- Existing SchemaGenerator supports both MySQL and SQLite

**Additional Test Compatibility:**
If additional test files require SQLite compatibility updates during implementation:
- Document specific issues and required changes
- Create an addendum to this implementation plan
- Implement additional changes in a separate phase
- Prioritize based on impact on CI/CD pipeline functionality

**Test Suite Execution Order:**
1. **Unit Tests** (fastest) - Isolated, mocked tests
2. **Integration Tests** (medium) - Database and service integration  
3. **Feature Tests** (slowest) - End-to-end workflows
4. **Demo Tests** (excluded) - Development demonstrations

**Demo Test Exclusion:**
- `Tests/Demo/Phase3DemonstrationTest.php` will be excluded from default CI/CD runs
- Can still be run manually with: `vendor/bin/phpunit --testsuite=Demo`
- Prevents demonstration code from affecting production pipeline

**Test Execution Commands:**
```bash
# Default run (excludes Demo)
vendor/bin/phpunit

# Run specific test suites
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Integration
vendor/bin/phpunit --testsuite=Feature

# Run demo tests manually
vendor/bin/phpunit --testsuite=Demo

# Coverage report
vendor/bin/phpunit --coverage-html coverage/html
```

#### Handling Additional Test Compatibility Issues

**Scope Management:**
Phase 1 focuses on updating only the two identified test base classes. This conservative approach ensures:
- Minimal risk of breaking existing functionality
- Clear scope boundaries for Phase 1
- Predictable timeline and deliverables

**Discovery and Documentation Process:**
If additional SQLite compatibility issues are discovered during Phase 1 implementation:

1. **Document the Issue:**
   ```
   File: [test file path]
   Issue: [specific compatibility problem]
   Error: [exact error message if applicable]
   Proposed Solution: [recommended fix]
   Priority: [High/Medium/Low based on CI impact]
   ```

2. **Create Implementation Addendum:**
   - Add section to this plan: "Phase 1 Addendum: Additional Test Updates"
   - Include all discovered issues and proposed solutions
   - Estimate effort for additional changes

3. **Decision Process:**
   - **Critical Issues** (block CI/CD): Implement immediately in Phase 1
   - **Non-Critical Issues**: Document for future phase or separate task
   - **Optional Improvements**: Note for consideration during pipeline optimization

**Example Addendum Structure:**
```markdown
## Phase 1 Addendum: Additional Test Updates

### Discovered Compatibility Issues

#### Issue 1: [Test File Name]
- **Problem**: [Description]
- **Solution**: [Fix required]
- **Status**: [Implemented/Deferred]

#### Issue 2: [Another Test File]
- **Problem**: [Description]  
- **Solution**: [Fix required]
- **Status**: [Implemented/Deferred]

### Implementation Impact
- Additional time required: [estimate]
- Risk assessment: [Low/Medium/High]
- Recommendation: [Implement now/Defer to later phase]
```

**Testing Strategy for Compatibility:**
```bash
# Test current MySQL setup
export DB_CONNECTION=mysql
vendor/bin/phpunit

# Test new SQLite setup
export DB_CONNECTION=sqlite
export DB_DATABASE=":memory:"
vendor/bin/phpunit

# Compare results and document any differences
```

2. **CI Testing Environment**
   - GitHub Actions runners
   - Service containers for dependencies
   - Parallel test execution
   - Artifact collection and reporting

3. **Production Health Checks**
   - API endpoint monitoring
   - Database connectivity tests
   - Frontend asset loading verification
   - Authentication system validation

### Test Execution Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Unit Tests    │───▶│ Integration Tests│───▶│   Feature Tests     │
│   (Fast)        │    │   (Medium)       │    │   (Comprehensive)   │
│   < 2 minutes   │    │   < 5 minutes    │    │   < 8 minutes       │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
                                                          │
                                                          ▼
                                                ┌─────────────────────┐
                                                │ Deployment Decision │
                                                │ Pass: Deploy        │
                                                │ Fail: Notify & Stop │
                                                └─────────────────────┘
```

## Documentation

### User Documentation

1. **Deployment Guide**
   - Manual deployment procedures (primary method)
   - Step-by-step GitHub Actions trigger instructions
   - Command-line deployment using GitHub CLI
   - Troubleshooting common deployment issues
   - Emergency procedures and contacts
   - Configuration management guide

2. **Developer Documentation**
   - Pipeline architecture overview
   - Manual deployment best practices
   - Custom script development guide
   - Testing best practices before deployment
   - Contributing to the CI/CD system

3. **Operations Manual**
   - Monitoring and alerting setup
   - Performance optimization guide
   - Security best practices
   - Backup and recovery procedures

### Technical Documentation

1. **API Documentation**
   - Script interfaces and parameters
   - Configuration file formats
   - Environment variable references
   - Exit codes and error handling

2. **Architecture Documentation**
   - System design decisions
   - Security model
   - Performance considerations
   - Scalability planning

## Risks and Mitigations

### Technical Risks

1. **Pipeline Failures**
   - **Risk**: Build or test failures blocking deployments
   - **Mitigation**: Comprehensive logging, rollback mechanisms, manual override options

2. **Security Vulnerabilities**
   - **Risk**: Credential exposure or unauthorized access
   - **Mitigation**: Encrypted secrets management, access controls, audit logging

3. **Performance Degradation**
   - **Risk**: Slow pipeline execution affecting development velocity
   - **Mitigation**: Parallel execution, incremental builds, pipeline optimization

4. **Deployment Failures**
   - **Risk**: Production outages during deployment
   - **Mitigation**: Atomic deployments, health checks, automatic rollback

### Operational Risks

1. **Complexity Management**
   - **Risk**: Pipeline becoming too complex to maintain
   - **Mitigation**: Modular design, comprehensive documentation, regular reviews

2. **Dependency Management**
   - **Risk**: External service dependencies causing failures
   - **Mitigation**: Fallback mechanisms, dependency caching, service monitoring

3. **Team Adoption**
   - **Risk**: Development team resistance to new processes
   - **Mitigation**: Training programs, gradual rollout, feedback incorporation

### Business Risks

1. **Deployment Delays**
   - **Risk**: Critical fixes delayed by pipeline issues
   - **Mitigation**: Manual deployment procedures, expedited review processes

2. **Quality Regressions**
   - **Risk**: Automated testing missing critical issues
   - **Mitigation**: Comprehensive test coverage, staging environment testing

## Success Metrics

### Performance Metrics
- **Pipeline Execution Time**: < 10 minutes for standard deployments
- **Test Coverage**: > 90% for backend code, > 80% for frontend code
- **Deployment Success Rate**: > 98% successful deployments
- **Rollback Time**: < 5 minutes for emergency rollbacks

### Quality Metrics
- **Bug Detection Rate**: > 95% of issues caught before production
- **Mean Time to Recovery**: < 30 minutes for production issues
- **False Positive Rate**: < 5% for test failures
- **Security Scan Pass Rate**: 100% for all deployments

### Adoption Metrics
- **Developer Satisfaction**: > 8/10 satisfaction score
- **Manual Deployment Reduction**: < 5% manual deployments
- **Documentation Usage**: Regular reference by team members
- **Training Completion**: 100% team completion within 30 days

## Implementation Timeline

### Week 1: Foundation (Phase 1)
- Repository setup and configuration
- Basic script infrastructure
- Testing framework enhancement
- **Review Checkpoint**: Foundation complete

### Week 2: Build Automation (Phase 2)
- Frontend and backend build systems
- Test automation implementation
- Local development workflow
- **Review Checkpoint**: Build system complete

### Week 3: GitHub Actions (Phase 3)
- Workflow implementation
- Security and secrets management
- Integration testing
- **Review Checkpoint**: CI pipeline complete

### Week 4: Deployment Pipeline (Phase 4)
- Production deployment automation
- Health verification system
- Package management
- **Review Checkpoint**: Deployment system complete

### Week 5: Finalization (Phase 5)
- Notification and monitoring
- Rollback mechanisms
- Documentation and training
- **Final Review**: Complete system ready for production

### Week 6: Testing and Validation
- End-to-end testing of complete pipeline
- Performance optimization
- Team training and feedback
- **Production Deployment**: Go-live with CI/CD pipeline

## Getting Started

### Prerequisites Setup
1. Ensure GitHub repository has appropriate permissions
2. Configure production server access credentials
3. Set up development environment with all dependencies
4. Review current deployment documentation

### Git Workflow Setup
Before beginning implementation, establish proper branch management:

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/ci-cd-pipeline
   git push -u origin feature/ci-cd-pipeline
   ```

2. **Branch Protection**
   - Set up branch protection rules for `main` branch
   - Require pull request reviews before merging
   - Require status checks to pass before merging

3. **Phase-Based Development**
   - Each phase will be implemented as separate commits
   - Review checkpoints after each phase completion
   - Incremental testing and validation

### Initial Implementation
1. Start with local script development for immediate benefit
2. Implement basic testing automation
3. Create GitHub Actions workflows incrementally
4. Test thoroughly in development environment before production use

### Gradual Rollout
1. Begin with manual trigger workflows
2. Add automatic triggers for non-production branches
3. Enable production deployment after thorough testing
4. Monitor and optimize based on usage patterns

### Git Workflow Throughout Implementation

**Branch Management**:
- Work on `feature/ci-cd-pipeline` branch throughout implementation
- Commit after each phase completion
- Request review at each checkpoint
- Merge to main only after final approval

**Review Process**:
- Each phase includes specific deliverables
- Mandatory review checkpoints prevent rushing ahead
- Incremental validation ensures quality at each step
- Early detection of issues before they compound

**Testing Strategy**:
- Test each phase independently before proceeding
- Validate scripts locally before committing
- Ensure backward compatibility at each step
- Document any issues or deviations from plan

This implementation plan provides a comprehensive approach to building a robust CI/CD pipeline that will significantly improve the Gravitycar Framework's deployment reliability and development velocity.

## Future Enhancement: Adding Staging Environment

When your application grows and you need additional testing before production deployment, you can add a staging environment. This section provides guidance for implementing staging as a future enhancement.

### When to Add Staging

Consider adding a staging environment when you experience:

1. **Team Growth**: Multiple developers working simultaneously
2. **Complex Features**: Features requiring extensive integration testing
3. **Stakeholder Review**: Business users need to review features before production
4. **Production Issues**: Bugs that only appear in production-like environments
5. **Performance Concerns**: Need to test performance under realistic conditions

### Staging Environment Setup

#### Prerequisites
- Additional subdomain: `staging.gravitycar.com` (both API and frontend)
- Separate server or server resources for staging
- Production-like database with test data
- Access to production-equivalent external services

#### Implementation Steps

1. **Infrastructure Setup**
   ```bash
   # Create staging subdomains (via hosting provider)
   # - staging-api.gravitycar.com
   # - staging-react.gravitycar.com
   
   # Or use staging.gravitycar.com with subdirectories:
   # - staging.gravitycar.com/api/
   # - staging.gravitycar.com/
   ```

2. **Configuration Files**
   ```bash
   # Add staging configuration
   config/environments/staging.json
   
   # Update deployment scripts to handle staging
   scripts/deploy/deploy-staging.sh
   ```

3. **Database Setup**
   ```sql
   -- Create staging database
   CREATE DATABASE gravitycar_staging;
   
   -- Copy production schema with test data
   -- (Sanitized version of production data)
   ```

4. **GitHub Actions Enhancement**
   ```yaml
   # Add staging deployment job
   deploy-staging:
     if: github.ref == 'refs/heads/develop'
     needs: test
     runs-on: ubuntu-latest
     environment: staging
     steps:
       - name: Deploy to Staging
         run: ./scripts/deploy/deploy-staging.sh
   
   # Add production deployment dependency
   deploy-production:
     if: github.ref == 'refs/heads/main'
     needs: [test, deploy-staging]  # Require staging success
   ```

5. **Enhanced Workflow with Staging**
   ```
   Developer → Development → Staging → Production
       ↓           ↓           ↓          ↓
   Local Code  → Push to    → Auto      → Manual
   Changes       develop      Deploy      Approval
                 Branch       Staging     Required
   ```

#### Staging-Specific Configuration

**Environment Variables** (`config/environments/staging.json`):
```json
{
  "database": {
    "host": "staging-db.gravitycar.com",
    "name": "gravitycar_staging",
    "user": "staging_user"
  },
  "api": {
    "base_url": "https://staging-api.gravitycar.com",
    "debug": false,
    "log_level": "info"
  },
  "frontend": {
    "api_url": "https://staging-api.gravitycar.com",
    "environment": "staging"
  },
  "external_services": {
    "tmdb_api": "production_key",  # Use real API
    "email": "test_smtp_settings"
  }
}
```

**Branch Strategy with Staging**:
```
main (production)     ←── merge after staging approval
  ↑
develop (staging)     ←── merge feature branches here
  ↑
feature branches      ←── development work
```

#### Staging-Enhanced Pipeline

1. **Feature Development**: Developer works on feature branch
2. **Development Testing**: Local testing and unit tests
3. **Staging Deployment**: Merge to `develop` triggers staging deployment
4. **Staging Testing**: Automated and manual testing on staging
5. **Stakeholder Review**: Business review on staging environment
6. **Production Deployment**: Approved changes merged to `main`

#### Additional Scripts for Staging

**Staging Health Checks** (`scripts/health-check-staging.sh`):
```bash
#!/bin/bash
# Enhanced health checks for staging environment
# - Test with production-like data volumes
# - Validate external service integrations
# - Performance benchmarking
# - User acceptance test automation
```

**Staging Data Management** (`scripts/staging/refresh-data.sh`):
```bash
#!/bin/bash
# Refresh staging with sanitized production data
# - Export production schema
# - Sanitize sensitive data
# - Import to staging database
# - Verify data integrity
```

#### Cost Considerations

**Resource Requirements**:
- Additional server resources (can be smaller than production)
- Database storage for staging data
- Bandwidth for staging deployments
- Monitoring and logging for staging environment

**Cost Optimization**:
- Use smaller server instances for staging
- Implement staging shutdown during off-hours
- Share resources between staging and development when possible
- Use staging for performance testing and optimization

### Migration to Staging

When you're ready to implement staging:

1. **Phase 1**: Set up staging infrastructure
2. **Phase 2**: Create staging configuration files
3. **Phase 3**: Update GitHub Actions workflows
4. **Phase 4**: Implement enhanced branch strategy
5. **Phase 5**: Train team on new workflow
6. **Phase 6**: Monitor and optimize staging processes

### Benefits of Adding Staging

Once implemented, staging provides:

- **Risk Reduction**: Catch production-specific issues early
- **Stakeholder Confidence**: Business users can review features safely
- **Performance Validation**: Test under realistic conditions
- **Deployment Practice**: Rehearse production deployments
- **Integration Testing**: Validate complex system interactions
- **User Training**: Train users on new features before production release

This phased approach allows you to start simple with direct development-to-production deployment and add staging when your needs and resources justify the additional complexity.