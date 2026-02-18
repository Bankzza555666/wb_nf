<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$transaction_id = isset($_GET['txn']) ? intval($_GET['txn']) : 0;

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT t.*, u.credit 
    FROM topup_transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ? AND t.user_id = ? 
    LIMIT 1
");
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    $stmt->close();
    exit;
}

$transaction = $result->fetch_assoc();
$stmt->close();

$response = [
    'success' => true,
    'transaction' => [
        'id' => $transaction['id'],
        'amount' => floatval($transaction['amount']),
        'bonus' => floatval($transaction['bonus']),
        'status' => $transaction['status'],
        'method' => $transaction['method'],
        'transaction_ref' => $transaction['transaction_ref'],
        'created_at' => $transaction['created_at'],
        'approved_at' => $transaction['approved_at']
    ],
    'user_credit' => floatval($transaction['credit'])
];

if ($transaction['status'] === 'approved') {
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        AND type = 'success' 
        AND title = 'เติมเงินสำเร็จ'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notif = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($notif) {
        $response['notification'] = [
            'title' => $notif['title'],
            'message' => $notif['message'],
            'type' => $notif['type']
        ];
    }
}

echo json_encode($response);
$conn->close();