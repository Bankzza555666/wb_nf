<?php
/**
 * VPN Product Controller
 * จัดการแพ็กเกจ VPN (price_v2) - รองรับ Image
 */

session_start();
require_once 'admin_config.php';
checkAdminAuth();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_products':
        getProducts();
        break;
    case 'get_product':
        getProduct($_GET['id'] ?? 0);
        break;
    case 'save_vpn_product':
        saveProduct();
        break;
    case 'delete_product':
        deleteProduct($_POST['id'] ?? 0);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getProducts()
{
    global $conn;

    $sql = "SELECT p.*, s.server_name, img.filename as image_filename 
            FROM price_v2 p 
            LEFT JOIN servers s ON p.server_id = s.server_id 
            LEFT JOIN product_images img ON p.image_id = img.id AND p.image_id > 0
            ORDER BY p.sort_order ASC, p.id DESC";
    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
        return;
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['image_url'] = $row['image_filename'] ? '../img/products/' . $row['image_filename'] : null;
        $products[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $products]);
}

function getProduct($id)
{
    global $conn;

    $id = intval($id);
    $stmt = $conn->prepare("SELECT p.*, img.filename as image_filename, img.uploaded_at 
                            FROM price_v2 p 
                            LEFT JOIN product_images img ON p.image_id = img.id AND p.image_id > 0 
                            WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $row['image_url'] = $row['image_filename'] ? '../img/products/' . $row['image_filename'] : null;
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
    }
    $stmt->close();
}

function saveProduct()
{
    global $conn;

    $id = intval($_POST['id'] ?? 0);
    $filename = trim($_POST['filename'] ?? '');
    $server_id = $_POST['server_id'] ?? '';
    $inbound_id = intval($_POST['inbound_id'] ?? 0);
    $host = trim($_POST['host'] ?? '');
    $port = intval($_POST['port'] ?? 0);
    $protocol = trim($_POST['protocol'] ?? 'vless');
    $network = trim($_POST['network'] ?? 'tcp');
    $security = trim($_POST['security'] ?? 'tls');
    $price_per_day = floatval($_POST['price_per_day'] ?? 0);
    $data_per_gb = floatval($_POST['data_per_gb'] ?? 0);
    $max_days = intval($_POST['max_days'] ?? 365);
    $min_data_gb = intval($_POST['min_data_gb'] ?? 10);
    $max_data_gb = intval($_POST['max_data_gb'] ?? 1000);
    $max_devices = intval($_POST['max_devices'] ?? 5);
    $speed_limit_mbps = intval($_POST['speed_limit_mbps'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $config_template = trim($_POST['config_template'] ?? '');
    $is_popular = intval($_POST['is_popular'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 1);
    $sort_order = intval($_POST['sort_order'] ?? 0);

    // Handle image_id
    $raw_image_id = $_POST['image_id'] ?? '';
    $image_id = ($raw_image_id !== '' && $raw_image_id !== '0' && intval($raw_image_id) > 0) ? intval($raw_image_id) : null;

    // Handle features - convert newline to JSON array
    $features_raw = trim($_POST['features'] ?? '');
    if (!empty($features_raw)) {
        $features_array = array_filter(array_map('trim', explode("\n", $features_raw)));
        $features = json_encode(array_values($features_array), JSON_UNESCAPED_UNICODE);
    } else {
        $features = null;
    }

    // Validation
    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาใส่ชื่อแพ็กเกจ']);
        return;
    }

    if (empty($server_id)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเลือก Server']);
        return;
    }

    try {
        if ($id > 0) {
            // Update
            if ($image_id === null) {
                $sql = "UPDATE price_v2 SET 
                        filename=?, server_id=?, inbound_id=?, host=?, port=?, 
                        protocol=?, network=?, security=?, 
                        price_per_day=?, data_per_gb=?, 
                        min_days=1, max_days=?, min_data_gb=?, max_data_gb=?, 
                        max_devices=?, speed_limit_mbps=?, 
                        description=?, features=?, is_popular=?, is_active=?, 
                        config_template=?, sort_order=?, image_id=NULL 
                        WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssisisssddiiiiissiiisi",
                    $filename, $server_id, $inbound_id, $host, $port,
                    $protocol, $network, $security,
                    $price_per_day, $data_per_gb,
                    $max_days, $min_data_gb, $max_data_gb,
                    $max_devices, $speed_limit_mbps,
                    $description, $features, $is_popular, $is_active,
                    $config_template, $sort_order, $id
                );
            } else {
                $sql = "UPDATE price_v2 SET 
                        filename=?, server_id=?, inbound_id=?, host=?, port=?, 
                        protocol=?, network=?, security=?, 
                        price_per_day=?, data_per_gb=?, 
                        min_days=1, max_days=?, min_data_gb=?, max_data_gb=?, 
                        max_devices=?, speed_limit_mbps=?, 
                        description=?, features=?, is_popular=?, is_active=?, 
                        config_template=?, sort_order=?, image_id=? 
                        WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssisisssddiiiiissiisiii",
                    $filename, $server_id, $inbound_id, $host, $port,
                    $protocol, $network, $security,
                    $price_per_day, $data_per_gb,
                    $max_days, $min_data_gb, $max_data_gb,
                    $max_devices, $speed_limit_mbps,
                    $description, $features, $is_popular, $is_active,
                    $config_template, $sort_order, $image_id, $id
                );
            }
        } else {
            // Insert
            if ($image_id === null) {
                $sql = "INSERT INTO price_v2 (
                        filename, server_id, inbound_id, host, port, 
                        protocol, network, security, 
                        price_per_day, data_per_gb, 
                        min_days, max_days, min_data_gb, max_data_gb, 
                        max_devices, speed_limit_mbps, 
                        description, features, is_popular, is_active, 
                        config_template, sort_order, image_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssisisssddiiiiissiisi",
                    $filename, $server_id, $inbound_id, $host, $port,
                    $protocol, $network, $security,
                    $price_per_day, $data_per_gb,
                    $max_days, $min_data_gb, $max_data_gb,
                    $max_devices, $speed_limit_mbps,
                    $description, $features, $is_popular, $is_active,
                    $config_template, $sort_order
                );
            } else {
                $sql = "INSERT INTO price_v2 (
                        filename, server_id, inbound_id, host, port, 
                        protocol, network, security, 
                        price_per_day, data_per_gb, 
                        min_days, max_days, min_data_gb, max_data_gb, 
                        max_devices, speed_limit_mbps, 
                        description, features, is_popular, is_active, 
                        config_template, sort_order, image_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssisisssddiiiiissiisii",
                    $filename, $server_id, $inbound_id, $host, $port,
                    $protocol, $network, $security,
                    $price_per_day, $data_per_gb,
                    $max_days, $min_data_gb, $max_data_gb,
                    $max_devices, $speed_limit_mbps,
                    $description, $features, $is_popular, $is_active,
                    $config_template, $sort_order, $image_id
                );
            }
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'บันทึกสำเร็จ']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

function deleteProduct($id)
{
    global $conn;

    $id = intval($id);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }

    // Check if product is in use
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_rentals WHERE price_id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ มีผู้ใช้งานอยู่ ' . $result['cnt'] . ' รายการ']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM price_v2 WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'ลบสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
}
