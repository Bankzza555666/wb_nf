<?php
// controller/admin_controller/admin_topup_api.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Check Admin Auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$chk = $conn->query("SELECT role FROM users WHERE id = {$_SESSION['user_id']}");
$user = $chk->fetch_assoc();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// 1. GET ALL PACKAGES
if ($action === 'get_packages') {
    $sql = "SELECT * FROM topup_packages ORDER BY sort_order ASC";
    $result = $conn->query($sql);
    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
    echo json_encode(['success' => true, 'packages' => $packages]);
}

// 2. ADD PACKAGE
elseif ($action === 'add_package') {
    $amount = floatval($_POST['amount'] ?? 0);
    $bonus = floatval($_POST['bonus'] ?? 0);
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $sort = intval($_POST['sort_order'] ?? 0);

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO topup_packages (amount, bonus, is_popular, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ddii", $amount, $bonus, $is_popular, $sort);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

// 3. EDIT PACKAGE
elseif ($action === 'update_package') {
    $id = intval($_POST['id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $bonus = floatval($_POST['bonus'] ?? 0);
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $sort = intval($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($id <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Data']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE topup_packages SET amount=?, bonus=?, is_popular=?, sort_order=?, is_active=? WHERE id=?");
    $stmt->bind_param("ddiiii", $amount, $bonus, $is_popular, $sort, $is_active, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

// 4. DELETE PACKAGE
elseif ($action === 'delete_package') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query("DELETE FROM topup_packages WHERE id = $id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

// 5. TOGGLE POPULAR
elseif ($action === 'toggle_popular') {
    $id = intval($_POST['id'] ?? 0);
    $val = intval($_POST['val'] ?? 0); // 0 or 1
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE topup_packages SET is_popular = ? WHERE id = ?");
        $stmt->bind_param("ii", $val, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Action']);
}
?>