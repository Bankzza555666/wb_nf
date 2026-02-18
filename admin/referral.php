<?php
/**
 * admin/referral.php
 * หน้าจัดการระบบแนะนำเพื่อน (Admin)
 * 
 * @version 2.0
 * @security Enhanced with comprehensive management
 */

require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
require_once __DIR__ . '/../controller/referral_helper.php';

checkAdminAuth();

$admin_id = $_SESSION['user_id'];

// สร้างตาราง
initReferralTables($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    
    switch ($action) {
        case 'save_settings':
            $settings_to_save = [
                'referral_enabled', 'commission_percent', 'min_topup_for_commission',
                'max_commission_per_transaction', 'referrer_add_days_limit',
                'max_referrals_per_user', 'same_ip_referral_limit', 'anti_fraud_enabled'
            ];
            
            foreach ($settings_to_save as $key) {
                if (isset($_POST[$key])) {
                    $value = $conn->real_escape_string($_POST[$key]);
                    $conn->query("DELETE FROM referral_settings WHERE setting_key = '$key'");
                    $conn->query("INSERT INTO referral_settings (setting_key, setting_value) VALUES ('$key', '$value')");
                }
            }
            
            logReferralAction($conn, $admin_id, 'admin_update_settings', null, null, json_encode($_POST));
            echo json_encode(['success' => true]);
            exit;
            
        case 'toggle_lock':
            $user_id = intval($_POST['user_id'] ?? 0);
            $lock = $_POST['lock'] === '1';
            
            if (adminToggleReferralLock($conn, $admin_id, $user_id, $lock)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update']);
            }
            exit;
            
        case 'change_referrer':
            $user_id = intval($_POST['user_id'] ?? 0);
            $new_referrer_id = $_POST['new_referrer_id'] === '' ? null : intval($_POST['new_referrer_id']);
            
            if (adminChangeReferrer($conn, $admin_id, $user_id, $new_referrer_id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update']);
            }
            exit;
            
        case 'get_logs':
            $page = intval($_POST['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $logs = [];
            $result = $conn->query("
                SELECT rl.*, u.username 
                FROM referral_logs rl 
                LEFT JOIN users u ON rl.user_id = u.id 
                ORDER BY rl.created_at DESC 
                LIMIT $offset, $limit
            ");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $logs[] = $row;
                }
            }
            
            $total = $conn->query("SELECT COUNT(*) as c FROM referral_logs")->fetch_assoc()['c'];
            
            echo json_encode(['success' => true, 'logs' => $logs, 'total' => $total, 'pages' => ceil($total / $limit)]);
            exit;
            
        case 'search_user':
            $q = $conn->real_escape_string($_POST['q'] ?? '');
            $users = [];
            
            if (strlen($q) >= 2) {
                $result = $conn->query("
                    SELECT id, username, referral_code 
                    FROM users 
                    WHERE username LIKE '%$q%' OR referral_code LIKE '%$q%' 
                    LIMIT 10
                ");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $users[] = $row;
                    }
                }
            }
            
            echo json_encode(['success' => true, 'users' => $users]);
            exit;
    }
}

// ดึง Settings
$settings = [];
$result = $conn->query("SELECT * FROM referral_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// สถิติ
$stats = [
    'total_referrers' => 0,
    'total_referred' => 0,
    'total_paid' => 0,
    'this_month_paid' => 0,
    'locked_users' => 0
];

try {
    $stats['total_referrers'] = $conn->query("SELECT COUNT(DISTINCT referred_by) as c FROM users WHERE referred_by IS NOT NULL")->fetch_assoc()['c'];
    $stats['total_referred'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE referred_by IS NOT NULL")->fetch_assoc()['c'];
    $stats['total_paid'] = floatval($conn->query("SELECT COALESCE(SUM(commission_amount), 0) as c FROM referral_earnings WHERE status = 'credited'")->fetch_assoc()['c']);
    $stats['this_month_paid'] = floatval($conn->query("SELECT COALESCE(SUM(commission_amount), 0) as c FROM referral_earnings WHERE status = 'credited' AND MONTH(created_at) = MONTH(NOW())")->fetch_assoc()['c']);
    
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'referral_locked'");
    if ($check && $check->num_rows > 0) {
        $stats['locked_users'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE referral_locked = 1")->fetch_assoc()['c'];
    }
} catch (Exception $e) {}

// Top Referrers
$top_referrers = [];
try {
    $result = $conn->query("
        SELECT u.id, u.username, u.referral_code, u.email,
               COALESCE(u.total_referral_earnings, 0) as total_earnings,
               COALESCE(u.referral_locked, 0) as is_locked,
               (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as friend_count,
               (SELECT COUNT(*) FROM referral_earnings WHERE referrer_id = u.id AND MONTH(created_at) = MONTH(NOW())) as this_month_count
        FROM users u 
        WHERE u.total_referral_earnings > 0 OR EXISTS (SELECT 1 FROM users WHERE referred_by = u.id)
        ORDER BY u.total_referral_earnings DESC 
        LIMIT 20
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $top_referrers[] = $row;
        }
    }
} catch (Exception $e) {}

// Recent Referrals
$recent_referrals = [];
try {
    $result = $conn->query("
        SELECT u.id, u.username, u.email, u.created_at, u.ip_address,
               r.username as referrer_name, r.referral_code
        FROM users u 
        LEFT JOIN users r ON u.referred_by = r.id
        WHERE u.referred_by IS NOT NULL
        ORDER BY u.created_at DESC 
        LIMIT 15
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_referrals[] = $row;
        }
    }
} catch (Exception $e) {}

// Recent Earnings
$recent_earnings = [];
try {
    $result = $conn->query("
        SELECT re.*, 
               u1.username as referrer_name,
               u2.username as friend_name
        FROM referral_earnings re
        LEFT JOIN users u1 ON re.referrer_id = u1.id
        LEFT JOIN users u2 ON re.referred_id = u2.id
        ORDER BY re.created_at DESC
        LIMIT 15
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_earnings[] = $row;
        }
    }
} catch (Exception $e) {}

// Suspicious Activity (Anti-fraud)
$suspicious = [];
try {
    // หา IP ที่มีการใช้ referral code เดียวกันหลายครั้ง
    $result = $conn->query("
        SELECT ip_address, referred_by, COUNT(*) as count,
               (SELECT username FROM users WHERE id = u.referred_by) as referrer_name
        FROM users u
        WHERE referred_by IS NOT NULL AND ip_address IS NOT NULL
        GROUP BY ip_address, referred_by
        HAVING count > 2
        ORDER BY count DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $suspicious[] = $row;
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการระบบแนะนำเพื่อน - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ใช้ค่าจาก theme_head.php */
        body { background: var(--bg-body, #000); color: var(--text-primary, #fff); font-family: 'Segoe UI', sans-serif; padding-bottom: 100px; }
        .glass-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, rgba(229,9,20,0.1), rgba(0,0,0,0.5)); border: 1px solid var(--border-color); border-radius: 15px; padding: 20px; text-align: center; height: 100%; }
        .stat-card .icon { font-size: 2rem; margin-bottom: 10px; }
        .stat-card .value { font-size: 1.5rem; font-weight: bold; }
        .stat-card .label { color: #888; font-size: 0.85rem; }
        .table-dark { background: transparent; }
        .table-dark th, .table-dark td { background: transparent; border-color: rgba(255,255,255,0.1); vertical-align: middle; font-size: 0.9rem; }
        .avatar { width: 32px; height: 32px; background: linear-gradient(135deg, #E50914, #99060d); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 0.75rem; }
        .form-control, .form-select { background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: #fff; }
        .form-control:focus, .form-select:focus { background: rgba(255,255,255,0.1); border-color: var(--accent); color: #fff; box-shadow: none; }
        .nav-tabs { border-bottom: 1px solid var(--border-color); }
        .nav-tabs .nav-link { color: #888; border: none; padding: 12px 20px; }
        .nav-tabs .nav-link.active { color: #fff; background: transparent; border-bottom: 2px solid var(--accent); }
        .nav-tabs .nav-link:hover { color: #fff; border-color: transparent; }
        .badge-earnings { background: #22c55e; }
        .badge-warning { background: #f59e0b; }
        .badge-danger { background: #ef4444; }
        .suspicious-card { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); }
    </style>
    <?php include __DIR__ . '/../include/theme_head.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0"><i class="fas fa-users text-danger me-2"></i>ระบบแนะนำเพื่อน</h3>
                <small class="text-secondary">Referral System Management v2.0</small>
            </div>
            <div>
                <span class="badge <?php echo ($settings['referral_enabled'] ?? '1') == '1' ? 'bg-success' : 'bg-danger'; ?>">
                    <?php echo ($settings['referral_enabled'] ?? '1') == '1' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <div class="stat-card">
                    <div class="icon text-primary"><i class="fas fa-user-plus"></i></div>
                    <div class="value"><?php echo number_format($stats['total_referrers']); ?></div>
                    <div class="label">ผู้แนะนำ</div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="stat-card">
                    <div class="icon text-info"><i class="fas fa-users"></i></div>
                    <div class="value"><?php echo number_format($stats['total_referred']); ?></div>
                    <div class="label">สมาชิกจากแนะนำ</div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="stat-card">
                    <div class="icon text-success"><i class="fas fa-coins"></i></div>
                    <div class="value">฿<?php echo number_format($stats['total_paid'], 0); ?></div>
                    <div class="label">จ่ายทั้งหมด</div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="stat-card">
                    <div class="icon text-warning"><i class="fas fa-calendar"></i></div>
                    <div class="value">฿<?php echo number_format($stats['this_month_paid'], 0); ?></div>
                    <div class="label">เดือนนี้</div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="stat-card">
                    <div class="icon text-danger"><i class="fas fa-ban"></i></div>
                    <div class="value"><?php echo number_format($stats['locked_users']); ?></div>
                    <div class="label">ถูกล็อค</div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="stat-card">
                    <div class="icon text-secondary"><i class="fas fa-percentage"></i></div>
                    <div class="value"><?php echo $settings['commission_percent'] ?? 10; ?>%</div>
                    <div class="label">ค่าคอม</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-overview">ภาพรวม</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-settings">ตั้งค่า</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-users">จัดการผู้ใช้</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-fraud">ตรวจจับ Fraud</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-logs">Audit Logs</a></li>
        </ul>

        <div class="tab-content">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="tab-overview">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="glass-card">
                            <h5 class="mb-3"><i class="fas fa-trophy text-warning me-2"></i>Top Referrers</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm mb-0">
                                    <thead><tr><th>#</th><th>ผู้ใช้</th><th>เพื่อน</th><th>รายได้</th><th>สถานะ</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($top_referrers as $i => $ref): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar"><?php echo strtoupper(substr($ref['username'], 0, 1)); ?></div>
                                                <div>
                                                    <div><?php echo htmlspecialchars($ref['username']); ?></div>
                                                    <small class="text-muted"><?php echo $ref['referral_code']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $ref['friend_count']; ?></td>
                                        <td><span class="badge badge-earnings">฿<?php echo number_format($ref['total_earnings'], 0); ?></span></td>
                                        <td>
                                            <?php if ($ref['is_locked']): ?>
                                                <span class="badge bg-danger">ล็อค</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">ปกติ</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="glass-card">
                            <h5 class="mb-3"><i class="fas fa-money-bill-wave text-success me-2"></i>Commission ล่าสุด</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm mb-0">
                                    <thead><tr><th>ผู้แนะนำ</th><th>เพื่อนเติม</th><th>ค่าคอม</th><th>เวลา</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($recent_earnings as $e): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($e['referrer_name'] ?? '-'); ?></td>
                                        <td>฿<?php echo number_format($e['topup_amount'], 0); ?></td>
                                        <td><span class="badge badge-earnings">+฿<?php echo number_format($e['commission_amount'], 2); ?></span></td>
                                        <td><small><?php echo date('d/m H:i', strtotime($e['created_at'])); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="glass-card">
                    <h5 class="mb-3"><i class="fas fa-user-plus text-info me-2"></i>สมาชิกใหม่จากการแนะนำ</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead><tr><th>สมาชิกใหม่</th><th>Email</th><th>แนะนำโดย</th><th>IP</th><th>วันที่</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_referrals as $ref): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ref['username']); ?></td>
                                <td><small><?php echo htmlspecialchars($ref['email']); ?></small></td>
                                <td><?php echo htmlspecialchars($ref['referrer_name'] ?? '-'); ?> <small class="text-muted">(<?php echo $ref['referral_code']; ?>)</small></td>
                                <td><small class="text-muted"><?php echo $ref['ip_address'] ?? '-'; ?></small></td>
                                <td><small><?php echo date('d/m/Y H:i', strtotime($ref['created_at'])); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-pane fade" id="tab-settings">
                <div class="glass-card">
                    <h5 class="mb-4"><i class="fas fa-cog text-warning me-2"></i>ตั้งค่าระบบ Referral</h5>
                    <form id="settingsForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">สถานะระบบ</label>
                                <select name="referral_enabled" class="form-select">
                                    <option value="1" <?php echo ($settings['referral_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>เปิดใช้งาน</option>
                                    <option value="0" <?php echo ($settings['referral_enabled'] ?? '1') == '0' ? 'selected' : ''; ?>>ปิดใช้งาน</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ค่าคอมมิชชั่น (%)</label>
                                <input type="number" name="commission_percent" class="form-control" value="<?php echo $settings['commission_percent'] ?? 10; ?>" min="0" max="50" step="0.5">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ยอดเติมขั้นต่ำ (บาท)</label>
                                <input type="number" name="min_topup_for_commission" class="form-control" value="<?php echo $settings['min_topup_for_commission'] ?? 20; ?>" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Commission สูงสุด/ครั้ง (0=ไม่จำกัด)</label>
                                <input type="number" name="max_commission_per_transaction" class="form-control" value="<?php echo $settings['max_commission_per_transaction'] ?? 500; ?>" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เพิ่ม Referrer ได้ภายใน (วัน)</label>
                                <input type="number" name="referrer_add_days_limit" class="form-control" value="<?php echo $settings['referrer_add_days_limit'] ?? 7; ?>" min="0" max="30">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">แนะนำได้สูงสุด (คน)</label>
                                <input type="number" name="max_referrals_per_user" class="form-control" value="<?php echo $settings['max_referrals_per_user'] ?? 100; ?>" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IP เดียวกันใช้ได้ (ครั้ง)</label>
                                <input type="number" name="same_ip_referral_limit" class="form-control" value="<?php echo $settings['same_ip_referral_limit'] ?? 3; ?>" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ระบบป้องกัน Fraud</label>
                                <select name="anti_fraud_enabled" class="form-select">
                                    <option value="1" <?php echo ($settings['anti_fraud_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>เปิด</option>
                                    <option value="0" <?php echo ($settings['anti_fraud_enabled'] ?? '1') == '0' ? 'selected' : ''; ?>>ปิด</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i>บันทึกการตั้งค่า</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Tab -->
            <div class="tab-pane fade" id="tab-users">
                <div class="glass-card">
                    <h5 class="mb-3"><i class="fas fa-users-cog me-2"></i>จัดการผู้ใช้</h5>
                    <div class="mb-3">
                        <input type="text" id="searchUser" class="form-control" placeholder="ค้นหา username หรือ referral code...">
                    </div>
                    <div id="userSearchResults"></div>
                    
                    <hr class="my-4 border-secondary">
                    
                    <h6>Top Referrers - จัดการ</h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead><tr><th>ผู้ใช้</th><th>Code</th><th>เพื่อน</th><th>รายได้</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
                            <tbody>
                            <?php foreach ($top_referrers as $ref): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ref['username']); ?></td>
                                <td><code><?php echo $ref['referral_code']; ?></code></td>
                                <td><?php echo $ref['friend_count']; ?></td>
                                <td>฿<?php echo number_format($ref['total_earnings'], 0); ?></td>
                                <td>
                                    <?php if ($ref['is_locked']): ?>
                                        <span class="badge bg-danger">ล็อค</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">ปกติ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-<?php echo $ref['is_locked'] ? 'success' : 'danger'; ?>" 
                                            onclick="toggleLock(<?php echo $ref['id']; ?>, <?php echo $ref['is_locked'] ? 0 : 1; ?>)">
                                        <i class="fas fa-<?php echo $ref['is_locked'] ? 'unlock' : 'lock'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Fraud Tab -->
            <div class="tab-pane fade" id="tab-fraud">
                <div class="glass-card suspicious-card">
                    <h5 class="mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i>กิจกรรมที่น่าสงสัย</h5>
                    <?php if (empty($suspicious)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shield-alt fa-3x mb-3 opacity-50"></i>
                            <p>ไม่พบกิจกรรมที่น่าสงสัย</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-sm">
                                <thead><tr><th>IP Address</th><th>Referrer</th><th>จำนวนสมัคร</th><th>ความเสี่ยง</th></tr></thead>
                                <tbody>
                                <?php foreach ($suspicious as $s): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($s['ip_address']); ?></code></td>
                                    <td><?php echo htmlspecialchars($s['referrer_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="badge bg-warning"><?php echo $s['count']; ?> accounts</span></td>
                                    <td>
                                        <?php if ($s['count'] >= 5): ?>
                                            <span class="badge bg-danger">สูง</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">ปานกลาง</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">* แสดง IP ที่มีการใช้ referral code เดียวกันมากกว่า 2 ครั้ง</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Logs Tab -->
            <div class="tab-pane fade" id="tab-logs">
                <div class="glass-card">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Audit Logs</h5>
                    <div id="logsContainer">
                        <p class="text-muted">กำลังโหลด...</p>
                    </div>
                    <div id="logsPagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Save Settings
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('ajax_action', 'save_settings');
        
        fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'บันทึกแล้ว', timer: 1500, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1500);
                }
            });
    });

    // Toggle Lock
    function toggleLock(userId, lock) {
        const action = lock ? 'ล็อค' : 'ปลดล็อค';
        Swal.fire({
            title: `${action}ผู้ใช้นี้?`,
            text: lock ? 'ผู้ใช้จะไม่สามารถรับ commission ได้' : 'ผู้ใช้จะสามารถรับ commission ได้ปกติ',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: action,
            cancelButtonText: 'ยกเลิก'
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_lock');
                formData.append('user_id', userId);
                formData.append('lock', lock);
                
                fetch('', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', timer: 1000, showConfirmButton: false });
                            setTimeout(() => location.reload(), 1000);
                        }
                    });
            }
        });
    }

    // Search User
    let searchTimeout;
    document.getElementById('searchUser').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value;
        
        if (q.length < 2) {
            document.getElementById('userSearchResults').innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('ajax_action', 'search_user');
            formData.append('q', q);
            
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.users.length === 0) {
                        document.getElementById('userSearchResults').innerHTML = '<p class="text-muted">ไม่พบผู้ใช้</p>';
                        return;
                    }
                    
                    let html = '<div class="list-group">';
                    data.users.forEach(u => {
                        html += `<div class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
                            <div><strong>${u.username}</strong> <small class="text-muted">(${u.referral_code})</small></div>
                            <button class="btn btn-sm btn-outline-danger" onclick="toggleLock(${u.id}, 1)"><i class="fas fa-lock"></i></button>
                        </div>`;
                    });
                    html += '</div>';
                    document.getElementById('userSearchResults').innerHTML = html;
                });
        }, 300);
    });

    // Load Logs
    function loadLogs(page = 1) {
        const formData = new FormData();
        formData.append('ajax_action', 'get_logs');
        formData.append('page', page);
        
        fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.logs.length === 0) {
                    document.getElementById('logsContainer').innerHTML = '<p class="text-muted">ไม่มี logs</p>';
                    return;
                }
                
                let html = '<div class="table-responsive"><table class="table table-dark table-sm"><thead><tr><th>เวลา</th><th>User</th><th>Action</th><th>IP</th><th>Notes</th></tr></thead><tbody>';
                data.logs.forEach(log => {
                    html += `<tr>
                        <td><small>${new Date(log.created_at).toLocaleString('th-TH')}</small></td>
                        <td>${log.username || '-'}</td>
                        <td><span class="badge bg-secondary">${log.action}</span></td>
                        <td><small>${log.ip_address || '-'}</small></td>
                        <td><small class="text-muted">${log.notes || '-'}</small></td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
                document.getElementById('logsContainer').innerHTML = html;
                
                // Pagination
                let pagination = '';
                for (let i = 1; i <= data.pages; i++) {
                    pagination += `<button class="btn btn-sm btn-${i === page ? 'danger' : 'outline-secondary'} me-1" onclick="loadLogs(${i})">${i}</button>`;
                }
                document.getElementById('logsPagination').innerHTML = pagination;
            });
    }

    // Load logs when tab is shown
    document.querySelector('a[href="#tab-logs"]').addEventListener('shown.bs.tab', () => loadLogs());
    </script>
</body>
</html>
