<?php
// Reservation helper functions
// Hotel Reservation System

function getCurrentHotelDateTime() {
    return new DateTime('now', new DateTimeZone('Asia/Manila'));
}

function getCurrentHotelDate() {
    return getCurrentHotelDateTime()->format('Y-m-d');
}

function getCurrentHotelTime() {
    return getCurrentHotelDateTime()->format('H:i:s');
}

function getCurrentHotelTimeForInput() {
    return getCurrentHotelDateTime()->format('H:i');
}

function combineReservationDateTime($date, $time) {
    $time = trim($time) === '' ? '12:00:00' : $time;
    $dateTime = trim($date . ' ' . $time);
    $timestamp = strtotime($dateTime);
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}

function isValidReservationDateTimeRange($check_in_date, $check_in_time, $check_out_date, $check_out_time) {
    $start = combineReservationDateTime($check_in_date, $check_in_time);
    $end = combineReservationDateTime($check_out_date, $check_out_time);
    return $start !== null && $end !== null && strtotime($end) > strtotime($start);
}

function hasRoomDateTimeConflict($db, $room_id, $check_in_datetime, $check_out_datetime, $exclude_reservation_id = 0) {
    $checkInExpr = reservationDateTimeExpression($db);
    $checkOutExpr = reservationEndDateTimeExpression($db);

    $sql = "SELECT COUNT(*) AS count
            FROM reservations
            WHERE room_id = ?
              AND status IN ('confirmed', 'checked_in')
              AND {$checkInExpr} < ?
              AND {$checkOutExpr} > ?";

    if ($exclude_reservation_id > 0) {
        $sql .= " AND reservation_id != ?";
    }

    $stmt = $db->prepare($sql);
    if ($exclude_reservation_id > 0) {
        $stmt->bind_param("issi", $room_id, $check_out_datetime, $check_in_datetime, $exclude_reservation_id);
    } else {
        $stmt->bind_param("iss", $room_id, $check_out_datetime, $check_in_datetime);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $count = (int)$result->fetch_assoc()['count'];
    $stmt->close();

    return $count > 0;
}

function calculateReservationAmount($db, $room_id, $check_in_datetime, $check_out_datetime) {
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

    if (!$room) {
        return null;
    }

    $basePrice = (float)$room['base_price'];
    $durationSeconds = strtotime($check_out_datetime) - strtotime($check_in_datetime);
    $hours = max(1, ceil($durationSeconds / 3600));
    $hourlyRate = $basePrice / 24;

    return round($hours * $hourlyRate, 2);
}

function calculateExtensionCheckout($check_out_date, $check_out_time, $extensionType) {
    $checkout = combineReservationDateTime($check_out_date, $check_out_time);
    if ($checkout === null) {
        return null;
    }

    $seconds = 0;
    switch ($extensionType) {
        case '1_hour': $seconds = 3600; break;
        case '3_hours': $seconds = 3 * 3600; break;
        case '6_hours': $seconds = 6 * 3600; break;
        case '1_day': $seconds = 24 * 3600; break;
        case '2_days': $seconds = 2 * 24 * 3600; break;
        case '1_week': $seconds = 7 * 24 * 3600; break;
        default: return null;
    }

    $newTimestamp = strtotime($checkout) + $seconds;
    return [
        'check_out_date' => date('Y-m-d', $newTimestamp),
        'check_out_time' => date('H:i:s', $newTimestamp)
    ];
}

function getReservationDurationHours($check_in_date, $check_in_time, $check_out_date, $check_out_time) {
    $checkIn = combineReservationDateTime($check_in_date, $check_in_time);
    $checkOut = combineReservationDateTime($check_out_date, $check_out_time);
    if ($checkIn === null || $checkOut === null) {
        return 0;
    }

    return max(1, ceil((strtotime($checkOut) - strtotime($checkIn)) / 3600));
}

function formatReservationDuration($hours) {
    if ($hours >= 24) {
        $days = floor($hours / 24);
        $remaining = $hours % 24;
        return $days . ' day' . ($days > 1 ? 's' : '')
            . ($remaining ? ' ' . $remaining . ' hour' . ($remaining > 1 ? 's' : '') : '');
    }
    return $hours . ' hour' . ($hours > 1 ? 's' : '');
}
