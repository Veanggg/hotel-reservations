<?php
// Room availability sync script
// Hotel Reservation System

// A simple script to update room and reservation status automatically.
// Use from CLI or schedule it with your OS task scheduler.

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/availability.php';

// Security token for HTTP execution. Change this to a strong secret.
define('SYNC_AVAILABILITY_TOKEN', 'change_this_to_a_strong_secret');

if (PHP_SAPI !== 'cli') {
    if (!isset($_GET['token']) || $_GET['token'] !== SYNC_AVAILABILITY_TOKEN) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    header('Content-Type: application/json');
}

try {
    $db = new Database();
    syncRoomAvailability($db);
    $db->close();

    $response = [
        'success' => true,
        'message' => 'Room availability synchronized successfully.',
        'timestamp' => date('c')
    ];

    if (PHP_SAPI === 'cli') {
        echo $response['message'] . "\n";
    } else {
        echo json_encode($response);
    }
} catch (Exception $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
