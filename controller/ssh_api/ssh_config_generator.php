<?php
/**
 * SSH/NPV Config Generator
 * สร้าง Config URLs จาก templates
 * 
 * @version 1.0
 */

class SSHConfigGenerator
{

    /**
     * Generate NetMod Config URL
     * แทนค่า placeholders ใน template
     * 
     * @param string $template NetMod Config template
     * @param string $username SSH username
     * @param string $password SSH password
     * @param string $customName Custom name จาก user
     * @return string Generated config URL
     */
    public static function generateSSHConfig($template, $username, $password, $customName = '')
    {
        $customName = $customName ?: $username;

        $replacements = [
            '{username}' => $username,
            '{password}' => $password,
            '{CUSTOM_NAME}' => $customName,
            '{CUSTOM _NAME}' => $customName,
            '{USER}' => $username,
            '{PASS}' => $password,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Generate NPV Config URL
     * Decode Base64 JSON -> แก้ไข credentials -> Encode กลับ
     * 
     * @param string $template NPV config template (Base64 หรือ URL)
     * @param string $username SSH username
     * @param string $password SSH password
     * @param string $customName Custom name
     * @return string Generated NPV config URL
     */
    public static function generateNPVConfig($template, $username, $password, $customName = '')
    {
        $customName = $customName ?: $username;

        // ตรวจสอบว่าเป็น npvt-ssh:// URL หรือไม่
        if (strpos($template, 'npvt-ssh://') === 0) {
            return self::processNPVTUrl($template, $username, $password, $customName);
        }

        // ถ้าเป็น Base64 JSON โดยตรง
        return self::processBase64Json($template, $username, $password, $customName);
    }

    /**
     * Process npvt-ssh:// URL format
     */
    private static function processNPVTUrl($url, $username, $password, $customName)
    {
        // แยก prefix ออก
        $base64Part = str_replace('npvt-ssh://', '', $url);

        // Decode Base64
        $jsonString = base64_decode($base64Part);

        if ($jsonString === false) {
            // ถ้า decode ไม่ได้ ให้ทำ simple replacement
            return self::simpleReplace($url, $username, $password, $customName);
        }

        // Parse JSON
        $config = json_decode($jsonString, true);

        if ($config === null) {
            return self::simpleReplace($url, $username, $password, $customName);
        }

        // แก้ไข credentials
        $config['sshUsername'] = $username;
        $config['sshPassword'] = $password;
        $config['remarks'] = $customName;

        // Encode กลับเป็น Base64
        $newJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $newBase64 = base64_encode($newJson);

        return 'npvt-ssh://' . $newBase64;
    }

    /**
     * Process raw Base64 JSON
     */
    private static function processBase64Json($template, $username, $password, $customName)
    {
        $jsonString = base64_decode($template);

        if ($jsonString === false) {
            return self::simpleReplace($template, $username, $password, $customName);
        }

        $config = json_decode($jsonString, true);

        if ($config === null) {
            return self::simpleReplace($template, $username, $password, $customName);
        }

        // แก้ไข credentials
        if (isset($config['sshUsername'])) {
            $config['sshUsername'] = $username;
        }
        if (isset($config['sshPassword'])) {
            $config['sshPassword'] = $password;
        }
        if (isset($config['remarks'])) {
            $config['remarks'] = $customName;
        }

        $newJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return base64_encode($newJson);
    }

    /**
     * Simple string replacement fallback
     */
    private static function simpleReplace($template, $username, $password, $customName)
    {
        $replacements = [
            '{username}' => $username,
            '{password}' => $password,
            '{CUSTOM_NAME}' => $customName,
            '{CUSTOM _NAME}' => $customName,
            '{USER}' => $username,
            '{PASS}' => $password,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Update custom name in existing configs
     * 
     * @param string $sshConfig Current NetMod Config
     * @param string $npvConfig Current NPV config
     * @param string $oldName Old custom name
     * @param string $newName New custom name
     * @return array ['ssh' => newSSH, 'npv' => newNPV]
     */
    public static function updateCustomName($sshConfig, $npvConfig, $oldName, $newName)
    {
        // Update NetMod Config (แทนที่ # suffix)
        $newSSH = preg_replace('/#[^#]*$/', '#' . $newName, $sshConfig);

        // Update NPV config
        $newNPV = self::updateNPVCustomName($npvConfig, $newName);

        return [
            'ssh' => $newSSH,
            'npv' => $newNPV
        ];
    }

    /**
     * Update custom name in NPV config
     */
    private static function updateNPVCustomName($npvConfig, $newName)
    {
        // ถ้าเป็น npvt-ssh:// format
        if (strpos($npvConfig, 'npvt-ssh://') === 0) {
            $base64Part = str_replace('npvt-ssh://', '', $npvConfig);
            $jsonString = base64_decode($base64Part);

            if ($jsonString !== false) {
                $config = json_decode($jsonString, true);

                if ($config !== null) {
                    $config['remarks'] = $newName;
                    $newJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    return 'npvt-ssh://' . base64_encode($newJson);
                }
            }
        }

        return $npvConfig;
    }

    /**
     * Validate NetMod Config template
     */
    public static function validateSSHTemplate($template)
    {
        $errors = [];

        if (empty($template)) {
            $errors[] = 'Template ว่างเปล่า';
        }

        if (strpos($template, 'ssh://') !== 0) {
            $errors[] = 'Template ควรเริ่มต้นด้วย ssh://';
        }

        if (strpos($template, '{username}') === false && strpos($template, '{USER}') === false) {
            $errors[] = 'Template ต้องมี {username} หรือ {USER} placeholder';
        }

        if (strpos($template, '{password}') === false && strpos($template, '{PASS}') === false) {
            $errors[] = 'Template ต้องมี {password} หรือ {PASS} placeholder';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate NPV config template
     */
    public static function validateNPVTemplate($template)
    {
        $errors = [];

        if (empty($template)) {
            $errors[] = 'Template ว่างเปล่า';
        }

        // ถ้าเป็น npvt-ssh:// format
        if (strpos($template, 'npvt-ssh://') === 0) {
            $base64Part = str_replace('npvt-ssh://', '', $template);
            $jsonString = base64_decode($base64Part);

            if ($jsonString === false) {
                $errors[] = 'ไม่สามารถ decode Base64 ได้';
            } else {
                $config = json_decode($jsonString, true);
                if ($config === null) {
                    $errors[] = 'JSON format ไม่ถูกต้อง';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
