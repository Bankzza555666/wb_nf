<?php
// controller/xui_api/multi_xui_api.php
// ✅ FIX: Ensure this file is clean and free of syntax errors or hidden characters.
/**
 * Multi-Server 3x-ui API Class
 * รองรับการจัดการหลาย Server พร้อมกัน
 */
class MultiXUIApi
{

    private $servers = []; // เก็บข้อมูล Server ทั้งหมด
    private $conn; // Database Connection

    /**
     * Constructor
     * @param mysqli $conn Database Connection
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadServers();
    }

    /**
     * โหลดข้อมูล Server ทั้งหมดจากฐานข้อมูล
     */
    private function loadServers()
    {
        $query = "SELECT * FROM servers WHERE is_active = 1 AND server_status = 'online' ORDER BY sort_order ASC";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->servers[$row['server_id']] = $row;
        }
    }

    /**
     * ดึงข้อมูล Server ตาม ID
     * @param string $server_id Server ID
     * @return array|null
     */
    public function getServer($server_id)
    {
        return $this->servers[$server_id] ?? null;
    }

    /** 
     * ดึงข้อมูล Server ทั้งหมด
     * @return array
     */
    public function getAllServers()
    {
        return $this->servers;
    }

    /**
     * สร้าง Base URL สำหรับ Server
     * @param string $server_id Server ID
     * @return string|null
     */
    private function getBaseUrl($server_id)
    {
        $server = $this->getServer($server_id);
        if (!$server)
            return null;

        $protocol = ($server['server_port'] == 443 || $server['server_port'] == 2053) ? 'https' : 'http';
        $path = rtrim($server['server_path'], '/');

        return "{$protocol}://{$server['server_ip']}:{$server['server_port']}{$path}";
    }

    /**
     * Login เข้าสู่ Server
     * @param string $server_id Server ID
     * @return array ['success' => bool, 'cookies' => string, 'message' => string]
     */
    public function login($server_id)
    {
        $server = $this->getServer($server_id);
        if (!$server) {
            return ['success' => false, 'cookies' => null, 'message' => 'Server not found'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . '/login';

        // Enhanced Logging
        if (function_exists('writeLog')) {
            writeLog("Attempting login for server_id: {$server_id} | URL: {$url} | Username: {$server['server_username']}");
        }

        $post_data = [
            'username' => $server['server_username'],
            'password' => $server['server_password']
        ];

        $response = $this->makeRequest('POST', $url, $post_data, false, $server_id);

        if ($response['success'] && !empty($response['cookies'])) {
            return [
                'success' => true,
                'cookies' => $response['cookies'],
                'message' => 'Login successful'
            ];
        }

        return [
            'success' => false,
            'cookies' => null,
            'message' => $response['message'] ?? 'Login failed'
        ];
    }

    /**
     * ดึงข้อมูล Inbound ทั้งหมดจาก Server
     * @param string $server_id Server ID
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function getInbounds($server_id)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'data' => null, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . '/panel/api/inbounds/list';

        return $this->makeRequest('GET', $url, null, true, $server_id, $login['cookies']);
    }

    /**
     * ดึงข้อมูล Inbound เฉพาะตัว
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @return array
     */
    public function getInbound($server_id, $inbound_id)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'data' => null, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . '/panel/api/inbounds/get/' . $inbound_id;

        return $this->makeRequest('GET', $url, null, true, $server_id, $login['cookies']);
    }

    /**
     * เพิ่ม Client ใหม่
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param array $client_data ข้อมูล Client
     * @return array
     */
    public function addClient($server_id, $inbound_id, $client_data)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . '/panel/api/inbounds/addClient';

        if (isset($client_data['uuid']) && !empty($client_data['uuid'])) {
            $uuid = $client_data['uuid'];
        } else {
            $uuid = $this->generateUUID();
        }

        // Calculate expiration time (in milliseconds)
        $expire_time = isset($client_data['expire_days'])
            ? (time() + ($client_data['expire_days'] * 24 * 60 * 60)) * 1000
            : 0;

        // แปลง GB เป็น Bytes
        $total_bytes = isset($client_data['data_gb'])
            ? $client_data['data_gb'] * 1024 * 1024 * 1024
            : 0;

        // สร้าง Client Settings
        $client_settings = [
            'clients' => [
                [
                    'id' => $uuid,
                    'flow' => '',
                    'email' => $client_data['email'],
                    'limitIp' => $client_data['limit_ip'] ?? 0,
                    'totalGB' => $total_bytes,
                    'expiryTime' => $expire_time,
                    'enable' => true,
                    'tgId' => '',
                    'subId' => $this->generateRandomString(16)
                ]
            ]
        ];

        $post_data = [
            'id' => $inbound_id,
            'settings' => json_encode($client_settings)
        ];

        $response = $this->makeRequest('POST', $url, $post_data, true, $server_id, $login['cookies']);

        // เพิ่ม UUID ใน response
        if ($response['success']) {
            $response['uuid'] = $uuid;
            $response['email'] = $client_data['email'];
        }

        return $response;
    }

    /**
     * อัพเดท Client (SAFE)
     * @param string $server_id Server ID
     * @param string $client_uuid Client UUID
     * @param int $inbound_id Inbound ID
     * @param array $client_object ข้อมูล client object ทั้งหมดที่จะอัพเดท
     * @return array
     */
    public function updateClient($server_id, $client_uuid, $inbound_id, $client_object)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . '/panel/api/inbounds/updateClient/' . $client_uuid;

        // The API expects a 'clients' array with a single object
        $client_settings = [
            'clients' => [$client_object]
        ];

        $post_data = [
            'id' => $inbound_id,
            'settings' => json_encode($client_settings)
        ];

        return $this->makeRequest('POST', $url, $post_data, true, $server_id, $login['cookies']);
    }

    /**
     * ลบ Client
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param string $client_uuid Client UUID
     * @return array
     */
    public function deleteClient($server_id, $inbound_id, $client_uuid)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . "/panel/api/inbounds/{$inbound_id}/delClient/{$client_uuid}";

        return $this->makeRequest('POST', $url, [], true, $server_id, $login['cookies']);
    }

    /**
     * ดึงข้อมูล Traffic ของ Client
     * @param string $server_id Server ID
     * @param string $client_uuid Client UUID
     * @return array ['success' => bool, 'data' => array]
     */
    public function getClientTraffic($server_id, $client_uuid)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'data' => null, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        // ✅ FIX: เปลี่ยนไปใช้ getClientTrafficsById/{uuid} เพื่อความแม่นยำ
        // API: GET /panel/api/inbounds/getClientTrafficsById/{uuid}
        $url = $base_url . '/panel/api/inbounds/getClientTrafficsById/' . urlencode($client_uuid);

        return $this->makeRequest('GET', $url, null, true, $server_id, $login['cookies']);
    }

    /**
     * รีเซ็ต Traffic ของ Client
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param string $client_email Client Email
     * @return array
     */
    public function resetClientTraffic($server_id, $inbound_id, $client_email)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'message' => 'Login failed'];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . "/panel/api/inbounds/{$inbound_id}/resetClientTraffic/" . urlencode($client_email);

        return $this->makeRequest('POST', $url, [], true, $server_id, $login['cookies']);
    }

    /**
     * สร้าง Config URL
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param string $client_uuid Client UUID
     * @param string $client_email Client Email
     * @return string|null
     */
    public function generateConfigUrl($server_id, $inbound_id, $client_uuid, $client_email)
    {
        // ดึงข้อมูล Inbound
        $inbound_result = $this->getInbound($server_id, $inbound_id);
        if (!$inbound_result['success']) {
            return null;
        }

        $inbound = $inbound_result['data']['obj'] ?? null;
        if (!$inbound)
            return null;

        // ดึงข้อมูล Price Profile
        $stmt = $this->conn->prepare("SELECT * FROM price_v2 WHERE server_id = ? AND inbound_id = ? LIMIT 1");
        $stmt->bind_param("si", $server_id, $inbound_id);
        $stmt->execute();
        $price = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$price)
            return null;

        $protocol = $price['protocol'];
        $host = $price['host'];
        $port = $price['port'];
        $network = $price['network'];
        $security = $price['security'];

        // สร้าง URL ตาม Protocol
        switch ($protocol) {
            case 'vless':
                return "vless://{$client_uuid}@{$host}:{$port}?type={$network}&security={$security}&sni={$host}#{$client_email}";

            case 'vmess':
                $config = [
                    'v' => '2',
                    'ps' => $client_email,
                    'add' => $host,
                    'port' => (string) $port,
                    'id' => $client_uuid,
                    'aid' => '0',
                    'net' => $network,
                    'type' => 'none',
                    'host' => $host,
                    'path' => '/',
                    'tls' => $security
                ];
                return 'vmess://' . base64_encode(json_encode($config));

            case 'trojan':
                return "trojan://{$client_uuid}@{$host}:{$port}?type={$network}&security={$security}&sni={$host}#{$client_email}";

            default:
                return null;
        }
    }

    /**
     * ทำคำขอ HTTP
     */
    private function makeRequest($method, $url, $data = null, $use_auth = false, $server_id = null, $cookies = null)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        if ($use_auth && $cookies) {
            $headers[] = 'Cookie: ' . $cookies;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_error = curl_error($ch);

        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($ch);

        // ดึง Cookies
        $response_cookies = '';
        if (preg_match_all('/Set-Cookie: (.*?);/i', $header, $matches)) {
            $response_cookies = implode('; ', $matches[1]);
        }

        $json_response = json_decode($body, true);

        // ✅ FIX: ตรวจสอบ success field จาก 3x-ui API response, ไม่ใช่แค่ HTTP status
        // 3x-ui API มักจะ return HTTP 200 แม้ว่าการดำเนินการจะล้มเหลว แต่จะมี {"success": false} ใน body
        $api_success = isset($json_response['success']) ? $json_response['success'] : true;

        if ($http_code >= 200 && $http_code < 300 && $api_success) {
            return [
                'success' => true,
                'data' => $json_response,
                'cookies' => $response_cookies,
                'message' => $json_response['msg'] ?? 'Success'
            ];
        } else if ($http_code >= 200 && $http_code < 300 && !$api_success) {
            // HTTP success but API reports failure
            if (function_exists('writeLog')) {
                writeLog("3x-ui API returned success=false for URL: {$url}");
                writeLog("API Error message: " . ($json_response['msg'] ?? 'Unknown'));
            }
            return [
                'success' => false,
                'data' => $json_response,
                'cookies' => $response_cookies,
                'message' => $json_response['msg'] ?? 'API operation failed'
            ];
        } else {
            // Enhanced Logging for failures
            if (function_exists('writeLog')) {
                writeLog("API Request Failed for URL: {$url}");
                if (!empty($curl_error)) {
                    writeLog("cURL Error: " . $curl_error);
                }
                writeLog("HTTP Status Code: " . $http_code);
                writeLog("Response Body: " . $body);
            }
            return [
                'success' => false,
                'data' => $json_response,
                'cookies' => $response_cookies,
                // ✅ FIX: เพิ่มรายละเอียด Error ให้ชัดเจนขึ้น
                'message' => 'API Request Failed. HTTP Code: ' . $http_code .
                    '. cURL Error: ' . ($curl_error ?: 'None') .
                    '. URL: ' . $url .
                    '. Response: ' . substr($body, 0, 200)
            ];
        }
    }

    /**
     * สร้าง UUID
     */
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * สร้าง Random String
     */
    private function generateRandomString($length = 16)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $string;
    }

    /**
     * สร้าง Config URL จาก Template
     */
    public function generateConfigFromTemplate($template, $data)
    {
        $replacements = [
            '{UUID}' => $data['uuid'] ?? '',
            '{EMAIL}' => $data['email'] ?? '',
            '{HOST}' => $data['host'] ?? '',
            '{PORT}' => $data['port'] ?? '',
            '{SNI}' => $data['sni'] ?? $data['host'],
            '{NETWORK}' => $data['network'] ?? 'tcp',
            '{PATH}' => $data['path'] ?? '/',
            '{PUBLIC_KEY}' => $data['public_key'] ?? '',
            '{SHORT_ID}' => $data['short_id'] ?? '',
            '{CUSTOM_NAME}' => $data['custom_name'] ?? $data['email'] ?? '',
        ];

        // สำหรับ VMess
        if (strpos($template, '{VMESS_JSON}') !== false) {
            $vmess_config = [
                'v' => '2',
                'ps' => $data['email'],
                'add' => $data['host'],
                'port' => $data['port'],
                'id' => $data['uuid'],
                'aid' => '0',
                'net' => $data['network'] ?? 'tcp',
                'type' => 'none',
                'host' => $data['host'],
                'path' => $data['path'] ?? '/',
                'tls' => $data['security'] == 'TLS' ? 'tls' : '',
                'sni' => $data['sni'] ?? $data['host']
            ];
            $vmess_json = base64_encode(json_encode($vmess_config));
            $replacements['{VMESS_JSON}'] = $vmess_json;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * ดึงข้อมูล Client ทั้งหมดใน Inbound
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @return array|null
     */
    public function getClients($server_id, $inbound_id)
    {
        $inbound_result = $this->getInbound($server_id, $inbound_id);

        if (!$inbound_result['success'] || !isset($inbound_result['data']['obj']['settings'])) {
            return ['success' => false, 'clients' => null, 'message' => 'Failed to retrieve inbound data.'];
        }

        $settings = json_decode($inbound_result['data']['obj']['settings'], true);

        if (!isset($settings['clients'])) {
            return ['success' => false, 'clients' => null, 'message' => 'No clients found in settings.'];
        }

        return ['success' => true, 'clients' => $settings['clients'], 'message' => 'Clients retrieved successfully.'];
    }

    /**
     * ต่ออายุ Client
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param string $client_email Client Email
     * @param int $days จำนวนวันที่จะเพิ่ม
     * @return array
     */
    public function extendClientExpiry($server_id, $inbound_id, $client_email, $days)
    {
        $clients_response = $this->getClients($server_id, $inbound_id);
        if (!$clients_response['success']) {
            return ['success' => false, 'message' => $clients_response['message']];
        }

        $client = null;
        foreach ($clients_response['clients'] as $c) {
            if (isset($c['email']) && $c['email'] == $client_email) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $current_expiry_ms = $client['expiryTime'];
        $now_ms = time() * 1000;

        $base_expiry_ms = ($current_expiry_ms > $now_ms) ? $current_expiry_ms : $now_ms;
        $new_expiry_ms = $base_expiry_ms + ($days * 86400 * 1000);

        $client['expiryTime'] = $new_expiry_ms;

        return $this->updateClient($server_id, $client['id'], $inbound_id, $client);
    }

    /**
     * ตั้งค่า Data Limit لل Client
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param string $client_email Client Email
     * @param int $new_total_bytes จำนวน data ทั้งหมด (bytes)
     * @return array
     */
    public function setClientDataLimit($server_id, $inbound_id, $client_email, $new_total_bytes)
    {
        $clients_response = $this->getClients($server_id, $inbound_id);
        if (!$clients_response['success']) {
            return ['success' => false, 'message' => $clients_response['message']];
        }

        $client = null;
        foreach ($clients_response['clients'] as $c) {
            if (isset($c['email']) && $c['email'] == $client_email) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        // ✅ FIX: 3x-ui API ใช้ 'totalGB' ไม่ใช่ 'total'
        $client['totalGB'] = $new_total_bytes;

        return $this->updateClient($server_id, $client['id'], $inbound_id, $client);
    }

    /**
     * อัพเดท IP Limit (จำนวนเครื่อง)
     * @param string $server_id Server ID
     * @param int $inbound_id Inbound ID
     * @param string $client_email Client Email
     * @param int $new_ip_limit จำนวน IP Limit ใหม่
     * @return array
     */
    public function updateClientIPLimit($server_id, $inbound_id, $client_email, $new_ip_limit)
    {
        $clients_response = $this->getClients($server_id, $inbound_id);
        if (!$clients_response['success']) {
            return ['success' => false, 'message' => $clients_response['message']];
        }

        $client = null;
        foreach ($clients_response['clients'] as $c) {
            if (isset($c['email']) && $c['email'] == $client_email) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $client['limitIp'] = $new_ip_limit;

        return $this->updateClient($server_id, $client['id'], $inbound_id, $client);
    }

    /**
     * ดึงรายชื่อ Client ที่กำลัง Online
     * @param string $server_id Server ID
     * @return array
     */
    public function getOnlineClients($server_id)
    {
        $login = $this->login($server_id);
        if (!$login['success']) {
            return ['success' => false, 'message' => 'Login failed', 'data' => null];
        }

        $base_url = $this->getBaseUrl($server_id);
        $url = $base_url . '/panel/api/inbounds/onlines';

        $response = $this->makeRequest('GET', $url, null, true, $server_id, $login['cookies']);

        if ($response['success'] && isset($response['data']['obj'])) {
            return ['success' => true, 'message' => 'Online clients retrieved.', 'data' => $response['data']['obj']];
        }

        return ['success' => false, 'message' => $response['message'] ?? 'Failed to get online clients.', 'data' => null];
    }
}

/**
 * ตัวอย่างการใช้งาน:
 * 
 * $api = new MultiXUIApi($conn);
 * 
 * // ดึงข้อมูล Server ทั้งหมด
 * $servers = $api->getAllServers();
 * 
 * // Login
 * $login = $api->login('sg-01');
 * 
 * // เพิ่ม Client
 * $client_data = [
 *     'email' => 'user@example.com',
 *     'expire_days' => 30,
 *     'data_gb' => 100,
 *     'limit_ip' => 2
 * ];
 * $result = $api->addClient('sg-01', 1, $client_data);
 * 
 * // ดึง Traffic
 * $traffic = $api->getClientTraffic('sg-01', 'user@example.com');
 * 
 * // สร้าง Config URL
 * $config_url = $api->generateConfigUrl('sg-01', 1, $uuid, 'user@example.com');
 */
?>