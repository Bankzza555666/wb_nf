<?php
/**
 * SSH Worker - Background process that keeps a persistent PTY shell alive.
 * Spawned by terminal_controller.php. Runs independently of HTTP requests.
 * Usage: php ssh_worker.php <sessionId>
 */

// Record errors to a log file for debugging
$debugLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssh_worker_debug.log';

function workerLog(string $msg): void
{
    global $debugLog;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($debugLog, "[{$ts}] {$msg}\n", FILE_APPEND);
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    workerLog("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
    return true;
});

set_exception_handler(function ($e) {
    workerLog("Uncaught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        workerLog("FATAL: {$error['message']} in {$error['file']}:{$error['line']}");
    }
    workerLog("Worker shutdown");
});

workerLog("Worker starting, argc=" . (isset($argc) ? $argc : 'N/A') . ", argv=" . json_encode($argv ?? []));

if (!isset($argv[1]) || empty($argv[1])) {
    workerLog("No session ID argument, exiting");
    exit(1);
}

$sessionId = preg_replace('/[^a-f0-9]/i', '', $argv[1]);
if (empty($sessionId)) {
    workerLog("Session ID empty after sanitization, exiting");
    exit(1);
}

workerLog("Session ID: {$sessionId}");

set_time_limit(0);
ignore_user_abort(true);
error_reporting(E_ALL);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
$credsFile  = $tmp . 'ssh_creds_' . $sessionId . '.json';
$inputFile  = $tmp . 'ssh_in_'    . $sessionId . '.pipe';
$outputFile = $tmp . 'ssh_out_'   . $sessionId . '.buf';
$pidFile    = $tmp . 'ssh_pid_'   . $sessionId . '.pid';
$stopFile   = $tmp . 'ssh_stop_'  . $sessionId . '.flag';
$resizeFile = $tmp . 'ssh_rs_'    . $sessionId . '.pipe';

workerLog("Creds file: {$credsFile}");

if (!file_exists($credsFile)) {
    workerLog("Creds file not found, exiting");
    exit(1);
}

$creds = json_decode(file_get_contents($credsFile), true);
if (!$creds) {
    workerLog("Failed to parse creds JSON, exiting");
    exit(1);
}

workerLog("Creds loaded: host={$creds['host']}, port={$creds['port']}, user={$creds['username']}");

// Write our PID
file_put_contents($pidFile, getmypid());
workerLog("PID file written: " . getmypid());

// Initialize pipe files
file_put_contents($inputFile,  '');
file_put_contents($outputFile, '');

// ------- Load phpseclib -------------------------------------------------------
$phpseclibPath = __DIR__ . '/../phpseclib/phpseclib';
$paragonPath   = __DIR__ . '/../paragonie/src';

workerLog("phpseclib path: {$phpseclibPath}");
workerLog("phpseclib exists: " . (is_dir($phpseclibPath) ? 'YES' : 'NO'));

spl_autoload_register(function ($class) use ($phpseclibPath, $paragonPath) {
    if (strncmp('ParagonIE\\ConstantTime\\', $class, 23) === 0) {
        $f = $paragonPath . '/' . str_replace('\\', '/', substr($class, 23)) . '.php';
        if (file_exists($f)) require_once $f;
        return;
    }
    if (strncmp('phpseclib3\\', $class, 11) === 0) {
        $f = $phpseclibPath . '/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (file_exists($f)) require_once $f;
    }
});

// ------- Helper ---------------------------------------------------------------
function appendOut(string $data): void
{
    global $outputFile;
    $fp = fopen($outputFile, 'ab');
    if ($fp) { fwrite($fp, $data); fclose($fp); }
}

// ------- Main -----------------------------------------------------------------
try {
    workerLog("Creating SSH2 connection to {$creds['host']}:{$creds['port']}");
    $ssh = new \phpseclib3\Net\SSH2($creds['host'], (int)$creds['port'], 15);
    $ssh->setWindowSize($creds['cols'] ?? 220, $creds['rows'] ?? 50);

    workerLog("Attempting login as {$creds['username']}");
    if (!$ssh->login($creds['username'], $creds['password'])) {
        workerLog("Authentication failed!");
        appendOut("\r\n\x1b[31mAuthentication failed.\x1b[0m\r\n");
        @unlink($pidFile);
        exit(1);
    }
    workerLog("Login successful");

    // Open fully-interactive PTY shell
    workerLog("Opening shell (PTY)...");
    $ssh->openShell();
    workerLog("Shell opened successfully");

    $inputPos = 0;

    // Drain initial MOTD / prompt
    workerLog("Draining MOTD...");
    $ssh->setTimeout(3);
    while (true) {
        $chunk = $ssh->read('', \phpseclib3\Net\SSH2::READ_NEXT);
        if ($ssh->isTimeout() || $chunk === false || $chunk === true) break;
        if ($chunk !== '') appendOut($chunk);
    }
    workerLog("MOTD drained, entering main loop");

    // ── Main interactive loop ─────────────────────────────────────────────────
    $loopCount = 0;
    while (!file_exists($stopFile)) {
        $loopCount++;

        // Handle PTY resize request
        if (file_exists($resizeFile)) {
            $rd = @file_get_contents($resizeFile);
            if ($rd) {
                $r = json_decode($rd, true);
                if ($r && isset($r['cols'], $r['rows'])) {
                    $ssh->setWindowSize((int)$r['cols'], (int)$r['rows']);
                    workerLog("Resized to {$r['cols']}x{$r['rows']}");
                }
            }
            @unlink($resizeFile);
        }

        // Forward pending keystrokes to SSH shell
        clearstatcache(true, $inputFile);
        if (file_exists($inputFile)) {
            $fsz = filesize($inputFile);
            if ($fsz > $inputPos) {
                $fp = fopen($inputFile, 'rb');
                if ($fp) {
                    fseek($fp, $inputPos);
                    $raw = fread($fp, $fsz - $inputPos);
                    fclose($fp);
                    if ($raw !== false && $raw !== '') {
                        $inputPos = $fsz;
                        $ssh->write($raw);
                        if ($loopCount % 100 === 0) {
                            workerLog("Wrote " . strlen($raw) . " bytes of input");
                        }
                    }
                }
            }
        }

        // Read SSH output
        try {
            $ssh->setTimeout(0.1);
            $out = $ssh->read('', \phpseclib3\Net\SSH2::READ_NEXT);
            if (!$ssh->isTimeout() && $out !== false && $out !== true && $out !== '') {
                appendOut($out);
            }
        } catch (\Exception $re) {
            if (!$ssh->isTimeout()) {
                workerLog("Read error: " . $re->getMessage());
                appendOut("\r\n\x1b[31mRead error: " . $re->getMessage() . "\x1b[0m\r\n");
                break;
            }
        }

        usleep(30000); // 30 ms idle
    }

    workerLog("Main loop exited (loopCount={$loopCount})");

} catch (\Throwable $e) {
    workerLog("FATAL EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (isset($outputFile)) {
        appendOut("\r\n\x1b[31mWorker error: " . $e->getMessage() . "\x1b[0m\r\n");
    }
}

// Cleanup
workerLog("Cleaning up temp files");
foreach ([$pidFile, $credsFile, $inputFile, $resizeFile, $stopFile] as $f) {
    if (isset($f) && $f) @unlink($f);
}
