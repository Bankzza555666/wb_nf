<?php
// controller/slot_controller.php - Mini Slot Machine API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

// ต้อง login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ตั้งค่าเกม
define('MAX_SPINS_PER_DAY', 3);
define('SYMBOLS', ['🍒', '🍋', '🍊', '🍇', '🍉', '⭐', '💎']);

// อัตราการออกรางวัล (ยิ่งน้อยยิ่งยาก)
$REWARDS = [
    '💎💎💎' => ['type' => 'credit', 'amount' => 50, 'name' => 'JACKPOT! 💎'],
    '⭐⭐⭐' => ['type' => 'credit', 'amount' => 30, 'name' => 'SUPER WIN! ⭐'],
    '🍒🍒🍒' => ['type' => 'credit', 'amount' => 20, 'name' => 'Cherry Bonus!'],
    '🍇🍇🍇' => ['type' => 'credit', 'amount' => 15, 'name' => 'Grape Win!'],
    '🍊🍊🍊' => ['type' => 'credit', 'amount' => 10, 'name' => 'Orange Win!'],
    '🍋🍋🍋' => ['type' => 'credit', 'amount' => 8, 'name' => 'Lemon Win!'],
    '🍉🍉🍉' => ['type' => 'credit', 'amount' => 5, 'name' => 'Melon Win!'],
];

// รางวัลสำหรับ 2 ตัวตรง
$TWO_MATCH_REWARD = ['type' => 'credit', 'amount' => 2, 'name' => 'Small Win!'];

switch ($action) {
    case 'get_status':
        getStatus($conn, $user_id);
        break;
    case 'spin':
        spin($conn, $user_id);
        break;
    case 'get_history':
        getHistory($conn, $user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getStatus($conn, $user_id) {
    $today = date('Y-m-d');
    
    // นับจำนวนครั้งที่หมุนวันนี้
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM slot_spins WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $spins_today = intval($result['count']);
    
    // ดึงยอดเงินปัจจุบัน
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'spins_today' => $spins_today,
        'spins_remaining' => max(0, MAX_SPINS_PER_DAY - $spins_today),
        'max_spins' => MAX_SPINS_PER_DAY,
        'balance' => floatval($user['balance'] ?? 0)
    ]);
}

function spin($conn, $user_id) {
    global $REWARDS, $TWO_MATCH_REWARD;
    
    $today = date('Y-m-d');
    
    // ตรวจสอบจำนวนครั้งที่หมุนวันนี้
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM slot_spins WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $spins_today = intval($result['count']);
    
    if ($spins_today >= MAX_SPINS_PER_DAY) {
        echo json_encode([
            'success' => false, 
            'message' => 'หมดโอกาสหมุนวันนี้แล้ว กลับมาใหม่พรุ่งนี้นะ!',
            'spins_remaining' => 0
        ]);
        return;
    }
    
    // สุ่มผลลัพธ์
    $symbols = SYMBOLS;
    $result = [];
    
    // สุ่มแบบมีโอกาส jackpot ต่ำ
    $jackpot_chance = random_int(1, 100);
    
    if ($jackpot_chance <= 2) {
        // 2% โอกาส jackpot (3 ตัวตรง พิเศษ)
        $special = ['💎', '⭐'];
        $symbol = $special[array_rand($special)];
        $result = [$symbol, $symbol, $symbol];
    } elseif ($jackpot_chance <= 15) {
        // 13% โอกาส 3 ตัวตรง (ผลไม้)
        $fruits = ['🍒', '🍋', '🍊', '🍇', '🍉'];
        $symbol = $fruits[array_rand($fruits)];
        $result = [$symbol, $symbol, $symbol];
    } elseif ($jackpot_chance <= 35) {
        // 20% โอกาส 2 ตัวตรง
        $symbol = $symbols[array_rand($symbols)];
        $pos = random_int(0, 2);
        for ($i = 0; $i < 3; $i++) {
            if ($i == $pos) {
                // ตัวที่ไม่ตรง
                do {
                    $diff = $symbols[array_rand($symbols)];
                } while ($diff == $symbol);
                $result[] = $diff;
            } else {
                $result[] = $symbol;
            }
        }
    } else {
        // 65% สุ่มปกติ (อาจตรงหรือไม่ตรง)
        for ($i = 0; $i < 3; $i++) {
            $result[] = $symbols[array_rand($symbols)];
        }
    }
    
    $result_string = implode('', $result);
    $result_csv = implode(',', $result);
    
    // ตรวจสอบรางวัล
    $reward_type = 'nothing';
    $reward_amount = 0;
    $reward_name = 'ไม่ถูกรางวัล';
    
    // ตรวจ 3 ตัวตรง
    if (isset($REWARDS[$result_string])) {
        $reward = $REWARDS[$result_string];
        $reward_type = $reward['type'];
        $reward_amount = $reward['amount'];
        $reward_name = $reward['name'];
    } 
    // ตรวจ 2 ตัวตรง
    elseif ($result[0] == $result[1] || $result[1] == $result[2] || $result[0] == $result[2]) {
        $reward_type = $TWO_MATCH_REWARD['type'];
        $reward_amount = $TWO_MATCH_REWARD['amount'];
        $reward_name = $TWO_MATCH_REWARD['name'];
    }
    
    // บันทึกผลการหมุน
    $stmt = $conn->prepare("INSERT INTO slot_spins (user_id, result, reward_type, reward_amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $user_id, $result_csv, $reward_type, $reward_amount);
    $stmt->execute();
    
    // ถ้าได้รางวัล ให้เพิ่มเงิน
    $new_balance = 0;
    if ($reward_amount > 0) {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $reward_amount, $user_id);
        $stmt->execute();
        
        // ดึงยอดเงินใหม่
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $new_balance = floatval($user['balance']);
    }
    
    echo json_encode([
        'success' => true,
        'result' => $result,
        'reward_type' => $reward_type,
        'reward_amount' => $reward_amount,
        'reward_name' => $reward_name,
        'is_winner' => $reward_amount > 0,
        'is_jackpot' => $reward_amount >= 30,
        'spins_remaining' => MAX_SPINS_PER_DAY - $spins_today - 1,
        'new_balance' => $new_balance
    ]);
}

function getHistory($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT result, reward_type, reward_amount, created_at 
        FROM slot_spins 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $symbols = explode(',', $row['result']);
        $history[] = [
            'symbols' => $symbols,
            'reward_amount' => floatval($row['reward_amount']),
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
}
