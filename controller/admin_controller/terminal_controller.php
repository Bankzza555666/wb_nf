<?php
/**
 * Terminal Controller - Interactive Web SSH Terminal Backend
 * Uses phpseclib for SSH connections
 */

// Suppress PHP errors
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

session_start();
require_once 'admin_config.php';

ob_clean();

// Check admin authentication
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'connect':
        connectSSH();
        break;
    case 'execute':
        executeCommand();
        break;
    case 'disconnect':
        disconnectSSH();
        break;
    case 'get_server_info':
        getServerInfo();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getServerInfo()
{
    global $conn;

    $serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? '';

    if (empty($serverId)) {
        echo json_encode(['success' => false, 'message' => 'Missing server_id']);
        return;
    }

    $stmt = $conn->prepare("SELECT server_id, server_name, server_host, ssh_port, location, admin_user FROM ssh_servers WHERE server_id = ?");
    $stmt->bind_param("s", $serverId);
    $stmt->execute();
    $server = $stmt->get_result()->fetch_assoc();

    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Server not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'server_id' => $server['server_id'],
            'server_name' => $server['server_name'],
            'server_host' => $server['server_host'],
            'ssh_port' => $server['ssh_port'],
            'location' => $server['location'],
            'username' => $server['admin_user']
        ]
    ]);
}

function connectSSH()
{
    global $conn;

    $serverId = $_POST['server_id'] ?? '';

    if (empty($serverId)) {
        echo json_encode(['success' => false, 'message' => 'Missing server_id']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM ssh_servers WHERE server_id = ?");
    $stmt->bind_param("s", $serverId);
    $stmt->execute();
    $server = $stmt->get_result()->fetch_assoc();

    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Server not found']);
        return;
    }

    // Test connection
    $socket = @fsockopen($server['server_host'], $server['ssh_port'], $errno, $errstr, 5);
    if (!$socket) {
        echo json_encode(['success' => false, 'message' => "Cannot connect: $errstr"]);
        return;
    }
    fclose($socket);

    $sessionId = bin2hex(random_bytes(16));
    $_SESSION['terminal_sessions'][$sessionId] = [
        'server_id' => $serverId,
        'host' => $server['server_host'],
        'port' => $server['ssh_port'],
        'username' => $server['admin_user'],
        'password' => $server['admin_pass'],
        'connected_at' => time(),
        'last_activity' => time(),
        'command_history' => [] // Track command history for context
    ];

    echo json_encode([
        'success' => true,
        'message' => "Connected to {$server['server_name']}",
        'session_id' => $sessionId,
        'server_info' => [
            'name' => $server['server_name'],
            'host' => $server['server_host'],
            'port' => $server['ssh_port'],
            'username' => $server['admin_user']
        ]
    ]);
}

/**
 * Execute command via SSH
 */
function executeCommand()
{
    try {
        $sessionId = $_POST['session_id'] ?? '';
        $command = $_POST['command'] ?? '';

        if (empty($sessionId) || !isset($_SESSION['terminal_sessions'][$sessionId])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
            return;
        }

        $session = $_SESSION['terminal_sessions'][$sessionId];
        $_SESSION['terminal_sessions'][$sessionId]['last_activity'] = time();

        // Check if this is a menu input (single number or short input after menu command)
        $lastCommand = end($_SESSION['terminal_sessions'][$sessionId]['command_history'] ?? []);
        $isMenuInput = $lastCommand === 'menu' && preg_match('/^\d{1,2}$/', trim($command));

        // Store command in history
        $_SESSION['terminal_sessions'][$sessionId]['command_history'][] = $command;

        // Keep only last 10 commands
        if (count($_SESSION['terminal_sessions'][$sessionId]['command_history']) > 10) {
            array_shift($_SESSION['terminal_sessions'][$sessionId]['command_history']);
        }

        // Execute command
        $output = executeSSHWithPhpseclib(
            $session['host'],
            $session['port'],
            $session['username'],
            $session['password'],
            $command,
            $isMenuInput
        );

        ob_clean();
        echo json_encode([
            'success' => true,
            'output' => $output,
            'prompt' => $session['username'] . '@' . $session['host'] . ':~$ '
        ]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    } catch (Error $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Execute SSH command using phpseclib
 */
function executeSSHWithPhpseclib($host, $port, $username, $password, $command, $isMenuInput = false)
{
    // Load phpseclib
    $phpseclibPath = __DIR__ . '/../phpseclib/phpseclib';
    $paragonPath = __DIR__ . '/../paragonie/src';

    // Register autoloader only if not already registered
    static $autoloaderRegistered = false;
    if (!$autoloaderRegistered) {
        spl_autoload_register(function ($class) use ($phpseclibPath, $paragonPath) {
            if (strncmp('ParagonIE\\ConstantTime\\', $class, 23) === 0) {
                $relative_class = substr($class, 23);
                $file = $paragonPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
            if (strncmp('phpseclib3\\', $class, 11) === 0) {
                $relative_class = substr($class, 11);
                $file = $phpseclibPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });
        $autoloaderRegistered = true;
    }

    try {
        $ssh = new \phpseclib3\Net\SSH2($host, $port, 10);
        $ssh->setTimeout(30);

        if (!$ssh->login($username, $password)) {
            return "Authentication failed";
        }

        // For interactive menu commands, use shell mode
        if ($command === 'menu' || $isMenuInput) {
            // Set TERM and run the command in a way that captures output
            $fullCommand = "export TERM=xterm && $command";
            $output = $ssh->exec($fullCommand);
        } else {
            // Regular command execution
            $output = $ssh->exec($command);
        }

        return $output ?: "(No output)";
    } catch (Exception $e) {
        return "SSH Error: " . $e->getMessage();
    }
}

function disconnectSSH()
{
    $sessionId = $_POST['session_id'] ?? '';

    if (!empty($sessionId) && isset($_SESSION['terminal_sessions'][$sessionId])) {
        unset($_SESSION['terminal_sessions'][$sessionId]);
        echo json_encode(['success' => true, 'message' => 'Disconnected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
    }
}
