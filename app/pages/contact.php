<?php
/**
 * หน้าจัดการสมุดโทรศัพท์ (พร้อม Modal นำเข้าเบอร์แบบหลากหลายช่องทาง)
 * ตำแหน่งไฟล์: pages/contact.php
 */

if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    exit('No direct script access allowed');
}

$userId =$_SESSION['user_id'] ?? 0;
$msg = '';$msgType = '';

// 1. ประมวลผลการบันทึกการนำเข้าเบอร์ (Form Import Submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'import_contacts') {
    $importName = trim($_POST['import_name'] ?? '');
    $note       = trim($_POST['note'] ?? '');
    $rawPhones  =$_POST['phones'] ?? []; // รับค่ามาเป็น Array ของเบอร์โทรศัพท์

    if (is_string($rawPhones)) {
        $rawPhones = explode("\n", $rawPhones);
    }

    $validPhones = [];
    foreach ($rawPhones as$p) {
        // กรองเอาเฉพาะตัวเลข
        $cleanPhone = preg_replace('/[^0-9]/', '', trim($p));
        if (!empty($cleanPhone)) {
            $validPhones[] =$cleanPhone;
        }
    }

    // ลบเบอร์ที่ซ้ำกันในชุดที่นำเข้า
    $validPhones = array_unique($validPhones);

    if (!empty($validPhones)) {
        try {
            $pdo->beginTransaction();
            $stmt =$pdo->prepare("INSERT INTO contacts (user_id, name, phone, group_name) VALUES (:uid, :name, :phone, :group_name)");

            $count = 0;
            foreach ($validPhones as$phone) {
                // กำหนดชื่อรายชื่อให้เป็น "ชื่อนำเข้า" หรือถ้าไม่มีให้ใช้ชื่อเริ่มต้น
                $contactName = !empty($importName) ?$importName : 'นำเข้า ' . date('Y-m-d H:i');
                $groupName   = !empty($note) ? $note : 'ทั่วไป';

                $stmt->execute([
                    ':uid'        => $userId,
                    ':name'       => $contactName,
                    ':phone'      => $phone,
                    ':group_name' => $groupName
                ]);
                $count++;
            }
            $pdo->commit();

            $msg = "นำเข้าข้อมูลสำเร็จจำนวน " . number_format($count) . " เบอร์";
            $msgType = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();$msg = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();$msgType = "danger";
        }
    } else {
        $msg = "ไม่พบเบอร์โทรศัพท์ที่ถูกต้องในการนำเข้า";
        $msgType = "warning";
    }
}

// 2. จัดการการลบรายชื่อ
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt =$pdo->prepare("DELETE FROM contacts WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $deleteId, ':uid' =>$userId]);
    header("Location: index.php?page=contact");
    exit;
}

// 3. ดึงข้อมูลรายชื่อและสรุปผล
$contacts = [];
$groupCounts = [];$totalContacts = 0;

try {
    $stmt =$pdo->prepare("SELECT * FROM contacts WHERE user_id = :uid ORDER BY id DESC");
    $stmt->execute([':uid' =>$userId]);
    $contacts =$stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalContacts = count($contacts);

    $stmtGroup =$pdo->prepare("SELECT group_name, COUNT(*) as total FROM contacts WHERE user_id = :uid GROUP BY group_name");
    $stmtGroup->execute([':uid' =>$userId]);
    $groupCounts =$stmtGroup->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch contacts error: " . $e->getMessage());
}
?>

<!-- นำเข้า SheetJS สำหรับอ่านไฟล์ Excel -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="fa-solid fa-address-book text-primary me-2"></i>จัดการสมุดโทรศัพท์ (Contacts)
        </h4>
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#importContactModal">
            <i class="fa-solid fa-file-import me-1"></i>นำเข้าเบอร์โทรศัพท์
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 bg-primary text-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="small text-white-50">จำนวนเบอร์ทั้งหมด</div>
                        <h3 class="fw-bold mb-0"><?= number_format($totalContacts) ?> เบอร์</h3>
                    </div>
                    <i class="fa-solid fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        
        <?php foreach ($groupCounts as$g): ?>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm rounded-3 bg-white p-3 border-start border-4 border-info">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">กลุ่ม / หมายเหตุ: <?= htmlspecialchars($g['group_name']) ?></div>
                            <h4 class="fw-bold text-dark mb-0"><?= number_format($g['total']) ?> เบอร์</h4>
                        </div>
                        <span class="badge bg-info bg-opacity-10 text-info rounded-circle p-3"><i class="fa-solid fa-layer-group fs-5"></i></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-list me-2 text-muted"></i>รายการเบอร์ที่บันทึกไว้</h5>
            <input type="text" id="searchContact" class="form-control form-control-sm w-auto" placeholder="ค้นหาชื่อ/เบอร์/กลุ่ม...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="contactTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">ลำดับ</th>
                            <th>ชื่อนำเข้า / ชื่อผู้รับ</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th>กลุ่ม / หมายเหตุ</th>
                            <th>วันที่บันทึก</th>
                            <th class="text-center" style="width: 100px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-address-book fa-2x mb-2 d-block"></i>
                                    ยังไม่มีรายชื่อในสมุดโทรศัพท์
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $index =>$c): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></td>
                                    <td><i class="fa-solid fa-phone me-1 text-muted small"></i><?= htmlspecialchars($c['phone']) ?></td>
                                    <td><span class="badge bg-light text-primary border"><?= htmlspecialchars($c['group_name']) ?></span></td>
                                    <td class="small text-muted"><?= htmlspecialchars($c['created_at']) ?></td>
                                    <td class="text-center">
                                        <a href="index.php?page=contact&delete_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบรายชื่อนี้?');">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal นำเข้าเบอร์แบบอเนกประสงค์ -->
<div class="modal fade" id="importContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" id="importForm">
                <input type="hidden" name="action" value="import_contacts">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-import me-2 text-primary"></i>นำเข้าข้อมูลรายชื่อ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- ช่องกรอกข้อมูลพื้นฐาน -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">ชื่อนำเข้า <span class="text-danger">*</span></label>
                            <input type="text" name="import_name" class="form-control" placeholder="เช่น รายชื่อลูกค้าแคมเปญ A" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">หมายเหตุ / กลุ่ม</label>
                            <input type="text" name="note" class="form-control" placeholder="เช่น ลูกค้า VIP, พฤศจิกายน" value="ทั่วไป">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- เลือกรูปแบบการนำเข้า (Tabs) -->
                    <label class="form-label small fw-bold mb-2">เลือกช่องทางการนำเข้าเบอร์</label>
                    <ul class="nav nav-pills nav-fill mb-3" id="importTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active small" id="text-tab" data-bs-toggle="tab" data-bs-target="#text-pane" type="button"><i class="fa-solid fa-align-left me-1"></i>วางเบอร์หลายๆ เบอร์</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link small" id="file-tab" data-bs-toggle="tab" data-bs-target="#file-pane" type="button"><i class="fa-solid fa-file-excel me-1"></i>Excel / CSV / vCard</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link small" id="gsheet-tab" data-bs-toggle="tab" data-bs-target="#gsheet-pane" type="button"><i class="fa-solid fa-table me-1"></i>Google Sheets</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importTabContent">
                        <!-- Tab 1: วางเบอร์หลายๆ เบอร์ (Textarea) -->
                        <div class="tab-pane fade show active" id="text-pane" role="tabpanel">
                            <div class="mb-2">
                                <textarea id="manualPhones" class="form-control" rows="6" placeholder="กรอก หรือ วางเบอร์โทรศัพท์ (1 เบอร์ต่อ 1 บรรทัด)&#10;เช่น:&#10;0812345678&#10;0898765432"></textarea>
                            </div>
                        </div>

                        <!-- Tab 2: อัปโหลดไฟล์ Excel, CSV, vCard -->
                        <div class="tab-pane fade" id="file-pane" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small text-muted">รองรับไฟล์ .xlsx, .xls, .csv และ .vcf (vCard)</label>
                                <input type="file" id="fileInput" class="form-control" accept=".xlsx, .xls, .csv, .vcf">
                            </div>
                        </div>

                        <!-- Tab 3: Google Sheets -->
                        <div class="tab-pane fade" id="gsheet-pane" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small text-muted">วาง URL ของ Google Sheets (ต้องตั้งค่าแชร์เป็น "ทุกคนที่มีลิงก์")</label>
                                <div class="input-group mb-2">
                                    <input type="url" id="gsheetUrl" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                                    <button class="btn btn-outline-primary" type="button" id="btnFetchGSheet">ดึงข้อมูล</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- พื้นที่ซ่อนสำหรับเก็บรายการเบอร์ที่จะส่งไป Backend -->
                    <div id="hiddenPhonesContainer"></div>

                    <!-- ส่วนแสดงสรุปจำนวนเบอร์ -->
                    <div class="alert alert-info d-flex justify-content-between align-items-center mt-3 mb-0 py-2">
                        <span class="small fw-bold"><i class="fa-solid fa-calculator me-1"></i>จำนวนเบอร์ที่พร้อมนำเข้า:</span>
                        <span class="fs-5 fw-bold text-primary" id="phoneCountDisplay">0 เบอร์</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitImport" disabled>
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i>บันทึกการนำเข้า
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript ประมวลผลการนำเข้าแบบต่างๆ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    let extractedPhones = new Set();

    const manualPhonesInput = document.getElementById('manualPhones');
    const fileInput         = document.getElementById('fileInput');
    const gsheetUrlInput    = document.getElementById('gsheetUrl');
    const btnFetchGSheet    = document.getElementById('btnFetchGSheet');
    const phoneCountDisplay = document.getElementById('phoneCountDisplay');
    const btnSubmitImport   = document.getElementById('btnSubmitImport');
    const hiddenContainer   = document.getElementById('hiddenPhonesContainer');

    // ฟังก์ชันอัปเดตการแสดงผลจำนวนเบอร์และเตรียมส่งข้อมูล
    function updatePhoneList(phonesArray) {
        extractedPhones.clear();
        phonesArray.forEach(p => {
            let clean = p.replace(/[^0-9]/g, '');
            if (clean.length >= 9 && clean.length <= 12) {
                extractedPhones.add(clean);
            }
        });

        const count = extractedPhones.size;
        phoneCountDisplay.textContent = count.toLocaleString() + ' เบอร์';
        btnSubmitImport.disabled = count === 0;

        // สร้าง Hidden input
        hiddenContainer.innerHTML = '';
        extractedPhones.forEach(phone => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'phones[]';
            input.value = phone;
            hiddenContainer.appendChild(input);
        });
    }

    // 1. อ่านจาก Textarea (1 เบอร์ต่อ 1 บรรทัด)
    manualPhonesInput.addEventListener('input', function () {
        const lines = this.value.split('\n');
        updatePhoneList(lines);
    });

    // 2. อ่านจากไฟล์ (Excel, CSV, vCard)
    fileInput.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const fileName = file.name.toLowerCase();

        if (fileName.endsWith('.vcf')) {
            // อ่านไฟล์ vCard
            const reader = new FileReader();
            reader.onload = function (e) {
                const text = e.target.result;
                const matches = text.match(/TEL[^\n:]*:?([0-9\-\+\s]+)/gi) || [];
                const phones = matches.map(m => m.replace(/TEL[^\n:]*:?/i, ''));
                updatePhoneList(phones);
            };
            reader.readAsText(file);
        } else {
            // อ่านไฟล์ Excel หรือ CSV ผ่าน SheetJS
            const reader = new FileReader();
            reader.onload = function (e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

                let phones = [];
                jsonData.forEach(row => {
                    row.forEach(cell => {
                        if (cell) phones.push(String(cell));
                    });
                });
                updatePhoneList(phones);
            };
            reader.readAsArrayBuffer(file);
        }
    });

    // 3. อ่านจาก Google Sheets
    btnFetchGSheet.addEventListener('click', function () {
        const url = gsheetUrlInput.value.trim();
        const matches = url.match(/\/d\/([a-zA-Z0-9-_]+)/);

        if (!matches) {
            alert('กรุณากรอก URL ของ Google Sheets ให้ถูกต้อง');
            return;
        }

        const sheetId = matches[1];
        const csvUrl = `https://docs.google.com/spreadsheets/d/${sheetId}/export?format=csv`;

        btnFetchGSheet.disabled = true;
        btnFetchGSheet.innerText = 'กำลังดึงข้อมูล...';

        fetch(csvUrl)
            .then(res => {
                if (!res.ok) throw new Error('ไม่สามารถดึงข้อมูลได้ ตรวจสอบสิทธิ์การแชร์ไฟล์');
                return res.text();
            })
            .then(csvText => {
                const workbook = XLSX.read(csvText, { type: 'string' });
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

                let phones = [];
                jsonData.forEach(row => {
                    row.forEach(cell => {
                        if (cell) phones.push(String(cell));
                    });
                });
                updatePhoneList(phones);
                alert('ดึงข้อมูลจาก Google Sheets สำเร็จ!');
            })
            .catch(err => {
                alert(err.message);
            })
            .finally(() => {
                btnFetchGSheet.disabled = false;
                btnFetchGSheet.innerText = 'ดึงข้อมูล';
            });
    });

    // ค้นหาตาราง
    const searchInput = document.getElementById('searchContact');
    const tableBody = document.querySelector('#contactTable tbody');
    if (searchInput && tableBody) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.trim().toLowerCase();
            const rows = tableBody.getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent || rows[i].innerText;
                rows[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
            }
        });
    }
});
</script>
