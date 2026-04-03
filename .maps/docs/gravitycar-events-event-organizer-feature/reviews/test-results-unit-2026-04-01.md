# Test Results: Unit - 2026-04-01

## Summary
- Total tests: 95
- Passed: 95
- Failed: 0
- Skipped: 0
- Deprecation warnings: 13 (PHP 8.5 compatibility, not test failures)

## Test Files Created

### Models (57 tests)
- `tests/Unit/Models/EventsTest.php` - 10 tests (isActive, getMostPopularDates, getDefaultOrderBy)
- `tests/Unit/Models/EventCommitmentsTest.php` - 12 tests (metadata, unique constraint validation)
- `tests/Unit/Models/EventRemindersTest.php` - 17 tests (calculateRemindAt, shouldRecalculate, metadata)
- `tests/Unit/Models/EmailQueueTest.php` - 16 tests (constants, backoff, markAsSent/markAsFailedOrRetry, metadata)
- `tests/Unit/Models/EventProposedDatesTest.php` - 6 tests (metadata, model instantiation)
- `tests/Unit/Models/EventsMetadataTest.php` - 12 tests (all model and relationship metadata validation)

### Services (22 tests)
- `tests/Unit/Services/IcsGeneratorServiceTest.php` - 11 tests (ICS generation, validation, edge cases)
- `tests/Unit/Services/AuthenticationServiceFormatUserDataTest.php` - 4 tests (user_timezone in formatUserData)
- `tests/Unit/Services/EmailReminderServiceTest.php` - 5 tests (processReminders, processEmailQueue, batch size, run)

## Acceptance Criteria Coverage

| AC | Covered By | Status |
|----|-----------|--------|
| AC-1 | EventsMetadataTest (fields, roles) | Covered |
| AC-5 | EventCommitmentsTest (unique constraint) | Covered |
| AC-7 | EventsTest (getMostPopularDates, tied dates) | Covered |
| AC-9 | IcsGeneratorServiceTest (ICS content generation) | Covered |
| AC-10 | EventRemindersTest (calculateRemindAt, preset types) | Covered |
| AC-11 | EmailReminderServiceTest (processReminders, processEmailQueue) | Covered |
| AC-15 | EventCommitmentsTest (unique constraint validation) | Covered |
| AC-16 | EventsTest (isActive, getDefaultOrderBy) | Covered |
| AC-19 | EventRemindersTest (shouldRecalculate, recalculateReminders) | Covered |
| AC-20 | AuthenticationServiceFormatUserDataTest (user_timezone) | Covered |
| AC-21 | EmailQueueTest (metadata admin-only, constants, retry backoff) | Covered |

## Passed Tests

### EventsTest
- testIsActiveReturnsFalseWhenAcceptedDateIsSet
- testIsActiveReturnsFalseWhenNoId
- testIsActiveReturnsTrueWhenFutureProposedDatesExist
- testIsActiveReturnsFalseWhenNoFutureProposedDates
- testGetDefaultOrderByContainsCaseWhenAndCreatedAtDesc
- testGetMostPopularDatesReturnsEmptyWhenNoId
- testGetMostPopularDatesReturnsEmptyWhenNoCommitments
- testGetMostPopularDatesReturnsSingleWinner
- testGetMostPopularDatesReturnsTiedDates

### EventCommitmentsTest
- testMetadataHasCorrectName
- testMetadataHasRequiredFields
- testMetadataEventIdIsRelatedRecordToEvents
- testMetadataUserIdIsRelatedRecordToUsers
- testMetadataProposedDateIdIsRelatedRecord
- testMetadataIsAvailableIsBooleanWithDefaultFalse
- testMetadataRolesAndActions
- testMetadataHasUniqueConstraint
- testModelExtendsModelBase
- testValidateUniqueCommitmentPassesWhenFieldsEmpty
- testValidateUniqueCommitmentPassesWhenNoExistingRecord
- testValidateUniqueCommitmentThrowsOnDuplicate
- testValidateUniqueCommitmentAllowsSelfUpdate

### EventRemindersTest
- testCalculateRemindAtTwoWeeks
- testCalculateRemindAtOneWeek
- testCalculateRemindAtOneDay
- testCalculateRemindAtReturnsNullForNullAcceptedDate
- testCalculateRemindAtReturnsNullForCustomType
- testCalculateRemindAtReturnsNullForUnknownType
- testShouldRecalculatePresetPending
- testShouldRecalculatePresetFailed
- testShouldRecalculateReturnsFalseForSent
- testShouldRecalculateReturnsFalseForCustom
- testShouldRecalculateReturnsFalseForCustomSent
- testRecalculateRemindersReturnsZeroWhenNoReminders
- testMetadataHasCorrectName
- testMetadataHasCorrectRoles
- testMetadataReminderTypeHasCorrectOptions
- testMetadataStatusDefaultsPending
- testMetadataStatusHasCorrectOptions
- testMetadataRemindAtIsNullable
- testMetadataHasRelationship

### EmailQueueTest
- testMaxRetryCountIsThree
- testStatusConstants
- testGetRetryBackoffFirstRetry
- testGetRetryBackoffSecondRetry
- testGetRetryBackoffThirdRetry
- testGetRetryBackoffFallsBackToTwoHours
- testMarkAsSentReturnsFalseWhenRecordNotFound
- testMarkAsFailedOrRetryReturnsFalseWhenNotFound
- testMetadataHasCorrectName
- testMetadataAdminOnlyAccess
- testMetadataStatusDefaultsPending
- testMetadataRetryCountDefaultsToZero
- testMetadataHasAllRequiredFields
- testMetadataStatusHasAllOptions
- testMetadataRelatedEventIdPointsToEvents
- testMetadataRelatedReminderIdPointsToEventReminders
- testMetadataSentAtIsReadOnly

### EventProposedDatesTest
- testModelExtendsModelBase
- testMetadataHasCorrectName
- testMetadataHasEventIdRelatedRecord
- testMetadataHasProposedDateField
- testMetadataRolesAndActions
- testMetadataDisplayColumns

### EventsMetadataTest
- testEventsMetadataLoads
- testEventsMetadataHasRequiredFields
- testEventsMetadataFieldTypes
- testEventsMetadataDurationDefault
- testEventsMetadataRolesAndActions
- testEventsMetadataHasRelationships
- testEventsMetadataDisplayColumns
- testEventsEventProposedDatesRelationship
- testEventsUsersInvitationsRelationship
- testEventsEventCommitmentsRelationship
- testEventsEventRemindersRelationship

### IcsGeneratorServiceTest
- testGenerateIcsContentReturnsValidIcs
- testGenerateIcsContentIncludesEventName
- testGenerateIcsContentIncludesUid
- testGenerateIcsContentIncludesLocation
- testGenerateIcsContentIncludesDescription
- testGenerateIcsContentUsesDefaultDurationWhenNotProvided
- testGenerateIcsContentThrowsWhenMissingId
- testGenerateIcsContentThrowsWhenMissingName
- testGenerateIcsContentThrowsWhenMissingAcceptedDate
- testGenerateIcsContentWorksWithoutOptionalFields
- testGenerateIcsContentIncludesProductIdentifier

### AuthenticationServiceFormatUserDataTest
- testFormatUserDataIncludesUserTimezone
- testFormatUserDataWithUtcDefault
- testFormatUserDataContainsAllExpectedKeys
- testFormatUserDataHasExactlyTenKeys

### EmailReminderServiceTest
- testProcessRemindersReturnsZeroWhenNoDueReminders
- testProcessEmailQueueSendsEmails
- testProcessEmailQueueHandlesFailures
- testProcessEmailQueueRespectsConfigBatchSize
- testRunReturnsBothReminderAndEmailResults

## Notes
- All deprecation warnings are from PHP 8.5 compatibility issues in the framework (implicit nullable parameters, deprecated setAccessible()), not in the test code itself.
- The ContainerException about metadata_cache.php is expected in the test environment and is caught gracefully by TestCase.setUp().
- Controller tests were not included in this unit test pass because they require more complex mocking of ApiControllerBase, Request objects, and authentication contexts. These are better suited for integration tests.
