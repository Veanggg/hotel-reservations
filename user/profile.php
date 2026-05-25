<?php
// User Profile Page
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireLogin();

// Prevent admin access
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$user_id = $_SESSION['user_id'];

function isDigitsOnly($value) {
    return preg_match('/^[0-9]+$/', $value) === 1;
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $full_name = $db->escape($_POST['full_name']);
    $email = $db->escape($_POST['email']);
    $phone = $db->escape($_POST['phone']);

    if (!isDigitsOnly($_POST['phone'])) {
        $error = "Phone number must contain numbers only.";
    } else {
        $sql = "UPDATE users SET full_name = '$full_name', email = '$email', phone = '$phone' 
                WHERE user_id = $user_id";
        $db->query($sql);
        
        // Update session variables
        $_SESSION['full_name'] = $full_name;
        
        $success = "Profile updated successfully!";
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $userResult = $db->query("SELECT password FROM users WHERE user_id = $user_id");
    $user = $userResult->fetch_assoc();
    
    if ($current_password == $user['password']) {
        if ($new_password === $confirm_password) {
            // Store password as plain text
            $sql = "UPDATE users SET password = '$new_password' WHERE user_id = $user_id";
            $db->query($sql);
            $password_success = "Password changed successfully!";
        } else {
            $password_error = "New passwords do not match!";
        }
    } else {
        $password_error = "Current password is incorrect!";
    }
}

// Get current user information
$userResult = $db->query("SELECT * FROM users WHERE user_id = $user_id");
$user = $userResult->fetch_assoc();

// Get user's reservation statistics
$statsQuery = "
    SELECT 
        COUNT(r.reservation_id) as total_reservations,
        COALESCE(SUM(r.total_amount), 0) as total_spent,
        COUNT(CASE WHEN r.status = 'confirmed' THEN 1 END) as upcoming_reservations,
        COUNT(CASE WHEN r.status = 'checked_out' THEN 1 END) as completed_reservations
    FROM reservations r
    WHERE r.guest_id = (SELECT guest_id FROM guests WHERE email = '{$user['email']}')
";
$statsResult = $db->query($statsQuery);
$stats = $statsResult->fetch_assoc();

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../node_modules/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">Hotel Guest</h4>
                        <small class="text-white-50"><?php echo $_SESSION['full_name']; ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="bi bi-calendar-check me-2"></i> My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="bi bi-person me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header">
                    <div>
                        <h2 class="mb-0">My Profile</h2>
                        <p class="text-muted mb-0">Manage your personal information and account settings</p>
                    </div>
                </div>

                <!-- Profile Overview -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="profile-card text-center">
                            <div class="avatar">
                                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                            </div>
                            <h4><?php echo $user['full_name']; ?></h4>
                            <p class="text-muted"><?php echo $user['email']; ?></p>
                            <span class="badge bg-primary">Guest Account</span>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <h3><?php echo $stats['total_reservations']; ?></h3>
                                    <p>Total Reservations</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <h3>₱<?php echo number_format($stats['total_spent'], 0); ?></h3>
                                    <p>Total Spent</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <h3><?php echo $stats['upcoming_reservations']; ?></h3>
                                    <p>Upcoming</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <h3><?php echo $stats['completed_reservations']; ?></h3>
                                    <p>Completed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="profile-card">
                    <h5 class="mb-4"><i class="bi bi-person-gear me-2"></i>Edit Profile Information</h5>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo $user['full_name']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo $user['username']; ?>" readonly>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo $user['email']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone"
                                       inputmode="numeric" pattern="[0-9]+" maxlength="12"
                                       title="Phone number must contain numbers only"
                                       value="<?php echo $user['phone']; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Type</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo date('M d, Y', strtotime($user['created_at'])); ?>" readonly>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="profile-card">
                    <h5 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                    
                    <?php if (isset($password_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $password_success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $password_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-shield-check me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('input[name="phone"]').forEach((phoneInput) => {
            phoneInput.addEventListener('input', () => {
                phoneInput.value = phoneInput.value.replace(/\D/g, '');
            });
        });
    </script>
</body>
</html>
