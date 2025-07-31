<?php

namespace Gravitycar\Tests\Helpers;

/**
 * Builder pattern utility for creating complex test data.
 */
class TestDataBuilder
{
    private array $data = [];

    public function __construct(array $defaults = [])
    {
        $this->data = $defaults;
    }

    /**
     * Set a field value.
     */
    public function with(string $field, $value): self
    {
        $this->data[$field] = $value;
        return $this;
    }

    /**
     * Set multiple field values.
     */
    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Remove a field from the data.
     */
    public function without(string $field): self
    {
        unset($this->data[$field]);
        return $this;
    }

    /**
     * Get the built data array.
     */
    public function build(): array
    {
        return $this->data;
    }

    /**
     * Create a new builder instance with user defaults.
     */
    public static function user(): self
    {
        return new self([
            'username' => 'testuser' . uniqid(),
            'email' => 'test' . uniqid() . '@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => 1
        ]);
    }

    /**
     * Create a new builder instance with movie defaults.
     */
    public static function movie(): self
    {
        return new self([
            'title' => 'Test Movie ' . uniqid(),
            'director' => 'Test Director',
            'release_year' => 2023,
            'genre' => 'Drama',
            'rating' => 'PG-13',
            'duration_minutes' => 120
        ]);
    }

    /**
     * Create a new builder instance with validation rule defaults.
     */
    public static function validationRule(): self
    {
        return new self([
            'field_name' => 'test_field',
            'rule_type' => 'required',
            'rule_config' => '{}',
            'error_message' => 'This field is required',
            'is_active' => 1
        ]);
    }
}
