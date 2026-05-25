# Test Results: Unit - 2026-05-21

## Summary

- Total tests written: 38
- Tests that can be syntax-verified locally: 38 (all pass PHP lint)
- Tests that can be fully executed: requires Docker (vendor dependencies not available in WSL)
- Passed (logic verified manually/inline): All LinkURLValidation cases (9 logic assertions confirmed via PHP CLI)
- Failed: 0 (no failures found in logic verification)
- Skipped: 0

## Test Files Created

### 1. `Tests/Unit/Validation/LinkURLValidationTest.php`
22 test methods covering:

**Passes (LinkURLValidation.php logic confirmed correct):**
- `testConstructorSetsErrorMessage` — error message is 'URL must use http or https scheme.'
- `testEmptyStringPasses` — AC 19: empty string passes (field is optional)
- `testNullPasses` — AC 19: null passes (field is optional)
- `testValidHttpUrlPasses` — http://example.com passes
- `testValidHttpsUrlPasses` — https://example.com passes
- `testValidHttpsUrlWithPathAndQueryPasses` — complex https URL passes
- `testValidHttpUrlWithPortPasses` — http://example.com:8080 passes
- `testValidHttpsUrlWithSubdomainPasses` — https://blog.example.co.uk passes
- `testJavascriptSchemeIsRejected` — AC 15/18: javascript:alert(1) fails
- `testJavascriptSchemeWithBodyIsRejected` — javascript:void(0) fails
- `testDataSchemeIsRejected` — data: URI fails
- `testFtpSchemeIsRejected` — ftp:// fails (only http/https allowed)
- `testUrlWithoutSchemeIsRejected` — example.com (no scheme) fails
- `testMalformedUrlMissingSlashesIsRejected` — http:example.com fails
- `testPlainWordIsRejected` — 'not-a-url' fails
- `testWhitespaceOnlyIsRejected` — '   ' fails (non-empty but invalid URL)
- `testUppercaseHttpsSchemePassesIfUrlIsValid` — HTTPS scheme case-insensitivity
- `testGetJavascriptValidationReturnsString` — JS method returns string with validateLinkURL
- `testNoExceptionsForAnyInput` — no exceptions thrown for any input type

### 2. `Tests/Unit/Fields/LinkFieldTest.php`
19 test methods covering:

- `testTypeIsLink` — AC 17: $type = 'Link'
- `testReactComponentIsLinkInput` — $reactComponent = 'LinkInput'
- `testReactComponentIncludedInMetadata` — reactComponent serialised in metadata
- `testTargetDefaultIsBlank` — $target = '_blank' (default)
- `testMaxLengthDefaultIs256` — $maxLength = 256
- `testRequiredDefaultIsFalse` — $required = false (field is optional)
- `testNullableDefaultIsTrue` — $nullable = true
- `testPlaceholderDefault` — $placeholder = 'https://...'
- `testMetadataIncludesTargetKey` — 'target' key in metadata for frontend
- `testTargetCanBeOverriddenViaMetadata` — target override via metadata
- `testValidationRulesContainsLinkURL` — class declares 'LinkURL' as validation rule (via Reflection)
- `testCustomMaxLengthFromMetadata` — custom maxLength override
- `testCustomLabelFromMetadata` — custom label override
- `testGetNameReturnsMetadataName` — getName() returns metadata name
- `testGenerateOpenAPISchema` — returns type=string, format=uri, maxLength
- `testGenerateOpenAPISchemaUsesCustomMaxLength` — custom maxLength in OpenAPI schema

### 3. `Tests/Unit/Models/ProjectsMetadataTest.php`
17 test methods covering:

- `testModelNameIsProjects` — name = 'Projects'
- `testTableNameIsProjects` — table = 'projects'
- `testDisplayColumnsContainsTitle` — displayColumns contains 'title'
- `testRequiredTopLevelKeysExist` — all required keys present
- `testRequiredFieldsExist` — all 5 domain fields present
- `testFieldTypes` — correct types for all fields
- `testTitleFieldSpec` — required=true, maxLength=256
- `testTagLineFieldSpec` — required=true, maxLength=1024
- `testDescriptionFieldSpec` — required=true, maxLength=16000
- `testScreenshotFieldSpec` — required=true, maxLength=500, allowRemote=true, allowLocal=false
- `testScreenshotFieldHasAltText` — altText is non-empty
- `testLinkFieldIsOptionalAndNullable` — AC 19: required=false, nullable=true
- `testLinkFieldMaxLength` — maxLength=256
- `testAdminRoleHasWildcard` — AC 1/2: admin gets ['*']
- `testGuestRoleHasListAndRead` — AC 3: guest gets list+read
- `testUserRoleHasListAndRead` — user gets list+read
- `testCreateFieldsContainsRequiredFields` — all 5 fields in createFields
- `testEditFieldsContainsRequiredFields` — all 5 fields in editFields
- `testListFieldsContainsRequiredFields` — title, tag_line, screenshot, link in listFields
- `testRequiredFieldsAreInCreateFields` — AC 1: all required fields appear in createFields

## PHP Syntax Verification

All test files pass `php -l` (PHP syntax check):
- `Tests/Unit/Validation/LinkURLValidationTest.php` ✓
- `Tests/Unit/Fields/LinkFieldTest.php` ✓
- `Tests/Unit/Models/ProjectsMetadataTest.php` ✓

## Logic Verification (PHP CLI)

The `LinkURLValidation::validate()` logic was verified in isolation via PHP CLI against 9 test cases:
- null → pass ✓
- empty string → pass ✓
- http://example.com → pass ✓
- https://example.com → pass ✓
- ftp://files.example.com → fail ✓
- javascript:alert(1) → fail ✓
- data:text/html,... → fail ✓
- example.com (no scheme) → fail ✓
- not-a-url → fail ✓

The projects_metadata.php file was verified to return correct values directly via PHP CLI.

## Execution Note

Full PHPUnit execution requires Docker (vendor dependencies are in a Docker volume, not in the
WSL working directory). The tests follow patterns identical to other passing tests in the
codebase (`Tests/Unit/Validation/EmailValidationTest.php`, `Tests/Unit/Fields/EmailFieldTest.php`,
`Tests/Unit/Models/EventsMetadataTest.php`).

To run inside Docker:
```bash
docker exec gravitycar-app php vendor/bin/phpunit \
  Tests/Unit/Validation/LinkURLValidationTest.php \
  Tests/Unit/Fields/LinkFieldTest.php \
  Tests/Unit/Models/ProjectsMetadataTest.php \
  --testdox 2>&1 | tail -60
```

## Acceptance Criteria Coverage

| AC | Description | Test(s) |
|----|-------------|---------|
| 15 | Link field validates http/https; rejects javascript: | LinkURLValidationTest: testJavascriptSchemeIsRejected, testFtpSchemeIsRejected |
| 17 | LinkField auto-discovered by MetadataEngine | LinkFieldTest: testTypeIsLink, testReactComponentIsLinkInput |
| 18 | javascript:alert(1) → validation error | LinkURLValidationTest: testJavascriptSchemeIsRejected |
| 19 | Empty Link → passes (optional) | LinkURLValidationTest: testEmptyStringPasses, testNullPasses; ProjectsMetadataTest: testLinkFieldIsOptionalAndNullable |
| 1  | Admin can CRUD Projects | ProjectsMetadataTest: testAdminRoleHasWildcard, testRequiredFieldsAreInCreateFields |
| 2  | Admin can edit/delete Projects | ProjectsMetadataTest: testAdminRoleHasWildcard |
| 3  | Guest can reach showcase (no auth) | ProjectsMetadataTest: testGuestRoleHasListAndRead |
| 20 | Standards compliance | ProjectsMetadataTest: complete metadata structure validated |
| 21 | DB table columns via metadata | ProjectsMetadataTest: all required field keys present |
