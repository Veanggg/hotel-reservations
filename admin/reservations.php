<?php
// Reservations Management - CRUD Operations
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/availability.php';
require_once __DIR__ . '/../functions/reservation.php';

$db = new Database();
syncRoomAvailability($db);

function isValidDateRange($check_in_date, $check_in_time, $check_out_date, $check_out_time) {
    return isValidReservationDateTimeRange($check_in_date, $check_in_time, $check_out_date, $check_out_time);
}

function hasRoomDateConflict($db, $room_id, $check_in_datetime, $check_out_datetime, $exclude_reservation_id = 0) {
    return hasRoomDateTimeConflict($db, $room_id, $check_in_datetime, $check_out_datetime, $exclude_reservation_id);
}

function getRoomBasePrice($db, $room_id) {
    $sql = "SELECT rt.base_price
            FROM rooms r
            JOIN room_types rt ON r.type_id = rt.type_id
            WHERE r.room_id = ?
              AND r.status != 'maintenance'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();

    return $room ? (float)$room['base_price'] : null;
}

$currentCheckInDate = getCurrentHotelDate();
$currentCheckInTime = getCurrentHotelTimeForInput();

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $guest_id = (int)$_POST['guest_id'];
                $room_id = (int)$_POST['room_id'];
                $check_in_date = ($_POST['check_in_date'] ?? '') ?: getCurrentHotelDate();
                $check_in_time = ($_POST['check_in_time'] ?? '') ?: getCurrentHotelTime();
                $check_out_date = $_POST['check_out_date'];
                $check_out_time = $_POST['check_out_time'] ?? '12:00';
                $check_in_datetime = combineReservationDateTime($check_in_date, $check_in_time);
                $check_out_datetime = combineReservationDateTime($check_out_date, $check_out_time);
                $base_price = getRoomBasePrice($db, $room_id);
                $created_by = $_SESSION['user_id'];

                if ($base_price === null) {
                    $error = "Please select a valid room from the database.";
                    break;
                }

                if (!isValidDateRange($check_in_date, $check_in_time, $check_out_date, $check_out_time)) {
                    $error = "Check-out date/time must be after check-in date/time.";
                    break;
                }

                if (hasRoomDateConflict($db, $room_id, $check_in_datetime, $check_out_datetime)) {
                    $error = "This room is already booked for the selected range.";
                    break;
                }

                $total_amount = calculateReservationAmount($db, $room_id, $check_in_datetime, $check_out_datetime);
                $hasTimeColumns = hasReservationTimeColumns($db);

                if ($hasTimeColumns) {
                    $sql = "INSERT INTO reservations (guest_id, room_id, check_in_date, check_in_time, check_out_date, check_out_time, total_amount, status, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $status = 'confirmed';
                    $stmt->bind_param("iissssdsi", $guest_id, $room_id, $check_in_date, $check_in_time, $check_out_date, $check_out_time, $total_amount, $status, $created_by);
                } else {
                    $sql = "INSERT INTO reservations (guest_id, room_id, check_in_date, check_out_date, total_amount, status, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $status = 'confirmed';
                    $stmt->bind_param("iissdsi", $guest_id, $room_id, $check_in_date, $check_out_date, $total_amount, $status, $created_by);
                }
                if (!$stmt->execute()) {
                    $error = "Unable to create reservation: " . $stmt->error;
                    $stmt->close();
                    break;
                }
                $stmt->close();
                
                // Update room status to reserved only after the reservation is created successfully
                $sql = "UPDATE rooms SET status = 'reserved' WHERE room_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();
                
                $success = "Reservation added successfully!";
                break;
                
            case 'edit':
                $reservation_id = (int)$_POST['reservation_id'];
                $guest_id = (int)$_POST['guest_id'];
                $room_id = (int)$_POST['room_id'];
                $check_in_date = $_POST['check_in_date'];
                $check_in_time = $_POST['check_in_time'] ?? '14:00';
                $check_out_date = $_POST['check_out_date'];
                $check_out_time = $_POST['check_out_time'] ?? '12:00';
                $check_in_datetime = combineReservationDateTime($check_in_date, $check_in_time);
                $check_out_datetime = combineReservationDateTime($check_out_date, $check_out_time);
                $base_price = getRoomBasePrice($db, $room_id);
                $status = $_POST['status'];

                if ($base_price === null) {
                    $error = "Please select a valid room from the database.";
                    break;
                }

                if (!isValidDateRange($check_in_date, $check_in_time, $check_out_date, $check_out_time)) {
                    $error = "Check-out date/time must be after check-in date/time.";
                    break;
                }

                if (hasRoomDateConflict($db, $room_id, $check_in_datetime, $check_out_datetime, $reservation_id)) {
                    $error = "This room is already booked for the selected range.";
                    break;
                }

                $total_amount = calculateReservationAmount($db, $room_id, $check_in_datetime, $check_out_datetime);
                $hasTimeColumns = hasReservationTimeColumns($db);
                
                // Get old room_id to update status
                $sql = "SELECT room_id FROM reservations WHERE reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $oldResult = $stmt->get_result();
                $oldRoom = $oldResult->fetch_assoc();
                $oldRoomId = $oldRoom['room_id'];
                $stmt->close();
                
                if ($hasTimeColumns) {
                    $sql = "UPDATE reservations SET guest_id = ?, room_id = ?, 
                            check_in_date = ?, check_in_time = ?, check_out_date = ?, check_out_time = ?, 
                            total_amount = ?, status = ? 
                            WHERE reservation_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("iissssdsi", $guest_id, $room_id, $check_in_date, $check_in_time, $check_out_date, $check_out_time, $total_amount, $status, $reservation_id);
                } else {
                    $sql = "UPDATE reservations SET guest_id = ?, room_id = ?, 
                            check_in_date = ?, check_out_date = ?, 
                            total_amount = ?, status = ? 
                            WHERE reservation_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("iissdsi", $guest_id, $room_id, $check_in_date, $check_out_date, $total_amount, $status, $reservation_id);
                }
                $stmt->execute();
                $stmt->close();
                
                // Update room statuses
                if ($oldRoomId != $room_id) {
                    $sql = "UPDATE rooms SET status = 'available' WHERE room_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $oldRoomId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $sql = "UPDATE rooms SET status = 'reserved' WHERE room_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $room_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $success = "Reservation updated successfully!";
                break;
                
            case 'delete':
                $reservation_id = (int)$_POST['reservation_id'];
                
                // Get room_id before deleting
                $sql = "SELECT room_id FROM reservations WHERE reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $room = $result->fetch_assoc();
                $room_id = $room['room_id'];
                $stmt->close();
                
                $sql = "DELETE FROM reservations WHERE reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();
                
                // Update room status to available
                $sql = "UPDATE rooms SET status = 'available' WHERE room_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();
                
                $success = "Reservation deleted successfully!";
                break;
                
            case 'check_in':
                $reservation_id = (int)$_POST['reservation_id'];
                
                $sql = "UPDATE reservations SET status = 'checked_in' WHERE reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();
                
                $sql = "UPDATE rooms SET status = 'occupied' WHERE room_id = (SELECT room_id FROM reservations WHERE reservation_id = ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();
                
                $success = "Guest checked in successfully!";
                break;
                
            case 'check_out':
                $reservation_id = (int)$_POST['reservation_id'];
                
                $sql = "UPDATE reservations SET status = 'checked_out' WHERE reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();
                
                $sql = "UPDATE rooms SET status = 'available' WHERE room_id = (SELECT room_id FROM reservations WHERE reservation_id = ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $stmt->close();
                
                $success = "Guest checked out successfully!";
                break;
                
            case 'extend':
                $reservation_id = (int)$_POST['reservation_id'];
                $extension_type = $_POST['extension_type'] ?? '';
                
                // Get current reservation details
                $sql = "SELECT r.*, rt.base_price FROM reservations r 
                        JOIN rooms rm ON r.room_id = rm.room_id
                        JOIN room_types rt ON rm.type_id = rt.type_id
                        WHERE r.reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $reservation = $result->fetch_assoc();
                $stmt->close();
                
                if (!$reservation) {
                    $error = "Reservation not found.";
                    break;
                }
                
                // Calculate extension duration
                $extension_hours = 0;
                switch ($extension_type) {
                    case '1_hour': $extension_hours = 1; break;
                    case '3_hours': $extension_hours = 3; break;
                    case '6_hours': $extension_hours = 6; break;
                    case '1_day': $extension_hours = 24; break;
                    case '2_days': $extension_hours = 48; break;
                    case '1_week': $extension_hours = 168; break;
                    default:
                        $error = "Invalid extension type.";
                        break 2;
                }
                
                // Calculate new checkout time
                $current_checkout = combineReservationDateTime($reservation['check_out_date'], $reservation['check_out_time']);
                $new_checkout_ts = strtotime($current_checkout) + ($extension_hours * 3600);
                $new_checkout_date = date('Y-m-d', $new_checkout_ts);
                $new_checkout_time = date('H:i:s', $new_checkout_ts);
                $new_checkout_time_display = date('H:00', $new_checkout_ts); // Round to hour
                
                // Determine pricing tier and calculate extension charge
                $base_price = (float)$reservation['base_price'];
                $new_checkout_hour = (int)date('H', $new_checkout_ts);
                $extension_charge = 0;
                $pricing_note = '';
                
                // Pricing rules:
                // Hourly extension: up to 3 PM (15:00) - PHP 400 per hour
                // Half-day rate: 3 PM to 6 PM - 50% of daily rate
                // Full-day rate: after 6 PM - 100% of daily rate
                
                if ($new_checkout_hour < 15) {
                    // Hourly extension (before 3 PM)
                    $hourly_rate = 400; // PHP 400 per hour (mid-range of 150-650)
                    $extension_charge = $extension_hours * $hourly_rate;
                    $pricing_note = "Hourly Extension @ PHP $hourly_rate/hour";
                } elseif ($new_checkout_hour < 18) {
                    // Half-day rate (3 PM to 6 PM)
                    $extension_charge = $base_price * 0.5; // 50% of daily rate
                    $pricing_note = "Half-Day Rate (50% of daily)";
                } else {
                    // Full-day rate (after 6 PM)
                    $extension_charge = $base_price; // 100% of daily rate
                    $pricing_note = "Full-Day Rate";
                }
                
                $extension_charge = round($extension_charge, 2);
                $new_total = (float)$reservation['total_amount'] + $extension_charge;
                
                // Update reservation
                $sql = "UPDATE reservations SET check_out_date = ?, check_out_time = ?, total_amount = ? WHERE reservation_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssdi", $new_checkout_date, $new_checkout_time, $new_total, $reservation_id);
                if (!$stmt->execute()) {
                    $error = "Failed to extend reservation: " . $stmt->error;
                    $stmt->close();
                    break;
                }
                $stmt->close();
                
                $success = "Reservation extended to {$new_checkout_time_display}. {$pricing_note} Additional charge: ₱" . number_format($extension_charge, 2);
                break;
        }
    }
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

// Check schema before building query
$hasTimeColumns = hasReservationTimeColumns($db);
$checkInTimeExpr = $hasTimeColumns ? 'r.check_in_time' : "'14:00:00'";
$checkOutTimeExpr = $hasTimeColumns ? 'r.check_out_time' : "'12:00:00'";

// Build query
$sql = "SELECT r.*, $checkInTimeExpr as check_in_time, $checkOutTimeExpr as check_out_time, CONCAT(g.first_name, ' ', g.last_name) as guest_name, 
        g.email as guest_email, g.phone as guest_phone,
        room.room_number, rt.type_name 
        FROM reservations r 
        LEFT JOIN guests g ON r.guest_id = g.guest_id 
        LEFT JOIN rooms room ON r.room_id = room.room_id 
        LEFT JOIN room_types rt ON room.type_id = rt.type_id 
        WHERE 1=1";

if ($search) {
    $search = $db->escape($search);
    $sql .= " AND (r.reservation_id LIKE '%$search%' OR g.first_name LIKE '%$search%' OR g.last_name LIKE '%$search%' OR room.room_number LIKE '%$search%')";
}

if ($filter_status) {
    $sql .= " AND r.status = '$filter_status'";
}

if ($filter_date) {
    $sql .= " AND r.check_in_date = '$filter_date'";
}

$sql .= " ORDER BY r.created_at DESC";

$reservationsResult = $db->query($sql);

$checkInDateTimeExpr = $hasTimeColumns
    ? "CONCAT(check_in_date, 'T', check_in_time)"
    : "CONCAT(check_in_date, 'T00:00:00')";
$checkOutDateTimeExpr = $hasTimeColumns
    ? "CONCAT(check_out_date, 'T', check_out_time)"
    : "CONCAT(check_out_date, 'T23:59:59')";

// Get data for dropdowns
$guestsResult = $db->query("SELECT guest_id, CONCAT(first_name, ' ', last_name) as name, email FROM guests ORDER BY first_name");
$roomsResult = $db->query("SELECT r.room_id, r.room_number, rt.type_name, rt.base_price FROM rooms r JOIN room_types rt ON r.type_id = rt.type_id WHERE r.status = 'available' ORDER BY r.room_number");
$bookedRangesResult = $db->query("SELECT reservation_id, room_id, 
                               {$checkInDateTimeExpr} AS check_in_datetime, 
                               {$checkOutDateTimeExpr} AS check_out_datetime 
                       FROM reservations 
                       WHERE status IN ('confirmed', 'checked_in')");
$bookedRanges = [];
while ($range = $bookedRangesResult->fetch_assoc()) {
    $bookedRanges[] = $range;
}

// Get reservation for editing
$editReservation = null;
if (isset($_GET['edit'])) {
    $reservation_id = (int)$_GET['edit'];
    $sql = "SELECT * FROM reservations WHERE reservation_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $editResult = $stmt->get_result();
    $editReservation = $editResult->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations Management - Hotel Reservation System</title>
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
        .reservation-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .reservation-card:hover {
            transform: translateY(-3px);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-checked_in { background: #d4edda; color: #155724; }
        .status-checked_out { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
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
                            <a class="nav-link active" href="reservations.php">
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
                            <h2 class="mb-0">Reservations Management</h2>
                            <p class="text-muted mb-0">Manage hotel reservations and bookings</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reservationModal">
                            <i class="bi bi-plus-circle me-2"></i>New Reservation
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

                <!-- Search and Filter -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by ID, guest name, room..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="filter_status">
                                    <option value="">All Status</option>
                                    <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="checked_in" <?php echo $filter_status == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                    <option value="checked_out" <?php echo $filter_status == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                    <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" name="filter_date" 
                                       value="<?php echo $filter_date; ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reservations List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Guest</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Nights</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($reservation = $reservationsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $reservation['reservation_id']; ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo $reservation['guest_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $reservation['guest_email']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $reservation['room_number']; ?></strong><br>
                                                <small class="text-muted"><?php echo $reservation['type_name']; ?></small>
                                            </div>
                                        </td>
                                        <?php
                                            $checkInDateTime = $reservation['check_in_date'];
                                            $checkOutDateTime = $reservation['check_out_date'];
                                            $checkInTime = !empty($reservation['check_in_time']) ? $reservation['check_in_time'] : '14:00:00';
                                            $checkOutTime = !empty($reservation['check_out_time']) ? $reservation['check_out_time'] : '12:00:00';
                                            $checkInDateTime .= ' ' . $checkInTime;
                                            $checkOutDateTime .= ' ' . $checkOutTime;
                                            $checkInTs = strtotime($checkInDateTime);
                                            $checkOutTs = strtotime($checkOutDateTime);
                                            $nights = max(1, ceil(($checkOutTs - $checkInTs) / (60 * 60 * 24)));
                                            $checkInDisplayDate = date('M d, Y', $checkInTs);
                                            $checkOutDisplayDate = date('M d, Y', $checkOutTs);
                                            $checkInDisplayTime = date('g:i A', strtotime($checkInTime));
                                            $checkOutDisplayTime = date('g:i A', strtotime($checkOutTime));
                                        ?>
                                        <td>
                                            <?php echo $checkInDisplayDate; ?><br>
                                            <?php if ($checkInDisplayTime): ?>
                                                <small class="text-muted"><?php echo $checkInDisplayTime; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $checkOutDisplayDate; ?><br>
                                            <?php if ($checkOutDisplayTime): ?>
                                                <small class="text-muted"><?php echo $checkOutDisplayTime; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $nights; ?></td>
                                        <td>₱<?php echo number_format($reservation['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $reservation['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editReservation(<?php echo $reservation['reservation_id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                
                                                <?php if ($reservation['status'] == 'confirmed'): ?>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="checkIn(<?php echo $reservation['reservation_id']; ?>)">
                                                    <i class="bi bi-box-arrow-in-right"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($reservation['status'] == 'checked_in'): ?>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="openExtendModal(<?php echo $reservation['reservation_id']; ?>)">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="checkOut(<?php echo $reservation['reservation_id']; ?>)">
                                                    <i class="bi bi-box-arrow-right"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteReservation(<?php echo $reservation['reservation_id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Reservation Modal -->
                <div class="modal fade" id="reservationModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitle">
                                    <?php echo $editReservation ? 'Edit Reservation' : 'New Reservation'; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" id="reservationForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="<?php echo $editReservation ? 'edit' : 'add'; ?>">
                                    <?php if ($editReservation): ?>
                                        <input type="hidden" name="reservation_id" value="<?php echo $editReservation['reservation_id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Guest</label>
                                            <div class="position-relative">
                                                <input type="text" class="form-control" id="guestSearch" placeholder="Type guest name or email..." autocomplete="off">
                                                <input type="hidden" name="guest_id" id="guest_id" required>
                                                <div class="list-group position-absolute w-100" id="guestDropdown" style="display: none; max-height: 300px; overflow-y: auto; z-index: 1000;">
                                                </div>
                                            </div>
                                            <small class="text-muted d-block mt-2">Start typing to search guests...</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Room</label>
                                            <select class="form-select" name="room_id" id="room_id" required>
                                                <?php 
                                                $roomsResult = $db->query("SELECT r.room_id, r.room_number, rt.type_name, rt.base_price FROM rooms r JOIN room_types rt ON r.type_id = rt.type_id WHERE r.status != 'maintenance' ORDER BY r.room_number");
                                                while ($room = $roomsResult->fetch_assoc()): 
                                                ?>
                                                    <option value="<?php echo $room['room_id']; ?>"
                                                            data-price="<?php echo $room['base_price']; ?>"
                                                            <?php echo ($editReservation && $editReservation['room_id'] == $room['room_id']) ? 'selected' : ''; ?>>
                                                        <?php echo $room['room_number']; ?> - <?php echo $room['type_name']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-in Date</label>
                                            <input type="date" class="form-control" name="check_in_date" id="check_in_date" required
                                                   value="<?php echo $editReservation['check_in_date'] ?? $currentCheckInDate; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-in Time</label>
                                            <input type="time" class="form-control" name="check_in_time" id="check_in_time" required
                                                   value="<?php echo $editReservation['check_in_time'] ?? $currentCheckInTime; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-out Date</label>
                                            <input type="date" class="form-control" name="check_out_date" id="check_out_date" required
                                                   value="<?php echo $editReservation['check_out_date'] ?? ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-out Time</label>
                                            <input type="time" class="form-control" name="check_out_time" id="check_out_time" required
                                                   value="<?php echo $editReservation['check_out_time'] ?? '12:00'; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Price per Night (₱)</label>
                                            <input type="number" step="0.01" class="form-control" id="price_per_night" readonly>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Total Amount (₱)</label>
                                            <input type="number" step="0.01" class="form-control" name="total_amount" id="total_amount" readonly required
                                                   value="<?php echo $editReservation['total_amount'] ?? ''; ?>">
                                        </div>
                                        
                                        <?php if ($editReservation): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status" required>
                                                <option value="confirmed" <?php echo ($editReservation['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="checked_in" <?php echo ($editReservation['status'] == 'checked_in') ? 'selected' : ''; ?>>Checked In</option>
                                                <option value="checked_out" <?php echo ($editReservation['status'] == 'checked_out') ? 'selected' : ''; ?>>Checked Out</option>
                                                <option value="cancelled" <?php echo ($editReservation['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $editReservation ? 'Update Reservation' : 'Create Reservation'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Extension Modal -->
                <div class="modal fade" id="extendModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Extend Checkout Time</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" id="extendForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="extend">
                                    <input type="hidden" name="reservation_id" id="extend_reservation_id">
                                    
                                    <div class="mb-4">
                                        <h6 class="mb-3"><strong>Pricing by Checkout Time:</strong></h6>
                                        <div class="alert alert-info mb-3">
                                            <small>
                                                <strong>⏰ Before 3:00 PM:</strong> PHP 400/hour<br>
                                                <strong>☀️ 3:00 PM - 6:00 PM:</strong> 50% of daily rate<br>
                                                <strong>🌙 After 6:00 PM:</strong> Full daily rate
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Select Extension Duration</strong></label>
                                        <div class="list-group">
                                            <label class="list-group-item">
                                                <input class="form-check-input" type="radio" name="extension_type" value="1_hour" required>
                                                <strong>1 Hour</strong> <span class="text-muted float-end">→ 3 PM (PHP 400)</span>
                                            </label>
                                            <label class="list-group-item">
                                                <input class="form-check-input" type="radio" name="extension_type" value="3_hours">
                                                <strong>3 Hours</strong> <span class="text-muted float-end">→ 5 PM (PHP 1,200)</span>
                                            </label>
                                            <label class="list-group-item">
                                                <input class="form-check-input" type="radio" name="extension_type" value="6_hours">
                                                <strong>6 Hours</strong> <span class="text-muted float-end">→ 8 PM (Full Day)</span>
                                            </label>
                                            <label class="list-group-item">
                                                <input class="form-check-input" type="radio" name="extension_type" value="1_day">
                                                <strong>1 Day</strong> <span class="text-muted float-end">→ Next Day (Full Day)</span>
                                            </label>
                                            <label class="list-group-item">
                                                <input class="form-check-input" type="radio" name="extension_type" value="2_days">
                                                <strong>2 Days</strong> <span class="text-muted float-end">→ +2 Days (2x Daily)</span>
                                            </label>
                                            <label class="list-group-item">
                                                <input class="form-check-input" type="radio" name="extension_type" value="1_week">
                                                <strong>1 Week</strong> <span class="text-muted float-end">→ +7 Days (7x Daily)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Extend Checkout</button>
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
        // Guest autocomplete data
        const allGuests = <?php 
            $guestsForJs = [];
            $guestsResult = $db->query("SELECT guest_id, CONCAT(first_name, ' ', last_name) as name, email FROM guests ORDER BY first_name");
            while ($guest = $guestsResult->fetch_assoc()) {
                $guestsForJs[] = [
                    'id' => $guest['guest_id'],
                    'name' => $guest['name'],
                    'email' => $guest['email'],
                    'display' => $guest['name'] . ' (' . $guest['email'] . ')'
                ];
            }
            echo json_encode($guestsForJs);
        ?>;

        // Guest autocomplete functionality
        const guestSearch = document.getElementById('guestSearch');
        const guestId = document.getElementById('guest_id');
        const guestDropdown = document.getElementById('guestDropdown');

        function filterGuests(query) {
            if (!query.trim()) {
                guestDropdown.style.display = 'none';
                return;
            }

            const filtered = allGuests.filter(guest => 
                guest.name.toLowerCase().includes(query.toLowerCase()) ||
                guest.email.toLowerCase().includes(query.toLowerCase())
            );

            if (filtered.length === 0) {
                guestDropdown.innerHTML = '<div class="list-group-item text-muted">No guests found</div>';
                guestDropdown.style.display = 'block';
                return;
            }

            guestDropdown.innerHTML = filtered.map(guest => `
                <button type="button" class="list-group-item list-group-item-action" 
                        onclick="selectGuest(${guest.id}, '${guest.display.replace(/'/g, "\\'")}')">
                    <strong>${guest.name}</strong><br>
                    <small class="text-muted">${guest.email}</small>
                </button>
            `).join('');
            guestDropdown.style.display = 'block';
        }

        function selectGuest(id, display) {
            guestId.value = id;
            guestSearch.value = display;
            guestDropdown.style.display = 'none';
        }

        guestSearch.addEventListener('input', (e) => {
            filterGuests(e.target.value);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target !== guestSearch && e.target !== guestDropdown) {
                guestDropdown.style.display = 'none';
            }
        });

        function editReservation(reservationId) {
            window.location.href = 'reservations.php?edit=' + reservationId;
        }
        
        function deleteReservation(reservationId) {
            if (confirm('Are you sure you want to delete this reservation?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function checkIn(reservationId) {
            if (confirm('Check in this guest?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="check_in">
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function checkOut(reservationId) {
            if (confirm('Check out this guest?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="check_out">
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function openExtendModal(reservationId) {
            document.getElementById('extend_reservation_id').value = reservationId;
            const modal = new bootstrap.Modal(document.getElementById('extendModal'));
            modal.show();
        }
        
        // Auto-open modal if editing
        <?php if ($editReservation): ?>
            const modal = new bootstrap.Modal(document.getElementById('reservationModal'));
            modal.show();
            
            // Pre-populate guest field when editing
            const editingGuest = allGuests.find(g => g.id == <?php echo $editReservation['guest_id']; ?>);
            if (editingGuest) {
                guestId.value = editingGuest.id;
                guestSearch.value = editingGuest.display;
            }
        <?php endif; ?>

        const bookedRanges = <?php echo json_encode($bookedRanges); ?>;
        const editingReservationId = <?php echo $editReservation ? (int)$editReservation['reservation_id'] : 0; ?>;
        const reservationForm = document.getElementById('reservationForm');
        const roomSelect = document.getElementById('room_id');
        const checkInInput = document.getElementById('check_in_date');
        const checkOutInput = document.getElementById('check_out_date');
        const pricePerNightInput = document.getElementById('price_per_night');
        const totalAmountInput = document.getElementById('total_amount');

        function datesOverlap(checkIn, checkOut, range) {
            return checkIn < range.check_out_date && checkOut > range.check_in_date;
        }

        function parseDateTime(dateValue, timeValue) {
            return dateValue && timeValue ? new Date(`${dateValue}T${timeValue}`) : null;
        }

        function selectedDatesConflict() {
            const roomId = roomSelect.value;
            const checkIn = parseDateTime(checkInInput.value, document.getElementById('check_in_time').value);
            const checkOut = parseDateTime(checkOutInput.value, document.getElementById('check_out_time').value);

            if (!roomId || !checkIn || !checkOut) {
                return false;
            }

            return bookedRanges.some((range) => {
                return Number(range.room_id) === Number(roomId)
                    && Number(range.reservation_id) !== editingReservationId
                    && checkIn < new Date(range.check_out_datetime)
                    && checkOut > new Date(range.check_in_datetime);
            });
        }

        function validateReservationDates() {
            const checkIn = parseDateTime(checkInInput.value, document.getElementById('check_in_time').value);
            const checkOut = parseDateTime(checkOutInput.value, document.getElementById('check_out_time').value);
            let message = '';

            if (checkIn && checkOut && checkOut <= checkIn) {
                message = 'Check-out must be after check-in.';
            } else if (selectedDatesConflict()) {
                message = 'This room is already booked for the selected range.';
            }

            checkOutInput.setCustomValidity(message);
            return message === '';
        }

        function setSelectedRoomPrice() {
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            const basePrice = selectedOption && selectedOption.dataset.price ? Number(selectedOption.dataset.price) : 0;
            pricePerNightInput.value = basePrice ? basePrice.toFixed(2) : '';
            return basePrice;
        }

        function calculateReservationAmount() {
            const basePrice = setSelectedRoomPrice();
            const checkIn = parseDateTime(checkInInput.value, document.getElementById('check_in_time').value);
            const checkOut = parseDateTime(checkOutInput.value, document.getElementById('check_out_time').value);

            if (basePrice && checkIn && checkOut && checkOut > checkIn && validateReservationDates()) {
                const hours = Math.max(1, Math.ceil((checkOut - checkIn) / (1000 * 60 * 60)));
                totalAmountInput.value = ((basePrice / 24) * hours).toFixed(2);
            } else if (!checkIn || !checkOut || checkOut <= checkIn || selectedDatesConflict()) {
                totalAmountInput.value = '';
            }
        }

        const checkInTimeInput = document.getElementById('check_in_time');
        const checkOutTimeInput = document.getElementById('check_out_time');

        [roomSelect, checkInInput, checkOutInput, checkInTimeInput, checkOutTimeInput].forEach((input) => {
            input.addEventListener('change', calculateReservationAmount);
        });

        reservationForm.addEventListener('submit', (event) => {
            calculateReservationAmount();

            if (!validateReservationDates()) {
                event.preventDefault();
                checkOutInput.reportValidity();
            }
        });

        calculateReservationAmount();
    </script>
</body>
</html>
