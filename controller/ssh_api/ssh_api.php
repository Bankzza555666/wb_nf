<?php
/**
 * SSH Plus Manager API Client
 * สำหรับเชื่อมต่อกับ SSH Plus Manager Script บน Linux Server
 * 
 * @version 1.0
 */

class SSHPlusManagerAPI
{
    private $host;
    private $sshPort;
    private $apiPort;
    private $apiPath;
    private $adminUser;
    private $adminPass;
    private $conn; // Database connection

    /**
     * Constructor
     * @param array $server Server config from database
     * @param mysqli $conn Database connection
     */
    public function __construct($server, $conn = null)
    {
        $this->host = $server['server_host'];
        $this->sshPort = $server['ssh_port'] ?? 22;
        $this->apiPort = $server['api_port'] ?? 80;
        $this->apiPath = $server['api_path'] ?? '/';
        $this->adminUser = $server['admin_user'];
        $this->adminPass = $server['admin_pass'];
        $this->conn = $conn;
    }

    /**
     * สร้าง SSH User ใหม่
     * @param string $username Username ที่ต้องการสร้าง
     * @param string $password Password
     * @param int $days จำนวนวัน
     * @param int $limit จำกัดการเชื่อมต่อ (default 1)
     * @return array ['success' => bool, 'message' => string]
     */
    public function createUser($username, $password, $days, $limit = 1)
    {
        try {
            // ✅ Development Mode - จำลองการสร้าง user สำหรับทดสอบบน Windows
            if (defined('SSH_DEV_MODE') && SSH_DEV_MODE === true) {
                error_log("[SSH DEV MODE] Simulating user creation: {$username}");
                return [
                    'success' => true,
                    'message' => "[DEV] สร้าง user {$username} สำเร็จ (จำลอง)",
                    'dev_mode' => true,
                    'data' => [
                        'username' => $username,
                        'password' => $password,
                        'days' => $days,
                        'limit' => $limit
                    ]
                ];
            }

            // สร้าง command สำหรับ SSH Plus Manager
            // รูปแบบคำสั่งจะแตกต่างกันตาม script version
            // ตัวอย่าง: useradd หรือ script command

            $command = $this->buildCreateUserCommand($username, $password, $days, $limit);
            $result = $this->executeSSHCommand($command);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => "สร้าง user {$username} สำเร็จ",
                    'data' => [
                        'username' => $username,
                        'password' => $password,
                        'days' => $days,
                        'limit' => $limit
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'ไม่สามารถสร้าง user ได้'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ลบ SSH User
     * @param string $username Username ที่ต้องการลบ
     * @return array
     */
    public function deleteUser($username)
    {
        try {
            $command = $this->buildDeleteUserCommand($username);
            $result = $this->executeSSHCommand($command);

            return [
                'success' => $result['success'],
                'message' => $result['success'] ? "ลบ user {$username} สำเร็จ" : 'ไม่สามารถลบ user ได้'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ต่ออายุ User
     * @param string $username
     * @param int $days จำนวนวันที่ต้องการเพิ่ม
     * @param string|null $newExpireDate วันหมดอายุใหม่ (Y-m-d format) - ถ้าไม่ระบุจะคำนวณจากวันนี้
     * @return array
     */
    public function extendUser($username, $days, $newExpireDate = null)
    {
        try {
            // ✅ Development Mode - จำลองการต่ออายุ user
            if (defined('SSH_DEV_MODE') && SSH_DEV_MODE === true) {
                error_log("[SSH DEV MODE] Simulating user extend: {$username} +{$days} days");
                return [
                    'success' => true,
                    'message' => "[DEV] ต่ออายุ {$username} + {$days} วัน สำเร็จ (จำลอง)",
                    'dev_mode' => true
                ];
            }

            $command = $this->buildExtendUserCommand($username, $days, $newExpireDate);
            error_log("[SSH EXTEND] Command: {$command}"); // Debug log

            $result = $this->executeSSHCommand($command);

            // Log the result for debugging
            error_log("[SSH EXTEND] Result: " . json_encode($result));

            $errDetail = $result['message'] ?? null;
            if (($errDetail === null || $errDetail === '') && !$result['success']) {
                $out = trim((string) ($result['output'] ?? ''));
                $code = $result['code'] ?? null;
                $errDetail = $out !== '' ? $out : ($code !== null ? "Exit code {$code}" : 'Unknown error');
            }

            return [
                'success' => $result['success'],
                'message' => $result['success']
                    ? "ต่ออายุ {$username} + {$days} วัน สำเร็จ"
                    : ('ไม่สามารถต่ออายุได้: ' . ($errDetail ?: 'Unknown error')),
                'output' => $result['output'] ?? null
            ];
        } catch (Exception $e) {
            error_log("[SSH EXTEND] Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ดูข้อมูล User
     * @param string $username
     * @return array
     */
    public function getUserInfo($username)
    {
        try {
            $command = "chage -l {$username} 2>/dev/null";
            $result = $this->executeSSHCommand($command);

            if ($result['success'] && !empty($result['output'])) {
                return [
                    'success' => true,
                    'data' => $this->parseUserInfo($result['output'])
                ];
            }

            return [
                'success' => false,
                'message' => 'ไม่พบ user หรือไม่สามารถดึงข้อมูลได้'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ตรวจสอบการเชื่อมต่อ Server
     * @return array
     */
    public function testConnection()
    {
        try {
            $command = "echo 'connected'";
            $result = $this->executeSSHCommand($command);

            return [
                'success' => $result['success'] && strpos($result['output'] ?? '', 'connected') !== false,
                'message' => $result['success'] ? 'เชื่อมต่อสำเร็จ' : 'ไม่สามารถเชื่อมต่อได้'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute arbitrary command on SSH server (public wrapper for web terminal)
     * @param string $command
     * @return array
     */
    public function executeCommand($command)
    {
        try {
            return $this->executeSSHCommand($command);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * Execute SSH Command
     * @param string $command
     * @return array
     */
    private function executeSSHCommand($command)
    {
        // Method 1: ใช้ phpseclib (Pure PHP - works on Windows!)
        $phpseclibPath = __DIR__ . '/../phpseclib/phpseclib/Net/SSH2.php';
        if (file_exists($phpseclibPath)) {
            return $this->executeViaPhpseclib($command);
        }

        // Method 2: ใช้ ssh2 extension (ถ้ามี)
        if (function_exists('ssh2_connect')) {
            return $this->executeViaSSH2($command);
        }

        // Method 3: ใช้ exec (ต้องมี ssh client)
        return $this->executeViaExec($command);
    }

    /**
     * Execute via phpseclib (Pure PHP SSH)
     */
    private function executeViaPhpseclib($command)
    {
        // Autoload paths
        $phpseclibPath = __DIR__ . '/../phpseclib/phpseclib';
        $paragonPath = __DIR__ . '/../paragonie/src';

        // Combined autoloader for phpseclib and ParagonIE
        spl_autoload_register(function ($class) use ($phpseclibPath, $paragonPath) {
            // ParagonIE\ConstantTime namespace => paragonie/src/
            if (strncmp('ParagonIE\\ConstantTime\\', $class, 23) === 0) {
                $relative_class = substr($class, 23); // Remove 'ParagonIE\ConstantTime\'
                $file = $paragonPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }

            // phpseclib3 namespace
            if (strncmp('phpseclib3\\', $class, 11) === 0) {
                $relative_class = substr($class, 11);
                $file = $phpseclibPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });

        try {
            $ssh = new \phpseclib3\Net\SSH2($this->host, $this->sshPort, 10); // 10 second timeout
            $ssh->setTimeout(10); // Command timeout

            if (!$ssh->login($this->adminUser, $this->adminPass)) {
                return ['success' => false, 'message' => 'Authentication failed: ' . $ssh->getLastError()];
            }

            $output = $ssh->exec($command);
            $exitStatus = $ssh->getExitStatus();

            return [
                'success' => ($exitStatus === 0 || $exitStatus === false),
                'output' => $output,
                'code' => $exitStatus
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'SSH Error: ' . $e->getMessage()];
        }
    }

    /**
     * Execute via SSH2 extension
     */
    private function executeViaSSH2($command)
    {
        $connection = @ssh2_connect($this->host, $this->sshPort);

        if (!$connection) {
            return ['success' => false, 'message' => 'Cannot connect to server'];
        }

        if (!@ssh2_auth_password($connection, $this->adminUser, $this->adminPass)) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        $stream = ssh2_exec($connection, $command);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [
            'success' => true,
            'output' => $output
        ];
    }

    /**
     * Execute via system exec (fallback)
     */
    private function executeViaExec($command)
    {
        // สร้าง SSH command พร้อม sshpass
        $sshCommand = sprintf(
            'sshpass -p %s ssh -o StrictHostKeyChecking=no -p %d %s@%s "%s" 2>&1',
            escapeshellarg($this->adminPass),
            $this->sshPort,
            escapeshellarg($this->adminUser),
            escapeshellarg($this->host),
            $command
        );

        $output = [];
        $returnCode = 0;
        exec($sshCommand, $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'code' => $returnCode
        ];
    }

    /**
     * Build create user command for SSH Plus Manager
     */
    private function buildCreateUserCommand($username, $password, $days, $limit = 1)
    {
        // คำนวณวันหมดอายุ
        $expireDate = date('Y-m-d', strtotime("+{$days} days"));

        // คำสั่งสำหรับ SSH Plus Manager แบบมาตรฐาน
        // ปรับแต่งตาม script ที่ใช้จริง
        $commands = [
            // สร้าง user
            "useradd -e {$expireDate} -s /bin/false -M {$username}",
            // ตั้ง password
            "echo '{$username}:{$password}' | chpasswd",
            // จำกัด session (optional)
            "echo '{$username} hard maxlogins {$limit}' >> /etc/security/limits.conf"
        ];

        return implode(' && ', $commands);
    }

    /**
     * Build delete user command
     */
    private function buildDeleteUserCommand($username)
    {
        return "userdel -r {$username} 2>/dev/null; pkill -u {$username} 2>/dev/null";
    }

    /**
     * Build extend user command
     * @param string $username
     * @param int $days
     * @param string|null $newExpireDate วันหมดอายุใหม่ (Y-m-d format)
     */
    private function buildExtendUserCommand($username, $days, $newExpireDate = null)
    {
        // ใช้วันที่ที่ส่งมา หรือคำนวณจากวันนี้ถ้าไม่ได้ระบุ
        if ($newExpireDate) {
            // ถ้าเป็น datetime format ให้แปลงเป็น date only
            $expire = date('Y-m-d', strtotime($newExpireDate));
        } else {
            $expire = date('Y-m-d', strtotime("+{$days} days"));
        }
        // 2>&1 เพื่อให้ข้อความ error จาก chage ไปที่ output (จะได้แสดงแทน "Unknown error")
        return "chage -E {$expire} {$username} 2>&1";
    }

    /**
     * Parse user info from chage output
     */
    private function parseUserInfo($output)
    {
        $info = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $info[trim($key)] = trim($value);
            }
        }

        return $info;
    }

    // ========================================
    // Static Helper Methods
    // ========================================

    /**
     * Generate random username
     */
    public static function generateUsername($prefix = 'nf')
    {
        return $prefix . '_' . strtolower(bin2hex(random_bytes(4)));
    }

    /**
     * Generate random password
     */
    public static function generatePassword($length = 8)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
}
