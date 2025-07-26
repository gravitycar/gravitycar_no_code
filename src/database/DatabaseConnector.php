<?php

namespace Gravitycar\Database;

use Gravitycar\Core\Config;
use PDO;
use PDOException;
use Gravitycar\Core\GCException;

/**
 * Database connector for the Gravitycar framework
 *
 * Handles all database operations and provides a consistent interface
 * for database interactions throughout the framework.
 */
class DatabaseConnector
{
    private static ?DatabaseConnector $instance = null;
    private ?PDO $connection = null;
    private Config $config;
    private ?\Monolog\Logger $logger = null;

    /**
     * @throws GCException
     */
    private function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = new \Monolog\Logger('database');
        if ($this->config->get('installed', false) !== true) {
            return;
        }

        $connectionParams = $this->getDBConnectionParams();
        if (empty($connectionParams['database']) || empty($connectionParams['username'])) {
            return;
        }

        $this->connect();
    }


    public function getDBConnectionParams(): array
    {
        $params = [
            'host' => $this->config->get('database.host', 'localhost'),
            'port' => $this->config->get('database.port', 3306),
            'database' => $this->config->get('database.dbname', ''),
            'username' => $this->config->get('database.username', ''),
            'password' => $this->config->get('database.password', ''),
        ];
        return $params;
    }

    public static function getInstance(): DatabaseConnector
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        if (!self::$instance->isConnected()) {
            try {
                self::$instance->connect();
            } catch (\Exception $e) {
                // ignore connection errors here, as it may be during installation
            }
        }

        return self::$instance;
    }

    private function connect(): void
    {
        $config = $this->getDBConnectionParams();
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['database'] ?? 'gravitycar'
            );

            $this->connection = new PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new GCException("Database connection failed: " . $e->getMessage());
        }
    }

    public function isConnected(): bool
    {
        if ($this->connection == null) {
            return false;
        }

        return true;
    }

    public function testConnection(): bool
    {
        if (is_null($this->connection)) {
            return false;
        }

        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function select(string $table, array $columns = ['*'], array $conditions = [], int $limit = null, int $offset = null): array
    {
        $sql = "SELECT " . implode(', ', $columns) . " FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new GCException("Database select failed: " . $e->getMessage());
        }
    }

    public function insert(string $table, array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($data);
        } catch (PDOException $e) {
            throw new GCException("Database insert failed: " . $e->getMessage());
        }
    }

    public function update(string $table, array $data, array $conditions): bool
    {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }

        $whereClause = [];
        foreach (array_keys($conditions) as $column) {
            $whereClause[] = "{$column} = :where_{$column}";
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);

        $params = $data;
        foreach ($conditions as $column => $value) {
            $params["where_{$column}"] = $value;
        }

        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new GCException("Database update failed: " . $e->getMessage());
        }
    }

    public function delete(string $table, array $conditions): bool
    {
        $whereClause = [];
        foreach (array_keys($conditions) as $column) {
            $whereClause[] = "{$column} = :{$column}";
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);

        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($conditions);
        } catch (PDOException $e) {
            throw new GCException("Database delete failed: " . $e->getMessage());
        }
    }

    public function getLastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new GCException("Database execute failed: " . $e->getMessage());
        }
    }

    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new GCException("Database query failed: " . $e->getMessage());
        }
    }

    public function tableExists(string $tableName): bool
    {
        $sql = "SHOW TABLES LIKE " . $this->connection->quote($tableName);
        $result = $this->query($sql);
        return !empty($result);
    }

    public function getTableSchema(string $tableName): array
    {
        $sql = "DESCRIBE {$tableName}";
        return $this->query($sql);
    }
}
