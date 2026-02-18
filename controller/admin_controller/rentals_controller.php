<?php
/**
 * Rentals Controller - Admin
 * จัดการรายการเช่า VPN/SSH ทั้งหมด
 */

session_start();
require_once 'admin_config.php';
checkAdminAuth();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_stats':
        getStats();
        break;
    case 'get_servers':
        getServers();
        break;
    case 'get_rentals':
        getRentals();
        break;
    case 'get_detail':
        getDetail();
        break;
    case 'extend_rental':
        extendRental();
        break;
    case 'cancel_rental':
        cancelRental();
        break;
    case 'export':
        exportCSV();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getStats()
{
    global $conn;

    // VPN Active
    $vpn_active = $conn->query("SELECT COUNT(*) as c FROM user_rentals WHERE status = 'active' AND expire_date > NOW() AND deleted_at IS NULL")->fetch_assoc()['c'];

    // SSH Active
    $ssh_active = $conn->query("SELECT COUNT(*) as c FROM ssh_rentals WHERE status = 'active' AND expire_date > NOW()")->fetch_assoc()['c'];

    // Expiring Soon (within 7 days)
    $expiring_vpn = $conn->query("SELECT COUNT(*) as c FROM user_rentals WHERE status = 'active' AND expire_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL")->fetch_assoc()['c'];
    $expiring_ssh = $conn->query("SELECT COUNT(*) as c FROM ssh_rentals WHERE status = 'active' AND expire_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

    // Total Revenue
    $vpn_revenue = $conn->query("SELECT COALESCE(SUM(price_paid), 0) as total FROM user_rentals WHERE deleted_at IS NULL")->fetch_assoc()['total'];
    $ssh_revenue = $conn->query("SELECT COALESCE(SUM(price_paid), 0) as total FROM ssh_rentals")->fetch_assoc()['total'];

    echo json_encode([
        'success' => true,
        'vpn_active' => intval($vpn_active),
        'ssh_active' => intval($ssh_active),
        'expiring_soon' => intval($expiring_vpn) + intval($expiring_ssh),
        'total_revenue' => floatval($vpn_revenue) + floatval($ssh_revenue)
    ]);
}

function getServers()
{
    global $conn;

    $vpn_servers = [];
    $result = $conn->query("SELECT server_id, server_name FROM servers WHERE is_active = 1 ORDER BY server_name");
    while ($row = $result->fetch_assoc()) {
        $vpn_servers[] = $row;
    }

    $ssh_servers = [];
    $result = $conn->query("SELECT server_id, server_name FROM ssh_servers WHERE is_active = 1 ORDER BY server_name");
    while ($row = $result->fetch_assoc()) {
        $ssh_servers[] = $row;
    }

    echo json_encode([
        'success' => true,
        'vpn_servers' => $vpn_servers,
        'ssh_servers' => $ssh_servers
    ]);
}

function getRentals()
{
    global $conn;

    $type = $_GET['type'] ?? 'vpn';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $server = $_GET['server'] ?? '';
    $date = $_GET['date'] ?? '';

    if ($type === 'vpn') {
        $sql = "SELECT r.*, u.username, u.email as user_email, s.server_name, p.filename as profile_name
                FROM user_rentals r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN servers s ON r.server_id = s.server_id
                LEFT JOIN price_v2 p ON r.price_id = p.id
                WHERE r.deleted_at IS NULL";

        $count_sql = "SELECT COUNT(*) as total FROM user_rentals r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.deleted_at IS NULL";
    } else {
        $sql = "SELECT r.*, u.username, u.email as user_email, s.server_name, p.product_name
                FROM ssh_rentals r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN ssh_servers s ON r.server_id = s.server_id
                LEFT JOIN ssh_products p ON r.product_id = p.id
                WHERE 1=1";

        $count_sql = "SELECT COUNT(*) as total FROM ssh_rentals r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE 1=1";
    }

    $params = [];
    $types = '';

    // Search
    if (!empty($search)) {
        $search_like = "%{$search}%";
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR r.rental_name LIKE ?)";
        $count_sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR r.rental_name LIKE ?)";
        $params = array_merge($params, [$search_like, $search_like, $search_like]);
        $types .= 'sss';
    }

    // Status filter
    if (!empty($status)) {
        if ($status === 'expired') {
            $sql .= " AND (r.status = 'expired' OR r.expire_date < NOW())";
            $count_sql .= " AND (r.status = 'expired' OR r.expire_date < NOW())";
        } else {
            $sql .= " AND r.status = ?";
            $count_sql .= " AND r.status = ?";
            $params[] = $status;
            $types .= 's';
        }
    }

    // Server filter
    if (!empty($server)) {
        $parts = explode('_', $server, 2);
        if (count($parts) === 2) {
            $sql .= " AND r.server_id = ?";
            $count_sql .= " AND r.server_id = ?";
            $params[] = $parts[1];
            $types .= 's';
        }
    }

    // Date filter
    if (!empty($date)) {
        $sql .= " AND DATE(r.created_at) = ?";
        $count_sql .= " AND DATE(r.created_at) = ?";
        $params[] = $date;
        $types .= 's';
    }

    // Order and limit
    $sql .= " ORDER BY r.id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    // Get total count
    if (!empty($types) && strlen($types) > 2) {
        $count_types = substr($types, 0, -2); // Remove 'ii' for limit/offset
        $count_params = array_slice($params, 0, -2);
        $stmt = $conn->prepare($count_sql);
        if (!empty($count_params)) {
            $stmt->bind_param($count_types, ...$count_params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $total = $conn->query($count_sql)->fetch_assoc()['total'];
    }

    // Get data
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => intval($total),
        'total_pages' => ceil($total / $limit),
        'current_page' => $page
    ]);
}

function getDetail()
{
    global $conn;

    $type = $_GET['type'] ?? 'vpn';
    $id = intval($_GET['id'] ?? 0);

    if ($type === 'vpn') {
        $stmt = $conn->prepare("SELECT r.*, u.username, u.email as user_email, s.server_name, p.filename as profile_name
                                FROM user_rentals r
                                LEFT JOIN users u ON r.user_id = u.id
                                LEFT JOIN servers s ON r.server_id = s.server_id
                                LEFT JOIN price_v2 p ON r.price_id = p.id
                                WHERE r.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT r.*, u.username, u.email as user_email, s.server_name, p.product_name
                                FROM ssh_rentals r
                                LEFT JOIN users u ON r.user_id = u.id
                                LEFT JOIN ssh_servers s ON r.server_id = s.server_id
                                LEFT JOIN ssh_products p ON r.product_id = p.id
                                WHERE r.id = ?");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
    }
}

function extendRental()
{
    global $conn;

    $type = $_POST['type'] ?? 'vpn';
    $id = intval($_POST['id'] ?? 0);
    $days = intval($_POST['days'] ?? 0);

    if ($id <= 0 || $days <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        return;
    }

    $table = $type === 'vpn' ? 'user_rentals' : 'ssh_rentals';

    $stmt = $conn->prepare("UPDATE {$table} SET expire_date = DATE_ADD(expire_date, INTERVAL ? DAY), status = 'active' WHERE id = ?");
    $stmt->bind_param("ii", $days, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "ต่ออายุ {$days} วัน เรียบร้อย"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
}

function cancelRental()
{
    global $conn;

    $type = $_POST['type'] ?? 'vpn';
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID ไม่ถูกต้อง']);
        return;
    }

    $table = $type === 'vpn' ? 'user_rentals' : 'ssh_rentals';

    $stmt = $conn->prepare("UPDATE {$table} SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'ยกเลิกเรียบร้อย']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
}

function exportCSV()
{
    global $conn;

    $type = $_GET['type'] ?? 'vpn';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_rentals_' . date('Ymd') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8

    if ($type === 'vpn') {
        fputcsv($output, ['ID', 'Username', 'Email', 'Server', 'Package', 'Days', 'Data GB', 'Price', 'Status', 'Expire Date', 'Created']);

        $result = $conn->query("SELECT r.*, u.username, u.email, s.server_name, p.filename 
                                FROM user_rentals r
                                LEFT JOIN users u ON r.user_id = u.id
                                LEFT JOIN servers s ON r.server_id = s.server_id
                                LEFT JOIN price_v2 p ON r.price_id = p.id
                                WHERE r.deleted_at IS NULL
                                ORDER BY r.id DESC");

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['email'],
                $row['server_name'],
                $row['filename'],
                $row['days_rented'],
                $row['data_gb_rented'],
                $row['price_paid'],
                $row['status'],
                $row['expire_date'],
                $row['created_at']
            ]);
        }
    } else {
        fputcsv($output, ['ID', 'Username', 'Email', 'Server', 'Package', 'SSH User', 'Days', 'Price', 'Status', 'Expire Date', 'Created']);

        $result = $conn->query("SELECT r.*, u.username, u.email, s.server_name, p.product_name 
                                FROM ssh_rentals r
                                LEFT JOIN users u ON r.user_id = u.id
                                LEFT JOIN ssh_servers s ON r.server_id = s.server_id
                                LEFT JOIN ssh_products p ON r.product_id = p.id
                                ORDER BY r.id DESC");

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['email'],
                $row['server_name'],
                $row['product_name'],
                $row['ssh_username'],
                $row['days_rented'],
                $row['price_paid'],
                $row['status'],
                $row['expire_date'],
                $row['created_at']
            ]);
        }
    }

    fclose($output);
    exit;
}
