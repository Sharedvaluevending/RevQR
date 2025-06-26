<?php
/**
 * Database Connection Handler
 * RevenueQR Platform - Core Database Connectivity
 */

// Prevent direct access
if (!defined('APP_URL')) {
    die('Direct access not permitted');
}

// Database configuration
$db_config = [
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASS,
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

// Global database connection
$pdo = null;

/**
 * Get database connection
 * @return PDO Database connection
 * @throws Exception on connection failure
 */
function get_db_connection() {
    global $pdo, $db_config;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['database']};charset={$db_config['charset']}";
            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
            
            // Test connection
            $pdo->query('SELECT 1');
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}

/**
 * Execute a prepared statement
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for the query
 * @return PDOStatement
 */
function db_execute($sql, $params = []) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql);
        throw new Exception("Database query failed.");
    }
}

/**
 * Fetch a single row
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array|false
 */
function db_fetch($sql, $params = []) {
    $stmt = db_execute($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array
 */
function db_fetch_all($sql, $params = []) {
    $stmt = db_execute($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Get the last insert ID
 * @return string
 */
function db_last_insert_id() {
    $pdo = get_db_connection();
    return $pdo->lastInsertId();
}

/**
 * Start a database transaction
 */
function db_begin_transaction() {
    $pdo = get_db_connection();
    return $pdo->beginTransaction();
}

/**
 * Commit a database transaction
 */
function db_commit() {
    $pdo = get_db_connection();
    return $pdo->commit();
}

/**
 * Rollback a database transaction
 */
function db_rollback() {
    $pdo = get_db_connection();
    return $pdo->rollback();
}

/**
 * Check database connection health
 * @return array Connection status information
 */
function db_health_check() {
    try {
        $pdo = get_db_connection();
        $start_time = microtime(true);
        
        // Test basic connectivity
        $stmt = $pdo->query('SELECT 1 as test');
        $result = $stmt->fetch();
        
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        // Get database info
        $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $info = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        
        return [
            'status' => 'healthy',
            'response_time_ms' => $response_time,
            'version' => $version,
            'info' => $info,
            'charset' => 'utf8mb4',
            'test_result' => $result['test'] ?? null
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'response_time_ms' => null
        ];
    }
}

/**
 * Get database statistics
 * @return array Database usage statistics
 */
function db_get_stats() {
    try {
        $pdo = get_db_connection();
        
        // Get table count and sizes
        $tables_query = "
            SELECT 
                COUNT(*) as table_count,
                SUM(data_length + index_length) as total_size
            FROM information_schema.tables 
            WHERE table_schema = ?
        ";
        
        $stats = db_fetch($tables_query, [DB_NAME]);
        
        // Get connection info
        $connections = db_fetch("SHOW STATUS LIKE 'Threads_connected'");
        $max_connections = db_fetch("SHOW VARIABLES LIKE 'max_connections'");
        
        return [
            'table_count' => $stats['table_count'] ?? 0,
            'total_size_bytes' => $stats['total_size'] ?? 0,
            'total_size_mb' => round(($stats['total_size'] ?? 0) / 1024 / 1024, 2),
            'active_connections' => $connections['Value'] ?? 0,
            'max_connections' => $max_connections['Value'] ?? 0,
            'database_name' => DB_NAME
        ];
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

// Initialize connection for apps that need it immediately
try {
    get_db_connection();
} catch (Exception $e) {
    // Log error but don't fail - some scripts may not need DB
    error_log("Database initialization warning: " . $e->getMessage());
}
?> 