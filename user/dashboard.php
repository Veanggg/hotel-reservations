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

$db = new Database();
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
        
        // Update room status
        $db->query("UPDATE rooms SET status = 'reserved' WHERE room_id = $room_id");
        
        $success = "Room booked successfully!";
    }
}

// Get available rooms for booking
$roomsQuery = "
    SELECT r.*, rt.type_name, rt.base_price, rt.max_occupancy 
    FROM rooms r 
    JOIN room_types rt ON r.type_id = rt.type_id 
    WHERE r.status = 'available' 
    ORDER BY r.floor_number, r.room_number
";
$roomsResult = $db->query($roomsQuery);
$rooms = [];
while ($room = $roomsResult->fetch_assoc()) {
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

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
            height: 100%;
        }
        .room-card:hover {
            transform: translateY(-3px);
        }
        .room-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .price-tag {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
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
        .welcome-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
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

                <!-- Available Rooms Section -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">Available Rooms</h3>
                            <p class="text-muted mb-0">
                                <span id="availableRoomsCount"><?php echo (int)$roomSummary['available_rooms']; ?></span>
                                of
                                <span id="totalRoomsCount"><?php echo (int)$roomSummary['total_rooms']; ?></span>
                                rooms available
                            </p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#searchModal">
                            <i class="bi bi-search me-2"></i>Search Rooms
                        </button>
                    </div>
                </div>

                <div class="row mb-4" id="availableRoomsList">
                    <?php foreach ($rooms as $room): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="room-card">
                            <div class="room-image">
                                <i class="bi bi-door-closed"></i>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">Room <?php echo $room['room_number']; ?></h5>
                                    <small class="text-muted">Floor <?php echo $room['floor_number']; ?></small>
                                </div>
                                    <span class="price-tag">₱<?php echo number_format($room['base_price'], 2); ?>/night</span>
                            </div>
                            
                            <button class="btn btn-primary w-100" 
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

                <!-- My Reservations Section -->
                <div class="page-header">
                    <div>
                        <h3 class="mb-0">My Reservations</h3>
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
                                        <th>Type</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($reservation = $reservationsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $reservation['reservation_id']; ?></td>
                                        <td><?php echo $reservation['room_number']; ?></td>
                                        <td><?php echo $reservation['type_name']; ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($reservation['check_in_date'] . ' ' . $reservation['check_in_time'])); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($reservation['check_out_date'] . ' ' . $reservation['check_out_time'])); ?></td>
                                        <td>₱<?php echo number_format($reservation['total_amount'], 2); ?></td>
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
                            <p class="text-muted">You haven't made any reservations. Book your first room above!</p>
                        </div>
                        <?php endif; ?>
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
        const currentCheckInDate = <?php echo json_encode($currentCheckInDate); ?>;
        const currentCheckInTime = <?php echo json_encode($currentCheckInTime); ?>;

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

        function renderAvailableRooms() {
            const roomList = document.getElementById('availableRoomsList');

            if (!availableRooms.length) {
                roomList.innerHTML = `
                    <div class="col-12">
                        <div class="text-center py-5 bg-white rounded shadow-sm">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                            <h5 class="mt-3">No Available Rooms</h5>
                            <p class="text-muted mb-0">Please check again later.</p>
                        </div>
                    </div>
                `;
                return;
            }

            roomList.innerHTML = availableRooms.map((room) => `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="room-card">
                        <div class="room-image">
                            <i class="bi bi-door-closed"></i>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">Room ${escapeHtml(room.room_number)}</h5>
                                <small class="text-muted">Floor ${escapeHtml(room.floor_number)}</small>
                            </div>
                            <span class="price-tag">₱${formatMoney(room.base_price)}/night</span>
                        </div>
                        <button class="btn btn-primary w-100"
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
                    } else {
                        document.getElementById('total_amount').value = '';
                    }
                } else {
                    checkout.setCustomValidity('Check-out must be after check-in.');
                    document.getElementById('total_amount').value = '';
                }
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
        
        // Set today as minimum date for check-in
        const today = currentCheckInDate;
        document.getElementById('check_in_date').min = today;
        renderAvailableRooms();
        renderRoomOptions();
        setInterval(refreshRoomData, 10000);
    </script>
</body>
</html>
