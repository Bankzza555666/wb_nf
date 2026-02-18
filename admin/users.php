<?php require_once './controller/admin_controller/admin_config.php';
checkAdminAuth(); ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-body: #000000;
            --card-bg: rgba(10, 10, 10, 0.8);
            --border: rgba(229, 9, 20, 0.2);
            --text: #ffffff;
            --accent: #E50914;
        }

        body {
            background: var(--bg-body);
            color: var(--text);
            font-family: 'Prompt', sans-serif;
        }

        /* Toolbar */
        .toolbar-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .search-box {
            position: relative;
        }

        .form-control,
        .form-select {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border);
            color: #fff;
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(15, 23, 42, 1);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
        }

        /* Auto Complete Dropdown */
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid var(--accent);
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            margin-top: 5px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: 0.2s;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
        }

        .autocomplete-item:hover {
            background: rgba(99, 102, 241, 0.2);
        }

        .autocomplete-item strong {
            color: #fff;
        }

        .autocomplete-item span {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        /* Table */
        .data-table {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            white-space: nowrap;
            color: var(--text);
            --bs-table-bg: transparent;
        }

        .table th {
            background-color: rgba(15, 23, 42, 0.9) !important;
            color: #94a3b8;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
            padding: 15px;
        }

        /* Modal */
        .modal-content {
            background-color: #1e293b;
            border: 1px solid var(--border);
            color: #fff;
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border);
        }

        .btn-close {
            filter: invert(1);
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-white mb-0"><i class="fas fa-users text-primary me-2"></i>จัดการสมาชิก</h3>
        </div>

        <div class="toolbar-card">
            <div class="row g-3">
                <div class="col-md-8 position-relative">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary text-secondary"><i
                                class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control border-secondary"
                            placeholder="พิมพ์ชื่อ หรือ อีเมล เพื่อค้นหา..." autocomplete="off">
                    </div>
                    <div id="autocompleteBox" class="autocomplete-list"></div>
                </div>

                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary text-secondary"><i
                                class="fas fa-sort"></i></span>
                        <select id="sortSelect" class="form-select border-secondary" onchange="loadUsers()">
                            <option value="newest">สมัครล่าสุด (Newest)</option>
                            <option value="oldest">สมัครเก่าสุด (Oldest)</option>
                            <option value="credit_high">เครดิตมาก -> น้อย</option>
                            <option value="credit_low">เครดิตน้อย -> มาก</option>
                            <option value="rentals_high">ยอดเช่ามากสุด</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ข้อมูลสมาชิก</th>
                            <th>เครดิต</th>
                            <th>การเช่า (ครั้ง)</th>
                            <th>สถานะ</th>
                            <th>วันที่สมัคร</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="listData">
                        <tr>
                            <td colspan="7" class="text-center py-5 text-secondary">กำลังโหลด...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">แก้ไขข้อมูล</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formData"><input type="hidden" name="action" value="save_user"><input type="hidden"
                            id="eid" name="id">
                        <div class="mb-3"><label class="text-secondary small">Username</label><input type="text"
                                class="form-control" id="eusername" disabled></div>
                        <div class="mb-3"><label class="text-secondary small">Email</label><input type="email"
                                class="form-control" id="eemail" name="email"></div>
                        <div class="mb-3"><label class="text-secondary small">Credit</label><input type="number"
                                step="0.01" class="form-control" id="ecredit" name="credit"></div>
                        <div class="mb-3"><label class="text-secondary small">Role</label><select class="form-select"
                                id="erole" name="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select></div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary rounded-pill"
                        data-bs-dismiss="modal">ปิด</button><button type="button"
                        class="btn btn-primary rounded-pill px-4" onclick="saveData()">บันทึก</button></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const API = '../controller/admin_controller/admin_api.php';
        let searchTimeout;

        // 1. Main Load Function
        function loadUsers(customSearch = null) {
            const search = customSearch !== null ? customSearch : document.getElementById('searchInput').value;
            const sort = document.getElementById('sortSelect').value;

            // Call API
            fetch(`${API}?action=get_users&search=${encodeURIComponent(search)}&sort=${sort}`)
                .then(r => r.json())
                .then(d => {
                    let html = '';
                    if (d.data.length === 0) {
                        html = '<tr><td colspan="7" class="text-center py-5 text-secondary">ไม่พบข้อมูล</td></tr>';
                    } else {
                        d.data.forEach(i => {
                            html += `<tr>
                            <td class="text-secondary">#${i.id}</td>
                            <td>
                                <div class="fw-bold text-white">${i.username}</div>
                                <div class="small text-secondary">${i.email}</div>
                            </td>
                            <td class="text-warning fw-bold">฿${parseFloat(i.credit).toFixed(2)}</td>
                            <td class="text-center"><span class="badge bg-info bg-opacity-25 text-info border border-info rounded-pill">${i.rental_count}</span></td>
                            <td><span class="badge bg-${i.role == 'admin' ? 'danger' : 'success'} rounded-pill bg-opacity-25 border border-${i.role == 'admin' ? 'danger' : 'success'} text-${i.role == 'admin' ? 'danger' : 'success'}">${i.role}</span></td>
                            <td class="text-secondary small">${new Date(i.register_at).toLocaleDateString('th-TH')}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning me-1" onclick="edit('${i.id}','${i.username}','${i.email}','${i.credit}','${i.role}')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="del(${i.id})"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>`;
                        });
                    }
                    document.getElementById('listData').innerHTML = html;
                });
        }

        // 2. Auto Complete Logic
        const searchInput = document.getElementById('searchInput');
        const autoBox = document.getElementById('autocompleteBox');

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length === 0) {
                autoBox.style.display = 'none';
                loadUsers(); // Reset to show all
                return;
            }

            // Debounce 300ms (รอหยุดพิมพ์ค่อยค้นหา)
            searchTimeout = setTimeout(() => {
                fetch(`${API}?action=get_users&search=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(d => {
                        let html = '';
                        if (d.data.length > 0) {
                            d.data.forEach(u => {
                                html += `<div class="autocomplete-item" onclick="selectUser('${u.username}')">
                                        <strong>${u.username}</strong>
                                        <span>${u.email}</span>
                                     </div>`;
                            });
                            autoBox.innerHTML = html;
                            autoBox.style.display = 'block';
                        } else {
                            autoBox.style.display = 'none';
                        }
                        // อัปเดตตารางทันทีที่พิมพ์ด้วย
                        loadUsers(query);
                    });
            }, 300);
        });

        // เมื่อคลิกเลือกจาก Auto Complete
        window.selectUser = function (username) {
            searchInput.value = username;
            autoBox.style.display = 'none';
            loadUsers(username);
        };

        // ซ่อน Auto complete เมื่อคลิกที่อื่น
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !autoBox.contains(e.target)) {
                autoBox.style.display = 'none';
            }
        });

        // ... (ฟังก์ชัน Edit, Save, Delete เหมือนเดิม) ...
        function edit(id, usr, em, cr, ro) {
            document.getElementById('eid').value = id; document.getElementById('eusername').value = usr;
            document.getElementById('eemail').value = em; document.getElementById('ecredit').value = cr;
            document.getElementById('erole').value = ro; new bootstrap.Modal(document.getElementById('modalForm')).show();
        }
        function saveData() {
            fetch(API, { method: 'POST', body: new FormData(document.getElementById('formData')) }).then(r => r.json()).then(d => {
                if (d.success) { bootstrap.Modal.getInstance(document.getElementById('modalForm')).hide(); loadUsers(); Swal.fire('สำเร็จ', '', 'success'); } else { Swal.fire('Error', d.message, 'error'); }
            });
        }
        function del(id) {
            Swal.fire({ title: 'ยืนยันลบ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบ' }).then((r) => {
                if (r.isConfirmed) { let fd = new FormData(); fd.append('action', 'delete_user'); fd.append('id', id); fetch(API, { method: 'POST', body: fd }).then(() => { loadUsers(); Swal.fire('ลบแล้ว', '', 'success'); }); }
            });
        }

        // Initial Load
        loadUsers();
    </script>
</body>

</html>