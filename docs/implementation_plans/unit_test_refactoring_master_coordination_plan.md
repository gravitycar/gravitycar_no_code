# Unit Test Refactoring - Master Coordination Plan

## Overview
This document coordinates the parallel development effort to fix all 115 failing unit tests in the Gravitycar Framework. The work has been divided into 4 independent developer tracks to minimize git conflicts.

## Developer Assignments & Progress Tracking

### Developer A: Router & Core Infrastructure
**Plan:** `unit_test_refactoring_plan_developer_a_router_infrastructure.md`
**Estimated Time:** 4-6 hours
**Status:** 🟡 Ready to Start

**Assigned Tests:**
- RouterTest.php (28 tests) - Constructor now requires 9 parameters
- DatabaseConnectorTest.php (20 tests) - Constructor now requires Config object  
- RelationshipBaseDatabaseTest.php (9 tests) - Inherits DatabaseTestCase fix
- **Total: 57 tests**

**Key Deliverables:**
- [ ] Fix DatabaseTestCase.php (impacts multiple other tests)
- [ ] Update Router constructor mocking (9 dependencies)
- [ ] Verify relationship tests auto-resolve

**Git Files:**
- `Tests/Unit/Api/RouterTest.php`
- `Tests/Unit/Database/DatabaseConnectorTest.php`
- `Tests/Unit/DatabaseTestCase.php` ⚠️ **SHARED FILE** - coordinate changes
- `Tests/Unit/Relationships/RelationshipBaseDatabaseTest.php`

## Summary & Accomplishments

### ✅ Completed Developers (2/4)

**Developer B: API Controllers** (72 tests fixed)
- Fixed dependency injection issues in all API controller tests
- Updated constructor parameters for proper type hinting
- All AuthController, MetadataAPIController, TMDBController, HealthAPIController, and ModelBaseAPIController tests passing
- Established dependency injection patterns for API controllers

**Developer D: External Services** (2 tests fixed, 4 new tests added)
- Implemented HTTP mocking for TMDBApiService to eliminate external API dependencies
- Created TestableHTTPTMDBApiService with comprehensive mocking capabilities
- Added realistic test data and error scenario coverage
- All TMDB external service tests now pass without real HTTP calls

### 🔄 Current Test Status
- **Total Tests**: 1,112 (increased from 1,108)
- **Passing**: 1,053 (94.7% pass rate)
- **Errors**: 59 (reduced from 61)
- **Failures**: 5 (unchanged)
- **Skipped**: 13 (unchanged)

### 🎯 Next Priority: Developer A (Router Infrastructure)
Developer A has the most critical foundation work that blocks Developer C's progress.

### Developer B - API Controllers Status ✅ COMPLETED
- **Assigned Tests**: 72 tests - AuthController (12), MetadataAPIController (10), TMDBController (6), HealthAPIController (21), ModelBaseAPIController (23)
- **Status**: ✅ COMPLETED - All API controller tests now passing with proper dependency injection  
- **Progress Tracker**: All fixes implemented successfully
- **Timeline**: Completed ahead of schedule
- **Point of Contact**: Lead developer completed dependency injection

### Developer D - External Services Status ✅ COMPLETED
- **Assigned Tests**: 2 failing tests - TMDBApiServiceTest::testSearchMoviesWithValidQuery, TMDBApiServiceTest::testGetMovieDetailsWithValidId
- **Status**: ✅ COMPLETED - All external service tests now use HTTP mocking instead of real API calls
- **Progress Tracker**: HTTP mocking implemented, realistic test data created, error scenarios covered
- **Timeline**: Completed successfully
- **Point of Contact**: Lead developer completed HTTP mocking patterns### Developer C: ModelFactory & Models
**Plan:** `unit_test_refactoring_plan_developer_c_modelfactory_models.md`
**Estimated Time:** 9-10 hours
**Status:** 🟡 Ready to Start (GuestUserManager tests depend on Developer A)

**Assigned Tests:**
- ModelFactoryTest.php (10 tests) - Static to instance method conversion
- ManyToManyRelationshipTest.php (1 test) - Missing dependencies
- RelationshipBaseRemoveMethodTest.php (1 test) - ModelBase dependencies
- GuestUserManagerEdgeCaseTest.php (9 tests) - DatabaseTestCase dependency
- GuestUserManagerIntegrationTest.php (9 tests) - DatabaseTestCase dependency
- **Total: 30 tests**

**Key Deliverables:**
- [ ] Convert all ModelFactory static calls to instance calls
- [ ] Setup ModelBase dependency injection patterns
- [ ] Wait for Developer A to fix DatabaseTestCase before starting GuestUserManager
- [ ] Create reusable model dependency injection helpers

**Dependencies:** 
- 🔗 **Depends on Developer A** completing DatabaseTestCase.php fix

**Git Files:**
- `Tests/Unit/Factories/ModelFactoryTest.php`
- `Tests/Unit/Relationships/ManyToManyRelationshipTest.php`
- `Tests/Unit/Relationships/RelationshipBaseRemoveMethodTest.php`
- `Tests/Unit/Utils/GuestUserManagerEdgeCaseTest.php`
- `Tests/Unit/Utils/GuestUserManagerIntegrationTest.php`

### Developer D: External Services & Integration
**Plan:** `unit_test_refactoring_plan_developer_d_external_services.md`
**Estimated Time:** 6-10 hours  
**Status:** 🟡 Ready to Start

**Assigned Tests:**
- TMDBApiServiceTest.php (2 tests) - Making real HTTP calls instead of mocks
- Cleanup & coordination (remaining issues)
- **Total: 2+ tests**

**Key Deliverables:**
- [ ] Mock TMDB API HTTP calls instead of real requests
- [ ] Establish external service testing patterns
- [ ] Monitor for additional issues after other developers complete
- [ ] Ensure 100% test pass rate

**Git Files:**
- `Tests/Unit/TMDBApiServiceTest.php`
- Any additional files with remaining issues

## Coordination Protocol

### Git Branch Strategy
```bash
# Create feature branches for each developer
git checkout -b fix-router-infrastructure        # Developer A
git checkout -b fix-api-controllers              # Developer B  
git checkout -b fix-modelfactory-models          # Developer C
git checkout -b fix-external-services            # Developer D
```

### Communication Points

#### Daily Standup Topics
- Progress on assigned test files
- Any blocking dependencies discovered
- Shared patterns or utilities that could help other developers

#### Merge Coordination
1. **Developer A merges first** - DatabaseTestCase.php fix unblocks Developer C
2. **Developer B and D can merge independently** 
3. **Developer C merges after A** - GuestUserManager tests need DatabaseTestCase fix
4. **Final merge to main** after all developers complete

### Shared Resources

#### DatabaseTestCase.php ⚠️ 
**Owner:** Developer A
**Impact:** Developer C's GuestUserManager tests  
**Coordination:** Developer C waits for Developer A's fix before starting GuestUserManager work

#### Common Patterns to Share
- **Dependency injection setup patterns** (all developers)
- **Mock service configuration** (Developers B & D)
- **ModelBase dependency injection** (Developer C, may help others)

### Testing Verification

#### Individual Developer Testing
```bash
# Each developer tests their own files
vendor/bin/phpunit Tests/Unit/Api/RouterTest.php                    # Developer A
vendor/bin/phpunit Tests/Unit/Api/AuthControllerTest.php            # Developer B
vendor/bin/phpunit Tests/Unit/Factories/ModelFactoryTest.php        # Developer C
vendor/bin/phpunit Tests/Unit/TMDBApiServiceTest.php                # Developer D
```

#### Integration Testing
```bash
# After all developers complete - test full suite
vendor/bin/phpunit Tests/Unit/
```

#### Success Criteria
- [ ] All 1108 unit tests pass
- [ ] No remaining "Call to member function on null" errors
- [ ] No remaining static method call errors
- [ ] No remaining constructor parameter mismatches
- [ ] Proper dependency injection patterns throughout

## Risk Mitigation

### Potential Conflicts
1. **DatabaseTestCase.php** - Only Developer A should modify
2. **Shared utility methods** - Coordinate through chat/documentation
3. **Container/DI configuration changes** - Avoid unless absolutely necessary

### Rollback Plan
Each developer maintains their own feature branch:
```bash
# If issues arise, rollback individual features
git checkout main
git branch -D fix-problematic-feature
```

### Testing Contingency
If combined changes cause new failures:
1. Merge branches one at a time
2. Test after each merge
3. Identify and fix integration issues immediately

## Timeline & Milestones

### Phase 1: Individual Development (Parallel)
- **Week 1:** All developers working on their assigned test files
- **Checkpoint:** Daily progress updates
- **Blocker Resolution:** Developer A prioritizes DatabaseTestCase.php for Developer C

### Phase 2: Integration & Testing
- **Week 2 Start:** Begin merging completed branches
- **Integration Testing:** Full test suite verification
- **Bug Fixes:** Address any integration issues

### Phase 3: Final Verification  
- **Week 2 End:** Complete test suite passes
- **Documentation:** Update test patterns and standards
- **Celebration:** 🎉 All 1108 tests passing!

## Success Metrics
- **Primary:** 1108/1108 unit tests passing
- **Secondary:** Improved test maintainability with proper DI patterns
- **Tertiary:** Reusable test patterns for future development

## Contact & Escalation
- **Technical Issues:** Post in developer chat with @all
- **Blocking Dependencies:** Direct message dependent developer
- **Merge Conflicts:** Escalate to tech lead for resolution
- **Timeline Concerns:** Update project manager immediately

---

**Generated:** September 15, 2025  
**Last Updated:** September 15, 2025  
**Status:** Ready for Development Assignment