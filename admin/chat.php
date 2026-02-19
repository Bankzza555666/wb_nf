<?php
// admin/chat.php
require_once './controller/admin_controller/admin_config.php';
// ✅ CSRF Helper (include config.php again or helper if needed, but admin_config might verify auth)
require_once dirname(__DIR__) . '/controller/config.php'; // Ensure config is loaded for csrf_helper
checkAdminAuth();

// ✅ CSRF Token
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="th">
<script>const CSRF_TOKEN = "<?php echo $csrf_token; ?>";</script>

<head>
    <meta charset="UTF-8">
    <title>Admin Chat Center</title>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-body: #000000;
            --bg-sidebar: #0a0a0a;
            --bg-element: #1a1a1a;
            --border: #330000;
            --text-primary: #ffffff;
            --text-secondary: #aaaaaa;
            --accent: #E50914;
            --accent-hover: #b20710;
            --msg-admin: linear-gradient(135deg, #E50914, #B20710);
            --msg-user: #1a1a1a;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Prompt', sans-serif;
            height: 100%;
            width: 100%;
            position: fixed;
            overflow: hidden;
        }

        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        .chat-layout {
            display: flex;
            height: calc(100% - 70px);
            width: 100%;
            margin-top: 0;
            position: relative;
            overflow: hidden;
        }

        .sidebar-users {
            width: 350px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 20;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sb-header {
            padding: 20px;
            background: rgba(30, 41, 59, 0.95);
            border-bottom: 1px solid var(--border);
        }

        .search-wrapper {
            position: relative;
            margin-top: 10px;
        }

        .search-input {
            width: 100%;
            background: var(--bg-body);
            border: 1px solid var(--border);
            padding: 10px 15px 10px 40px;
            border-radius: 10px;
            color: var(--text-primary);
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .user-list-container {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .user-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.2s;
        }

        .user-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .user-item.active {
            background: rgba(99, 102, 241, 0.15);
            border-left: 4px solid var(--accent);
        }

        .u-avatar {
            width: 45px;
            height: 45px;
            background: var(--bg-element);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .u-info {
            flex: 1;
            min-width: 0;
        }

        .u-badge {
            background: #ef4444;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-body);
            position: relative;
            z-index: 10;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            min-width: 0;
        }

        .chat-topbar {
            height: 60px;
            padding: 0 15px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }

        .btn-back {
            display: none;
            color: var(--text-secondary);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 5px;
        }

        .chat-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            -webkit-overflow-scrolling: touch;
        }

        .msg-row {
            display: flex;
            width: 100%;
            animation: fadeIn 0.3s ease;
        }

        .msg-row.admin {
            justify-content: flex-end;
        }

        .msg-row.user {
            justify-content: flex-start;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 0.95rem;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .msg-row.admin .msg-bubble {
            background: var(--msg-admin);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .msg-row.user .msg-bubble {
            background: var(--msg-user);
            color: var(--text-primary);
            border-bottom-left-radius: 4px;
        }

        .chat-image {
            max-width: 100%;
            border-radius: 10px;
            margin-top: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }

        .chat-input-area {
            padding: 10px 15px;
            background: var(--bg-sidebar);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            width: 100%;
            position: relative;
            z-index: 100;
        }

        .input-group-custom {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 25px;
            padding: 5px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-primary);
            outline: none;
            padding: 12px 0;
            min-width: 0;
            font-size: 16px;
        }

        .btn-icon {
            color: var(--text-secondary);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
        }

        .btn-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 991px) {
            .sidebar-users {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                transform: translateX(0);
            }

            .chat-main {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                transform: translateX(100%);
                background: var(--bg-body);
            }

            .chat-layout.chat-active .sidebar-users {
                transform: translateX(-20%);
                opacity: 0;
                pointer-events: none;
            }

            .chat-layout.chat-active .chat-main {
                transform: translateX(0);
            }

            .btn-back {
                display: block;
            }

            .msg-bubble {
                max-width: 80%;
            }

            .chat-input-area {
                padding-bottom: max(10px, env(safe-area-inset-bottom));
            }
        }

        /* ===== New Chat Toggles ===== */
        .chat-toggle-group {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            padding: 4px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            gap: 4px;
        }

        .chat-toggle-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chat-toggle-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .chat-toggle-btn[data-active="true"] {
            background: rgba(229, 9, 20, 0.2);
            color: #ff4757;
            box-shadow: 0 0 10px rgba(229, 9, 20, 0.2);
            border: 1px solid rgba(229, 9, 20, 0.3);
        }

        .chat-toggle-btn[data-active="true"] i {
            animation: pulse-icon 2s infinite;
        }

        @keyframes pulse-icon {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="chat-layout" id="chatLayout">

        <div class="sidebar-users">
            <div class="sb-header">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-inbox text-primary me-2"></i> ข้อความ</h5>

                    <div class="d-flex gap-2">
                        <div class="chat-toggle-group">
                            <button class="chat-toggle-btn" id="voiceToggleBtn" data-active="false" title="เปิด/ปิด เสียงอ่าน">
                                <i class="fas fa-volume-mute"></i> <span>Voice</span>
                            </button>
                            <button class="chat-toggle-btn" id="aiToggleBtn" data-active="false" title="เปิด/ปิด AI">
                                <i class="fas fa-robot"></i> <span>AI</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="ค้นหาลูกค้า...">
                </div>
            </div>
            <div class="user-list-container" id="usersList">
                <div class="text-center p-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary mb-2"></div><br>กำลังโหลด...
                </div>
            </div>
        </div>

        <div class="chat-main">
            <div id="emptyState" class="empty-state">
                <div class="empty-icon"><i class="far fa-comments"></i></div>
                <h4>เลือกแชท</h4>
                <p class="small text-muted">แตะที่รายชื่อเพื่อเริ่มสนทนา</p>
            </div>

            <div id="chatContent" style="display: none; flex-direction: column; height: 100%;">
                <div class="chat-topbar">
                    <div class="d-flex align-items-center flex-grow-1">
                        <i class="fas fa-arrow-left btn-back" onclick="backToList()"></i>
                        <div class="u-avatar me-3" id="headerAvatar">U</div>
                        <div>
                            <div class="fw-bold" id="headerName">User</div>
                            <div class="text-success small" style="font-size: 0.75rem;"><i class="fas fa-circle"
                                    style="font-size: 0.5rem;"></i> ลูกค้า</div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-info ms-auto" onclick="openSimulationModal()">
                        <i class="fas fa-pen-nib"></i> <span class="d-none d-sm-inline">จำลองแชท</span>
                    </button>
                </div>

                <div class="chat-content" id="messageArea"></div>

                <div class="chat-input-area">
                    <form id="replyForm" enctype="multipart/form-data">
                        <input type="hidden" id="currentUserId">
                        <div class="input-group-custom">
                            <label for="adminImgInput" class="btn-icon mb-0"><i class="fas fa-paperclip"></i></label>
                            <input type="file" id="adminImgInput" accept="image/*" style="display:none;">
                            <input type="text" id="replyInput" class="chat-input" placeholder="ข้อความ..."
                                autocomplete="off" list="cannedList">
                            <datalist id="cannedList"></datalist>
                            <button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i></button>
                        </div>
                        <div id="adminFilePreview" class="small text-info mt-1 ps-2"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let currentUserId = null;
            let lastChatData = null;
            let lastMsgMap = {};
            let isFirstLoad = true;

            const voiceToggleBtn = document.getElementById('voiceToggleBtn');
            const aiToggleBtn = document.getElementById('aiToggleBtn');
            const layout = document.getElementById('chatLayout');
            const usersList = document.getElementById('usersList');
            const messageArea = document.getElementById('messageArea');
            const adminImgInput = document.getElementById('adminImgInput');
            const adminFilePreview = document.getElementById('adminFilePreview');
            const API_URL = '../controller/admin_controller/admin_chat_api.php';

            function setAppHeight() { document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`); }
            window.addEventListener('resize', setAppHeight);
            setAppHeight();

            // 1. Voice Toggle
            function updateVoiceUI(active) {
                voiceToggleBtn.dataset.active = active;
                voiceToggleBtn.innerHTML = active 
                    ? '<i class="fas fa-volume-up"></i> <span>Voice: ON</span>' 
                    : '<i class="fas fa-volume-mute"></i> <span>Voice: OFF</span>';
            }

            function loadVoiceStatus() {
                fetch(`${API_URL}?action=get_voice_status`)
                    .then(res => res.json())
                    .then(data => { if (data.success) updateVoiceUI(data.active); });
            }

            voiceToggleBtn.addEventListener('click', () => {
                const isActive = voiceToggleBtn.dataset.active === 'true';
                const newState = !isActive;
                
                updateVoiceUI(newState); // Optimistic update

                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN); // ✅ CSRF
                formData.append('action', 'toggle_voice');
                formData.append('status', newState);
                fetch(API_URL, { method: 'POST', body: formData });

                if (newState) speakText("เปิดระบบแจ้งเตือนด้วยเสียงแล้วครับ");
            });
            loadVoiceStatus();

            function speakText(text) {
                if (voiceToggleBtn.dataset.active !== 'true') return;

                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'th-TH';
                utterance.rate = 1.0;
                utterance.volume = 1.0;
                window.speechSynthesis.speak(utterance);
            }

            // 2. AI Toggle
            function updateAI_UI(active) {
                aiToggleBtn.dataset.active = active;
                aiToggleBtn.innerHTML = active 
                    ? '<i class="fas fa-robot"></i> <span>AI: ON</span>' 
                    : '<i class="fas fa-robot"></i> <span>AI: OFF</span>';
            }

            function loadAIStatus() {
                fetch(`${API_URL}?action=get_ai_status`)
                    .then(res => res.json())
                    .then(data => { if (data.success) updateAI_UI(data.active); });
            }

            aiToggleBtn.addEventListener('click', () => {
                const isActive = aiToggleBtn.dataset.active === 'true';
                const newState = !isActive;

                updateAI_UI(newState); // Optimistic update

                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN); // ✅ CSRF
                formData.append('action', 'toggle_ai');
                formData.append('status', newState);
                fetch(API_URL, { method: 'POST', body: formData });

                if (newState) {
                    speakText("เปิดระบบเอไอ ตอบกลับแล้วครับ");
                } else {
                    speakText("ปิดระบบเอไอแล้วครับ");
                }
            });
            loadAIStatus();

            // Autocomplete
            function loadSuggestions() {
                fetch(`${API_URL}?action=get_suggestions`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const dataList = document.getElementById('cannedList');
                            dataList.innerHTML = '';
                            data.data.forEach(msg => {
                                const option = document.createElement('option');
                                option.value = msg;
                                dataList.appendChild(option);
                            });
                        }
                    });
            }
            loadSuggestions();

            // Load Users
            function loadUsers() {
                fetch(`${API_URL}?action=get_users`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            usersList.innerHTML = `<div class="text-center p-5 text-danger small"><i class="fas fa-exclamation-circle"></i> Load Failed: ${data.message || 'Unknown error'}</div>`;
                            return;
                        }
                        let html = '';
                        if (data.users.length === 0) {
                            html = '<div class="text-center p-5 text-muted small">ไม่มีรายการแชท</div>';
                        } else {
                            data.users.forEach(u => {
                                const userKey = 'u_' + u.id;
                                const msgContent = u.last_msg || 'ส่งรูปภาพ';

                                if (!isFirstLoad && u.unread > 0 && lastMsgMap[userKey] !== msgContent) {
                                    speakText(`มีข้อความจากคุณ ${u.username} ว่า ${msgContent}`);
                                }

                                lastMsgMap[userKey] = msgContent;

                                const isActive = (u.id == currentUserId) ? 'active' : '';
                                const initial = u.username.charAt(0).toUpperCase();
                                const badge = u.unread > 0 ? `<span class="u-badge ms-auto">${u.unread}</span>` : '';
                                const timeStr = new Date(u.last_msg_time).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                                html += `
                        <div class="user-item ${isActive}" onclick="selectUser(${u.id}, '${u.username}')">
                            <div class="u-avatar">${initial}</div>
                            <div class="flex-grow-1 overflow-hidden" style="min-width:0;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-truncate">${u.username}</span>
                                    <small class="text-secondary" style="font-size:0.7rem;">${timeStr}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="small text-secondary text-truncate" style="max-width: 70%;">${u.last_msg}</div>
                                    ${badge}
                                </div>
                            </div>
                        </div>`;
                            });
                        }
                        usersList.innerHTML = html;
                        isFirstLoad = false;
                    })
                    .catch(err => {
                        usersList.innerHTML = `<div class="text-center p-5 text-danger small"><i class="fas fa-wifi"></i> Connection Error</div>`;
                        console.error(err);
                    });
            }

            window.selectUser = function (id, name) {
                if (currentUserId == id) return;
                currentUserId = id;
                lastChatData = null;
                document.getElementById('currentUserId').value = id;
                document.getElementById('headerName').innerText = name;
                document.getElementById('headerAvatar').innerText = name.charAt(0).toUpperCase();
                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('chatContent').style.display = 'flex';
                messageArea.innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-primary"></div></div>';
                layout.classList.add('chat-active');
                loadMessages();
                loadUsers();
            };

            window.backToList = function () {
                layout.classList.remove('chat-active');
                currentUserId = null;
                lastChatData = null;
            };

            function loadMessages() {
                if (!currentUserId) return;
                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN); // ✅ CSRF
                formData.append('action', 'fetch_chat');
                formData.append('user_id', currentUserId);

                fetch(API_URL, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) return;
                        if (JSON.stringify(data.messages) === JSON.stringify(lastChatData)) return;
                        lastChatData = data.messages;

                        let html = '';
                        data.messages.forEach(msg => {
                            const cls = (msg.sender === 'admin') ? 'admin' : 'user';
                            const aiBadge = (msg.is_ai == 1) ? '<span class="badge bg-success bg-opacity-25 text-success border border-success rounded-pill me-2" style="font-size:0.6rem">AI</span>' : '';
                            let content = '';
                            if (msg.image_path) content += `<a href="../${msg.image_path}" target="_blank"><img src="../${msg.image_path}" class="chat-image"></a>`;
                            if (msg.message) content += `<div>${msg.message}</div>`;
                            html += `<div class="msg-row ${cls}"><div class="msg-bubble">${(cls == 'admin' && msg.is_ai == 1) ? aiBadge : ''}${content}</div></div>`;
                        });

                        const isAtBottom = messageArea.scrollHeight - messageArea.scrollTop <= messageArea.clientHeight + 150;
                        messageArea.innerHTML = html;
                        if (isAtBottom || messageArea.scrollTop === 0) messageArea.scrollTop = messageArea.scrollHeight;
                    });
            }

            adminImgInput.addEventListener('change', () => {
                if (adminImgInput.files.length > 0) adminFilePreview.innerHTML = `<i class="fas fa-image me-1"></i> ${adminImgInput.files[0].name}`;
                else adminFilePreview.innerText = '';
            });

            document.getElementById('replyForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const input = document.getElementById('replyInput');
                const msg = input.value.trim();
                const file = adminImgInput.files[0];
                if ((!msg && !file) || !currentUserId) return;

                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN); // ✅ CSRF
                formData.append('action', 'send_reply');
                formData.append('user_id', currentUserId);
                formData.append('message', msg);
                if (file) formData.append('image', file);

                input.value = '';
                adminImgInput.value = '';
                adminFilePreview.innerText = '';

                messageArea.insertAdjacentHTML('beforeend', `<div class="msg-row admin"><div class="msg-bubble text-white-50 small"><i>กำลังส่ง...</i></div></div>`);
                messageArea.scrollTop = messageArea.scrollHeight;

                fetch(API_URL, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            lastChatData = null;
                            loadMessages();
                            loadSuggestions();
                        }
                    });
            });

            loadUsers();
            setInterval(loadUsers, 5000);
            setInterval(loadMessages, 3000);
        });

        window.openSimulationModal = async function () {
            const currentUserIdInput = document.getElementById('currentUserId');
            if (!currentUserIdInput || !currentUserIdInput.value) {
                Swal.fire({ icon: 'warning', title: 'กรุณาเลือกแชท', text: 'โปรดเลือกแชทลูกค้าทางซ้ายมือก่อนใช้งานฟังก์ชันนี้', confirmButtonColor: '#6366f1' });
                return;
            }
            const currentUserId = currentUserIdInput.value;

            const { value: formValues } = await Swal.fire({
                title: '🛠️ จำลองบทสนทนา',
                html: `
                <div class="text-start fs-6" style="color: #334155;">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">ต้องการเพิ่มข้อความในฐานะ:</label>
                        <div class="d-flex gap-2 justify-content-center">
                            <input type="radio" class="btn-check" name="simSender" id="simUser" value="user" checked>
                            <label class="btn btn-outline-primary flex-fill" for="simUser"><i class="fas fa-user me-1"></i> ลูกค้า</label>
                            <input type="radio" class="btn-check" name="simSender" id="simAdmin" value="admin">
                            <label class="btn btn-outline-warning flex-fill" for="simAdmin"><i class="fas fa-user-shield me-1"></i> แอดมิน</label>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted fw-bold">ข้อความ:</label>
                        <textarea id="simMessage" class="form-control" rows="3" placeholder="พิมพ์ข้อความที่ต้องการเพิ่มลงในประวัติ..."></textarea>
                    </div>
                </div>
            `,
                showCancelButton: true, confirmButtonText: '<i class="fas fa-save"></i> บันทึกข้อมูล', cancelButtonText: 'ยกเลิก', focusConfirm: false, confirmButtonColor: '#0f172a',
                preConfirm: () => {
                    const message = document.getElementById('simMessage').value;
                    const sender = document.querySelector('input[name="simSender"]:checked').value;
                    if (!message.trim()) { Swal.showValidationMessage('กรุณาพิมพ์ข้อความก่อนบันทึก'); return false; }
                    return { sender: sender, message: message }
                }
            });

            if (formValues) {
                Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN); // ✅ CSRF
                formData.append('action', 'simulate_chat');
                formData.append('user_id', currentUserId);
                formData.append('sender', formValues.sender);
                formData.append('message', formValues.message);

                fetch('../controller/admin_controller/admin_chat_api.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true });
                            Toast.fire({ icon: 'success', title: 'บันทึกข้อมูลเรียบร้อย' });

                            const headerName = document.getElementById('headerName').innerText;
                            if (typeof selectUser === 'function') selectUser(currentUserId, headerName);
                            else if (typeof loadMessages === 'function') loadMessages();

                            if (typeof loadSuggestions === 'function') loadSuggestions();
                        } else { Swal.fire('Error', data.message || 'บันทึกไม่สำเร็จ', 'error'); }
                    })
                    .catch(err => { Swal.fire('Error', 'การเชื่อมต่อขัดข้อง', 'error'); });
            }
        };
    </script>
</body>

</html>