<?php
require_once './controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายการเช่า - Admin</title>
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

        body { background: var(--bg-body); color: var(--text-primary); font-family: 'Segoe UI', sans-serif; }

        .card-custom {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }

        .nav-tabs { border-bottom: 1px solid var(--border-color); }
        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            padding: 15px 25px;
        }
        .nav-tabs .nav-link:hover { color: #fff; border: none; }
        .nav-tabs .nav-link.active {
            background: transparent;
            color: var(--accent);
            border-bottom: 3px solid var(--accent);
        }

        .table {
            color: var(--text-primary);
            --bs-table-bg: transparent;
            --bs-table-hover-bg: rgba(255, 255, 255, 0.05);
        }
        .table thead th {
            background: rgba(15, 23, 42, 0.8) !important;
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

        .badge-active { background: rgba(40, 167, 69, 0.2); color: #4ade80; border: 1px solid #28a745; }
        .badge-expired { background: rgba(220, 53, 69, 0.2); color: #f87171; border: 1px solid #dc3545; }
        .badge-cancelled { background: rgba(108, 117, 125, 0.2); color: #9ca3af; border: 1px solid #6c757d; }

        .stat-card {
            background: linear-gradient(135deg, rgba(229,9,20,0.1), rgba(0,0,0,0.3));
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-number { font-size: 2rem; font-weight: bold; color: var(--accent); }
        .stat-label { color: var(--text-secondary); font-size: 0.9rem; }

        .btn-action { padding: 5px 10px; font-size: 0.8rem; }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-white mb-0"><i class="fas fa-clipboard-list text-danger me-2"></i>จัดการรายการเช่า</h3>
                <small class="text-secondary">Rentals Management</small>
            </div>
            <button class="btn btn-outline-light btn-sm" onclick="exportData()">
                <i class="fas fa-download me-2"></i>Export CSV
            </button>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number" id="statVpnActive">-</div>
                    <div class="stat-label"><i class="fas fa-shield-alt me-1"></i>VPN Active</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number" id="statSshActive">-</div>
                    <div class="stat-label"><i class="fas fa-terminal me-1"></i>SSH Active</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number" id="statExpiringSoon">-</div>
                    <div class="stat-label"><i class="fas fa-clock me-1"></i>ใกล้หมดอายุ</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number" id="statTotalRevenue">-</div>
                    <div class="stat-label"><i class="fas fa-baht-sign me-1"></i>รายได้รวม</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="rentalTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-vpn">
                    <i class="fas fa-shield-alt me-2"></i>VPN Rentals
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ssh">
                    <i class="fas fa-terminal me-2"></i>SSH Rentals
                </button>
            </li>
        </ul>

        <!-- Filters -->
        <div class="card-custom p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-secondary">ค้นหา</label>
                    <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="ชื่อผู้ใช้, Email, UUID...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">สถานะ</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">ทั้งหมด</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">Server</label>
                    <select class="form-select form-select-sm" id="filterServer">
                        <option value="">ทั้งหมด</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">วันที่เช่า</label>
                    <input type="date" class="form-control form-control-sm" id="filterDate">
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-primary btn-sm" onclick="applyFilters()">
                        <i class="fas fa-search me-1"></i>ค้นหา
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="resetFilters()">
                        <i class="fas fa-redo me-1"></i>รีเซ็ต
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- VPN Tab -->
            <div class="tab-pane fade show active" id="tab-vpn">
                <div class="card-custom">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ผู้ใช้</th>
                                    <th>Server</th>
                                    <th>แพ็กเกจ</th>
                                    <th>วัน/Data</th>
                                    <th>วันหมดอายุ</th>
                                    <th>ราคา</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="vpnRentalsList">
                                <tr><td colspan="9" class="text-center py-4 text-secondary">กำลังโหลด...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SSH Tab -->
            <div class="tab-pane fade" id="tab-ssh">
                <div class="card-custom">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ผู้ใช้</th>
                                    <th>Server</th>
                                    <th>แพ็กเกจ</th>
                                    <th>Username</th>
                                    <th>วันหมดอายุ</th>
                                    <th>ราคา</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="sshRentalsList">
                                <tr><td colspan="9" class="text-center py-4 text-secondary">กำลังโหลด...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center" id="pagination"></ul>
        </nav>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>รายละเอียดการเช่า</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content loaded via JS -->
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const API = '../controller/admin_controller/rentals_controller.php';
        let currentTab = 'vpn';
        let currentPage = 1;

        // Tab change
        document.querySelectorAll('#rentalTabs button').forEach(btn => {
            btn.addEventListener('shown.bs.tab', e => {
                currentTab = e.target.dataset.bsTarget === '#tab-vpn' ? 'vpn' : 'ssh';
                currentPage = 1;
                loadRentals();
            });
        });

        function loadStats() {
            fetch(API + '?action=get_stats')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('statVpnActive').textContent = data.vpn_active || 0;
                        document.getElementById('statSshActive').textContent = data.ssh_active || 0;
                        document.getElementById('statExpiringSoon').textContent = data.expiring_soon || 0;
                        document.getElementById('statTotalRevenue').textContent = '฿' + (data.total_revenue || 0).toLocaleString();
                    }
                });
        }

        function loadServers() {
            fetch(API + '?action=get_servers')
                .then(r => r.json())
                .then(data => {
                    const select = document.getElementById('filterServer');
                    if (data.vpn_servers) {
                        data.vpn_servers.forEach(s => {
                            select.innerHTML += `<option value="vpn_${s.server_id}">[VPN] ${s.server_name}</option>`;
                        });
                    }
                    if (data.ssh_servers) {
                        data.ssh_servers.forEach(s => {
                            select.innerHTML += `<option value="ssh_${s.server_id}">[SSH] ${s.server_name}</option>`;
                        });
                    }
                });
        }

        function loadRentals() {
            const params = new URLSearchParams({
                action: 'get_rentals',
                type: currentTab,
                page: currentPage,
                search: document.getElementById('searchInput').value,
                status: document.getElementById('filterStatus').value,
                server: document.getElementById('filterServer').value,
                date: document.getElementById('filterDate').value
            });

            const listId = currentTab === 'vpn' ? 'vpnRentalsList' : 'sshRentalsList';
            document.getElementById(listId).innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i></td></tr>';

            fetch(API + '?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.data || data.data.length === 0) {
                        document.getElementById(listId).innerHTML = '<tr><td colspan="9" class="text-center py-4 text-secondary">ไม่พบข้อมูล</td></tr>';
                        return;
                    }

                    let html = '';
                    data.data.forEach((r, i) => {
                        const statusBadge = getStatusBadge(r.status, r.expire_date);
                        const expireDate = new Date(r.expire_date).toLocaleDateString('th-TH');
                        
                        if (currentTab === 'vpn') {
                            html += `
                                <tr>
                                    <td class="text-secondary">${r.id}</td>
                                    <td>
                                        <strong class="text-white">${r.username || 'N/A'}</strong>
                                        <div class="small text-secondary">${r.user_email || ''}</div>
                                    </td>
                                    <td><span class="badge bg-info bg-opacity-25 text-info">${r.server_name || r.server_id}</span></td>
                                    <td>${r.profile_name || r.rental_name || '-'}</td>
                                    <td>${r.days_rented}วัน / ${r.data_gb_rented}GB</td>
                                    <td>${expireDate}</td>
                                    <td class="text-warning">฿${parseFloat(r.price_paid || 0).toFixed(2)}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info btn-action" onclick="viewDetail('vpn', ${r.id})"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-success btn-action" onclick="extendRental('vpn', ${r.id})" title="ต่ออายุ"><i class="fas fa-plus"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-action" onclick="cancelRental('vpn', ${r.id})" title="ยกเลิก"><i class="fas fa-ban"></i></button>
                                    </td>
                                </tr>
                            `;
                        } else {
                            html += `
                                <tr>
                                    <td class="text-secondary">${r.id}</td>
                                    <td>
                                        <strong class="text-white">${r.username || 'N/A'}</strong>
                                        <div class="small text-secondary">${r.user_email || ''}</div>
                                    </td>
                                    <td><span class="badge bg-purple bg-opacity-25 text-info">${r.server_name || r.server_id}</span></td>
                                    <td>${r.product_name || '-'}</td>
                                    <td><code>${r.ssh_username || '-'}</code></td>
                                    <td>${expireDate}</td>
                                    <td class="text-warning">฿${parseFloat(r.price_paid || 0).toFixed(2)}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info btn-action" onclick="viewDetail('ssh', ${r.id})"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-success btn-action" onclick="extendRental('ssh', ${r.id})" title="ต่ออายุ"><i class="fas fa-plus"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-action" onclick="cancelRental('ssh', ${r.id})" title="ยกเลิก"><i class="fas fa-ban"></i></button>
                                    </td>
                                </tr>
                            `;
                        }
                    });

                    document.getElementById(listId).innerHTML = html;
                    renderPagination(data.total_pages || 1);
                });
        }

        function getStatusBadge(status, expireDate) {
            const now = new Date();
            const exp = new Date(expireDate);
            
            if (status === 'cancelled') {
                return '<span class="badge badge-cancelled">Cancelled</span>';
            } else if (exp < now || status === 'expired') {
                return '<span class="badge badge-expired">Expired</span>';
            } else {
                return '<span class="badge badge-active">Active</span>';
            }
        }

        function renderPagination(totalPages) {
            const container = document.getElementById('pagination');
            let html = '';
            
            for (let i = 1; i <= totalPages; i++) {
                html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link bg-dark border-secondary ${i === currentPage ? 'text-danger' : 'text-white'}" href="#" onclick="goToPage(${i})">${i}</a>
                </li>`;
            }
            container.innerHTML = html;
        }

        function goToPage(page) {
            currentPage = page;
            loadRentals();
        }

        function applyFilters() {
            currentPage = 1;
            loadRentals();
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterServer').value = '';
            document.getElementById('filterDate').value = '';
            currentPage = 1;
            loadRentals();
        }

        function viewDetail(type, id) {
            fetch(`${API}?action=get_detail&type=${type}&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const r = data.data;
                        let html = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-secondary">ข้อมูลผู้ใช้</h6>
                                    <p><strong>Username:</strong> ${r.username || 'N/A'}</p>
                                    <p><strong>Email:</strong> ${r.user_email || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-secondary">ข้อมูลการเช่า</h6>
                                    <p><strong>Server:</strong> ${r.server_name || r.server_id}</p>
                                    <p><strong>สถานะ:</strong> ${r.status}</p>
                                    <p><strong>ราคา:</strong> ฿${parseFloat(r.price_paid || 0).toFixed(2)}</p>
                                </div>
                            </div>
                            <hr class="border-secondary">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>วันที่เริ่ม:</strong> ${r.start_date || r.created_at}</p>
                                    <p><strong>วันหมดอายุ:</strong> ${r.expire_date}</p>
                                </div>
                                <div class="col-md-6">
                                    ${type === 'vpn' ? `
                                        <p><strong>UUID:</strong> <code class="text-warning">${r.client_uuid || '-'}</code></p>
                                        <p><strong>Data Used:</strong> ${formatBytes(r.data_used_bytes || 0)} / ${r.data_gb_rented}GB</p>
                                    ` : `
                                        <p><strong>SSH Username:</strong> <code>${r.ssh_username || '-'}</code></p>
                                        <p><strong>SSH Password:</strong> <code>${r.ssh_password || '-'}</code></p>
                                    `}
                                </div>
                            </div>
                            ${r.config_url ? `
                                <hr class="border-secondary">
                                <h6 class="text-secondary">Config URL</h6>
                                <div class="bg-black p-2 rounded">
                                    <code class="text-success small" style="word-break: break-all;">${r.config_url}</code>
                                </div>
                            ` : ''}
                        `;
                        document.getElementById('detailContent').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('detailModal')).show();
                    }
                });
        }

        function extendRental(type, id) {
            Swal.fire({
                title: 'ต่ออายุการเช่า',
                html: `
                    <input type="number" id="extendDays" class="swal2-input" placeholder="จำนวนวัน" value="30">
                    <small class="text-muted">ใส่จำนวนวันที่ต้องการเพิ่ม (ฟรี)</small>
                `,
                showCancelButton: true,
                confirmButtonText: 'ต่ออายุ',
                cancelButtonText: 'ยกเลิก',
                background: '#1a1a1a',
                color: '#fff'
            }).then(result => {
                if (result.isConfirmed) {
                    const days = document.getElementById('extendDays').value;
                    const fd = new FormData();
                    fd.append('action', 'extend_rental');
                    fd.append('type', type);
                    fd.append('id', id);
                    fd.append('days', days);

                    fetch(API, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: `ต่ออายุ ${days} วัน เรียบร้อย`, timer: 2000, showConfirmButton: false, background: '#1a1a1a', color: '#fff' });
                                loadRentals();
                                loadStats();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.message, background: '#1a1a1a', color: '#fff' });
                            }
                        });
                }
            });
        }

        function cancelRental(type, id) {
            Swal.fire({
                title: 'ยืนยันยกเลิกการเช่า?',
                text: 'การดำเนินการนี้ไม่สามารถย้อนกลับได้',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ยกเลิกเลย',
                cancelButtonText: 'ไม่',
                background: '#1a1a1a',
                color: '#fff'
            }).then(result => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'cancel_rental');
                    fd.append('type', type);
                    fd.append('id', id);

                    fetch(API, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: 'ยกเลิกแล้ว', timer: 1500, showConfirmButton: false, background: '#1a1a1a', color: '#fff' });
                                loadRentals();
                                loadStats();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.message, background: '#1a1a1a', color: '#fff' });
                            }
                        });
                }
            });
        }

        function exportData() {
            window.open(API + '?action=export&type=' + currentTab, '_blank');
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Init
        loadStats();
        loadServers();
        loadRentals();

        // Search on Enter
        document.getElementById('searchInput').addEventListener('keypress', e => {
            if (e.key === 'Enter') applyFilters();
        });
    </script>
</body>
</html>
