<?php
require_once './controller/admin_controller/admin_config.php';
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแพ็กเกจ SSH - Admin</title>
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
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

        .form-select option {
            background: #1a1a1a;
            color: #fff;
        }

        .form-select option:hover,
        .form-select option:checked {
            background: var(--accent);
            color: #fff;
        }

        .config-textarea {
            font-family: monospace;
            font-size: 0.85rem;
            min-height: 80px;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--accent);
            margin: 20px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: var(--accent);
            background: rgba(229, 9, 20, 0.05);
        }

        .img-selectable {
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid transparent;
        }

        .img-selectable:hover {
            transform: scale(1.05);
            border-color: var(--accent);
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
                <h3 class="fw-bold text-white mb-0">จัดการแพ็กเกจ SSH/NPV</h3>
                <p class="text-secondary mb-0">แพ็กเกจพร้อม Dual Config (SSH + NPV)</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus me-2"></i>เพิ่มแพ็กเกจ
            </button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อแพ็กเกจ</th>
                                <th>Server</th>
                                <th>ราคา/วัน</th>
                                <th>วัน (min-max)</th>
                                <th>สถานะ</th>
                                <th class="text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">กำลังโหลดข้อมูล...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-box-open me-2 text-danger"></i>ข้อมูลแพ็กเกจ SSH
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formData">
                        <input type="hidden" name="action" value="save_ssh_product">
                        <input type="hidden" id="eid" name="id">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary">ชื่อแพ็กเกจ</label>
                                <input type="text" class="form-control" name="product_name" id="eproduct_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary">เลือก Server</label>
                                <select class="form-select" name="server_id" id="eserver_id" required>
                                    <option value="">-- เลือก Server --</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary">รูปภาพแพ็กเกจ (Optional)</label>
                                <div class="d-flex align-items-center gap-3 p-3"
                                    style="background: rgba(255,255,255,0.02); border-radius: 10px;">
                                    <div id="image-preview-box"
                                        style="width: 80px; height: 80px; background: rgba(0,0,0,0.3); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border-color);">
                                        <i class="fas fa-image text-secondary fa-2x"></i>
                                    </div>
                                    <div>
                                        <input type="hidden" name="image_id" id="eimage_id" value="">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="openImagePicker()">
                                                <i class="fas fa-images me-2"></i>เลือกรูปภาพ
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="clearImage()" id="btnClearImage" style="display: none;">
                                                <i class="fas fa-times"></i> ลบ
                                            </button>
                                        </div>
                                        <small class="text-secondary d-block mt-1">แนะนำขนาด: 600x400px</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary">ราคา/วัน (บาท)</label>
                                <input type="number" step="0.01" class="form-control" name="price_per_day"
                                    id="eprice_per_day" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary">วันขั้นต่ำ</label>
                                <input type="number" class="form-control" name="min_days" id="emin_days" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary">วันสูงสุด</label>
                                <input type="number" class="form-control" name="max_days" id="emax_days" value="30">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary">อุปกรณ์สูงสุด</label>
                                <input type="number" class="form-control" name="max_devices" id="emax_devices"
                                    value="1">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary">รายละเอียด</label>
                                <textarea class="form-control" name="description" id="edescription" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary">คุณสมบัติ (Features) - บรรทัดละ 1
                                    รายการ</label>
                                <textarea class="form-control" name="features" id="efeatures" rows="5"
                                    placeholder="รองรับ PC/Mobile&#10;ความเร็วสูง&#10;Auto Setup"></textarea>
                            </div>
                        </div>

                        <div class="section-title"><i class="fas fa-code me-2"></i>Config Templates</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-secondary">NetMod Config Template</label>
                                <textarea class="form-control config-textarea" name="ssh_config_template"
                                    id="essh_config_template" rows="3" required
                                    placeholder="ssh://{username}:{password}@host:port?...#{CUSTOM_NAME}"></textarea>
                                <small class="text-secondary">Placeholders: {username}, {password},
                                    {CUSTOM_NAME}</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary">NPV Config Template</label>
                                <textarea class="form-control config-textarea" name="npv_config_template"
                                    id="enpv_config_template" rows="3" required
                                    placeholder="npvt-ssh://eyJ..."></textarea>
                                <small class="text-secondary">ระบบจะ decode Base64 และแทนค่า sshUsername, sshPassword,
                                    remarks อัตโนมัติ</small>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_popular" id="eis_popular">
                                    <label class="form-check-label text-secondary">แนะนำ (Popular)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="eis_active"
                                        checked>
                                    <label class="form-check-label text-secondary">เปิดใช้งาน</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary">ลำดับ</label>
                                <input type="number" class="form-control" name="sort_order" id="esort_order" value="0">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" onclick="saveData()">
                        <i class="fas fa-save me-2"></i>บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Picker Modal -->
    <div class="modal fade" id="modalImagePicker" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">เลือกรูปภาพ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="nav nav-pills p-3 border-bottom border-secondary" id="imageTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-library"
                                onclick="loadImages()">
                                <i class="fas fa-th-large me-2"></i>คลังรูปภาพ
                            </button>
                        </li>
                        <li class="nav-item ms-2">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-upload">
                                <i class="fas fa-cloud-upload-alt me-2"></i>อัปโหลดใหม่
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content p-4">
                        <div class="tab-pane fade show active" id="tab-library">
                            <div id="imageLibrary" class="row g-3" style="max-height: 400px; overflow-y: auto;">
                                <div class="col-12 text-center text-secondary py-5">
                                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>กำลังโหลด...
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-upload">
                            <div class="upload-area p-5 text-center"
                                style="border: 2px dashed #666; border-radius: 10px;">
                                <i class="fas fa-cloud-upload-alt fa-4x text-secondary mb-3"></i>
                                <h5 class="text-white mb-3">ลากไฟล์มาวางที่นี่ หรือ</h5>
                                <button type="button" class="btn btn-primary px-4 py-2"
                                    onclick="document.getElementById('uploadInput').click()">
                                    <i class="fas fa-folder-open me-2"></i>เลือกไฟล์จากเครื่อง
                                </button>
                                <p class="text-secondary mt-3 mb-0">รองรับ JPG, PNG, WEBP (Max 5MB)</p>
                                <input type="file" id="uploadInput" class="d-none" accept="image/*"
                                    onchange="uploadImage(this)">
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
        // Version: 2026-01-25-v3 (Image fix)
        console.log('SSH Products Admin v2026-01-25-v3 loaded');
        
        const modal = new bootstrap.Modal(document.getElementById('modalForm'));

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
            loadServers();
        });

        function loadServers() {
            fetch('../controller/admin_controller/ssh_server_controller.php?action=get_servers&t=' + new Date().getTime())
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('eserver_id');
                        data.data.forEach(s => {
                            select.innerHTML += `<option value="${s.server_id}">${s.server_name} (${s.server_host})</option>`;
                        });
                    }
                })
                .catch(err => console.error('Error loading servers:', err));
        }

        function loadData() {
            fetch('../controller/admin_controller/ssh_product_controller.php?action=get_products&t=' + new Date().getTime())
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        document.getElementById('tableBody').innerHTML = data.data.map(p => `
                            <tr>
                                <td>
                                    <strong>${p.product_name}</strong>
                                    ${p.is_popular ? '<span class="badge bg-warning ms-2">แนะนำ</span>' : ''}
                                </td>
                                <td>${p.server_name || p.server_id}</td>
                                <td>฿${parseFloat(p.price_per_day).toFixed(2)}</td>
                                <td>${p.min_days} - ${p.max_days} วัน</td>
                                <td>${p.is_active ? '<span class="badge bg-success">เปิด</span>' : '<span class="badge bg-secondary">ปิด</span>'}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editData(${p.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteData(${p.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        document.getElementById('tableBody').innerHTML = '<tr><td colspan="6" class="text-center py-5 text-secondary">ยังไม่มีข้อมูลแพ็กเกจ</td></tr>';
                    }
                })
                .catch(err => {
                    console.error('Error loading products:', err);
                    document.getElementById('tableBody').innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
                });
        }

        function openModal() {
            document.getElementById('formData').reset();
            document.getElementById('eid').value = '';
            document.getElementById('eid').value = '';
            document.getElementById('eis_active').checked = true;
            clearImage();
            modal.show();
        }

        function editData(id) {
            fetch(`../controller/admin_controller/ssh_product_controller.php?action=get_product&id=${id}&t=${new Date().getTime()}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const p = data.data;
                        document.getElementById('eid').value = p.id;
                        document.getElementById('eproduct_name').value = p.product_name;
                        document.getElementById('eserver_id').value = p.server_id;
                        document.getElementById('eprice_per_day').value = p.price_per_day;
                        document.getElementById('emin_days').value = p.min_days;
                        document.getElementById('emax_days').value = p.max_days;
                        document.getElementById('emax_devices').value = p.max_devices;
                        document.getElementById('edescription').value = p.description || '';

                        let featuresText = '';
                        try {
                            if (p.features) {
                                const features = JSON.parse(p.features);
                                if (Array.isArray(features)) {
                                    featuresText = features.join('\n');
                                }
                            }
                        } catch (e) { }
                        document.getElementById('efeatures').value = featuresText;

                        document.getElementById('essh_config_template').value = p.ssh_config_template;
                        document.getElementById('enpv_config_template').value = p.npv_config_template;
                        document.getElementById('eis_popular').checked = p.is_popular == 1;
                        document.getElementById('eis_active').checked = p.is_active == 1;
                        document.getElementById('esort_order').value = p.sort_order;

                        // ตรวจสอบ image_id ต้องมากกว่า 0 และมี image_url ด้วย
                        if (p.image_id && p.image_id > 0 && p.image_url) {
                            document.getElementById('eimage_id').value = p.image_id;
                            document.getElementById('image-preview-box').innerHTML = `<img src="${p.image_url}" style="width:100%; height:100%; object-fit: cover;">`;
                            document.getElementById('btnClearImage').style.display = 'block';
                        } else {
                            clearImage();
                        }

                        modal.show();
                    }
                });
        }

        function saveData() {
            const imageIdValue = document.getElementById('eimage_id').value;
            console.log('Saving with image_id:', imageIdValue); // Debug
            
            const formData = new FormData(document.getElementById('formData'));
            formData.set('is_popular', document.getElementById('eis_popular').checked ? 1 : 0);
            formData.set('is_active', document.getElementById('eis_active').checked ? 1 : 0);
            // Explicitly set image_id to ensure it's captured
            formData.set('image_id', imageIdValue || '');
            
            // Debug: log all form data
            console.log('Form data image_id:', formData.get('image_id'));

            fetch('../controller/admin_controller/ssh_product_controller.php', {
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
                    console.error('Error saving:', err);
                    Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                });
        }

        function deleteData(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: 'แพ็กเกจนี้จะถูกลบถาวร',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E50914',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('../controller/admin_controller/ssh_product_controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_ssh_product&id=${id}`
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

        // Image Picker Functions
        const imageModal = new bootstrap.Modal(document.getElementById('modalImagePicker'));
        let isImagePickerActive = false;
        let pendingImageSelection = null; // เก็บรูปที่เลือกไว้ชั่วคราว

        function openImagePicker() {
            isImagePickerActive = true;
            pendingImageSelection = null; // reset
            modal.hide();
            // Wait for main modal to close before opening image modal
            setTimeout(() => {
                imageModal.show();
                loadImages();
            }, 500);
        }

        // Always reopen main modal when image picker closes
        document.getElementById('modalImagePicker').addEventListener('hidden.bs.modal', function () {
            if (isImagePickerActive) {
                setTimeout(() => {
                    modal.show();
                    // Apply pending image selection หลังจาก modal เปิดแล้ว
                    setTimeout(() => {
                        if (pendingImageSelection) {
                            document.getElementById('eimage_id').value = pendingImageSelection.id;
                            document.getElementById('image-preview-box').innerHTML = `<img src="../${pendingImageSelection.url}" style="width:100%; height:100%; object-fit: cover;">`;
                            document.getElementById('btnClearImage').style.display = 'block';
                            console.log('Applied pending image:', pendingImageSelection.id);
                            pendingImageSelection = null;
                        }
                    }, 100);
                    isImagePickerActive = false;
                }, 200);
            }
        });

        function loadImages() {
            console.log('Loading images...');
            fetch('../controller/admin_controller/image_upload_controller.php?action=get_images&t=' + Date.now())
                .then(r => r.json())
                .then(data => {
                    console.log('Images API response:', data);
                    const container = document.getElementById('imageLibrary');
                    if (data.success && data.data && data.data.length > 0) {
                        // กรองเฉพาะรูปที่มี id > 0
                        const validImages = data.data.filter(img => img.id && parseInt(img.id) > 0);
                        console.log('Images loaded:', data.data.length, 'Valid:', validImages.length);
                        
                        if (validImages.length > 0) {
                            container.innerHTML = validImages.map(img => `
                                <div class="col-6 col-sm-4 col-md-3">
                                    <div class="card bg-dark h-100 img-selectable position-relative" onclick="selectImage(${img.id}, '${img.url}')">
                                        <img src="../${img.url}" class="card-img-top" style="height: 120px; object-fit: cover;">
                                        <div class="card-body p-2">
                                            <small class="text-secondary text-truncate d-block">${img.original_name}</small>
                                            <small class="text-info d-block">ID: ${img.id}</small>
                                            <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="deleteImage(event, ${img.id})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            container.innerHTML = '<div class="col-12 text-center text-warning py-5">พบรูปภาพแต่ ID ไม่ถูกต้อง กรุณาอัปโหลดใหม่</div>';
                        }
                    } else {
                        container.innerHTML = '<div class="col-12 text-center text-secondary py-5">ไม่พบรูปภาพ</div>';
                        if (!data.success) {
                            console.error('API Error:', data.message);
                        }
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    document.getElementById('imageLibrary').innerHTML = '<div class="col-12 text-center text-danger py-5">เกิดข้อผิดพลาดในการโหลด</div>';
                });
        }

        function uploadImage(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('image', input.files[0]);
                formData.append('action', 'upload_image');

                Swal.fire({
                    title: 'กำลังอัปโหลด...',
                    didOpen: () => Swal.showLoading()
                });

                fetch('../controller/admin_controller/image_upload_controller.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        console.log('Upload response:', data);
                        if (data.success) {
                            Swal.fire('สำเร็จ', data.message, 'success');
                            // Switch to library tab
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
                    .catch(err => {
                        console.error('Upload error:', err);
                        Swal.fire('Error', 'Upload failed: ' + err.message, 'error');
                    });
            }
        }

        function selectImage(id, url) {
            // ตรวจสอบ ID ต้อง > 0
            const imageId = parseInt(id);
            if (!imageId || imageId <= 0) {
                Swal.fire('Error', 'รูปภาพนี้มี ID ไม่ถูกต้อง กรุณาอัปโหลดใหม่', 'error');
                return;
            }
            
            // เก็บไว้ใน pending ก่อน แล้วค่อย apply หลัง main modal เปิด
            pendingImageSelection = { id: imageId, url: url };
            console.log('Image selected (valid):', imageId, url);
            imageModal.hide();
            // Main modal will reopen and apply the selection via event listener
        }

        function clearImage() {
            document.getElementById('eimage_id').value = '';
            document.getElementById('image-preview-box').innerHTML = '<i class="fas fa-image text-secondary fa-2x"></i>';
            document.getElementById('btnClearImage').style.display = 'none';
        }

        function deleteImage(event, id) {
            event.stopPropagation(); // Prevent selection
            Swal.fire({
                title: 'ลบรูปภาพ?',
                text: 'การลบนี้จะไม่สามารถกู้คืนได้',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก',
                zIndex: 1060 // Show above image modal
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../controller/admin_controller/image_upload_controller.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_image&id=${id}`
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                loadImages();
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        }
    </script>
</body>

</html>