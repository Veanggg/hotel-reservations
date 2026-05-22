<?php
// Rooms Management - CRUD Operations
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$db = new Database();

// Handle form submissions
$error = null;
$warning = null;
$pageError = null;
$roomNumberError = null;
$showRoomModal = false;
$formData = [
    'room_number' => null,
    'type_id' => null,
    'floor_number' => null,
    'status' => null
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $showRoomModal = true;
                $formData['room_number'] = trim($_POST['room_number']);
                $formData['type_id'] = $_POST['type_id'];
                $formData['floor_number'] = $_POST['floor_number'];
                $formData['status'] = $_POST['status'];

                if ($formData['room_number'] === '' || !preg_match('/^\d+$/', $formData['room_number'])) {
                    $roomNumberError = 'Room number must contain only digits.';
                    $warning = 'Please fix the invalid room number.';
                    break;
                }

                $room_number = $db->escape($formData['room_number']);
                try {
                    $duplicateCheck = $db->query("SELECT room_id FROM rooms WHERE room_number = '$room_number'");
                    if ($duplicateCheck->num_rows > 0) {
                        $roomNumberError = "Room number $room_number is already in use.";
                        $warning = 'Please choose a different room number.';
                        break;
                    }

                    $sql = "INSERT INTO rooms (room_number, type_id, floor_number, status) 
                            VALUES ('$room_number', {$formData['type_id']}, {$formData['floor_number']}, '{$formData['status']}')";
                    $db->query($sql);
                    $success = "Room added successfully!";
                    $showRoomModal = false;
                } catch (Exception $e) {
                    $error = "Error adding room: " . $e->getMessage();
                }
                break;
                
            case 'edit':
                $showRoomModal = true;
                $room_id = (int) $_POST['room_id'];
                $formData['room_id'] = $room_id;
                $formData['room_number'] = trim($_POST['room_number']);
                $formData['type_id'] = $_POST['type_id'];
                $formData['floor_number'] = $_POST['floor_number'];
                $formData['status'] = $_POST['status'];
                $editRoom = ['room_id' => $room_id];

                if ($formData['room_number'] === '' || !preg_match('/^\d+$/', $formData['room_number'])) {
                    $roomNumberError = 'Room number must contain only digits.';
                    $warning = 'Please fix the invalid room number.';
                    break;
                }

                $room_number = $db->escape($formData['room_number']);
                try {
                    $duplicateCheck = $db->query("SELECT room_id FROM rooms WHERE room_number = '$room_number' AND room_id <> $room_id");
                    if ($duplicateCheck->num_rows > 0) {
                        $roomNumberError = "Room number $room_number is already in use.";
                        $warning = 'Please choose a different room number.';
                        break;
                    }

                    $sql = "UPDATE rooms SET room_number = '$room_number', type_id = {$formData['type_id']}, 
                            floor_number = {$formData['floor_number']}, status = '{$formData['status']}' 
                            WHERE room_id = $room_id";
                    $db->query($sql);
                    $success = "Room updated successfully!";
                    $showRoomModal = false;
                } catch (Exception $e) {
                    $error = "Error updating room: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $room_id = $_POST['room_id'];
                $sql = "DELETE FROM rooms WHERE room_id = $room_id";
                try {
                    $db->query($sql);
                    $success = "Room deleted successfully!";
                } catch (Exception $e) {
                    $error = "Error deleting room: " . $e->getMessage();
                }
                break;
        }
    }
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

// Build query
$sql = "SELECT r.*, rt.type_name, rt.base_price 
        FROM rooms r 
        JOIN room_types rt ON r.type_id = rt.type_id 
        WHERE 1=1";

if ($search) {
    $search = $db->escape($search);
    $sql .= " AND (r.room_number LIKE '%$search%' OR rt.type_name LIKE '%$search%')";
}

if ($filter_type) {
    $sql .= " AND r.type_id = $filter_type";
}

if ($filter_status) {
    $sql .= " AND r.status = '$filter_status'";
}

$sql .= " ORDER BY r.floor_number, r.room_number";

try {
    $roomsResult = $db->query($sql);
} catch (Exception $e) {
    $pageError = "Error loading rooms: " . $e->getMessage();
}

// Get room types for dropdown
$roomTypes = [];
try {
    $roomTypesResult = $db->query("SELECT * FROM room_types ORDER BY type_name");
    if ($roomTypesResult) {
        $roomTypes = $roomTypesResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $pageError = $pageError ? $pageError . "<br>" . "Error loading room types: " . $e->getMessage() : "Error loading room types: " . $e->getMessage();
}

// Get room for editing (if edit mode)
$editRoom = null;
if (isset($_GET['edit'])) {
    $room_id = $_GET['edit'];
    try {
        $editResult = $db->query("SELECT * FROM rooms WHERE room_id = $room_id");
        $editRoom = $editResult->fetch_assoc();
    } catch (Exception $e) {
        $pageError = $pageError ? $pageError . "<br>" . "Error loading room for edit: " . $e->getMessage() : "Error loading room for edit: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms Management - Hotel Reservation System</title>
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
        .room-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .room-card:hover {
            transform: translateY(-3px);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-available { background: #d4edda; color: #155724; }
        .status-occupied { background: #f8d7da; color: #721c24; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .status-reserved { background: #d1ecf1; color: #0c5460; }
        
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
                            <a class="nav-link active" href="rooms.php">
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
                            <h2 class="mb-0">Rooms Management</h2>
                            <p class="text-muted mb-0">Manage hotel rooms and their status</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Room
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

                <?php if ($warning): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div>
                            <strong>Warning!</strong> <?php echo $warning; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>
                            <strong>Error!</strong> <?php echo $error; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($pageError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i>
                        <div>
                            <strong>Error!</strong> <?php echo $pageError; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by room number or type..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="filter_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?php echo $type['type_id']; ?>" 
                                                <?php echo $filter_type == $type['type_id'] ? 'selected' : ''; ?>>
                                            <?php echo $type['type_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="filter_status">
                                    <option value="">All Status</option>
                                    <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="occupied" <?php echo $filter_status == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                    <option value="maintenance" <?php echo $filter_status == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="reserved" <?php echo $filter_status == 'reserved' ? 'selected' : ''; ?>>Reserved</option>
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

                <!-- Rooms Grid -->
                <div class="row">
                    <?php 
                    if (isset($roomsResult) && $roomsResult):
                    while ($room = $roomsResult->fetch_assoc()): 
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="room-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">Room <?php echo $room['room_number']; ?></h5>
                                    <small class="text-muted">Floor <?php echo $room['floor_number']; ?></small>
                                </div>
                                <span class="status-badge status-<?php echo $room['status']; ?>">
                                    <?php echo ucfirst($room['status']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><strong>Type:</strong> <?php echo $room['type_name']; ?></p>
                                <p class="mb-1"><strong>Price:</strong> ₱<?php echo number_format($room['base_price'], 2); ?>/night</p>
                            </div>
                            
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editRoom(<?php echo $room['room_id']; ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo $room['room_number']; ?>')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <!-- Room Modal -->
                <div class="modal fade" id="roomModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitle">
                                    <?php echo $editRoom ? 'Edit Room' : 'Add New Room'; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="<?php echo $editRoom ? 'edit' : 'add'; ?>">
                                    <?php if ($editRoom): ?>
                                        <input type="hidden" name="room_id" value="<?php echo $editRoom['room_id']; ?>">
                                    <?php endif; ?>
                                    
                                    <?php if ($warning && $showRoomModal): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <?php echo $warning; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label">Room Number</label>
                                        <input type="number" class="form-control <?php echo $roomNumberError ? 'is-invalid' : ''; ?>" name="room_number" required
                                               value="<?php echo htmlspecialchars($formData['room_number'] ?? $editRoom['room_number'] ?? ''); ?>">
                                        <?php if ($roomNumberError): ?>
                                            <div class="invalid-feedback">
                                                <?php echo $roomNumberError; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Room Type</label>
                                        <select class="form-select" name="type_id" required>
                                            <?php foreach ($roomTypes as $type): ?>
                                                <option value="<?php echo $type['type_id']; ?>"
                                                        <?php echo ((isset($formData['type_id']) && $formData['type_id'] == $type['type_id']) || ($editRoom && !isset($formData['type_id']) && $editRoom['type_id'] == $type['type_id'])) ? 'selected' : ''; ?>>
                                                    <?php echo $type['type_name']; ?> - ₱<?php echo number_format($type['base_price'], 2); ?>/night
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Floor Number</label>
                                        <input type="number" class="form-control" name="floor_number" required
                                               value="<?php echo htmlspecialchars($formData['floor_number'] ?? $editRoom['floor_number'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" required>
                                            <option value="available" <?php echo ((isset($formData['status']) && $formData['status'] === 'available') || ($editRoom && !isset($formData['status']) && $editRoom['status'] === 'available')) ? 'selected' : ''; ?>>Available</option>
                                            <option value="occupied" <?php echo ((isset($formData['status']) && $formData['status'] === 'occupied') || ($editRoom && !isset($formData['status']) && $editRoom['status'] === 'occupied')) ? 'selected' : ''; ?>>Occupied</option>
                                            <option value="maintenance" <?php echo ((isset($formData['status']) && $formData['status'] === 'maintenance') || ($editRoom && !isset($formData['status']) && $editRoom['status'] === 'maintenance')) ? 'selected' : ''; ?>>Maintenance</option>
                                            <option value="reserved" <?php echo ((isset($formData['status']) && $formData['status'] === 'reserved') || ($editRoom && !isset($formData['status']) && $editRoom['status'] === 'reserved')) ? 'selected' : ''; ?>>Reserved</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $editRoom ? 'Update Room' : 'Add Room'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteRoomModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" id="deleteRoomForm">
                                <div class="modal-body">
                                    <p>Are you sure you want to delete room <strong id="deleteRoomNumber"></strong>?</p>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="room_id" id="deleteRoomId">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete Room</button>
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
        function editRoom(roomId) {
            window.location.href = 'rooms.php?edit=' + roomId;
        }
        
        function deleteRoom(roomId, roomNumber) {
            document.getElementById('deleteRoomNumber').textContent = roomNumber;
            document.getElementById('deleteRoomId').value = roomId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteRoomModal'));
            deleteModal.show();
        }
        
        // Auto-open modal if editing or after form validation warning
        <?php if ($editRoom || $showRoomModal): ?>
            const modal = new bootstrap.Modal(document.getElementById('roomModal'));
            modal.show();
        <?php endif; ?>
    </script>
</body>
</html>
