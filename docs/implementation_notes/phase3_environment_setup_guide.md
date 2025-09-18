# Phase 3 User Action Guide: Environment Protection Setup

## Overview
This guide walks you through setting up GitHub environment protection rules for the production deployment workflow.

## Required Actions

### 1. Create Production Environment

1. **Navigate to GitHub Settings**:
   - Go to: `https://github.com/gravitycar/gravitycar_no_code/settings/environments`

2. **Create Environment**:
   - Click "New environment"
   - Name: `production`
   - Click "Configure environment"

### 2. Configure Environment Protection Rules

In the production environment configuration page:

#### Required Reviewers (Recommended)
- ✅ Check "Required reviewers"
- Add your GitHub username as a required reviewer
- This ensures manual approval before production deployments

#### Wait Timer (Optional)
- ⚠️ Consider adding a wait timer (e.g., 5 minutes) for additional safety
- Allows time to cancel accidental deployments

#### Deployment Branches (Recommended)
- ✅ Restrict deployments to specific branches
- Add `main` branch as allowed deployment source
- This prevents accidental deployments from feature branches

### 3. Environment Variables (If Needed)

If you want environment-specific variables:
- Add any production-specific environment variables
- These override repository secrets for this environment

### 4. Save Configuration

- Click "Save protection rules"
- The environment is now protected

## Testing the Setup

### Test Manual Deployment Trigger

1. **Via GitHub Web Interface**:
   - Go to: `https://github.com/gravitycar/gravitycar_no_code/actions`
   - Click "Deploy Gravitycar Framework to Production"
   - Click "Run workflow"
   - Fill in the form:
     - Environment: `production`
     - Confirmation: `DEPLOY`
     - Git reference: `main` (or leave default)
     - Dry run: `true` (for testing)
   - Click "Run workflow"

2. **Expected Behavior**:
   - Workflow should start
   - If reviewers are required, you'll need to approve
   - Dry run should complete successfully without actual deployment

### Test via GitHub CLI (Alternative)

```bash
# Install GitHub CLI if not already installed
# Ubuntu: sudo apt install gh
# macOS: brew install gh

# Authenticate (if not already done)
gh auth login

# Trigger deployment
gh workflow run deploy.yml \
  --field environment=production \
  --field confirmation=DEPLOY \
  --field dry_run=true
```

## Security Benefits

With these protection rules:
- ✅ Prevents accidental deployments
- ✅ Requires explicit approval for production changes  
- ✅ Logs all deployment actions and approvers
- ✅ Restricts deployments to stable branches
- ✅ Provides audit trail for compliance

## Troubleshooting

### Common Issues

**1. "Environment not found" error**:
- Verify environment name is exactly `production`
- Check that environment is properly saved

**2. "Required reviewers" blocking deployment**:
- This is expected behavior
- Approve the deployment in the GitHub Actions interface

**3. "Branch not allowed" error**:
- Check deployment branch restrictions
- Ensure you're deploying from `main` branch

## Next Steps

After completing environment setup:
1. ✅ Test dry run deployment
2. ✅ Verify all scripts are working
3. ✅ Review deployment logs
4. ✅ Proceed with actual deployment when ready

---

**Status**: Environment protection setup complete  
**Next Phase**: Ready for production deployment testing