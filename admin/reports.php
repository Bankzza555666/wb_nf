<?php
require_once './controller/admin_controller/admin_config.php';
checkAdminAuth();

// Get date range
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Helper function to safely query
function safeQuery($conn, $sql) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row;
    }
    return null;
}

// Revenue Stats
$vpn_revenue = 0;
$ssh_revenue = 0;
$topup_revenue = 0;

$row = safeQuery($conn, "SELECT COALESCE(SUM(price_paid), 0) as total FROM user_rentals WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' AND deleted_at IS NULL");
if ($row) $vpn_revenue = floatval($row['total']);

$row = safeQuery($conn, "SELECT COALESCE(SUM(price_paid), 0) as total FROM ssh_rentals WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'");
if ($row) $ssh_revenue = floatval($row['total']);

$row = safeQuery($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM topup_transactions WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' AND status = 'completed'");
if ($row) $topup_revenue = floatval($row['total']);

// Count Stats
$new_users = 0;
$new_vpn_rentals = 0;
$new_ssh_rentals = 0;

$row = safeQuery($conn, "SELECT COUNT(*) as c FROM users WHERE DATE(register_at) BETWEEN '$start_date' AND '$end_date'");
if ($row) $new_users = intval($row['c']);

$row = safeQuery($conn, "SELECT COUNT(*) as c FROM user_rentals WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' AND deleted_at IS NULL");
if ($row) $new_vpn_rentals = intval($row['c']);

$row = safeQuery($conn, "SELECT COUNT(*) as c FROM ssh_rentals WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'");
if ($row) $new_ssh_rentals = intval($row['c']);

// Daily Revenue (last 30 days)
$daily_revenue = [];
$daily_result = $conn->query("
    SELECT DATE(created_at) as date, SUM(price_paid) as revenue, 'vpn' as type 
    FROM user_rentals 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND deleted_at IS NULL
    GROUP BY DATE(created_at)
    UNION ALL
    SELECT DATE(created_at) as date, SUM(price_paid) as revenue, 'ssh' as type 
    FROM ssh_rentals 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
if ($daily_result) {
    while ($row = $daily_result->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($daily_revenue[$date])) {
            $daily_revenue[$date] = ['vpn' => 0, 'ssh' => 0];
        }
        $daily_revenue[$date][$row['type']] = floatval($row['revenue']);
    }
}

// Top Packages
$top_vpn_data = [];
$top_vpn = $conn->query("
    SELECT p.filename, COUNT(*) as count, SUM(r.price_paid) as revenue
    FROM user_rentals r
    JOIN price_v2 p ON r.price_id = p.id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND r.deleted_at IS NULL
    GROUP BY r.price_id
    ORDER BY count DESC
    LIMIT 5
");
if ($top_vpn) {
    while ($row = $top_vpn->fetch_assoc()) {
        $top_vpn_data[] = $row;
    }
}

$top_ssh_data = [];
$top_ssh = $conn->query("
    SELECT p.product_name as name, COUNT(*) as count, SUM(r.price_paid) as revenue
    FROM ssh_rentals r
    JOIN ssh_products p ON r.product_id = p.id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY r.product_id
    ORDER BY count DESC
    LIMIT 5
");
if ($top_ssh) {
    while ($row = $top_ssh->fetch_assoc()) {
        $top_ssh_data[] = $row;
    }
}

// Monthly comparison
$this_month = 0;
$last_month = 0;

$row = safeQuery($conn, "SELECT COALESCE(SUM(price_paid), 0) as total FROM user_rentals WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND deleted_at IS NULL");
if ($row) $this_month += floatval($row['total']);

$row = safeQuery($conn, "SELECT COALESCE(SUM(price_paid), 0) as total FROM ssh_rentals WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
if ($row) $this_month += floatval($row['total']);

$row = safeQuery($conn, "SELECT COALESCE(SUM(price_paid), 0) as total FROM user_rentals WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND deleted_at IS NULL");
if ($row) $last_month += floatval($row['total']);

$row = safeQuery($conn, "SELECT COALESCE(SUM(price_paid), 0) as total FROM ssh_rentals WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))");
if ($row) $last_month += floatval($row['total']);

$growth = $last_month > 0 ? (($this_month - $last_month) / $last_month) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-body: #000000;
            --card-bg: rgba(10, 10, 10, 0.8);
            --border-color: rgba(229, 9, 20, 0.2);
            --accent: #E50914;
        }
        body { background: var(--bg-body); color: #fff; font-family: 'Segoe UI', sans-serif; }
        .card-custom {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(229,9,20,0.15), rgba(0,0,0,0.5));
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
        }
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.8; }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { color: #aaa; font-size: 0.9rem; }
        .text-success { color: #4ade80 !important; }
        .text-danger { color: #f87171 !important; }
        .form-control, .form-select {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            color: #fff;
        }
        .form-control:focus { background: rgba(255,255,255,0.1); border-color: var(--accent); color: #fff; }
        .table { color: #fff; }
        .table thead th { background: rgba(15,23,42,0.8); color: #aaa; border-color: var(--border-color); }
        .table td { border-color: var(--border-color); }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0"><i class="fas fa-chart-bar text-danger me-2"></i>รายงานและสถิติ</h3>
                <small class="text-secondary">Reports & Analytics</small>
            </div>
            <form class="d-flex gap-2" method="GET">
                <input type="hidden" name="p" value="admin_reports">
                <input type="date" name="start" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                <input type="date" name="end" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            </form>
        </div>

        <!-- Summary Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-success"><i class="fas fa-baht-sign"></i></div>
                    <div class="stat-number text-success">฿<?php echo number_format($vpn_revenue + $ssh_revenue, 2); ?></div>
                    <div class="stat-label">รายได้รวม (ช่วงเวลา)</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-info"><i class="fas fa-wallet"></i></div>
                    <div class="stat-number text-info">฿<?php echo number_format($topup_revenue, 2); ?></div>
                    <div class="stat-label">ยอดเติมเงิน</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-warning"><i class="fas fa-users"></i></div>
                    <div class="stat-number text-warning"><?php echo number_format($new_users); ?></div>
                    <div class="stat-label">สมาชิกใหม่</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-purple" style="color: #a78bfa;"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-number" style="color: #a78bfa;"><?php echo number_format($new_vpn_rentals + $new_ssh_rentals); ?></div>
                    <div class="stat-label">รายการเช่าใหม่</div>
                </div>
            </div>
        </div>

        <!-- Monthly Comparison -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-calendar-alt me-2 text-danger"></i>เปรียบเทียบรายเดือน</h5>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="text-secondary small">เดือนที่แล้ว</div>
                            <div class="fs-4 fw-bold">฿<?php echo number_format($last_month, 0); ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-secondary small">เดือนนี้</div>
                            <div class="fs-4 fw-bold text-success">฿<?php echo number_format($this_month, 0); ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-secondary small">การเติบโต</div>
                            <div class="fs-4 fw-bold <?php echo $growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($growth >= 0 ? '+' : '') . number_format($growth, 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-pie-chart me-2 text-danger"></i>สัดส่วนรายได้</h5>
                    <canvas id="pieChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="card-custom mb-4">
            <h5 class="mb-3"><i class="fas fa-chart-line me-2 text-danger"></i>รายได้ 30 วันล่าสุด</h5>
            <canvas id="revenueChart" height="100"></canvas>
        </div>

        <!-- Top Packages -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-shield-alt me-2 text-info"></i>VPN ยอดนิยม (30 วัน)</h5>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>แพ็กเกจ</th><th class="text-center">จำนวน</th><th class="text-end">รายได้</th></tr></thead>
                        <tbody>
                            <?php foreach ($top_vpn_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['filename']); ?></td>
                                <td class="text-center"><?php echo $row['count']; ?></td>
                                <td class="text-end text-success">฿<?php echo number_format($row['revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($top_vpn_data)): ?>
                            <tr><td colspan="3" class="text-center text-secondary">ไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-terminal me-2 text-warning"></i>SSH ยอดนิยม (30 วัน)</h5>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>แพ็กเกจ</th><th class="text-center">จำนวน</th><th class="text-end">รายได้</th></tr></thead>
                        <tbody>
                            <?php foreach ($top_ssh_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="text-center"><?php echo $row['count']; ?></td>
                                <td class="text-end text-success">฿<?php echo number_format($row['revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($top_ssh_data)): ?>
                            <tr><td colspan="3" class="text-center text-secondary">ไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily Revenue Chart
        const dailyData = <?php echo json_encode($daily_revenue); ?>;
        const labels = Object.keys(dailyData).slice(-30);
        const vpnData = labels.map(d => dailyData[d]?.vpn || 0);
        const sshData = labels.map(d => dailyData[d]?.ssh || 0);

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: labels.map(d => d.substring(5)),
                datasets: [
                    {
                        label: 'VPN',
                        data: vpnData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'SSH',
                        data: sshData,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { labels: { color: '#fff' } } },
                scales: {
                    x: { ticks: { color: '#aaa' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { ticks: { color: '#aaa' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });

        // Pie Chart
        new Chart(document.getElementById('pieChart'), {
            type: 'doughnut',
            data: {
                labels: ['VPN', 'SSH'],
                datasets: [{
                    data: [<?php echo $vpn_revenue; ?>, <?php echo $ssh_revenue; ?>],
                    backgroundColor: ['#3b82f6', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { labels: { color: '#fff' } } }
            }
        });
    </script>
</body>
</html>
