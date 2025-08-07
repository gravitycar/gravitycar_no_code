<?php

namespace Gravitycar\Tests\Fixtures;

/**
 * Factory for creating test data fixtures.
 * Provides consistent test data across different test cases.
 */
class FixtureFactory
{
    /**
     * Create user test data.
     */
    public static function createUser(array $overrides = []): array
    {
        return array_merge([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ], $overrides);
    }

    /**
     * Create movie test data.
     */
    public static function createMovie(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Movie',
            'director' => 'Test Director',
            'release_year' => 2023,
            'genre' => 'Drama',
            'rating' => 'PG-13',
            'duration_minutes' => 120,
            'description' => 'A test movie for unit testing',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Create movie quote test data.
     */
    public static function createMovieQuote(array $overrides = []): array
    {
        return array_merge([
            'movie_id' => 1,
            'character_name' => 'Test Character',
            'quote_text' => 'This is a test quote',
            'scene_description' => 'Test scene',
            'difficulty_level' => 'medium',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Create validation rule test data.
     */
    public static function createValidationRule(array $overrides = []): array
    {
        return array_merge([
            'field_name' => 'test_field',
            'rule_type' => 'required',
            'rule_config' => '{}',
            'error_message' => 'This field is required',
            'is_active' => 1
        ], $overrides);
    }

    /**
     * Create field metadata test data.
     */
    public static function createFieldMetadata(array $overrides = []): array
    {
        return array_merge([
            'field_name' => 'test_field',
            'field_type' => 'TextField',
            'label' => 'Test Field',
            'is_required' => false,
            'default_value' => null,
            'validation_rules' => '[]',
            'display_order' => 1,
            'is_active' => 1
        ], $overrides);
    }

    /**
     * Generate multiple fixtures of the same type.
     */
    public static function createMultiple(string $type, int $count, array $baseOverrides = []): array
    {
        $fixtures = [];
        $method = 'create' . ucfirst($type);

        if (!method_exists(self::class, $method)) {
            throw new \InvalidArgumentException("Unknown fixture type: {$type}");
        }

        for ($i = 0; $i < $count; $i++) {
            $overrides = array_merge($baseOverrides, ['id' => $i + 1]);
            $fixtures[] = self::$method($overrides);
        }

        return $fixtures;
    }
}
