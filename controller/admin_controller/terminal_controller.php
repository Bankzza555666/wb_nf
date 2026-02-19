<?php
/**
 * Terminal Controller - Uses a persistent background SSH worker process.
 *
 * Architecture:
 *   connect      → validate creds, write JSON creds file, spawn ssh_worker.php
 *   stream (SSE) → long-poll the worker's output buffer file (no SSH connection here)
 *   send_input   → append raw bytes to the worker's input pipe file
 *   resize       → write resize JSON file for worker to pick up
 *   disconnect   → signal worker to stop, clean up files
 */
error_reporting(0);
ini_set('display_errors', 0);

ob_start();
session_start();
require_once 'admin_config.php';
ob_clean();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$uid  = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'connect':       connectSSH();      break;
    case 'stream':        streamOutput();    break;
    case 'send_input':    sendInput();       break;
    case 'resize':        resizeTerminal();  break;
    case 'disconnect':    disconnectSSH();   break;
    case 'get_server_info': getServerInfo(); break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ── File path helpers ─────────────────────────────────────────────────────────
function sanitizeId(string $id): string
{
    return preg_replace('/[^a-f0-9]/i', '', $id);
}
function credsFile(string $sid): string { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_creds_' . $sid . '.json'; }
function inputFile(string $sid): string { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_in_'    . $sid . '.pipe'; }
function outputFile(string $sid): string { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_out_'   . $sid . '.buf';  }
function pidFile(string $sid): string   { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_pid_'   . $sid . '.pid';  }
function stopFile(string $sid): string  { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_stop_'  . $sid . '.flag'; }
function resizeFile(string $sid): string{ return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_rs_'    . $sid . '.pipe'; }

function cleanupFiles(string $sid): void
{
    foreach ([credsFile($sid), inputFile($sid), outputFile($sid), pidFile($sid), stopFile($sid), resizeFile($sid)] as $f)
        @unlink($f);
}

function spawnWorker(string $sessionId): void
{
    $worker = __DIR__ . DIRECTORY_SEPARATOR . 'ssh_worker.php';
    if (!file_exists($worker)) return;

    // When PHP runs as apache2handler, PHP_BINARY = httpd.exe (wrong!)
    // We must find the actual php.exe CLI binary
    $phpBin = PHP_BINARY;
    if (PHP_OS_FAMILY === 'Windows') {
        $basename = strtolower(basename($phpBin));
        // If PHP_BINARY is NOT php.exe, we need to search for it
        if ($basename !== 'php.exe') {
            $phpBin = ''; // will search below

            // Strategy 1: Look for php.exe next to the extension_dir
            $extDir = ini_get('extension_dir');
            if ($extDir) {
                $candidate = dirname(str_replace('/', '\\', $extDir)) . '\\php.exe';
                if (file_exists($candidate)) $phpBin = $candidate;
            }

            // Strategy 2: Common XAMPP paths
            if (!$phpBin) {
                foreach (['C:\\xampp\\php\\php.exe', 'D:\\xampp\\php\\php.exe'] as $p) {
                    if (file_exists($p)) { $phpBin = $p; break; }
                }
            }

            // Strategy 3: where.exe lookup
            if (!$phpBin) {
                $w = trim(shell_exec('where php.exe 2>NUL') ?? '');
                if ($w && file_exists($w)) $phpBin = explode("\n", $w)[0];
            }

            if (!$phpBin) return; // cannot find php.exe
        }
        $phpBin = str_replace('/', '\\', $phpBin);
        $worker = str_replace('/', '\\', $worker);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        // Use cmd /c start /B for non-blocking background spawn on Windows
        // Note: pclose(popen()) returns immediately with start /B
        $cmd = sprintf(
            'cmd /c start /B "" "%s" "%s" "%s"',
            $phpBin,
            $worker,
            $sessionId
        );
        pclose(popen($cmd, 'r'));
    } else {
        exec(sprintf('%s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($worker),
            escapeshellarg($sessionId)
        ));
    }
}

function isWorkerAlive(string $sid): bool
{
    $f = pidFile($sid);
    if (!file_exists($f)) return false;
    $pid = (int)file_get_contents($f);
    if ($pid <= 0) return false;
    if (PHP_OS_FAMILY === 'Windows') {
        $out = shell_exec("tasklist /FI \"PID eq {$pid}\" /NH 2>NUL");
        return $out && strpos($out, (string)$pid) !== false;
    }
    return file_exists("/proc/{$pid}");
}

// ── Actions ───────────────────────────────────────────────────────────────────

function getServerInfo()
{
    global $conn;
    $serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing server_id']);
        return;
    }
    $stmt = $conn->prepare("SELECT server_id,server_name,server_host,ssh_port,location,admin_user FROM ssh_servers WHERE server_id=?");
    $stmt->bind_param("s", $serverId);
    $stmt->execute();
    $server = $stmt->get_result()->fetch_assoc();
    if (!$server) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server not found']);
        return;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => [
        'server_id'   => $server['server_id'],
        'server_name' => $server['server_name'],
        'server_host' => $server['server_host'],
        'ssh_port'    => $server['ssh_port'],
        'location'    => $server['location'],
        'username'    => $server['admin_user'],
    ]]);
}

function connectSSH()
{
    global $conn;
    $serverId = $_POST['server_id'] ?? '';
    if (empty($serverId)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing server_id']);
        return;
    }
    $stmt = $conn->prepare("SELECT * FROM ssh_servers WHERE server_id=?");
    $stmt->bind_param("s", $serverId);
    $stmt->execute();
    $server = $stmt->get_result()->fetch_assoc();
    if (!$server) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server not found']);
        return;
    }

    // Quick connectivity test
    $sock = @fsockopen($server['server_host'], $server['ssh_port'], $errno, $errstr, 5);
    if (!$sock) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Cannot reach server: $errstr"]);
        return;
    }
    fclose($sock);

    $sessionId = sanitizeId(bin2hex(random_bytes(16)));

    // Store minimal info in PHP session
    $_SESSION['terminal_sessions'][$sessionId] = [
        'server_id'    => $serverId,
        'connected_at' => time(),
    ];

    // Write SSH credentials to temp JSON (worker reads this)
    file_put_contents(credsFile($sessionId), json_encode([
        'host'     => $server['server_host'],
        'port'     => (int)$server['ssh_port'],
        'username' => $server['admin_user'],
        'password' => $server['admin_pass'],
        'cols'     => 220,
        'rows'     => 50,
    ]));

    // Initialize input pipe
    file_put_contents(inputFile($sessionId), '');

    // Spawn background worker
    spawnWorker($sessionId);

    // Wait up to 4 s for worker to write its PID (confirm it started)
    $waited = 0;
    while (!file_exists(pidFile($sessionId)) && $waited < 40) {
        usleep(100000);
        $waited++;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success'     => true,
        'message'     => "Connected to {$server['server_name']}",
        'session_id'  => $sessionId,
        'server_info' => [
            'name'     => $server['server_name'],
            'host'     => $server['server_host'],
            'port'     => $server['ssh_port'],
            'username' => $server['admin_user'],
        ],
    ]);
}

function sendInput()
{
    $sessionId = sanitizeId($_POST['session_id'] ?? '');
    $input     = $_POST['input'] ?? '';

    if (empty($sessionId) || !isset($_SESSION['terminal_sessions'][$sessionId])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        return;
    }
    session_write_close(); // release lock ASAP

    $fp = fopen(inputFile($sessionId), 'ab');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $input);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
}

function resizeTerminal()
{
    $sessionId = sanitizeId($_POST['session_id'] ?? '');
    $cols      = max(20, (int)($_POST['cols'] ?? 220));
    $rows      = max(5,  (int)($_POST['rows'] ?? 50));

    if (empty($sessionId) || !isset($_SESSION['terminal_sessions'][$sessionId])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        return;
    }
    session_write_close();

    file_put_contents(resizeFile($sessionId), json_encode(['cols' => $cols, 'rows' => $rows]));
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
}

function streamOutput()
{
    $sessionId = sanitizeId($_GET['session_id'] ?? '');

    if (empty($sessionId) || !isset($_SESSION['terminal_sessions'][$sessionId])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        return;
    }
    session_write_close(); // must release before long loop

    while (ob_get_level() > 0) ob_end_clean();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    set_time_limit(0);
    ignore_user_abort(false);

    $outFile = outputFile($sessionId);
    $pidF    = pidFile($sessionId);

    // Resume from Last-Event-ID (= byte offset in $outFile)
    $pos = (int)($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);
    if ($pos < 0) $pos = 0;

    $lastHeartbeat = time();
    $maxIdle       = 3600;
    $lastData      = time();

    // Wait up to 5 s for output file (worker may still be starting)
    $waited = 0;
    while (!file_exists($outFile) && $waited < 50) {
        usleep(100000);
        $waited++;
    }

    while (true) {
        if (connection_aborted()) break;
        if ((time() - $lastData) > $maxIdle) break;

        // Check worker is still alive (check every 5 s to save syscalls)
        static $lastAliveCheck = 0;
        if ((time() - $lastAliveCheck) >= 5) {
            if (!file_exists($pidF)) {
                sendSSE('close', base64_encode('SSH session ended'));
                break;
            }
            $lastAliveCheck = time();
        }

        // Read new bytes from the output buffer
        clearstatcache(true, $outFile);
        if (file_exists($outFile)) {
            $fsz = filesize($outFile);
            if ($fsz > $pos) {
                $fp = fopen($outFile, 'rb');
                if ($fp) {
                    fseek($fp, $pos);
                    $chunk = fread($fp, min(65536, $fsz - $pos));
                    fclose($fp);
                    if ($chunk !== '') {
                        $pos += strlen($chunk);
                        sendSSEWithId('data', base64_encode($chunk), $pos);
                        $lastData = time();
                    }
                }
            }
        }

        // Heartbeat ping every 20 s (keeps the connection alive through proxies)
        if ((time() - $lastHeartbeat) >= 20) {
            echo ": ping\n\n";
            flush();
            $lastHeartbeat = time();
        }

        usleep(50000); // 50 ms poll
    }
}

function sendSSEWithId(string $event, string $data, int $id): void
{
    echo "id: {$id}\n";
    echo "event: {$event}\n";
    echo "data: {$data}\n\n";
    flush();
}

function sendSSE(string $event, string $data): void
{
    echo "event: {$event}\n";
    echo "data: {$data}\n\n";
    flush();
}

function disconnectSSH()
{
    $sessionId = sanitizeId($_POST['session_id'] ?? '');
    if (!empty($sessionId) && isset($_SESSION['terminal_sessions'][$sessionId])) {
        // Signal worker to exit cleanly
        file_put_contents(stopFile($sessionId), '1');
        usleep(400000); // 400 ms grace period
        // Force-remove all temp files
        cleanupFiles($sessionId);
        unset($_SESSION['terminal_sessions'][$sessionId]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Disconnected']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session not found']);
    }
}
