<?php
namespace Gravitycar\Validation;

/**
 * PasswordStrengthValidation: Validates password strength requirements.
 * 
 * Requirements:
 * - At least 1 uppercase letter
 * - At least 1 lowercase letter
 * - At least 1 number
 * - Minimum length of 8 characters
 * 
 * Context-aware behavior:
 * - For users with auth_provider='local': password is always validated
 * - For users with other auth providers: empty passwords are allowed (OAuth users)
 */
class PasswordStrengthValidation extends ValidationRuleBase {
    /** @var string Human-readable description */
    protected static string $description = 'Validates password strength (1 uppercase, 1 lowercase, 1 number, min 8 chars)';

    /** @var int Minimum password length */
    protected int $minLength = 8;

    public function __construct() {
        parent::__construct(
            'PasswordStrength',
            'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.'
        );
    }

    public function validate($value, $model = null): bool {
        // Check if validation should be skipped based on auth provider
        if ($model && $this->shouldSkipForAuthProvider($value, $model)) {
            return true;
        }

        // If we reach here, validate the password strength
        if (!$this->shouldValidateValue($value)) {
            // If value is empty and we didn't skip above, it means a local auth user
            // has an empty password, which is invalid
            return false;
        }

        // Convert to string for validation
        $password = (string) $value;

        // Check minimum length
        if (strlen($password) < $this->minLength) {
            $this->errorMessage = "Password must be at least {$this->minLength} characters long.";
            return false;
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $this->errorMessage = 'Password must contain at least one uppercase letter.';
            return false;
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $this->errorMessage = 'Password must contain at least one lowercase letter.';
            return false;
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            $this->errorMessage = 'Password must contain at least one number.';
            return false;
        }

        // All validations passed
        return true;
    }

    /**
     * Determine if password validation should be skipped based on auth provider
     * 
     * @param mixed $value The password value
     * @param mixed $model The user model (must have a get() method)
     * @return bool True if validation should be skipped
     */
    protected function shouldSkipForAuthProvider($value, $model): bool {
        // If no model context, we can't determine auth provider, so don't skip
        if (!$model) {
            return false;
        }

        // Get the auth provider from the model
        $authProvider = $model->get('auth_provider');

        // If auth_provider is 'local', never skip validation
        if ($authProvider === 'local') {
            return false;
        }

        // For non-local auth providers (google, hybrid, etc.), skip validation if password is empty
        // This allows OAuth users to have no password
        if (empty($value) || $value === null || $value === '') {
            return true;
        }

        // If auth provider is not local and password is not empty, 
        // we still need to validate it (user might be setting a password for hybrid auth)
        return false;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validatePasswordStrength(value, fieldName, model) {
            // Check if we should skip validation based on auth provider
            if (model && model.auth_provider && model.auth_provider !== 'local') {
                // For non-local auth providers, allow empty passwords
                if (!value || value === '') {
                    return { valid: true };
                }
            }

            // If value is empty at this point, it's invalid (local auth user)
            if (!value || value === '') {
                return { 
                    valid: false, 
                    message: 'Password is required for local authentication.' 
                };
            }

            // Check minimum length
            if (value.length < {$this->minLength}) {
                return { 
                    valid: false, 
                    message: 'Password must be at least {$this->minLength} characters long.' 
                };
            }

            // Check for at least one uppercase letter
            if (!/[A-Z]/.test(value)) {
                return { 
                    valid: false, 
                    message: 'Password must contain at least one uppercase letter.' 
                };
            }

            // Check for at least one lowercase letter
            if (!/[a-z]/.test(value)) {
                return { 
                    valid: false, 
                    message: 'Password must contain at least one lowercase letter.' 
                };
            }

            // Check for at least one number
            if (!/[0-9]/.test(value)) {
                return { 
                    valid: false, 
                    message: 'Password must contain at least one number.' 
                };
            }

            return { valid: true };
        }
        ";
    }
}
