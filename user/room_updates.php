<?php
// Live room availability feed for the user dashboard

require_once __DIR__ . '/../functions/auth.php';
requireLogin();

if (isAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/availability.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$db = new Database();
syncRoomAvailability($db);

$hasTimeColumns = hasReservationTimeColumns($db);
$checkInDateTimeExpr = $hasTimeColumns
    ? "CONCAT(check_in_date, 'T', check_in_time)"
    : "CONCAT(check_in_date, 'T00:00:00')";
$checkOutDateTimeExpr = $hasTimeColumns
    ? "CONCAT(check_out_date, 'T', check_out_time)"
    : "CONCAT(check_out_date, 'T23:59:59')";

$roomsResult = $db->query("
    SELECT r.room_id, r.room_number, r.floor_number, r.status,
           rt.type_name, rt.base_price, rt.max_occupancy
    FROM rooms r
    JOIN room_types rt ON r.type_id = rt.type_id
    WHERE r.status = 'available'
    ORDER BY r.floor_number, r.room_number
");

$rooms = [];
while ($room = $roomsResult->fetch_assoc()) {
    $rooms[] = $room;
}

$summaryResult = $db->query("
    SELECT
        COUNT(*) AS total_rooms,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_rooms
    FROM rooms
");
$summary = $summaryResult->fetch_assoc();

$bookedRangesResult = $db->query("
    SELECT reservation_id, room_id, 
           {$checkInDateTimeExpr} AS check_in_datetime, 
           {$checkOutDateTimeExpr} AS check_out_datetime
    FROM reservations
    WHERE status IN ('confirmed', 'checked_in')
");

$bookedRanges = [];
while ($range = $bookedRangesResult->fetch_assoc()) {
    $bookedRanges[] = $range;
}

$db->close();

echo json_encode([
    'rooms' => $rooms,
    'bookedRanges' => $bookedRanges,
    'summary' => [
        'totalRooms' => (int)$summary['total_rooms'],
        'availableRooms' => (int)$summary['available_rooms']
    ]
]);
?>
