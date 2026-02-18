<?php
require_once '../config.php';
require_once 'ksher_pay_sdk.php';
require_once 'ksher_config.php';
require_once '../alert_modul/topup_telegram_notify.php';

$allowed_ips = ['127.0.0.1', '::1', '52.220.174.162', '18.141.213.76'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (KSHER_APPID !== 'mch40034') {
    if (!in_array($client_ip, $allowed_ips)) {
        http_response_code(403);
        echo json_encode(['code' => 403, 'msg' => 'Forbidden']);
        exit;
    }
}

$log_file = __DIR__ . '/../../notify/ksher_webhook_' . date('Y-m-d') . '.log';
@mkdir(__DIR__ . '/../../notify', 0755, true);

$raw_data = file_get_contents('php://input');
file_put_contents($log_file, date('[Y-m-d H:i:s] ') . 'IP: ' . $client_ip . ' | ' . $raw_data . PHP_EOL, FILE_APPEND);

$callback_data = $_POST;

if (empty($callback_data)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => 'No data received']);
    exit;
}

file_put_contents($log_file, date('[Y-m-d H:i:s] ') . 'Parsed: ' . json_encode($callback_data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

try {
    $ksher = new KsherPay(KSHER_APPID, KSHER_PRIVATE_KEY);
    $received_sign = $callback_data['sign'] ?? '';
    unset($callback_data['sign']);
    $verify_result = $ksher->verify_ksher_sign($callback_data, $received_sign);
    
    if ($verify_result !== 1) {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '❌ INVALID SIGNATURE!' . PHP_EOL, FILE_APPEND);
        throw new Exception('Invalid signature');
    }
    
    $data = $callback_data['data'] ?? $callback_data;
    $mch_order_no = isset($data['mch_order_no']) ? strip_tags($data['mch_order_no']) : '';
    $channel_order_no = isset($data['channel_order_no']) ? strip_tags($data['channel_order_no']) : '';
    $order_status = isset($data['order_status']) ? strip_tags($data['order_status']) : '';
    $total_fee = isset($data['total_fee']) ? floatval($data['total_fee']) / 100 : 0;
    $pay_time = isset($data['pay_time']) ? strip_tags($data['pay_time']) : '';
    
    if (empty($mch_order_no)) {
        throw new Exception('Missing mch_order_no');
    }
    
    $stmt = $conn->prepare("SELECT t.*, u.username, u.credit FROM topup_transactions t JOIN users u ON t.user_id = u.id WHERE t.transaction_ref = ? LIMIT 1");
    $stmt->bind_param("s", $mch_order_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '❌ Transaction not found: ' . $mch_order_no . PHP_EOL, FILE_APPEND);
        throw new Exception('Transaction not found');
    }
    
    $transaction = $result->fetch_assoc();
    $stmt->close();
    
    if ($transaction['status'] === 'approved') {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '⚠️ Already approved: ' . $mch_order_no . PHP_EOL, FILE_APPEND);
        echo json_encode(['code' => 0, 'msg' => 'Already processed']);
        exit;
    }
    
    if (abs($total_fee - $transaction['amount']) > 0.01) {
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '❌ Amount mismatch!' . PHP_EOL, FILE_APPEND);
        throw new Exception('Amount mismatch');
    }
    
    if ($order_status === 'SUCCESS') {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("UPDATE topup_transactions SET status = 'approved', admin_note = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $note_data = json_encode(['channel_order_no' => $channel_order_no, 'pay_time' => $pay_time, 'verified' => true, 'callback_data' => $callback_data], JSON_UNESCAPED_UNICODE);
            $stmt->bind_param("si", $note_data, $transaction['id']);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Failed to update transaction');
            }
            $stmt->close();
            
            $total_credit = $transaction['amount'] + $transaction['bonus'];
            $stmt = $conn->prepare("UPDATE users SET credit = credit + ? WHERE id = ?");
            $stmt->bind_param("di", $total_credit, $transaction['user_id']);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO topup_logs (transaction_id, action, old_status, new_status, note, created_at) VALUES (?, 'webhook_approved', 'pending', 'approved', ?, NOW())");
            $log_note = 'Auto approved by Ksher webhook';
            $stmt->bind_param("is", $transaction['id'], $log_note);
            $stmt->execute();
            $stmt->close();
            
            $new_balance = $transaction['credit'] + $total_credit;
            
            $stmt = $conn->prepare("UPDATE notifications SET type = 'success', title = ?, message = ?, updated_at = NOW() WHERE user_id = ? AND title = 'กำลังดำเนินการเติมเงิน' AND message LIKE ? ORDER BY created_at DESC LIMIT 1");
            $notif_title = 'เติมเงินสำเร็จ';
            $notif_message = sprintf('คุณได้เติมเงินจำนวน ฿%s (โบนัส ฿%s) สำเร็จแล้ว ยอดเงินคงเหลือ ฿%s', number_format($transaction['amount'], 2), number_format($transaction['bonus'], 2), number_format($new_balance, 2));
            $search_pattern = '%รหัสอ้างอิง: ' . $mch_order_no . '%';
            $stmt->bind_param("ssis", $notif_title, $notif_message, $transaction['user_id'], $search_pattern);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, 'success', ?, ?, NOW())");
                $stmt->bind_param("iss", $transaction['user_id'], $notif_title, $notif_message);
                $stmt->execute();
            }
            $stmt->close();
            
            $conn->commit();
            
            sendTopupSuccessNotify($transaction['username'], $transaction['amount'], $transaction['bonus'], $transaction['id'], $new_balance);
            
            file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '✅ SUCCESS: Credited ' . $total_credit . ' THB to ' . $transaction['username'] . PHP_EOL, FILE_APPEND);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        $stmt = $conn->prepare("UPDATE topup_transactions SET status = 'failed', admin_note = ?, updated_at = NOW() WHERE id = ?");
        $note_data = json_encode(['order_status' => $order_status, 'callback_data' => $callback_data], JSON_UNESCAPED_UNICODE);
        $stmt->bind_param("si", $note_data, $transaction['id']);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("UPDATE notifications SET type = 'error', title = ?, message = ?, updated_at = NOW() WHERE user_id = ? AND title = 'กำลังดำเนินการเติมเงิน' AND message LIKE ? ORDER BY created_at DESC LIMIT 1");
        $notif_title = 'เติมเงินล้มเหลว';
        $notif_message = sprintf('การเติมเงินจำนวน ฿%s ล้มเหลว กรุณาลองใหม่อีกครั้ง', number_format($transaction['amount'], 2));
        $search_pattern = '%รหัสอ้างอิง: ' . $mch_order_no . '%';
        $stmt->bind_param("ssis", $notif_title, $notif_message, $transaction['user_id'], $search_pattern);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, 'error', ?, ?, NOW())");
            $stmt->bind_param("iss", $transaction['user_id'], $notif_title, $notif_message);
            $stmt->execute();
        }
        $stmt->close();
        
        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '❌ FAILED: ' . $mch_order_no . PHP_EOL, FILE_APPEND);
    }
    
    echo json_encode(['code' => 0, 'msg' => 'success']);
    
} catch (Exception $e) {
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . '⚠️ ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => $e->getMessage()]);
}

$conn->close();