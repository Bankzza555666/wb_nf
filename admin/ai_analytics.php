<?php
// admin/ai_analytics.php - AI Performance Analytics Dashboard
require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Analytics - ประเมินผล AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-body: #000000;
            --bg-card: rgba(15, 15, 15, 0.9);
            --border-color: rgba(229, 9, 20, 0.2);
            --text-primary: #ffffff;
            --text-secondary: #aaaaaa;
            --accent: #E50914;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }

        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Prompt', sans-serif;
        }

        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(229,9,20,0.1), rgba(0,0,0,0.5));
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.8; }
        .stat-number { font-size: 2.2rem; font-weight: bold; }
        .stat-label { color: var(--text-secondary); font-size: 0.9rem; }
        
        /* Custom 5 columns layout */
        @media (min-width: 992px) {
            .col-lg-2-4 {
                flex: 0 0 20%;
                max-width: 20%;
            }
        }

        .nav-tabs { border-bottom: 1px solid var(--border-color); }
        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            padding: 15px 25px;
            font-weight: 500;
        }
        .nav-tabs .nav-link:hover { color: #fff; border: none; }
        .nav-tabs .nav-link.active {
            background: transparent;
            color: var(--accent);
            border-bottom: 3px solid var(--accent);
        }

        .chat-bubble {
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 10px;
            max-width: 85%;
        }
        .chat-user {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            margin-left: auto;
        }
        .chat-ai {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid rgba(229, 9, 20, 0.3);
        }

        .rating-btn {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .rating-btn.good { background: rgba(34, 197, 94, 0.2); border: 1px solid var(--success); color: var(--success); }
        .rating-btn.bad { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); color: var(--danger); }
        .rating-btn.good:hover { background: var(--success); color: #fff; }
        .rating-btn.bad:hover { background: var(--danger); color: #fff; }
        .rating-btn.active { color: #fff !important; }
        .rating-btn.good.active { background: var(--success) !important; }
        .rating-btn.bad.active { background: var(--danger) !important; }

        .keyword-tag {
            display: inline-block;
            padding: 6px 14px;
            margin: 4px;
            border-radius: 20px;
            font-size: 0.85rem;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: var(--info);
        }
        .keyword-tag .count {
            background: var(--info);
            color: #fff;
            border-radius: 10px;
            padding: 2px 8px;
            margin-left: 8px;
            font-size: 0.75rem;
        }

        .table { color: var(--text-primary); }
        .table thead th {
            background: rgba(15, 23, 42, 0.8);
            color: var(--text-secondary);
            border-color: var(--border-color);
            font-size: 0.85rem;
        }
        .table td { border-color: var(--border-color); vertical-align: middle; }

        .form-control, .form-select {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.1);
            border-color: var(--accent);
            color: #fff;
        }
        .form-select option { background: #1a1a1a; }

        .progress {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            height: 8px;
        }

        .chat-container {
            max-height: 500px;
            overflow-y: auto;
        }

        .badge-rule { background: rgba(168, 85, 247, 0.2); border: 1px solid #a855f7; color: #a855f7; }
        .badge-ai { background: rgba(229, 9, 20, 0.2); border: 1px solid var(--accent); color: var(--accent); }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0"><i class="fas fa-chart-line text-danger me-2"></i>AI Analytics Dashboard</h3>
                <small class="text-secondary">ประเมินผลและวิเคราะห์การตอบของ AI</small>
            </div>
            <div class="d-flex gap-2">
                <a href="?p=admin_ai_training" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-brain me-1"></i>สอน AI
                </a>
                <button class="btn btn-danger btn-sm" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-1"></i>รีเฟรช
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2-4">
                <div class="stat-card">
                    <div class="stat-icon text-info"><i class="fas fa-comments"></i></div>
                    <div class="stat-number text-info" id="statTotalChats">-</div>
                    <div class="stat-label">ข้อความทั้งหมด</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2-4">
                <div class="stat-card">
                    <div class="stat-icon text-success"><i class="fas fa-robot"></i></div>
                    <div class="stat-number text-success" id="statAiResponses">-</div>
                    <div class="stat-label">AI ตอบ</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2-4">
                <div class="stat-card">
                    <div class="stat-icon text-warning"><i class="fas fa-book"></i></div>
                    <div class="stat-number text-warning" id="statRuleMatches">-</div>
                    <div class="stat-label">ตรงกับ Rule</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2-4">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #22c55e;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number" style="color: #22c55e;" id="statGoodResponses">-</div>
                    <div class="stat-label">ใช้คำตอบดี</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2-4">
                <div class="stat-card">
                    <div class="stat-icon text-danger"><i class="fas fa-star"></i></div>
                    <div class="stat-number text-danger" id="statSatisfaction">-</div>
                    <div class="stat-label">ความพอใจ</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="mainTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-conversations">
                    <i class="fas fa-history me-2"></i>ประวัติการสนทนา
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-keywords">
                    <i class="fas fa-tags me-2"></i>คำถามยอดนิยม
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rules">
                    <i class="fas fa-list-check me-2"></i>Rule Performance
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-feedback">
                    <i class="fas fa-thumbs-up me-2"></i>Feedback
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab: Conversations -->
            <div class="tab-pane fade show active" id="tab-conversations">
                <div class="row g-3">
                    <!-- User List -->
                    <div class="col-md-4">
                        <div class="glass-card">
                            <h6 class="mb-3"><i class="fas fa-users me-2 text-info"></i>ผู้ใช้ที่สนทนา</h6>
                            <input type="text" class="form-control form-control-sm mb-3" id="searchUser" placeholder="ค้นหาผู้ใช้...">
                            <div id="userList" style="max-height: 400px; overflow-y: auto;">
                                <div class="text-center text-secondary py-3">กำลังโหลด...</div>
                            </div>
                        </div>
                    </div>
                    <!-- Chat Detail -->
                    <div class="col-md-8">
                        <div class="glass-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="fas fa-message me-2 text-danger"></i>รายละเอียดการสนทนา</h6>
                                <span id="chatUserName" class="badge bg-secondary">เลือกผู้ใช้</span>
                            </div>
                            <div id="chatDetail" class="chat-container">
                                <div class="text-center text-secondary py-5">
                                    <i class="fas fa-arrow-left fa-2x mb-3"></i>
                                    <p>เลือกผู้ใช้เพื่อดูประวัติการสนทนา</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Keywords -->
            <div class="tab-pane fade" id="tab-keywords">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="glass-card">
                            <h6 class="mb-3"><i class="fas fa-fire me-2 text-warning"></i>คำถามที่ถูกถามบ่อย (7 วัน)</h6>
                            <div id="topKeywords">
                                <div class="text-center text-secondary py-3">กำลังโหลด...</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card">
                            <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-info"></i>แนวโน้มคำถาม</h6>
                            <canvas id="keywordChart" height="200"></canvas>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="glass-card">
                            <h6 class="mb-3"><i class="fas fa-question-circle me-2 text-danger"></i>คำถามที่ AI อาจตอบไม่ตรง (ไม่มี Rule)</h6>
                            <div id="unmatchedQuestions">
                                <div class="text-center text-secondary py-3">กำลังโหลด...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Rules Performance -->
            <div class="tab-pane fade" id="tab-rules">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Rule ที่ถูกใช้บ่อย</h6>
                        <a href="?p=admin_ai_training" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-plus me-1"></i>เพิ่ม Rule
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Keywords</th>
                                    <th>Response Preview</th>
                                    <th>ใช้งาน</th>
                                    <th>ความพอใจ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="rulesPerformance">
                                <tr><td colspan="6" class="text-center py-4 text-secondary">กำลังโหลด...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Feedback -->
            <div class="tab-pane fade" id="tab-feedback">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="glass-card">
                            <h6 class="mb-3"><i class="fas fa-clipboard-list me-2 text-info"></i>Feedback ล่าสุด</h6>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ผู้ใช้</th>
                                            <th>คำถาม</th>
                                            <th>คำตอบ AI</th>
                                            <th>Rating</th>
                                            <th>วันที่</th>
                                        </tr>
                                    </thead>
                                    <tbody id="feedbackList">
                                        <tr><td colspan="5" class="text-center py-4 text-secondary">กำลังโหลด...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="glass-card mb-3">
                            <h6 class="mb-3"><i class="fas fa-chart-bar me-2 text-success"></i>สัดส่วน Feedback</h6>
                            <canvas id="feedbackChart" height="200"></canvas>
                        </div>
                        <div class="glass-card">
                            <h6 class="mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>แนะนำ</h6>
                            <div id="suggestions" class="small">
                                <p class="text-secondary mb-2">ระบบจะแนะนำ Rule ใหม่จากคำถามที่ถูกถามบ่อย</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // API path - detect based on current location
        const getApiPath = () => {
            const path = window.location.pathname;
            if (path.includes('/admin/')) {
                return '../controller/admin_controller/ai_analytics_controller.php';
            }
            return 'controller/admin_controller/ai_analytics_controller.php';
        };
        const API = getApiPath();
        let keywordChart, feedbackChart;

        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadUserList();
            loadKeywords();
            loadRulesPerformance();
            loadFeedback();
        });

        function refreshData() {
            loadStats();
            loadUserList();
            loadKeywords();
            loadRulesPerformance();
            loadFeedback();
            Swal.fire({ icon: 'success', title: 'รีเฟรชแล้ว', timer: 1000, showConfirmButton: false, background: '#1a1a1a', color: '#fff' });
        }

        // Load Statistics
        function loadStats() {
            fetch(`${API}?action=get_stats`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('statTotalChats').textContent = data.total_messages?.toLocaleString() || 0;
                        document.getElementById('statAiResponses').textContent = data.ai_responses?.toLocaleString() || 0;
                        document.getElementById('statRuleMatches').textContent = data.rule_matches?.toLocaleString() || 0;
                        document.getElementById('statGoodResponses').textContent = data.good_responses_used?.toLocaleString() || 0;
                        document.getElementById('statSatisfaction').textContent = (data.satisfaction || 0) + '%';
                    }
                });
        }

        // Load User List
        function loadUserList() {
            fetch(`${API}?action=get_chat_users`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('userList');
                    if (!data.success || !data.users || data.users.length === 0) {
                        container.innerHTML = '<div class="text-center text-secondary py-3">ไม่พบข้อมูล</div>';
                        return;
                    }

                    let html = '';
                    data.users.forEach(u => {
                        html += `
                            <div class="d-flex align-items-center p-2 rounded mb-2" style="background: rgba(255,255,255,0.05); cursor: pointer;" onclick="loadChat(${u.user_id}, '${u.username}')">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-white">${u.username}</div>
                                    <small class="text-secondary">${u.message_count} ข้อความ</small>
                                </div>
                                <small class="text-secondary">${u.last_message}</small>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                });
        }

        // Load Chat Detail
        function loadChat(userId, username) {
            document.getElementById('chatUserName').textContent = username;
            const container = document.getElementById('chatDetail');
            container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i></div>';

            fetch(`${API}?action=get_chat_history&user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.messages || data.messages.length === 0) {
                        container.innerHTML = '<div class="text-center text-secondary py-5">ไม่พบประวัติการสนทนา</div>';
                        return;
                    }

                    let html = '';
                    data.messages.forEach(m => {
                        const isUser = m.sender === 'user';
                        const bubbleClass = isUser ? 'chat-user' : 'chat-ai';
                        const icon = isUser ? '<i class="fas fa-user me-2"></i>' : '<i class="fas fa-robot me-2"></i>';
                        const label = isUser ? 'User' : (m.is_ai ? 'AI' : 'Admin');
                        const badge = m.is_ai ? '<span class="badge badge-ai ms-2">AI</span>' : (m.sender === 'admin' ? '<span class="badge bg-success ms-2">Admin</span>' : '');
                        
                        html += `
                            <div class="d-flex ${isUser ? 'justify-content-end' : 'justify-content-start'} mb-2">
                                <div class="chat-bubble ${bubbleClass}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-secondary">${icon}${label}${badge}</small>
                                        <small class="text-secondary ms-3">${m.created_at}</small>
                                    </div>
                                    <div>${m.message}</div>
                                    ${m.is_ai && m.sender === 'admin' ? `
                                        <div class="mt-2 pt-2 border-top border-secondary" id="rating-${m.id}">
                                            <small class="text-secondary me-2">ให้คะแนน:</small>
                                            <button class="rating-btn good ${m.rating === 'good' ? 'active' : ''}" onclick="rateResponse(${m.id}, 'good', this)">
                                                <i class="fas fa-thumbs-up"></i> ดี
                                            </button>
                                            <button class="rating-btn bad ${m.rating === 'bad' ? 'active' : ''}" onclick="rateResponse(${m.id}, 'bad', this)">
                                                <i class="fas fa-thumbs-down"></i> ไม่ดี
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                    container.scrollTop = container.scrollHeight;
                });
        }

        // Rate AI Response
        function rateResponse(messageId, rating, buttonElement) {
            console.log('Rating:', messageId, rating, 'API:', API);
            
            const fd = new FormData();
            fd.append('action', 'rate_response');
            fd.append('message_id', messageId);
            fd.append('rating', rating);

            // Disable button while processing
            buttonElement.disabled = true;
            
            fetch(API, { method: 'POST', body: fd })
                .then(r => {
                    console.log('Response status:', r.status);
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    buttonElement.disabled = false;
                    
                    if (data.success) {
                        // Update button states
                        const ratingContainer = document.getElementById('rating-' + messageId);
                        if (ratingContainer) {
                            const buttons = ratingContainer.querySelectorAll('.rating-btn');
                            buttons.forEach(btn => {
                                btn.classList.remove('active');
                                btn.disabled = false;
                            });
                            buttonElement.classList.add('active');
                        }
                        loadStats();
                        Swal.fire({ 
                            icon: 'success', 
                            title: 'บันทึกแล้ว', 
                            text: 'ขอบคุณสำหรับ Feedback', 
                            timer: 1500, 
                            showConfirmButton: false, 
                            background: '#1a1a1a', 
                            color: '#fff' 
                        });
                    } else {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'เกิดข้อผิดพลาด', 
                            text: data.message || 'ไม่สามารถบันทึกได้', 
                            background: '#1a1a1a', 
                            color: '#fff' 
                        });
                    }
                })
                .catch(err => {
                    console.error('Error rating:', err);
                    buttonElement.disabled = false;
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'เกิดข้อผิดพลาด', 
                        text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้: ' + err.message, 
                        background: '#1a1a1a', 
                        color: '#fff' 
                    });
                });
        }

        // Load Keywords
        function loadKeywords() {
            fetch(`${API}?action=get_keywords`)
                .then(r => r.json())
                .then(data => {
                    // Top Keywords
                    const container = document.getElementById('topKeywords');
                    if (data.success && data.keywords && data.keywords.length > 0) {
                        let html = '';
                        data.keywords.forEach(k => {
                            html += `<span class="keyword-tag">${k.keyword}<span class="count">${k.count}</span></span>`;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="text-secondary">ไม่พบข้อมูล</p>';
                    }

                    // Unmatched Questions
                    const unmatchedContainer = document.getElementById('unmatchedQuestions');
                    if (data.unmatched && data.unmatched.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-sm"><tbody>';
                        data.unmatched.forEach(q => {
                            html += `
                                <tr>
                                    <td class="text-truncate" style="max-width: 400px;">${q.message}</td>
                                    <td><span class="badge bg-secondary">${q.count}x</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-warning" onclick="suggestRule('${encodeURIComponent(q.message)}')">
                                            <i class="fas fa-plus"></i> สร้าง Rule
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table></div>';
                        unmatchedContainer.innerHTML = html;
                    } else {
                        unmatchedContainer.innerHTML = '<p class="text-secondary">AI ตอบได้ครบทุกคำถาม!</p>';
                    }

                    // Chart
                    if (data.chart_data) {
                        renderKeywordChart(data.chart_data);
                    }
                });
        }

        function renderKeywordChart(chartData) {
            const ctx = document.getElementById('keywordChart').getContext('2d');
            if (keywordChart) keywordChart.destroy();
            
            keywordChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels || [],
                    datasets: [{
                        label: 'จำนวนครั้ง',
                        data: chartData.values || [],
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: '#3b82f6',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#aaa' }, grid: { display: false } },
                        y: { ticks: { color: '#aaa' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                    }
                }
            });
        }

        // Suggest Rule
        function suggestRule(question) {
            const decoded = decodeURIComponent(question);
            Swal.fire({
                title: 'สร้าง Rule ใหม่',
                html: `
                    <p class="small text-muted mb-3">คำถาม: "${decoded.substring(0, 50)}..."</p>
                    <input id="swalKeywords" class="swal2-input" placeholder="Keywords (เช่น vpn, ไม่ได้, connect)" value="${decoded.split(' ').slice(0, 3).join(', ')}">
                    <textarea id="swalResponse" class="swal2-textarea" placeholder="คำตอบที่ต้องการให้ AI ตอบ"></textarea>
                `,
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                background: '#1a1a1a',
                color: '#fff'
            }).then(result => {
                if (result.isConfirmed) {
                    const keywords = document.getElementById('swalKeywords').value;
                    const response = document.getElementById('swalResponse').value;
                    
                    if (keywords && response) {
                        const fd = new FormData();
                        fd.append('action', 'add_rule');
                        fd.append('keywords', keywords);
                        fd.append('response', response);
                        fd.append('match_type', 'fuzzy');
                        fd.append('priority', 0);

                        fetch('../controller/admin_controller/admin_ai_api.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({ icon: 'success', title: 'เพิ่ม Rule สำเร็จ', timer: 1500, showConfirmButton: false, background: '#1a1a1a', color: '#fff' });
                                    loadKeywords();
                                    loadRulesPerformance();
                                }
                            });
                    }
                }
            });
        }

        // Load Rules Performance
        function loadRulesPerformance() {
            fetch(`${API}?action=get_rules_performance`)
                .then(r => r.json())
                .then(data => {
                    const tbody = document.getElementById('rulesPerformance');
                    if (!data.success || !data.rules || data.rules.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-secondary">ไม่พบ Rule</td></tr>';
                        return;
                    }

                    let html = '';
                    data.rules.forEach((r, i) => {
                        const satisfaction = r.good_count + r.bad_count > 0 
                            ? Math.round((r.good_count / (r.good_count + r.bad_count)) * 100) 
                            : '-';
                        
                        html += `
                            <tr>
                                <td class="text-secondary">${i + 1}</td>
                                <td><span class="text-info">${r.keywords}</span></td>
                                <td class="text-truncate" style="max-width: 200px;">${r.response.substring(0, 50)}...</td>
                                <td><span class="badge bg-info">${r.use_count || 0}</span></td>
                                <td>
                                    ${satisfaction !== '-' ? `
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="width: 60px;">
                                                <div class="progress-bar ${satisfaction >= 70 ? 'bg-success' : satisfaction >= 40 ? 'bg-warning' : 'bg-danger'}" style="width: ${satisfaction}%"></div>
                                            </div>
                                            <small>${satisfaction}%</small>
                                        </div>
                                    ` : '<span class="text-secondary">-</span>'}
                                </td>
                                <td>
                                    <a href="?p=admin_ai_training" class="btn btn-sm btn-outline-light"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                });
        }

        // Load Feedback
        function loadFeedback() {
            fetch(`${API}?action=get_feedback`)
                .then(r => r.json())
                .then(data => {
                    const tbody = document.getElementById('feedbackList');
                    if (!data.success || !data.feedback || data.feedback.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-secondary">ไม่พบ Feedback</td></tr>';
                    } else {
                        let html = '';
                        data.feedback.forEach(f => {
                            html += `
                                <tr>
                                    <td>${f.username}</td>
                                    <td class="text-truncate" style="max-width: 150px;">${f.question}</td>
                                    <td class="text-truncate" style="max-width: 200px;">${f.answer}</td>
                                    <td>
                                        ${f.rating === 'good' 
                                            ? '<span class="badge badge-rule bg-success">ดี</span>' 
                                            : '<span class="badge bg-danger">ไม่ดี</span>'}
                                    </td>
                                    <td class="small text-secondary">${f.rated_at}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                    }

                    // Render Feedback Chart
                    if (data.summary) {
                        renderFeedbackChart(data.summary);
                    }

                    // Suggestions
                    const suggestionsContainer = document.getElementById('suggestions');
                    if (data.suggestions && data.suggestions.length > 0) {
                        let sugHtml = '';
                        data.suggestions.forEach(s => {
                            sugHtml += `<div class="p-2 mb-2 rounded" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);">
                                <i class="fas fa-lightbulb text-warning me-2"></i>${s}
                            </div>`;
                        });
                        suggestionsContainer.innerHTML = sugHtml;
                    }
                });
        }

        function renderFeedbackChart(summary) {
            const ctx = document.getElementById('feedbackChart').getContext('2d');
            if (feedbackChart) feedbackChart.destroy();
            
            feedbackChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['ดี', 'ไม่ดี'],
                    datasets: [{
                        data: [summary.good || 0, summary.bad || 0],
                        backgroundColor: ['#22c55e', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#fff' } } }
                }
            });
        }

        // Search User
        document.getElementById('searchUser').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('#userList > div').forEach(el => {
                const name = el.querySelector('.fw-bold').textContent.toLowerCase();
                el.style.display = name.includes(search) ? 'flex' : 'none';
            });
        });
    </script>
</body>

</html>
