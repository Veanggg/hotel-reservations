<?php
// Database Connection Class
// Hotel Reservation System

error_reporting(E_ALL);
ini_set('display_errors', 1);

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "hotel_reservation";
    private $connection;
    
    // Constructor - establish database connection
    public function __construct() {
        $this->connection = $this->getConnection();
    }
    
    // Get database connection
    private function getConnection() {
        try {
            $conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            // Check connection
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // Set charset to utf8
            $conn->set_charset("utf8");
            
            return $conn;
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    // Execute query and return results
    public function query($sql) {
        $result = $this->connection->query($sql);

        if (!$result) {
            throw new Exception("Query failed: " . $this->connection->error);
        }

        return $result;
    }
    
    // Execute prepared statement (for security)
    public function prepare($sql) {
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->connection->error);
        }

        return $stmt;
    }
    
    // Escape string to prevent SQL injection
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    // Get last inserted ID
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    // Get number of affected rows
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }
    
    // Close connection
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    // Begin transaction
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    // Commit transaction
    public function commit() {
        $this->connection->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        $this->connection->rollback();
    }
}

// Helper functions for common database operations

// Function to execute SELECT query and return all rows
function selectAll($sql) {
    $db = new Database();
    $result = $db->query($sql);
    $rows = [];
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $db->close();
    return $rows;
}

// Function to execute SELECT query and return single row
function selectOne($sql) {
    $db = new Database();
    $result = $db->query($sql);
    $row = $result->fetch_assoc();
    $db->close();
    return $row;
}

// Function to execute INSERT/UPDATE/DELETE query
function executeQuery($sql) {
    $db = new Database();
    $db->query($sql);
    $affected = $db->getAffectedRows();
    $lastId = $db->getLastInsertId();
    $db->close();
    
    return [
        'affected_rows' => $affected,
        'last_id' => $lastId
    ];
}

// Function to check if record exists
function recordExists($table, $condition) {
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $condition";
    $result = selectOne($sql);
    return $result['count'] > 0;
}
?>
