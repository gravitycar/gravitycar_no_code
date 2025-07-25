<?php

namespace Gravitycar\Core;

use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Database\SchemaGenerator;
use Gravitycar\Core\GCException;

/**
 * Installation and setup manager for the Gravitycar framework
 *
 * Handles initial installation, database setup, and configuration.
 */
class Installer
{
    private Config $config;
    private ?DatabaseConnector $db = null;
    private SchemaGenerator $schemaGenerator;
    private array $installationSteps = [];

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->schemaGenerator = new SchemaGenerator();
    }

    public function checkInstallationRequired(): bool
    {
        // Check if database connection is available
        if (!$this->testDatabaseConnection()) {
            return true;
        }

        // Check if core tables exist
        if (!$this->coreTablesExist()) {
            return true;
        }

        return false;
    }

    public function install(array $dbCredentials = [], string $adminUsername = 'admin'): array
    {
        $results = [];

        try {
            // Step 1: Configure database connection
            if (!empty($dbCredentials)) {
                $this->configureDatabaseConnection($dbCredentials);
                $results[] = ['step' => 'database_config', 'success' => true];
            }

            // Step 2: Test database connection
            if ($this->testDatabaseConnection()) {
                $results[] = ['step' => 'database_connection', 'success' => true];
            } else {
                throw new GCException("Database connection failed");
            }

            // Step 3: Create database schema
            $schemaResults = $this->createDatabaseSchema();
            $results[] = ['step' => 'schema_creation', 'success' => true, 'details' => $schemaResults];

            // Step 4: Create initial admin user
            $this->createInitialUser($adminUsername);
            $results[] = ['step' => 'admin_user', 'success' => true];

            // Step 5: Save configuration
            $this->config->save();
            $results[] = ['step' => 'save_config', 'success' => true];

            return [
                'success' => true,
                'message' => 'Installation completed successfully',
                'steps' => $results
            ];

        } catch (GCException $e) {
            $results[] = ['step' => 'installation', 'success' => false, 'error' => $e->getMessage()];
            return [
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
                'steps' => $results
            ];
        }
    }

    private function configureDatabaseConnection(array $credentials): void
    {
        $requiredFields = ['host', 'database', 'username', 'password'];

        foreach ($requiredFields as $field) {
            if (!isset($credentials[$field])) {
                throw new GCException("Missing required database credential: {$field}");
            }
        }

        $this->config->set('database.host', $credentials['host']);
        $this->config->set('database.database', $credentials['database']);
        $this->config->set('database.username', $credentials['username']);
        $this->config->set('database.password', $credentials['password']);
        $this->config->set('database.port', $credentials['port'] ?? 3306);
    }

    private function testDatabaseConnection(): bool
    {
        try {
            $this->db = DatabaseConnector::getInstance($this->config->get('database'));
            return $this->db->testConnection();
        } catch (GCException $e) {
            return false;
        }
    }

    private function coreTablesExist(): bool
    {
        if (!$this->db) {
            return false;
        }

        $requiredTables = ['users'];

        foreach ($requiredTables as $table) {
            if (!$this->db->tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    private function createDatabaseSchema(): array
    {
        $metadataPath = __DIR__ . '/../../metadata/models';
        return $this->schemaGenerator->generateAllTablesFromDirectory($metadataPath);
    }

    private function createInitialUser(string $username): void
    {
        // Create initial admin user
        $userData = [
            'username' => $username,
            'email' => 'admin@gravitycar.local',
            'password' => 'admin123', // This will be hashed by the PasswordField
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_active' => true
        ];

        $userClass = 'Gravitycar\\Models\\User';
        if (class_exists($userClass)) {
            $user = new $userClass($userData);
            if (!$user->save()) {
                throw new GCException("Failed to create initial admin user");
            }
        }
    }

    public function getInstallationStatus(): array
    {
        return [
            'database_configured' => $this->config->get('database.host') !== null,
            'database_connected' => $this->testDatabaseConnection(),
            'tables_exist' => $this->coreTablesExist(),
            'installation_required' => $this->checkInstallationRequired()
        ];
    }

    public function updateSchema(): array
    {
        if (!$this->testDatabaseConnection()) {
            throw new GCException("Database connection not available");
        }

        $metadataPath = __DIR__ . '/../../metadata/models';
        return $this->schemaGenerator->generateAllTablesFromDirectory($metadataPath);
    }
}
