<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Password field implementation
 *
 * Handles password input with hashing and security features.
 */
class PasswordField extends FieldsBase
{
    protected string $type = 'Password';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'VARCHAR(255)';
    protected string $uiDataType = 'password';
    protected bool $showInList = false;

    public function __construct(array $fieldDefinition)
    {
        // Add password validation by default
        if (!isset($fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'] = [];
        }

        if (!in_array('MinLength', $fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'][] = 'MinLength';
            $fieldDefinition['minLength'] = $fieldDefinition['minLength'] ?? 8;
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        // Never return password values in API responses
        return null;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = (string) $value;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }

    public function set(string $fieldName, mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // Hash password before storing
        if (!empty($value) && !$this->isAlreadyHashed($value)) {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }

        parent::set($fieldName, $value, $model);
    }

    private function isAlreadyHashed(string $value): bool
    {
        // Check if value is already a password hash
        return preg_match('/^\$2[ayb]\$.{56}$/', $value) === 1;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->value);
    }
}
