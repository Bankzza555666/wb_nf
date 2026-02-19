<?php require_once './controller/admin_controller/admin_config.php';
checkAdminAuth(); ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเซิร์ฟเวอร์ - V2BOX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
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
            font-family: 'Prompt', sans-serif;
        }

        .card-custom {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        /* Table Styles */
        .data-table {
            overflow: hidden;
            border-radius: 16px;
        }

        .table {
            margin-bottom: 0;
            white-space: nowrap;
            color: var(--text-primary);
            --bs-table-bg: transparent;
            --bs-table-hover-bg: rgba(255, 255, 255, 0.05);
            --bs-table-hover-color: #fff;
            border-color: var(--border-color);
        }

        .table thead th {
            background-color: rgba(15, 23, 42, 0.8) !important;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-color);
            padding: 15px;
        }

        .table tbody td {
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            padding: 15px;
        }

        .table tbody tr:last-child td {
            border-bottom: 0;
        }

        /* Form Styles */
        .form-control,
        .form-select {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            color: #fff;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
        }

        .modal-content {
            background-color: #1e293b;
            border: 1px solid var(--border-color);
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border-color);
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
            <div>
                <h3 class="fw-bold text-white mb-0">จัดการเซิร์ฟเวอร์</h3>
                <span class="text-secondary small">Server Nodes Management</span>
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="add()">
                <i class="fas fa-plus me-2"></i>เพิ่มเซิร์ฟเวอร์
            </button>
        </div>

        <div class="card-custom p-0 data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Server Name</th>
                            <th>Location</th>
                            <th>Endpoint</th>
                            <th>Path</th>
                            <th>User / Pass</th>
                            <th>Status</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="listData">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-secondary">กำลังโหลดข้อมูล...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content text-white">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-server me-2"></i>ข้อมูลเซิร์ฟเวอร์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formData">
                        <input type="hidden" name="action" value="save_server">
                        <input type="hidden" id="eid" name="id">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Server Name</label>
                                <input type="text" class="form-control" name="server_name" id="ename"
                                    placeholder="เช่น TH-01 VIP" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Location</label>
                                <input type="text" class="form-control" name="server_location" id="eloc"
                                    placeholder="เช่น Thailand">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Max Clients</label>
                                <input type="number" class="form-control" name="max_clients" id="emax"
                                    placeholder="0 = Unlimited" value="100">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-secondary small">IP Address / Domain</label>
                                <input type="text" class="form-control font-monospace" name="server_ip" id="eip"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Port</label>
                                <input type="number" class="form-control font-monospace" name="server_port" id="eport"
                                    value="2053">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Web Path</label>
                                <input type="text" class="form-control font-monospace" name="server_path" id="epath"
                                    value="/">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-secondary small">Username (X-UI)</label>
                                <input type="text" class="form-control" name="server_username" id="euser">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary small">Password</label>
                                <input type="text" class="form-control" name="server_password" id="epass">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-secondary small">Status</label>
                                <select class="form-select" name="server_status" id="estatus">
                                    <option value="online">🟢 Online</option>
                                    <option value="maintenance">🟠 Maintenance</option>
                                    <option value="offline">🔴 Offline</option>
                                </select>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4"
                        onclick="saveData()">บันทึกข้อมูล</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // เรียก API ผ่าน index.php เพื่อให้ session และ path ตรงกับระบบ
        const API = 'index.php?p=admin_api';

        function load() {
            fetch(API + '&action=get_servers')
                .then(function(r) { return r.text().then(function(text) { return { ok: r.ok, status: r.status, text: text }; }); })
                .then(function(res) {
                    var d;
                    try { d = JSON.parse(res.text); } catch (e) {
                        throw new Error(res.ok ? 'ตอบกลับไม่ใช่ JSON' : 'HTTP ' + res.status + (res.text ? ': ' + res.text.substring(0, 100) : ''));
                    }
                    if (!d.success && d.message) throw new Error(d.message);
                    if (!res.ok) throw new Error(d.message || ('HTTP ' + res.status));
                    var list = d.data || [];
                    var h = '';
                    if (list.length === 0) {
                        h = '<tr><td colspan="8" class="text-center py-5 text-secondary">ไม่พบข้อมูลเซิร์ฟเวอร์</td></tr>';
                    } else {
                        list.forEach(function(i) {
                            var statusBadge = '';
                            if (i.server_status === 'online') statusBadge = '<span class="badge bg-success bg-opacity-25 text-success border border-success rounded-pill"><i class="fas fa-check-circle me-1"></i>Online</span>';
                            else if (i.server_status === 'maintenance') statusBadge = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning rounded-pill"><i class="fas fa-tools me-1"></i>Maint</span>';
                            else statusBadge = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger rounded-pill"><i class="fas fa-times-circle me-1"></i>Offline</span>';
                            var id = (i.id != null) ? i.id : i.server_id;
                            var sn = (i.server_name || '').replace(/'/g, "\\'");
                            var sl = (i.server_location || '').replace(/'/g, "\\'");
                            var su = (i.server_username || '').replace(/'/g, "\\'");
                            var sp = (i.server_password || '').replace(/'/g, "\\'");
                                                        var panelUrl = 'https://' + (i.server_ip || '') + ':' + (i.server_port || '2053') + (i.server_path || '/');
                            h += '<tr><td class="text-secondary">#' + id + '</td><td><span class="fw-bold text-white">' + (i.server_name || '-') + '</span></td><td><span class="badge bg-info bg-opacity-25 text-info border border-info rounded-pill">' + (i.server_location || 'N/A') + '</span></td><td><span class="text-white-50 font-monospace">' + (i.server_ip || '') + ':' + (i.server_port || '') + '</span></td><td class="text-warning font-monospace">' + (i.server_path || '-') + '</td><td class="text-white-50 small">' + (i.server_username || '') + ' / ****</td><td>' + statusBadge + '</td><td><a href="' + panelUrl + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info me-1" title="เปิด Panel"><i class="fas fa-external-link-alt"></i></a><button class="btn btn-sm btn-outline-warning me-1" onclick="edit(\'' + id + '\',\'' + sn + '\',\'' + sl + '\',\'' + (i.max_clients || '') + '\',\'' + (i.server_ip || '') + '\',\'' + (i.server_port || '') + '\',\'' + (i.server_path || '').replace(/'/g, "\\'") + '\',\'' + su + '\',\'' + sp + '\',\'' + (i.server_status || '') + '\')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-outline-danger" onclick="del(' + id + ')"><i class="fas fa-trash"></i></button></td></tr>';
                        });
                    }
                    document.getElementById('listData').innerHTML = h;
                })
                .catch(function(err) {
                    document.getElementById('listData').innerHTML = '<tr><td colspan="8" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>โหลดข้อมูลไม่สำเร็จ: ' + (err.message || err) + '<br><small>ตรวจสอบ Console (F12) หรือว่า API path ถูกต้อง</small><br><button class="btn btn-sm btn-outline-light mt-2" onclick="load()">ลองใหม่</button></td></tr>';
                });
        }

        function add() {
            document.getElementById('formData').reset();
            document.getElementById('eid').value = '';
            document.getElementById('emax').value = '100';
            new bootstrap.Modal(document.getElementById('modalForm')).show();
        }

        function edit(id, nm, loc, max, ip, pt, path, us, pw, st) {
            document.getElementById('eid').value = id;
            document.getElementById('ename').value = nm;
            document.getElementById('eloc').value = loc;
            document.getElementById('emax').value = max;
            document.getElementById('eip').value = ip;
            document.getElementById('eport').value = pt;
            document.getElementById('epath').value = path;
            document.getElementById('euser').value = us;
            document.getElementById('epass').value = pw;
            document.getElementById('estatus').value = st; // ✅ Set ค่า Status
            new bootstrap.Modal(document.getElementById('modalForm')).show();
        }

        function saveData() {
            fetch(API, { method: 'POST', body: new FormData(document.getElementById('formData')) })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalForm')).hide();
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'บันทึกข้อมูลเรียบร้อย', timer: 1500, showConfirmButton: false });
                        load();
                    } else {
                        Swal.fire('Error', d.message, 'error');
                    }
                });
        }

        function del(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: 'เซิร์ฟเวอร์และแพ็กเกจที่เกี่ยวข้องจะถูกลบออก',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ลบเลย',
                confirmButtonColor: '#d33'
            }).then((r) => {
                if (r.isConfirmed) {
                    let fd = new FormData(); fd.append('action', 'delete_server'); fd.append('id', id);
                    fetch(API, { method: 'POST', body: fd }).then(() => {
                        Swal.fire({ icon: 'success', title: 'ลบสำเร็จ', timer: 1000, showConfirmButton: false });
                        load();
                    });
                }
            });
        }

        load();
    </script>
</body>

</html>