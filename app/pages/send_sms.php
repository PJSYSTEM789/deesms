<?php
/**
 * ไฟล์: pages/send_sms.php
 * วัตถุประสงค์: หน้าอินเทอร์เฟซสำหรับพิมพ์ข้อความ เลือก Sender และส่ง SMS
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    echo '<div class="alert alert-danger m-4">คุณไม่มีสิทธิ์เข้าถึงหน้านี้ กรุณาล็อกอินก่อนใช้งาน</div>';
    return;
}
?>

<div class="container-fluid py-3">
    <div class="card border-0 shadow-sm mx-auto p-4" style="max-width: 700px; border-radius: 12px;">
        <h4 class="fw-bold text-primary mb-2">
            <i class="fa-solid fa-paper-plane me-2"></i>ส่งข้อความ SMS
        </h4>
        <p class="text-muted small mb-4">กรอกข้อมูลเบอร์โทรศัพท์ เลือกชื่อผู้ส่ง และพิมพ์ข้อความเพื่อส่ง SMS ออกจริง</p>

        <!-- Alert Notification -->
        <div id="alertBox" class="d-none alert alert-dismissible fade show small" role="alert">
            <span id="alertMessage"></span>
            <button type="button" class="btn-close" onclick="hideAlert()"></button>
        </div>

        <form id="formSendSms" onsubmit="submitSendSms(event)">
            <!-- Sender Name Dropdown -->
            <div class="mb-3">
                <label class="form-label fw-bold small">ชื่อผู้ส่ง (Sender Name)</label>
                <select class="form-select" id="selSender" name="sender" required>
                    <option value="">-- กำลังโหลดชื่อผู้ส่ง... --</option>
                </select>
            </div>

            <!-- Recipient Phone Number -->
            <div class="mb-3">
                <label class="form-label fw-bold small">เบอร์โทรศัพท์ผู้รับ</label>
                <input type="tel" class="form-control" name="phone" id="txtPhone" placeholder="เช่น 0812345678" required pattern="[0-9]{9,10}">
                <div class="form-text small text-muted">กรอกเบอร์โทรศัพท์ 9-10 หลัก (เช่น 0812345678)</div>
            </div>

            <!-- Message Content -->
            <div class="mb-3">
                <label class="form-label fw-bold small">ข้อความ SMS</label>
                <textarea class="form-control" name="message" id="txtMessage" rows="4" placeholder="พิมพ์ข้อความที่ต้องการส่งที่นี่..." required oninput="calcMessageStats()"></textarea>
                
                <!-- Character & SMS Counter -->
                <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                    <span>จำนวนตัวอักษร: <strong id="lblCharCount" class="text-primary">0</strong> ตัว</span>
                    <span>จำนวนข้อความ: <strong id="lblSmsCount" class="text-primary">1</strong> SMS</span>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-4">
                <button type="submit" id="btnSubmit" class="btn btn-primary fw-bold w-100 py-2">
                    <i class="fa-solid fa-paper-plane me-1"></i> ส่งข้อความ SMS
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    loadSenderNames();
});

// 1. ดึงรายชื่อ Sender Name จาก Backend API
async function loadSenderNames() {
    const selSender = document.getElementById('selSender');
    try {
        let response = await fetch('../api/get_senders.php');
        let result = await response.json();

        if (result.status === 'success' && result.data && result.data.length > 0) {
            selSender.innerHTML = '';
            result.data.forEach(sender => {
                let senderName = typeof sender === 'string' ? sender : (sender.name || sender.sender_name);
                selSender.innerHTML += `<option value="${escapeHtml(senderName)}">${escapeHtml(senderName)}</option>`;
            });
        } else {
            selSender.innerHTML = '<option value="SMS">SMS (ค่าเริ่มต้น)</option>';
        }
    } catch (e) {
        selSender.innerHTML = '<option value="SMS">SMS (ค่าเริ่มต้น)</option>';
    }
}

// 2. คำนวณตัวอักษรและจำนวนข้อความ SMS
function calcMessageStats() {
    const text = document.getElementById('txtMessage').value;
    const charCount = text.length;
    
    // เช็กว่าเป็นภาษาไทยหรือไม่ (ถ้านับภาษาไทยจะคิด 70 ตัวอักษร/SMS, อังกฤษ 160 ตัวอักษร/SMS)
    const isThai = /[ก-๙]/.test(text);
    const maxPerSms = isThai ? 70 : 160;
    const smsCount = charCount === 0 ? 1 : Math.ceil(charCount / maxPerSms);

    document.getElementById('lblCharCount').innerText = charCount;
    document.getElementById('lblSmsCount').innerText = smsCount;
}

// 3. ส่งข้อมูล SMS ไปยัง Backend API
async function submitSendSms(event) {
    event.preventDefault();
    
    const btnSubmit = document.getElementById('btnSubmit');
    const form = document.getElementById('formSendSms');
    const formData = new FormData(form);

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> กำลังส่งข้อความ...`;

    try {
        let response = await fetch('../api/send_sms.php', {
            method: 'POST',
            body: formData
        });

        let result = await response.json();

        if (result.status === 'success') {
            showAlert('success', 'ส่งข้อความ SMS เรียบร้อยแล้ว!');
            document.getElementById('txtMessage').value = '';
            calcMessageStats();
        } else {
            showAlert('danger', result.message || 'ส่งข้อความไม่สำเร็จ');
        }
    } catch (e) {
        showAlert('danger', 'เกิดข้อผิดพลาดในการเชื่อมต่อระบบ');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = `<i class="fa-solid fa-paper-plane me-1"></i> ส่งข้อความ SMS`;
    }
}

function showAlert(type, message) {
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');
    
    alertBox.className = `alert alert-${type} alert-dismissible fade show small`;
    alertMessage.innerText = message;
    alertBox.classList.remove('d-none');
}

function hideAlert() {
    document.getElementById('alertBox').classList.add('d-none');
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
