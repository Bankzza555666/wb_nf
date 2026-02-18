<?php
/**
 * Editable Content Helper
 * ระบบแก้ไขเนื้อหาถาวรผ่าน DB
 * 
 * วิธีใช้ในไฟล์ PHP:
 *   <?php echo editableText('hero_title', 'ข้อความเริ่มต้น'); ?>
 *   <?php echo editableHtml('hero_content', '<p>HTML เริ่มต้น</p>'); ?>
 *   <img src="<?php echo editableImage('hero_image', 'img/default.png'); ?>">
 */

// โหลด config ถ้ายังไม่มี $conn
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * ดึงข้อความที่แก้ไขได้จาก DB
 * @param string $key - key ของข้อความ (ไม่ซ้ำกัน)
 * @param string $default - ข้อความเริ่มต้นถ้ายังไม่มีใน DB
 * @param string $page - หน้าที่ใช้ (ถ้าไม่ระบุจะใช้ '*' = ทุกหน้า)
 * @return string
 */
function editableText($key, $default = '', $page = '*') {
    global $conn;
    if (!$conn) return htmlspecialchars($default);
    
    static $cache = [];
    $cacheKey = $page . '::' . $key;
    
    if (isset($cache[$cacheKey])) {
        return htmlspecialchars($cache[$cacheKey]);
    }
    
    try {
        $stmt = $conn->prepare("SELECT custom_text FROM site_texts WHERE text_key = ? AND (page_path = ? OR page_path = '*') ORDER BY (page_path = ?) DESC LIMIT 1");
        $stmt->bind_param("sss", $key, $page, $page);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cache[$cacheKey] = $row['custom_text'];
            $stmt->close();
            return htmlspecialchars($row['custom_text']);
        }
        $stmt->close();
    } catch (Exception $e) {}
    
    return htmlspecialchars($default);
}

/**
 * ดึง HTML ที่แก้ไขได้จาก DB (ไม่ escape)
 * @param string $key - key ของ HTML
 * @param string $default - HTML เริ่มต้น
 * @param string $page - หน้าที่ใช้
 * @return string
 */
function editableHtml($key, $default = '', $page = '*') {
    global $conn;
    if (!$conn) return $default;
    
    static $cache = [];
    $cacheKey = $page . '::html:' . $key;
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    try {
        $htmlKey = 'html:' . $key;
        $stmt = $conn->prepare("SELECT custom_text FROM site_texts WHERE text_key = ? AND (page_path = ? OR page_path = '*') ORDER BY (page_path = ?) DESC LIMIT 1");
        $stmt->bind_param("sss", $htmlKey, $page, $page);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cache[$cacheKey] = $row['custom_text'];
            $stmt->close();
            return $row['custom_text'];
        }
        $stmt->close();
    } catch (Exception $e) {}
    
    return $default;
}

/**
 * ดึง URL รูปภาพที่แก้ไขได้จาก DB
 * @param string $key - key ของรูป
 * @param string $default - URL รูปเริ่มต้น
 * @param string $page - หน้าที่ใช้
 * @return string
 */
function editableImage($key, $default = '', $page = '*') {
    global $conn;
    if (!$conn) return htmlspecialchars($default);
    
    static $cache = [];
    $cacheKey = $page . '::img:' . $key;
    
    if (isset($cache[$cacheKey])) {
        return htmlspecialchars($cache[$cacheKey]);
    }
    
    try {
        $imgKey = 'img:' . $key;
        $stmt = $conn->prepare("SELECT custom_text FROM site_texts WHERE text_key = ? AND (page_path = ? OR page_path = '*') ORDER BY (page_path = ?) DESC LIMIT 1");
        $stmt->bind_param("sss", $imgKey, $page, $page);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cache[$cacheKey] = $row['custom_text'];
            $stmt->close();
            return htmlspecialchars($row['custom_text']);
        }
        $stmt->close();
    } catch (Exception $e) {}
    
    return htmlspecialchars($default);
}

/**
 * ดึงสไตล์ CSS ที่แก้ไขได้จาก DB
 * @param string $key - key ของสไตล์ (หรือ selector)
 * @param string $property - CSS property
 * @param string $default - ค่าเริ่มต้น
 * @param string $page - หน้าที่ใช้
 * @return string
 */
function editableStyle($key, $property, $default = '', $page = '*') {
    global $conn;
    if (!$conn) return $default;
    
    try {
        $stmt = $conn->prepare("SELECT property_value FROM site_customizations WHERE selector = ? AND property_name = ? AND (page_path = ? OR page_path = '*') ORDER BY (page_path = ?) DESC LIMIT 1");
        $stmt->bind_param("ssss", $key, $property, $page, $page);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['property_value'];
        }
        $stmt->close();
    } catch (Exception $e) {}
    
    return $default;
}

/**
 * สร้าง wrapper HTML สำหรับ element ที่แก้ไขได้
 * จะมี data-editable attribute ให้ Visual Editor รู้จัก
 * @param string $key - key ของ element
 * @param string $content - เนื้อหาเริ่มต้น
 * @param string $tag - HTML tag (default: span)
 * @param string $page - หน้าที่ใช้
 * @return string
 */
function editable($key, $content, $tag = 'span', $page = '*') {
    $text = editableText($key, $content, $page);
    $safeKey = htmlspecialchars($key);
    return "<{$tag} data-editable=\"{$safeKey}\">{$text}</{$tag}>";
}

/**
 * สร้าง wrapper สำหรับ HTML ที่แก้ไขได้
 */
function editableBlock($key, $content, $tag = 'div', $page = '*') {
    $html = editableHtml($key, $content, $page);
    $safeKey = htmlspecialchars($key);
    return "<{$tag} data-editable-html=\"{$safeKey}\">{$html}</{$tag}>";
}
