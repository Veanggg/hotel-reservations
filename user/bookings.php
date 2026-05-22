<?php
// User Bookings Page
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

$db = new Database();
syncRoomAvailability($db);
$user_id = $_SESSION['user_id'];
$success = null;
$error = null;

// Handle reservation cancellation and extension
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'cancel') {
            $reservation_id = (int)$_POST['reservation_id'];
            
            try {
                // Get the reservation and verify it belongs to current user
                $checkSql = "SELECT r.*, g.email FROM reservations r 
                            JOIN guests g ON r.guest_id = g.guest_id 
                            WHERE r.reservation_id = ? AND g.email = (SELECT email FROM users WHERE user_id = ?)";
                $stmt = $db->prepare($checkSql);
                $stmt->bind_param("ii", $reservation_id, $user_id);
                $stmt->execute();
                $checkResult = $stmt->get_result();
                $reservation = $checkResult->fetch_assoc();
                $stmt->close();
                
                if ($reservation) {
                    // Only allow cancellation of confirmed reservations
                    if ($reservation['status'] != 'confirmed') {
                        $error = "Only confirmed reservations can be cancelled.";
                    } else {
                        // Update reservation status to cancelled
                        $cancelSql = "UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?";
                        $stmt = $db->prepare($cancelSql);
                        $stmt->bind_param("i", $reservation_id);
                        $stmt->execute();
                        $stmt->close();

                        // Synchronize room availability after cancellation
                        syncRoomAvailability($db);
                        
                        $success = "Reservation cancelled successfully. The room has been released.";
                    }
                } else {
                    $error = "Reservation not found or does not belong to you.";
                }
            } catch (Exception $e) {
                $error = "Error cancelling reservation: " . $e->getMessage();
            }
        }

        if ($_POST['action'] == 'extend') {
            $reservation_id = (int)$_POST['reservation_id'];
            $extension_type = $_POST['extension_type'] ?? '';

            try {
                $checkSql = "SELECT r.*, g.email FROM reservations r 
                            JOIN guests g ON r.guest_id = g.guest_id 
                            WHERE r.reservation_id = ? AND g.email = (SELECT email FROM users WHERE user_id = ?)";
                $stmt = $db->prepare($checkSql);
                $stmt->bind_param("ii", $reservation_id, $user_id);
                $stmt->execute();
                $checkResult = $stmt->get_result();
                $reservation = $checkResult->fetch_assoc();
                $stmt->close();

                if ($reservation && in_array($reservation['status'], ['confirmed', 'checked_in'])) {
                    $newCheckout = calculateExtensionCheckout($reservation['check_out_date'], $reservation['check_out_time'], $extension_type);
                    if (!$newCheckout) {
                        $error = "Invalid extension selection.";
                    } else {
                        $newCheckoutDatetime = combineReservationDateTime($newCheckout['check_out_date'], $newCheckout['check_out_time']);
                        $currentCheckinDatetime = combineReservationDateTime($reservation['check_in_date'], $reservation['check_in_time']);

                        if (hasRoomDateTimeConflict($db, $reservation['room_id'], $currentCheckinDatetime, $newCheckoutDatetime, $reservation_id)) {
                            $error = "This extension conflicts with another reservation for the same room.";
                        } else {
                            $total_amount = calculateReservationAmount($db, $reservation['room_id'], $currentCheckinDatetime, $newCheckoutDatetime);
                            $hasTimeColumns = hasReservationTimeColumns($db);

                            if ($hasTimeColumns) {
                                $updateSql = "UPDATE reservations 
                                              SET check_out_date = ?, check_out_time = ?, total_amount = ? 
                                              WHERE reservation_id = ?";
                                $stmt = $db->prepare($updateSql);
                                $stmt->bind_param("ssdi", $newCheckout['check_out_date'], $newCheckout['check_out_time'], $total_amount, $reservation_id);
                            } else {
                                $updateSql = "UPDATE reservations 
                                              SET check_out_date = ?, total_amount = ? 
                                              WHERE reservation_id = ?";
                                $stmt = $db->prepare($updateSql);
                                $stmt->bind_param("sdi", $newCheckout['check_out_date'], $total_amount, $reservation_id);
                            }
                            $stmt->execute();
                            $stmt->close();

                            syncRoomAvailability($db);
                            $success = "Reservation extended successfully.";
                        }
                    }
                } else {
                    $error = "Reservation not found, not active, or cannot be extended.";
                }
            } catch (Exception $e) {
                $error = "Error extending reservation: " . $e->getMessage();
            }
        }
    }
}

// Get user's reservations with detailed information
$hasTimeColumns = hasReservationTimeColumns($db);
$checkInDateTimeExpr = $hasTimeColumns
    ? "CONCAT(r.check_in_date, 'T', r.check_in_time)"
    : "CONCAT(r.check_in_date, 'T00:00:00')";
$checkOutDateTimeExpr = $hasTimeColumns
    ? "CONCAT(r.check_out_date, 'T', r.check_out_time)"
    : "CONCAT(r.check_out_date, 'T23:59:59')";
$checkInSql = $hasTimeColumns
    ? "CONCAT(r.check_in_date, ' ', r.check_in_time)"
    : "CAST(CONCAT(r.check_in_date, ' 00:00:00') AS DATETIME)";
$checkOutSql = $hasTimeColumns
    ? "CONCAT(r.check_out_date, ' ', r.check_out_time)"
    : "CAST(CONCAT(r.check_out_date, ' 23:59:59') AS DATETIME)";
$checkInTimeField = $hasTimeColumns ? "r.check_in_time" : "'00:00:00'";
$checkOutTimeField = $hasTimeColumns ? "r.check_out_time" : "'00:00:00'";

$reservationsQuery = "
    SELECT r.*, 
           room.room_number, 
           rt.type_name, 
           rt.base_price,
           CONCAT(g.first_name, ' ', g.last_name) as guest_name,
           g.email as guest_email,
           g.phone as guest_phone,
           {$checkInTimeField} AS check_in_time,
           {$checkOutTimeField} AS check_out_time,
           {$checkInDateTimeExpr} AS check_in_datetime,
           {$checkOutDateTimeExpr} AS check_out_datetime,
           TIMESTAMPDIFF(HOUR, {$checkInSql}, {$checkOutSql}) AS duration_hours
    FROM reservations r 
    JOIN rooms room ON r.room_id = room.room_id 
    JOIN room_types rt ON room.type_id = rt.type_id
    JOIN guests g ON r.guest_id = g.guest_id
    WHERE g.email = (SELECT email FROM users WHERE user_id = $user_id)
    ORDER BY r.created_at DESC
";
$reservationsResult = $db->query($reservationsQuery);

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Hotel Reservation System</title>
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
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .booking-card:hover {
            transform: translateY(-3px);
        }
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-checked_in { background: #d4edda; color: #155724; }
        .status-checked_out { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .room-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        .price-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
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
                <div class="page-header">
                    <div>
                        <h2 class="mb-0">My Bookings</h2>
                        <p class="text-muted mb-0">View and manage all your hotel reservations</p>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($reservationsResult->num_rows > 0): ?>
                    <?php while ($reservation = $reservationsResult->fetch_assoc()): ?>
                    <div class="booking-card"
                         data-res-id="<?php echo $reservation['reservation_id']; ?>"
                         data-res-checkin="<?php echo $reservation['check_in_datetime']; ?>"
                         data-res-checkout="<?php echo $reservation['check_out_datetime']; ?>"
                         data-res-hours="<?php echo $reservation['duration_hours']; ?>"
                         data-res-guest="<?php echo $reservation['guest_name']; ?>"
                         data-res-email="<?php echo $reservation['guest_email']; ?>"
                         data-res-phone="<?php echo $reservation['guest_phone']; ?>"
                         data-res-room="<?php echo $reservation['room_number']; ?>"
                         data-res-roomtype="<?php echo $reservation['type_name']; ?>"
                         data-res-baseprice="<?php echo $reservation['base_price']; ?>"
                         data-res-amount="<?php echo $reservation['total_amount']; ?>"
                         data-res-status="<?php echo $reservation['status']; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start">
                                    <div class="room-icon me-3">
                                        <i class="bi bi-door-closed"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1">Reservation #<?php echo $reservation['reservation_id']; ?></h5>
                                                <p class="text-muted mb-0">
                                                    Room <?php echo $reservation['room_number']; ?> - <?php echo $reservation['type_name']; ?>
                                                </p>
                                            </div>
                                            <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $reservation['status'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Check-in:</strong> <?php echo date('M d, Y g:i A', strtotime($reservation['check_in_date'] . ' ' . $reservation['check_in_time'])); ?></p>
                                                <p class="mb-1"><strong>Check-out:</strong> <?php echo date('M d, Y g:i A', strtotime($reservation['check_out_date'] . ' ' . $reservation['check_out_time'])); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Duration:</strong> <?php echo max(1, $reservation['duration_hours']); ?> hour<?php echo $reservation['duration_hours'] != 1 ? 's' : ''; ?></p>
                                                <p class="mb-1"><strong>Guest:</strong> <?php echo $reservation['guest_name']; ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $reservation['reservation_id']; ?>)">
                                                <i class="bi bi-eye me-1"></i> View Details
                                            </button>
                                            
                                            <?php if (in_array($reservation['status'], ['confirmed', 'checked_in'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="openExtendModal(<?php echo $reservation['reservation_id']; ?>)">
                                                <i class="bi bi-arrow-clockwise me-1"></i> Extend
                                            </button>
                                            <?php endif; ?>

                                            <?php if ($reservation['status'] == 'confirmed'): ?>
                                            <button class="btn btn-sm btn-outline-warning" onclick="cancelBooking(<?php echo $reservation['reservation_id']; ?>)">
                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-outline-primary" onclick="printReceipt(<?php echo $reservation['reservation_id']; ?>)">
                                                <i class="bi bi-printer me-1"></i> Print
                                            </button>

                                            <button class="btn btn-sm btn-outline-success" onclick="downloadReceipt(<?php echo $reservation['reservation_id']; ?>)">
                                                <i class="bi bi-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="price-display">
                                    ₱<?php echo number_format($reservation['total_amount'], 2); ?>
                                </div>
                                <small class="text-muted">Total Amount</small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-calendar-x" style="font-size: 4rem; color: #ccc;"></i>
                            <h4 class="mt-4">No Bookings Found</h4>
                            <p class="text-muted mb-4">You haven't made any reservations yet.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-house-door me-2"></i>Browse Rooms
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Reservation Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reservation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">RESERVATION ID</h6>
                            <p id="detailResId" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h6 class="text-muted">STATUS</h6>
                            <p id="detailStatus" class="fw-bold"></p>
                        </div>
                    </div>
                    <hr>
                    <h6 class="text-muted mb-3">CHECK-IN & CHECK-OUT</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Check-in Date</p>
                            <p id="detailCheckIn" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Check-out Date</p>
                            <p id="detailCheckOut" class="fw-bold"></p>
                        </div>
                    </div>
                    <p class="text-muted mb-1">Number of Nights</p>
                    <p id="detailNights" class="fw-bold mb-3"></p>
                    <hr>
                    <h6 class="text-muted mb-3">ROOM INFORMATION</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Room Number</p>
                            <p id="detailRoomNumber" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Room Type</p>
                            <p id="detailRoomType" class="fw-bold"></p>
                        </div>
                    </div>
                    <hr>
                    <h6 class="text-muted mb-3">GUEST INFORMATION</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Guest Name</p>
                            <p id="detailGuestName" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Email</p>
                            <p id="detailGuestEmail" class="fw-bold"></p>
                        </div>
                    </div>
                    <p class="text-muted mb-1">Phone</p>
                    <p id="detailGuestPhone" class="fw-bold mb-3"></p>
                    <hr>
                    <h6 class="text-muted mb-3">PRICING</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Nightly Rate</p>
                            <p id="detailBasePrice" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="text-muted mb-1">Total Amount</p>
                            <p id="detailTotalAmount" class="fw-bold" style="font-size: 1.3rem; color: #28a745;"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Extension Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Reservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="extendForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="extend">
                        <input type="hidden" name="reservation_id" id="extend_reservation_id">

                        <div class="mb-3">
                            <label class="form-label">Extend by</label>
                            <select class="form-select" name="extension_type" id="extension_type" required>
                                <option value="">Choose extension length</option>
                                <option value="1_hour">1 hour</option>
                                <option value="3_hours">3 hours</option>
                                <option value="6_hours">6 hours</option>
                                <option value="1_day">1 day</option>
                                <option value="2_days">2 days</option>
                                <option value="1_week">1 week</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            The price will be adjusted automatically based on the new checkout date and time.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Extend Reservation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(reservationId) {
            // Find the booking card with this reservation ID
            const card = document.querySelector(`[data-res-id="${reservationId}"]`);
            if (!card) return;
            
            const checkIn = new Date(card.dataset.resCheckin);
            const checkOut = new Date(card.dataset.resCheckout);

            // Populate modal with data from attributes
            document.getElementById('detailResId').textContent = '#' + card.dataset.resId;
            document.getElementById('detailStatus').innerHTML = `<span class="badge bg-info">${card.dataset.resStatus.replace('_', ' ').toUpperCase()}</span>`;
            document.getElementById('detailCheckIn').textContent = checkIn.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
            document.getElementById('detailCheckOut').textContent = checkOut.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
            document.getElementById('detailNights').textContent = card.dataset.resHours + ' hour' + (card.dataset.resHours > 1 ? 's' : '');
            document.getElementById('detailRoomNumber').textContent = card.dataset.resRoom;
            document.getElementById('detailRoomType').textContent = card.dataset.resRoomtype;
            document.getElementById('detailGuestName').textContent = card.dataset.resGuest;
            document.getElementById('detailGuestEmail').textContent = card.dataset.resEmail;
            document.getElementById('detailGuestPhone').textContent = card.dataset.resPhone;
            document.getElementById('detailBasePrice').textContent = '₱' + parseFloat(card.dataset.resBaseprice).toFixed(2);
            document.getElementById('detailTotalAmount').textContent = '₱' + parseFloat(card.dataset.resAmount).toFixed(2);
            
            // Open the modal
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
        }

        function openExtendModal(reservationId) {
            document.getElementById('extend_reservation_id').value = reservationId;
            document.getElementById('extension_type').value = '';
            const modal = new bootstrap.Modal(document.getElementById('extendModal'));
            modal.show();
        }
        
        function cancelBooking(reservationId) {
            if (confirm('Are you sure you want to cancel this reservation?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function printReceipt(reservationId) {
            window.open('generate_receipt.php?id=' + reservationId + '&print=1', 'receipt', 'width=900,height=700');
        }

        function downloadReceipt(reservationId) {
            window.location.href = 'generate_receipt.php?id=' + reservationId + '&download=1';
        }
    </script>
</body>
</html>
