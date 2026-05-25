<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;

/**
 * Metadata validation tests for the Projects model.
 *
 * These tests load the metadata array directly (no ServiceLocator needed)
 * and verify structure, field types, RBAC, and UI config match the spec.
 *
 * Acceptance criteria covered:
 *   - AC 1:  Admin can CRUD Projects via GenericCRUD at /Projects
 *   - AC 3:  Guest user can reach projects_showcase without auth (guest RBAC entry)
 *   - AC 20: PHP model and metadata follow CLAUDE.md standards
 *   - AC 21: DB table includes all required columns (via metadata field keys)
 *
 * Spec reference: §2 Projects Metadata File
 */
class ProjectsMetadataTest extends UnitTestCase
{
    private array $metadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metadata = require __DIR__ . '/../../../src/Models/projects/projects_metadata.php';
    }

    // ------------------------------------------------------------------
    // Model identity
    // ------------------------------------------------------------------

    /**
     * @test
     * Metadata 'name' key equals 'Projects'.
     */
    public function testModelNameIsProjects(): void
    {
        $this->assertSame('Projects', $this->metadata['name']);
    }

    /**
     * @test
     * Metadata 'table' key equals 'projects'.
     */
    public function testTableNameIsProjects(): void
    {
        $this->assertSame('projects', $this->metadata['table']);
    }

    /**
     * @test
     * displayColumns contains 'title'.
     */
    public function testDisplayColumnsContainsTitle(): void
    {
        $this->assertContains('title', $this->metadata['displayColumns']);
    }

    // ------------------------------------------------------------------
    // Required top-level keys
    // ------------------------------------------------------------------

    /**
     * @test
     * All required top-level keys are present.
     */
    public function testRequiredTopLevelKeysExist(): void
    {
        foreach (['name', 'table', 'displayColumns', 'fields', 'rolesAndActions', 'ui'] as $key) {
            $this->assertArrayHasKey($key, $this->metadata, "Missing top-level key: {$key}");
        }
    }

    // ------------------------------------------------------------------
    // Fields
    // ------------------------------------------------------------------

    /**
     * @test
     * All required domain fields exist in the 'fields' array.
     */
    public function testRequiredFieldsExist(): void
    {
        $expected = ['title', 'tag_line', 'description', 'screenshot', 'link'];
        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $this->metadata['fields'], "Missing field: {$field}");
        }
    }

    /**
     * @test
     * Field types match the spec exactly.
     */
    public function testFieldTypes(): void
    {
        $this->assertSame('Text', $this->metadata['fields']['title']['type']);
        $this->assertSame('Text', $this->metadata['fields']['tag_line']['type']);
        $this->assertSame('BigText', $this->metadata['fields']['description']['type']);
        $this->assertSame('Image', $this->metadata['fields']['screenshot']['type']);
        $this->assertSame('Link', $this->metadata['fields']['link']['type']);
    }

    /**
     * @test
     * title field: required, maxLength 256.
     */
    public function testTitleFieldSpec(): void
    {
        $field = $this->metadata['fields']['title'];
        $this->assertTrue($field['required']);
        $this->assertSame(256, $field['maxLength']);
    }

    /**
     * @test
     * tag_line field: required, maxLength 1024.
     */
    public function testTagLineFieldSpec(): void
    {
        $field = $this->metadata['fields']['tag_line'];
        $this->assertTrue($field['required']);
        $this->assertSame(1024, $field['maxLength']);
    }

    /**
     * @test
     * description field: required, maxLength 16000.
     */
    public function testDescriptionFieldSpec(): void
    {
        $field = $this->metadata['fields']['description'];
        $this->assertTrue($field['required']);
        $this->assertSame(16000, $field['maxLength']);
    }

    /**
     * @test
     * screenshot field: required, maxLength 500, allowRemote true, allowLocal false.
     */
    public function testScreenshotFieldSpec(): void
    {
        $field = $this->metadata['fields']['screenshot'];
        $this->assertTrue($field['required']);
        $this->assertSame(500, $field['maxLength']);
        $this->assertTrue($field['allowRemote']);
        $this->assertFalse($field['allowLocal']);
    }

    /**
     * @test
     * screenshot field has altText set.
     */
    public function testScreenshotFieldHasAltText(): void
    {
        $this->assertNotEmpty($this->metadata['fields']['screenshot']['altText']);
    }

    /**
     * @test
     * AC 19: link field is NOT required and is nullable (optional URL).
     */
    public function testLinkFieldIsOptionalAndNullable(): void
    {
        $field = $this->metadata['fields']['link'];
        $this->assertFalse($field['required']);
        $this->assertTrue($field['nullable']);
    }

    /**
     * @test
     * link field maxLength is 256.
     */
    public function testLinkFieldMaxLength(): void
    {
        $this->assertSame(256, $this->metadata['fields']['link']['maxLength']);
    }

    // ------------------------------------------------------------------
    // RBAC
    // ------------------------------------------------------------------

    /**
     * @test
     * AC 1, AC 2: admin role has wildcard (*) action.
     */
    public function testAdminRoleHasWildcard(): void
    {
        $this->assertSame(['*'], $this->metadata['rolesAndActions']['admin']);
    }

    /**
     * @test
     * AC 3: guest role has list and read actions (enables public Projects showcase).
     */
    public function testGuestRoleHasListAndRead(): void
    {
        $guestActions = $this->metadata['rolesAndActions']['guest'];
        $this->assertContains('list', $guestActions);
        $this->assertContains('read', $guestActions);
    }

    /**
     * @test
     * user role has list and read actions.
     */
    public function testUserRoleHasListAndRead(): void
    {
        $userActions = $this->metadata['rolesAndActions']['user'];
        $this->assertContains('list', $userActions);
        $this->assertContains('read', $userActions);
    }

    // ------------------------------------------------------------------
    // UI configuration
    // ------------------------------------------------------------------

    /**
     * @test
     * createFields contains all five domain fields.
     */
    public function testCreateFieldsContainsRequiredFields(): void
    {
        $createFields = $this->metadata['ui']['createFields'];
        foreach (['title', 'tag_line', 'description', 'screenshot', 'link'] as $field) {
            $this->assertContains($field, $createFields, "createFields missing: {$field}");
        }
    }

    /**
     * @test
     * editFields contains all five domain fields.
     */
    public function testEditFieldsContainsRequiredFields(): void
    {
        $editFields = $this->metadata['ui']['editFields'];
        foreach (['title', 'tag_line', 'description', 'screenshot', 'link'] as $field) {
            $this->assertContains($field, $editFields, "editFields missing: {$field}");
        }
    }

    /**
     * @test
     * listFields contains title, tag_line, screenshot, and link.
     */
    public function testListFieldsContainsRequiredFields(): void
    {
        $listFields = $this->metadata['ui']['listFields'];
        foreach (['title', 'tag_line', 'screenshot', 'link'] as $field) {
            $this->assertContains($field, $listFields, "listFields missing: {$field}");
        }
    }

    // ------------------------------------------------------------------
    // Required fields appear in createFields (AC 1)
    // ------------------------------------------------------------------

    /**
     * @test
     * AC 1: Every field marked required=true appears in createFields so the
     * admin create form renders all mandatory inputs.
     */
    public function testRequiredFieldsAreInCreateFields(): void
    {
        $createFields = $this->metadata['ui']['createFields'];
        foreach ($this->metadata['fields'] as $fieldKey => $fieldDef) {
            if (!empty($fieldDef['required'])) {
                $this->assertContains(
                    $fieldKey,
                    $createFields,
                    "Required field '{$fieldKey}' is missing from createFields"
                );
            }
        }
    }
}
