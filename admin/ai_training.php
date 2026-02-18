<?php
// admin/ai_training.php
require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
checkAdminAuth();

// ตรวจสอบและสร้างตาราง ai_suggestions ถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS `ai_suggestions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `text` VARCHAR(100) NOT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>AI Training Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-body: #000000;
            --bg-card: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #aaaaaa;
            --accent: #E50914;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Prompt', sans-serif;
        }

        .glass-card {
            background: rgba(25, 25, 25, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
        }

        .table-dark {
            background: transparent;
        }

        .table-dark th,
        .table-dark td {
            background: transparent;
            border-color: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .nav-tabs {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 3px solid transparent;
            background: transparent;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link:hover {
            color: #fff;
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: #fff;
            background: transparent;
            border-bottom: 3px solid var(--accent);
        }

        .suggestion-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 8px 15px;
            margin: 5px;
            font-size: 0.9rem;
        }

        .suggestion-chip .btn-remove {
            background: none;
            border: none;
            color: #ef4444;
            padding: 0;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .suggestion-chip .btn-remove:hover {
            color: #ff6b6b;
        }

        .suggestion-preview {
            background: rgba(0,0,0,0.3);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .preview-chip {
            display: inline-block;
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 20px;
            padding: 8px 16px;
            margin: 5px;
            font-size: 0.85rem;
            color: #fff;
        }

        .add-suggestion-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .add-suggestion-form input {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 10px 15px;
            color: #fff;
        }

        .add-suggestion-form input:focus {
            outline: none;
            border-color: var(--accent);
        }
    </style>
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <h3 class="mb-4"><i class="fas fa-brain text-danger me-2"></i> AI Training Center</h3>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab-rules">
                    <i class="fas fa-robot me-2"></i>กฎการตอบ (Rules)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-suggestions">
                    <i class="fas fa-lightbulb me-2"></i>คำแนะนำ (Suggestions)
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab 1: AI Rules -->
            <div class="tab-pane fade show active" id="tab-rules">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-secondary mb-0">กำหนดคีย์เวิร์ดและคำตอบที่ AI จะตอบทันที</p>
                    <button class="btn btn-danger btn-sm" onclick="openRuleModal()">
                        <i class="fas fa-plus me-1"></i>เพิ่มกฎใหม่
                    </button>
                </div>

                <div class="glass-card">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="25%">คีย์เวิร์ด</th>
                                    <th width="40%">คำตอบ</th>
                                    <th width="10%">รูปแบบ</th>
                                    <th width="10%">Priority</th>
                                    <th width="10%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="rulesTable">
                                <tr><td colspan="6" class="text-center p-4">กำลังโหลด...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 2: AI Suggestions -->
            <div class="tab-pane fade" id="tab-suggestions">
                <div class="glass-card">
                    <h5 class="mb-3"><i class="fas fa-comment-dots text-info me-2"></i>จัดการคำแนะนำในแชท</h5>
                    <p class="text-secondary small mb-4">คำแนะนำเหล่านี้จะแสดงให้ลูกค้าเลือกกดในหน้าแชท</p>

                    <!-- Add New Suggestion -->
                    <div class="add-suggestion-form">
                        <input type="text" id="newSuggestion" placeholder="พิมพ์คำแนะนำใหม่..." maxlength="50">
                        <button class="btn btn-danger" onclick="addSuggestion()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <!-- Current Suggestions -->
                    <div id="suggestionsList" class="mb-4">
                        <div class="text-center text-secondary py-3">กำลังโหลด...</div>
                    </div>

                    <!-- Preview -->
                    <div class="suggestion-preview">
                        <h6 class="text-secondary mb-3"><i class="fas fa-eye me-2"></i>ตัวอย่างที่ลูกค้าจะเห็น:</h6>
                        <div id="suggestionsPreview">
                            <span class="preview-chip">สวัสดีครับ</span>
                            <span class="preview-chip">เติมเงินยังไง</span>
                            <span class="preview-chip">VPN หลุด</span>
                        </div>
                    </div>

                    <!-- Default Toggle -->
                    <div class="mt-4 p-3 rounded" style="background: rgba(255,255,255,0.03);">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="useCustomSuggestions" checked>
                            <label class="form-check-label" for="useCustomSuggestions">
                                ใช้คำแนะนำที่กำหนดเอง (ถ้าปิด จะใช้คำถามยอดฮิตจากลูกค้าแทน)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rule Modal -->
    <div class="modal fade" id="ruleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="ruleModalTitle">เพิ่มกฎใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="ruleForm">
                        <input type="hidden" id="ruleId">
                        <div class="mb-3">
                            <label class="form-label">คีย์เวิร์ด (แยกด้วยจุลภาค ,)</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" id="keywords"
                                placeholder="เช่น ราคา, ค่าบริการ, price" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">คำตอบ</label>
                            <textarea class="form-control bg-secondary text-white border-0" id="response" rows="4"
                                placeholder="AI จะตอบข้อความนี้ทันที..." required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">รูปแบบการจับคู่</label>
                                <select class="form-select bg-secondary text-white border-0" id="matchType">
                                    <option value="fuzzy">ใกล้เคียง (Fuzzy)</option>
                                    <option value="exact">ตรงตัว (Exact)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ความสำคัญ (Priority)</label>
                                <input type="number" class="form-control bg-secondary text-white border-0" id="priority" value="0">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-danger" onclick="saveRule()">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = '../controller/admin_controller/admin_ai_api.php';
        let ruleModal;

        document.addEventListener('DOMContentLoaded', () => {
            ruleModal = new bootstrap.Modal(document.getElementById('ruleModal'));
            loadRules();
            loadSuggestions();
            loadSuggestionSetting();
        });

        // ==================== RULES ====================
        function loadRules() {
            fetch(`${API_URL}?action=get_rules`)
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('rulesTable');
                    if (!data.success || !data.rules || data.rules.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-muted">ยังไม่มีกฎ</td></tr>';
                        return;
                    }
                    let html = '';
                    data.rules.forEach((r, index) => {
                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td><span class="text-info">${escapeHtml(r.keywords)}</span></td>
                                <td class="text-truncate" style="max-width: 250px;">${escapeHtml(r.response)}</td>
                                <td><span class="badge ${r.match_type === 'exact' ? 'bg-warning text-dark' : 'bg-secondary'}">${r.match_type}</span></td>
                                <td>${r.priority}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-light me-1" onclick='editRule(${JSON.stringify(r)})'><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRule(${r.id})"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('rulesTable').innerHTML = '<tr><td colspan="6" class="text-center text-danger">โหลดไม่สำเร็จ</td></tr>';
                });
        }

        function openRuleModal() {
            document.getElementById('ruleForm').reset();
            document.getElementById('ruleId').value = '';
            document.getElementById('ruleModalTitle').innerText = 'เพิ่มกฎใหม่';
            ruleModal.show();
        }

        function editRule(r) {
            document.getElementById('ruleId').value = r.id;
            document.getElementById('keywords').value = r.keywords;
            document.getElementById('response').value = r.response;
            document.getElementById('matchType').value = r.match_type;
            document.getElementById('priority').value = r.priority;
            document.getElementById('ruleModalTitle').innerText = 'แก้ไขกฎ';
            ruleModal.show();
        }

        function saveRule() {
            const id = document.getElementById('ruleId').value;
            const formData = new FormData();
            formData.append('action', id ? 'update_rule' : 'add_rule');
            if (id) formData.append('id', id);
            formData.append('keywords', document.getElementById('keywords').value);
            formData.append('response', document.getElementById('response').value);
            formData.append('match_type', document.getElementById('matchType').value);
            formData.append('priority', document.getElementById('priority').value);

            fetch(API_URL, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        ruleModal.hide();
                        loadRules();
                        Swal.fire({ icon: 'success', title: 'บันทึกแล้ว', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }

        function deleteRule(id) {
            Swal.fire({
                title: 'ยืนยันลบ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบเลย'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_rule');
                    formData.append('id', id);
                    fetch(API_URL, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) loadRules();
                        });
                }
            });
        }

        // ==================== SUGGESTIONS ====================
        function loadSuggestions() {
            fetch(`${API_URL}?action=get_suggestions`)
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('suggestionsList');
                    const preview = document.getElementById('suggestionsPreview');
                    
                    if (!data.success || !data.suggestions || data.suggestions.length === 0) {
                        container.innerHTML = '<div class="text-center text-secondary py-3">ยังไม่มีคำแนะนำ</div>';
                        preview.innerHTML = '<span class="text-secondary">ไม่มีคำแนะนำ</span>';
                        return;
                    }

                    container.innerHTML = data.suggestions.map(s => `
                        <div class="suggestion-chip">
                            <span>${escapeHtml(s.text)}</span>
                            <button class="btn-remove" onclick="deleteSuggestion(${s.id})"><i class="fas fa-times"></i></button>
                        </div>
                    `).join('');

                    preview.innerHTML = data.suggestions.map(s => `
                        <span class="preview-chip">${escapeHtml(s.text)}</span>
                    `).join('');
                });
        }

        function addSuggestion() {
            const input = document.getElementById('newSuggestion');
            const text = input.value.trim();
            if (!text) return;

            const formData = new FormData();
            formData.append('action', 'add_suggestion');
            formData.append('text', text);

            fetch(API_URL, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        input.value = '';
                        loadSuggestions();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }

        function deleteSuggestion(id) {
            const formData = new FormData();
            formData.append('action', 'delete_suggestion');
            formData.append('id', id);

            fetch(API_URL, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) loadSuggestions();
                });
        }

        function loadSuggestionSetting() {
            fetch(`${API_URL}?action=get_suggestion_setting`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('useCustomSuggestions').checked = data.use_custom === '1';
                });
        }

        document.getElementById('useCustomSuggestions').addEventListener('change', function() {
            const formData = new FormData();
            formData.append('action', 'save_suggestion_setting');
            formData.append('use_custom', this.checked ? '1' : '0');
            fetch(API_URL, { method: 'POST', body: formData });
        });

        // Enter key to add suggestion
        document.getElementById('newSuggestion').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') addSuggestion();
        });

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }
    </script>
</body>

</html>
