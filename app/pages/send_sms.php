<?php

if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    exit('No direct script access allowed');
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
    <!-- SheetJS (สำหรับอ่านไฟล์ Excel .xlsx, .xls และ CSV) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Sarabun', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .nav-pills .nav-link { border-radius: 8px; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: #0d6efd; }
        #progressContainer { display: none; }
        .phone-count-badge { background-color: #e7f1ff; color: #0d6efd; border: 1px solid #b6d4fe; }
    </style>
</head>
<body class="py-4">

<div class="container-lg">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h3 class="fw-bold text-primary mb-0"><i class="fa-solid fa-paper-plane me-2"></i>SMS Gateway API Dashboard</h3>
            <p class="text-muted small mb-0">ระบบส่ง SMS บริหารจัดการคิวส่งผ่าน SMS Gateway API</p>
        </div>
      <!--  <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <button class="btn btn-outline-primary me-2" onclick="switchMainTab('send')"><i class="fa-solid fa-paper-plane me-1"></i> หน้าส่ง SMS</button>
            <button class="btn btn-outline-secondary" onclick="window.location.href='history.php'">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> ประวัติการส่ง
            </button>
        </div>-->
    </div>

    <!-- Section 1: หน้าส่ง SMS -->
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

                    <!-- หัวข้อและ ป้าย Badge แสดงจำนวนเบอร์ -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="fw-bold"><i class="fa-solid fa-users-viewfinder me-1 text-primary"></i> รายชื่อเบอร์ผู้รับ</label>
                        <span id="detectedPhoneBadge" class="badge phone-count-badge fs-6 px-3 py-1">
                            <i class="fa-solid fa-mobile-screen me-1"></i>0 เบอร์
                        </span>
                    </div>

                    <!-- Tab เลือกรูปแบบการกรอกเบอร์ -->
                    <ul class="nav nav-pills nav-justified mb-3" id="inputTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="manual-tab" data-bs-toggle="pill" data-bs-target="#manual-pane" type="button"><i class="fa-solid fa-keyboard me-1"></i> กรอกเบอร์เอง</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="file-tab" data-bs-toggle="pill" data-bs-target="#file-pane" type="button"><i class="fa-solid fa-file-excel me-1"></i> ไฟล์ CSV/Excel</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="gsheet-tab" data-bs-toggle="pill" data-bs-target="#gsheet-pane" type="button"><i class="fa-solid fa-table me-1"></i> Google Sheets</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="inputTabContent">
                        <!-- กรอกเบอร์เอง (1 เบอร์ต่อ 1 บรรทัด) -->
                        <div class="tab-pane fade show active" id="manual-pane">
                            <div class="mb-3">
                                <label class="form-label small text-muted">ใส่เบอร์โทรศัพท์ (1 เบอร์ ต่อ 1 บรรทัด กด Enter เพื่อขึ้นบรรทัดใหม่)</label>
                                <textarea id="manualPhones" class="form-control" rows="5" placeholder="0812345678&#10;0898765432" oninput="calculatePhones()"></textarea>
                            </div>
                        </div>
                        <!-- นำเข้าไฟล์ (xlsx, xls, csv) -->
                        <div class="tab-pane fade" id="file-pane">
                            <div class="mb-3">
                                <label class="form-label small text-muted">เลือกไฟล์ Excel (.xlsx, .xls) หรือ CSV</label>
                                <input type="file" id="fileInput" class="form-control" accept=".csv, .xlsx, .xls" onchange="calculatePhones()">
                            </div>
                        </div>
                        <!-- Google Sheets -->
                        <div class="tab-pane fade" id="gsheet-pane">
                            <div class="mb-3">
                                <label class="form-label small text-muted">วางลิงก์ Google Sheets (แชร์เป็นทุกคนที่มีลิงก์อ่านได้)</label>
                                <div class="input-group">
                                    <input type="url" id="gsheetUrl" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit#gid=0" oninput="calculatePhones()">
                                    <button class="btn btn-outline-primary" type="button" onclick="calculatePhones()"><i class="fa-solid fa-arrows-rotate me-1"></i>ดึงข้อมูลเบอร์</button>
                                </div>
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

                    <div id="progressContainer" class="mt-4">
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

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    loadSenders();

    // คำนวณตัวอักษรและจำนวน SMS/เบอร์
    document.getElementById('smsMessage').addEventListener('input', function () {
        let msg = this.value;
        let len = msg.length;
        document.getElementById('charCount').innerText = len + " ตัวอักษร";
        
        let smsQty = 1;
        const isThai = /[\u0E00-\u0E7F]/.test(msg);
        if (isThai) {
            if (len > 70) smsQty = Math.ceil(len / 67);
        } else {
            if (len > 160) smsQty = Math.ceil(len / 153);
        }
        if (len === 0) smsQty = 1;

        document.getElementById('smsCount').innerText = smsQty + " SMS / เบอร์";
    });

    // สลับ Tab ให้คำนวณเบอร์ของ Tab นั้นทันที
    const tabButtons = document.querySelectorAll('#inputTab button');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function () {
            calculatePhones();
        });
    });
});
// ปรับปรุงฟังก์ชันโหลด Sender Name ให้ดึงข้อมูลและแสดงผลได้อย่างถูกต้อง
async function loadSenders() {
    const select = document.getElementById('senderSelect');
    select.innerHTML = '<option value="">-- กำลังโหลดรายการ... --</option>';

    try {
        let response = await fetch('api/get_senders.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let result = await response.json();

        // ดึง array ออกมาจากโครงสร้าง response ต่างๆ
        let senderList = [];
        if (Array.isArray(result)) {
            senderList = result;
        } else if (result.data && Array.isArray(result.data)) {
            senderList = result.data;
        } else if (result.senders && Array.isArray(result.senders)) {
            senderList = result.senders;
        }

        select.innerHTML = '';

        if (senderList.length > 0) {
            let hasSelected = false;

            senderList.forEach((sender, idx) => {
                // รองรับทั้งแบบ Object และแบบ String แบบปกติ
                let name = typeof sender === 'string' ? sender : (sender.name || sender.sender_name || sender.sender_id || sender.id);
                let id = typeof sender === 'string' ? sender : (sender.id || sender.sender_id || name);
                
                // ตรวจสอบสถานะการใช้งาน (รองรับทั้ง boolean true/false, 1/"1", "true")
                let isActive = true;
                if (typeof sender === 'object' && sender.is_active !== undefined) {
                    isActive = (sender.is_active === true || sender.is_active === 1 || sender.is_active === "1" || sender.is_active === "true");
                }

                if (isActive) {
                    let opt = document.createElement('option');
                    opt.value = id;
                    let isDefault = typeof sender === 'object' && (sender.default === true || sender.default === 1 || sender.default === "1");
                    opt.innerText = name + (isDefault ? ' (Default)' : '');
                    
                    if (isDefault || idx === 0) {
                        opt.selected = true;
                        hasSelected = true;
                    }
                    select.appendChild(opt);
                }
            });

            if (!hasSelected && select.options.length > 0) {
                select.options[0].selected = true;
            }
        } else {
            // กรณีไม่พบข้อมูลในระบบ
            select.innerHTML = '<option value="SMS_INFO">SMS_INFO (Default)</option>';
        }
    } catch (e) {
        console.error("Load senders error:", e);
        // Fallback กรณีเกิด Error ให้มีตัวเลือกเริ่มต้นเสมอ
        select.innerHTML = '<option value="SMS_INFO">SMS_INFO (Default)</option>';
    }
}

// 1. ดึงรายการ Sender Name จาก api/get_senders.php
/*async function loadSenders() {
    const select = document.getElementById('senderSelect');
    select.innerHTML = '<option value="">-- กำลังโหลดรายการ... --</option>';

    try {
        let response = await fetch('api/get_senders.php');
        let result = await response.json();

        let senderList = [];
        if (Array.isArray(result)) {
            senderList = result;
        } else if (result.senders && Array.isArray(result.senders)) {
            senderList = result.senders;
        } else if (result.data && Array.isArray(result.data)) {
            senderList = result.data;
        }

        select.innerHTML = '';

        if (senderList.length > 0) {
            let hasSelected = false;
            senderList.forEach((sender, idx) => {
                let name = sender.name || sender.sender_name || sender.sender_id || sender;
                let id = sender.id || sender.sender_id || name;
                let isActive = sender.is_active !== undefined ? sender.is_active : true;

                if (isActive) {
                    let opt = document.createElement('option');
                    opt.value = id;
                    opt.innerText = name + (sender.default ? ' (Default)' : '');
                    
                    if (sender.default || idx === 0) {
                        opt.selected = true;
                        hasSelected = true;
                    }
                    select.appendChild(opt);
                }
            });

            if (!hasSelected && select.options.length > 0) {
                select.options[0].selected = true;
            }
        } else {
            select.innerHTML = '<option value="SMS_INFO">SMS_INFO (Default)</option>';
        }
    } catch (e) {
        console.error("Load senders error:", e);
        select.innerHTML = '<option value="SMS_INFO">SMS_INFO (Default)</option>';
    }
}*/

// 2. ดึงรายการเบอร์จากแต่ละ Tab (1 เบอร์ต่อ 1 บรรทัด / ไฟล์ .xlsx .csv / Google Sheets)
async function getPhoneList() {
    const activeTab = document.querySelector('#inputTab .nav-link.active').id;
    let phoneList = [];

    if (activeTab === 'manual-tab') {
        let text = document.getElementById('manualPhones').value;
        // แยกเบอร์จากการขึ้นบรรทัดใหม่เท่านั้น (\n)
        phoneList = text.split('\n')
                        .map(p => p.trim().replace(/[^0-9]/g, ''))
                        .filter(p => p.length >= 9 && p.length <= 10);
    } else if (activeTab === 'file-tab') {
        let fileInput = document.getElementById('fileInput');
        if (fileInput.files.length > 0) {
            phoneList = await parseFile(fileInput.files[0]);
        }
    } else if (activeTab === 'gsheet-tab') {
        let gsheetUrl = document.getElementById('gsheetUrl').value.trim();
        if (gsheetUrl) {
            phoneList = await fetchGoogleSheets(gsheetUrl);
        }
    }

    // ตัดรายการเบอร์ซ้ำออก
    return [...new Set(phoneList)];
}

// 3. ฟังก์ชันอัปเดตตัวเลขเบอร์บน Badge แบบเรียลไทม์ (ใส่ async/await เพื่อรออ่านไฟล์ให้เสร็จสมบูรณ์)
async function calculatePhones() {
    const badge = document.getElementById('detectedPhoneBadge');
    badge.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>กำลังอ่านข้อมูล...`;
    
    let phones = await getPhoneList();
    badge.innerHTML = `<i class="fa-solid fa-mobile-screen me-1"></i>นำเข้าได้ ${phones.length} เบอร์`;
}

// 4. อ่านไฟล์ Excel (.xlsx, .xls) และ CSV ด้วย SheetJS
function parseFile(file) {
    return new Promise((resolve) => {
        let reader = new FileReader();
        reader.onload = function (e) {
            try {
                let data = new Uint8Array(e.target.result);
                let workbook = XLSX.read(data, { type: 'array' });
                let firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                let rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

                let phones = [];
                rows.forEach(row => {
                    row.forEach(cell => {
                        let clean = String(cell).replace(/[^0-9]/g, '');
                        if (clean.length >= 9 && clean.length <= 10) phones.push(clean);
                    });
                });
                resolve(phones);
            } catch (err) {
                console.error("Parse file error:", err);
                resolve([]);
            }
        };
        reader.readAsArrayBuffer(file);
    });
}

// 5. ดึงข้อมูลเบอร์จาก Google Sheets
async function fetchGoogleSheets(url) {
    try {
        let matches = url.match(/\/d\/([a-zA-Z0-9-_]+)/);
        if (!matches) return [];
        let sheetId = matches[1];
        let csvUrl = `https://docs.google.com/spreadsheets/d/${sheetId}/export?format=csv`;

        let res = await fetch(csvUrl);
        if (!res.ok) return [];
        let text = await res.text();
        let lines = text.split('\n');
        let phones = [];

        lines.forEach(line => {
            let cells = line.split(',');
            cells.forEach(cell => {
                let clean = cell.replace(/[^0-9]/g, '');
                if (clean.length >= 9 && clean.length <= 10) phones.push(clean);
            });
        });
        return phones;
    } catch (e) {
        console.error("Fetch Google Sheets error:", e);
        return [];
    }
}

// 6. ประมวลผลและส่ง SMS (เงื่อนไข: ต่ำกว่า 200 ส่งหมด / ตั้งแต่ 200 ขึ้นไปส่ง 30%)
async function processAndSendSMS() {
    const senderId = document.getElementById('senderSelect').value;
    const message = document.getElementById('smsMessage').value.trim();

    if (!senderId) { alert("กรุณาเลือกชื่อผู้ส่ง (Sender Name)"); return; }
    if (!message) { alert("กรุณากรอกข้อความที่ต้องการส่ง"); return; }

    let phoneList = await getPhoneList();

    if (phoneList.length === 0) { 
        alert("ไม่พบเบอร์โทรศัพท์ที่ถูกต้อง กรุณาตรวจสอบเบอร์โทรศัพท์ที่ใส่เข้ามา"); 
        return; 
    }

    let totalOriginal = phoneList.length;
    let realSendPhones = [];
    let blockedPhones = [];

    if (totalOriginal < 200) {
        realSendPhones = phoneList;
        blockedPhones = [];

        let confirmSend = confirm(
            `พบเบอร์โทรศัพท์ทั้งหมด: ${totalOriginal} เบอร์ (น้อยกว่า 200 เบอร์)\n` +
            `ระบบจะทำการส่งออกทั้งหมด 100% (${totalOriginal} เบอร์)\n\n` +
            `ต้องการยืนยันการส่ง SMS หรือไม่?`
        );
        if (!confirmSend) return;

    } else {
        let limitCount = Math.ceil(totalOriginal * 0.30);
        realSendPhones = phoneList.slice(0, limitCount);
        blockedPhones = phoneList.slice(limitCount);

        let confirmSend = confirm(
            `พบเบอร์โทรศัพท์ทั้งหมด: ${totalOriginal} เบอร์ (ตั้งแต่ 200 เบอร์ขึ้นไป)\n` +
            `- ส่งผ่าน API จริง (30%): ${limitCount} เบอร์\n` +
            `- จำลองส่งไม่สำเร็จ/บล็อค (70%): ${blockedPhones.length} เบอร์\n\n` +
            `ต้องการยืนยันการส่ง SMS หรือไม่?`
        );
        if (!confirmSend) return;
    }

    startSendingWithSimulation(realSendPhones, blockedPhones, message, senderId);
}

async function startSendingWithSimulation(realPhones, blockedPhones, message, senderId) {
    const btn = document.getElementById('btnStartSend');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logContainer = document.getElementById('logContainer');

    btn.disabled = true;
    progressContainer.style.display = 'block';
    logContainer.innerHTML = '';

    let totalAll = realPhones.length + blockedPhones.length;
    let successCount = 0;
    let failedCount = 0;
    let currentIndex = 0;

    const failReasons = [
        "ส่งไม่สำเร็จ (บล็อค)",
        "ส่งไม่สำเร็จ (ไม่มีสัญญาณ)",
        "ส่งไม่สำเร็จ (ปลายทางปฏิเสธการรับ)"
    ];

    // 1. ส่งเบอร์กลุ่มส่งจริงผ่าน API (30% หรือ 100%)
    for (let i = 0; i < realPhones.length; i++) {
        currentIndex++;
        let phone = realPhones[i];
        
        let currentPercent = Math.round((currentIndex / totalAll) * 100);
        progressBar.style.width = currentPercent + '%';
        progressText.innerText = `${currentIndex}/${totalAll}`;

        try {
            let response = await fetch('api/send_sms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    phone: phone,
                    message: message,
                    sender_id: senderId
                })
            });

            let res = await response.json();

            if (res.success) {
                successCount++;
                logContainer.innerHTML += `<div class="text-success"><i class="fa-solid fa-check me-1"></i> [${currentIndex}/${totalAll}] ${phone} : ส่งสำเร็จ</div>`;
            } else {
                failedCount++;
                logContainer.innerHTML += `<div class="text-danger"><i class="fa-solid fa-xmark me-1"></i> [${currentIndex}/${totalAll}] ${phone} : ${res.message || 'ส่งไม่สำเร็จ'}</div>`;
            }
        } catch (err) {
            failedCount++;
            logContainer.innerHTML += `<div class="text-danger"><i class="fa-solid fa-xmark me-1"></i> [${currentIndex}/${totalAll}] ${phone} : เกิดข้อผิดพลาดทางเครือข่าย</div>`;
        }

        logContainer.scrollTop = logContainer.scrollHeight;
        await new Promise(r => setTimeout(r, 200));
    }

       // 2. จำลองส่งไม่สำเร็จสำหรับกลุ่ม 70% (ถ้ามี)
    for (let i = 0; i < blockedPhones.length; i++) {
        currentIndex++;
        let phone = blockedPhones[i];
        
        let currentPercent = Math.round((currentIndex / totalAll) * 100);
        progressBar.style.width = currentPercent + '%';
        progressText.innerText = `${currentIndex}/${totalAll}`;

        failedCount++;
        let randomReason = failR
