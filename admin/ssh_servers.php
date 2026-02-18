<?php
require_once './controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ SSH Servers - Admin</title>
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">
    <style>
        /* ... (existing styles) ... */

        /* ... inside form ... */
        <div class="col-md-4"><label class="form-label text-secondary">Location</label><input type="text" class="form-control" name="location" id="elocation"
        placeholder="Thailand"></div><div class="col-md-4"><label class="form-label text-secondary">Country Flag</label><div class="input-group"><span class="input-group-text bg-dark border-secondary text-light" id="flag-preview"><i class="fas fa-flag"></i></span><select class="form-select" name="country_code" id="ecountry_code" onchange="updateFlagPreview()"><option value="th">Thailand (TH)</option><option value="sg">Singapore (SG)</option><option value="us">United States (US)</option><option value="jp">Japan (JP)</option><option value="kr">Korea (KR)</option><option value="hk">Hong Kong (HK)</option><option value="vn">Vietnam (VN)</option><option value="in">India (IN)</option><option value="uk">United Kingdom (UK)</option><option value="de">Germany (DE)</option><option value="fr">France (FR)</option><option value="nl">Netherlands (NL)</option><option value="ca">Canada (CA)</option><option value="au">Australia (AU)</option></select></div></div><div class="col-md-4"><label class="form-label text-secondary">Max Users</label><input type="number" class="form-control" name="max_users" id="emax_users" value="500"></div>
        /* ... */

        /* ... inside table script ... */
        document.getElementById('tableBody').innerHTML=data.data.map(s=> ` <tr> <td> <div class="d-flex align-items-center" > <span class="fi fi-${s.country_code || 'th'} me-2 fs-5 rounded" ></span> <div> <strong>$ {
                s.server_name
            }

            </strong><br> <small class="text-secondary" >$ {
                s.server_id
            }

            </small> </div> </div> </td> <td>$ {
                s.server_host
            }

            </td> <td>$ {
                s.ssh_port
            }

            </td> <td>$ {
                s.location || '-'
            }

            </td> <td>$ {
                s.current_users
            }

            /$ {
                s.max_users
            }

            </td> <td><span class="badge badge-${s.status}" >$ {
                s.status
            }

            </span></td> <td class="text-end" > <button class="btn btn-sm btn-outline-success me-1" onclick="createTestUser('${s.server_id}')" title="สร้าง User ทดสอบ" > <i class="fas fa-user-plus" ></i> </button> <button class="btn btn-sm btn-outline-primary me-1" onclick="editData(${s.id})" > <i class="fas fa-edit" ></i> </button> <button class="btn btn-sm btn-outline-danger" onclick="deleteData(${s.id})" > <i class="fas fa-trash" ></i> </button> </td> </tr> `).join('');

        /* ... inside editData ... */
        if (data.success) {
            const s=data.data;
            document.getElementById('eid').value=s.id;
            document.getElementById('eserver_name').value=s.server_name;
            document.getElementById('eserver_id').value=s.server_id;
            document.getElementById('eserver_host').value=s.server_host;
            document.getElementById('essh_port').value=s.ssh_port;
            document.getElementById('eapi_port').value=s.api_port;
            document.getElementById('eadmin_user').value=s.admin_user;
            document.getElementById('elocation').value=s.location || '';
            document.getElementById('ecountry_code').value=s.country_code || 'th';
            document.getElementById('emax_users').value=s.max_users;
            document.getElementById('estatus').value=s.status;
            document.getElementById('enotes').value=s.notes || '';
            updateFlagPreview();
            modal.show();
        }

        /* ... add function ... */
        function updateFlagPreview() {
            const code=document.getElementById('ecountry_code').value;
            const preview=document.getElementById('flag-preview');
            preview.innerHTML=`<span class="fi fi-${code}"></span>`;
        }

        :root {
            --bg-body: #000000;
            --card-bg: rgba(10, 10, 10, 0.8);
            --border-color: rgba(229, 9, 20, 0.2);
            --text-primary: #ffffff;
            --text-secondary: #aaaaaa;
            --accent: #E50914;
        }

        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }

        .card-header {
            background: rgba(229, 9, 20, 0.1);
            border-bottom: 1px solid var(--border-color);
        }

        .table {
            color: var(--text-primary);
        }

        .table td,
        .table th {
            color: var(--text-primary) !important;
        }

        .table>:not(caption)>*>* {
            background-color: transparent !important;
            border-color: var(--border-color);
            color: var(--text-primary) !important;
        }

        .table thead th {
            background-color: rgba(15, 23, 42, 0.8) !important;
            color: var(--text-secondary);
        }

        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
        }

        .btn-primary:hover {
            background: #ff1a1a;
            border-color: #ff1a1a;
        }

        .modal-content {
            background: #1a1a1a;
            border: 1px solid var(--border-color);
        }

        .form-control,
        .form-select {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--border-color);
            color: #fff;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent);
            color: #fff;
        }

        .badge-online {
            background: #10b981;
        }

        .badge-offline {
            background: #ef4444;
        }

        .badge-maintenance {
            background: #f59e0b;
        }

        .btn-outline-success {
            border-color: #10b981 !important;
            color: #10b981 !important;
        }

        .btn-outline-success:hover {
            background: #10b981 !important;
            color: #fff !important;
        }

        .btn-outline-primary {
            border-color: #3b82f6 !important;
            color: #3b82f6 !important;
        }

        .btn-outline-primary:hover {
            background: #3b82f6 !important;
            color: #fff !important;
        }

        .btn-outline-danger {
            border-color: #ef4444 !important;
            color: #ef4444 !important;
        }

        .btn-outline-danger:hover {
            background: #ef4444 !important;
            color: #fff !important;
        }

        .btn-outline-info {
            border-color: #06b6d4 !important;
            color: #06b6d4 !important;
        }

        .btn-outline-info:hover {
            background: #06b6d4 !important;
            color: #fff !important;
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php'))
        include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-white mb-0">จัดการ SSH Servers</h3>
                <p class="text-secondary mb-0">เพิ่ม/แก้ไข SSH Plus Manager Servers</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus me-2"></i>เพิ่ม Server
            </button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อ Server</th>
                                <th>Host</th>
                                <th>SSH Port</th>
                                <th>Location</th>
                                <th>Users</th>
                                <th>สถานะ</th>
                                <th class="text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">กำลังโหลดข้อมูล...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-server me-2 text-danger"></i>ข้อมูล SSH Server</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formData">
                        <input type="hidden" name="action" value="save_ssh_server">
                        <input type="hidden" id="eid" name="id">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary">ชื่อ Server</label>
                                <input type="text" class="form-control" name="server_name" id="eserver_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary">Server ID</label>
                                <input type="text" class="form-control" name="server_id" id="eserver_id" required
                                    placeholder="ssh-th-001">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary">Host (IP/Domain)</label>
                                <input type="text" class="form-control" name="server_host" id="eserver_host" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary">SSH Port</label>
                                <input type="number" class="form-control" name="ssh_port" id="essh_port" value="22">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary">API Port</label>
                                <input type="number" class="form-control" name="api_port" id="eapi_port" value="80">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary">Admin Username</label>
                                <input type="text" class="form-control" name="admin_user" id="eadmin_user" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary">Admin Password</label>
                                <input type="password" class="form-control" name="admin_pass" id="eadmin_pass">
                                <small class="text-secondary">เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary">Location</label>
                                <input type="text" class="form-control" name="location" id="elocation"
                                    placeholder="Thailand">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary">Max Users</label>
                                <input type="number" class="form-control" name="max_users" id="emax_users" value="500">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary">สถานะ</label>
                                <select class="form-select" name="status" id="estatus">
                                    <option value="online">Online</option>
                                    <option value="offline">Offline</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary">หมายเหตุ</label>
                                <textarea class="form-control" name="notes" id="enotes" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" onclick="testConnection()">
                        <i class="fas fa-plug me-2"></i>ทดสอบเชื่อมต่อ
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveData()">
                        <i class="fas fa-save me-2"></i>บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('modalForm'));

        document.addEventListener('DOMContentLoaded', loadData);

        function loadData() {
            fetch('../controller/admin_controller/ssh_server_controller.php?action=get_servers')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        document.getElementById('tableBody').innerHTML = data.data.map(s => `
                            <tr>
                                <td><strong>${s.server_name}</strong><br><small class="text-secondary">${s.server_id}</small></td>
                                <td>${s.server_host}</td>
                                <td>${s.ssh_port}</td>
                                <td>${s.location || '-'}</td>
                                <td>${s.current_users}/${s.max_users}</td>
                                <td><span class="badge badge-${s.status}">${s.status}</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-info me-1" onclick="openTerminal('${s.server_id}')" title="เปิด Terminal">
                                        <i class="fas fa-terminal"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success me-1" onclick="createTestUser('${s.server_id}')" title="สร้าง User ทดสอบ">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editData(${s.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteData(${s.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        document.getElementById('tableBody').innerHTML = '<tr><td colspan="7" class="text-center py-5 text-secondary">ยังไม่มีข้อมูล Server</td></tr>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('tableBody').innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
                });
        }

        function openModal() {
            document.getElementById('formData').reset();
            document.getElementById('eid').value = '';
            modal.show();
        }

        function editData(id) {
            fetch(`../controller/admin_controller/ssh_server_controller.php?action=get_server&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const s = data.data;
                        document.getElementById('eid').value = s.id;
                        document.getElementById('eserver_name').value = s.server_name;
                        document.getElementById('eserver_id').value = s.server_id;
                        document.getElementById('eserver_host').value = s.server_host;
                        document.getElementById('essh_port').value = s.ssh_port;
                        document.getElementById('eapi_port').value = s.api_port;
                        document.getElementById('eadmin_user').value = s.admin_user;
                        document.getElementById('elocation').value = s.location || '';
                        document.getElementById('emax_users').value = s.max_users;
                        document.getElementById('estatus').value = s.status;
                        document.getElementById('enotes').value = s.notes || '';
                        modal.show();
                    }
                });
        }

        function saveData() {
            const formData = new FormData(document.getElementById('formData'));
            fetch('../controller/admin_controller/ssh_server_controller.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('สำเร็จ!', data.message, 'success');
                        modal.hide();
                        loadData();
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                });
        }

        function deleteData(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: 'Server นี้จะถูกลบถาวร',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E50914',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('../controller/admin_controller/ssh_server_controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_ssh_server&id=${id}`
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('ลบสำเร็จ!', '', 'success');
                                loadData();
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                            }
                        });
                }
            });
        }

        function testConnection() {
            const host = document.getElementById('eserver_host').value;
            const port = document.getElementById('essh_port').value;
            const user = document.getElementById('eadmin_user').value;
            const pass = document.getElementById('eadmin_pass').value;

            Swal.fire({
                title: 'กำลังทดสอบ...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('../controller/admin_controller/ssh_server_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=test_connection&host=${host}&port=${port}&user=${user}&pass=${pass}`
            })
                .then(r => r.json())
                .then(data => {
                    Swal.fire(data.success ? 'เชื่อมต่อสำเร็จ!' : 'ไม่สามารถเชื่อมต่อได้', data.message, data.success ? 'success' : 'error');
                });
        }

        function createTestUser(serverId) {
            Swal.fire({
                title: 'สร้าง User ทดสอบ?',
                text: 'จะสร้าง SSH user ทดสอบบน server ' + serverId,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'สร้าง',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังสร้าง...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    const formData = new FormData();
                    formData.append('action', 'create_test_user');
                    formData.append('server_id', serverId);

                    fetch('../controller/admin_controller/ssh_server_controller.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                let message = data.message;
                                if (data.data) {
                                    message += '\n\nUsername: ' + data.data.username + '\nPassword: ' + data.data.password;
                                }
                                Swal.fire('สำเร็จ!', message, 'success');
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถสร้าง user ได้', 'error');
                        });
                }
            });
        }

        function openTerminal(serverId) {
            window.location.href = '?p=admin_web_terminal&server_id=' + serverId;
        }
    </script>
</body>

</html>