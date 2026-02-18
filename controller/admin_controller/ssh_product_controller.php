<?php
/**
 * SSH Product Admin Controller
 * จัดการ CRUD สำหรับ SSH Products
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
    case 'save_ssh_product':
        saveProduct();
        break;
    case 'delete_ssh_product':
        deleteProduct($_POST['id'] ?? 0);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getProducts()
{
    global $conn;

    $sql = "SELECT p.*, s.server_name, img.filename as image_filename 
            FROM ssh_products p 
            LEFT JOIN ssh_servers s ON p.server_id = s.server_id 
            LEFT JOIN product_images img ON p.image_id = img.id AND p.image_id > 0
            ORDER BY p.sort_order, p.product_name";
    $result = $conn->query($sql);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $products]);
}

function getProduct($id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT p.*, img.filename as image_filename, img.uploaded_at FROM ssh_products p LEFT JOIN product_images img ON p.image_id = img.id AND p.image_id > 0 WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['image_filename']) {
            $row['image_url'] = '../img/products/' . $row['image_filename'];
        }
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
    }
}

function saveProduct()
{
    global $conn;

    $id = $_POST['id'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $server_id = $_POST['server_id'] ?? '';
    $price_per_day = floatval($_POST['price_per_day'] ?? 0);
    $min_days = intval($_POST['min_days'] ?? 1);
    $max_days = intval($_POST['max_days'] ?? 30);
    $max_devices = intval($_POST['max_devices'] ?? 1);
    $description = $_POST['description'] ?? '';

    // Process features
    $features_text = $_POST['features'] ?? '';
    $features_array = array_values(array_filter(array_map('trim', explode("\n", $features_text))));
    $features_json = json_encode($features_array, JSON_UNESCAPED_UNICODE);

    $ssh_config_template = $_POST['ssh_config_template'] ?? '';
    $npv_config_template = $_POST['npv_config_template'] ?? '';
    $is_popular = intval($_POST['is_popular'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 1);
    $is_active = intval($_POST['is_active'] ?? 1);

    // Fix: Treat '0', '', '0' as null for image_id
    $raw_image_id = $_POST['image_id'] ?? '';
    $image_id = ($raw_image_id !== '' && $raw_image_id !== '0' && intval($raw_image_id) > 0) ? intval($raw_image_id) : null;

    $sort_order = intval($_POST['sort_order'] ?? 0);

    if (empty($product_name) || empty($server_id) || empty($ssh_config_template) || empty($npv_config_template)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็น']);
        return;
    }

    if ($price_per_day <= 0) {
        echo json_encode(['success' => false, 'message' => 'ราคาต้องมากกว่า 0']);
        return;
    }

    if (empty($id)) {
        // Insert new - ใช้ IFNULL เพื่อจัดการ NULL ได้ถูกต้อง
        if ($image_id === null) {
            $stmt = $conn->prepare("INSERT INTO ssh_products (product_name, server_id, price_per_day, min_days, max_days, max_devices, description, features, ssh_config_template, npv_config_template, is_popular, is_active, sort_order, image_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)");
            $stmt->bind_param("ssdiiissssiii", $product_name, $server_id, $price_per_day, $min_days, $max_days, $max_devices, $description, $features_json, $ssh_config_template, $npv_config_template, $is_popular, $is_active, $sort_order);
        } else {
            $stmt = $conn->prepare("INSERT INTO ssh_products (product_name, server_id, price_per_day, min_days, max_days, max_devices, description, features, ssh_config_template, npv_config_template, is_popular, is_active, sort_order, image_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdiiissssiiii", $product_name, $server_id, $price_per_day, $min_days, $max_days, $max_devices, $description, $features_json, $ssh_config_template, $npv_config_template, $is_popular, $is_active, $sort_order, $image_id);
        }
    } else {
        // Update - ใช้ IFNULL เพื่อจัดการ NULL ได้ถูกต้อง
        if ($image_id === null) {
            $stmt = $conn->prepare("UPDATE ssh_products SET product_name=?, server_id=?, price_per_day=?, min_days=?, max_days=?, max_devices=?, description=?, features=?, ssh_config_template=?, npv_config_template=?, is_popular=?, is_active=?, sort_order=?, image_id=NULL WHERE id=?");
            $stmt->bind_param("ssdiiissssiiii", $product_name, $server_id, $price_per_day, $min_days, $max_days, $max_devices, $description, $features_json, $ssh_config_template, $npv_config_template, $is_popular, $is_active, $sort_order, $id);
        } else {
            $stmt = $conn->prepare("UPDATE ssh_products SET product_name=?, server_id=?, price_per_day=?, min_days=?, max_days=?, max_devices=?, description=?, features=?, ssh_config_template=?, npv_config_template=?, is_popular=?, is_active=?, sort_order=?, image_id=? WHERE id=?");
            $stmt->bind_param("ssdiiissssiiiii", $product_name, $server_id, $price_per_day, $min_days, $max_days, $max_devices, $description, $features_json, $ssh_config_template, $npv_config_template, $is_popular, $is_active, $sort_order, $image_id, $id);
        }
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
    }
}

function deleteProduct($id)
{
    global $conn;

    // Check if there are active rentals
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM ssh_rentals WHERE product_id = ? AND status = 'active'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ มีการเช่าที่ยังใช้งานอยู่']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM ssh_products WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
    }
}
