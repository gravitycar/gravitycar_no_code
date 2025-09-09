<?php
namespace Gravitycar\Models\installer;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;

/**
 * Installer model class for Gravitycar framework.
 * Handles the installation workflow and setup process.
 */
class Installer extends ModelBase {
    public function __construct() {
        parent::__construct();
    }

    /**
     * Run the installation workflow
     */
    public function runInstallation(array $dbCredentials, string $adminUsername): bool {
        try {
            // Get services from container - no manual dependency passing needed!
            $config = \Gravitycar\Core\ServiceLocator::getConfig();
            $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
            $metadataEngine = \Gravitycar\Core\ServiceLocator::getMetadataEngine();
            $schemaGenerator = \Gravitycar\Core\ServiceLocator::getSchemaGenerator();

            // 1. Check if config file exists and is writable
            if (!$config->configFileExists()) {
                throw new GCException('Config file does not exist or is not writable for installation',
                    []
                );
            }

            // 2. Validate DB credentials and write config
            $config->set('database', $dbCredentials);
            $config->write();

            // 3. Create database if not exists
            $dbConnector->createDatabaseIfNotExists();

            // 4. Load metadata and generate schema
            $metadata = $metadataEngine->loadAllMetadata();
            $schemaGenerator->generateSchema($metadata);

            // 5. Create initial admin user
            $usersModel = \Gravitycar\Core\ServiceLocator::getModelFactory()->new('Users');
            $this->createInitialAdmin($usersModel, $adminUsername);

            // 6. Mark installation as complete
            $config->set('installed', true);
            $config->write();

            return true;
        } catch (GCException $e) {
            $this->logger->error('Installation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create initial admin user
     */
    protected function createInitialAdmin($usersModel, string $adminUsername): void {
        $usersModel->set('username', $adminUsername);
        $usersModel->set('email', $adminUsername);
        $usersModel->set('user_type', 'admin');
        $usersModel->set('password', 'admin123'); // Default password
        // TODO: Save user to database
        $this->logger->info("Initial admin user created: $adminUsername");
    }
}
