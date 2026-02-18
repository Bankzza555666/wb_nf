<?php
/**
 * Server_price/api/toggle_auto_renew.php
 * เปิด/ปิด การต่ออายุอัตโนมัติ VPN
 */
// ไม่ให้มี output ก่อน JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/auth_check.php';

// ต้องล็อกอิน
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$rental_id = isset($_POST['rental_id']) ? (int) $_POST['rental_id'] : 0;
$status = isset($_POST['status']) ? (int) $_POST['status'] : 0;

if ($rental_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'รหัส VPN ไม่ถูกต้อง']);
    exit;
}

// ตรวจสอบว่ามีคอลัมน์ auto_renew หรือไม่ ถ้าไม่มีให้เพิ่ม
$check = @$conn->query("SHOW COLUMNS FROM user_rentals LIKE 'auto_renew'");
if ($check && $check->num_rows === 0) {
    @$conn->query("ALTER TABLE user_rentals ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
}

// ตรวจสอบว่า rental นี้เป็นของ user นี้ และดึงราคาต่อวัน
$stmt = $conn->prepare("
    SELECT ur.id, ur.auto_renew, p.price_per_day, p.min_days
    FROM user_rentals ur
    LEFT JOIN price_v2 p ON ur.price_id = p.id
    WHERE ur.id = ? AND ur.user_id = ? AND ur.deleted_at IS NULL
");
$stmt->bind_param("ii", $rental_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรายการ VPN นี้หรือไม่มีสิทธิ์แก้ไข']);
    exit;
}

// ถ้าเปิดต่ออายุอัตโนมัติ ตรวจสอบเครดิต
if ($status === 1) {
    $price_per_day = isset($row['price_per_day']) ? (float) $row['price_per_day'] : 0;
    $min_days = isset($row['min_days']) ? (int) $row['min_days'] : 1;
    $min_credit = $price_per_day * $min_days;

    $stmt_credit = $conn->prepare("SELECT credit FROM users WHERE id = ?");
    $stmt_credit->bind_param("i", $user_id);
    $stmt_credit->execute();
    $user_row = $stmt_credit->get_result()->fetch_assoc();
    $stmt_credit->close();

    $user_credit = isset($user_row['credit']) ? (float) $user_row['credit'] : 0;

    if ($user_credit < $min_credit) {
        echo json_encode([
            'success' => false,
            'message' => 'เครดิตไม่เพียงพอ',
            'detail' => sprintf(
                'การต่ออายุอัตโนมัติต้องมีเครดิตขั้นต่ำ ฿%.2f (ราคา ฿%.2f/วัน × ขั้นต่ำ %d วัน) คุณมีเครดิต ฿%.2f กรุณาเติมเครดิตก่อนเปิดใช้',
                $min_credit,
                $price_per_day,
                $min_days,
                $user_credit
            )
        ]);
        exit;
    }
}

// อัปเดต
$stmt = $conn->prepare("UPDATE user_rentals SET auto_renew = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("iii", $status, $rental_id, $user_id);

if ($stmt->execute()) {
    $msg = $status
        ? 'เปิดการต่ออายุอัตโนมัติสำเร็จ ระบบจะตัดเครดิตและต่ออายุให้ก่อนหมดอายุ'
        : 'ปิดการต่ออายุอัตโนมัติแล้ว';
    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดต: ' . $conn->error]);
}

$stmt->close();
