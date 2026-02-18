<?php
// Server_price/api/extend_days.php
// API to extend VPN subscription, handles re-creation of expired users.

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
$days_to_add = isset($_POST['days']) ? intval($_POST['days']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($rental_id <= 0 || $days_to_add <= 0) {
    send_json_response(['success' => false, 'message' => "ข้อมูลไม่ถูกต้อง (rental_id=$rental_id, days=$days_to_add)"], $conn);
}

$conn->begin_transaction();

try {
    // Step 1: Fetch rental and pricing info.
    $stmt = $conn->prepare("
        SELECT ur.*, p.price_per_day, p.min_days
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

    if ($days_to_add < ($rental['min_days'] ?? 1)) {
        throw new Exception('ต้องต่ออายุขั้นต่ำ ' . ($rental['min_days'] ?? 1) . ' วัน');
    }

    // Step 2: Calculate price and check user credit
    $price_per_day = $rental['price_per_day'];
    if ($price_per_day <= 0) {
        throw new Exception('ไม่พบข้อมูลราคาโปรแพ็กเกจนี้');
    }
    $total_price = $days_to_add * $price_per_day;

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

    if ($client_exists) {
        $xui_result = $api->extendClientExpiry($rental['server_id'], $rental['inbound_id'], $rental['client_email'], $days_to_add);
    } else {
        $base_timestamp = (strtotime($rental['expire_date']) > time()) ? strtotime($rental['expire_date']) : time();
        $new_expiry_timestamp = $base_timestamp + ($days_to_add * 86400);
        $client_data = [
            'email' => $rental['client_email'],
            'uuid' => $rental['client_uuid'],
            'expire_days' => ceil(($new_expiry_timestamp - time()) / 86400),
            'data_gb' => $rental['data_total_bytes'] / (1024 * 1024 * 1024),
            'limit_ip' => $rental['max_devices']
        ];
        $xui_result = $api->addClient($rental['server_id'], $rental['inbound_id'], $client_data);
    }

    if (empty($xui_result['success'])) {
        throw new Exception('ไม่สามารถอัปเดตบริการบนเซิร์ฟเวอร์ VPN ได้: ' . ($xui_result['message'] ?? 'Unknown API error.'));
    }

    // Step 6: Update local database
    $base_date_for_db = (strtotime($rental['expire_date']) > time()) ? $rental['expire_date'] : date('Y-m-d H:i:s');
    $new_expire_date_db = date('Y-m-d H:i:s', strtotime($base_date_for_db . ' +' . $days_to_add . ' days'));

    $stmt = $conn->prepare("UPDATE user_rentals SET expire_date = ?, status = 'active' WHERE id = ?");
    $stmt->bind_param("si", $new_expire_date_db, $rental_id);
    $stmt->execute();
    $stmt->close();

    // Step 7: Log history and notify
    $stmt = $conn->prepare("INSERT INTO vpn_management_history (rental_id, user_id, action_type, old_value, new_value, amount_changed, price_paid) VALUES (?, ?, 'extend_days', ?, ?, ?, ?)");
    $stmt->bind_param("iissid", $rental_id, $user_id, $rental['expire_date'], $new_expire_date_db, $days_to_add, $total_price);
    $stmt->execute();
    $stmt->close();

    $notification_message = 'ต่ออายุ VPN ' . htmlspecialchars($rental['rental_name']) . ' สำเร็จ จำนวน ' . $days_to_add . ' วัน';
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'success', 'ต่ออายุสำเร็จ', ?)");
    $stmt->bind_param("is", $user_id, $notification_message);
    $stmt->execute();
    $stmt->close();

    // Step 8: Commit transaction
    $conn->commit();

    $response = [
        'success' => true,
        'message' => 'ต่ออายุสำเร็จจำนวน ' . $days_to_add . ' วัน',
        'data' => [
            'new_expire_date' => $new_expire_date_db,
            'price_paid' => $total_price,
            'new_credit' => $new_credit
        ]
    ];

    send_json_response($response, $conn);

} catch (Exception $e) {
    $conn->rollback();
    send_json_response(['success' => false, 'message' => $e->getMessage()], $conn);
}