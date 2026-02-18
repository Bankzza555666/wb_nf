<?php
/**
 * Image Upload Controller
 * จัดการอัปโหลดและเลือกรูปสำหรับ Products
 */

session_start();
require_once 'admin_config.php';
checkAdminAuth();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'upload_image':
        uploadImage();
        break;
    case 'get_images':
        getImages();
        break;
    case 'delete_image':
        deleteImage($_POST['id'] ?? 0);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function uploadImage()
{
    global $conn;

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์หรือเกิดข้อผิดพลาดในการอัปโหลด']);
        return;
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง (รองรับ jpg, png, gif, webp)']);
        return;
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'ไฟล์ใหญ่เกินไป (สูงสุด 5MB)']);
        return;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadDir = realpath(__DIR__ . '/../../img/products') . DIRECTORY_SEPARATOR;
    $uploadPath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้']);
        return;
    }

    // Save to database
    $originalName = $file['name'];
    $stmt = $conn->prepare("INSERT INTO product_images (filename, original_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $filename, $originalName);

    if ($stmt->execute()) {
        $imageId = $conn->insert_id;
        
        // Debug: log the insert
        file_put_contents(__DIR__ . '/../../logs/image_upload.txt', 
            date('[Y-m-d H:i:s] ') . "Uploaded: ID=$imageId, file=$filename\n", FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'อัปโหลดสำเร็จ (ID: ' . $imageId . ')',
            'data' => [
                'id' => $imageId,
                'filename' => $filename,
                'original_name' => $originalName,
                'url' => 'img/products/' . $filename
            ]
        ]);
    } else {
        // Delete the uploaded file if database insert fails
        @unlink($uploadPath);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
    }
}

function getImages()
{
    global $conn;

    // ตรวจสอบ connection
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    $result = $conn->query("SELECT * FROM product_images ORDER BY uploaded_at DESC");
    
    // ตรวจสอบ query error
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
        return;
    }
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $row['url'] = 'img/products/' . $row['filename'];
        $images[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $images, 'count' => count($images)]);
}

function deleteImage($id)
{
    global $conn;

    // Check if image is in use
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM ssh_products WHERE image_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ มีแพ็กเกจที่ใช้รูปนี้อยู่']);
        return;
    }

    // Get filename
    $stmt = $conn->prepare("SELECT filename FROM product_images WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $image = $stmt->get_result()->fetch_assoc();

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรูปภาพ']);
        return;
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM product_images WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Delete file
        $filePath = realpath(__DIR__ . '/../../img/products') . DIRECTORY_SEPARATOR . $image['filename'];
        @unlink($filePath);
        echo json_encode(['success' => true, 'message' => 'ลบรูปสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
    }
}
