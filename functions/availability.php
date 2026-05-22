<?php
// Room availability synchronization helper
// Hotel Reservation System

function syncRoomAvailability($db) {
    $hasTimeColumns = hasReservationTimeColumns($db);
    $checkInExpr = $hasTimeColumns
        ? "CONCAT(check_in_date, ' ', check_in_time)"
        : "CAST(CONCAT(check_in_date, ' 00:00:00') AS DATETIME)";
    $checkOutExpr = $hasTimeColumns
        ? "CONCAT(check_out_date, ' ', check_out_time)"
        : "CAST(CONCAT(check_out_date, ' 23:59:59') AS DATETIME)";

    // Automatically close reservations whose check-out date/time has passed.
    $db->query("UPDATE reservations
                SET status = 'checked_out'
                WHERE status IN ('confirmed', 'checked_in')
                  AND {$checkOutExpr} <= NOW()");

    // Mark rooms occupied when a guest is currently checked in.
    $db->query("UPDATE rooms r
                JOIN reservations res ON r.room_id = res.room_id
                SET r.status = 'occupied'
                WHERE res.status = 'checked_in'
                  AND {$checkInExpr} <= NOW()
                  AND {$checkOutExpr} > NOW()
                  AND r.status != 'maintenance'");

    // Mark rooms reserved when there is any confirmed reservation that has not yet ended.
    $db->query("UPDATE rooms r
                JOIN (
                    SELECT DISTINCT room_id
                    FROM reservations
                    WHERE status = 'confirmed'
                      AND {$checkOutExpr} > NOW()
                ) future ON r.room_id = future.room_id
                SET r.status = 'reserved'
                WHERE r.status != 'maintenance'
                  AND r.status != 'occupied'");

    // Make rooms available again if they have no active or future reservation.
    $db->query("UPDATE rooms r
                SET r.status = 'available'
                WHERE r.status != 'maintenance'
                  AND r.room_id NOT IN (
                      SELECT room_id FROM reservations
                      WHERE status = 'checked_in'
                        AND {$checkInExpr} <= NOW()
                        AND {$checkOutExpr} > NOW()
                      UNION
                      SELECT room_id FROM reservations
                      WHERE status = 'confirmed'
                        AND {$checkOutExpr} > NOW()
                  )");
}

function hasReservationTimeColumns($db) {
    $result = $db->query("SHOW COLUMNS FROM reservations LIKE 'check_out_time'");
    return $result && $result->num_rows > 0;
}

function reservationDateTimeExpression($db, $columnPrefix = '') {
    $hasTimeColumns = hasReservationTimeColumns($db);
    if ($hasTimeColumns) {
        return "CONCAT({$columnPrefix}check_in_date, ' ', {$columnPrefix}check_in_time)";
    }

    return "CAST(CONCAT({$columnPrefix}check_in_date, ' 00:00:00') AS DATETIME)";
}

function reservationEndDateTimeExpression($db, $columnPrefix = '') {
    $hasTimeColumns = hasReservationTimeColumns($db);
    if ($hasTimeColumns) {
        return "CONCAT({$columnPrefix}check_out_date, ' ', {$columnPrefix}check_out_time)";
    }

    return "CAST(CONCAT({$columnPrefix}check_out_date, ' 23:59:59') AS DATETIME)";
}

