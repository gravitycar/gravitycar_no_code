<?php

namespace Gravitycar\Tests\Unit\Utils;

use Gravitycar\Tests\Unit\DatabaseTestCase;
use Gravitycar\Utils\GuestUserManager;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;

/**
 * Edge case tests for GuestUserManager class
 * 
 * These tests cover edge cases, error conditions, and boundary scenarios
 * that might occur in production use.
 */
class GuestUserManagerEdgeCaseTest extends DatabaseTestCase
{
    private GuestUserManager $guestUserManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
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
     * Test that duplicate guest users are not created
     */
    public function testNoDuplicateGuestUsers(): void
    {
        // Create first guest user
        $guestUser1 = $this->guestUserManager->getGuestUser();
        $userId1 = $guestUser1->get('id');
        
        // Clear cache to force database lookup
        GuestUserManager::clearCache();
        
        // Create another manager and get guest user again
        $guestUserManager2 = new GuestUserManager();
        $guestUser2 = $guestUserManager2->getGuestUser();
        $userId2 = $guestUser2->get('id');
        
        // Should be the same user (no duplicate created)
        $this->assertEquals($userId1, $userId2);
        
        // Verify only one guest user exists in database
        $userModel = ModelFactory::new('Users');
        $guestUsers = $userModel->find(['email' => 'guest@gravitycar.com']);
        $this->assertCount(1, $guestUsers);
    }
    
    /**
     * Test guest user creation with minimal required fields
     */
    public function testGuestUserCreationWithMinimalFields(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        // Verify essential fields are set
        $this->assertNotNull($guestUser->get('id'));
        $this->assertNotNull($guestUser->get('email'));
        $this->assertNotNull($guestUser->get('username'));
        $this->assertNotNull($guestUser->get('password'));
        
        // Verify email format is valid
        $email = $guestUser->get('email');
        $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
    }
    
    /**
     * Test that guest user persists across application restarts (cache clearing)
     */
    public function testGuestUserPersistence(): void
    {
        // Create guest user
        $guestUser1 = $this->guestUserManager->getGuestUser();
        $originalId = $guestUser1->get('id');
        
        // Simulate application restart by clearing all caches and creating new manager
        GuestUserManager::clearCache();
        $newManager = new GuestUserManager();
        
        // Should find the existing guest user
        $guestUser2 = $newManager->getGuestUser();
        $persistedId = $guestUser2->get('id');
        
        $this->assertEquals($originalId, $persistedId);
    }
    
    /**
     * Test guest user with various email validation scenarios
     */
    public function testGuestUserEmailValidation(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        $email = $guestUser->get('email');
        
        // Should be a valid email
        $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
        
        // Should be the expected guest email
        $this->assertEquals(GuestUserManager::getGuestEmail(), $email);
        
        // Should contain @ symbol
        $this->assertStringContainsString('@', $email);
        
        // Should contain domain
        $this->assertStringContainsString('gravitycar.com', $email);
    }
    
    /**
     * Test concurrent access to guest user creation
     */
    public function testConcurrentGuestUserAccess(): void
    {
        // Simulate multiple concurrent requests by creating multiple managers
        $managers = [];
        $guestUsers = [];
        
        for ($i = 0; $i < 3; $i++) {
            $managers[$i] = new GuestUserManager();
            $guestUsers[$i] = $managers[$i]->getGuestUser();
        }
        
        // All should return the same guest user ID
        $firstId = $guestUsers[0]->get('id');
        for ($i = 1; $i < 3; $i++) {
            $this->assertEquals($firstId, $guestUsers[$i]->get('id'));
        }
    }
    
    /**
     * Test guest user with various field types
     */
    public function testGuestUserFieldTypes(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        // String fields
        $this->assertIsString($guestUser->get('email'));
        $this->assertIsString($guestUser->get('username'));
        $this->assertIsString($guestUser->get('first_name'));
        $this->assertIsString($guestUser->get('last_name'));
        $this->assertIsString($guestUser->get('auth_provider'));
        $this->assertIsString($guestUser->get('last_login_method'));
        
        // ID field (could be string UUID)
        $id = $guestUser->get('id');
        $this->assertTrue(is_string($id) || is_int($id));
        $this->assertNotEmpty($id);
        
        // Boolean field (might come back as string from database)
        $isActive = $guestUser->get('is_active');
        $this->assertTrue($isActive == '1' || $isActive === true || $isActive === 1);
    }
    
    /**
     * Test guest user identification with edge cases
     */
    public function testGuestUserIdentificationEdgeCases(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        // Test with actual guest user
        $this->assertTrue($this->guestUserManager->isGuestUser($guestUser));
        
        // Test with user having similar but different email
        $similarUser = ModelFactory::new('Users');
        $similarUser->set('email', 'guest2@gravitycar.com');
        $this->assertFalse($this->guestUserManager->isGuestUser($similarUser));
        
        // Test with user having empty email
        $emptyEmailUser = ModelFactory::new('Users');
        $emptyEmailUser->set('email', '');
        $this->assertFalse($this->guestUserManager->isGuestUser($emptyEmailUser));
        
        // Test with user having null email
        $nullEmailUser = ModelFactory::new('Users');
        $nullEmailUser->set('email', null);
        $this->assertFalse($this->guestUserManager->isGuestUser($nullEmailUser));
    }
    
    /**
     * Test that guest user handles special characters correctly
     */
    public function testGuestUserSpecialCharacters(): void
    {
        $guestUser = $this->guestUserManager->getGuestUser();
        
        // Email should not contain special characters that could cause issues
        $email = $guestUser->get('email');
        $this->assertStringNotContainsString(' ', $email);
        $this->assertStringNotContainsString("\n", $email);
        $this->assertStringNotContainsString("\t", $email);
        $this->assertStringNotContainsString("\r", $email);
        
        // Names should be clean
        $firstName = $guestUser->get('first_name');
        $lastName = $guestUser->get('last_name');
        $this->assertIsString($firstName);
        $this->assertIsString($lastName);
        $this->assertNotEmpty($firstName);
        $this->assertNotEmpty($lastName);
    }
    
    /**
     * Test guest user constants are immutable
     */
    public function testGuestUserConstantsImmutable(): void
    {
        $email1 = GuestUserManager::getGuestEmail();
        $email2 = GuestUserManager::getGuestEmail();
        $username1 = GuestUserManager::getGuestUsername();
        $username2 = GuestUserManager::getGuestUsername();
        
        // Should always return the same values
        $this->assertEquals($email1, $email2);
        $this->assertEquals($username1, $username2);
        
        // Should be the expected values
        $this->assertEquals('guest@gravitycar.com', $email1);
        $this->assertEquals('guest@gravitycar.com', $username1);
    }
    
    /**
     * Helper method to clean up any existing guest user
     */
    private function cleanupGuestUser(): void
    {
        try {
            $userModel = ModelFactory::new('Users');
            $existingGuestUsers = $userModel->find(['email' => 'guest@gravitycar.com']);
            
            foreach ($existingGuestUsers as $guestUser) {
                $guestUser->delete();
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}
