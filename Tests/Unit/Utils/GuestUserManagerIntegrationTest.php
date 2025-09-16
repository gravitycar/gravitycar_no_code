<?php

namespace Gravitycar\Tests\Unit\Utils;

use Gravitycar\Tests\Unit\DatabaseTestCase;
use Gravitycar\Utils\GuestUserManager;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Contracts\MetadataEngineInterface;
use Aura\Di\Container;
use Monolog\Logger;

/**
 * Integration tests for GuestUserManager class
 * 
 * These tests use the actual database and framework components to verify
 * the GuestUserManager works correctly in a real environment.
 */
class GuestUserManagerIntegrationTest extends DatabaseTestCase
{
    private GuestUserManager $guestUserManager;
    private ModelFactory $modelFactory;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create ModelFactory with proper dependencies for integration testing
        $mockContainer = $this->createMock(Container::class);
        $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        // Configure MetadataEngine to support the models we'll use
        $mockMetadataEngine->method('getAvailableModels')
            ->willReturn(['Users', 'Movies', 'Roles']);
        
        // Create ModelFactory instance using the real database connector from DatabaseTestCase
        // @phpstan-ignore-next-line - Mock objects are compatible at runtime
        /** @var Container $mockContainer */
        /** @var MetadataEngineInterface $mockMetadataEngine */
        $this->modelFactory = new ModelFactory(
            $mockContainer,
            $this->logger, // From parent UnitTestCase
            $this->db,     // From parent DatabaseTestCase
            $mockMetadataEngine
        );
        
        // Clear any cached guest user before each test
        GuestUserManager::clearCache();
        
        // Create the GuestUserManager instance
        $this->guestUserManager = new GuestUserManager();
        
        // Clean up any existing guest user from previous tests
        $this->cleanupGuestUser();
    }
    
    protected function tearDown(): void
    {
        // Clean up after each test
        $this->cleanupGuestUser();
        GuestUserManager::clearCache();
        parent::tearDown();
    }
    
    /**
     * Test creating and retrieving a guest user
     */
    public function testCreateAndRetrieveGuestUser(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        $this->assertNotNull($guestUser);
        $this->assertEquals('guest@gravitycar.com', $guestUser->get('email'));
        $this->assertEquals('guest@gravitycar.com', $guestUser->get('username'));
        $this->assertEquals('Guest', $guestUser->get('first_name'));
        $this->assertEquals('User', $guestUser->get('last_name'));
        $this->assertEquals('system', $guestUser->get('auth_provider'));
        $this->assertTrue((bool)$guestUser->get('is_active')); // Convert to boolean
        $this->assertEquals('guest', $guestUser->get('last_login_method'));
        
        // Verify ID is set (UUID format)
        $id = $guestUser->get('id');
        $this->assertNotNull($id);
        $this->assertIsString($id);
        $this->assertTrue(strlen($id) > 0);
        
        // Verify password is set and hashed
        $password = $guestUser->get('password');
        $this->assertNotNull($password);
        $this->assertIsString($password);
        $this->assertTrue(strlen($password) >= 60); // password_hash produces at least 60 chars
    }
    
    /**
     * Test that existing guest user is returned on subsequent calls
     */
    public function testRetrieveExistingGuestUser(): void
    {
        // First call creates the guest user
        $guestUser1 = $this->guestUserManager->getGuestUser();
        $userId1 = $guestUser1->get('id');
        
        // Clear cache to ensure we hit the database
        GuestUserManager::clearCache();
        
        // Create new manager instance to simulate different request
        $guestUserManager2 = new GuestUserManager();
        
        // Second call should find the existing guest user
        $guestUser2 = $guestUserManager2->getGuestUser();
        $userId2 = $guestUser2->get('id');
        
        // Should be the same user ID (found existing rather than created new)
        $this->assertEquals($userId1, $userId2);
        $this->assertEquals('guest@gravitycar.com', $guestUser2->get('email'));
    }
    
    /**
     * Test guest user caching within the same instance
     */
    public function testGuestUserCaching(): void
    {
        // First call
        $guestUser1 = $this->guestUserManager->getGuestUser();
        
        // Second call should return the same cached instance
        $guestUser2 = $this->guestUserManager->getGuestUser();
        
        $this->assertSame($guestUser1, $guestUser2);
        $this->assertEquals($guestUser1->get('id'), $guestUser2->get('id'));
    }
    
    /**
     * Test cache clearing functionality
     */
    public function testClearCache(): void
    {
        // Get guest user (should be cached)
        $guestUser1 = $this->guestUserManager->getGuestUser();
        
        // Clear cache
        GuestUserManager::clearCache();
        
        // Get guest user again (should create new instance but same database record)
        $guestUser2 = $this->guestUserManager->getGuestUser();
        
        // Should not be the same object instance
        $this->assertNotSame($guestUser1, $guestUser2);
        
        // But should have the same ID (same database record)
        $this->assertEquals($guestUser1->get('id'), $guestUser2->get('id'));
    }
    
    /**
     * Test guest user identification
     */
    public function testIsGuestUser(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        $this->assertTrue($this->guestUserManager->isGuestUser($guestUser));
        
        // Create a regular user for comparison
        $regularUser = $this->modelFactory->new('Users');
        $regularUser->set('username', 'test@example.com');
        $regularUser->set('email', 'test@example.com');
        $regularUser->set('password', password_hash('testpass123', PASSWORD_DEFAULT));
        $regularUser->set('first_name', 'Test');
        $regularUser->set('last_name', 'User');
        $regularUser->set('auth_provider', 'local');
        $regularUser->set('is_active', true);
        $regularUser->create();
        
        $this->assertFalse($this->guestUserManager->isGuestUser($regularUser));
        
        // Clean up regular user - ensure it has an ID before deletion
        if ($regularUser->get('id')) {
            $regularUser->delete();
        }
    }
    
    /**
     * Test guest user constants
     */
    public function testGuestUserConstants(): void
    {
        $this->assertEquals('guest@gravitycar.com', GuestUserManager::getGuestEmail());
        $this->assertEquals('guest@gravitycar.com', GuestUserManager::getGuestUsername());
    }
    
    /**
     * Test that guest user has system user fields set correctly if they exist
     */
    public function testGuestUserSystemFields(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        // Check if system user field exists and is set correctly
        if ($guestUser->hasField('is_system_user')) {
            $this->assertTrue($guestUser->get('is_system_user'));
        }
        
        // Check if email verified field exists and is set
        if ($guestUser->hasField('email_verified_at')) {
            $this->assertNotNull($guestUser->get('email_verified_at'));
        }
    }
    
    /**
     * Test that multiple GuestUserManager instances work correctly
     */
    public function testMultipleManagerInstances(): void
    {
        $manager1 = new GuestUserManager();
        $manager2 = new GuestUserManager();
        
        $guestUser1 = $manager1->getGuestUser();
        $guestUser2 = $manager2->getGuestUser();
        
        // Should get the same user ID from database
        $this->assertEquals($guestUser1->get('id'), $guestUser2->get('id'));
        $this->assertEquals($guestUser1->get('email'), $guestUser2->get('email'));
    }
    
    /**
     * Test that guest user can be used for audit trails
     */
    public function testGuestUserForAuditTrails(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        // Create a movie using the guest user context
        $movie = $this->modelFactory->new('Movies');
        $movie->set('name', 'Test Movie for Guest Audit');
        $movie->set('synopsis', 'Test movie created by guest user');
        
        $created = $movie->create();
        $this->assertTrue($created);
        
        // Verify audit fields are populated with guest user
        $createdBy = $movie->get('created_by');
        $updatedBy = $movie->get('updated_by');
        
        $this->assertEquals($guestUser->get('id'), $createdBy);
        $this->assertEquals($guestUser->get('id'), $updatedBy);
        
        // Clean up
        $movie->delete();
    }
    
    /**
     * Helper method to clean up any existing guest user
     */
    private function cleanupGuestUser(): void
    {
        try {
            $userModel = $this->modelFactory->new('Users');
            $existingGuestUsers = $userModel->find(['email' => 'guest@gravitycar.com']);
            
            foreach ($existingGuestUsers as $guestUser) {
                $guestUser->delete();
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}
