<?php
// admin/topup_packages.php
require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Manage Top-up Packages</title>
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* SweetAlert2 Dark Theme Override */
        .swal2-popup {
            background: rgba(25, 25, 25, 0.95) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 16px !important;
            color: #fff !important;
        }

        .swal2-title,
        .swal2-html-container {
            color: #fff !important;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #E50914, #99060d) !important;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4) !important;
            border: none !important;
        }

        .swal2-cancel {
            background: #333 !important;
            color: #ccc !important;
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5 pt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-coins text-warning me-2"></i> จัดการแพ็กเกจเติมเงิน (Packages)</h3>
            <button class="btn btn-warning text-dark fw-bold" onclick="openModal()"><i class="fas fa-plus"></i>
                เพิ่มแพ็กเกจ</button>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="table table-dark table-hover text-center">
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>ราคา (บาท)</th>
                            <th>โบนัส (บาท)</th>
                            <th>สถานะ Popular</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="pkgTable">
                        <tr>
                            <td colspan="5" class="text-center p-4">กำลังโหลดข้อมูล...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="pkgModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="modalTitle">เพิ่มแพ็กเกจ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="pkgForm">
                        <input type="hidden" id="pkgId">
                        <div class="mb-3">
                            <label class="form-label">ราคา (บาท)</label>
                            <input type="number" class="form-control bg-secondary text-white border-0" id="amount"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">โบนัส (บาท)</label>
                            <input type="number" class="form-control bg-secondary text-white border-0" id="bonus"
                                value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ลำดับการแสดงผล (Sort Order)</label>
                            <input type="number" class="form-control bg-secondary text-white border-0" id="sortOrder"
                                value="0">
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="isPopular">
                            <label class="form-check-label" for="isPopular">สถานะยอดนิยม (Popular Badge)</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-warning text-dark fw-bold" onclick="savePkg()">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const API_URL = '../controller/admin_controller/admin_topup_api.php';
        let pkgModal;

        document.addEventListener('DOMContentLoaded', () => {
            pkgModal = new bootstrap.Modal(document.getElementById('pkgModal'));
            loadPackages();
        });

        function loadPackages() {
            fetch(`${API_URL}?action=get_packages`)
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('pkgTable');
                    if (!data.success || !data.packages || data.packages.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-muted">ยังไม่มีข้อมูล</td></tr>';
                        return;
                    }
                    let html = '';
                    data.packages.forEach((p, index) => {
                        html += `
                            <tr>
                                <td>${p.sort_order}</td>
                                <td class="fw-bold text-success fs-5">฿${p.amount}</td>
                                <td class="${p.bonus > 0 ? 'text-warning' : 'text-muted'}">+${p.bonus}</td>
                                <td>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" ${p.is_popular == 1 ? 'checked' : ''} onchange="togglePopular(${p.id}, this)">
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-light me-1" onclick="editPkg(${p.id}, ${p.amount}, ${p.bonus}, ${p.is_popular}, ${p.sort_order})"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deletePkg(${p.id})"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                })
                .catch(err => console.error(err));
        }

        function openModal() {
            document.getElementById('pkgForm').reset();
            document.getElementById('pkgId').value = '';
            document.getElementById('modalTitle').innerText = 'เพิ่มแพ็กเกจ';
            pkgModal.show();
        }

        function editPkg(id, amount, bonus, isPopular, sort) {
            document.getElementById('pkgId').value = id;
            document.getElementById('amount').value = amount;
            document.getElementById('bonus').value = bonus;
            document.getElementById('sortOrder').value = sort;
            document.getElementById('isPopular').checked = (isPopular == 1);
            document.getElementById('modalTitle').innerText = 'แก้ไขแพ็กเกจ';
            pkgModal.show();
        }

        function savePkg() {
            const id = document.getElementById('pkgId').value;
            const action = id ? 'update_package' : 'add_package';
            const formData = new FormData();
            formData.append('action', action);
            if (id && id !== "") formData.append('id', id); // Ensure Valid ID
            formData.append('amount', document.getElementById('amount').value);
            formData.append('bonus', document.getElementById('bonus').value);
            formData.append('sort_order', document.getElementById('sortOrder').value);
            formData.append('is_popular', document.getElementById('isPopular').checked ? 1 : 0);

            // Default Active = 1 for Update
            if (id) formData.append('is_active', 1);

            fetch(API_URL, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        pkgModal.hide();
                        loadPackages();
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'บันทึกเรียบร้อย',
                            background: '#1a1a1a',
                            color: '#fff'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            background: '#1a1a1a',
                            color: '#fff'
                        });
                    }
                });
        }

        function deletePkg(id) {
            Swal.fire({
                title: 'ยืนยันลบ?',
                text: "ต้องการลบแพ็กเกจนี้หรือไม่",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบเลย',
                background: '#1a1a1a',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_package');
                    formData.append('id', id);
                    fetch(API_URL, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(d => { if (d.success) loadPackages(); });
                }
            })
        }

        function togglePopular(id, checkbox) {
            const formData = new FormData();
            formData.append('action', 'toggle_popular');
            formData.append('id', id);
            formData.append('val', checkbox.checked ? 1 : 0);
            fetch(API_URL, { method: 'POST', body: formData });
        }
    </script>
</body>

</html>