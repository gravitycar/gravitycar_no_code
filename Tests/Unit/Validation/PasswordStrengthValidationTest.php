<?php
namespace Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Gravitycar\Validation\PasswordStrengthValidation;

/**
 * Unit tests for PasswordStrengthValidation
 */
class PasswordStrengthValidationTest extends TestCase {
    private PasswordStrengthValidation $validation;

    protected function setUp(): void {
        $this->validation = new PasswordStrengthValidation();
    }

    public function testValidPassword(): void {
        $this->assertTrue($this->validation->validate('Password123'));
    }

    public function testPasswordTooShort(): void {
        $this->assertFalse($this->validation->validate('Pass1'));
        $this->assertStringContainsString('8 characters', $this->validation->getErrorMessage());
    }

    public function testPasswordNoUppercase(): void {
        $this->assertFalse($this->validation->validate('password123'));
        $this->assertStringContainsString('uppercase', $this->validation->getErrorMessage());
    }

    public function testPasswordNoLowercase(): void {
        $this->assertFalse($this->validation->validate('PASSWORD123'));
        $this->assertStringContainsString('lowercase', $this->validation->getErrorMessage());
    }

    public function testPasswordNoNumber(): void {
        $this->assertFalse($this->validation->validate('PasswordABC'));
        $this->assertStringContainsString('number', $this->validation->getErrorMessage());
    }

    public function testEmptyPasswordWithLocalAuth(): void {
        $model = $this->createMockModel(['auth_provider' => 'local']);
        $this->assertFalse($this->validation->validate('', $model));
    }

    public function testEmptyPasswordWithGoogleAuth(): void {
        $model = $this->createMockModel(['auth_provider' => 'google']);
        $this->assertTrue($this->validation->validate('', $model));
    }

    public function testEmptyPasswordWithHybridAuth(): void {
        $model = $this->createMockModel(['auth_provider' => 'hybrid']);
        $this->assertTrue($this->validation->validate('', $model));
    }

    public function testValidPasswordWithGoogleAuth(): void {
        $model = $this->createMockModel(['auth_provider' => 'google']);
        $this->assertTrue($this->validation->validate('Password123', $model));
    }

    public function testInvalidPasswordWithGoogleAuth(): void {
        $model = $this->createMockModel(['auth_provider' => 'google']);
        $this->assertFalse($this->validation->validate('weak', $model));
        $this->assertStringContainsString('8 characters', $this->validation->getErrorMessage());
    }

    public function testNullPassword(): void {
        $this->assertFalse($this->validation->validate(null));
    }

    public function testNoModelContext(): void {
        // Without model context, validation should still work for non-empty passwords
        $this->assertTrue($this->validation->validate('Password123', null));
        $this->assertFalse($this->validation->validate('weak', null));
    }

    public function testComplexValidPassword(): void {
        $this->assertTrue($this->validation->validate('MyP@ssw0rd!2024'));
    }

    public function testMinimumValidPassword(): void {
        // Exactly 8 chars with 1 upper, 1 lower, 1 number
        $this->assertTrue($this->validation->validate('Pass123w'));
    }

    /**
     * Create a mock model object for testing
     */
    private function createMockModel(array $data): object {
        return new class($data) {
            private array $data;
            
            public function __construct(array $data) {
                $this->data = $data;
            }
            
            public function get(string $key) {
                return $this->data[$key] ?? null;
            }
        };
    }
}
