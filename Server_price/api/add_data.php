<?php
// Server_price/api/add_data.php
// API to add data to a VPN subscription, handles re-creation of expired users.

require_once __DIR__ . '/../../controller/auth_check.php';
require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/xui_api/multi_xui_api.php';

// --- Function to send JSON response and exit ---
function send_json_response($data, $conn_to_close = null)
{
    if ($conn_to_close) {
        @$conn_to_close->close();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Connection: close');
        $json_data = json_encode($data);
        header('Content-Length: ' . strlen($json_data));
        echo $json_data;
    }

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response(['success' => false, 'message' => 'Invalid request method.']);
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$data_gb_to_add = isset($_POST['data_gb']) ? intval($_POST['data_gb']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($rental_id <= 0 || $data_gb_to_add <= 0) {
    send_json_response(['success' => false, 'message' => "ข้อมูลไม่ถูกต้อง (rental_id=$rental_id, data_gb=$data_gb_to_add)"], $conn);
}

$conn->begin_transaction();

try {
    // Step 1: Fetch rental and pricing info.
    $stmt = $conn->prepare("
        SELECT ur.*, p.data_per_gb, p.min_data_gb
        FROM user_rentals ur
        JOIN price_v2 p ON ur.price_id = p.id
        WHERE ur.id = ? AND ur.user_id = ?
    ");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rental) {
        throw new Exception('ไม่พบรายการเช่าบริการนี้');
    }

    if ($data_gb_to_add < ($rental['min_data_gb'] ?? 10)) {
        throw new Exception('ต้องเพิ่ม Data ขั้นต่ำ ' . ($rental['min_data_gb'] ?? 10) . ' GB');
    }

    // Step 2: Calculate price and check user credit
    $price_per_gb = $rental['data_per_gb'];
    if ($price_per_gb <= 0) {
        throw new Exception('ไม่พบข้อมูลราคาโปรแพ็กเกจนี้');
    }
    $total_price = $data_gb_to_add * $price_per_gb;

    $stmt = $conn->prepare("SELECT credit FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['credit'] < $total_price) {
        throw new Exception('เครดิตไม่เพียงพอ ต้องการ: ฿' . number_format($total_price, 2));
    }

    // Step 3: Deduct credit
    $new_credit = $user['credit'] - $total_price;
    $stmt = $conn->prepare("UPDATE users SET credit = ? WHERE id = ?");
    $stmt->bind_param("di", $new_credit, $user_id);
    $stmt->execute();
    $stmt->close();

    // Step 4: Interact with X-UI API
    $api = new MultiXUIApi($conn);
    $clients_response = $api->getClients($rental['server_id'], $rental['inbound_id']);

    $client_exists = false;
    if (!empty($clients_response['success']) && is_array($clients_response['clients'])) {
        foreach ($clients_response['clients'] as $client) {
            if (isset($client['email']) && $client['email'] == $rental['client_email']) {
                $client_exists = true;
                break;
            }
        }
    }

    $xui_result = null;
    $new_total_bytes = $rental['data_total_bytes'] + ($data_gb_to_add * 1024 * 1024 * 1024);
    $was_recreated = false;

    if ($client_exists) {
        $xui_result = $api->setClientDataLimit($rental['server_id'], $rental['inbound_id'], $rental['client_email'], $new_total_bytes);
    } else {
        $was_recreated = true;
        $client_data = [
            'email' => $rental['client_email'],
            'uuid' => $rental['client_uuid'],
            'expire_days' => 30,
            'data_gb' => $new_total_bytes / (1024 * 1024 * 1024),
            'limit_ip' => $rental['max_devices']
        ];
        $xui_result = $api->addClient($rental['server_id'], $rental['inbound_id'], $client_data);
    }

    if (empty($xui_result['success'])) {
        throw new Exception('ไม่สามารถอัปเดตบริการบนเซิร์ฟเวอร์ VPN ได้: ' . ($xui_result['message'] ?? 'Unknown API error.'));
    }

    // Step 6: Update local database
    if ($was_recreated) {
        $new_expire_date_db = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $conn->prepare("UPDATE user_rentals SET data_total_bytes = ?, expire_date = ?, status = 'active' WHERE id = ?");
        $stmt->bind_param("dsi", $new_total_bytes, $new_expire_date_db, $rental_id);
    } else {
        $stmt = $conn->prepare("UPDATE user_rentals SET data_total_bytes = ? WHERE id = ?");
        $stmt->bind_param("di", $new_total_bytes, $rental_id);
    }
    $stmt->execute();
    $stmt->close();

    // Step 7: Log history and notify
    $old_gb = round($rental['data_total_bytes'] / (1024 * 1024 * 1024), 2);
    $new_gb = round($new_total_bytes / (1024 * 1024 * 1024), 2);
    $stmt = $conn->prepare("INSERT INTO vpn_management_history (rental_id, user_id, action_type, old_value, new_value, amount_changed, price_paid) VALUES (?, ?, 'add_data', ?, ?, ?, ?)");
    $stmt->bind_param("iissid", $rental_id, $user_id, $old_gb, $new_gb, $data_gb_to_add, $total_price);
    $stmt->execute();
    $stmt->close();

    $notification_message = 'เพิ่ม Data ให้ ' . htmlspecialchars($rental['rental_name']) . ' สำเร็จ จำนวน ' . $data_gb_to_add . ' GB';
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'success', 'เพิ่ม Data สำเร็จ', ?)");
    $stmt->bind_param("is", $user_id, $notification_message);
    $stmt->execute();
    $stmt->close();

    // Step 8: Commit transaction
    $conn->commit();

    $response = [
        'success' => true,
        'message' => 'เพิ่ม Data สำเร็จจำนวน ' . $data_gb_to_add . ' GB',
        'data' => [
            'new_total_gb' => $new_gb,
            'price_paid' => $total_price,
            'new_credit' => $new_credit
        ]
    ];

    send_json_response($response, $conn);

} catch (Exception $e) {
    $conn->rollback();
    send_json_response(['success' => false, 'message' => $e->getMessage()], $conn);
}