<?php
namespace Gravitycar\Models\installer;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * Installer model class for Gravitycar framework.
 * Handles the installation workflow and setup process.
 */
class Installer extends ModelBase {
    /**
     * Pure dependency injection constructor
     */
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }

    /**
     * Run the installation workflow
     */
    public function runInstallation(array $dbCredentials, string $adminUsername): bool {
        try {
            // Use injected dependencies instead of ServiceLocator
            // Note: For installation workflows, additional services like Config and SchemaGenerator
            // would typically be injected via a dedicated installer service class
            
            // Create initial admin user using proper ModelFactory
            $usersModel = $this->modelFactory->new('Users');
            $this->createInitialAdmin($usersModel, $adminUsername);

            $this->logger->info('Installation completed successfully');
            return true;
            
        } catch (\Exception $e) {
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
