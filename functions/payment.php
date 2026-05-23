<?php
// Payment handling functions
// Hotel Reservation System

/**
 * Calculate half payment amount
 * @param float $totalAmount The total reservation amount
 * @return float Half of the total amount
 */
function calculateHalfPayment($totalAmount) {
    return round($totalAmount / 2, 2);
}

/**
 * Get available payment methods
 * @return array Array of payment method options
 */
function getPaymentMethods() {
    return [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'paypal' => 'PayPal',
        'credit_card' => 'Credit Card',
        'bank_transfer' => 'Bank Transfer'
    ];
}

/**
 * Create a payment record
 * @param object $db Database connection
 * @param int $reservation_id Reservation ID
 * @param float $amount Payment amount
 * @param string $payment_method Payment method
 * @param string $payment_type Either 'full' or 'half'
 * @return int Payment ID or null on failure
 */
function createPayment($db, $reservation_id, $amount, $payment_method, $payment_type = 'full') {
    $reservation_id = (int)$reservation_id;
    $amount = (float)$amount;
    $payment_method = $db->escape($payment_method);
    $payment_type = $db->escape($payment_type);
    
    $sql = "INSERT INTO payments (reservation_id, amount, payment_method, payment_type, status) 
            VALUES ($reservation_id, $amount, '$payment_method', '$payment_type', 'pending')";
    
    if ($db->query($sql)) {
        return $db->getLastInsertId();
    }
    return null;
}

/**
 * Get payment details for a reservation
 * @param object $db Database connection
 * @param int $reservation_id Reservation ID
 * @return array Array of payment records
 */
function getReservationPayments($db, $reservation_id) {
    $reservation_id = (int)$reservation_id;
    $sql = "SELECT * FROM payments WHERE reservation_id = $reservation_id ORDER BY payment_date DESC";
    $result = $db->query($sql);
    
    $payments = [];
    while ($payment = $result->fetch_assoc()) {
        $payments[] = $payment;
    }
    return $payments;
}

/**
 * Get total paid amount for a reservation
 * @param object $db Database connection
 * @param int $reservation_id Reservation ID
 * @return float Total paid amount
 */
function getTotalPaidAmount($db, $reservation_id) {
    $reservation_id = (int)$reservation_id;
    $sql = "SELECT COALESCE(SUM(amount), 0) as total_paid 
            FROM payments 
            WHERE reservation_id = $reservation_id AND status = 'completed'";
    
    $result = $db->query($sql);
    $row = $result->fetch_assoc();
    return (float)$row['total_paid'];
}

/**
 * Update payment status
 * @param object $db Database connection
 * @param int $payment_id Payment ID
 * @param string $status New payment status
 * @return bool True if successful
 */
function updatePaymentStatus($db, $payment_id, $status) {
    $payment_id = (int)$payment_id;
    $status = $db->escape($status);
    
    $sql = "UPDATE payments SET status = '$status' WHERE payment_id = $payment_id";
    return $db->query($sql) ? true : false;
}

/**
 * Get payment summary for a reservation
 * @param object $db Database connection
 * @param int $reservation_id Reservation ID
 * @return array Payment summary with total_amount, paid_amount, remaining_amount
 */
function getPaymentSummary($db, $reservation_id) {
    $reservation_id = (int)$reservation_id;
    
    $sql = "SELECT r.total_amount,
                   COALESCE(SUM(p.amount), 0) as paid_amount
            FROM reservations r
            LEFT JOIN payments p ON r.reservation_id = p.reservation_id AND p.status = 'completed'
            WHERE r.reservation_id = $reservation_id
            GROUP BY r.reservation_id, r.total_amount";
    
    $result = $db->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        $total = (float)$row['total_amount'];
        $paid = (float)$row['paid_amount'];
        
        return [
            'total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => round($total - $paid, 2),
            'payment_status' => $paid >= $total ? 'fully_paid' : ($paid > 0 ? 'partially_paid' : 'unpaid')
        ];
    }
    
    return [
        'total_amount' => 0,
        'paid_amount' => 0,
        'remaining_amount' => 0,
        'payment_status' => 'unpaid'
    ];
}

/**
 * Check if payment table has payment_type column
 * @param object $db Database connection
 * @return bool True if column exists
 */
function hasPaymentTypeColumn($db) {
    $result = $db->query("DESCRIBE payments");
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'payment_type') {
            return true;
        }
    }
    return false;
}

/**
 * Ensure payment table has payment_type column
 * @param object $db Database connection
 * @return bool True if column exists or was added successfully
 */
function ensurePaymentTypeColumn($db) {
    if (hasPaymentTypeColumn($db)) {
        return true;
    }
    
    // Add payment_type column if it doesn't exist
    $sql = "ALTER TABLE payments ADD COLUMN payment_type ENUM('full', 'half') DEFAULT 'full' AFTER payment_method";
    return $db->query($sql) ? true : false;
}

/**
 * Format payment method display
 * @param string $method Payment method key
 * @return string Formatted payment method name
 */
function formatPaymentMethod($method) {
    $methods = getPaymentMethods();
    return isset($methods[$method]) ? $methods[$method] : ucfirst(str_replace('_', ' ', $method));
}

/**
 * Get payment method icon
 * @param string $method Payment method key
 * @return string HTML icon code
 */
function getPaymentMethodIcon($method) {
    $icons = [
        'cash' => '<i class="bi bi-cash-coin"></i>',
        'gcash' => '<i class="bi bi-phone"></i>',
        'paypal' => '<i class="bi bi-credit-card"></i>',
        'credit_card' => '<i class="bi bi-credit-card"></i>',
        'bank_transfer' => '<i class="bi bi-bank"></i>'
    ];
    
    return isset($icons[$method]) ? $icons[$method] : '<i class="bi bi-wallet2"></i>';
}
?>
