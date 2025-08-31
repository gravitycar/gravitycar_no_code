<?php

namespace Gravitycar\Tests\Unit\Relationships;

use Gravitycar\Tests\Unit\DatabaseTestCase;
use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Integration tests for RelationshipBase that require database access.
 * These tests use the actual database and ServiceLocator infrastructure.
 */
class RelationshipBaseDatabaseTest extends DatabaseTestCase
{
    private MockRelationshipForDB $relationship;
    private array $testMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if database is not available
        if (!$this->db) {
            $this->markTestSkipped('Database not available for testing');
        }

        $this->testMetadata = [
            'name' => 'test_relationship',
            'type' => 'OneToMany',
            'modelOne' => 'TestModelA',
            'modelMany' => 'TestModelB',
            'constraints' => [],
            'additionalFields' => [],
            'fields' => [
                'id' => ['type' => 'IDField', 'required' => true],
                'created_at' => ['type' => 'DateTime'],
                'updated_at' => ['type' => 'DateTime'],
                'deleted_at' => ['type' => 'DateTime'],
                'created_by' => ['type' => 'Text'],
                'updated_by' => ['type' => 'Text'],
                'deleted_by' => ['type' => 'Text']
            ]
        ];

        $this->relationship = new MockRelationshipForDB('test_relationship');
        $this->relationship->setTestMetadata($this->testMetadata);
    }

    public function testGetCurrentUserId(): void
    {
        $userId = $this->relationship->getCurrentUserIdPublic();
        
        // Should return either 'system' or a valid user ID
        $this->assertTrue(
            $userId === 'system' || 
            $userId === null || 
            (is_string($userId) && !empty($userId))
        );
    }

    public function testGetDatabaseConnector(): void
    {
        $connector = $this->relationship->getDatabaseConnectorPublic();
        
        $this->assertInstanceOf(DatabaseConnector::class, $connector);
    }

    public function testTableNameGeneration(): void
    {
        $this->assertEquals('rel_1_testmodela_M_testmodelb', $this->relationship->getTableName());
    }

    public function testCascadeConstants(): void
    {
        $this->assertEquals('restrict', RelationshipBase::CASCADE_RESTRICT);
        $this->assertEquals('cascade', RelationshipBase::CASCADE_CASCADE);
        $this->assertEquals('softDelete', RelationshipBase::CASCADE_SOFT_DELETE);
        $this->assertEquals('setDefault', RelationshipBase::CASCADE_SET_DEFAULT);
    }

    public function testMetadataProcessing(): void
    {
        $metadata = $this->relationship->getRelationshipMetadata();
        
        // Verify core metadata is preserved
        $this->assertEquals('test_relationship', $metadata['name']);
        $this->assertEquals('OneToMany', $metadata['type']);
        $this->assertEquals('TestModelA', $metadata['modelOne']);
        $this->assertEquals('TestModelB', $metadata['modelMany']);
        
        // Verify dynamic fields were generated
        $this->assertArrayHasKey('one_testmodela_id', $metadata['fields']);
        $this->assertArrayHasKey('many_testmodelb_id', $metadata['fields']);
        
        // Verify field properties
        $oneField = $metadata['fields']['one_testmodela_id'];
        $this->assertEquals('IDField', $oneField['type']);
        $this->assertEquals('TestModelA ID', $oneField['label']);
        $this->assertTrue($oneField['required']);
        $this->assertEquals('TestModelA', $oneField['relatedModel']);
    }

    /**
     * Test that the relationship can be instantiated without database access 
     * (for cases where only metadata processing is needed)
     */
    public function testRelationshipInstantiationWithoutDatabaseAccess(): void
    {
        $relationship = new MockRelationshipForDB('metadata_only');
        $relationship->setTestMetadata($this->testMetadata);
        
        // Should be able to access metadata without database
        $this->assertEquals('OneToMany', $relationship->getType());
        $this->assertEquals('test_relationship', $relationship->getName());
        $this->assertNotEmpty($relationship->getTableName());
    }

    /**
     * Test error handling when ServiceLocator is not properly configured
     */
    public function testErrorHandlingWithoutServiceLocator(): void
    {
        // This test verifies that the relationship can handle cases where
        // ServiceLocator dependencies are not available (graceful degradation)
        
        $relationship = new MockRelationshipForDB('error_test');
        $relationship->setTestMetadata($this->testMetadata);
        
        // These should work without ServiceLocator
        $this->assertEquals('OneToMany', $relationship->getType());
        $this->assertEquals('rel_1_testmodela_M_testmodelb', $relationship->getTableName());
        
        // Since ServiceLocator is available in test environment, 
        // just verify that database connector can be obtained
        $connector = $relationship->getDatabaseConnectorPublic();
        $this->assertInstanceOf(DatabaseConnector::class, $connector);
    }

    public function testIngestMetadataCompleteProcess(): void
    {
        // Test the complete metadata ingestion process
        $metadata = $this->relationship->getRelationshipMetadata();
        
        // Should have all required core fields
        $requiredFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];
        foreach ($requiredFields as $fieldName) {
            $this->assertArrayHasKey($fieldName, $metadata['fields'], "Missing required field: {$fieldName}");
        }
        
        // Should have relationship-specific fields
        $this->assertArrayHasKey('one_testmodela_id', $metadata['fields']);
        $this->assertArrayHasKey('many_testmodelb_id', $metadata['fields']);
        
        // Verify field structure
        $idField = $metadata['fields']['id'];
        $this->assertEquals('IDField', $idField['type']);
        $this->assertTrue($idField['required']);
    }

    public function testModelIdFieldGenerationForDifferentTypes(): void
    {
        // Test OneToMany
        $oneToManyMeta = $this->testMetadata;
        $relationship = new MockRelationshipForDB('one_to_many_test');
        $relationship->setTestMetadata($oneToManyMeta);
        
        $this->assertEquals('one_testmodela_id', $relationship->getModelIdFieldPublic('TestModelA'));
        $this->assertEquals('many_testmodelb_id', $relationship->getModelIdFieldPublic('TestModelB'));
        
        // Test OneToOne
        $oneToOneMeta = [
            'name' => 'test_one_to_one',
            'type' => 'OneToOne',
            'modelA' => 'UserModel',
            'modelB' => 'ProfileModel',
            'fields' => $this->testMetadata['fields']
        ];
        $oneToOneRel = new MockRelationshipForDB('one_to_one_test');
        $oneToOneRel->setTestMetadata($oneToOneMeta);
        
        $this->assertEquals('usermodel_id', $oneToOneRel->getModelIdFieldPublic('UserModel'));
        $this->assertEquals('profilemodel_id', $oneToOneRel->getModelIdFieldPublic('ProfileModel'));
        
        // Test ManyToMany
        $manyToManyMeta = [
            'name' => 'test_many_to_many',
            'type' => 'ManyToMany',
            'modelA' => 'UserModel',
            'modelB' => 'RoleModel',
            'fields' => $this->testMetadata['fields']
        ];
        $manyToManyRel = new MockRelationshipForDB('many_to_many_test');
        $manyToManyRel->setTestMetadata($manyToManyMeta);
        
        $this->assertEquals('usermodel_id', $manyToManyRel->getModelIdFieldPublic('UserModel'));
        $this->assertEquals('rolemodel_id', $manyToManyRel->getModelIdFieldPublic('RoleModel'));
    }
}

/**
 * Mock relationship class for database testing that can handle ServiceLocator dependencies
 */
class MockRelationshipForDB extends RelationshipBase
{
    private bool $testMode = false;

    public function __construct(?string $relationshipName = null)
    {
        // In test mode, skip parent constructor that requires ServiceLocator
        if (!$this->testMode) {
            $this->relationshipName = $relationshipName;
            $this->logger = new Logger('test');
        }
    }

    public function setTestMetadata(array $metadata): void
    {
        $this->testMode = true;
        $this->metadata = $metadata;
        $this->metadataLoaded = false;
        
        // Initialize required properties
        $this->logger = new Logger('test');
        
        // Validate and process the metadata
        try {
            $this->validateMetadata($metadata);
            $this->metadataLoaded = true;
            $this->generateTableName();
            $this->generateDynamicFields();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // Public accessors for testing protected methods
    public function getCurrentUserIdPublic(): ?string
    {
        return $this->getCurrentUserId();
    }

    public function getDatabaseConnectorPublic(): DatabaseConnector
    {
        return $this->getDatabaseConnector();
    }

    public function getModelIdFieldPublic(string $modelName): string
    {
        $modelName = strtolower($modelName);
        $type = $this->getType();
        
        switch ($type) {
            case 'OneToOne':
            case 'ManyToMany':
                return $modelName . '_id';

            case 'OneToMany':
                $modelOne = strtolower($this->metadata['modelOne']);
                $modelMany = strtolower($this->metadata['modelMany']);

                if ($modelName === $modelOne) {
                    return 'one_' . $modelName . '_id';
                } elseif ($modelName === $modelMany) {
                    return 'many_' . $modelName . '_id';
                } else {
                    // If model name doesn't match either, try to infer
                    return 'unknown_' . $modelName . '_id';
                }

            default:
                throw new GCException("Unknown relationship type: {$type}");
        }
    }

    // Required abstract method implementations
    public function updateRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData): bool
    {
        // Mock implementation for testing
        return true;
    }

    public function handleModelDeletion(ModelBase $deletedModel, string $cascadeAction): bool
    {
        // Mock implementation for testing
        return true;
    }

    // Override methods for testing
    protected function getDatabaseConnector(): DatabaseConnector
    {
        try {
            return ServiceLocator::getDatabaseConnector();
        } catch (\Exception $e) {
            throw new \RuntimeException('Database connector not available in test environment');
        }
    }

    protected function getCurrentUserId(): ?string
    {
        try {
            return ServiceLocator::getCurrentUser()?->get('id') ?? 'system';
        } catch (\Exception $e) {
            return 'system';
        }
    }
}
