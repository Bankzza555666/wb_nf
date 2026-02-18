<?php
// controller/topup_detail.php

require_once 'config.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_POST['transaction_id'] ?? 0;

if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

// ดึงข้อมูลรายละเอียด
$stmt = $conn->prepare("
    SELECT 
        id,
        amount,
        method,
        status,
        transaction_ref,
        admin_note,
        DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at,
        DATE_FORMAT(approved_at, '%d/%m/%Y %H:%i') as approved_at
    FROM topup_transactions
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();
$stmt->close();

if (!$transaction) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit;
}

// Format amount
$transaction['amount'] = number_format($transaction['amount'], 2);

echo json_encode([
    'success' => true,
    'transaction' => $transaction
]);

$conn->close();