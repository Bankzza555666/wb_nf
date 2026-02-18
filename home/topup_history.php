<?php
// --- [ส่วน PHP: ตรรกะหลัก] ---
$user_id = $_SESSION['user_id'];

/**
 * ฟังก์ชันแปลงข้อมูล Transaction สำหรับการแสดงผล
 * @param array $transaction ข้อมูล Transaction จาก DB
 * @return array ข้อมูลสำหรับการแสดงผล (key, text, class, icon, payment_url, is_verified)
 */
function getTransactionDisplayData($transaction) {
    $status_key = $transaction['status'];
    $payment_url = null;
    $is_verified = false;

    // ตรวจสอบ admin_note (อาจมีการยืนยันด้วยตนเอง หรือเก็บ URL การชำระเงิน)
    if (!empty($transaction['admin_note'])) {
        try {
            // ใช้ @ เพื่อป้องกัน warning หาก JSON ไม่ถูกต้อง
            $note_data = @json_decode($transaction['admin_note'], true);
            
            // ตรวจสอบว่า JSON decode สำเร็จและเป็น array
            if (is_array($note_data)) {
                if (isset($note_data['payment_verified']) && $note_data['payment_verified'] === true) {
                    $status_key = 'success'; // Admin ยืนยันเอง
                    $is_verified = true;
                }
                if (isset($note_data['payment_url'])) {
                    $payment_url = $note_data['payment_url']; // URL สำหรับกลับไปชำระเงิน
                }
            }
        } catch (Exception $e) {
            // ไม่ต้องทำอะไรหาก JSON parse ล้มเหลว
        }
    }

    // หากสถานะจากระบบเป็น 'approved' หรือ 'success' ให้ถือว่าสำเร็จ
    if ($transaction['status'] === 'approved' || $transaction['status'] === 'success') {
        $status_key = 'success';
        $is_verified = true;
    }
    
    // กำหนดค่าการแสดงผลตามสถานะสุดท้าย
    $display = [];
    if ($status_key === 'success' || $is_verified) {
        $display = [
            'key' => 'success',
            'text' => 'สำเร็จ',
            'class' => 'status-success',
            'icon' => 'fas fa-check-circle'
        ];
    } elseif ($status_key === 'pending') {
        $display = [
            'key' => 'pending',
            'text' => 'รอชำระ',
            'class' => 'status-pending',
            'icon' => 'fas fa-clock'
        ];
    } else { // 'failed', 'cancelled', 'expired', etc.
        $display = [
            'key' => 'failed',
            'text' => 'ล้มเหลว',
            'class' => 'status-failed',
            'icon' => 'fas fa-times-circle'
        ];
    }
    
    $display['payment_url'] = $payment_url;
    $display['is_verified'] = $is_verified;
    return $display;
}

// --- [การคำนวณสถิติ (Stats Cards)] ---
// ดึง Transaction *ทั้งหมด* เพื่อคำนวณสถิติ
$stmt_all = $conn->prepare("SELECT * FROM topup_transactions WHERE user_id = ?");
$stmt_all->bind_param("i", $user_id);
$stmt_all->execute();
$all_transactions_result = $stmt_all->get_result();

$stats_total_amount = 0;
$stats_total_bonus = 0;
$stats_month_amount = 0;
$stats_success_count = 0;
$current_month_start_str = date('Y-m-01'); // วันที่ 1 ของเดือนปัจจุบัน

while ($t_stats = $all_transactions_result->fetch_assoc()) {
    // *สำคัญ* ใช้ฟังก์ชันเดียวกับที่แสดงผล เพื่อให้สถิติตรงกัน
    $display_data = getTransactionDisplayData($t_stats); 
    
    if ($display_data['key'] === 'success') {
        $stats_total_amount += $t_stats['amount'];
        $stats_total_bonus += $t_stats['bonus'];
        $stats_success_count++;
        
        // ตรวจสอบว่าเป็นรายการของเดือนนี้หรือไม่
        $transaction_date_str = date('Y-m-01', strtotime($t_stats['created_at']));
        if ($transaction_date_str === $current_month_start_str) {
            $stats_month_amount += $t_stats['amount'];
        }
    }
}
$stmt_all->close();

// --- [การดึงข้อมูลสำหรับตาราง (Pagination)] ---
$limit = 10; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// นับจำนวน Transaction ทั้งหมด (สำหรับ Pagination)
$stmt = $conn->prepare("SELECT COUNT(id) as total FROM topup_transactions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_transactions = $total_result['total'];
$total_pages = ceil($total_transactions / $limit);
$stmt->close();

// ดึงข้อมูล Transaction ตามหน้าที่เลือก
$stmt = $conn->prepare("SELECT * FROM topup_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ดึงข้อมูล User (สำหรับแสดงยอดเงินคงเหลือ)
$stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/**
 * ฟังก์ชันแสดงผล Badge ช่องทางการชำระเงิน
 * @param string $method
 * @return string HTML
 */
function renderPaymentMethod($method) {
    $method = strtolower($method);
    if ($method === 'promptpay') {
        return '<span class="payment-badge promptpay"><i class="fas fa-qrcode"></i> PromptPay</span>';
    } elseif ($method === 'truemoney') {
        return '<span class="payment-badge truemoney"><i class="fas fa-mobile-alt"></i> TrueMoney</span>';
    }
    // สำหรับช่องทางอื่นๆ
    return '<span class="payment-badge other"><i class="fas fa-credit-card"></i> ' . htmlspecialchars(ucfirst($method)) . '</span>';
}

// --- [ส่วน HTML: แสดงผล] ---
include 'home/header.php';
include 'home/navbar.php';
?>

<style>
/* ========================================
   TOPUP HISTORY PAGE STYLES
   ======================================== */
.history-container {
    /* ✅ [ปรับปรุง] ลด padding-top จาก 80px เหลือ 30px */
    padding: 30px 0 60px;
    min-height: 100vh;
}

/* Breadcrumb */
.breadcrumb-nav {
    margin-bottom: 1.5rem;
    padding: 0.75rem 1.25rem;
    background: rgba(30, 41, 59, 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb-item:hover { color: #6366f1; }
.breadcrumb-item.active { color: white; font-weight: 600; }
.breadcrumb-separator { color: #475569; font-size: 0.8rem; }

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1.5rem;
    padding: 2rem;
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.page-title-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 2rem;
    font-weight: 800;
    color: white;
    margin: 0;
    letter-spacing: -0.5px;
}

.page-title i {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    animation: iconFloat 3s ease-in-out infinite;
}

@keyframes iconFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
}

.page-subtitle {
    color: #94a3b8;
    font-size: 0.95rem;
    font-weight: 500;
    margin-left: 76px;
}

.header-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-topup, .btn-print {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    padding: 0.85rem 1.75rem;
    border-radius: 12px;
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    cursor: pointer;
    white-space: nowrap;
}

.btn-print {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
}

.btn-topup:hover, .btn-print:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
    color: white;
}

.btn-print:hover {
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 1.75rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    flex-shrink: 0;
}

.stat-info {
    flex: 1;
    min-width: 0;
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    color: #94a3b8;
    margin-bottom: 0.35rem;
}

.stat-value {
    display: block;
    font-size: 1.75rem;
    font-weight: 800;
    color: white;
}

.stat-count {
    display: block;
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.5rem;
    font-weight: 500;
}

/* History Table */
.history-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
}

.table-container {
    overflow-x: auto;
}

.history-table {
    width: 100%;
    margin: 0;
    color: white;
}

.history-table thead {
    background: rgba(99, 102, 241, 0.1);
    border-bottom: 2px solid rgba(99, 102, 241, 0.3);
}

.history-table th {
    padding: 1.25rem 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    color: #cbd5e1;
    border: none;
    white-space: nowrap;
}

.history-table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.history-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.history-table td {
    padding: 1.25rem 1rem;
    vertical-align: middle;
    border: none;
}

/* Badges */
.payment-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.payment-badge.promptpay {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.payment-badge.truemoney {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.payment-badge.other {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.2));
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.4);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.85rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-success {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-failed {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-detail {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #818cf8;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-detail:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(-2px);
}

.btn-detail i {
    margin-right: 0.25rem;
}

/* Pagination */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    padding: 2rem 1.5rem;
    background: rgba(30, 41, 59, 0.3);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.pagination {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    margin: 0;
    padding: 0;
}

.page-item {
    list-style: none;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0.5rem 0.85rem;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #e2e8f0;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.page-link:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.3);
    color: white;
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
}

.page-item.disabled .page-link {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    color: rgba(99, 102, 241, 0.3);
    margin-bottom: 1.5rem;
}

.empty-text {
    font-size: 1.25rem;
    color: #94a3b8;
    margin-bottom: 2rem;
}

/* Modal */
.modal-content {
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    color: white;
}

.modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
}

.modal-title {
    font-weight: 700;
    font-size: 1.25rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    gap: 1rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.85rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.detail-label {
    color: #94a3b8;
    font-weight: 500;
}

.detail-value {
    color: white;
    font-weight: 600;
}

/* Mobile Card View (ซ่อนใน Desktop) */
.transaction-cards {
    display: none; /* ซ่อนเป็นค่าเริ่มต้น */
    flex-direction: column;
    padding: 1rem;
    gap: 1rem;
}

.transaction-card {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.transaction-card:hover {
    transform: translateY(-4px);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.card-id {
    font-family: 'Courier New', monospace;
    color: #818cf8;
    font-weight: 700;
    font-size: 1rem;
}

.card-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.card-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.card-label {
    color: #94a3b8;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-value {
    color: white;
    font-weight: 700;
    font-size: 0.95rem;
}

.card-amount {
    font-size: 1.5rem;
    color: white;
}

.card-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Print Styles */
@media print {
    body { background: white !important; }
    .main-nav, .breadcrumb-nav, .header-actions, .btn-detail, .pagination-wrapper, .modal { display: none !important; }
    .history-container { padding: 20px !important; }
    .page-header, .stat-card, .history-card { background: white !important; border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
    .page-title, .stat-label, .stat-value, .history-table th, .history-table td { color: black !important; }
    .history-table { border: 1px solid #ddd; }
    .history-table th, .history-table td { border: 1px solid #ddd; }
    .page-subtitle { color: #666 !important; }
}

/* Responsive Design */
@media (max-width: 991px) {
    /* ✅ [ปรับปรุง] ลด padding-top จาก 90px เหลือ 40px */
    .history-container { padding: 40px 0 40px; }
    .page-header { padding: 1.5rem; }
    .page-title { font-size: 1.75rem; }
    .page-title i { width: 50px; height: 50px; font-size: 1.5rem; }
    .page-subtitle { margin-left: 66px; font-size: 0.85rem; }
    .header-actions { width: 100%; }
    .btn-topup, .btn-print { flex: 1; justify-content: center; }
    .stats-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    /* ✅ [ปรับปรุง] ลด padding-top จาก 80px เหลือ 30px */
    .history-container { padding: 30px 0 30px; }
    .page-header { padding: 1.25rem; }
    .page-title { font-size: 1.5rem; }
    .page-title i { width: 45px; height: 45px; font-size: 1.25rem; }
    .page-subtitle { margin-left: 61px; font-size: 0.8rem; }
    
    /* ซ่อนตาราง (Desktop) และแสดงการ์ด (Mobile) */
    .table-container { display: none; }
    .transaction-cards { display: flex; }
    
    .stats-grid { grid-template-columns: 1fr; }
    .stat-card { padding: 1.25rem; gap: 1rem; }
    .stat-icon { width: 55px; height: 55px; font-size: 1.5rem; }
    .stat-value { font-size: 1.5rem; }
    .stat-label { font-size: 0.8rem; }
}

@media (max-width: 576px) {
    .breadcrumb-nav { padding: 0.5rem 1rem; font-size: 0.85rem; }
    .page-header { padding: 1rem; gap: 1rem; }
    .page-title { font-size: 1.25rem; }
    .page-title i { width: 40px; height: 40px; font-size: 1.1rem; }
    .page-subtitle { margin-left: 56px; font-size: 0.75rem; }
    .btn-topup, .btn-print { padding: 0.75rem 1.25rem; font-size: 0.9rem; }
    .stat-card { padding: 1rem; }
    .stat-icon { width: 50px; height: 50px; font-size: 1.25rem; }
    .stat-value { font-size: 1.25rem; }
}
</style>

<div class="history-container">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="?p=home" class="breadcrumb-item">
                <i class="fas fa-home"></i>
                หน้าหลัก
            </a>
            <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-item active">
                <i class="fas fa-history"></i>
                ประวัติการเติมเงิน
            </span>
        </nav>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1 class="page-title">
                    <i class="fas fa-history"></i>
                    ประวัติการเติมเงิน
                </h1>
                <p class="page-subtitle">ดูประวัติและรายละเอียดการเติมเงินทั้งหมดของคุณ</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i>
                    พิมพ์
                </button>
                <a href="?p=topup" class="btn-topup">
                    <i class="fas fa-plus"></i>
                    เติมเงิน
                </a>
            </div>
        </div>

        <!-- Stats Cards (การ์ดสรุปสถิติ) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">เครดิตคงเหลือ</span>
                    <span class="stat-value">฿<?php echo number_format($user['credit'], 2); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.2); color: #4ade80;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">เติมเดือนนี้</span>
                    <span class="stat-value">฿<?php echo number_format($stats_month_amount, 2); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.2); color: #a78bfa;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">เติมทั้งหมด</span>
                    <span class="stat-value">฿<?php echo number_format($stats_total_amount, 2); ?></span>
                    <span class="stat-count"><?php echo $stats_success_count; ?> ครั้ง</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24;">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">โบนัสทั้งหมด</span>
                    <span class="stat-value">+฿<?php echo number_format($stats_total_bonus, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- History Card (ตาราง/การ์ด ประวัติ) -->
        <div class="history-card">
            <?php if (count($transactions) > 0): // ตรวจสอบว่ามีข้อมูลหรือไม่ ?>
                
                <!-- 1. Desktop Table View (แสดงเฉพาะบน Desktop) -->
                <div class="table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>วันที่</th>
                                <th>จำนวน</th>
                                <th>โบนัส</th>
                                <th>ช่องทาง</th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $index => $t): 
                                // ใช้ฟังก์ชัน helper เพื่อรับข้อมูลการแสดงผล
                                $display = getTransactionDisplayData($t);
                                $row_number = ($page - 1) * $limit + $index + 1; // คำนวณลำดับที่
                            ?>
                            <tr>
                                <td><?php echo $row_number; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                <td>฿<?php echo number_format($t['amount'], 2); ?></td>
                                <td>+฿<?php echo number_format($t['bonus'], 2); ?></td>
                                <td><?php echo renderPaymentMethod($t['method']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $display['class']; ?>">
                                        <i class="<?php echo $display['icon']; ?>"></i>
                                        <?php echo $display['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- ปุ่มดูรายละเอียด (ส่งข้อมูลผ่าน data- attributes) -->
                                    <button class="btn-detail" 
                                        onclick="showDetail(this)"
                                        data-transaction='<?php echo json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                        data-display='<?php echo json_encode($display, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                        <i class="fas fa-eye"></i>
                                        ดูเพิ่ม
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 2. Mobile Card View (แสดงเฉพาะบน Mobile) -->
                <div class="transaction-cards">
                    <?php foreach ($transactions as $index => $t): 
                        $display = getTransactionDisplayData($t);
                        $row_number = ($page - 1) * $limit + $index + 1;
                    ?>
                    <div class="transaction-card">
                        <div class="card-header">
                            <span class="card-id">#<?php echo $t['id']; ?></span>
                            <span class="status-badge <?php echo $display['class']; ?>">
                                <i class="<?php echo $display['icon']; ?>"></i>
                                <?php echo $display['text']; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="card-row">
                                <span class="card-label">วันที่</span>
                                <span class="card-value"><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">จำนวนเงิน</span>
                                <span class="card-value card-amount">฿<?php echo number_format($t['amount'], 2); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">โบนัส</span>
                                <span class="card-value" style="color: #fbbf24;">+฿<?php echo number_format($t['bonus'], 2); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">ช่องทาง</span>
                                <span class="card-value"><?php echo renderPaymentMethod($t['method']); ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn-detail" 
                                onclick="showDetail(this)"
                                data-transaction='<?php echo json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                data-display='<?php echo json_encode($display, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                <i class="fas fa-eye"></i>
                                ดูรายละเอียด
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- 3. Pagination (ส่วนปุ่มเปลี่ยนหน้า) -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <ul class="pagination">
                        <!-- ปุ่ม "ก่อนหน้า" -->
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?p=topup_history&page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- ปุ่มหมายเลขหน้า -->
                        <?php 
                        $start = max(1, $page - 2); // แสดง 2 หน้าก่อนหน้า
                        $end = min($total_pages, $page + 2); // แสดง 2 หน้าถัดไป
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?p=topup_history&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <!-- ปุ่ม "ถัดไป" -->
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?p=topup_history&page=<?php echo $page + 1; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

            <?php else: // กรณีไม่มี Transaction ?>
                <!-- 4. Empty State (กรณีไม่พบข้อมูล) -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="empty-text">ยังไม่มีประวัติการเติมเงิน</div>
                    <a href="?p=topup" class="btn-topup">
                        <i class="fas fa-plus"></i>
                        เติมเงินเลย
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal (หน้าต่าง Pop-up แสดงรายละเอียด) -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i>รายละเอียดการเติมเงิน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- เนื้อหาจะถูกใส่โดย JavaScript -->
            </div>
            <div class="modal-footer" id="modalFooter">
                <!-- ปุ่มจะถูกใส่โดย JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
/**
 * ฟังก์ชันแสดงรายละเอียด Transaction ใน Modal
 * @param {HTMLElement} buttonElement - ปุ่มที่ถูกคลิก
 */
function showDetail(buttonElement) {
    let transaction, displayData;
    
    // 1. พยายามอ่านและ Parse ข้อมูลจาก data attributes
    try {
        transaction = JSON.parse(buttonElement.dataset.transaction);
        displayData = JSON.parse(buttonElement.dataset.display);
    } catch (e) {
        console.error("Error parsing JSON data:", e);
        Swal.fire('ผิดพลาด', 'ไม่สามารถโหลดข้อมูลได้', 'error');
        return;
    }
    
    // 2. สร้าง HTML เนื้อหา (body) ของ Modal
    let content = `
        <div class="detail-row">
            <span class="detail-label">รหัสรายการ</span>
            <span class="detail-value">#${transaction.id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">วันที่</span>
            <span class="detail-value">${new Date(transaction.created_at).toLocaleString('th-TH')}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">จำนวนเงิน</span>
            <span class="detail-value">฿${parseFloat(transaction.amount).toFixed(2)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">โบนัส</span>
            <span class="detail-value">+฿${parseFloat(transaction.bonus).toFixed(2)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">รวมทั้งหมด</span>
            <span class="detail-value" style="color: #4ade80; font-size: 1.25rem;">฿${(parseFloat(transaction.amount) + parseFloat(transaction.bonus)).toFixed(2)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">ช่องทาง</span>
            <span class="detail-value">${transaction.method}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">สถานะ</span>
            <span class="detail-value">
                <span class="status-badge ${displayData.class}">
                    <i class="${displayData.icon}"></i>
                    ${displayData.text}
                </span>
            </span>
        </div>
        <div class="detail-row" style="border: none;">
            <span class="detail-label">เลขที่อ้างอิง</span>
            <span class="detail-value" style="word-break: break-all;">${transaction.transaction_ref || 'ไม่มี'}</span>
        </div>
    `;
    
    // 3. สร้าง HTML ปุ่ม (footer) ของ Modal
    // (ปุ่มปิด)
    let footer = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); color: white; padding: 0.75rem 2rem; border-radius: 10px;"><i class="fas fa-times me-2"></i>ปิด</button>`;
    
    // (ปุ่มชำระเงิน) - แสดงเฉพาะเมื่อสถานะเป็น 'pending', ยังไม่ถูก verify และมี payment_url
    if (displayData.key === 'pending' && !displayData.is_verified && displayData.payment_url) {
        footer = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); color: white; padding: 0.75rem 2rem; border-radius: 10px;">
                <i class="fas fa-times me-2"></i>ปิด
            </button>
            <a href="${displayData.payment_url}" class="btn btn-success" target="_blank" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; padding: 0.75rem 2rem; border-radius: 10px; text-decoration: none;">
                <i class="fas fa-arrow-right me-2"></i>ชำระเงิน
            </a>
        `;
    }
    
    // 4. ใส่ HTML เข้าไปใน Modal
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('modalFooter').innerHTML = footer;
    
    // 5. สั่งเปิด Modal
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<?php include 'home/footer.php'; ?>