<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'auth_check.php';
require_once 'payment/ksher_pay_sdk.php';
require_once 'payment/ksher_config.php';
require_once 'alert_modul/topup_telegram_notify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$allowed_origins = ['https://netfree.in.th'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if ($origin && !in_array($origin, $allowed_origins)) {
    if (strpos($origin, 'http') === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid origin']);
        exit;
    }
}

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ CSRF Check
if (!verifyCsrfToken()) {
    echo json_encode(['success' => false, 'message' => 'Security Token Invalid (CSRF)']);
    exit;
}

$package_id = isset($_POST['package_id']) ? strip_tags(trim($_POST['package_id'])) : '';
$custom_amount = isset($_POST['custom_amount']) ? floatval($_POST['custom_amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? strip_tags(trim($_POST['payment_method'])) : '';

if (!in_array($payment_method, ['promptpay', 'truemoney'], true)) {
    echo json_encode(['success' => false, 'message' => 'ช่องทางการชำระเงินไม่ถูกต้อง']);
    exit;
}

$amount = 0;
$bonus = 0;

if ($package_id === 'custom') {
    $amount = $custom_amount;
    if ($amount < 1 || $amount > 100000) {
        echo json_encode(['success' => false, 'message' => 'จำนวนเงินต้องอยู่ระหว่าง 10-100,000 บาท']);
        exit;
    }
    $bonus = 0;
} else {
    $stmt = $conn->prepare("SELECT * FROM topup_packages WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $package = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$package) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบแพ็กเกจที่เลือก']);
        exit;
    }

    $amount = $package['amount'];
    $bonus = $package['bonus'];
}

$stmt = $conn->prepare("SELECT id, username, email, credit FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) as recent_count FROM topup_transactions WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rate_check = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($rate_check['recent_count'] >= 5) {
    echo json_encode(['success' => false, 'message' => 'กรุณารอสักครู่ก่อนเติมเงินอีกครั้ง']);
    exit;
}

$transaction_ref = 'TXN' . time() . rand(1000, 9999);
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

$stmt = $conn->prepare("INSERT INTO topup_transactions (user_id, amount, bonus, method, status, transaction_ref, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, NOW())");
$stmt->bind_param("iddssss", $user_id, $amount, $bonus, $payment_method, $transaction_ref, $ip_address, $user_agent);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'บันทึกรายการล้มเหลว: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$transaction_id = $stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare("INSERT INTO notifications (user_id, transaction_id, type, title, message, created_at) VALUES (?, ?, 'info', ?, ?, NOW())");
$notif_title = 'กำลังดำเนินการเติมเงิน';
$notif_message = sprintf('คำขอเติมเงินจำนวน ฿%s กำลังรอการชำระเงิน รหัสอ้างอิง: %s', number_format($amount, 2), $transaction_ref);
$stmt->bind_param("iiss", $user_id, $transaction_id, $notif_title, $notif_message);
$stmt->execute();
$stmt->close();

try {
    $ksher = new KsherPay(KSHER_APPID, KSHER_PRIVATE_KEY);

    $order_data = [
        'mch_order_no' => $transaction_ref,
        'total_fee' => (int) ($amount * 100),
        'fee_type' => 'THB',
        'channel_list' => $payment_method,
        'mch_redirect_url' => KSHER_REDIRECT_SUCCESS . '&txn=' . $transaction_id . '&mch_order_no=' . $transaction_ref,
        'mch_redirect_url_fail' => KSHER_REDIRECT_FAIL,
        'mch_code' => KSHER_APPID,
        'product_name' => 'เติมเงิน V2BOX',
        'refer_url' => 'https://netfree.in.th',
        'device' => 'h5',
        'attach' => $transaction_id,
    ];

    $response_json = $ksher->gateway_pay($order_data);
    $response = json_decode($response_json, true);

    if ($response['code'] == 0 && !empty($response['data']['pay_content'])) {
        $payment_url = $response['data']['pay_content'];

        if (!filter_var($payment_url, FILTER_VALIDATE_URL) || strpos($payment_url, 'gateway.ksher.com') === false) {
            throw new Exception('Invalid payment URL');
        }

        $stmt = $conn->prepare("UPDATE topup_transactions SET admin_note = ? WHERE id = ?");
        $note_data = json_encode(['payment_url' => $payment_url, 'ksher_response' => $response], JSON_UNESCAPED_UNICODE);
        $stmt->bind_param("si", $note_data, $transaction_id);
        $stmt->execute();
        $stmt->close();

        sendTopupRequestNotify($user['username'], $amount, $transaction_id, $payment_method);

        echo json_encode([
            'success' => true,
            'payment_url' => $payment_url,
            'transaction_id' => $transaction_id
        ]);

    } else {
        throw new Exception($response['msg'] ?? 'ไม่สามารถสร้าง Payment URL ได้');
    }

} catch (Exception $e) {
    $stmt = $conn->prepare("UPDATE topup_transactions SET status = 'failed', admin_note = ? WHERE id = ?");
    $error_note = json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    $stmt->bind_param("si", $error_note, $transaction_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}

$conn->close();