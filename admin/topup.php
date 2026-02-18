<?php require_once './controller/admin_controller/admin_config.php';
checkAdminAuth(); ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <title>Admin - เติมเงิน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            padding-bottom: 50px;
        }

        .data-table,
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .stat-card {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-icon-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .bg-icon-green {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .bg-icon-purple {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
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

        .table> :not(caption)>*>* {
            background-color: transparent !important;
            color: inherit;
            border-bottom-width: 1px;
            padding: 1rem 1.5rem;
        }

        .table thead th {
            background-color: rgba(15, 23, 42, 0.9) !important;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container py-4">

        <!-- Header & Cleanup -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h3 class="fw-bold text-white m-0"><i
                    class="fas fa-money-bill-wave text-primary me-2"></i>ประวัติการเติมเงิน</h3>
            <div class="dropdown">
                <button class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-trash-alt me-2"></i>ลบรายการที่ไม่สำเร็จ
                </button>
                <ul class="dropdown-menu dropdown-menu-dark border-secondary">
                    <li>
                        <h6 class="dropdown-header text-secondary">เลือกช่วงเวลา</h6>
                    </li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="cleanup('today')"><i
                                class="fas fa-calendar-day me-2"></i>เฉพาะวันนี้</a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="cleanup('older')"><i
                                class="fas fa-history me-2"></i>เก่ากว่าวันนี้</a></li>
                    <li>
                        <hr class="dropdown-divider bg-secondary">
                    </li>
                    <li><a class="dropdown-item text-danger fw-bold" href="#" onclick="cleanup('all')"><i
                                class="fas fa-dumpster me-2"></i>ลบทั้งหมด (ที่ค้าง)</a></li>
                </ul>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div>
                        <div class="text-secondary small mb-1">ทำรายการทั้งหมด</div>
                        <h4 class="fw-bold mb-0" id="statTotal">...</h4>
                    </div>
                    <div class="stat-icon bg-icon-blue"><i class="fas fa-list-ul"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div>
                        <div class="text-secondary small mb-1">สำเร็จ</div>
                        <h4 class="fw-bold mb-0 text-success" id="statSuccess">...</h4>
                    </div>
                    <div class="stat-icon bg-icon-green"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div>
                        <div class="text-secondary small mb-1">อัตราความสำเร็จ</div>
                        <h4 class="fw-bold mb-0 text-warning" id="statRate">...</h4>
                    </div>
                    <div class="stat-icon bg-icon-purple"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="data-table mb-4 p-4">
            <h5 class="fw-bold text-white mb-3"><i class="fas fa-chart-area me-2 text-info"></i>สถิติย้อนหลัง 30 วัน
            </h5>
            <div class="chart-container">
                <canvas id="topupChart"></canvas>
            </div>
        </div>

        <!-- Table Section -->
        <div class="data-table">
            <div
                class="p-3 border-bottom border-light border-opacity-10 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold"><i class="fas fa-list me-2"></i>รายการล่าสุด</h6>
                <button class="btn btn-sm btn-outline-light" onclick="load()"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>จำนวน</th>
                            <th>ช่องทาง</th>
                            <th>Ref</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="listData">
                        <tr>
                            <td colspan="7" class="text-center p-5 text-secondary">กำลังโหลด...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const API = '../controller/admin_controller/admin_api.php';
        let myChart = null;

        document.addEventListener('DOMContentLoaded', () => {
            load();
            loadStats();
        });

        function load() {
            fetch(API + '?action=get_topups').then(r => r.json()).then(d => {
                let h = '';
                if (!d.data || d.data.length === 0) h = '<tr><td colspan="7" class="text-center p-5 text-secondary">ไม่มีรายการ</td></tr>';
                else d.data.forEach(i => {
                    let statusBadge = i.status == 'success' || i.status == 'approved' ? 'success' : (i.status == 'pending' ? 'warning' : 'danger');
                    let btnAction = '';
                    if (i.status === 'pending') {
                        btnAction = `<button class="btn btn-sm btn-success me-1" onclick="action(${i.id},'approve')" title="อนุมัติ"><i class="fas fa-check"></i></button>
                                     <button class="btn btn-sm btn-danger" onclick="action(${i.id},'reject')" title="ยกเลิก"><i class="fas fa-times"></i></button>`;
                    }
                    h += `<tr>
                            <td class="text-secondary">#${i.id}</td>
                            <td class="fw-bold">${i.username}</td>
                            <td class="text-warning">฿${parseFloat(i.amount).toLocaleString()}</td>
                            <td><span class="badge bg-secondary bg-opacity-25 border border-secondary text-white">${i.method}</span></td>
                            <td class="text-secondary small">${i.transaction_ref || '-'}</td>
                            <td><span class="badge bg-${statusBadge} bg-opacity-25 border border-${statusBadge} text-${statusBadge}">${i.status}</span></td>
                            <td>${btnAction}</td>
                          </tr>`;
                });
                document.getElementById('listData').innerHTML = h;
            });
        }

        function loadStats() {
            fetch(API + '?action=get_topup_stats').then(r => r.json()).then(d => {
                if (d.success) {
                    // Update Cards
                    document.getElementById('statTotal').innerText = d.stats.total_tx.toLocaleString();
                    document.getElementById('statSuccess').innerText = d.stats.success_tx.toLocaleString();
                    document.getElementById('statRate').innerText = d.stats.success_rate + '%';

                    // Update Chart
                    renderChart(d.chart_data);
                }
            });
        }

        function renderChart(data) {
            const ctx = document.getElementById('topupChart').getContext('2d');
            const labels = data.map(i => i.date);
            const total = data.map(i => i.total);
            const success = data.map(i => i.success);

            if (myChart) myChart.destroy();

            const grad1 = ctx.createLinearGradient(0, 0, 0, 300);
            grad1.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
            grad1.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            const grad2 = ctx.createLinearGradient(0, 0, 0, 300);
            grad2.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
            grad2.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'ทั้งหมด',
                            data: total,
                            borderColor: '#3b82f6',
                            backgroundColor: grad1,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'สำเร็จ',
                            data: success,
                            borderColor: '#10b981',
                            backgroundColor: grad2,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#ccc' } } },
                    scales: {
                        x: { ticks: { color: '#888' }, grid: { display: false } },
                        y: { ticks: { color: '#888' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                    }
                }
            });
        }

        function action(id, type) {
            Swal.fire({
                title: type == 'approve' ? 'อนุมัติยอด?' : 'ยกเลิกรายการ?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: type == 'approve' ? '#10b981' : '#ef4444',
                background: '#1a1a1a', color: '#fff'
            }).then((r) => {
                if (r.isConfirmed) {
                    let fd = new FormData();
                    fd.append('action', type + '_topup');
                    fd.append('id', id);
                    fetch(API, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                load(); loadStats();
                                Swal.fire({ icon: 'success', title: 'สำเร็จ', background: '#1a1a1a', color: '#fff', timer: 1500, showConfirmButton: false });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: d.message, background: '#1a1a1a', color: '#fff' });
                            }
                        });
                }
            });
        }

        function cleanup(scope) {
            let title = '';
            if (scope === 'today') title = 'ลบรายการ (เฉพาะวันนี้)?';
            else if (scope === 'older') title = 'ลบรายการ (ก่อนวันนี้)?';
            else title = 'ลบรายการทั้งหมด?';

            Swal.fire({
                title: title,
                text: "เฉพาะรายการที่ยังไม่สำเร็จ (Pending/Cancelled) เท่านั้น",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบทันที',
                background: '#1a1a1a', color: '#fff'
            }).then((r) => {
                if (r.isConfirmed) {
                    let fd = new FormData();
                    fd.append('action', 'cleanup_topups');
                    fd.append('scope', scope);
                    fetch(API, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                load(); loadStats();
                                Swal.fire({ icon: 'success', title: 'ลบเสร็จสิ้น', text: `ลบไปทั้งหมด ${d.affected} รายการ`, background: '#1a1a1a', color: '#fff' });
                            } else {
                                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message, background: '#1a1a1a', color: '#fff' });
                            }
                        })
                }
            })
        }
    </script>
</body>

</html>