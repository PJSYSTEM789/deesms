/**
 * ไฟล์: assets/js/dashboard.js
 * วัตถุประสงค์: Script ควบคุมหน้า Dashboard ดึงเครดิต, Sender และส่ง SMS
 */

document.addEventListener("DOMContentLoaded", function () {
    loadBalance();
    loadSenders();

    // ตัวนับตัวอักษร SMS
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
});

// 1. ดึงยอดเครดิตคงเหลือ
async function loadBalance() {
    const balanceElem = document.getElementById('creditBalance');
    if (!balanceElem) return;

    balanceElem.innerHTML = '<i class="fa-solid fa-spinner fa-spin fs-5 text-muted"></i> <span class="fs-6 text-muted">กำลังโหลด...</span>';

    try {
        let response = await fetch('../api/get_balance.php');
        let result = await response.json();

        if (response.ok && result.success && result.credit !== undefined) {
            let formattedCredit = Number(result.credit).toLocaleString();
            balanceElem.innerHTML = `<i class="fa-solid fa-coins me-2 text-warning"></i>${formattedCredit} <small class="fs-6 text-muted">เครดิต</small>`;
        } else {
            balanceElem.innerHTML = `<span class="text-danger fs-6"><i class="fa-solid fa-circle-exclamation me-1"></i>${result.message || 'ดึงข้อมูล ไม่สำเร็จ'}</span>`;
        }
    } catch (error) {
        console.error("Load balance error:", error);
        balanceElem.innerHTML = '<span class="text-danger fs-6"><i class="fa-solid fa-triangle-exclamation me-1"></i>เชื่อมต่อระบบไม่สำเร็จ</span>';
    }
}

// 2. ดึงรายการ Sender Name
async function loadSenders() {
    const select = document.getElementById('senderSelect');
    select.innerHTML = '<option value="">-- กำลังโหลดรายการ... --</option>';

    try {
        let response = await fetch('../api/get_senders.php');
        let result = await response.json();

        let senderList = [];
        if (Array.isArray(result)) senderList = result;
        else if (result.senders && Array.isArray(result.senders)) senderList = result.senders;
        else if (result.data && Array.isArray(result.data)) senderList = result.data;

        select.innerHTML = '';

        if (senderList.length > 0) {
            senderList.forEach((sender, idx) => {
                let name = typeof sender === 'string' ? sender : (sender.name || sender.sender_name || sender.id);
                let id = typeof sender === 'string' ? sender : (sender.id || name);

                let opt = document.createElement('option');
                opt.value = id;
                opt.innerText = name;
                if (idx === 0) opt.selected = true;
                select.appendChild(opt);
            });
        } else {
            select.innerHTML = '<option value="SMS_INFO">SMS_INFO (Default)</option>';
        }
    } catch (e) {
        console.error("Load senders error:", e);
        select.innerHTML = '<option value="SMS_INFO">SMS_INFO (Default)</option>';
    }
}

// 3. ประมวลผลเบอร์โทรศัพท์
async function calculatePhones() {
    const badge = document.getElementById('detectedPhoneBadge');
    let text = document.getElementById('manualPhones').value;
    let phones = text.split('\n')
                     .map(p => p.trim().replace(/[^0-9]/g, ''))
                     .filter(p => p.length >= 9 && p.length <= 10);
    
    let uniquePhones = [...new Set(phones)];
    badge.innerHTML = `<i class="fa-solid fa-mobile-screen me-1"></i>นำเข้าได้ ${uniquePhones.length} เบอร์`;
    return uniquePhones;
}

// 4. เริ่มส่ง SMS
async function processAndSendSMS() {
    const senderId = document.getElementById('senderSelect').value;
    const message = document.getElementById('smsMessage').value.trim();

    if (!senderId) { alert("กรุณาเลือก Sender Name"); return; }
    if (!message) { alert("กรุณากรอกข้อความ"); return; }

    let phoneList = await calculatePhones();
    if (phoneList.length === 0) { alert("ไม่พบเบอร์โทรศัพท์ที่ถูกต้อง"); return; }

    if (!confirm(`ยืนยันการส่ง SMS ไปยัง ${phoneList.length} เบอร์?`)) return;

    const btn = document.getElementById('btnStartSend');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logContainer = document.getElementById('logContainer');

    btn.disabled = true;
    progressContainer.style.display = 'block';
    logContainer.innerHTML = '';

    let total = phoneList.length;

    for (let i = 0; i < total; i++) {
        let phone = phoneList[i];
        let percent = Math.round(((i + 1) / total) * 100);
        progressBar.style.width = percent + '%';
        progressText.innerText = `${i + 1}/${total}`;

        try {
            let response = await fetch('../api/send_sms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone: phone, message: message, sender_id: senderId })
            });

            let res = await response.json();
            if (res.success) {
                logContainer.innerHTML += `<div class="text-success"><i class="fa-solid fa-check me-1"></i> [${i+1}/${total}] ${phone} : ส่งสำเร็จ</div>`;
                // อัปเดตยอดเครดิตคงเหลือทันทีเมื่อส่งผ่าน
                loadBalance();
            } else {
                logContainer.innerHTML += `<div class="text-danger"><i class="fa-solid fa-xmark me-1"></i> [${i+1}/${total}] ${phone} : ${res.message || 'ส่งไม่สำเร็จ'}</div>`;
            }
        } catch (err) {
            logContainer.innerHTML += `<div class="text-danger"><i class="fa-solid fa-xmark me-1"></i> [${i+1}/${total}] ${phone} : เกิดข้อผิดพลาดทางเครือข่าย</div>`;
        }

        logContainer.scrollTop = logContainer.scrollHeight;
        await new Promise(r => setTimeout(r, 200));
    }

    btn.disabled = false;
    alert("ส่ง SMS เรียบร้อยแล้ว!");
}
