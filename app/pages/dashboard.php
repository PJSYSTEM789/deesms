<?php
/**
 * ไฟล์: pages/dashboard.php
 * วัตถุประสงค์: หน้าจอหลัก Dashboard แสดงผลยอดเครดิตและฟอร์มส่ง SMS
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    // exit('No direct script access allowed');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Gateway API - Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Sarabun) -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- SheetJS -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Sarabun', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .phone-count-badge { background-color: #e7f1ff; color: #0d6efd; border: 1px solid #b6d4fe; }
    </style>
</head>
<body class="py-4">

<div class="container-lg">
    <!-- Section Header & Credit Balance Card -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-7 mb-3 mb-md-0">
            <h3 class="fw-bold text-primary mb-0"><i class="fa-solid fa-paper-plane me-2"></i>SMS Gateway API Dashboard</h3>
            <p class="text-muted small mb-0">ระบบส่ง SMS บริหารจัดการคิวส่งผ่าน SMS Gateway API</p>
        </div>
        <!-- Card แสดงยอดเครดิตคงเหลือ -->
        <div class="col-md-5">
            <div class="card p-3 bg-white border-start border-4 border-primary shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold d-block text-uppercase">
                            <i class="fa-solid fa-wallet me-1 text-primary"></i> ยอดเครดิตคงเหลือ (Credits)
                        </span>
                        <h3 class="fw-bold my-1 text-primary" id="creditBalance">
                            <i class="fa-solid fa-spinner fa-spin fs-5 text-muted"></i> 
                            <span class="fs-6 text-muted">กำลังโหลด...</span>
                        </h3>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadBalance()" title="กดเพื่ออัปเดตยอดเครดิต">
                            <i class="fa-solid fa-rotate me-1"></i> รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: ฟอร์มส่ง SMS -->
    <div id="sectionSend">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card p-4">
                    <!-- เลือก Sender Name -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="senderSelect" class="form-label fw-bold mb-0">
                                <i class="fa-solid fa-id-card me-1 text-primary"></i> เลือกชื่อผู้ส่ง (Sender Name)
                            </label>
                            <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="loadSenders()" type="button">
                                <i class="fa-solid fa-rotate me-1"></i>รีเฟรชรายการ
                            </button>
                        </div>
                        <select id="senderSelect" class="form-select">
                            <option value="">-- กำลังโหลดรายการ Sender... --</option>
                        </select>
                    </div>

                    <hr class="my-3">

                    <!-- รายชื่อเบอร์ผู้รับ -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="fw-bold"><i class="fa-solid fa-users-viewfinder me-1 text-primary"></i> รายชื่อเบอร์ผู้รับ</label>
                        <span id="detectedPhoneBadge" class="badge phone-count-badge fs-6 px-3 py-1">
                            <i class="fa-solid fa-mobile-screen me-1"></i>0 เบอร์
                        </span>
                    </div>

                    <ul class="nav nav-pills nav-justified mb-3" id="inputTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="manual-tab" data-bs-toggle="pill" data-bs-target="#manual-pane" type="button"><i class="fa-solid fa-keyboard me-1"></i> กรอกเบอร์เอง</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="file-tab" data-bs-toggle="pill" data-bs-target="#file-pane" type="button"><i class="fa-solid fa-file-excel me-1"></i> ไฟล์ CSV/Excel</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="inputTabContent">
                        <div class="tab-pane fade show active" id="manual-pane">
                            <div class="mb-3">
                                <label class="form-label small text-muted">ใส่เบอร์โทรศัพท์ (1 เบอร์ ต่อ 1 บรรทัด)</label>
                                <textarea id="manualPhones" class="form-control" rows="5" placeholder="0812345678&#10;0898765432" oninput="calculatePhones()"></textarea>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="file-pane">
                            <div class="mb-3">
                                <label class="form-label small text-muted">เลือกไฟล์ Excel (.xlsx, .xls) หรือ CSV</label>
                                <input type="file" id="fileInput" class="form-control" accept=".csv, .xlsx, .xls" onchange="calculatePhones()">
                            </div>
                        </div>
                    </div>

                    <!-- ข้อความ SMS -->
                    <div class="mb-3 mt-2">
                        <label class="form-label fw-bold"><i class="fa-solid fa-comment-dots me-1 text-primary"></i> ข้อความที่ต้องการส่ง</label>
                        <textarea id="smsMessage" class="form-control" rows="3" placeholder="พิมพ์ข้อความที่นี่..."></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <span id="charCount" class="small text-muted">0 ตัวอักษร</span>
                            <span id="smsCount" class="small text-primary fw-bold">1 SMS / เบอร์</span>
                        </div>
                    </div>

                    <button id="btnStartSend" class="btn btn-primary btn-lg w-100 fw-bold" onclick="processAndSendSMS()">
                        <i class="fa-solid fa-paper-plane me-2"></i>เริ่มส่ง SMS ผ่าน SMS Gateway API
                    </button>

                    <div id="progressContainer" class="mt-4" style="display: none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold">กำลังส่งข้อความ...</span>
                            <span id="progressText" class="small text-muted">0/0</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log บันทึกการส่ง -->
            <div class="col-lg-5">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check me-2"></i>บันทึกผลการส่ง (Logs)</h5>
                    <div id="logContainer" class="border rounded p-3 bg-light" style="height: 420px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;">
                        <div class="text-muted text-center pt-5">ยังไม่มีรายการส่งในขณะนี้</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
