<?php
// Test login function
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/auth.php';

echo "<h2>Login Test</h2>";
echo "<pre>";

try {
    // Test database connection
    echo "=== Testing Database Connection ===\n";
    $db = new Database();
    echo "✓ Database connection successful\n\n";
    
    // Test user query
    echo "=== Testing User Query ===\n";
    $sql = "SELECT * FROM users";
    $result = $db->query($sql);
    echo "✓ Query executed successfully\n";
    echo "Number of users: " . $result->num_rows . "\n\n";
    
    // Display users
    echo "=== Users in Database ===\n";
    while ($user = $result->fetch_assoc()) {
        echo "Username: " . $user['username'] . " | Password: " . $user['password'] . " | Role: " . $user['role'] . "\n";
    }
    
    // Test login function
    echo "\n=== Testing Login Function ===\n";
    $login_result = loginUser('admin', '12345678');
    echo "Login result: " . ($login_result ? "SUCCESS" : "FAILED") . "\n";
    
    if (isset($_SESSION['user_id'])) {
        echo "Session user_id: " . $_SESSION['user_id'] . "\n";
        echo "Session username: " . $_SESSION['username'] . "\n";
        echo "Session role: " . $_SESSION['user_role'] . "\n";
    } else {
        echo "No session variables set\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";
?>
