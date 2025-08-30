<?php

namespace Gravitycar\Tests\Unit\Utils;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Utils\GuestUserManager;
use ReflectionClass;

/**
 * Focused unit tests for GuestUserManager class
 * 
 * These tests focus on the methods that can be tested in isolation
 * without requiring complex mocking or database access.
 */
class GuestUserManagerUnitTest extends UnitTestCase
{
    /**
     * Test that guest user constants are correctly defined
     */
    public function testGuestUserConstants(): void
    {
        $this->assertEquals('guest@gravitycar.com', GuestUserManager::getGuestEmail());
        $this->assertEquals('guest@gravitycar.com', GuestUserManager::getGuestUsername());
    }
    
    /**
     * Test cache clearing functionality
     */
    public function testClearCache(): void
    {
        // Use reflection to check the static cache property
        $reflection = new ReflectionClass(GuestUserManager::class);
        $cacheProperty = $reflection->getProperty('cachedGuestUser');
        $cacheProperty->setAccessible(true);
        
        // Set a dummy value in cache
        $dummyUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $cacheProperty->setValue($dummyUser);
        
        // Verify cache has value
        $this->assertSame($dummyUser, $cacheProperty->getValue());
        
        // Clear cache
        GuestUserManager::clearCache();
        
        // Verify cache is cleared
        $this->assertNull($cacheProperty->getValue());
    }
    
    /**
     * Test secure password generation method (using reflection)
     */
    public function testSecurePasswordGeneration(): void
    {
        $guestManager = new GuestUserManager();
        $reflection = new ReflectionClass($guestManager);
        $method = $reflection->getMethod('generateSecureGuestPassword');
        $method->setAccessible(true);
        
        // Generate a password
        $password = $method->invoke($guestManager);
        
        // Verify it's a valid password hash
        $this->assertIsString($password);
        $this->assertTrue(strlen($password) >= 60); // password_hash produces at least 60 chars
        $this->assertStringStartsWith('$', $password); // Hash format starts with $
        
        // Generate another password and ensure they're different
        $password2 = $method->invoke($guestManager);
        $this->assertNotEquals($password, $password2);
    }
    
    /**
     * Test guest user identification with mock user
     */
    public function testIsGuestUser(): void
    {
        $guestManager = new GuestUserManager();
        
        // Create mock guest user
        $guestUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $guestUser->method('get')
            ->with('email')
            ->willReturn('guest@gravitycar.com');
        
        // Create mock regular user
        $regularUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $regularUser->method('get')
            ->with('email')
            ->willReturn('john@example.com');
        
        // Cast to satisfy type checker
        /** @var \Gravitycar\Models\ModelBase $guestUser */
        /** @var \Gravitycar\Models\ModelBase $regularUser */
        
        $this->assertTrue($guestManager->isGuestUser($guestUser));
        $this->assertFalse($guestManager->isGuestUser($regularUser));
    }
    
    /**
     * Test that class constants match expected values
     */
    public function testClassConstants(): void
    {
        $reflection = new ReflectionClass(GuestUserManager::class);
        $constants = $reflection->getConstants();
        
        $this->assertArrayHasKey('GUEST_EMAIL', $constants);
        $this->assertArrayHasKey('GUEST_USERNAME', $constants);
        $this->assertEquals('guest@gravitycar.com', $constants['GUEST_EMAIL']);
        $this->assertEquals('guest@gravitycar.com', $constants['GUEST_USERNAME']);
    }
    
    /**
     * Test that the GuestUserManager can be instantiated
     */
    public function testInstantiation(): void
    {
        $guestManager = new GuestUserManager();
        $this->assertInstanceOf(GuestUserManager::class, $guestManager);
    }
    
    /**
     * Test that static methods work correctly
     */
    public function testStaticMethods(): void
    {
        // Test getGuestEmail
        $email = GuestUserManager::getGuestEmail();
        $this->assertIsString($email);
        $this->assertEquals('guest@gravitycar.com', $email);
        
        // Test getGuestUsername
        $username = GuestUserManager::getGuestUsername();
        $this->assertIsString($username);
        $this->assertEquals('guest@gravitycar.com', $username);
        
        // Test clearCache (should not throw exception)
        GuestUserManager::clearCache();
        $this->assertTrue(true); // If we get here, clearCache worked
    }
    
    /**
     * Test password generation produces unique passwords
     */
    public function testPasswordUniqueness(): void
    {
        $guestManager = new GuestUserManager();
        $reflection = new ReflectionClass($guestManager);
        $method = $reflection->getMethod('generateSecureGuestPassword');
        $method->setAccessible(true);
        
        $passwords = [];
        for ($i = 0; $i < 5; $i++) {
            $passwords[] = $method->invoke($guestManager);
        }
        
        // All passwords should be unique
        $uniquePasswords = array_unique($passwords);
        $this->assertCount(5, $uniquePasswords);
        
        // All passwords should be properly hashed
        foreach ($passwords as $password) {
            $this->assertStringStartsWith('$', $password);
            $this->assertTrue(strlen($password) >= 60);
        }
    }
}
