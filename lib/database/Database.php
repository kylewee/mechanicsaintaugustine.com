<?php
/**
 * Unified Database Connection Manager
 *
 * Consolidates all database connection logic into a single, secure implementation
 * using PDO with prepared statements for SQL injection protection.
 */

class Database {
    private static $instances = [];
    private $connection = null;
    private $config = [];

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct($config) {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Get database instance (Singleton)
     *
     * @param string $name Database name (main, rating, crm)
     * @return Database
     */
    public static function getInstance($name = 'main') {
        if (!isset(self::$instances[$name])) {
            $config = self::getConfig($name);
            self::$instances[$name] = new self($config);
        }
        return self::$instances[$name];
    }

    /**
     * Get database configuration
     */
    private static function getConfig($name) {
        switch ($name) {
            case 'rating':
                return [
                    'host' => getenv('RATING_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost',
                    'username' => getenv('RATING_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'root',
                    'password' => getenv('RATING_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '',
                    'database' => getenv('RATING_DB_NAME') ?: 'rating',
                    'charset' => 'utf8mb4'
                ];

            case 'crm':
                // Load CRM config from their config file
                $crm_config_file = __DIR__ . '/../../crm/config/database.php';
                if (file_exists($crm_config_file)) {
                    $crm_config = include $crm_config_file;

                    // Support both DB_SERVER and DB_HOST naming conventions
                    $host = isset($crm_config['DB_SERVER']) ? $crm_config['DB_SERVER'] :
                            (isset($crm_config['DB_HOST']) ? $crm_config['DB_HOST'] : 'localhost');

                    return [
                        'host' => $host,
                        'username' => $crm_config['DB_USERNAME'] ?? 'root',
                        'password' => $crm_config['DB_PASSWORD'] ?? '',
                        'database' => $crm_config['DB_NAME'] ?? 'crm',
                        'charset' => 'utf8mb4'
                    ];
                }
                // Fallback to environment variables
                return [
                    'host' => getenv('CRM_DB_HOST') ?: 'localhost',
                    'username' => getenv('CRM_DB_USERNAME') ?: 'root',
                    'password' => getenv('CRM_DB_PASSWORD') ?: '',
                    'database' => getenv('CRM_DB_NAME') ?: 'crm',
                    'charset' => 'utf8mb4'
                ];

            case 'main':
            default:
                return [
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'username' => getenv('DB_USERNAME') ?: 'root',
                    'password' => getenv('DB_PASSWORD') ?: '',
                    'database' => getenv('DB_NAME') ?: 'mm',
                    'charset' => 'utf8mb4'
                ];
        }
    }

    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['database'],
                $this->config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    /**
     * Get PDO connection
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Execute a query with parameters
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Database query failed");
        }
    }

    /**
     * Fetch a single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert a record and return last insert ID
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ':' . $field; }, $fields);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $field => $value) {
            $params[':' . $field] = $value;
        }

        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $setParts[] = "$field = :$field";
            $params[':' . $field] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge($params, $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Delete records
     */
    public function delete($table, $where, $params = []) {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
