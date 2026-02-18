<?php
// admin/notifications.php
// Admin Notification Management Interface (Full Page Structure)

require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการแจ้งเตือน - Admin</title>

    <!-- Dependencies -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Base Theme Overrides to match Admin structure */
        :root {
            --bg-body: #000000;
            --glass-bg: rgba(15, 23, 42, 0.8);
            --glass-border: rgba(255, 255, 255, 0.08);
            --primary-red: #E50914;
            --primary-red-hover: #b20610;
            --text-main: #ffffff;
            --text-sub: #94a3b8;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Prompt', sans-serif;
            color: var(--text-main);
            overflow-x: hidden;
            padding-bottom: 80px;
            /* Space for bottom navbar */
        }

        /* Animation */
        .fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .text-gradient-icon {
            background: linear-gradient(135deg, #E50914, #ff4d4d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 15px rgba(229, 9, 20, 0.5));
        }

        /* Glass Cards */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
        }

        .card-glow {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(229, 9, 20, 0.08) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* Inputs */
        .premium-input {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s;
        }

        .premium-input:focus {
            background: rgba(0, 0, 0, 0.5);
            border-color: var(--primary-red);
            box-shadow: 0 0 0 4px rgba(229, 9, 20, 0.1);
            color: white;
            outline: none;
        }

        .ls-1 {
            letter-spacing: 1px;
        }

        /* Type Selector Radio Cards */
        .type-selector input {
            display: none;
        }

        .selector-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-sub);
            height: 100%;
            justify-content: center;
        }

        .type-selector input:checked+.selector-box {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05), rgba(0, 0, 0, 0));
            border-color: var(--accent-color);
            color: var(--accent-color);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            transform: translateY(-4px);
        }

        .selector-box i {
            font-size: 1.8rem;
            transition: transform 0.3s;
            margin-bottom: 8px;
        }

        .type-selector:hover .selector-box i {
            transform: scale(1.15);
        }

        /* Button */
        .btn-primary-gradient {
            background: linear-gradient(135deg, #E50914, #99060d);
            border: none;
            color: white;
            position: relative;
            overflow: hidden;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
        }

        .btn-glass-danger {
            color: #ff4d4d;
            border-color: rgba(255, 77, 77, 0.3);
            background: rgba(255, 77, 77, 0.05);
            transition: all 0.3s;
        }

        .btn-glass-danger:hover {
            background: rgba(255, 77, 77, 0.15);
            border-color: #ff4d4d;
            color: white;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.2);
        }

        /* Table */
        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-color: #cbd5e1;
        }

        .table-custom th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #64748b;
            padding-bottom: 1rem;
        }

        .table-custom td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            padding: 1.2rem 0.5rem;
            vertical-align: middle;
            transition: background 0.2s;
        }

        .table-custom tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Animations */
        .list-item {
            animation: slideIn 0.4s ease-out forwards;
            opacity: 0;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .scroll-y-custom::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-y-custom::-webkit-scrollbar-track {
            background: transparent;
        }

        .scroll-y-custom::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }

        /* Responsive Fixes */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 1rem !important;
            }

            .glass-card {
                padding: 1rem;
                border-radius: 16px;
            }

            .type-selector .selector-box {
                padding: 10px;
            }

            .selector-box i {
                font-size: 1.4rem;
            }

            .selector-box span {
                font-size: 0.8rem;
            }

            .th-target,
            .td-target {
                display: none;
            }
        }

        /* Fix Bootstrap Dark Mode Text Colors */
        .glass-card .text-muted {
            color: #cbd5e1 !important;
        }

        .glass-card .text-secondary {
            color: #94a3b8 !important;
        }

        .glass-card td,
        .glass-card th {
            color: #e2e8f0;
        }

        /* Modal Glass */
        .modal-content.glass-card {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>

    <!-- Include Admin Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container py-5 fade-in-up">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
            <div>
                <h2 class="text-white fw-bold mb-1 display-6">
                    <i class="fas fa-bell me-3 text-gradient-icon"></i>จัดการการแจ้งเตือน
                </h2>
                <p class="text-white-50 mb-0 ps-1">สร้างประกาศและจัดการการแจ้งเตือนในระบบ</p>
            </div>
            <button class="btn btn-outline-danger btn-glass-danger px-4 py-2 rounded-pill fw-bold" id="btnClearAll">
                <i class="fas fa-trash-alt me-2"></i> ล้างประวัติทั้งหมด
            </button>
        </div>

        <div class="row g-4">
            <!-- 1. Create Announcement Card -->
            <div class="col-lg-4">
                <div class="glass-card h-100 position-relative overflow-hidden p-4">
                    <div class="card-glow"></div>
                    <div class="position-relative z-1">
                        <div class="d-flex align-items-center mb-4">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                                <i class="fas fa-paper-plane text-danger fa-lg"></i>
                            </div>
                            <h5 class="text-white mb-0 fw-bold">สร้างประกาศใหม่</h5>
                        </div>

                        <form id="createNotifyForm">
                            <div class="mb-4">
                                <label
                                    class="form-label text-light small fw-bold text-uppercase ls-1">หัวข้อประกาศ</label>
                                <input type="text" class="form-control premium-input" name="title" required
                                    placeholder="Ex. ปิดปรับปรุงระบบชั่วคราว">
                            </div>

                            <div class="mb-4">
                                <label
                                    class="form-label text-light small fw-bold text-uppercase ls-1">รายละเอียด</label>
                                <textarea class="form-control premium-input" name="message" rows="4" required
                                    placeholder="รายละเอียดเพิ่มเติม..."></textarea>
                            </div>

                            <div class="mb-4">
                                <label
                                    class="form-label text-light small fw-bold text-uppercase ls-1 mb-3">ประเภทแจ้งเตือน</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="type-selector w-100" style="--accent-color: #3b82f6;">
                                            <input type="radio" name="type" value="info">
                                            <div class="selector-box">
                                                <i class="fas fa-info-circle"></i>
                                                <span>General</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <label class="type-selector w-100" style="--accent-color: #10b981;">
                                            <input type="radio" name="type" value="success">
                                            <div class="selector-box">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Success</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <label class="type-selector w-100" style="--accent-color: #E50914;">
                                            <input type="radio" name="type" value="announcement" checked>
                                            <div class="selector-box">
                                                <i class="fas fa-bullhorn"></i>
                                                <span>Announce</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <label class="type-selector w-100" style="--accent-color: #f59e0b;">
                                            <input type="radio" name="type" value="warning">
                                            <div class="selector-box">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>Warning</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit"
                                class="btn btn-primary-gradient w-100 fw-bold py-3 rounded-4 shadow-lg hover-scale">
                                <i class="fas fa-paper-plane me-2"></i>ส่งประกาศถึงทุกคน
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 2. Notification List -->
            <div class="col-lg-8">
                <div class="glass-card h-100 position-relative p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                <i class="fas fa-history text-primary fa-lg"></i>
                            </div>
                            <h5 class="text-white mb-0 fw-bold">ประวัติล่าสุด</h5>
                        </div>
                        <button class="btn btn-icon-glass rounded-circle" onclick="loadNotifications()" title="รีเฟรช">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>

                    <div class="scroll-y-custom" style="max-height: 650px; overflow-y: auto; padding-right: 5px;">
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="15%">Type</th>
                                        <th width="35%">Message</th>
                                        <th width="20%" class="th-target">Target</th>
                                        <th width="20%">When</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="notifyTableBody" class="list-container">
                                    <!-- JS Populated -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white"><i class="fas fa-edit me-2"></i>แก้ไขประกาศ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editNotifyForm">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label text-light small fw-bold">หัวข้อประกาศ</label>
                            <input type="text" class="form-control premium-input" name="title" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light small fw-bold">รายละเอียด</label>
                            <textarea class="form-control premium-input" name="message" id="edit_message" rows="4"
                                required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light small fw-bold mb-3">ประเภทแจ้งเตือน</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="type-selector w-100" style="--accent-color: #3b82f6;">
                                        <input type="radio" name="type" value="info" id="edit_type_info">
                                        <div class="selector-box">
                                            <i class="fas fa-info-circle"></i>
                                            <span>General</span>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="type-selector w-100" style="--accent-color: #10b981;">
                                        <input type="radio" name="type" value="success" id="edit_type_success">
                                        <div class="selector-box">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Success</span>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="type-selector w-100" style="--accent-color: #E50914;">
                                        <input type="radio" name="type" value="announcement"
                                            id="edit_type_announcement">
                                        <div class="selector-box">
                                            <i class="fas fa-bullhorn"></i>
                                            <span>Announce</span>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="type-selector w-100" style="--accent-color: #f59e0b;">
                                        <input type="radio" name="type" value="warning" id="edit_type_warning">
                                        <div class="selector-box">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span>Warning</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary-gradient px-4" id="btnSaveEdit">บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    let editModal;
    
    document.addEventListener('DOMContentLoaded', () => {
        editModal = new bootstrap.Modal(document.getElementById('editModal'));
        loadNotifications();

        // Create Form
        document.getElementById('createNotifyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            handleFormSubmit(e.target, 'create');
        });
        
        // Save Edit
        document.getElementById('btnSaveEdit').addEventListener('click', async () => {
            const form = document.getElementById('editNotifyForm');
            handleFormSubmit(form, 'update', () => {
                editModal.hide();
            });
        });

        // Clear All
        document.getElementById('btnClearAll').addEventListener('click', () => {
            Swal.fire({
                title: 'ล้างประวัติทั้งหมด?',
                text: "การแจ้งเตือนทั้งหมดในระบบจะถูกลบถาวร!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E50914',
                cancelButtonColor: '#334155',
                confirmButtonText: 'ใช่, ลบทั้งหมด',
                cancelButtonText: 'ยกเลิก',
                background: '#0f172a',
                color: '#fff'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'clear_all');
                        await fetch('controller/admin_controller/admin_notify_api.php', { method: 'POST', body: formData });
                        
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            background: '#0f172a',
                            color: '#fff'
                        })
                        Toast.fire({ icon: 'success', title: 'ล้างข้อมูลเรียบร้อยแล้ว' });
                        loadNotifications();
                    } catch(err) { console.error(err); }
                }
            });
        });
    });

    async function handleFormSubmit(form, action, onSuccess = null) {
        const formData = new FormData(form);
        formData.append('action', action);
        
        try {
            const res = await fetch('controller/admin_controller/admin_notify_api.php', {
                method: 'POST',
                body: formData
            });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch(e) { throw new Error(text); }
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: data.message || 'ดำเนินการเรียบร้อย',
                    timer: 1500,
                    showConfirmButton: false,
                    background: '#0f172a',
                    color: '#fff',
                    iconColor: '#10b981'
                });
                if(action === 'create') form.reset();
                if(onSuccess) onSuccess();
                loadNotifications();
            } else {
                throw new Error(data.message || 'Failed');
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message, background: '#0f172a', color: '#fff' });
        }
    }

    async function loadNotifications() {
        const tbody = document.getElementById('notifyTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-danger" role="status"></div></td></tr>';

        try {
            const res = await fetch('controller/admin_controller/admin_notify_api.php?action=get_all');
            const text = await res.text();
            let json = JSON.parse(text);
            
            if (!json.success || !json.data.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5"><div class="opacity-50"><i class="fas fa-bell-slash fa-2x mb-2 text-secondary"></i><p class="text-secondary m-0">ไม่พบข้อมูล</p></div></td></tr>`;
                return;
            }

            let html = '';
            json.data.forEach((item, index) => {
                let icon = '';
                let color = '';
                switch(item.type) {
                    case 'success': icon='fa-check-circle'; color='#10b981'; break;
                    case 'warning': icon='fa-exclamation-triangle'; color='#f59e0b'; break;
                    case 'error': icon='fa-times-circle'; color='#ef4444'; break;
                    default: icon='fa-bullhorn'; color='#E50914'; break;
                }

                const targetDisplay = item.username 
                    ? `<div class="d-flex align-items-center text-info td-target"><div class="status-dot bg-info me-2"></div>${item.username}</div>` 
                    : `<div class="d-flex align-items-center text-danger td-target"><div class="status-dot bg-danger me-2"></div>Global</div>`;

                const date = new Date(item.created_at);
                const timeStr = date.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});
                const dateStr = date.toLocaleDateString('th-TH');
                
                // Escape simple quotes for the onclick function
                const safeTitle = item.title.replace(/'/g, "\\'").replace(/"/g, "&quot;");
                const safeMsg = item.message.replace(/'/g, "\\'").replace(/"/g, "&quot;");

                html += `
                    <tr class="list-item" style="animation-delay: ${index * 0.05}s">
                        <td class="text-center text-secondary small">${index + 1}</td>
                        <td>
                            <div class="d-flex align-items-center" style="color: ${color}">
                                <i class="fas ${icon} fa-lg me-2"></i>
                                <span class="small fw-bold text-uppercase ms-2 d-none d-lg-inline">${item.type}</span>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-white mb-1">${item.title}</div>
                            <div class="small text-muted text-truncate" style="max-width: 300px;">${item.message}</div>
                        </td>
                        <td>${targetDisplay}</td>
                        <td>
                            <div class="small text-white">${timeStr}</div>
                            <div class="xs text-muted">${dateStr}</div>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn btn-icon-glass text-warning hover-warning rounded-circle" 
                                    onclick="openEditModal(${item.id}, '${safeTitle}', '${safeMsg}', '${item.type}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-icon-glass text-danger hover-danger rounded-circle" onclick="deleteNotify(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">โหลดข้อมูลไม่สำเร็จ</td></tr>';
        }
    }

    function openEditModal(id, title, message, type) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_message').value = message;
        // Select correct radio
        const radios = document.getElementsByName('type');
        radios.forEach(r => r.checked = false);
        const targetRadio = document.getElementById('edit_type_' + type) || document.getElementById('edit_type_info');
        if(targetRadio) targetRadio.checked = true;
        
        editModal.show();
    }

    async function deleteNotify(id) {
        Swal.fire({
            title: 'ลบรายการนี้?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'ลบ',
            background: '#0f172a',
            color: '#fff'
        }).then(async (result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                await fetch('controller/admin_controller/admin_notify_api.php', { method: 'POST', body: formData });
                loadNotifications();
            }
        }); 
    }
    </script>
    <style>
        .hover-warning:hover {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.3);
        }

        /* Previously defined styles inherited */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .btn-icon-glass {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            transition: all 0.2s;
        }

        .btn-icon-glass:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: rotate(15deg);
        }

        .hover-danger:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .xs {
            font-size: 0.7rem;
        }
    </style>
</body>

</html>