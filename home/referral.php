<?php
/**
 * home/referral.php
 * หน้าระบบแนะนำเพื่อน (Referral) - User Side
 * 
 * @version 2.0
 * @security Enhanced with anti-abuse measures
 */

// Debug mode
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../controller/config.php';

// เช็คว่า referral_helper.php มีหรือไม่
$helper_path = __DIR__ . '/../controller/referral_helper.php';
if (file_exists($helper_path)) {
    require_once $helper_path;
} else {
    // ถ้าไม่มี ให้สร้าง dummy functions
    function initReferralTables($conn) { return true; }
    function getReferralSetting($conn, $key, $default = null) { return $default; }
    function generateReferralCode($conn) { return strtoupper(substr(md5(time()), 0, 6)); }
    function canAddReferrer($conn, $user_id) { return ['can' => false, 'days_left' => 0]; }
    function logReferralAction($conn, $a, $b, $c = null, $d = null, $e = null) { return true; }
    function validateReferralCode($conn, $code, $user_id) { return ['valid' => false, 'error' => 'System not ready']; }
    function addReferrer($conn, $user_id, $referrer_id) { return ['success' => false]; }
    function getUserReferralStats($conn, $user_id) { return ['referral_code' => null, 'total_friends' => 0, 'total_earnings' => 0, 'this_month_earnings' => 0]; }
}

// ต้อง login
if (!isset($_SESSION['user_id'])) {
    header('Location: ?r=landing');
    exit;
}

$user_id = intval($_SESSION['user_id']);

// สร้างตารางถ้ายังไม่มี
initReferralTables($conn);

// ดึงข้อมูล User (มี fallback ถ้า columns ยังไม่มี)
$user = null;

// เช็คว่า columns มีอยู่หรือยัง
$has_referral_code = false;
$has_created_at = false;

$check1 = @$conn->query("SHOW COLUMNS FROM users LIKE 'referral_code'");
if ($check1 && $check1->num_rows > 0) $has_referral_code = true;

$check2 = @$conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if ($check2 && $check2->num_rows > 0) $has_created_at = true;

// สร้าง query ตาม columns ที่มี
$select_fields = "id, username, email, credit";
if ($has_referral_code) $select_fields .= ", referral_code, total_referral_earnings, referred_by";
if ($has_created_at) $select_fields .= ", created_at";

$stmt = $conn->prepare("SELECT $select_fields FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// เพิ่ม default values สำหรับ columns ที่ไม่มี
if ($user) {
    if (!$has_referral_code) {
        $user['referral_code'] = null;
        $user['total_referral_earnings'] = 0;
        $user['referred_by'] = null;
    }
    if (!$has_created_at) {
        $user['created_at'] = date('Y-m-d H:i:s');
    }
}

$has_referral_columns = $has_referral_code;

if (!$user) {
    header('Location: ?r=landing');
    exit;
}

// Generate referral code ถ้ายังไม่มี
if (empty($user['referral_code'])) {
    // เช็คว่า column มีหรือยัง
    if ($has_referral_columns) {
        $new_code = generateReferralCode($conn);
        $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ? AND (referral_code IS NULL OR referral_code = '')");
        $stmt->bind_param("si", $new_code, $user_id);
        $stmt->execute();
        
        // เช็คว่า update สำเร็จไหม (อาจมีคนอื่น update ไปแล้ว)
        if ($stmt->affected_rows > 0) {
            $user['referral_code'] = $new_code;
            logReferralAction($conn, $user_id, 'generate_code', null, null, $new_code);
        } else {
            // ดึงค่าปัจจุบันจาก DB
            $check = $conn->query("SELECT referral_code FROM users WHERE id = $user_id");
            if ($check && $row = $check->fetch_assoc()) {
                $user['referral_code'] = $row['referral_code'];
            }
        }
        $stmt->close();
    } else {
        // ถ้า column ยังไม่มี แสดง code ชั่วคราว
        $user['referral_code'] = 'SETUP';
    }
}

// ดึง Settings
$commission_percent = floatval(getReferralSetting($conn, 'commission_percent', 10));
$min_topup = floatval(getReferralSetting($conn, 'min_topup_for_commission', 20));
$max_commission = floatval(getReferralSetting($conn, 'max_commission_per_transaction', 500));
$days_limit = intval(getReferralSetting($conn, 'referrer_add_days_limit', 7));

// ตรวจสอบสถานะการเพิ่มผู้แนะนำ
$can_add_result = canAddReferrer($conn, $user_id);
$can_add_referrer = $can_add_result['can'];
$days_left = $can_add_result['days_left'] ?? 0;
$has_referrer = !empty($user['referred_by']);

// ดึงชื่อผู้แนะนำ
$referrer_name = null;
if ($has_referrer) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['referred_by']);
    $stmt->execute();
    $ref = $stmt->get_result()->fetch_assoc();
    $referrer_name = $ref['username'] ?? 'Unknown';
    $stmt->close();
}

// Handle POST - เพิ่มรหัสแนะนำภายหลัง
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $message = 'Invalid request';
        $message_type = 'error';
    } elseif ($_POST['action'] === 'add_referrer') {
        $ref_code = strtoupper(trim($_POST['referrer_code'] ?? ''));
        
        // Rate limiting - max 5 attempts per hour
        $rate_key = 'referral_attempts_' . $user_id;
        $_SESSION[$rate_key] = ($_SESSION[$rate_key] ?? 0) + 1;
        
        if ($_SESSION[$rate_key] > 5) {
            $message = 'คุณลองหลายครั้งเกินไป กรุณารอสักครู่';
            $message_type = 'error';
        } elseif (!$can_add_referrer) {
            $message = $can_add_result['reason'] ?? 'ไม่สามารถเพิ่มผู้แนะนำได้';
            $message_type = 'error';
        } else {
            // Validate
            $validate = validateReferralCode($conn, $ref_code, $user_id);
            
            if (!$validate['valid']) {
                $message = $validate['error'];
                $message_type = 'error';
            } else {
                // เพิ่ม referrer
                $add_result = addReferrer($conn, $user_id, $validate['referrer']['id']);
                
                if ($add_result['success']) {
                    $message = 'เพิ่มผู้แนะนำสำเร็จ! ' . htmlspecialchars($validate['referrer']['username']) . ' จะได้รับเครดิตเมื่อคุณเติมเงิน';
                    $message_type = 'success';
                    $has_referrer = true;
                    $can_add_referrer = false;
                    $referrer_name = $validate['referrer']['username'];
                    $_SESSION[$rate_key] = 0; // Reset rate limit on success
                } else {
                    $message = $add_result['error'];
                    $message_type = 'error';
                }
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ดึงสถิติ
$stats = getUserReferralStats($conn, $user_id);

// นับจำนวนเพื่อนที่แนะนำ
$friends_count = $stats['total_friends'];

// ดึงรายชื่อเพื่อนที่แนะนำมา
$friends = [];
try {
    $stmt = $conn->prepare("SELECT id, username, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($f = $result->fetch_assoc()) {
        $friends[] = $f;
    }
    $stmt->close();
} catch (Exception $e) {}

// ดึงประวัติรายได้
$earnings = [];
try {
    $stmt = $conn->prepare("
        SELECT re.*, u.username as friend_name 
        FROM referral_earnings re 
        LEFT JOIN users u ON re.referred_id = u.id 
        WHERE re.referrer_id = ? 
        ORDER BY re.created_at DESC 
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($e = $result->fetch_assoc()) {
        $earnings[] = $e;
    }
    $stmt->close();
} catch (Exception $e) {}

// สร้าง referral link
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$referral_link = $base_url . "/index.php?r=landing&ref=" . urlencode($user['referral_code']);

include 'header.php';
include 'navbar.php';
?>

<style>
    .referral-hero {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.2), rgba(0, 0, 0, 0.8));
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        text-align: center;
    }
    .referral-hero h2 { color: #fff; margin-bottom: 10px; }
    .referral-hero p { color: #aaa; margin-bottom: 20px; }
    .commission-badge {
        display: inline-block;
        background: linear-gradient(135deg, #E50914, #99060d);
        color: #fff;
        font-size: 2rem;
        font-weight: bold;
        padding: 15px 30px;
        border-radius: 15px;
        margin-bottom: 15px;
    }
    .referral-code-box {
        background: rgba(0, 0, 0, 0.5);
        border: 2px dashed rgba(229, 9, 20, 0.5);
        border-radius: 15px;
        padding: 20px;
        margin: 20px 0;
    }
    .referral-code {
        font-size: 2.5rem;
        font-weight: bold;
        letter-spacing: 5px;
        color: #E50914;
        font-family: monospace;
    }
    .referral-link {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 12px 15px;
        color: #aaa;
        font-size: 0.85rem;
        word-break: break-all;
    }
    .stat-card {
        background: rgba(25, 25, 25, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        height: 100%;
    }
    .stat-card .icon { font-size: 2rem; margin-bottom: 10px; }
    .stat-card .value { font-size: 1.8rem; font-weight: bold; color: #fff; }
    .stat-card .label { color: #888; font-size: 0.9rem; }
    .glass-card {
        background: rgba(25, 25, 25, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .glass-card h5 {
        color: #fff;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .friend-item, .earning-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
        margin-bottom: 8px;
    }
    .friend-item .avatar {
        width: 40px; height: 40px;
        background: linear-gradient(135deg, #E50914, #99060d);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: bold; margin-right: 12px;
    }
    .earning-amount { color: #22c55e; font-weight: bold; }
    .share-buttons { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .share-btn {
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .share-btn:hover { transform: translateY(-2px); }
    .share-btn.copy { background: #E50914; color: #fff; }
    .share-btn.line { background: #06c755; color: #fff; }
    .share-btn.facebook { background: #1877f2; color: #fff; }
    .how-it-works {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .step-card { text-align: center; padding: 20px; }
    .step-number {
        width: 40px; height: 40px;
        background: linear-gradient(135deg, #E50914, #99060d);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: bold; margin: 0 auto 15px;
    }
    .step-card h6 { color: #fff; margin-bottom: 8px; }
    .step-card p { color: #888; font-size: 0.85rem; margin: 0; }
    .info-box {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 10px;
        padding: 15px;
        margin-top: 15px;
    }
    .info-box small { color: #93c5fd; }
</style>

<div class="container py-4">
    <!-- Hero Section -->
    <div class="referral-hero">
        <h2><i class="fas fa-gift me-2"></i>ชวนเพื่อน รับเครดิต!</h2>
        <p>รับเครดิตฟรี <?php echo $commission_percent; ?>% ทุกครั้งที่เพื่อนเติมเงิน</p>
        
        <div class="commission-badge">
            <i class="fas fa-coins me-2"></i><?php echo $commission_percent; ?>%
        </div>
        
        <div class="referral-code-box">
            <div class="text-muted small mb-2">รหัสแนะนำของคุณ</div>
            <div class="referral-code" id="referralCode"><?php echo htmlspecialchars($user['referral_code']); ?></div>
        </div>

        <div class="referral-link mb-3" id="referralLink"><?php echo htmlspecialchars($referral_link); ?></div>

        <div class="share-buttons">
            <button class="share-btn copy" onclick="copyLink()">
                <i class="fas fa-copy"></i> คัดลอกลิงก์
            </button>
            <a href="https://line.me/R/msg/text/?<?php echo urlencode("มาใช้ NF~SHOP ด้วยกัน! ใช้รหัสแนะนำ " . $user['referral_code'] . " รับส่วนลดพิเศษ " . $referral_link); ?>" target="_blank" class="share-btn line">
                <i class="fab fa-line"></i> แชร์ Line
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="share-btn facebook">
                <i class="fab fa-facebook-f"></i> แชร์ Facebook
            </a>
        </div>

        <div class="info-box">
            <small>
                <i class="fas fa-info-circle me-1"></i>
                เพื่อนเติมขั้นต่ำ ฿<?php echo number_format($min_topup, 0); ?> ถึงจะได้รับ commission
                <?php if ($max_commission > 0): ?>
                | สูงสุด ฿<?php echo number_format($max_commission, 0); ?>/ครั้ง
                <?php endif; ?>
            </small>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon text-danger"><i class="fas fa-users"></i></div>
                <div class="value"><?php echo number_format($friends_count); ?></div>
                <div class="label">เพื่อนที่แนะนำ</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon text-success"><i class="fas fa-wallet"></i></div>
                <div class="value">฿<?php echo number_format($stats['total_earnings'], 2); ?></div>
                <div class="label">รายได้รวม</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon text-warning"><i class="fas fa-calendar-alt"></i></div>
                <div class="value">฿<?php echo number_format($stats['this_month_earnings'], 2); ?></div>
                <div class="label">เดือนนี้</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon text-info"><i class="fas fa-percent"></i></div>
                <div class="value"><?php echo $commission_percent; ?>%</div>
                <div class="label">ค่าคอมมิชชั่น</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Friends List -->
        <div class="col-md-6 mb-4">
            <div class="glass-card h-100">
                <h5><i class="fas fa-user-friends text-primary me-2"></i>เพื่อนที่แนะนำมา</h5>
                <?php if (empty($friends)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-3x mb-3 opacity-50"></i>
                        <p>ยังไม่มีเพื่อนที่สมัครผ่านรหัสของคุณ</p>
                        <small>แชร์รหัสแนะนำให้เพื่อนเลย!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($friends as $friend): ?>
                        <div class="friend-item">
                            <div class="d-flex align-items-center">
                                <div class="avatar"><?php echo strtoupper(substr($friend['username'], 0, 1)); ?></div>
                                <div>
                                    <div class="text-white"><?php echo htmlspecialchars($friend['username']); ?></div>
                                    <small class="text-muted">สมัครเมื่อ <?php echo date('d/m/Y', strtotime($friend['created_at'])); ?></small>
                                </div>
                            </div>
                            <span class="badge bg-success">Active</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Earnings History -->
        <div class="col-md-6 mb-4">
            <div class="glass-card h-100">
                <h5><i class="fas fa-history text-success me-2"></i>ประวัติรายได้</h5>
                <?php if (empty($earnings)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-coins fa-3x mb-3 opacity-50"></i>
                        <p>ยังไม่มีรายได้จากการแนะนำ</p>
                        <small>เมื่อเพื่อนเติมเงิน คุณจะได้รับเครดิตทันที!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($earnings as $e): ?>
                        <div class="earning-item">
                            <div>
                                <div class="text-white"><?php echo htmlspecialchars($e['friend_name'] ?? 'Unknown'); ?> เติมเงิน</div>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($e['created_at'])); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="earning-amount">+฿<?php echo number_format($e['commission_amount'], 2); ?></div>
                                <small class="text-muted"><?php echo $e['commission_percent']; ?>% ของ ฿<?php echo number_format($e['topup_amount'], 2); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- เพิ่มรหัสแนะนำภายหลัง -->
    <?php if ($can_add_referrer && !$has_referrer): ?>
    <div class="glass-card" style="border: 2px solid rgba(34, 197, 94, 0.3); background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(0,0,0,0.5));">
        <h5><i class="fas fa-user-plus text-success me-2"></i>มีรหัสแนะนำจากเพื่อน?</h5>
        <p class="text-muted small mb-3">คุณยังไม่มีผู้แนะนำ สามารถเพิ่มได้ภายใน <strong><?php echo $days_left; ?> วัน</strong></p>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> py-2">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="d-flex gap-2">
            <input type="hidden" name="action" value="add_referrer">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="referrer_code" class="form-control" placeholder="กรอกรหัสแนะนำ 6 ตัว" 
                   maxlength="6" pattern="[A-Za-z0-9]{6}" required
                   style="text-transform: uppercase; background: rgba(255,255,255,0.1); border-color: rgba(34,197,94,0.3); color: #fff;">
            <button type="submit" class="btn btn-success px-4">
                <i class="fas fa-check me-1"></i>ยืนยัน
            </button>
        </form>
    </div>
    <?php elseif ($has_referrer): ?>
    <div class="glass-card" style="border: 1px solid rgba(34, 197, 94, 0.3);">
        <div class="d-flex align-items-center">
            <div class="me-3" style="width:45px;height:45px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-check text-white"></i>
            </div>
            <div>
                <div class="text-success fw-bold">คุณถูกแนะนำโดย: <?php echo htmlspecialchars($referrer_name); ?></div>
                <small class="text-muted">ผู้แนะนำของคุณจะได้รับเครดิตทุกครั้งที่คุณเติมเงิน</small>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- How it works -->
    <div class="glass-card">
        <h5><i class="fas fa-question-circle text-warning me-2"></i>วิธีการทำงาน</h5>
        <div class="how-it-works">
            <div class="step-card">
                <div class="step-number">1</div>
                <h6>แชร์รหัสแนะนำ</h6>
                <p>ส่งรหัสหรือลิงก์ให้เพื่อน</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h6>เพื่อนสมัครสมาชิก</h6>
                <p>เพื่อนกรอกรหัสแนะนำตอนสมัคร</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h6>เพื่อนเติมเงิน</h6>
                <p>ขั้นต่ำ ฿<?php echo number_format($min_topup, 0); ?> ต่อครั้ง</p>
            </div>
            <div class="step-card">
                <div class="step-number">4</div>
                <h6>รับเครดิตทันที!</h6>
                <p>คุณได้รับ <?php echo $commission_percent; ?>% อัตโนมัติ</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function copyLink() {
    const link = document.getElementById('referralLink').innerText;
    navigator.clipboard.writeText(link).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'คัดลอกแล้ว!',
            text: 'ลิงก์แนะนำถูกคัดลอกแล้ว',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
    });
}
</script>

<?php include 'footer.php'; ?>
