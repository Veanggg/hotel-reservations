<?php
// Guests Management - CRUD Operations
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$db = new Database();

function isDigitsOnly($value) {
    return preg_match('/^[0-9]+$/', $value) === 1;
}

function isOptionalDigitsOnly($value) {
    return $value === '' || isDigitsOnly($value);
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $first_name = $db->escape($_POST['first_name']);
                $last_name = $db->escape($_POST['last_name']);
                $email = $db->escape($_POST['email']);
                $phone = $db->escape($_POST['phone']);
                $address = $db->escape($_POST['address']);
                $id_number = $db->escape($_POST['id_number']);

                if (!isDigitsOnly($_POST['phone'])) {
                    $error = "Phone number must contain numbers only.";
                    break;
                }

                if (!isOptionalDigitsOnly($_POST['id_number'])) {
                    $error = "ID number must contain numbers only.";
                    break;
                }
                
                $sql = "INSERT INTO guests (first_name, last_name, email, phone, address, id_number) 
                        VALUES ('$first_name', '$last_name', '$email', '$phone', '$address', '$id_number')";
                $db->query($sql);
                $success = "Guest added successfully!";
                break;
                
            case 'edit':
                $guest_id = (int)$_POST['guest_id'];
                $first_name = $db->escape($_POST['first_name']);
                $last_name = $db->escape($_POST['last_name']);
                $email = $db->escape($_POST['email']);
                $phone = $db->escape($_POST['phone']);
                $address = $db->escape($_POST['address']);
                $id_number = $db->escape($_POST['id_number']);

                if (!isDigitsOnly($_POST['phone'])) {
                    $error = "Phone number must contain numbers only.";
                    break;
                }

                if (!isOptionalDigitsOnly($_POST['id_number'])) {
                    $error = "ID number must contain numbers only.";
                    break;
                }
                
                $sql = "UPDATE guests SET first_name = '$first_name', last_name = '$last_name', 
                        email = '$email', phone = '$phone', address = '$address', id_number = '$id_number' 
                        WHERE guest_id = $guest_id";
                $db->query($sql);
                $success = "Guest updated successfully!";
                break;
                
            case 'delete':
                $guest_id = $_POST['guest_id'];
                
                // Check if guest has reservations
                $checkResult = $db->query("SELECT COUNT(*) as count FROM reservations WHERE guest_id = $guest_id");
                $count = $checkResult->fetch_assoc()['count'];
                
                if ($count == 0) {
                    $sql = "DELETE FROM guests WHERE guest_id = $guest_id";
                    $db->query($sql);
                    $success = "Guest deleted successfully!";
                } else {
                    $error = "Cannot delete guest with existing reservations";
                }
                break;
        }
    }
}

// Handle search
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT g.*, 
               COUNT(r.reservation_id) as total_reservations,
               COALESCE(SUM(r.total_amount), 0) as total_spent
        FROM guests g 
        LEFT JOIN reservations r ON g.guest_id = r.guest_id";

if ($search) {
    $search = $db->escape($search);
    $sql .= " WHERE (g.first_name LIKE '%$search%' OR g.last_name LIKE '%$search%' OR g.email LIKE '%$search%' OR g.phone LIKE '%$search%')";
}

$sql .= " GROUP BY g.guest_id ORDER BY g.created_at DESC";

$guestsResult = $db->query($sql);

// Get guest for editing
$editGuest = null;
if (isset($_GET['edit'])) {
    $guest_id = (int)$_GET['edit'];
    $editResult = $db->query("SELECT * FROM guests WHERE guest_id = $guest_id");
    $editGuest = $editResult->fetch_assoc();
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests Management - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
        .guest-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .guest-card:hover {
            transform: translateY(-3px);
        }
        .guest-avatar {
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
        
        /* Enhanced Alert Styling */
        .alert {
            border: none;
            border-left: 4px solid;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .alert i {
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .alert-success {
            background-color: #e8f5e9;
            border-left-color: #4caf50;
            color: #2e7d32;
        }
        .alert-danger {
            background-color: #ffebee;
            border-left-color: #f44336;
            color: #c62828;
        }
        .alert-warning {
            background-color: #fff9c4;
            border-left-color: #ff9800;
            color: #f57c00;
        }
        .alert-info {
            background-color: #e3f2fd;
            border-left-color: #2196f3;
            color: #1565c0;
        }
        .alert .btn-close {
            margin-left: auto;
        }
        .alert strong {
            font-weight: 600;
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
                            <a class="nav-link active" href="guests.php">
                                <i class="bi bi-people me-2"></i> Guests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-graph-up me-2"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
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
                            <h2 class="mb-0">Guests Management</h2>
                            <p class="text-muted mb-0">Manage hotel guests and their information</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#guestModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Guest
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>
                        <div>
                            <strong>Success!</strong> <?php echo $success; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>
                            <strong>Error!</strong> <?php echo $error; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by name, email, or phone..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Guests Grid -->
                <div class="row">
                    <?php while ($guest = $guestsResult->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="guest-card">
                            <div class="d-flex align-items-start mb-3">
                                <div class="guest-avatar me-3">
                                    <?php echo strtoupper(substr($guest['first_name'], 0, 1) . substr($guest['last_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?php echo $guest['first_name'] . ' ' . $guest['last_name']; ?></h5>
                                    <small class="text-muted">Guest ID: #<?php echo $guest['guest_id']; ?></small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><i class="bi bi-envelope me-2"></i><?php echo $guest['email']; ?></p>
                                <p class="mb-1"><i class="bi bi-phone me-2"></i><?php echo $guest['phone']; ?></p>
                                <?php if ($guest['address']): ?>
                                <p class="mb-1"><i class="bi bi-geo-alt me-2"></i><?php echo $guest['address']; ?></p>
                                <?php endif; ?>
                                <?php if ($guest['id_number']): ?>
                                <p class="mb-1"><i class="bi bi-card-text me-2"></i>ID: <?php echo $guest['id_number']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Reservations:</strong> <?php echo $guest['total_reservations']; ?><br>
                                    <strong>Total Spent:</strong> ₱<?php echo number_format($guest['total_spent'], 2); ?>
                                </small>
                            </div>
                            
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editGuest(<?php echo $guest['guest_id']; ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="viewReservations(<?php echo $guest['guest_id']; ?>)">
                                    <i class="bi bi-calendar"></i> Reservations
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteGuest(<?php echo $guest['guest_id']; ?>, '<?php echo $guest['first_name'] . ' ' . $guest['last_name']; ?>')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <!-- Guest Modal -->
                <div class="modal fade" id="guestModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitle">
                                    <?php echo $editGuest ? 'Edit Guest' : 'Add New Guest'; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="<?php echo $editGuest ? 'edit' : 'add'; ?>">
                                    <?php if ($editGuest): ?>
                                        <input type="hidden" name="guest_id" value="<?php echo $editGuest['guest_id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">First Name</label>
                                            <input type="text" class="form-control" name="first_name" required
                                                   value="<?php echo $editGuest['first_name'] ?? ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" class="form-control" name="last_name" required
                                                   value="<?php echo $editGuest['last_name'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required
                                               value="<?php echo $editGuest['email'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" required
                                               inputmode="numeric" pattern="[0-9]+" maxlength="20"
                                               title="Phone number must contain numbers only"
                                               value="<?php echo $editGuest['phone'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2"><?php echo $editGuest['address'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ID Number</label>
                                        <input type="tel" class="form-control" name="id_number"
                                               inputmode="numeric" pattern="[0-9]*" maxlength="50"
                                               title="ID number must contain numbers only"
                                               value="<?php echo $editGuest['id_number'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $editGuest ? 'Update Guest' : 'Add Guest'; ?>
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
        function editGuest(guestId) {
            window.location.href = 'guests.php?edit=' + guestId;
        }
        
        function deleteGuest(guestId, guestName) {
            if (confirm('Are you sure you want to delete guest ' + guestName + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="guest_id" value="${guestId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewReservations(guestId) {
            window.location.href = 'reservations.php?search=' + guestId;
        }
        
        // Auto-open modal if editing
        <?php if ($editGuest): ?>
            const modal = new bootstrap.Modal(document.getElementById('guestModal'));
            modal.show();
        <?php endif; ?>

        document.querySelectorAll('input[name="phone"]').forEach((phoneInput) => {
            phoneInput.addEventListener('input', () => {
                phoneInput.value = phoneInput.value.replace(/\D/g, '');
            });
        });

        document.querySelectorAll('input[name="id_number"]').forEach((idInput) => {
            idInput.addEventListener('input', () => {
                idInput.value = idInput.value.replace(/\D/g, '');
            });
        });
    </script>
</body>
</html>
