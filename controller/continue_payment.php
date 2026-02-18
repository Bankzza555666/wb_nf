<?php
// controller/continue_payment.php

// 1. เริ่ม Buffer เพื่อป้องกัน Error text หลุดไปปน JSON
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดการแสดง Error ทางหน้าจอ (ให้ส่งผ่าน JSON แทน)
header('Content-Type: application/json');

session_start();

try {
    // 2. เชื่อมต่อฐานข้อมูล (ใช้ไฟล์เดียว)
    require_once __DIR__ . '/config.php';

    // 3. ตรวจสอบสิทธิ์
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("กรุณาเข้าสู่ระบบก่อนทำรายการ");
    }

    $user_id = $_SESSION['user_id'];
    $ref = $_POST['transaction_ref'] ?? '';

    if (empty($ref)) {
        throw new Exception("ไม่พบรหัสอ้างอิงธุรกรรม");
    }

    // 4. ดึงข้อมูล
    $stmt = $conn->prepare("SELECT admin_note, status FROM topup_transactions WHERE transaction_ref = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("si", $ref, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bill = $result->fetch_assoc();

    if ($bill) {
        // ตรวจสอบสถานะ
        if ($bill['status'] !== 'pending') {
            throw new Exception("รายการนี้ดำเนินการเสร็จสิ้นหรือถูกยกเลิกไปแล้ว");
        }

        // แกะ JSON
        $noteData = json_decode($bill['admin_note'], true);

        if (isset($noteData['payment_url'])) {
            // ✅ สำเร็จ: เคลียร์ Buffer ก่อนส่ง JSON
            ob_clean();
            echo json_encode(['success' => true, 'payment_url' => $noteData['payment_url']]);
        } else {
            throw new Exception("ไม่พบลิงก์ชำระเงินในระบบ (URL Not Found)");
        }
    } else {
        throw new Exception("ไม่พบรายการสั่งซื้อนี้");
    }

} catch (Exception $e) {
    // ❌ เกิดข้อผิดพลาด: เคลียร์ Buffer แล้วส่ง Error Message
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// จบการทำงาน
$conn->close();
exit;
?>