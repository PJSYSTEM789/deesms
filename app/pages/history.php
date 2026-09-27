<?php
/**
 * ไฟล์: pages/history.php
 * วัตถุประสงค์: หน้าแสดงตารางประวัติการส่ง SMS พร้อมระบบค้นหาและแบ่งหน้า
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการส่ง SMS - Dee SMS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Sarabun) -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Sarabun', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table th { background-color: #f1f5f9; font-weight: 600; }
    </style>
</head>
<body class="py-4">

<div class="container-lg">
    <!-- Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h3 class="fw-bold text-primary mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>ประวัติการส่ง SMS</h3>
            <p class="text-muted small mb-0">ตรวจสอบรายการและสถานะการส่งข้อความย้อนหลัง</p>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>กลับหน้า Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card p-3 mb-4">
        <form id="filterForm" class="row g-3 align-items-end" onsubmit="event.preventDefault(); loadHistory(1);">
            <div class="col-md-3">
                <label for="startDate" class="form-label small fw-bold">วันที่เริ่มต้น (Start Date)</label>
                <input type="date" id="startDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label small fw-bold">วันที่สิ้นสุด (End Date)</label>
                <input type="date" id="endDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="limitSelect" class="form-label small fw-bold">แสดงต่อหน้า</label>
                <select id="limitSelect" class="form-select form-select-sm">
                    <option value="25" selected>25 รายการ</option>
                    <option value="50">50 รายการ</option>
                    <option value="100">100 รายการ</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> ค้นหา
                </button>
                <button type="button" class="btn btn-light btn-sm flex-fill border" onclick="resetFilter()">
                    <i class="fa-solid fa-rotate-left me-1"></i> รีเซ็ต
                </button>
            </div>
        </form>
    </div>

    <!-- History Table Card -->
    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ผู้ส่ง (Sender)</th>
                        <th>เบอร์ผู้รับ (Recipient)</th>
                        <th>ข้อความ (Message)</th>
                        <th class="text-center">เครดิต</th>
                        <th class="text-center">สถานะ (DR)</th>
                        <th class="text-center">เวลาที่ส่ง</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-spinner fa-spin me-2"></i>กำลังโหลดข้อมูล...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="small text-muted" id="paginationInfo">แสดง 0 ถึง 0 จาก 0 รายการ</div>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="paginationNav">
                    <!-- จะถูกสร้างผ่าน JS -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/history.js"></script>
</body>
</html>
