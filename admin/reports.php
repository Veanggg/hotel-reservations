<?php
// Reports Page - SQL Queries and Data Analysis
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/payment.php';

$db = new Database();
// Ensure payment table has payment_type column
ensurePaymentTypeColumn($db);

// Get date range from form
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Query 1: Revenue Report (Aggregate Query - SUM)
$revenueQuery = "
    SELECT 
        DATE_FORMAT(p.payment_date, '%Y-%m') AS month,
        COUNT(p.payment_id) AS payment_count,
        SUM(p.amount) AS total_revenue,
        AVG(p.amount) AS average_payment,
        MAX(p.amount) AS highest_payment,
        MIN(p.amount) AS lowest_payment
    FROM payments p
    WHERE p.status = 'completed' 
    AND p.payment_date BETWEEN '$start_date' AND '$end_date 23:59:59'
    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
    ORDER BY month DESC
";
$revenueResult = $db->query($revenueQuery);

// Query 2: Room Occupancy Report (Aggregate Query - COUNT)
$occupancyQuery = "
    SELECT 
        rt.type_name,
        COUNT(r.room_id) AS total_rooms,
        SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) AS available_rooms,
        SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) AS occupied_rooms,
        SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_rooms,
        SUM(CASE WHEN r.status = 'reserved' THEN 1 ELSE 0 END) AS reserved_rooms,
        ROUND((SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) * 100.0 / COUNT(r.room_id)), 2) AS occupancy_rate
    FROM room_types rt
    LEFT JOIN rooms r ON rt.type_id = r.type_id
    GROUP BY rt.type_id, rt.type_name
    ORDER BY occupancy_rate DESC
";
$occupancyResult = $db->query($occupancyQuery);

// Query 3: Guest Statistics (Aggregate Query - COUNT, AVG)
$guestStatsQuery = "
    SELECT 
        COUNT(DISTINCT g.guest_id) AS total_guests,
        COUNT(r.reservation_id) AS total_reservations,
        AVG(DATEDIFF(r.check_out_date, r.check_in_date)) AS avg_stay_duration,
        SUM(r.total_amount) AS total_revenue_all_time,
        AVG(r.total_amount) AS avg_reservation_value
    FROM guests g
    LEFT JOIN reservations r ON g.guest_id = r.guest_id
";
$guestStatsResult = $db->query($guestStatsQuery);
$guestStats = $guestStatsResult->fetch_assoc();

// Query 4: Top Guests by Revenue (JOIN Query)
$topGuestsQuery = "
    SELECT 
        g.guest_id,
        CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
        g.email,
        COUNT(r.reservation_id) AS total_reservations,
        SUM(r.total_amount) AS total_spent,
        AVG(r.total_amount) AS avg_spending,
        MAX(r.created_at) AS last_reservation_date
    FROM guests g
    INNER JOIN reservations r ON g.guest_id = r.guest_id
    GROUP BY g.guest_id, g.first_name, g.last_name, g.email
    HAVING total_reservations > 0
    ORDER BY total_spent DESC
    LIMIT 10
";
$topGuestsResult = $db->query($topGuestsQuery);

// Query 5: Monthly Occupancy Trends (JOIN + Aggregate)
$monthlyTrendsQuery = "
    SELECT 
        DATE_FORMAT(check_in_date, '%Y-%m') AS month,
        COUNT(reservation_id) AS total_reservations,
        SUM(total_amount) AS monthly_revenue,
        AVG(DATEDIFF(check_out_date, check_in_date)) AS avg_stay_length,
        COUNT(DISTINCT guest_id) AS unique_guests
    FROM reservations
    WHERE check_in_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$monthlyTrendsResult = $db->query($monthlyTrendsQuery);

// Query 6: Service Usage Report (JOIN Query)
$serviceUsageQuery = "
    SELECT 
        s.service_name,
        COUNT(gs.guest_service_id) AS usage_count,
        SUM(gs.total_price) AS total_revenue,
        AVG(gs.quantity) AS avg_quantity_per_use,
        COUNT(DISTINCT gs.reservation_id) AS unique_reservations
    FROM services s
    LEFT JOIN guest_services gs ON s.service_id = gs.service_id
    GROUP BY s.service_id, s.service_name
    HAVING usage_count > 0
    ORDER BY total_revenue DESC
";
$serviceUsageResult = $db->query($serviceUsageQuery);

// Query 7: Payment Methods Analysis (Aggregate Query)
$paymentMethodsQuery = "
    SELECT 
        payment_method,
        COALESCE(payment_type, 'full') AS payment_type,
        COUNT(payment_id) AS transaction_count,
        SUM(amount) AS total_amount,
        AVG(amount) AS avg_amount,
        ROUND(COUNT(payment_id) * 100.0 / (SELECT COUNT(*) FROM payments WHERE status = 'completed'), 2) AS percentage
    FROM payments
    WHERE status = 'completed'
    GROUP BY payment_method, COALESCE(payment_type, 'full')
    ORDER BY total_amount DESC
";
$paymentMethodsResult = $db->query($paymentMethodsQuery);

// Query 7b: Payment Method Summary (without payment_type breakdown)
$paymentMethodsSummaryQuery = "
    SELECT 
        payment_method,
        COUNT(payment_id) AS transaction_count,
        SUM(amount) AS total_amount,
        AVG(amount) AS avg_amount,
        ROUND(COUNT(payment_id) * 100.0 / (SELECT COUNT(*) FROM payments WHERE status = 'completed'), 2) AS percentage
    FROM payments
    WHERE status = 'completed'
    GROUP BY payment_method
    ORDER BY total_amount DESC
";
$paymentMethodsSummaryResult = $db->query($paymentMethodsSummaryQuery);

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Hotel Reservation System</title>
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
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
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
                            <a class="nav-link" href="reservations.php">
                                <i class="bi bi-calendar-check me-2"></i> Reservations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="guests.php">
                                <i class="bi bi-people me-2"></i> Guests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">
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
                            <h2 class="mb-0">Reports & Analytics</h2>
                            <p class="text-muted mb-0">Comprehensive hotel performance reports</p>
                        </div>
                        <form method="GET" class="d-flex gap-2">
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-calendar-range me-2"></i>Filter
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Overview Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3><?php echo $guestStats['total_guests']; ?></h3>
                            <p>Total Guests</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3><?php echo $guestStats['total_reservations']; ?></h3>
                            <p>Total Reservations</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3><?php echo number_format($guestStats['avg_stay_duration'], 1); ?></h3>
                            <p>Avg Stay (Days)</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3>₱<?php echo number_format($guestStats['total_revenue_all_time'], 0); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Revenue Report -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-currency-dollar me-2"></i>Revenue Analysis</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Transactions</th>
                                    <th>Total Revenue</th>
                                    <th>Average Payment</th>
                                    <th>Highest Payment</th>
                                    <th>Lowest Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($revenue = $revenueResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $revenue['month']; ?></td>
                                    <td><?php echo $revenue['payment_count']; ?></td>
                                    <td>₱<?php echo number_format($revenue['total_revenue'], 2); ?></td>
                                    <td>₱<?php echo number_format($revenue['average_payment'], 2); ?></td>
                                    <td>₱<?php echo number_format($revenue['highest_payment'], 2); ?></td>
                                    <td>₱<?php echo number_format($revenue['lowest_payment'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Room Occupancy Report -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-door-closed me-2"></i>Room Occupancy by Type</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Room Type</th>
                                    <th>Total Rooms</th>
                                    <th>Available</th>
                                    <th>Occupied</th>
                                    <th>Maintenance</th>
                                    <th>Reserved</th>
                                    <th>Occupancy Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($occupancy = $occupancyResult->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $occupancy['type_name']; ?></strong></td>
                                    <td><?php echo $occupancy['total_rooms']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $occupancy['available_rooms']; ?></span></td>
                                    <td><span class="badge bg-primary"><?php echo $occupancy['occupied_rooms']; ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo $occupancy['maintenance_rooms']; ?></span></td>
                                    <td><span class="badge bg-info"><?php echo $occupancy['reserved_rooms']; ?></span></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $occupancy['occupancy_rate']; ?>%">
                                                <?php echo $occupancy['occupancy_rate']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Guests Report -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-trophy me-2"></i>Top Guests by Revenue</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Guest Name</th>
                                    <th>Email</th>
                                    <th>Reservations</th>
                                    <th>Total Spent</th>
                                    <th>Avg Spending</th>
                                    <th>Last Reservation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($guest = $topGuestsResult->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $guest['guest_name']; ?></strong></td>
                                    <td><?php echo $guest['email']; ?></td>
                                    <td><?php echo $guest['total_reservations']; ?></td>
                                    <td>₱<?php echo number_format($guest['total_spent'], 2); ?></td>
                                    <td>₱<?php echo number_format($guest['avg_spending'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($guest['last_reservation_date'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Monthly Trends -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-graph-up-arrow me-2"></i>Monthly Trends</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Reservations</th>
                                    <th>Revenue</th>
                                    <th>Avg Stay Length</th>
                                    <th>Unique Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($trend = $monthlyTrendsResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $trend['month']; ?></td>
                                    <td><?php echo $trend['total_reservations']; ?></td>
                                    <td>₱<?php echo number_format($trend['monthly_revenue'], 2); ?></td>
                                    <td><?php echo number_format($trend['avg_stay_length'], 1); ?> days</td>
                                    <td><?php echo $trend['unique_guests']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Service Usage Report -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-stars me-2"></i>Service Usage Analysis</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Usage Count</th>
                                    <th>Total Revenue</th>
                                    <th>Avg Quantity</th>
                                    <th>Unique Reservations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($service = $serviceUsageResult->fetch_assoc()): ?>
                                <?php $avgQuantity = $service['avg_quantity'] ?? $service['avg_quantity_per_use'] ?? 0; ?>
                                <tr>
                                    <td><strong><?php echo $service['service_name']; ?></strong></td>
                                    <td><?php echo $service['usage_count']; ?></td>
                                    <td>₱<?php echo number_format($service['total_revenue'], 2); ?></td>
                                    <td><?php echo number_format($avgQuantity, 1); ?></td>
                                    <td><?php echo $service['unique_reservations']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Methods Report -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-credit-card me-2"></i>Payment Methods Analysis</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Payment Type</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                    <th>Average Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasData = false;
                                if ($paymentMethodsResult->num_rows > 0) {
                                    $hasData = true;
                                    while ($payment = $paymentMethodsResult->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php 
                                            $methods = [
                                                'cash' => '💵 Cash',
                                                'gcash' => '📱 GCash',
                                                'paypal' => '🌐 PayPal',
                                                'credit_card' => '💳 Credit Card',
                                                'bank_transfer' => '🏦 Bank Transfer'
                                            ];
                                            echo $methods[$payment['payment_method']] ?? ucfirst(str_replace('_', ' ', $payment['payment_method']));
                                            ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $payment['payment_type'] === 'half' ? 'bg-warning' : 'bg-success'; ?>">
                                            <?php echo $payment['payment_type'] === 'half' ? 'Half Payment' : 'Full Payment'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $payment['transaction_count']; ?></td>
                                    <td><strong>₱<?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                                    <td>₱<?php echo number_format($payment['avg_amount'], 2); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $payment['percentage']; ?>%; background: linear-gradient(90deg, #667eea, #764ba2);">
                                                <?php echo $payment['percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php if (!$hasData): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No payment data available yet. Payments will appear here once bookings are completed.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
