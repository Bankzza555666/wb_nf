<?php
require_once './controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแพ็กเกจ VPN - V2BOX Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .data-table { overflow: hidden; border-radius: 16px; }

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
            vertical-align: middle;
        }

        .table thead th {
            background-color: rgba(15, 23, 42, 0.8) !important;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:last-child td { border-bottom: 0; }

        .modal-content {
            background-color: #1e293b;
            border: 1px solid var(--border-color);
            color: #fff;
            border-radius: 16px;
        }

        .modal-header { border-bottom: 1px solid var(--border-color); padding: 1.5rem; }
        .modal-footer { border-top: 1px solid var(--border-color); padding: 1.5rem; }
        .modal-body { padding: 2rem; max-height: 70vh; overflow-y: auto; }

        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            color: #fff;
            border-radius: 10px;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
        }

        .form-select option { background: #1e293b; color: #fff; }

        .btn-close { filter: invert(1); opacity: 0.5; }
        .btn-close:hover { opacity: 1; }

        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .img-selectable {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .img-selectable:hover {
            transform: scale(1.05);
            border-color: var(--accent) !important;
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
                <h3 class="fw-bold text-white mb-0"><i class="fas fa-shield-alt text-danger me-2"></i>จัดการแพ็กเกจ VPN</h3>
                <span class="text-secondary small">VPN Products Management</span>
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="add()">
                <i class="fas fa-plus me-2"></i>เพิ่มแพ็กเกจ
            </button>
        </div>

        <div class="card-custom p-0 data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>รูป</th>
                            <th>ชื่อแพ็กเกจ</th>
                            <th>Server Node</th>
                            <th>Protocol</th>
                            <th>ราคา / วัน</th>
                            <th>Data / GB</th>
                            <th>สถานะ</th>
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

    <!-- Main Form Modal -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-box-open me-2 text-primary"></i>ข้อมูลแพ็กเกจ VPN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formData">
                        <input type="hidden" name="action" value="save_vpn_product">
                        <input type="hidden" id="eid" name="id">
                        <input type="hidden" id="eimage_id" name="image_id" value="">

                        <div class="section-title">ข้อมูลทั่วไป</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">ชื่อแพ็กเกจ</label>
                                <input type="text" class="form-control" name="filename" id="ename" placeholder="เช่น VIP Thailand" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">เลือกเซิร์ฟเวอร์</label>
                                <select class="form-select" name="server_id" id="eserver" required></select>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary small">รูปภาพแพ็กเกจ</label>
                                <div class="d-flex align-items-center gap-3 p-3" style="background: rgba(0,0,0,0.2); border-radius: 10px; border: 1px solid var(--border-color);">
                                    <div id="image-preview-box" style="width: 80px; height: 80px; background: rgba(0,0,0,0.3); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border-color);">
                                        <i class="fas fa-image text-secondary fa-2x"></i>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openImagePicker()">
                                            <i class="fas fa-images me-2"></i>เลือกรูปภาพ
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearImage()" id="btnClearImage" style="display: none;">
                                            <i class="fas fa-times"></i> ลบ
                                        </button>
                                        <div class="small text-secondary mt-1">แนะนำ: 600x400px</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-title">การเชื่อมต่อ (V2Ray Config)</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Inbound ID</label>
                                <input type="number" class="form-control font-monospace" name="inbound_id" id="einbound" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Host / Domain</label>
                                <input type="text" class="form-control font-monospace" name="host" id="ehost">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Port</label>
                                <input type="number" class="form-control font-monospace" name="port" id="eport">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary small">Protocol</label>
                                <select class="form-select" name="protocol" id="eprotocol">
                                    <option value="vless">VLESS</option>
                                    <option value="vmess">VMess</option>
                                    <option value="trojan">Trojan</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary small">Network</label>
                                <select class="form-select" name="network" id="enetwork">
                                    <option value="tcp">TCP</option>
                                    <option value="ws">WS</option>
                                    <option value="grpc">gRPC</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary small">Security</label>
                                <select class="form-select" name="security" id="esecurity">
                                    <option value="tls">TLS</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                        </div>

                        <div class="section-title">ราคาและข้อจำกัด</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">ราคา/วัน</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-secondary border-secondary text-white">฿</span>
                                    <input type="number" step="0.01" class="form-control" name="price_per_day" id="eprice" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">ราคา/GB</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-secondary border-secondary text-white">฿</span>
                                    <input type="number" step="0.01" class="form-control" name="data_per_gb" id="egb" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">วันสูงสุด</label>
                                <input type="number" class="form-control" name="max_days" id="edays" value="365">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Min Data</label>
                                <input type="number" class="form-control" name="min_data_gb" id="emingb" value="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Max Data</label>
                                <input type="number" class="form-control" name="max_data_gb" id="emaxgb" value="1000">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Devices</label>
                                <input type="number" class="form-control" name="max_devices" id="edevices" value="5">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Speed (Mbps)</label>
                                <input type="number" class="form-control" name="speed_limit_mbps" id="espeed" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" id="esort" value="0">
                            </div>
                        </div>

                        <div class="section-title">ตั้งค่าเพิ่มเติม</div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Config Template</label>
                            <textarea class="form-control font-monospace small text-warning bg-opacity-10" name="config_template" id="etemplate" rows="2" placeholder="vless://{UUID}@{HOST}:{PORT}..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">รายละเอียด</label>
                            <textarea class="form-control" name="description" id="edesc" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Features (บรรทัดละ 1 รายการ)</label>
                            <textarea class="form-control" name="features" id="efeatures" rows="3" placeholder="รองรับ PC/Mobile&#10;ความเร็วสูง&#10;Auto Setup"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch pt-2">
                                    <input class="form-check-input" type="checkbox" id="epopular" name="is_popular">
                                    <label class="form-check-label text-white" for="epopular">🔥 แพ็กเกจแนะนำ (Popular)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch pt-2">
                                    <input class="form-check-input" type="checkbox" id="eactive" name="is_active" checked>
                                    <label class="form-check-label text-white" for="eactive">✅ เปิดใช้งาน (Active)</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveData()">
                        <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Picker Modal -->
    <div class="modal fade" id="modalImagePicker" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content text-white">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>เลือกรูปภาพ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="imageTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-library">คลังรูปภาพ</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-upload">อัปโหลดใหม่</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-library">
                            <div class="row g-3" id="imageLibrary">
                                <div class="col-12 text-center text-secondary py-5">กำลังโหลด...</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-upload">
                            <div class="text-center py-4">
                                <input type="file" id="imageUploadInput" class="d-none" accept="image/*" onchange="uploadImage(this)">
                                <button class="btn btn-outline-primary btn-lg" onclick="document.getElementById('imageUploadInput').click()">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>เลือกไฟล์อัปโหลด
                                </button>
                                <p class="text-secondary mt-2 small">รองรับ: JPG, PNG, GIF, WebP (สูงสุด 5MB)</p>
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
        console.log('VPN Products Admin v2026-01-25 loaded');
        
        const API = '../controller/admin_controller/vpn_product_controller.php';
        const API_OLD = '../controller/admin_controller/admin_api.php';
        
        const modal = new bootstrap.Modal(document.getElementById('modalForm'));
        const imageModal = new bootstrap.Modal(document.getElementById('modalImagePicker'));
        let isImagePickerActive = false;
        let pendingImageSelection = null;

        function loadServers() {
            fetch(API_OLD + '?action=get_servers').then(r => r.json()).then(d => {
                let opts = '<option value="">-- เลือกเซิร์ฟเวอร์ --</option>';
                if (d.data) {
                    d.data.forEach(s => opts += `<option value="${s.server_id}">${s.server_name} (${s.server_location || 'N/A'})</option>`);
                }
                document.getElementById('eserver').innerHTML = opts;
            });
        }

        function load() {
            fetch(API + '?action=get_products').then(r => r.json()).then(d => {
                let h = '';
                if (!d.success || !d.data || d.data.length === 0) {
                    h = '<tr><td colspan="8" class="text-center py-5 text-secondary">ไม่พบข้อมูลแพ็กเกจ</td></tr>';
                } else {
                    d.data.forEach(i => {
                        const imgHtml = i.image_filename 
                            ? `<img src="../img/products/${i.image_filename}" class="product-image">`
                            : `<div class="product-image d-flex align-items-center justify-content-center bg-dark"><i class="fas fa-shield-alt text-secondary"></i></div>`;
                        
                        const statusBadge = i.is_active == 1 
                            ? '<span class="badge bg-success">Active</span>'
                            : '<span class="badge bg-secondary">Inactive</span>';
                        
                        const popularBadge = i.is_popular == 1 
                            ? '<span class="badge bg-warning text-dark ms-1">🔥</span>' 
                            : '';

                        h += `<tr>
                            <td>${imgHtml}</td>
                            <td>
                                <div class="fw-bold text-white">${i.filename}</div>${popularBadge}
                                <div class="small text-white-50 text-truncate" style="max-width: 150px;">${i.description || '-'}</div>
                            </td>
                            <td><span class="badge bg-info bg-opacity-25 text-info border border-info rounded-pill">${i.server_name || i.server_id}</span></td>
                            <td><span class="badge bg-secondary bg-opacity-25 border border-secondary text-white font-monospace">${i.protocol || 'N/A'}</span></td>
                            <td class="text-warning fw-bold">฿${parseFloat(i.price_per_day).toFixed(2)}</td>
                            <td class="text-success fw-bold">฿${parseFloat(i.data_per_gb).toFixed(2)}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning me-1" onclick='editData(${i.id})'><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="del(${i.id})"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>`;
                    });
                }
                document.getElementById('listData').innerHTML = h;
            });
        }

        function add() {
            document.getElementById('formData').reset();
            document.getElementById('eid').value = '';
            document.getElementById('epopular').checked = false;
            document.getElementById('eactive').checked = true;
            clearImage();
            modal.show();
        }

        function editData(id) {
            fetch(`${API}?action=get_product&id=${id}&t=${Date.now()}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const d = data.data;
                        document.getElementById('eid').value = d.id;
                        document.getElementById('ename').value = d.filename;
                        document.getElementById('eserver').value = d.server_id;
                        document.getElementById('einbound').value = d.inbound_id;
                        document.getElementById('ehost').value = d.host || '';
                        document.getElementById('eport').value = d.port || '';
                        document.getElementById('eprotocol').value = d.protocol || 'vless';
                        document.getElementById('enetwork').value = d.network || 'tcp';
                        document.getElementById('esecurity').value = d.security || 'tls';
                        document.getElementById('eprice').value = d.price_per_day;
                        document.getElementById('egb').value = d.data_per_gb;
                        document.getElementById('edays').value = d.max_days;
                        document.getElementById('emingb').value = d.min_data_gb;
                        document.getElementById('emaxgb').value = d.max_data_gb;
                        document.getElementById('edevices').value = d.max_devices;
                        document.getElementById('espeed').value = d.speed_limit_mbps || 0;
                        document.getElementById('esort').value = d.sort_order || 0;
                        document.getElementById('edesc').value = d.description || '';
                        document.getElementById('etemplate').value = d.config_template || '';
                        document.getElementById('epopular').checked = (d.is_popular == 1);
                        document.getElementById('eactive').checked = (d.is_active == 1);

                        // Features
                        let featuresText = '';
                        try {
                            if (d.features) {
                                const features = JSON.parse(d.features);
                                if (Array.isArray(features)) featuresText = features.join('\n');
                            }
                        } catch (e) {}
                        document.getElementById('efeatures').value = featuresText;

                        // Image
                        if (d.image_id && d.image_id > 0 && d.image_url) {
                            document.getElementById('eimage_id').value = d.image_id;
                            document.getElementById('image-preview-box').innerHTML = `<img src="${d.image_url}" style="width:100%; height:100%; object-fit: cover;">`;
                            document.getElementById('btnClearImage').style.display = 'inline-block';
                        } else {
                            clearImage();
                        }

                        modal.show();
                    } else {
                        Swal.fire('Error', data.message || 'ไม่พบข้อมูล', 'error');
                    }
                });
        }

        function saveData() {
            const imageIdValue = document.getElementById('eimage_id').value;
            const formData = new FormData(document.getElementById('formData'));
            formData.set('is_popular', document.getElementById('epopular').checked ? 1 : 0);
            formData.set('is_active', document.getElementById('eactive').checked ? 1 : 0);
            formData.set('image_id', imageIdValue || '');

            fetch(API, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        modal.hide();
                        load();
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', timer: 1000, showConfirmButton: false });
                    } else {
                        Swal.fire('Error', d.message || 'เกิดข้อผิดพลาด', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Connection failed', 'error'));
        }

        function del(id) {
            Swal.fire({
                title: 'ยืนยันลบ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบเลย'
            }).then((r) => {
                if (r.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete_product');
                    fd.append('id', id);
                    fetch(API, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                load();
                                Swal.fire({ icon: 'success', title: 'ลบแล้ว', timer: 1000, showConfirmButton: false });
                            } else {
                                Swal.fire('Error', d.message, 'error');
                            }
                        });
                }
            });
        }

        // Image Picker Functions
        function openImagePicker() {
            isImagePickerActive = true;
            pendingImageSelection = null;
            modal.hide();
            setTimeout(() => {
                imageModal.show();
                loadImages();
            }, 500);
        }

        document.getElementById('modalImagePicker').addEventListener('hidden.bs.modal', function () {
            if (isImagePickerActive) {
                setTimeout(() => {
                    modal.show();
                    setTimeout(() => {
                        if (pendingImageSelection) {
                            document.getElementById('eimage_id').value = pendingImageSelection.id;
                            document.getElementById('image-preview-box').innerHTML = `<img src="../${pendingImageSelection.url}" style="width:100%; height:100%; object-fit: cover;">`;
                            document.getElementById('btnClearImage').style.display = 'inline-block';
                            console.log('Applied pending image:', pendingImageSelection.id);
                            pendingImageSelection = null;
                        }
                    }, 100);
                    isImagePickerActive = false;
                }, 200);
            }
        });

        function loadImages() {
            fetch('../controller/admin_controller/image_upload_controller.php?action=get_images&t=' + Date.now())
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('imageLibrary');
                    if (data.success && data.data && data.data.length > 0) {
                        const validImages = data.data.filter(img => img.id && parseInt(img.id) > 0);
                        if (validImages.length > 0) {
                            container.innerHTML = validImages.map(img => `
                                <div class="col-6 col-sm-4 col-md-3">
                                    <div class="card bg-dark h-100 img-selectable position-relative" onclick="selectImage(${img.id}, '${img.url}')">
                                        <img src="../${img.url}" class="card-img-top" style="height: 100px; object-fit: cover;">
                                        <div class="card-body p-2">
                                            <small class="text-secondary text-truncate d-block">${img.original_name}</small>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            container.innerHTML = '<div class="col-12 text-center text-warning py-5">พบรูปภาพแต่ ID ไม่ถูกต้อง กรุณาอัปโหลดใหม่</div>';
                        }
                    } else {
                        container.innerHTML = '<div class="col-12 text-center text-secondary py-5">ไม่พบรูปภาพ กรุณาอัปโหลด</div>';
                    }
                })
                .catch(err => {
                    document.getElementById('imageLibrary').innerHTML = '<div class="col-12 text-center text-danger py-5">เกิดข้อผิดพลาด</div>';
                });
        }

        function uploadImage(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('image', input.files[0]);
                formData.append('action', 'upload_image');

                Swal.fire({ title: 'กำลังอัปโหลด...', didOpen: () => Swal.showLoading() });

                fetch('../controller/admin_controller/image_upload_controller.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'อัปโหลดสำเร็จ', timer: 1500, showConfirmButton: false });
                        const triggerEl = document.querySelector('#imageTabs button[data-bs-target="#tab-library"]');
                        if (triggerEl) {
                            const tab = bootstrap.Tab.getInstance(triggerEl) || new bootstrap.Tab(triggerEl);
                            tab.show();
                        }
                        loadImages();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Upload failed', 'error'));
            }
        }

        function selectImage(id, url) {
            const imageId = parseInt(id);
            if (!imageId || imageId <= 0) {
                Swal.fire('Error', 'รูปภาพนี้มี ID ไม่ถูกต้อง', 'error');
                return;
            }
            pendingImageSelection = { id: imageId, url: url };
            console.log('Image selected:', imageId, url);
            imageModal.hide();
        }

        function clearImage() {
            document.getElementById('eimage_id').value = '';
            document.getElementById('image-preview-box').innerHTML = '<i class="fas fa-image text-secondary fa-2x"></i>';
            document.getElementById('btnClearImage').style.display = 'none';
        }

        // Init
        loadServers();
        load();
    </script>
</body>

</html>
