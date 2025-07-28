<?php
namespace Gravitycar\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * DatabaseConnector provides DBAL connection and utility methods for Gravitycar.
 */
class DatabaseConnector {
    /** @var array */
    protected array $dbParams;
    /** @var Logger */
    protected Logger $logger;
    /** @var Connection|null */
    protected ?Connection $connection = null;

    public function __construct(Logger $logger, array $dbParams) {
        $this->logger = $logger;
        $this->dbParams = $dbParams;
    }

    /**
     * Get Doctrine DBAL connection
     */
    public function getConnection(): Connection {
        if ($this->connection === null) {
            try {
                $this->connection = DriverManager::getConnection($this->dbParams);
            } catch (\Exception $e) {
                throw new GCException('Database connection failed: ' . $e->getMessage(),
                    ['db_params' => $this->dbParams, 'error' => $e->getMessage()], 0, $e);
            }
        }
        return $this->connection;
    }

    /**
     * Test database connection
     */
    public function testConnection(): bool {
        try {
            $conn = $this->getConnection();
            $conn->connect();
            return $conn->isConnected();
        } catch (\Exception $e) {
            $this->logger->error('Database connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     */
    public function tableExists(string $tableName): bool {
        try {
            $conn = $this->getConnection();
            $schemaManager = method_exists($conn, 'createSchemaManager') ? $conn->createSchemaManager() : $conn->getSchemaManager();
            return $schemaManager->tablesExist([$tableName]);
        } catch (\Exception $e) {
            $this->logger->error('Table existence check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new database if it does not exist
     */
    public function createDatabaseIfNotExists(): bool {
        $dbName = $this->dbParams['dbname'] ?? null;
        if (!$dbName) {
            throw new GCException('Database name not specified in config',
                ['db_params' => $this->dbParams]);
        }
        try {
            $params = $this->dbParams;
            unset($params['dbname']);
            $conn = DriverManager::getConnection($params);
            $conn->executeStatement("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->logger->info("Database '$dbName' created or already exists.");
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Database creation failed: ' . $e->getMessage());
            return false;
        }
    }
}
