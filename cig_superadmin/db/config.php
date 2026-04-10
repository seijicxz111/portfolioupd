<?php
/**
 * Database Configuration
 * CIG Admin Dashboard
 */

// Database Credentials
// Supports both Railway/cloud (env vars) and XAMPP localhost
define('DB_HOST', (getenv('MYSQLHOST')     !== false && getenv('MYSQLHOST')     !== '') ? getenv('MYSQLHOST')     : 'localhost');
define('DB_USER', (getenv('MYSQLUSER')     !== false && getenv('MYSQLUSER')     !== '') ? getenv('MYSQLUSER')     : 'root');
define('DB_PASS', (getenv('MYSQLPASSWORD') !== false && getenv('MYSQLPASSWORD') !== '') ? getenv('MYSQLPASSWORD') : '');
define('DB_NAME', (getenv('MYSQLDATABASE') !== false && getenv('MYSQLDATABASE') !== '') ? getenv('MYSQLDATABASE') : 'cig_system');
define('DB_PORT', (int)((getenv('MYSQLPORT') !== false && getenv('MYSQLPORT') !== '') ? getenv('MYSQLPORT') : 3306));
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('DEBUG_MODE', true);
define('APP_NAME', 'CIG Admin Dashboard');

// Security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);

/**
 * Database Class
 * Simple wrapper for MySQLi connection
 */
class Database {
    private $conn;
    
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($this->conn->connect_error) {
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset(DB_CHARSET);
    }
    
    /**
     * Fetch a single row
     */
    public function fetchRow($query, $params = []) {
        if ($stmt = $this->conn->prepare($query)) {
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        }
        throw new Exception("Query failed: " . $this->conn->error);
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($query, $params = []) {
        if ($stmt = $this->conn->prepare($query)) {
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        throw new Exception("Query failed: " . $this->conn->error);
    }
    
    /**
     * Insert a record
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $values = implode(',', array_fill(0, count($data), '?'));
        $query = "INSERT INTO $table ($columns) VALUES ($values)";
        
        if ($stmt = $this->conn->prepare($query)) {
            $types = str_repeat('s', count($data));
            $stmt->bind_param($types, ...array_values($data));
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
        }
        throw new Exception("Insert failed: " . $this->conn->error);
    }
    
    /**
     * Update a record
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
        $query = "UPDATE $table SET $set WHERE $where";
        
        if ($stmt = $this->conn->prepare($query)) {
            $params = array_merge(array_values($data), $whereParams);
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                return $stmt->affected_rows;
            }
        }
        throw new Exception("Update failed: " . $this->conn->error);
    }
    
    /**
     * Delete a record
     */
    public function delete($table, $where, $params = []) {
        $query = "DELETE FROM $table WHERE $where";
        
        if ($stmt = $this->conn->prepare($query)) {
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            if ($stmt->execute()) {
                return $stmt->affected_rows;
            }
        }
        throw new Exception("Delete failed: " . $this->conn->error);
    }
    
    /**
     * Close connection
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>