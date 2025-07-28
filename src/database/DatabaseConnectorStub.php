<?php
namespace Gravitycar\Database;

use Monolog\Logger;
use Exception;
use Doctrine\DBAL\Connection;

/**
 * DatabaseConnectorStub provides meaningful error messages when database connection fails.
 * Prevents cascading failures while providing clear feedback about database issues.
 */
class DatabaseConnectorStub extends DatabaseConnector {
    private Exception $originalError;
    private string $errorMessage;

    public function __construct(Logger $logger, Exception $error) {
        $this->logger = $logger;
        $this->originalError = $error;
        $this->errorMessage = 'Database service unavailable: ' . $error->getMessage();
        $this->dbParams = []; // Empty params since connection failed

        $this->logger->error('DatabaseConnectorStub active - database operations will fail gracefully');
    }

    public function getConnection(): Connection {
        throw new \Gravitycar\Exceptions\GCException(
            $this->errorMessage . ' - Please check database configuration and connectivity.',
            $this->logger,
            $this->originalError
        );
    }

    public function testConnection(): bool {
        $this->logger->warning('Database connection test failed - using DatabaseConnectorStub');
        return false;
    }

    public function tableExists(string $tableName): bool {
        $this->logger->warning("Cannot check if table '$tableName' exists - database unavailable");
        return false;
    }

    public function createDatabaseIfNotExists(): bool {
        $this->logger->error('Cannot create database - DatabaseConnectorStub is active');
        throw new \Gravitycar\Exceptions\GCException(
            'Database creation failed: ' . $this->errorMessage,
            $this->logger,
            $this->originalError
        );
    }

    public function getOriginalError(): Exception {
        return $this->originalError;
    }

    public function getErrorMessage(): string {
        return $this->errorMessage;
    }

    public function isStub(): bool {
        return true;
    }
}
