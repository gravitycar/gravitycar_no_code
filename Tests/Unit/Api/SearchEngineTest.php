<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\SearchEngine;
use Gravitycar\Models\ModelBase;
use Gravitycar\Fields\TextField;
use Gravitycar\Fields\EmailField;
use Gravitycar\Fields\PasswordField;
use Gravitycar\Fields\EnumField;
use Gravitycar\Fields\BigTextField;
use Gravitycar\Fields\IntegerField;
use Gravitycar\Fields\ImageField;

class SearchEngineTest extends TestCase
{
    private SearchEngine $searchEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchEngine = new SearchEngine();
    }

    public function testValidateSearchForModelWithValidFields(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true]),
            'email' => new EmailField(['name' => 'email', 'label' => 'Email', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => 'john doe',
            'fields' => ['name', 'email'],
            'operator' => 'contains'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        $this->assertEquals('john doe', $validated['term']);
        $this->assertEquals(['name', 'email'], $validated['fields']);
        $this->assertEquals('contains', $validated['operator']);
    }

    public function testValidateSearchForModelWithEmptyTerm(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => '',
            'fields' => ['name'],
            'operator' => 'contains'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        $this->assertEmpty($validated);
    }

    public function testValidateSearchForModelTrimsSearchTerm(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => '  john doe  ',
            'fields' => ['name'],
            'operator' => 'contains'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        $this->assertEquals('john doe', $validated['term']);
    }

    public function testValidateSearchForModelFiltersInvalidFields(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true]),
            'email' => new EmailField(['name' => 'email', 'label' => 'Email', 'isDBField' => true]),
            'password' => new PasswordField(['name' => 'password', 'label' => 'Password', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => 'search',
            'fields' => ['name', 'nonexistent_field', 'password', 'email'],
            'operator' => 'contains'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        // Should exclude nonexistent_field and password field
        $this->assertEquals(['name', 'email'], $validated['fields']);
    }

    public function testValidateSearchForModelWithInvalidOperator(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => 'search',
            'fields' => ['name'],
            'operator' => 'invalid_operator'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        // Should default to 'contains'
        $this->assertEquals('contains', $validated['operator']);
    }

    public function testValidateSearchForModelWithNoOperator(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => 'search',
            'fields' => ['name']
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        // Should default to 'contains'
        $this->assertEquals('contains', $validated['operator']);
    }

    public function testGetSearchableFields(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true]),
            'email' => new EmailField(['name' => 'email', 'label' => 'Email', 'isDBField' => true]),
            'password' => new PasswordField(['name' => 'password', 'label' => 'Password', 'isDBField' => true]),
            'role' => new EnumField(['name' => 'role', 'label' => 'Role', 'isDBField' => true, 'options' => ['admin', 'user']]),
            'avatar' => new ImageField(['name' => 'avatar', 'label' => 'Avatar', 'isDBField' => true])
        ]);

        $searchableFields = $this->searchEngine->getSearchableFields($model);

        // Should include searchable fields
        $this->assertArrayHasKey('name', $searchableFields);
        $this->assertArrayHasKey('email', $searchableFields);
        $this->assertArrayHasKey('role', $searchableFields);

        // Should not include password field
        $this->assertArrayNotHasKey('password', $searchableFields);

        // Should not include image field
        $this->assertArrayNotHasKey('avatar', $searchableFields);

        // Check field information structure
        $nameField = $searchableFields['name'];
        $this->assertArrayHasKey('fieldType', $nameField);
        $this->assertArrayHasKey('searchOperators', $nameField);
        $this->assertArrayHasKey('fieldDescription', $nameField);
        $this->assertArrayHasKey('isDefaultSearchable', $nameField);
    }

    public function testParseSearchTerm(): void
    {
        $term = 'john "doe smith" jane';
        $parsed = $this->searchEngine->parseSearchTerm($term);

        $this->assertEquals('john "doe smith" jane', $parsed['original']);
        $this->assertEquals('john "doe smith" jane', $parsed['cleaned']);
        $this->assertEquals(['doe smith'], $parsed['quoted_phrases']);
        $this->assertEquals(['john', 'jane'], $parsed['words']);
    }

    public function testParseSearchTermWithOnlyQuotedPhrases(): void
    {
        $term = '"hello world" "foo bar"';
        $parsed = $this->searchEngine->parseSearchTerm($term);

        $this->assertEquals(['hello world', 'foo bar'], $parsed['quoted_phrases']);
        $this->assertEmpty($parsed['words']);
    }

    public function testParseSearchTermFiltersShortWords(): void
    {
        $term = 'a john b doe c';
        $parsed = $this->searchEngine->parseSearchTerm($term);

        // Should filter out single character words 'a', 'b', 'c'
        $this->assertEquals(['john', 'doe'], array_values($parsed['words']));
    }

    public function testParseSearchTermWithWhitespace(): void
    {
        $term = '  john   doe  ';
        $parsed = $this->searchEngine->parseSearchTerm($term);

        $this->assertEquals('john   doe', $parsed['cleaned']);
        $this->assertEquals(['john', 'doe'], $parsed['words']);
    }

    public function testValidateSearchForModelWithAllInvalidFields(): void
    {
        $model = $this->createModelWithFields([
            'password' => new PasswordField(['name' => 'password', 'label' => 'Password', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => 'search',
            'fields' => ['password', 'nonexistent'],
            'operator' => 'contains'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        // Should return empty array when no valid fields
        $this->assertEmpty($validated);
    }

    public function testValidateSearchForModelWithNoFieldsUsesDefaults(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true])
        ]);

        $searchParams = [
            'term' => 'search',
            'operator' => 'contains'
        ];

        $validated = $this->searchEngine->validateSearchForModel($searchParams, $model);

        // Should get default searchable fields (name field should be included)
        $this->assertNotEmpty($validated['fields']);
        $this->assertContains('name', $validated['fields']);
    }

    public function testSearchableFieldTypesIncludeTextAndEmail(): void
    {
        $model = $this->createModelWithFields([
            'name' => new TextField(['name' => 'name', 'label' => 'Name', 'isDBField' => true]),
            'email' => new EmailField(['name' => 'email', 'label' => 'Email', 'isDBField' => true])
        ]);

        $searchableFields = $this->searchEngine->getSearchableFields($model);

        $this->assertArrayHasKey('name', $searchableFields);
        $this->assertArrayHasKey('email', $searchableFields);
        
        // Check that text field has multiple operators
        $nameOperators = $searchableFields['name']['searchOperators'];
        $this->assertContains('contains', $nameOperators);
        $this->assertContains('startsWith', $nameOperators);
        $this->assertContains('endsWith', $nameOperators);
        $this->assertContains('equals', $nameOperators);
    }

    public function testSearchableFieldTypesIncludeEnumFields(): void
    {
        $model = $this->createModelWithFields([
            'role' => new EnumField(['name' => 'role', 'label' => 'Role', 'isDBField' => true, 'options' => ['admin', 'user']])
        ]);

        $searchableFields = $this->searchEngine->getSearchableFields($model);

        $this->assertArrayHasKey('role', $searchableFields);
        
        // Enum fields should only support equals operator
        $roleOperators = $searchableFields['role']['searchOperators'];
        $this->assertEquals(['equals'], $roleOperators);
    }

    /**
     * Create a mock model with specified fields
     */
    private function createModelWithFields(array $fields): ModelBase
    {
        $model = $this->createMock(ModelBase::class);
        $model->method('getFields')->willReturn($fields);
        return $model;
    }
}
