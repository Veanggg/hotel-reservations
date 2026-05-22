<?php
// Receipt Generator - Hotel Reservation System
// Generates printable receipt with option to save as PDF

require_once __DIR__ . '/../functions/auth.php';
requireLogin();

// Prevent admin access
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$user_id = $_SESSION['user_id'];
$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$reservation_id) {
    die("Invalid reservation ID");
}

// Get reservation details
try {
    $sql = "SELECT r.*, 
                   room.room_number, 
                   rt.type_name, 
                   rt.base_price,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   g.email as guest_email,
                   g.phone as guest_phone,
                   DATEDIFF(r.check_out_date, r.check_in_date) as nights_stayed
            FROM reservations r 
            JOIN rooms room ON r.room_id = room.room_id 
            JOIN room_types rt ON room.type_id = rt.type_id
            JOIN guests g ON r.guest_id = g.guest_id
            WHERE r.reservation_id = ? AND g.email = (SELECT email FROM users WHERE user_id = ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    $stmt->close();
    
    if (!$reservation) {
        die("Reservation not found");
    }
} catch (Exception $e) {
    die("Error retrieving reservation: " . $e->getMessage());
}

// Format dates
$checkin = date('F d, Y', strtotime($reservation['check_in_date']));
$checkout = date('F d, Y', strtotime($reservation['check_out_date']));
$created = date('F d, Y', strtotime($reservation['created_at']));
$nights = $reservation['nights_stayed'];
$subtotal = $reservation['base_price'] * $nights;
$total = $reservation['total_amount'];

$download = isset($_GET['download']) && $_GET['download'] == '1';
$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';

if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reservation-receipt-' . $reservation['reservation_id'] . '.html"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Receipt #<?php echo $reservation['reservation_id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
        }
        .hotel-name {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .hotel-subtitle {
            color: #666;
            font-size: 0.9rem;
        }
        .receipt-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #333;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-weight: bold;
            color: #667eea;
            font-size: 1rem;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .receipt-row-last {
            border-bottom: none;
        }
        .receipt-label {
            color: #666;
            font-size: 0.9rem;
        }
        .receipt-value {
            font-weight: 500;
            color: #333;
        }
        .total-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1.1rem;
            font-weight: bold;
        }
        .total-amount {
            color: #28a745;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #667eea;
            color: #666;
            font-size: 0.85rem;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-checked_in { background: #d4edda; color: #155724; }
        .status-checked_out { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .action-buttons {
            text-align: center;
            margin-top: 30px;
            gap: 10px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="hotel-name">🏨 Hotel Reservation System</div>
            <div class="hotel-subtitle">Your Trusted Hotel Partner</div>
        </div>

        <!-- Receipt Title -->
        <div style="text-align: center;">
            <h3 class="receipt-title">RESERVATION RECEIPT</h3>
            <p style="color: #666; margin: 0;">Reservation #<?php echo $reservation['reservation_id']; ?></p>
            <span class="status-badge status-<?php echo $reservation['status']; ?>">
                <?php echo strtoupper(str_replace('_', ' ', $reservation['status'])); ?>
            </span>
        </div>

        <!-- Reservation Info -->
        <div class="section">
            <div class="section-title">RESERVATION INFORMATION</div>
            <div class="receipt-row">
                <span class="receipt-label">Reservation ID:</span>
                <span class="receipt-value">#<?php echo $reservation['reservation_id']; ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Date Booked:</span>
                <span class="receipt-value"><?php echo $created; ?></span>
            </div>
            <div class="receipt-row receipt-row-last">
                <span class="receipt-label">Status:</span>
                <span class="receipt-value"><?php echo ucfirst(str_replace('_', ' ', $reservation['status'])); ?></span>
            </div>
        </div>

        <!-- Guest Information -->
        <div class="section">
            <div class="section-title">GUEST INFORMATION</div>
            <div class="receipt-row">
                <span class="receipt-label">Guest Name:</span>
                <span class="receipt-value"><?php echo $reservation['guest_name']; ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Email:</span>
                <span class="receipt-value"><?php echo $reservation['guest_email']; ?></span>
            </div>
            <div class="receipt-row receipt-row-last">
                <span class="receipt-label">Phone:</span>
                <span class="receipt-value"><?php echo $reservation['guest_phone']; ?></span>
            </div>
        </div>

        <!-- Check-in & Check-out -->
        <div class="section">
            <div class="section-title">CHECK-IN & CHECK-OUT</div>
            <div class="receipt-row">
                <span class="receipt-label">Check-in Date:</span>
                <span class="receipt-value"><?php echo $checkin; ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Check-out Date:</span>
                <span class="receipt-value"><?php echo $checkout; ?></span>
            </div>
            <div class="receipt-row receipt-row-last">
                <span class="receipt-label">Number of Nights:</span>
                <span class="receipt-value"><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
            </div>
        </div>

        <!-- Room Information -->
        <div class="section">
            <div class="section-title">ROOM INFORMATION</div>
            <div class="receipt-row">
                <span class="receipt-label">Room Number:</span>
                <span class="receipt-value"><?php echo $reservation['room_number']; ?></span>
            </div>
            <div class="receipt-row receipt-row-last">
                <span class="receipt-label">Room Type:</span>
                <span class="receipt-value"><?php echo $reservation['type_name']; ?></span>
            </div>
        </div>

        <!-- Pricing -->
        <div class="section total-section">
            <div class="section-title" style="margin-bottom: 15px;">PRICING DETAILS</div>
            <div class="receipt-row">
                <span class="receipt-label">Nightly Rate:</span>
                <span class="receipt-value">₱<?php echo number_format($reservation['base_price'], 2); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Number of Nights:</span>
                <span class="receipt-value"><?php echo $nights; ?></span>
            </div>
            <div class="receipt-row" style="border-bottom: 2px solid #ddd; padding-bottom: 15px; margin-bottom: 15px;">
                <span class="receipt-label">Subtotal (<?php echo $nights; ?> × ₱<?php echo number_format($reservation['base_price'], 2); ?>):</span>
                <span class="receipt-value">₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="total-row">
                <span>TOTAL AMOUNT:</span>
                <span class="total-amount">₱<?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <p><strong>Thank you for your reservation!</strong></p>
            <p>For inquiries or modifications, please contact our hotel directly.</p>
            <p>Generated on <?php echo date('F d, Y g:i:s A'); ?></p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons d-flex justify-content-center mt-4">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Receipt
        </button>
        <a class="btn btn-success" href="generate_receipt.php?id=<?php echo $reservation['reservation_id']; ?>&download=1">
            <i class="bi bi-download"></i> Download Receipt
        </a>
        <button class="btn btn-secondary" onclick="window.history.back()">
            <i class="bi bi-arrow-left"></i> Back to Bookings
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($autoPrint): ?>
    <script>
        window.addEventListener('load', () => {
            window.print();
        });
    </script>
    <?php endif; ?>
</body>
</html>
