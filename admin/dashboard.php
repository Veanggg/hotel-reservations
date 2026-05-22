<?php
// Admin Dashboard
// Hotel Reservation System

require_once __DIR__ . '/../functions/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

// Get dashboard statistics
$db = new Database();

// Total rooms
$totalRoomsResult = $db->query("SELECT COUNT(*) as total FROM rooms");
$totalRooms = $totalRoomsResult->fetch_assoc()['total'];

// Available rooms
$availableRoomsResult = $db->query("SELECT COUNT(*) as available FROM rooms WHERE status = 'available'");
$availableRooms = $availableRoomsResult->fetch_assoc()['available'];

// Total reservations
$totalReservationsResult = $db->query("SELECT COUNT(*) as total FROM reservations");
$totalReservations = $totalReservationsResult->fetch_assoc()['total'];

// Today's actual check-ins
$todayCheckinsResult = $db->query("SELECT COUNT(*) as count FROM reservations WHERE check_in_date = CURDATE() AND status = 'checked_in'");
$todayCheckins = $todayCheckinsResult->fetch_assoc()['count'];

// Today's actual check-outs
$todayCheckoutsResult = $db->query("SELECT COUNT(*) as count FROM reservations WHERE check_out_date = CURDATE() AND status = 'checked_out'");
$todayCheckouts = $todayCheckoutsResult->fetch_assoc()['count'];

// Recent reservations
$recentReservationsResult = $db->query("
    SELECT r.reservation_id, CONCAT(g.first_name, ' ', g.last_name) as guest_name, 
           room.room_number, r.check_in_date, r.check_out_date, r.status, r.total_amount
    FROM reservations r
    JOIN guests g ON r.guest_id = g.guest_id
    JOIN rooms room ON r.room_id = room.room_id
    ORDER BY r.created_at DESC
    LIMIT 5
");

// Revenue this month
$revenueResult = $db->query("
    SELECT COALESCE(SUM(total_amount), 0) as revenue
    FROM reservations
    WHERE status IN ('checked_in', 'checked_out')
    AND MONTH(check_in_date) = MONTH(CURDATE())
    AND YEAR(check_in_date) = YEAR(CURDATE())
");
$monthlyRevenue = $revenueResult->fetch_assoc()['revenue'];

// Revenue analysis
$revenueAnalysisResult = $db->query("
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN check_in_date = CURDATE() THEN total_amount ELSE 0 END), 0) AS today_revenue,
        COALESCE(AVG(total_amount), 0) AS average_reservation,
        COUNT(*) AS paid_reservations
    FROM reservations
    WHERE status IN ('checked_in', 'checked_out')
");
$revenueAnalysis = $revenueAnalysisResult->fetch_assoc();

$previousMonthRevenueResult = $db->query("
    SELECT COALESCE(SUM(total_amount), 0) AS revenue
    FROM reservations
    WHERE status IN ('checked_in', 'checked_out')
      AND check_in_date >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
      AND check_in_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$previousMonthRevenue = (float)$previousMonthRevenueResult->fetch_assoc()['revenue'];
$currentMonthRevenue = (float)$monthlyRevenue;
$monthlyRevenueChange = $previousMonthRevenue > 0
    ? (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100
    : ($currentMonthRevenue > 0 ? 100 : 0);

$revenueTrendResult = $db->query("
    SELECT DATE_FORMAT(check_in_date, '%b %Y') AS month_label,
           COALESCE(SUM(total_amount), 0) AS revenue
    FROM reservations
    WHERE status IN ('checked_in', 'checked_out')
      AND check_in_date >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
    GROUP BY DATE_FORMAT(check_in_date, '%Y-%m'), DATE_FORMAT(check_in_date, '%b %Y')
    ORDER BY DATE_FORMAT(check_in_date, '%Y-%m')
");
$revenueTrend = [];
$maxTrendRevenue = 0;
while ($trend = $revenueTrendResult->fetch_assoc()) {
    $trend['revenue'] = (float)$trend['revenue'];
    $maxTrendRevenue = max($maxTrendRevenue, $trend['revenue']);
    $revenueTrend[] = $trend;
}

$monthlyLineResult = $db->query("
    SELECT DATE_FORMAT(check_in_date, '%b %Y') AS label,
           DATE_FORMAT(check_in_date, '%Y-%m') AS sort_month,
           COALESCE(SUM(total_amount), 0) AS revenue
    FROM reservations
    WHERE status IN ('checked_in', 'checked_out')
      AND check_in_date >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
    GROUP BY DATE_FORMAT(check_in_date, '%Y-%m'), DATE_FORMAT(check_in_date, '%b %Y')
    ORDER BY sort_month
");
$monthlyLineLabels = [];
$monthlyLineData = [];
while ($month = $monthlyLineResult->fetch_assoc()) {
    $monthlyLineLabels[] = $month['label'];
    $monthlyLineData[] = (float)$month['revenue'];
}
if (count($monthlyLineLabels) === 0) {
    $monthlyLineLabels[] = date('M Y');
    $monthlyLineData[] = 0;
}

$weeklyLineResult = $db->query("
    SELECT CONCAT('Week ', WEEK(check_in_date, 1), ' ', YEAR(check_in_date)) AS label,
           YEARWEEK(check_in_date, 1) AS sort_week,
           COALESCE(SUM(total_amount), 0) AS revenue
    FROM reservations
    WHERE status IN ('checked_in', 'checked_out')
      AND check_in_date >= CURDATE() - INTERVAL 11 WEEK
    GROUP BY YEARWEEK(check_in_date, 1), YEAR(check_in_date), WEEK(check_in_date, 1)
    ORDER BY sort_week
");
$weeklyLineLabels = [];
$weeklyLineData = [];
while ($week = $weeklyLineResult->fetch_assoc()) {
    $weeklyLineLabels[] = $week['label'];
    $weeklyLineData[] = (float)$week['revenue'];
}
if (count($weeklyLineLabels) === 0) {
    $weeklyLineLabels[] = 'Week ' . date('W Y');
    $weeklyLineData[] = 0;
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hotel Reservation System</title>
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .stat-icon.rooms { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.reservations { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-icon.checkins { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-icon.revenue { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .analysis-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        .metric-tile {
            border: 1px solid #eef0f4;
            border-radius: 8px;
            padding: 16px;
            height: 100%;
        }
        .trend-bar {
            height: 10px;
            border-radius: 999px;
            background: #e9ecef;
            overflow: hidden;
        }
        .trend-bar-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #198754, #20c997);
        }
        .method-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #198754;
            display: inline-block;
        }
        .chart-wrap {
            height: 300px;
            position: relative;
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
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="reports.php">
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
                            <h2 class="mb-0">Admin Dashboard</h2>
                            <p class="text-muted mb-0">Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
                        </div>
                        <div>
                            <?php echo getRoleBadge($_SESSION['user_role']); ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon rooms">
                                    <i class="bi bi-door-closed"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $totalRooms; ?></h3>
                                    <p class="text-muted mb-0">Total Rooms</p>
                                    <small class="text-success"><?php echo $availableRooms; ?> available</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon reservations">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $totalReservations; ?></h3>
                                    <p class="text-muted mb-0">Total Reservations</p>
                                    <small class="text-info">All time</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon checkins">
                                    <i class="bi bi-arrow-left-right"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $todayCheckins; ?></h3>
                                    <p class="text-muted mb-0">Today's Checked In</p>
                                    <small class="text-warning"><?php echo $todayCheckouts; ?> check-outs</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon revenue">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0">₱<?php echo number_format($monthlyRevenue, 2); ?></h3>
                                    <p class="text-muted mb-0">Checked-in Revenue</p>
                                    <small class="text-success">This month</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Analysis -->
                <div class="row mb-4">
                    <div class="col-12 mb-3">
                        <h5 class="mb-0">Revenue Analysis</h5>
                        <small class="text-muted">Revenue from reservations marked checked in or checked out</small>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="metric-tile bg-white">
                            <small class="text-muted">Paid Reservation Revenue</small>
                            <h4 class="mb-1">₱<?php echo number_format($revenueAnalysis['total_revenue'], 2); ?></h4>
                            <span class="text-muted"><?php echo $revenueAnalysis['paid_reservations']; ?> checked-in records</span>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="metric-tile bg-white">
                            <small class="text-muted">Today's Checked-in Revenue</small>
                            <h4 class="mb-1">₱<?php echo number_format($revenueAnalysis['today_revenue'], 2); ?></h4>
                            <span class="text-muted"><?php echo date('M d, Y'); ?></span>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="metric-tile bg-white">
                            <small class="text-muted">Average Paid Reservation</small>
                            <h4 class="mb-1">₱<?php echo number_format($revenueAnalysis['average_reservation'], 2); ?></h4>
                            <span class="text-muted">Per checked-in record</span>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="metric-tile bg-white">
                            <small class="text-muted">Month vs Previous</small>
                            <h4 class="mb-1"><?php echo ($monthlyRevenueChange >= 0 ? '+' : '') . number_format($monthlyRevenueChange, 1); ?>%</h4>
                            <span class="<?php echo $monthlyRevenueChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                Previous: ₱<?php echo number_format($previousMonthRevenue, 2); ?>
                            </span>
                        </div>
                    </div>

                    <div class="col-lg-12 mb-3">
                        <div class="analysis-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">6-Month Revenue Trend</h6>
                                <small class="text-muted">Checked-in reservations</small>
                            </div>
                            <?php if (count($revenueTrend) > 0): ?>
                                <?php foreach ($revenueTrend as $trend): ?>
                                    <?php $barWidth = $maxTrendRevenue > 0 ? ($trend['revenue'] / $maxTrendRevenue) * 100 : 0; ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?php echo $trend['month_label']; ?></span>
                                            <strong>₱<?php echo number_format($trend['revenue'], 2); ?></strong>
                                        </div>
                                        <div class="trend-bar">
                                            <div class="trend-bar-fill" style="width: <?php echo $barWidth; ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No checked-in reservation revenue yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="analysis-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Monthly Revenue Line Graph</h6>
                                <small class="text-muted">Checked-in revenue</small>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="monthlyRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <div class="analysis-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Weekly Revenue Line Graph</h6>
                                <small class="text-muted">Checked-in revenue</small>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="weeklyRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="mb-0">Recent Reservations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Guest Name</th>
                                                <th>Room</th>
                                                <th>Check-in</th>
                                                <th>Check-out</th>
                                                <th>Status</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($reservation = $recentReservationsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td>#<?php echo $reservation['reservation_id']; ?></td>
                                                <td><?php echo $reservation['guest_name']; ?></td>
                                                <td><?php echo $reservation['room_number']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($reservation['check_in_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($reservation['check_out_date'])); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch($reservation['status']) {
                                                        case 'confirmed': $statusClass = 'bg-primary'; break;
                                                        case 'checked_in': $statusClass = 'bg-success'; break;
                                                        case 'checked_out': $statusClass = 'bg-secondary'; break;
                                                        case 'cancelled': $statusClass = 'bg-danger'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $reservation['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>₱<?php echo number_format($reservation['total_amount'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const monthlyRevenueLabels = <?php echo json_encode($monthlyLineLabels); ?>;
        const monthlyRevenueData = <?php echo json_encode($monthlyLineData); ?>;
        const weeklyRevenueLabels = <?php echo json_encode($weeklyLineLabels); ?>;
        const weeklyRevenueData = <?php echo json_encode($weeklyLineData); ?>;

        function pesoTick(value) {
            return '₱' + Number(value).toLocaleString();
        }

        function buildRevenueLineChart(canvasId, labels, data, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                return;
            }

            const wrapper = canvas.parentElement;
            const ratio = window.devicePixelRatio || 1;
            const width = wrapper.clientWidth || 600;
            const height = wrapper.clientHeight || 300;
            canvas.width = width * ratio;
            canvas.height = height * ratio;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';

            const ctx = canvas.getContext('2d');
            ctx.scale(ratio, ratio);
            ctx.clearRect(0, 0, width, height);

            const padding = { top: 24, right: 20, bottom: 46, left: 74 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;
            const values = data.map(Number);
            const maxValue = Math.max(...values, 1);
            const pointCount = Math.max(labels.length, 1);

            ctx.font = '12px Arial';
            ctx.strokeStyle = '#e9ecef';
            ctx.lineWidth = 1;
            ctx.fillStyle = '#6c757d';

            for (let i = 0; i <= 4; i++) {
                const y = padding.top + (chartHeight / 4) * i;
                const value = maxValue - (maxValue / 4) * i;
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(width - padding.right, y);
                ctx.stroke();
                ctx.fillText(pesoTick(Math.round(value)), 8, y + 4);
            }

            const points = values.map((value, index) => {
                const x = pointCount === 1
                    ? padding.left + chartWidth / 2
                    : padding.left + (chartWidth / (pointCount - 1)) * index;
                const y = padding.top + chartHeight - ((value / maxValue) * chartHeight);
                return { x, y, value, label: labels[index] };
            });

            if (points.length > 1) {
                ctx.beginPath();
                ctx.moveTo(points[0].x, points[0].y);
                points.slice(1).forEach((point) => ctx.lineTo(point.x, point.y));
                ctx.strokeStyle = color;
                ctx.lineWidth = 3;
                ctx.stroke();
            }

            points.forEach((point, index) => {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
                ctx.fillStyle = color;
                ctx.fill();

                if (index === 0 || index === points.length - 1 || points.length <= 6) {
                    ctx.fillStyle = '#495057';
                    ctx.textAlign = 'center';
                    ctx.fillText(point.label, point.x, height - 20);
                    ctx.fillText(pesoTick(point.value), point.x, Math.max(16, point.y - 10));
                }
            });
        }

        function drawRevenueCharts() {
            buildRevenueLineChart('monthlyRevenueChart', monthlyRevenueLabels, monthlyRevenueData, '#198754');
            buildRevenueLineChart('weeklyRevenueChart', weeklyRevenueLabels, weeklyRevenueData, '#0d6efd');
        }

        drawRevenueCharts();
        window.addEventListener('resize', drawRevenueCharts);
    </script>
</body>
</html>


