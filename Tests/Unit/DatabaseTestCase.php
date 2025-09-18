<?php

namespace Gravitycar\Tests\Unit;

use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Core\Config;
use Doctrine\DBAL\Connection;

/**
 * Base class for tests that require database interaction.
 * Provides database setup and transaction rollback for isolated tests.
 */
abstract class DatabaseTestCase extends UnitTestCase
{
    protected Connection $connection;
    protected bool $inTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Get database configuration from environment or use defaults
        $dbParams = $this->getTestDatabaseConfig();

        // Create Config mock and configure it to return test database parameters
        $mockConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('get')
            ->with('database')
            ->willReturn($dbParams);

        // @phpstan-ignore-next-line - Mock object is compatible at runtime  
        /** @var Config $mockConfig */
        $this->db = new DatabaseConnector($this->logger, $mockConfig);
        $this->connection = $this->db->getConnection();

        // Enable foreign key constraints only for SQLite (not applicable to MySQL)
        $platform = $this->connection->getDatabasePlatform();
        if ($platform->getName() === 'sqlite') {
            $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        }

        // Start transaction for test isolation
        $this->connection->beginTransaction();
        $this->inTransaction = true;
    }

    /**
     * Get database configuration for testing.
     * Supports both SQLite (for CI/CD) and MySQL (for local development).
     */
    protected function getTestDatabaseConfig(): array
    {
        // Check environment variables for database configuration
        $dbConnection = $_ENV['DB_CONNECTION'] ?? 'mysql';
        
        if ($dbConnection === 'sqlite') {
            // SQLite configuration for CI/CD and lightweight testing
            $dbPath = $_ENV['DB_DATABASE'] ?? ':memory:';
            
            return [
                'driver' => 'pdo_sqlite',
                'path' => $dbPath,
                'memory' => $dbPath === ':memory:',
            ];
        } else {
            // MySQL configuration for local development (default)
            return [
                'driver' => 'pdo_mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'dbname' => $_ENV['DB_DATABASE'] ?? 'gravitycar_nc_test',
                'user' => $_ENV['DB_USERNAME'] ?? 'mike',
                'password' => $_ENV['DB_PASSWORD'] ?? 'mike',
                'charset' => 'utf8mb4',
            ];
        }
    }

    protected function tearDown(): void
    {
        // Rollback transaction to isolate tests
        if ($this->inTransaction && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Execute a raw SQL query for test setup.
     */
    protected function executeQuery(string $sql, array $params = []): \Doctrine\DBAL\Result
    {
        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * Insert test data and return the inserted ID.
     */
    protected function insertTestData(string $table, array $data): int
    {
        $this->connection->insert($table, $data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Clear all data from specified tables.
     */
    protected function clearTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->connection->executeStatement("DELETE FROM {$table}");
        }
    }

    /**
     * Assert that a record exists in the database.
     */
    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(*)')
                    ->from($table);

        foreach ($conditions as $column => $value) {
            $queryBuilder->andWhere("{$column} = :{$column}")
                        ->setParameter($column, $value);
        }

        $count = $queryBuilder->executeQuery()->fetchOne();
        $this->assertGreaterThan(0, $count, "Failed asserting that table '{$table}' contains matching record.");
    }

    /**
     * Assert that a record does not exist in the database.
     */
    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(*)')
                    ->from($table);

        foreach ($conditions as $column => $value) {
            $queryBuilder->andWhere("{$column} = :{$column}")
                        ->setParameter($column, $value);
        }

        $count = $queryBuilder->executeQuery()->fetchOne();
        $this->assertEquals(0, $count, "Failed asserting that table '{$table}' does not contain matching record.");
    }
}
