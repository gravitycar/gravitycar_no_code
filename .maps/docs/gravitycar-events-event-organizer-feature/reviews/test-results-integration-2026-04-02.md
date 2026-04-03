# Test Results: Integration - 2026-04-02

## Summary
- Total tests: 54
- Passed: 54
- Failed: 0
- Skipped: 0

## Test Files Created
1. `Tests/Integration/Api/Events/EventApiTestCase.php` - Base test case with shared mocks and helpers
2. `Tests/Integration/Api/Events/ChartAPIControllerTest.php` - 6 tests
3. `Tests/Integration/Api/Events/CommitmentsAPIControllerTest.php` - 8 tests
4. `Tests/Integration/Api/Events/AcceptedDateAPIControllerTest.php` - 7 tests
5. `Tests/Integration/Api/Events/MostPopularDateAPIControllerTest.php` - 7 tests
6. `Tests/Integration/Api/Events/IcsExportAPIControllerTest.php` - 7 tests
7. `Tests/Integration/Api/Events/SmartRouteAPIControllerTest.php` - 8 tests
8. `Tests/Integration/Api/Events/EventAccessTraitTest.php` - 8 tests (+stub class)

## Acceptance Criteria Coverage

| AC | Description | Test Coverage |
|----|-------------|---------------|
| AC-4 | Chart displays grid data correctly | ChartAPIControllerTest: admin data, guest data, commitments indexing |
| AC-5 | User can toggle availability | CommitmentsAPIControllerTest: upsert create/update |
| AC-6 | Accept All marks all dates available | CommitmentsAPIControllerTest: acceptAll, empty proposed dates |
| AC-7 | Most Popular Date with ties | MostPopularDateAPIControllerTest: single, tied, empty |
| AC-8 | Admin sets accepted date | AcceptedDateAPIControllerTest: admin set, reminder recalc |
| AC-9 | ICS export with accepted date | IcsExportAPIControllerTest: admin export, no accepted_date 404 |
| AC-13 | Smart routing | SmartRouteAPIControllerTest: single/multi/zero events, guest |
| AC-14 | Guest read-only access | ChartAPIControllerTest: guest access; MostPopularDateAPIControllerTest: guest access |
| AC-15 | Users cannot modify others' commitments | CommitmentsAPIControllerTest: uninvited user denied, guest denied |
| AC-19 | Reminder recalculation on accepted_date change | AcceptedDateAPIControllerTest: recalc count, recalc failure graceful |

## Passed Tests

### ChartAPIControllerTest
- testRouteRegistration
- testGetChartAsAdminReturnsFullData
- testGetChartAsGuestReturnsReadOnlyData
- testGetChartDeniesUninvitedUser
- testGetChartThrowsNotFoundForMissingEvent
- testCommitmentsIndexedByUserAndDate

### CommitmentsAPIControllerTest
- testRouteRegistration
- testUpsertCommitmentsCreatesNewRecords
- testUpsertCommitmentsUpdatesExistingRecord
- testUpsertCommitmentsDeniesGuest
- testUpsertCommitmentsDeniesUninvitedUser
- testUpsertCommitmentsRejectsEmptyArray
- testUpsertCommitmentsRejectsMissingProposedDateId
- testAcceptAllMarksAllDatesAvailable
- testAcceptAllWithNoProposedDatesReturnsZero

### AcceptedDateAPIControllerTest
- testRouteRegistration
- testSetAcceptedDateAsAdmin
- testSetAcceptedDateDeniesRegularUser
- testSetAcceptedDateDeniesGuest
- testSetAcceptedDateThrowsNotFoundForMissingEvent
- testSetAcceptedDateRejectsMissingProposedDateId
- testSetAcceptedDateRejectsInvalidProposedDate
- testReminderRecalcFailureDoesNotFailRequest

### MostPopularDateAPIControllerTest
- testRouteRegistration
- testReturnsSingleMostPopularDate
- testReturnsTiedDates
- testReturnsEmptyWhenNoCommitments
- testGuestCanAccessMostPopularDate
- testDeniesUninvitedAuthenticatedUser
- testThrowsNotFoundForMissingEvent

### IcsExportAPIControllerTest
- testRouteRegistration
- testIcsExportAsAdminWithAcceptedDate
- testIcsExportReturns404WhenNoAcceptedDate
- testIcsExportDeniesGuest
- testIcsExportDeniesUninvitedUser
- testIcsExportThrowsNotFoundForMissingEvent
- testRolesAndActionsExcludeGuest

### SmartRouteAPIControllerTest
- testRouteRegistration
- testGuestRedirectsToEventsList
- testSingleUpcomingEventRedirectsToChart
- testMultipleUpcomingEventsRedirectsToList
- testNoUpcomingEventsRedirectsToList
- testPastEventsNotCountedAsUpcoming
- testEventWithFutureProposedDateIsUpcoming
- testResponseIncludesTimestamp

### EventAccessTraitTest
- testValidateCommitmentAccessForInvitedUser
- testValidateCommitmentAccessForAdmin
- testValidateCommitmentAccessThrowsForMissingEvent
- testValidateCommitmentAccessThrowsForGuest
- testValidateCommitmentAccessThrowsForUninvitedUser
- testIsUserAdminReturnsFalseForMissingUser
- testIsUserAdminReturnsTrueForAdmin
- testIsUserAdminReturnsFalseForRegularUser

## Failed Tests
None.
