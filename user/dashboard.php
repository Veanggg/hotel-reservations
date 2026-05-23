<?php
// User Dashboard
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireLogin();

// Prevent admin access
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/availability.php';
require_once __DIR__ . '/../functions/reservation.php';
require_once __DIR__ . '/../functions/payment.php';

$db = new Database();
// Ensure payment table has payment_type column
ensurePaymentTypeColumn($db);
syncRoomAvailability($db);

// Get current user info
$user_id = $_SESSION['user_id'];

function getRoomBasePrice($db, $room_id) {
    $sql = "SELECT rt.base_price
            FROM rooms r
            JOIN room_types rt ON r.type_id = rt.type_id
            WHERE r.room_id = ?
              AND r.status = 'available'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();

    return $room ? (float)$room['base_price'] : null;
}

function hasRoomDateConflict($db, $room_id, $check_in_datetime, $check_out_datetime) {
    return hasRoomDateTimeConflict($db, $room_id, $check_in_datetime, $check_out_datetime);
}

$currentCheckInDate = getCurrentHotelDate();
$currentCheckInTime = getCurrentHotelTimeForInput();

// Handle new reservation form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'book_room') {
    $room_id = (int)$_POST['room_id'];
    $check_in_date = ($_POST['check_in_date'] ?? '') ?: getCurrentHotelDate();
    $check_in_time = ($_POST['check_in_time'] ?? '') ?: getCurrentHotelTime();
    $check_out_date = $_POST['check_out_date'];
    $check_out_time = $_POST['check_out_time'] ?? '12:00';
    $check_in_datetime = combineReservationDateTime($check_in_date, $check_in_time);
    $check_out_datetime = combineReservationDateTime($check_out_date, $check_out_time);
    $base_price = getRoomBasePrice($db, $room_id);
    
    // Payment handling
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
    $is_half_payment = isset($_POST['half_payment']) && $_POST['half_payment'] == '1';

    if ($base_price === null) {
        $error = "Please select a valid room from the database.";
    } elseif (!isValidReservationDateTimeRange($check_in_date, $check_in_time, $check_out_date, $check_out_time)) {
        $error = "Check-out date/time must be after check-in date/time.";
    } elseif (hasRoomDateConflict($db, $room_id, $check_in_datetime, $check_out_datetime)) {
        $error = "This room is already booked for the selected range.";
    } else {
        $total_amount = calculateReservationAmount($db, $room_id, $check_in_datetime, $check_out_datetime);
        $hasTimeColumns = hasReservationTimeColumns($db);

        // Create or find guest record for current user
        $userResult = $db->query("SELECT * FROM users WHERE user_id = $user_id");
        $user = $userResult->fetch_assoc();
        
        // Check if guest exists
        $guestEmail = $db->escape($user['email']);
        $guestResult = $db->query("SELECT * FROM guests WHERE email = '$guestEmail'");
        $guest = $guestResult->fetch_assoc();
        
        if (!$guest) {
            // Create new guest record
            $name_parts = explode(' ', $user['full_name']);
            $first_name = $db->escape($name_parts[0]);
            $last_name = $db->escape(isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '');
            $phone = $db->escape($user['phone']);
            
            $sql = "INSERT INTO guests (first_name, last_name, email, phone) 
                    VALUES ('$first_name', '$last_name', '$guestEmail', '$phone')";
            $db->query($sql);
            $guest_id = $db->getLastInsertId();
        } else {
            $guest_id = $guest['guest_id'];
        }
        
        // Create reservation
        if ($hasTimeColumns) {
            $sql = "INSERT INTO reservations (guest_id, room_id, check_in_date, check_in_time, check_out_date, check_out_time, total_amount, created_by) 
                    VALUES ($guest_id, $room_id, '$check_in_date', '$check_in_time', '$check_out_date', '$check_out_time', $total_amount, $user_id)";
        } else {
            $sql = "INSERT INTO reservations (guest_id, room_id, check_in_date, check_out_date, total_amount, created_by) 
                    VALUES ($guest_id, $room_id, '$check_in_date', '$check_out_date', $total_amount, $user_id)";
        }
        $db->query($sql);
        $reservation_id = $db->getLastInsertId();
        
        // Handle payment creation
        $paymentMethods = getPaymentMethods();
        $payment_method_display = $paymentMethods[$payment_method] ?? ucfirst($payment_method);
        
        if ($is_half_payment) {
            $payment_amount = calculateHalfPayment($total_amount);
            createPayment($db, $reservation_id, $payment_amount, $payment_method, 'half');
        } else {
            createPayment($db, $reservation_id, $total_amount, $payment_method, 'full');
        }
        
        // Update room status
        $db->query("UPDATE rooms SET status = 'reserved' WHERE room_id = $room_id");
        
        if ($is_half_payment) {
            $half_amount = calculateHalfPayment($total_amount);
            $success = "✓ Room booked successfully! Payment method: <strong>" . $payment_method_display . "</strong><br>Half payment of ₱" . number_format($half_amount, 2) . " recorded. Remaining balance: ₱" . number_format($total_amount - $half_amount, 2);
        } else {
            $success = "✓ Room booked successfully! Payment method: <strong>" . $payment_method_display . "</strong><br>Full payment of ₱" . number_format($total_amount, 2) . " recorded.";
        }
    }
}

// Get available rooms for booking
$roomsQuery = "
    SELECT r.*, rt.type_name, rt.base_price, rt.max_occupancy, rt.description
    FROM rooms r 
    JOIN room_types rt ON r.type_id = rt.type_id 
    WHERE r.status = 'available' 
    ORDER BY r.floor_number, r.room_number
";
$roomsResult = $db->query($roomsQuery);
$rooms = [];

// Image mapping for room types
$imageMapping = [
    'Standard' => 'Single Standard.jpg',
    'Standard Double' => 'standard double.jpg',
    'Deluxe' => 'deluxe room.jpg',
    'Suite' => 'suite.jpg',
    'Family Room' => 'family room.jpg',
    'Twin Room' => 'twin room.jpg',
    'Budget Room' => 'budget room.jpg',
    'Connecting Rooms' => 'connecting rooms.jpg',
    'Penthouse' => 'penthouse.jpg'
];

while ($room = $roomsResult->fetch_assoc()) {
    // Determine image file based on room type
    $room['image_file'] = $imageMapping[$room['type_name']] ?? 'Single Standard.jpg';
    $rooms[] = $room;
}

// Get user's reservations
$reservationsQuery = "
    SELECT r.*, room.room_number, rt.type_name 
    FROM reservations r 
    JOIN rooms room ON r.room_id = room.room_id 
    JOIN room_types rt ON room.type_id = rt.type_id 
    WHERE r.guest_id = (SELECT guest_id FROM guests WHERE email = (SELECT email FROM users WHERE user_id = $user_id))
    ORDER BY r.created_at DESC
";
$reservationsResult = $db->query($reservationsQuery);

$hasTimeColumns = hasReservationTimeColumns($db);
$checkInDateTimeExpr = $hasTimeColumns
    ? "CONCAT(check_in_date, 'T', check_in_time)"
    : "CONCAT(check_in_date, 'T00:00:00')";
$checkOutDateTimeExpr = $hasTimeColumns
    ? "CONCAT(check_out_date, 'T', check_out_time)"
    : "CONCAT(check_out_date, 'T23:59:59')";

$bookedRangesResult = $db->query("SELECT reservation_id, room_id, 
                                   {$checkInDateTimeExpr} AS check_in_datetime, 
                                   {$checkOutDateTimeExpr} AS check_out_datetime 
                            FROM reservations 
                            WHERE status IN ('confirmed', 'checked_in')");
$bookedRanges = [];
while ($range = $bookedRangesResult->fetch_assoc()) {
    $bookedRanges[] = $range;
}

$roomSummaryResult = $db->query("
    SELECT
        COUNT(*) AS total_rooms,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_rooms
    FROM rooms
");
$roomSummary = $roomSummaryResult->fetch_assoc();

// Get all room types with statistics
$roomTypesQuery = "
    SELECT 
        rt.type_id,
        rt.type_name,
        rt.description,
        rt.base_price,
        rt.max_occupancy,
        COUNT(r.room_id) AS total_rooms,
        SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) AS available_rooms
    FROM room_types rt
    LEFT JOIN rooms r ON rt.type_id = r.type_id
    GROUP BY rt.type_id, rt.type_name, rt.description, rt.base_price, rt.max_occupancy
    ORDER BY rt.base_price ASC
";
$roomTypesResult = $db->query($roomTypesQuery);
$roomTypes = [];
while ($roomType = $roomTypesResult->fetch_assoc()) {
    $roomTypes[] = $roomType;
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../node_modules/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s;
            position: relative;
            z-index: 1;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .sidebar .text-center {
            position: relative;
            z-index: 1;
        }

        .main-content {
            background: linear-gradient(135deg, #f5f7ff 0%, #f8f9fa 50%, #fff5f7 100%);
            min-height: 100vh;
            padding-top: 20px;
            padding-bottom: 40px;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s;
        }

        .page-header:hover {
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.15);
        }

        .room-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            border: 1px solid rgba(102, 126, 234, 0.05);
            position: relative;
            overflow: hidden;
        }

        .room-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .room-card:hover::before {
            left: 100%;
        }

        .room-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }

        .room-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .room-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
            transition: all 0.3s;
            z-index: 1;
        }

        .room-card:hover .room-image-overlay {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .room-image::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .price-tag {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            transition: all 0.3s;
        }

        .price-tag:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .status-badge {
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .status-confirmed { 
            background: linear-gradient(135deg, #d1ecf1, #c7e9f3); 
            color: #0c5460;
            box-shadow: 0 2px 8px rgba(12, 84, 96, 0.15);
        }

        .status-checked_in { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb); 
            color: #155724;
            box-shadow: 0 2px 8px rgba(21, 87, 36, 0.15);
        }

        .status-checked_out { 
            background: linear-gradient(135deg, #e2e3e5, #d6d8db); 
            color: #383d41;
            box-shadow: 0 2px 8px rgba(56, 61, 65, 0.15);
        }

        .status-cancelled { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb); 
            color: #721c24;
            box-shadow: 0 2px 8px rgba(114, 28, 36, 0.15);
        }

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .welcome-card > * {
            position: relative;
            z-index: 1;
        }

        .welcome-card h2 {
            font-weight: 700;
            font-size: 28px;
            letter-spacing: -0.5px;
        }

        .welcome-card p {
            font-size: 16px;
            opacity: 0.95;
            line-height: 1.6;
        }

        .nav-tabs {
            border-bottom: 3px solid #e9ecef !important;
            background: white;
            padding: 10px 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .nav-tabs .nav-link {
            color: #667eea;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: -13px;
            border-radius: 10px 10px 0 0;
        }

        .nav-tabs .nav-link:hover {
            color: #764ba2;
            border-bottom-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .nav-tabs .nav-link.active {
            color: white;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px 15px 0 0;
            border-bottom-color: transparent;
            box-shadow: 0 -4px 15px rgba(102, 126, 234, 0.2);
        }

        .room-type-card {
            background: white;
            border-radius: 18px;
            padding: 28px;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.09);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .room-type-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0) 0%, rgba(118, 75, 162, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .room-type-card:hover::after {
            opacity: 1;
        }

        .room-type-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }

        .room-type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s;
            z-index: 10;
        }

        .room-type-card:hover::before {
            left: 100%;
        }

        .room-type-header {
            display: flex;
            align-items: center;
            margin-bottom: 18px;
            position: relative;
            z-index: 2;
        }

        .room-type-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-right: 18px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .room-type-icon.standard-single {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .room-type-icon.standard-double {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .room-type-icon.deluxe-room {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }

        .room-type-icon.suite {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .room-type-icon.family-room {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .room-type-icon.executive-suite {
            background: linear-gradient(135deg, #fa709a, #fee140);
        }

        .room-type-icon.penthouse {
            background: linear-gradient(135deg, #fa709a, #fee140);
        }

        .room-type-icon.budget-room {
            background: linear-gradient(135deg, #a8edea, #fed6e3);
        }

        .room-type-icon.twin-room {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .room-type-icon.connecting-rooms {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        /* Fallback for any other room types */
        .room-type-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .room-type-title {
            flex-grow: 1;
            position: relative;
            z-index: 2;
        }

        .room-type-title h5 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }

        .room-type-price {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            position: relative;
            z-index: 2;
        }

        .room-type-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 2px solid #f5f7ff;
            position: relative;
            z-index: 2;
        }

        .room-type-stat {
            text-align: center;
        }

        .room-type-stat-value {
            display: block;
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }

        .room-type-stat-label {
            display: block;
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 6px;
            font-weight: 600;
        }

        .room-type-description {
            color: #666;
            font-size: 14px;
            margin-top: 12px;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }

        .room-type-occupancy {
            display: inline-block;
            background: linear-gradient(135deg, #f5f7ff, #f8f9fa);
            padding: 8px 14px;
            border-radius: 18px;
            font-size: 13px;
            color: #667eea;
            margin-top: 12px;
            font-weight: 600;
            position: relative;
            z-index: 2;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .tab-pane {
            animation: fadeIn 0.4s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert {
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .badge {
            padding: 6px 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .btn {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table tbody tr {
            transition: all 0.3s;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
        }

        .room-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 12px;
            padding: 12px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .room-detail-item {
            display: flex;
            align-items: center;
            font-size: 13px;
        }

        .room-detail-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 14px;
        }

        .room-detail-icon.occupancy {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .room-detail-icon.floor {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .room-detail-icon.status {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .room-detail-text {
            display: flex;
            flex-direction: column;
        }

        .room-detail-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }

        .room-detail-value {
            font-size: 13px;
            color: #333;
            font-weight: 600;
        }

        .room-description {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            margin-top: 10px;
            padding: 8px 10px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .room-amenities {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .amenity-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .room-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
        }

        .room-rating-stars {
            color: #ffc107;
            font-size: 12px;
        }

        .room-rating-text {
            font-size: 12px;
            color: #666;
            font-weight: 500;
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="bi bi-calendar-check me-2"></i> My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
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
                <div class="welcome-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-3">Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
                            <p class="mb-0">Book your perfect stay at our luxury hotel. Browse available rooms and make reservations instantly.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex align-items-center justify-content-end">
                                <i class="bi bi-person-circle me-2" style="font-size: 2rem;"></i>
                                <div>
                                    <small>Guest Account</small><br>
                                    <strong><?php echo $_SESSION['username']; ?></strong>
                                </div>
                            </div>
                        </div>
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

                <!-- Room Types Section -->
                <div class="page-header">
                    <h5 class="mb-0">Our Room Types</h5>
                </div>

                <div class="row mb-4" id="roomTypesContainer">
                    <?php foreach ($roomTypes as $roomType): ?>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="room-type-card" onclick="filterByRoomType('<?php echo htmlspecialchars($roomType['type_name']); ?>')">
                            <div class="room-type-header">
                                <div class="room-type-icon <?php echo strtolower(str_replace(' ', '-', $roomType['type_name'])); ?>">
                                    <?php 
                                    $icons = [
                                        'Standard Single' => 'bi-door-closed',
                                        'Standard Double' => 'bi-door-closed',
                                        'Deluxe Room' => 'bi-gem',
                                        'Suite' => 'bi-star',
                                        'Family Room' => 'bi-houses',
                                        'Executive Suite' => 'bi-briefcase',
                                        'Penthouse' => 'bi-building',
                                        'Budget Room' => 'bi-wallet-fill',
                                        'Twin Room' => 'bi-door-closed',
                                        'Connecting Rooms' => 'bi-bezier'
                                    ];
                                    $icon = $icons[$roomType['type_name']] ?? 'bi-door-closed';
                                    ?>
                                    <i class="bi <?php echo $icon; ?>"></i>
                                </div>
                                <div class="room-type-title">
                                    <h5><?php echo htmlspecialchars($roomType['type_name']); ?></h5>
                                </div>
                                <div class="room-type-price">₱<?php echo number_format($roomType['base_price'], 0); ?></div>
                            </div>
                            
                            <?php if (!empty($roomType['description'])): ?>
                            <div class="room-type-description">
                                <?php echo htmlspecialchars($roomType['description']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="room-type-occupancy">
                                <i class="bi bi-people me-1"></i>Max <?php echo $roomType['max_occupancy']; ?> guests
                            </div>
                            
                            <div class="room-type-info">
                                <div class="room-type-stat">
                                    <span class="room-type-stat-value"><?php echo $roomType['total_rooms']; ?></span>
                                    <span class="room-type-stat-label">Total</span>
                                </div>
                                <div class="room-type-stat">
                                    <span class="room-type-stat-value" style="color: #28a745;"><?php echo $roomType['available_rooms'] ?? 0; ?></span>
                                    <span class="room-type-stat-label">Available</span>
                                </div>
                                <div class="room-type-stat">
                                    <span class="room-type-stat-value" style="color: #ffc107;"><?php echo ($roomType['total_rooms'] - ($roomType['available_rooms'] ?? 0)); ?></span>
                                    <span class="room-type-stat-label">Booked</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabbed Navigation -->
                <div class="page-header">
                    <ul class="nav nav-tabs border-0" id="reservationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="browse-tab" data-bs-toggle="tab" data-bs-target="#browse-pane" type="button" role="tab" aria-controls="browse-pane" aria-selected="true">
                                <i class="bi bi-door-closed me-2"></i>Browse Rooms
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations-pane" type="button" role="tab" aria-controls="reservations-pane" aria-selected="false">
                                <i class="bi bi-calendar-check me-2"></i>My Reservations
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="reservationTabsContent">
                    <!-- Browse Rooms Tab -->
                    <div class="tab-pane fade show active" id="browse-pane" role="tabpanel" aria-labelledby="browse-tab">
                        <div class="page-header mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">Available Rooms</h5>
                                    <p class="text-muted mb-0">
                                        <span id="availableRoomsCount"><?php echo (int)$roomSummary['available_rooms']; ?></span>
                                        of
                                        <span id="totalRoomsCount"><?php echo (int)$roomSummary['total_rooms']; ?></span>
                                        rooms available
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4" id="availableRoomsList">
                            <?php foreach ($rooms as $room): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="room-card">
                                    <div class="room-image" style="background-image: url('../images/<?php echo htmlspecialchars($room['image_file']); ?>'); background-size: cover; background-position: center;">
                                        <div class="room-image-overlay"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="mb-1">Room <?php echo $room['room_number']; ?></h5>
                                            <small class="text-muted">Room ID: #<?php echo $room['room_id']; ?></small>
                                        </div>
                                        <span class="price-tag">₱<?php echo number_format($room['base_price'], 2); ?>/night</span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <span class="badge bg-info text-dark"><?php echo $room['type_name']; ?></span>
                                        <span class="badge bg-success">Available</span>
                                    </div>

                                    <!-- Room Details -->
                                    <div class="room-details">
                                        <div class="room-detail-item">
                                            <div class="room-detail-icon occupancy">
                                                <i class="bi bi-people"></i>
                                            </div>
                                            <div class="room-detail-text">
                                                <span class="room-detail-label">Capacity</span>
                                                <span class="room-detail-value">Max <?php echo $room['max_occupancy']; ?> guests</span>
                                            </div>
                                        </div>
                                        <div class="room-detail-item">
                                            <div class="room-detail-icon floor">
                                                <i class="bi bi-building"></i>
                                            </div>
                                            <div class="room-detail-text">
                                                <span class="room-detail-label">Location</span>
                                                <span class="room-detail-value">Floor <?php echo $room['floor_number']; ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($room['description'])): ?>
                                    <div class="room-description">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <?php echo htmlspecialchars($room['description']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="room-amenities">
                                        <span class="amenity-badge">
                                            <i class="bi bi-wifi"></i> WiFi
                                        </span>
                                        <span class="amenity-badge">
                                            <i class="bi bi-thermometer-half"></i> AC
                                        </span>
                                        <span class="amenity-badge">
                                            <i class="bi bi-tv"></i> TV
                                        </span>
                                    </div>
                                    
                                    <button class="btn btn-primary w-100 mt-3" 
                                            onclick="bookRoom(<?php echo $room['room_id']; ?>, '<?php echo $room['room_number']; ?>', <?php echo $room['base_price']; ?>)">
                                        <i class="bi bi-calendar-plus me-2"></i>Book Now
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (count($rooms) === 0): ?>
                            <div class="col-12">
                                <div class="text-center py-5 bg-white rounded shadow-sm">
                                    <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                                    <h5 class="mt-3">No Available Rooms</h5>
                                    <p class="text-muted mb-0">Please check again later.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- My Reservations Tab -->
                    <div class="tab-pane fade" id="reservations-pane" role="tabpanel" aria-labelledby="reservations-tab">
                        <div class="page-header mt-3">
                            <div>
                                <h5 class="mb-0">My Reservations</h5>
                                <p class="text-muted mb-0">View and manage your current and past reservations</p>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <?php if ($reservationsResult->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Reservation ID</th>
                                                <th>Room</th>
                                                <th>Room Type</th>
                                                <th>Check-in</th>
                                                <th>Check-out</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($reservation = $reservationsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong>#<?php echo $reservation['reservation_id']; ?></strong></td>
                                                <td><?php echo $reservation['room_number']; ?></td>
                                                <td><span class="badge bg-info text-dark"><?php echo $reservation['type_name']; ?></span></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($reservation['check_in_date'] . ' ' . $reservation['check_in_time'])); ?></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($reservation['check_out_date'] . ' ' . $reservation['check_out_time'])); ?></td>
                                                <td><strong>₱<?php echo number_format($reservation['total_amount'], 2); ?></strong></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $reservation['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                                    <h5 class="mt-3">No Reservations Yet</h5>
                                    <p class="text-muted">You haven't made any reservations. Book your first room in the Browse Rooms tab!</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Modal -->
                <div class="modal fade" id="bookingModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Book Room</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" id="bookingForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="book_room">
                                    <div class="mb-3">
                                        <label class="form-label">Selected Room</label>
                                        <select class="form-select" id="room_id" name="room_id" required>
                                            <option value="">Choose a room</option>
                                            <?php foreach ($rooms as $room): ?>
                                                <option value="<?php echo $room['room_id']; ?>"
                                                        data-price="<?php echo $room['base_price']; ?>"
                                                        data-room-number="<?php echo $room['room_number']; ?>">
                                                    Room <?php echo $room['room_number']; ?> - <?php echo $room['type_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Price per Night (₱)</label>
                                        <input type="number" step="0.01" class="form-control" id="price_per_night" readonly>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-in Date</label>
                                            <input type="date" class="form-control" name="check_in_date" id="check_in_date" value="<?php echo $currentCheckInDate; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-in Time</label>
                                            <input type="time" class="form-control" name="check_in_time" id="check_in_time" value="<?php echo $currentCheckInTime; ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-out Date</label>
                                            <input type="date" class="form-control" name="check_out_date" id="check_out_date" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Check-out Time</label>
                                            <input type="time" class="form-control" name="check_out_time" id="check_out_time" value="12:00" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Total Amount (₱)</label>
                                        <input type="number" step="0.01" class="form-control" name="total_amount" id="total_amount" readonly required>
                                    </div>

                                    <!-- Payment Options -->
                                    <div class="card border-primary mb-3">
                                        <div class="card-header bg-primary text-white">
                                            <i class="bi bi-credit-card me-2"></i>Payment Information
                                        </div>
                                        <div class="card-body">
                                            <!-- Payment Method Selection -->
                                            <div class="mb-3">
                                                <label class="form-label"><strong>Payment Method</strong></label>
                                                <div class="row">
                                                    <?php 
                                                    $paymentMethods = getPaymentMethods();
                                                    foreach ($paymentMethods as $key => $method): 
                                                    ?>
                                                        <div class="col-md-6 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="payment_method" id="payment_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo $key === 'cash' ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="payment_<?php echo $key; ?>">
                                                                    <?php echo getPaymentMethodIcon($key); ?> <?php echo $method; ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Half Payment Option -->
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="half_payment" id="half_payment" value="1">
                                                    <label class="form-check-label" for="half_payment">
                                                        <strong>Pay Half Now, Half on Arrival</strong>
                                                    </label>
                                                </div>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="bi bi-info-circle"></i> 
                                                    When enabled, you only pay half of the total amount now. The remaining balance will be due upon check-in.
                                                </small>
                                            </div>

                                            <!-- Payment Summary -->
                                            <div class="alert alert-info mb-0">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small><strong>Full Amount:</strong></small><br>
                                                        <strong id="full_amount_display">₱0.00</strong>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small id="payment_label"><strong>Pay Now:</strong></small><br>
                                                        <strong id="pay_now_display">₱0.00</strong>
                                                    </div>
                                                </div>
                                                <div class="row mt-2" id="remaining_row" style="display: none;">
                                                    <div class="col-md-6">
                                                        <small><strong>Balance Due:</strong></small><br>
                                                        <strong id="remaining_display">₱0.00</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Total amount will be calculated automatically based on the number of nights.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Confirm Booking</button>
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
        let basePrice = 0;
        let availableRooms = <?php echo json_encode($rooms); ?>;
        let bookedRanges = <?php echo json_encode($bookedRanges); ?>;
        let selectedRoomType = null;
        const currentCheckInDate = <?php echo json_encode($currentCheckInDate); ?>;
        const currentCheckInTime = <?php echo json_encode($currentCheckInTime); ?>;

        function filterByRoomType(typeName) {
            selectedRoomType = typeName;
            renderAvailableRooms();
            
            // Scroll to browse rooms tab
            document.getElementById('browse-tab').click();
            setTimeout(() => {
                document.getElementById('availableRoomsList').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }

        function clearRoomTypeFilter() {
            selectedRoomType = null;
            renderAvailableRooms();
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseDateTime(dateValue, timeValue) {
            return dateValue && timeValue ? new Date(dateValue + 'T' + timeValue) : null;
        }

        function formatReservationDuration(hours) {
            if (hours >= 24) {
                const days = Math.floor(hours / 24);
                const remainder = hours % 24;
                return `${days} day${days > 1 ? 's' : ''}` + (remainder ? ` ${remainder} hour${remainder > 1 ? 's' : ''}` : '');
            }
            return `${hours} hour${hours > 1 ? 's' : ''}`;
        }

        function formatMoney(value) {
            return Number(value || 0).toFixed(2);
        }

        // Image mapping for room types
        const imageMapping = {
            'Standard': 'Single Standard.jpg',
            'Standard Double': 'standard double.jpg',
            'Deluxe': 'deluxe room.jpg',
            'Suite': 'suite.jpg',
            'Family Room': 'family room.jpg',
            'Twin Room': 'twin room.jpg',
            'Budget Room': 'budget room.jpg',
            'Connecting Rooms': 'connecting rooms.jpg',
            'Penthouse': 'penthouse.jpg'
        };

        function getRoomImage(roomType) {
            return imageMapping[roomType] || 'Single Standard.jpg';
        }

        function renderAvailableRooms() {
            const roomList = document.getElementById('availableRoomsList');
            
            // Filter rooms by selected type if any
            let filteredRooms = availableRooms;
            if (selectedRoomType) {
                filteredRooms = availableRooms.filter(room => room.type_name === selectedRoomType);
            }

            if (!filteredRooms.length) {
                roomList.innerHTML = `
                    <div class="col-12">
                        <div class="text-center py-5 bg-white rounded shadow-sm">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                            <h5 class="mt-3">No Available Rooms</h5>
                            <p class="text-muted mb-0">` + (selectedRoomType ? `No ${selectedRoomType} rooms available at this time.` : 'Please check again later.') + `</p>
                            ` + (selectedRoomType ? `<button class="btn btn-sm btn-outline-primary mt-2" onclick="clearRoomTypeFilter()">View All Rooms</button>` : '') + `
                        </div>
                    </div>
                `;
                return;
            }

            roomList.innerHTML = (selectedRoomType ? `
                <div class="col-12 mb-3">
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-funnel me-2"></i>
                        Showing <strong>${filteredRooms.length}</strong> ${selectedRoomType} room${filteredRooms.length !== 1 ? 's' : ''}
                        <button type="button" class="btn-close" onclick="clearRoomTypeFilter()" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            ` : '') + filteredRooms.map((room) => `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="room-card">
                        <div class="room-image" style="background-image: url('../images/${getRoomImage(room.type_name)}'); background-size: cover; background-position: center;">
                            <div class="room-image-overlay"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">Room ${escapeHtml(room.room_number)}</h5>
                                <small class="text-muted">Room ID: #${Number(room.room_id)}</small>
                            </div>
                            <span class="price-tag">₱${formatMoney(room.base_price)}/night</span>
                        </div>
                        <div class="mb-2">
                            <span class="badge bg-info text-dark">${escapeHtml(room.type_name)}</span>
                            <span class="badge bg-success">Available</span>
                        </div>

                        <div class="room-details">
                            <div class="room-detail-item">
                                <div class="room-detail-icon occupancy">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="room-detail-text">
                                    <span class="room-detail-label">Capacity</span>
                                    <span class="room-detail-value">Max ${Number(room.max_occupancy)} guests</span>
                                </div>
                            </div>
                            <div class="room-detail-item">
                                <div class="room-detail-icon floor">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div class="room-detail-text">
                                    <span class="room-detail-label">Location</span>
                                    <span class="room-detail-value">Floor ${Number(room.floor_number)}</span>
                                </div>
                            </div>
                        </div>

                        ${room.description ? `
                            <div class="room-description">
                                <i class="bi bi-info-circle me-1"></i>
                                ${escapeHtml(room.description)}
                            </div>
                        ` : ''}

                        <div class="room-amenities">
                            <span class="amenity-badge">
                                <i class="bi bi-wifi"></i> WiFi
                            </span>
                            <span class="amenity-badge">
                                <i class="bi bi-thermometer-half"></i> AC
                            </span>
                            <span class="amenity-badge">
                                <i class="bi bi-tv"></i> TV
                            </span>
                        </div>
                        
                        <button class="btn btn-primary w-100 mt-3"
                                onclick="bookRoom(${Number(room.room_id)}, '${escapeHtml(room.room_number)}', ${Number(room.base_price)})">
                            <i class="bi bi-calendar-plus me-2"></i>Book Now
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function renderRoomOptions() {
            const roomSelect = document.getElementById('room_id');
            const selectedRoomId = roomSelect.value;

            roomSelect.innerHTML = '<option value="">Choose a room</option>'
                + availableRooms.map((room) => `
                    <option value="${Number(room.room_id)}"
                            data-price="${Number(room.base_price)}"
                            data-room-number="${escapeHtml(room.room_number)}">
                        Room ${escapeHtml(room.room_number)} - ${escapeHtml(room.type_name)}
                    </option>
                `).join('');

            if (availableRooms.some((room) => Number(room.room_id) === Number(selectedRoomId))) {
                roomSelect.value = selectedRoomId;
            } else {
                roomSelect.value = '';
                document.getElementById('price_per_night').value = '';
                document.getElementById('total_amount').value = '';
                basePrice = 0;
            }
        }

        function applyRoomUpdate(data) {
            availableRooms = data.rooms || [];
            bookedRanges = data.bookedRanges || [];

            document.getElementById('availableRoomsCount').textContent = data.summary?.availableRooms ?? availableRooms.length;
            document.getElementById('totalRoomsCount').textContent = data.summary?.totalRooms ?? availableRooms.length;
            renderAvailableRooms();
            renderRoomOptions();
            calculateAmount();
        }

        async function refreshRoomData() {
            try {
                const response = await fetch('room_updates.php', { cache: 'no-store' });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                applyRoomUpdate(data);
            } catch (error) {
                console.warn('Room availability refresh failed.', error);
            }
        }
        
        function bookRoom(roomId, roomNumber, price) {
            const roomSelect = document.getElementById('room_id');
            roomSelect.value = roomId;
            setSelectedRoomPrice();
            
            // Set minimum dates
            const today = currentCheckInDate;
            document.getElementById('check_in_date').min = today;
            document.getElementById('check_out_date').min = today;
            
            document.getElementById('check_in_time').value = currentCheckInTime;
            document.getElementById('check_out_time').value = '12:00';
            
            // Reset dates and amount
            document.getElementById('check_in_date').value = currentCheckInDate;
            document.getElementById('check_out_date').value = '';
            document.getElementById('total_amount').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
            modal.show();
        }
        
        // Calculate total amount when reservation inputs change
        document.getElementById('room_id').addEventListener('change', function() {
            setSelectedRoomPrice();
            calculateAmount();
        });
        document.getElementById('check_in_date').addEventListener('change', calculateAmount);
        document.getElementById('check_out_date').addEventListener('change', calculateAmount);
        document.getElementById('check_in_time').addEventListener('change', calculateAmount);
        document.getElementById('check_out_time').addEventListener('change', calculateAmount);
        document.getElementById('bookingForm').addEventListener('submit', function(event) {
            calculateAmount();
            if (!validateSelectedDates()) {
                event.preventDefault();
                document.getElementById('check_out_time').reportValidity();
            }
        });

        function setSelectedRoomPrice() {
            const roomSelect = document.getElementById('room_id');
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            basePrice = selectedOption && selectedOption.dataset.price ? Number(selectedOption.dataset.price) : 0;
            document.getElementById('price_per_night').value = basePrice ? basePrice.toFixed(2) : '';
        }
        
        function calculateAmount() {
            const checkInDate = document.getElementById('check_in_date').value;
            const checkInTime = document.getElementById('check_in_time').value;
            const checkOutDate = document.getElementById('check_out_date').value;
            const checkOutTime = document.getElementById('check_out_time').value;
            const checkout = document.getElementById('check_out_time');
            checkout.setCustomValidity('');
            
            const startDate = parseDateTime(checkInDate, checkInTime);
            const endDate = parseDateTime(checkOutDate, checkOutTime);
            
            if (startDate && endDate) {
                if (endDate > startDate) {
                    if (basePrice && validateSelectedDates()) {
                        const hours = Math.max(1, Math.ceil((endDate - startDate) / (1000 * 60 * 60)));
                        const total = (basePrice / 24) * hours;
                        document.getElementById('total_amount').value = total.toFixed(2);
                        updatePaymentDisplay(total);
                    } else {
                        document.getElementById('total_amount').value = '';
                        updatePaymentDisplay(0);
                    }
                } else {
                    checkout.setCustomValidity('Check-out must be after check-in.');
                    document.getElementById('total_amount').value = '';
                    updatePaymentDisplay(0);
                }
            }
        }

        function updatePaymentDisplay(totalAmount) {
            const fullAmount = parseFloat(totalAmount) || 0;
            const isHalfPayment = document.getElementById('half_payment').checked;
            const halfAmount = fullAmount / 2;
            
            // Update full amount display
            document.getElementById('full_amount_display').textContent = '₱' + fullAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Update pay now display
            const payNowAmount = isHalfPayment ? halfAmount : fullAmount;
            document.getElementById('pay_now_display').textContent = '₱' + payNowAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Update remaining balance display
            const remainingRow = document.getElementById('remaining_row');
            const paymentLabel = document.getElementById('payment_label');
            
            if (isHalfPayment) {
                remainingRow.style.display = 'block';
                document.getElementById('remaining_display').textContent = '₱' + halfAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                paymentLabel.innerHTML = '<strong>Pay Now (Half):</strong>';
            } else {
                remainingRow.style.display = 'none';
                paymentLabel.innerHTML = '<strong>Pay Now (Full):</strong>';
            }
        }

        function validateSelectedDates() {
            const roomId = document.getElementById('room_id').value;
            const checkInDate = document.getElementById('check_in_date').value;
            const checkInTime = document.getElementById('check_in_time').value;
            const checkOutDate = document.getElementById('check_out_date').value;
            const checkOutTime = document.getElementById('check_out_time').value;
            const checkout = document.getElementById('check_out_time');
            checkout.setCustomValidity('');

            if (!roomId || !checkInDate || !checkInTime || !checkOutDate || !checkOutTime) {
                return true;
            }

            const startDate = parseDateTime(checkInDate, checkInTime);
            const endDate = parseDateTime(checkOutDate, checkOutTime);

            if (!startDate || !endDate || endDate <= startDate) {
                checkout.setCustomValidity('Check-out must be after check-in.');
                return false;
            }

            const conflict = bookedRanges.some((range) => {
                return Number(range.room_id) === Number(roomId)
                    && startDate < new Date(range.check_out_datetime)
                    && endDate > new Date(range.check_in_datetime);
            });

            checkout.setCustomValidity(conflict ? 'This room is already booked for the selected range.' : '');
            return !conflict;
        }
        
        // Event listeners for payment options
        document.getElementById('half_payment').addEventListener('change', function() {
            const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
            updatePaymentDisplay(totalAmount);
        });

        // Add change listener to all payment method radio buttons (optional for future use)
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                console.log('Selected payment method:', this.value);
            });
        });
        
        // Set today as minimum date for check-in
        const today = currentCheckInDate;
        document.getElementById('check_in_date').min = today;
        renderAvailableRooms();
        renderRoomOptions();
        setInterval(refreshRoomData, 10000);
    </script>
</body>
</html>
