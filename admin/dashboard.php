<?php
// admin/dashboard.php
// V7.0 Analytics Dashboard

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
checkAdminAuth();

// --- 1. ข้อมูลการเงิน (Financial Stats) ---
// ยอดรวมทั้งหมด
$total_rev = $conn->query("SELECT SUM(amount) as t FROM topup_transactions WHERE status IN ('approved','success')")->fetch_assoc()['t'] ?? 0;

// ยอดเดือนนี้
$this_month = date('Y-m');
$month_rev = $conn->query("SELECT SUM(amount) as t FROM topup_transactions WHERE status IN ('approved','success') AND DATE_FORMAT(created_at, '%Y-%m') = '$this_month'")->fetch_assoc()['t'] ?? 0;

// ยอดวันนี้
$today = date('Y-m-d');
$today_rev = $conn->query("SELECT SUM(amount) as t FROM topup_transactions WHERE status IN ('approved','success') AND DATE(created_at) = '$today'")->fetch_assoc()['t'] ?? 0;

// จำนวน User
$total_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'] ?? 0;

// --- 2. กราฟรายได้รายเดือน (Monthly Income Chart) ---
// ดึงข้อมูลยอดขายแยกตามเดือนในปีปัจจุบัน
$current_year = date('Y');
$sql_chart = "SELECT MONTH(created_at) as m, SUM(amount) as total 
              FROM topup_transactions 
              WHERE status IN ('approved','success') AND YEAR(created_at) = '$current_year' 
              GROUP BY m";
$res_chart = $conn->query($sql_chart);

$monthly_data = array_fill(1, 12, 0); // สร้าง Array ว่าง 12 เดือน
while ($row = $res_chart->fetch_assoc()) {
    $monthly_data[$row['m']] = $row['total'];
}
$chart_values = array_values($monthly_data); // Data for JS
$chart_labels = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];

// --- 3. วิเคราะห์เซิร์ฟเวอร์ (Server Analytics) ---
// ดูว่า Server ไหนสร้างรายได้/มีการเช่ามากที่สุด
$server_stats = [];
$server_labels = [];
$server_income = [];
$sql_server = "SELECT s.server_name, 
               COUNT(ur.id) as total_rentals,
               SUM(ur.price_paid) as total_income,
               s.max_clients,
               (SELECT COUNT(*) FROM user_rentals WHERE server_id = s.server_id AND status = 'active') as active_now
               FROM servers s 
               LEFT JOIN user_rentals ur ON s.server_id = ur.server_id 
               GROUP BY s.server_id, s.server_name, s.max_clients 
               ORDER BY total_income DESC";
$res_server = $conn->query($sql_server);
if (!$res_server) {
    error_log("Admin Dashboard SQL Error: " . $conn->error . " | Query: " . $sql_server);
}
while ($res_server && $row = $res_server->fetch_assoc()) {
    $server_stats[] = $row;
    $server_labels[] = $row['server_name'];
    $server_income[] = $row['total_income'] ?? 0;
}

// --- 4. รายการล่าสุด ---
$recent_tx = $conn->query("SELECT t.*, u.username FROM topup_transactions t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Analytics</title>
    <link rel="icon" type="image/png" href="img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-body: #000000;
            --card-bg: rgba(10, 10, 10, 0.8);
            --border: rgba(229, 9, 20, 0.2);
            --text-main: #ffffff;
            --text-sub: #aaaaaa;
            --accent: #E50914;
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            font-family: 'Prompt', sans-serif;
            padding-bottom: 80px;
        }

        .card-stat {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            height: 100%;
            backdrop-filter: blur(10px);
            transition: transform 0.2s;
        }

        .card-stat:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }

        .icon-box {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .ib-blue {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .ib-green {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .ib-orange {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .ib-purple {
            background: rgba(139, 92, 246, 0.2);
            color: #8b5cf6;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        /* Table Fix */
        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            margin-bottom: 0;
            white-space: nowrap;
        }

        .table-custom td,
        .table-custom th {
            border-bottom: 1px solid var(--border);
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table-custom th {
            background: rgba(0, 0, 0, 0.2) !important;
            color: var(--text-sub);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .table-custom tr:last-child td {
            border-bottom: 0;
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h3 class="fw-bold text-white mb-0">Dashboard Analytics</h3>
                <span class="text-secondary small">ภาพรวมและสถิติประจำปี <?php echo $current_year; ?></span>
            </div>
            <div class="text-end">
                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary rounded-pill">
                    <i class="fas fa-clock me-1"></i> <?php echo date('d M Y'); ?>
                </span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card-stat">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-secondary small mb-1">ยอดเติมวันนี้</div>
                            <h4 class="fw-bold text-white mb-0">฿<?php echo number_format($today_rev); ?></h4>
                        </div>
                        <div class="icon-box ib-green"><i class="fas fa-calendar-day"></i></div>
                    </div>
                    <div class="small text-success mt-2"><i class="fas fa-arrow-up"></i> อัปเดตล่าสุด</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card-stat">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-secondary small mb-1">ยอดเดือนนี้</div>
                            <h4 class="fw-bold text-white mb-0">฿<?php echo number_format($month_rev); ?></h4>
                        </div>
                        <div class="icon-box ib-blue"><i class="fas fa-calendar-alt"></i></div>
                    </div>
                    <div class="small text-info mt-2">สะสมทั้งเดือน</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card-stat">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-secondary small mb-1">รายได้รวมทั้งหมด</div>
                            <h4 class="fw-bold text-white mb-0">฿<?php echo number_format($total_rev); ?></h4>
                        </div>
                        <div class="icon-box ib-orange"><i class="fas fa-wallet"></i></div>
                    </div>
                    <div class="small text-secondary mt-2">Lifetime</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card-stat">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-secondary small mb-1">สมาชิกทั้งหมด</div>
                            <h4 class="fw-bold text-white mb-0"><?php echo number_format($total_users); ?></h4>
                        </div>
                        <div class="icon-box ib-purple"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="small text-secondary mt-2">Active Users</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card-stat">
                    <h6 class="fw-bold text-white mb-4"><i
                            class="fas fa-chart-line me-2 text-primary"></i>แนวโน้มรายได้รายเดือน
                        (<?php echo $current_year; ?>)</h6>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-stat">
                    <h6 class="fw-bold text-white mb-4"><i class="fas fa-server me-2 text-warning"></i>ส่วนแบ่งรายได้ตาม
                        Server</h6>
                    <div class="chart-container" style="height: 200px; display: flex; justify-content: center;">
                        <canvas id="serverChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <?php if (!empty($server_stats)): ?>
                            <div
                                class="d-flex justify-content-between small text-secondary border-bottom border-secondary border-opacity-25 pb-1 mb-2">
                                <span>Top Server</span>
                                <span class="text-white"><?php echo $server_stats[0]['server_name']; ?></span>
                            </div>
                            <div class="d-flex justify-content-between small text-secondary">
                                <span>Max Usage</span>
                                <span class="text-success"><?php echo $server_stats[0]['active_now']; ?> Active</span>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-secondary small">ไม่มีข้อมูล</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card-stat p-0 overflow-hidden">
                    <div class="p-3 border-bottom border-secondary border-opacity-25">
                        <h6 class="fw-bold text-white mb-0"><i
                                class="fas fa-hdd me-2 text-info"></i>ประสิทธิภาพเซิร์ฟเวอร์</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th>Server Name</th>
                                    <th>รายได้รวม</th>
                                    <th>การใช้งาน (Active/Max)</th>
                                    <th>Load</th>
                                    <th>ยอดเช่าทั้งหมด (ครั้ง)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($server_stats as $sv):
                                    $percent = ($sv['max_clients'] > 0) ? ($sv['active_now'] / $sv['max_clients']) * 100 : 0;
                                    $color = $percent > 80 ? 'danger' : ($percent > 50 ? 'warning' : 'success');
                                    ?>
                                    <tr>
                                        <td class="fw-bold text-white"><?php echo $sv['server_name']; ?></td>
                                        <td class="text-warning">฿<?php echo number_format($sv['total_income'], 2); ?></td>
                                        <td><?php echo $sv['active_now']; ?> / <?php echo $sv['max_clients']; ?></td>
                                        <td style="width: 200px;">
                                            <div class="progress" style="height: 6px; background: rgba(255,255,255,0.1);">
                                                <div class="progress-bar bg-<?php echo $color; ?>"
                                                    style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo number_format($sv['total_rentals']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($server_stats)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-4">ไม่มีข้อมูล</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold text-white mb-3"><i class="fas fa-history me-2"></i>รายการเติมเงินล่าสุด</h6>
        <div class="card-stat p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recent_tx->fetch_assoc()): ?>
                            <tr>
                                <td class="text-secondary">#<?php echo $row['id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="text-warning fw-bold">+฿<?php echo number_format($row['amount'], 2); ?></td>
                                <td><span
                                        class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary rounded-pill"><?php echo $row['method']; ?></span>
                                </td>
                                <td>
                                    <?php
                                    $s = $row['status'];
                                    $c = ($s == 'success' || $s == 'approved') ? 'success' : (($s == 'pending') ? 'warning' : 'danger');
                                    echo "<span class='badge bg-$c bg-opacity-25 text-$c border border-$c rounded-pill'>$s</span>";
                                    ?>
                                </td>
                                <td class="text-secondary small">
                                    <?php echo date('d/m H:i', strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Chart 1: Monthly Income ---
        const ctxMonth = document.getElementById('monthlyChart').getContext('2d');
        const gradMonth = ctxMonth.createLinearGradient(0, 0, 0, 300);
        gradMonth.addColorStop(0, 'rgba(99, 102, 241, 0.5)');
        gradMonth.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        new Chart(ctxMonth, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'ยอดเติมเงิน (บาท)',
                    data: <?php echo json_encode($chart_values); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: gradMonth,
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6366f1',
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });

        // --- Chart 2: Server Income Share ---
        const ctxServer = document.getElementById('serverChart').getContext('2d');
        new Chart(ctxServer, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($server_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($server_income); ?>,
                    backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#cbd5e1', boxWidth: 10 } }
                },
                cutout: '70%'
            }
        });
    </script>
</body>

</html>