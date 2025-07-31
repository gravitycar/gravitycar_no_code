<?php

namespace Gravitycar\Tests\Unit;

use Gravitycar\Database\DatabaseConnector;
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

        // Initialize database connection with SQLite for testing (no credentials needed)
        $dbParams = [
            'driver' => 'pdo_sqlite',
            'path' => ':memory:', // In-memory SQLite database for tests
        ];

        $this->db = new DatabaseConnector($this->logger, $dbParams);
        $this->connection = $this->db->getConnection();

        // Enable foreign key constraints in SQLite
        $this->connection->executeStatement('PRAGMA foreign_keys = ON');

        // Start transaction for test isolation
        $this->connection->beginTransaction();
        $this->inTransaction = true;
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
