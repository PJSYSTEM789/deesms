<?php
/**
 * ไฟล์: pages/send_sms.php
 * วัตถุประสงค์: หน้าจอส่ง SMS เชื่อมต่อ API get_senders.php และ send_sms.php
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
    <title>ส่งข้อความ SMS - Dee SMS Gateway</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Sarabun) -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Sarabun', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="py-4">

<div class="container-lg">
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="fw-bold text-primary mb-0"><i class="fa-solid fa-paper-plane me-2"></i>ระบบส่ง SMS ผ่าน Dee SMS API</h3>
            <p class="text-muted small">เลือกชื่อผู้ส่ง กรอกเบอร์ผู้รับ และส่งข้อความอย่างรวดเร็ว</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card p-4">
                <!-- 1. เลือก Sender Name -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="senderSelect" class="form-label fw-bold mb-0">
                            <i class="fa-solid fa-id-card me-1 text-primary"></i> ชื่อผู้ส่ง (Sender Name)
                        </label>
                        <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="loadSenders()" type="button">
                            <i class="fa-solid fa-rotate me-1"></i>รีเฟรช
                        </button>
                    </div>
                    <select id="senderSelect" class="form-select">
                        <option value="">-- กำลังโหลดรายการ Sender... --</option>
                    </select>
                </div>

                <hr class="my-3">

                <!-- 2. กรอกเบอร์ผู้รับ -->
                <div class="mb-3">
                    <label for="recipientsText" class="form-label fw-bold">
                        <i class="fa-solid fa-mobile-screen me-1 text-primary"></i> เบอร์โทรศัพท์ผู้รับ (1 เบอร์ ต่อ 1 บรรทัด)
                    </label>
                    <textarea id="recipientsText" class="form-control" rows="4" placeholder="0812345678&#10;0898765432" oninput="updateRecipientCount()"></textarea>
                    <div class="form-text text-end" id="recipientCount">จำนวน: 0 เบอร์</div>
                </div>

                <!-- 3. ข้อความ SMS -->
                <div class="mb-3">
                    <label for="smsMessage" class="form-label fw-bold">
                        <i class="fa-solid fa-comment-dots me-1 text-primary"></i> ข้อความ SMS
                    </label>
                    <textarea id="smsMessage" class="form-control" rows="3" placeholder="พิมพ์ข้อความที่ต้องการส่ง..." oninput="calculateSMSCount()"></textarea>
                    <div class="d-flex justify-content-between form-text mt-1">
                        <span id="charCount">0 ตัวอักษร</span>
                        <span id="smsCount" class="fw-bold text-primary">1 SMS / เบอร์</span>
                    </div>
                </div>

                <!-- ปุ่มส่งข้อความ -->
                <button id="btnSend" class="btn btn-primary btn-lg w-100 fw-bold mt-2" onclick="startSendingProcess()">
                    <i class="fa-solid fa-paper-plane me-2"></i>เริ่มส่ง SMS
                </button>

                <!-- Progress Bar -->
                <div id="progressContainer" class="mt-4" style="display: none;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold">กำลังดำเนินการส่ง...</span>
                        <span id="progressText" class="small text-muted">0/0</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section แสดง Logs ผลการส่ง -->
        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check me-2"></i>ผลการส่ง (Logs)</h5>
                <div id="logContainer" class="border rounded p-3 bg-light" style="height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;">
                    <div class="text-muted text-center pt-5">ยังไม่มีรายการส่งในขณะนี้</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    loadSenders();
});

// 1. ดึงข้อมูล Sender Name จาก api/get_senders.php[span_3](start_span)[span_3](end_span)
async function loadSenders() {
    const select = document.getElementById('senderSelect');
    select.innerHTML = '<option value="">-- กำลังโหลดรายการ... --</option>';

    try {
        let response = await fetch('../api/get_senders.php');
        let result = await response.json();

        select.innerHTML = '';

        if (response.ok && result.success && Array.isArray(result.senders) && result.senders.length > 0) {
            let activeSenders = result.senders.filter(s => s.is_active !== false);[span_4](start_span)[span_4](end_span)
            
            activeSenders.forEach((sender, idx) => {
                let opt = document.createElement('option');
                opt.value = sender.id || sender.name;[span_5](start_span)[span_5](end_span)
                opt.innerText = sender.name + (sender.default ? ' (Default)' : '');[span_6](start_span)[span_6](end_span)
                if (sender.default || idx === 0) opt.selected = true;[span_7](start_span)[span_7](end_span)
                select.appendChild(opt);
            });
        } else {
            select.innerHTML = '<option value="">ไม่พบข้อมูล Sender Name</option>';
        }
    } catch (e) {
        console.error("Load senders error:", e);
        select.innerHTML = '<option value="">เกิดข้อผิดพลาดในการดึงข้อมูล</option>';
    }
}

// 2. คำนวณเบอร์โทรศัพท์
function getRecipientList() {
    let text = document.getElementById('recipientsText').value;
    let lines = text.split('\n')
                    .map(p => p.trim().replace(/[^0-9]/g, ''))
                    .filter(p => p.length >= 9 && p.length <= 10);
    return [...new Set(lines)];
}

function updateRecipientCount() {
    let list = getRecipientList();
    document.getElementById('recipientCount').innerText = `จำนวน: ${list.length} เบอร์`;
}

// 3. คำนวณความยาวข้อความและ SMS
function calculateSMSCount() {
    let msg = document.getElementById('smsMessage').value;
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
}

// 4. เริ่มประมวลผลการส่ง SMS
async function startSendingProcess() {
    const senderId = document.getElementById('senderSelect').value;
    const message = document.getElementById('smsMessage').value.trim();
    const recipients = getRecipientList();

    if (!senderId) { alert("กรุณาเลือก Sender Name"); return; }
    if (!recipients.length) { alert("กรุณากรอกเบอร์ผู้รับอย่างน้อย 1 เบอร์"); return; }
    if (!message) { alert("กรุณากรอกข้อความ SMS"); return; }

    if (!confirm(`ยืนยันการส่ง SMS ไปยัง ${recipients.length} เบอร์?`)) return;

    const btn = document.getElementById('btnSend');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logContainer = document.getElementById('logContainer');

    btn.disabled = true;
    progressContainer.style.display = 'block';
    logContainer.innerHTML = '';

    let total = recipients.length;

    for (let i = 0; i < total; i++) {
        let recipient = recipients[i];
        let percent = Math.round(((i + 1) / total) * 100);
        progressBar.style.width = percent + '%';
        progressText.innerText = `${i + 1}/${total}`;

        try {
            let response = await fetch('../api/send_sms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sender_id: senderId,
                    recipient: recipient,
                    message: message
                })
            });

            let res = await response.json();

            if (res.success) {
                logContainer.innerHTML += `<div class="text-success"><i class="fa-solid fa-check me-1"></i> [${i+1}/${total}] ${recipient} : ส่งสำเร็จ</div>`;
            } else {
                logContainer.innerHTML += `<div class="text-danger"><i class="fa-solid fa-xmark me-1"></i> [${i+1}/${total}] ${recipient} : ${res.message || 'ส่งไม่สำเร็จ'}</div>`;
            }
        } catch (err) {
            logContainer.innerHTML += `<div class="text-danger"><i class="fa-solid fa-xmark me-1"></i> [${i+1}/${total}] ${recipient} : เกิดข้อผิดพลาดทางเครือข่าย</div>`;
        }

        logContainer.scrollTop = logContainer.scrollHeight;
        await new Promise(r => setTimeout(r, 200));
    }

    btn.disabled = false;
    alert("ประมวลผลการส่งเสร็จสิ้น!");
}
</script>
</body>
</html>
