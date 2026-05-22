<?php
// Authentication Functions
// Hotel Reservation System

session_start();

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Function to login user
function loginUser($username, $password) {
    require_once __DIR__ . '/../config/database.php';
    
    // Trim whitespace from inputs
    $username = trim($username);
    $password = trim($password);
    
    error_log("LOGIN: Attempting login with username='$username', password='$password'");
    
    try {
        $db = new Database();
        $escaped_username = $db->escape($username);
        
        error_log("LOGIN: Escaped username='$escaped_username'");
        
        $sql = "SELECT * FROM users WHERE username = '$escaped_username'";
        error_log("LOGIN: Query=$sql");
        
        $result = $db->query($sql);
        error_log("LOGIN: Query result type=" . gettype($result) . ", num_rows=" . $result->num_rows);
        
        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();
            error_log("LOGIN: User found. Password in DB='" . $user['password'] . "', entered='" . $password . "'");
            
            // Direct password comparison (plain text)
            if ($user && $password === $user['password']) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                error_log("LOGIN: Password matched! User ID=" . $user['user_id']);
                return true;
            } else {
                error_log("LOGIN: Password mismatch!");
            }
        } else {
            error_log("LOGIN: User not found!");
        }
        
        return false;
    } catch (Exception $e) {
        error_log("LOGIN ERROR: " . $e->getMessage());
        return false;
    }
}

// Function to logout user
function logoutUser() {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Function to get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

// Function to display user role badge
function getRoleBadge($role) {
    if ($role == 'admin') {
        return '<span class="badge bg-danger">Admin</span>';
    } else {
        return '<span class="badge bg-primary">User</span>';
    }
}

// Function to hash password (for registration)
function hashPassword($password) {
    // Plain text password - no hashing
    return $password;
}

// Function to verify password
function verifyPassword($password, $hash) {
    // Direct comparison for plain text passwords
    return $password == $hash;
}
?>
