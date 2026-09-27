<?php
// pages/api_status.php
if (!isAdmin()) exit;
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-0">
                        <i class="fa-solid fa-heart-pulse me-2"></i>สถานะของ API Gateway
                    </h4>
                    <p class="text-muted small mb-0">ตรวจสอบการเชื่อมต่อและความพร้อมใช้งานของ SMS Gateway</p>
                </div>
                <button class="btn btn-sm btn-outline-primary fw-bold" onclick="checkApiHealth()">
                    <i class="fa-solid fa-rotate me-1"></i> ทดสอบการเชื่อมต่อ
                </button>
            </div>

            <!-- Health Status Display -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <span class="text-muted small d-block">สถานะ Server Connection</span>
                        <div id="connStatus" class="fw-bold fs-5 text-secondary mt-1">
                            <i class="fa-solid fa-spinner fa-spin me-1"></i>กำลังตรวจสอบ...
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <span class="text-muted small d-block">ความเร็วในการตอบสนอง (Latency)</span>
                        <div id="latencyStatus" class="fw-bold fs-5 text-secondary mt-1">- ms</div>
                    </div>
                </div>
            </div>

            <h6 class="fw-bold text-dark mb-2">
                <i class="fa-solid fa-id-card me-1"></i> Sender Name ที่ใช้งานได้
            </h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Sender ID / Name</th>
                            <th>สถานะ (Active)</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody id="senderStatusTbody">
                        <tr>
                            <td colspan="3" class="text-center py-3 text-muted">กำลังตรวจสอบรายชื่อ Sender...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="col-lg-4">
        <div class="card p-4 border-0 shadow-sm bg-white">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-circle-info me-2 text-info"></i>ข้อมูล API ปัจจุบัน
            </h5>
            <div class="mb-2">
                <span class="text-muted small d-block">Endpoint URL Base:</span>
                <span class="fw-bold text-break" id="lblApiUrl"><?= htmlspecialchars(getSetting('api_url', 'https://api.deesms.net')) ?></span>
            </div>
            <div class="mb-2">
                <span class="text-muted small d-block">API Key Masked:</span>
                <span class="font-monospace text-muted">
                    <?php 
                        $key = getSetting('api_key', '');
                        echo !empty($key) ? htmlspecialchars(substr($key, 0, 8)) . '****************' : 'ยังไม่ได้ตั้งค่า';
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('expanded');
}
</script>

<script>
document.addEventListener("DOMContentLoaded", checkApiHealth);

async function checkApiHealth() {
    let connStatus = document.getElementById('connStatus');
    let latencyStatus = document.getElementById('latencyStatus');
    let tbody = document.getElementById('senderStatusTbody');

    // รีเซ็ตการแสดงผลระหว่างรอดึงข้อมูล
    connStatus.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>กำลังตรวจสอบ...`;
    connStatus.className = 'fw-bold fs-5 text-secondary mt-1';
    latencyStatus.innerText = '- ms';
    latencyStatus.className = 'fw-bold fs-5 text-secondary mt-1';

    let startTime = Date.now();

    try {
        // ดึงข้อมูลสถานะจาก api/test_api.php
        let res = await fetch('api/test_api.php');
        let endTime = Date.now();
        let pingTime = endTime - startTime;

        let data = await res.json();

        // ตรวจสอบว่าผลลัพธ์เป็น "connected" หรือไม่
        if (data.status === 'connected' || data.success) {
            connStatus.innerHTML = `<i class="fa-solid fa-circle-check text-success me-1"></i>เชื่อมต่อ`;
            connStatus.className = 'fw-bold fs-5 text-success mt-1';
            
            // แสดงค่า Latency
            latencyStatus.innerText = `${pingTime} ms`;
            if (pingTime < 400) {
                latencyStatus.className = 'fw-bold fs-5 text-success mt-1';
            } else if (pingTime < 1000) {
                latencyStatus.className = 'fw-bold fs-5 text-warning mt-1';
            } else {
                latencyStatus.className = 'fw-bold fs-5 text-danger mt-1';
            }

            // แสดงรายการ Sender Names ในตาราง
            if (data.senders && data.senders.length > 0) {
                tbody.innerHTML = '';
                data.senders.forEach(s => {
                    let senderName = s.name || s.sender_name || s.id || '-';
                    let isActive = s.is_active === true || s.is_active === 1 || s.is_active === '1' || s.status === 'active';
                    let isDefault = s.default === true || s.default === 1 || s.default === '1';

                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-bold">${escapeHtml(senderName)}</td>
                            <td><span class="badge ${isActive ? 'bg-success' : 'bg-secondary'}">${isActive ? 'พร้อมใช้งาน' : 'ปิดใช้งาน'}</span></td>
                            <td>${isDefault ? '<i class="fa-solid fa-check text-success"></i>' : '-'}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="3" class="text-center py-3 text-muted">ไม่พบรายการ Sender Name</td></tr>`;
            }
        } else {
            // กรณีดึง Sender ไม่ได้ หรือ API ตอบกลับเป็นความผิดพลาด
            connStatus.innerHTML = `<i class="fa-solid fa-circle-xmark text-danger me-1"></i>ไม่เชื่อมต่อ`;
            connStatus.className = 'fw-bold fs-5 text-danger mt-1';
            tbody.innerHTML = `<tr><td colspan="3" class="text-center py-3 text-danger">ไม่สามารถดึงข้อมูลได้: ${escapeHtml(data.message || data.error || 'ดึง Sender ไม่สำเร็จ')}</td></tr>`;
        }
    } catch(e) {
        // กรณีเกิดปัญหาทางเครือข่าย หรือไฟล์ไม่มีอยู่จริง
        connStatus.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>ไม่เชื่อมต่อ`;
        connStatus.className = 'fw-bold fs-5 text-danger mt-1';
        tbody.innerHTML = `<tr><td colspan="3" class="text-center py-3 text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อระบบ (Network Error)</td></tr>`;
    }
}

// ฟังก์ชันช่วยจัดการตัวอักษรเพื่อป้องกัน XSS
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
