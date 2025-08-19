<?php

namespace Tests\Unit\Api;

use Gravitycar\Api\FilterCriteria;
use Gravitycar\Models\ModelBase;
use Gravitycar\Fields\TextField;
use Gravitycar\Fields\IntegerField;
use Gravitycar\Fields\EnumField;
use Gravitycar\Fields\PasswordField;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class FilterCriteriaTest extends TestCase
{
    private FilterCriteria $filterCriteria;
    private MockObject $mockModel;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create FilterCriteria instance
        $this->filterCriteria = new FilterCriteria();
        
        // Mock ModelBase
        $this->mockModel = $this->createMock(ModelBase::class);
    }

    public function testValidateAndFilterForModelWithValidTextFieldFilter(): void
    {
        // Create real TextField instance with proper metadata
        $textField = new TextField([
            'name' => 'name',
            'label' => 'Name',
            'isDBField' => true
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn(['name' => $textField]);
        
        $filters = [
            [
                'field' => 'name',
                'operator' => 'contains',
                'value' => 'john'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        $this->assertCount(1, $validatedFilters);
        $this->assertEquals('contains', $validatedFilters[0]['operator']);
        $this->assertEquals('john', $validatedFilters[0]['value']);
        $this->assertEquals('name', $validatedFilters[0]['field']);
        $this->assertEquals(TextField::class, $validatedFilters[0]['fieldType']);
    }

    public function testValidateAndFilterForModelWithInvalidOperatorForFieldType(): void
    {
        // Create real IntegerField instance (doesn't support 'contains')
        $integerField = new IntegerField([
            'name' => 'age',
            'label' => 'Age',
            'isDBField' => true
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn(['age' => $integerField]);
        
        $filters = [
            [
                'field' => 'age',
                'operator' => 'contains', // Invalid for IntegerField
                'value' => '25'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        // Should be filtered out due to invalid operator
        $this->assertCount(0, $validatedFilters);
    }

    public function testValidateAndFilterForModelWithValidIntegerFieldFilter(): void
    {
        // Create real IntegerField instance
        $integerField = new IntegerField([
            'name' => 'age',
            'label' => 'Age',
            'isDBField' => true
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn(['age' => $integerField]);
        
        $filters = [
            [
                'field' => 'age',
                'operator' => 'greaterThanOrEqual', // Use correct operator name
                'value' => '18'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        $this->assertCount(1, $validatedFilters);
        $this->assertEquals('greaterThanOrEqual', $validatedFilters[0]['operator']);
        $this->assertEquals('age', $validatedFilters[0]['field']);
        $this->assertEquals(IntegerField::class, $validatedFilters[0]['fieldType']);
    }

    public function testValidateAndFilterForModelWithNonExistentField(): void
    {
        // Setup mock model without the requested field
        $this->mockModel->method('getFields')->willReturn([]);
        
        $filters = [
            [
                'field' => 'nonexistent',
                'operator' => 'equals',
                'value' => 'test'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        // Should be filtered out due to non-existent field
        $this->assertCount(0, $validatedFilters);
    }

    public function testValidateAndFilterForModelWithNonDBField(): void
    {
        // Create field that is not a database field
        $nonDBField = new TextField([
            'name' => 'computed',
            'label' => 'Computed Field',
            'isDBField' => false
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn(['computed' => $nonDBField]);
        
        $filters = [
            [
                'field' => 'computed',
                'operator' => 'equals',
                'value' => 'test'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        // Should be filtered out because field is not a database field
        $this->assertCount(0, $validatedFilters);
    }

    public function testValidateAndFilterForModelWithPasswordField(): void
    {
        // Create real PasswordField instance (very limited operators)
        $passwordField = new PasswordField([
            'name' => 'password',
            'label' => 'Password',
            'isDBField' => true
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn(['password' => $passwordField]);
        
        $filters = [
            [
                'field' => 'password',
                'operator' => 'contains', // Not allowed for PasswordField
                'value' => 'secret'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        // Should be filtered out due to security restrictions on PasswordField
        $this->assertCount(0, $validatedFilters);
    }

    public function testValidateAndFilterForModelWithEnumFieldValidValue(): void
    {
        // Create real EnumField instance
        $enumField = new EnumField([
            'name' => 'role',
            'label' => 'Role',
            'isDBField' => true,
            'options' => ['admin', 'user', 'moderator']
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn(['role' => $enumField]);
        
        $filters = [
            [
                'field' => 'role',
                'operator' => 'equals',
                'value' => 'admin'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        
        $this->assertCount(1, $validatedFilters);
        $this->assertEquals('equals', $validatedFilters[0]['operator']);
        $this->assertEquals('admin', $validatedFilters[0]['value']);
        $this->assertEquals('role', $validatedFilters[0]['field']);
    }

    public function testGetSupportedFiltersForModel(): void
    {
        // Create multiple field types
        $textField = new TextField([
            'name' => 'name',
            'label' => 'Name',
            'isDBField' => true
        ]);
        
        $integerField = new IntegerField([
            'name' => 'age',
            'label' => 'Age',
            'isDBField' => true
        ]);
        
        $enumField = new EnumField([
            'name' => 'role',
            'label' => 'Role',
            'isDBField' => true,
            'options' => ['admin', 'user']
        ]);
        
        $nonDBField = new TextField([
            'name' => 'computed',
            'label' => 'Computed',
            'isDBField' => false
        ]);
        
        // Setup mock model
        $this->mockModel->method('getFields')->willReturn([
            'name' => $textField,
            'age' => $integerField,
            'role' => $enumField,
            'computed' => $nonDBField
        ]);
        
        $supportedFilters = $this->filterCriteria->getSupportedFilters($this->mockModel);
        
        // Only DB fields should be included
        $this->assertCount(3, $supportedFilters);
        $this->assertArrayHasKey('name', $supportedFilters);
        $this->assertArrayHasKey('age', $supportedFilters);
        $this->assertArrayHasKey('role', $supportedFilters);
        $this->assertArrayNotHasKey('computed', $supportedFilters);
        
        // Check that each field has the required structure
        foreach (['name', 'age', 'role'] as $fieldName) {
            $this->assertArrayHasKey('fieldType', $supportedFilters[$fieldName]);
            $this->assertArrayHasKey('operators', $supportedFilters[$fieldName]);
            $this->assertArrayHasKey('operatorDescriptions', $supportedFilters[$fieldName]);
            $this->assertArrayHasKey('fieldDescription', $supportedFilters[$fieldName]);
        }
        
        // TextField should have string operators
        $this->assertContains('contains', $supportedFilters['name']['operators']);
        $this->assertContains('startsWith', $supportedFilters['name']['operators']);
        
        // IntegerField should have numeric operators but not string operators
        $this->assertContains('greaterThanOrEqual', $supportedFilters['age']['operators']);
        $this->assertContains('lessThanOrEqual', $supportedFilters['age']['operators']);
        $this->assertNotContains('contains', $supportedFilters['age']['operators']);
        
        // EnumField should have enum-specific operators
        $this->assertContains('equals', $supportedFilters['role']['operators']);
        $this->assertContains('in', $supportedFilters['role']['operators']);
    }

    public function testValidateAndFilterForModelWithInvalidFilterStructure(): void
    {
        $textField = new TextField([
            'name' => 'name',
            'label' => 'Name',
            'isDBField' => true
        ]);
        
        $this->mockModel->method('getFields')->willReturn(['name' => $textField]);
        
        // Test filter missing operator
        $filters = [
            [
                'field' => 'name',
                // Missing operator
                'value' => 'john'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        $this->assertCount(0, $validatedFilters);
        
        // Test filter missing field
        $filters = [
            [
                // Missing field
                'operator' => 'equals',
                'value' => 'john'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        $this->assertCount(0, $validatedFilters);
    }

    public function testValidateAndFilterForModelWithNullOperators(): void
    {
        $textField = new TextField([
            'name' => 'name',
            'label' => 'Name',
            'isDBField' => true
        ]);
        
        $this->mockModel->method('getFields')->willReturn(['name' => $textField]);
        
        // Test isNull operator (should not require value)
        $filters = [
            [
                'field' => 'name',
                'operator' => 'isNull'
                // No value provided - should be okay for isNull
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        $this->assertCount(1, $validatedFilters);
        $this->assertEquals('isNull', $validatedFilters[0]['operator']);
        $this->assertEquals('name', $validatedFilters[0]['field']);
        
        // Test isNotNull operator
        $filters = [
            [
                'field' => 'name',
                'operator' => 'isNotNull'
            ]
        ];
        
        $validatedFilters = $this->filterCriteria->validateAndFilterForModel($filters, $this->mockModel);
        $this->assertCount(1, $validatedFilters);
        $this->assertEquals('isNotNull', $validatedFilters[0]['operator']);
    }
}
