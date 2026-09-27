<?php
/**
 * ไฟล์: pages/dashboard.php
 * วัตถุประสงค์: แสดงผล Dashboard สรุปภาพรวมระบบ ยอดเครดิต และสถิติการส่ง SMS
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    echo '<div class="alert alert-danger m-4">คุณไม่มีสิทธิ์เข้าถึงหน้านี้ กรุณาล็อกอินก่อนใช้งาน</div>';
    return;
}
?>

<div class="container-fluid py-3">
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-primary mb-1">
                <i class="fa-solid fa-chart-line me-2"></i>Dashboard ภาพรวมระบบ
            </h4>
            <p class="text-muted small mb-0">สรุปข้อมูลการใช้งาน SMS Gateway และยอดเครดิตคงเหลือ</p>
        </div>
        <button class="btn btn-sm btn-outline-primary fw-bold px-3" onclick="loadDashboardData()">
            <i class="fa-solid fa-rotate me-1"></i> รีเฟรชข้อมูล
        </button>
    </div>

    <!-- Cards Stats Overview -->
    <div class="row g-3 mb-4">
        <!-- Credit API Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-primary text-white h-100" style="border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small opacity-75">เครดิต API คงเหลือ</span>
                    <i class="fa-solid fa-coins fs-4"></i>
                </div>
                <h3 class="fw-bold mb-0" id="lblCreditBalance">
                    <i class="fa-solid fa-spinner fa-spin fs-5"></i>
                </h3>
                <small class="opacity-75 mt-2 d-block" id="lblApiStatus">กำลังตรวจสอบสถานะ...</small>
            </div>
        </div>

        <!-- Total Sent Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white h-100" style="border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">ปริมาณการส่งทั้งหมด</span>
                    <i class="fa-solid fa-paper-plane text-info fs-4"></i>
                </div>
                <h3 class="fw-bold text-dark mb-0" id="lblTotalSent">0</h3>
                <small class="text-muted mt-2 d-block">รายการทั้งหมดในระบบ</small>
            </div>
        </div>

        <!-- Success Sent Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white h-100" style="border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">ส่งสำเร็จ</span>
                    <i class="fa-solid fa-circle-check text-success fs-4"></i>
                </div>
                <h3 class="fw-bold text-success mb-0" id="lblSuccessSent">0</h3>
                <small class="text-muted mt-2 d-block">ส่งถึง API Gateway จริง</small>
            </div>
        </div>

        <!-- Failed / Throttled Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white h-100" style="border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">จำลองส่ง / ไม่สำเร็จ</span>
                    <i class="fa-solid fa-filter text-warning fs-4"></i>
                </div>
                <h3 class="fw-bold text-warning mb-0" id="lblFailedSent">0</h3>
                <small class="text-muted mt-2 d-block">ตามเกณฑ์ Volume Routing</small>
            </div>
        </div>
    </div>

    <!-- Recent SMS Logs Table -->
    <div class="card border-0 shadow-sm p-4" style="border-radius: 12px;">
        <h6 class="fw-bold text-dark mb-3">
            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>ประวัติการส่ง SMS ล่าสุด
        </h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เบอร์โทรศัพท์</th>
                        <th>ข้อความ</th>
                        <th>สถานะ</th>
                        <th>เวลาส่ง</th>
                    </tr>
                </thead>
                <tbody id="tblRecentLogs">
                    <tr>
                        <td colspan="4" class="text-center py-3 text-muted">กำลังโหลดข้อมูล...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", loadDashboardData);

async function loadDashboardData() {
    let creditBalance = document.getElementById('lblCreditBalance');
    let apiStatus      = document.getElementById('lblApiStatus');
    let totalSent       = document.getElementById('lblTotalSent');
    let successSent     = document.getElementById('lblSuccessSent');
    let failedSent      = document.getElementById('lblFailedSent');
    let tblLogs         = document.getElementById('tblRecentLogs');

    creditBalance.innerHTML = `<i class="fa-solid fa-spinner fa-spin fs-5"></i>`;

    try {
        let response = await fetch('../api/get_dashboard_data.php');
        let data = await response.json();

        if (data.status === 'success') {
            // แสดงผลยอดเครดิต
            creditBalance.innerText = `${data.credit_balance} THB`;
            if (data.api_status === 'online') {
                apiStatus.innerHTML = `<i class="fa-solid fa-circle-check me-1"></i>เชื่อมต่อ API Gateway สำเร็จ`;
            } else {
                apiStatus.innerHTML = `<i class="fa-solid fa-circle-exclamation me-1"></i>ไม่สามารถดึงยอดเครดิตได้`;
            }

            // แสดงผลสถิติ
            totalSent.innerText   = data.stats.total_sent.toLocaleString();
            successSent.innerText = data.stats.success.toLocaleString();
            failedSent.innerText  = data.stats.failed.toLocaleString();

            // แสดงตาราง Log ล่าสุด
            if (data.recent_logs && data.recent_logs.length > 0) {
                tblLogs.innerHTML = '';
                data.recent_logs.forEach(log => {
                    let isSuccess = log.status === 'success';
                    tblLogs.innerHTML += `
                        <tr>
                            <td class="fw-bold font-monospace">${escapeHtml(log.phone_number)}</td>
                            <td>${escapeHtml(log.message)}</td>
                            <td>
                                <span class="badge ${isSuccess ? 'bg-success' : 'bg-warning'}">
                                    ${isSuccess ? 'สำเร็จ' : 'จำลอง/ไม่สำเร็จ'}
                                </span>
                            </td>
                            <td class="small text-muted">${escapeHtml(log.created_at)}</td>
                        </tr>
                    `;
                });
            } else {
                tblLogs.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted">ยังไม่มีประวัติการส่ง SMS ในระบบ</td></tr>`;
            }
        }
    } catch (e) {
        creditBalance.innerText = '0.00 THB';
        apiStatus.innerText = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
        tblLogs.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">ไม่สามารถดึงข้อมูล Dashboard ได้</td></tr>`;
    }
}

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
