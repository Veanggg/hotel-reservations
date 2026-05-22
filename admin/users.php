<?php
// Users Management - CRUD Operations
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$db = new Database();

function isDigitsOnly($value) {
    return preg_match('/^[0-9]+$/', $value) === 1;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = $db->escape($_POST['username']);
                $password = $db->escape($_POST['password']);
                $full_name = $db->escape($_POST['full_name']);
                $email = $db->escape($_POST['email']);
                $phone = $db->escape($_POST['phone']);
                $role = $_POST['role'];

                if (!isDigitsOnly($_POST['phone'])) {
                    $error = "Phone number must contain numbers only.";
                    break;
                }
                
                $sql = "INSERT INTO users (username, password, full_name, email, phone, role) 
                        VALUES ('$username', '$password', '$full_name', '$email', '$phone', '$role')";
                $db->query($sql);
                $success = "User added successfully!";
                break;
                
            case 'edit':
                $user_id = (int)$_POST['user_id'];
                $username = $db->escape($_POST['username']);
                $full_name = $db->escape($_POST['full_name']);
                $email = $db->escape($_POST['email']);
                $phone = $db->escape($_POST['phone']);
                $role = $_POST['role'];

                if (!isDigitsOnly($_POST['phone'])) {
                    $error = "Phone number must contain numbers only.";
                    break;
                }
                
                // Check if password should be updated
                if (!empty($_POST['password'])) {
                    $password = $db->escape($_POST['password']);
                    $sql = "UPDATE users SET username = '$username', password = '$password', 
                            full_name = '$full_name', email = '$email', phone = '$phone', role = '$role' 
                            WHERE user_id = $user_id";
                } else {
                    $sql = "UPDATE users SET username = '$username', full_name = '$full_name', 
                            email = '$email', phone = '$phone', role = '$role' 
                            WHERE user_id = $user_id";
                }
                $db->query($sql);
                $success = "User updated successfully!";
                break;
                
            case 'delete':
                $user_id = (int)$_POST['user_id'];
                
                // Don't allow deletion of current admin
                if ($user_id == $_SESSION['user_id']) {
                    $error = "Cannot delete your own account!";
                } else {
                    $reservationCheck = $db->query("SELECT COUNT(*) AS total FROM reservations WHERE created_by = $user_id");
                    $reservationCount = (int)$reservationCheck->fetch_assoc()['total'];

                    if ($reservationCount > 0) {
                        $error = "Cannot delete this user because they created $reservationCount reservation" . ($reservationCount === 1 ? "" : "s") . ".";
                    } else {
                        $sql = "DELETE FROM users WHERE user_id = $user_id";
                        $db->query($sql);
                        $success = "User deleted successfully!";
                    }
                }
                break;
        }
    }
}

// Handle search
$search = $_GET['search'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';

// Build query
$sql = "SELECT u.*, 
               COUNT(r.reservation_id) as reservation_count
        FROM users u 
        LEFT JOIN reservations r ON u.user_id = r.created_by";

if ($search) {
    $search = $db->escape($search);
    $sql .= " WHERE (u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR u.email LIKE '%$search%')";
}

if ($filter_role) {
    $sql .= ($search ? " AND" : " WHERE") . " u.role = '$filter_role'";
}

$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";

$usersResult = $db->query($sql);

// Get user for editing
$editUser = null;
if (isset($_GET['edit'])) {
    $user_id = (int)$_GET['edit'];
    $editResult = $db->query("SELECT * FROM users WHERE user_id = $user_id");
    $editUser = $editResult->fetch_assoc();
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Hotel Reservation System</title>
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
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .user-card:hover {
            transform: translateY(-3px);
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
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
                        <h4 class="text-white">Hotel Admin</h4>
                        <small class="text-white-50"><?php echo $_SESSION['full_name']; ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rooms.php">
                                <i class="bi bi-door-closed me-2"></i> Rooms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reservations.php">
                                <i class="bi bi-calendar-check me-2"></i> Reservations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="guests.php">
                                <i class="bi bi-people me-2"></i> Guests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-graph-up me-2"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="bi bi-person-gear me-2"></i> Users
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
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0">Users Management</h2>
                            <p class="text-muted mb-0">Manage system users and their roles</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="bi bi-plus-circle me-2"></i>Add User
                        </button>
                    </div>
                </div>

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

                <!-- Search and Filter -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by username, name, or email..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="filter_role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="user" <?php echo $filter_role == 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Grid -->
                <div class="row">
                    <?php while ($user = $usersResult->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="user-card">
                            <div class="d-flex align-items-start mb-3">
                                <div class="user-avatar me-3">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?php echo $user['full_name']; ?></h5>
                                    <small class="text-muted">@<?php echo $user['username']; ?></small>
                                </div>
                                <div>
                                    <?php
                                    if ($user['role'] == 'admin') {
                                        echo '<span class="badge bg-danger">Admin</span>';
                                    } else {
                                        echo '<span class="badge bg-primary">User</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><i class="bi bi-envelope me-2"></i><?php echo $user['email']; ?></p>
                                <p class="mb-1"><i class="bi bi-phone me-2"></i><?php echo $user['phone']; ?></p>
                                <p class="mb-1"><i class="bi bi-calendar me-2"></i>Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Reservations:</strong> <?php echo $user['reservation_count']; ?>
                                </small>
                            </div>
                            
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editUser(<?php echo $user['user_id']; ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo $user['username']; ?>')"
                                        <?php echo $user['user_id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <!-- User Modal -->
                <div class="modal fade" id="userModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitle">
                                    <?php echo $editUser ? 'Edit User' : 'Add New User'; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="<?php echo $editUser ? 'edit' : 'add'; ?>">
                                    <?php if ($editUser): ?>
                                        <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="username" required
                                                   value="<?php echo $editUser['username'] ?? ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" name="full_name" required
                                                   value="<?php echo $editUser['full_name'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required
                                               value="<?php echo $editUser['email'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" required
                                               inputmode="numeric" pattern="[0-9]+" maxlength="20"
                                               title="Phone number must contain numbers only"
                                               value="<?php echo $editUser['phone'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Role</label>
                                            <select class="form-select" name="role" required>
                                                <option value="user" <?php echo ($editUser && $editUser['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo ($editUser && $editUser['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Password <?php echo $editUser ? '(leave blank to keep current)' : ''; ?></label>
                                            <input type="password" class="form-control" name="password" 
                                                   <?php echo $editUser ? '' : 'required'; ?>>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $editUser ? 'Update User' : 'Add User'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userId) {
            window.location.href = 'users.php?edit=' + userId;
        }
        
        function deleteUser(userId, username) {
            if (confirm('Are you sure you want to delete user ' + username + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-open modal if editing
        <?php if ($editUser): ?>
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        <?php endif; ?>

        document.querySelectorAll('input[name="phone"]').forEach((phoneInput) => {
            phoneInput.addEventListener('input', () => {
                phoneInput.value = phoneInput.value.replace(/\D/g, '');
            });
        });
    </script>
</body>
</html>
