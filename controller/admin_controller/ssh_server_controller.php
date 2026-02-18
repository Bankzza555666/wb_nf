<?php
/**
 * SSH Server Admin Controller
 * จัดการ CRUD สำหรับ SSH Servers
 */

session_start();
require_once 'admin_config.php';

// Check admin authentication and return JSON error if fails
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้ล็อกอิน']);
    exit;
}

$uid = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_servers':
        getServers();
        break;
    case 'get_server':
        getServer($_GET['id'] ?? 0);
        break;
    case 'save_ssh_server':
        saveServer();
        break;
    case 'delete_ssh_server':
        deleteServer($_POST['id'] ?? 0);
        break;
    case 'test_connection':
        testConnection();
        break;
    case 'create_test_user':
        createTestUser();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

function getServers()
{
    global $conn;

    $result = $conn->query("SELECT * FROM ssh_servers ORDER BY server_name, id");
    $servers = [];

    while ($row = $result->fetch_assoc()) {
        $servers[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $servers]);
}

function getServer($id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM ssh_servers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
    }
}

function saveServer()
{
    global $conn;

    $id = $_POST['id'] ?? '';
    $server_name = $_POST['server_name'] ?? '';
    $server_id = $_POST['server_id'] ?? '';
    $server_host = $_POST['server_host'] ?? '';
    $ssh_port = intval($_POST['ssh_port'] ?? 22);
    $api_port = intval($_POST['api_port'] ?? 80);
    $admin_user = $_POST['admin_user'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';
    $location = $_POST['location'] ?? '';
    $country_code = $_POST['country_code'] ?? 'th';
    $max_users = intval($_POST['max_users'] ?? 500);
    $status = $_POST['status'] ?? 'online';
    $notes = $_POST['notes'] ?? '';

    if (empty($server_name) || empty($server_host) || empty($admin_user)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็น']);
        return;
    }

    // Auto-generate server_id if empty or duplicate
    if (empty($server_id)) {
        $server_id = 'srv-' . time() . '-' . rand(100, 999);
    } else {
        // Check if server_id already exists (for new servers)
        if (empty($id)) {
            $check = $conn->prepare("SELECT id FROM ssh_servers WHERE server_id = ?");
            $check->bind_param("s", $server_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                // Generate unique server_id
                $original_id = $server_id;
                $counter = 1;
                do {
                    $server_id = $original_id . '-' . $counter;
                    $check->bind_param("s", $server_id);
                    $check->execute();
                    $counter++;
                } while ($check->get_result()->num_rows > 0);
            }
            $check->close();
        }
    }

    try {
        if (empty($id)) {
            // Insert new
            if (empty($admin_pass)) {
                echo json_encode(['success' => false, 'message' => 'กรุณาใส่รหัสผ่าน']);
                return;
            }

            $stmt = $conn->prepare("INSERT INTO ssh_servers (server_name, server_id, server_host, ssh_port, api_port, admin_user, admin_pass, location, country_code, max_users, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $conn->error]);
                return;
            }
            $stmt->bind_param("sssiissssiss", $server_name, $server_id, $server_host, $ssh_port, $api_port, $admin_user, $admin_pass, $location, $country_code, $max_users, $status, $notes);
        } else {
            // Update
            if (!empty($admin_pass)) {
                $stmt = $conn->prepare("UPDATE ssh_servers SET server_name=?, server_id=?, server_host=?, ssh_port=?, api_port=?, admin_user=?, admin_pass=?, location=?, country_code=?, max_users=?, status=?, notes=? WHERE id=?");
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $conn->error]);
                    return;
                }
                $stmt->bind_param("sssiissssissi", $server_name, $server_id, $server_host, $ssh_port, $api_port, $admin_user, $admin_pass, $location, $country_code, $max_users, $status, $notes, $id);
            } else {
                $stmt = $conn->prepare("UPDATE ssh_servers SET server_name=?, server_id=?, server_host=?, ssh_port=?, api_port=?, admin_user=?, location=?, country_code=?, max_users=?, status=?, notes=? WHERE id=?");
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $conn->error]);
                    return;
                }
                $stmt->bind_param("sssiisssissi", $server_name, $server_id, $server_host, $ssh_port, $api_port, $admin_user, $location, $country_code, $max_users, $status, $notes, $id);
            }
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ', 'server_id' => $server_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

function deleteServer($id)
{
    global $conn;

    $stmt = $conn->prepare("DELETE FROM ssh_servers WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
    }
}

function testConnection()
{
    $host = $_POST['host'] ?? '';
    $port = intval($_POST['port'] ?? 22);
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if (empty($host) || empty($user)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอก Host และ Username']);
        return;
    }

    // ทดสอบ connection ด้วย socket
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);

    if ($socket) {
        fclose($socket);

        // ถ้ามี SSH2 extension ทดสอบ login
        if (function_exists('ssh2_connect')) {
            $connection = @ssh2_connect($host, $port);
            if ($connection && @ssh2_auth_password($connection, $user, $pass)) {
                echo json_encode(['success' => true, 'message' => 'เชื่อมต่อและ Login สำเร็จ']);
                return;
            }
            echo json_encode(['success' => false, 'message' => 'เชื่อมต่อได้แต่ Login ล้มเหลว']);
            return;
        }

        // Test with SSH API (alternative method)
        require_once __DIR__ . '/../ssh_api/ssh_api.php';

        $server = [
            'server_host' => $host,
            'ssh_port' => $port,
            'admin_user' => $user,
            'admin_pass' => $pass
        ];

        $sshApi = new SSHPlusManagerAPI($server);
        $testResult = $sshApi->testConnection();

        if ($testResult['success']) {
            echo json_encode(['success' => true, 'message' => "เชื่อมต่อสำเร็จ (พร้อมจัดการผู้ใช้งาน)"]);
        } else {
            echo json_encode(['success' => true, 'message' => "เชื่อมต่อ Port {$port} สำเร็จ (ไม่มี SSH2 extension - ใช้งานผ่าน SSH API ได้)"]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "ไม่สามารถเชื่อมต่อ: {$errstr}"]);
    }
}

function createTestUser()
{
    $serverId = $_POST['server_id'] ?? '';

    if (empty($serverId)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาระบุ Server ID']);
        return;
    }

    global $conn;

    // Get server info
    $stmt = $conn->prepare("SELECT * FROM ssh_servers WHERE server_id = ?");
    $stmt->bind_param("s", $serverId);
    $stmt->execute();
    $server = $stmt->get_result()->fetch_assoc();

    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล Server']);
        return;
    }

    require_once __DIR__ . '/../ssh_api/ssh_api.php';

    $sshApi = new SSHPlusManagerAPI($server, $conn);

    // Generate test user
    $username = SSHPlusManagerAPI::generateUsername('test');
    $password = SSHPlusManagerAPI::generatePassword(8);

    $result = $sshApi->createUser($username, $password, 1, 1);

    echo json_encode($result);
}
