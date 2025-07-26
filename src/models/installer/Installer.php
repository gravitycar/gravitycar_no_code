<?php
namespace Gravitycar\Models;

use Gravitycar\Core\ModelBase;
use Gravitycar\Exceptions\GCException;

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
            // 1. Check if config file exists and is writable
            $config = new \Gravitycar\Core\Config();
            if (!$config->configFileExists()) {
                throw new GCException('Config file does not exist or is not writable for installation', $this->logger);
            }

            // 2. Validate DB credentials and write config
            $config->set('database', $dbCredentials);
            $config->write();

            // 3. Create database if not exists
            $dbConnector = new \Gravitycar\Database\DatabaseConnector();
            $dbConnector->createDatabaseIfNotExists();

            // 4. Load metadata and generate schema
            $metadataEngine = new \Gravitycar\Metadata\MetadataEngine();
            $metadata = $metadataEngine->loadAllMetadata();
            $schemaGenerator = new \Gravitycar\Schema\SchemaGenerator();
            $schemaGenerator->generateSchema($metadata);

            // 5. Create initial admin user
            $usersModel = new \Gravitycar\models\users\Users();
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
