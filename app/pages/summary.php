<?php
/**
 * หน้าสรุปการใช้งานเครดิต และ รายการเบอร์ Blacklist
 * ตำแหน่งไฟล์: pages/summary.php
 */

if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    exit('No direct script access allowed');
}

$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// 1. คำนวณยอดรวมเครดิตที่ใช้ไปทั้งหมดจากฐานข้อมูล sms_logs
$totalCreditUsed = 0;
$totalLogsCount = 0;

try {
    if (isset($pdo)) {
        // คำนวณผลรวมเครดิตที่ใช้
        $sqlCredit = "SELECT SUM(credit_used) AS total_credit, COUNT(*) AS total_count FROM sms_logs" . ($isAdmin ? "" : " WHERE user_id = :uid");
        $stmtCredit = $pdo->prepare($sqlCredit);
        if (!$isAdmin) {
            $stmtCredit->bindValue(':uid', $userId, PDO::PARAM_INT);
        }
        $stmtCredit->execute();
        $creditData = $stmtCredit->fetch(PDO::FETCH_ASSOC);

        $totalCreditUsed = (int)($creditData['total_credit'] ?? 0);
        $totalLogsCount = (int)($creditData['total_count'] ?? 0);
    }
} catch (PDOException $e) {
    error_log("Summary calculation error: " . $e->getMessage());
}

// 2. ดึงข้อมูลเบอร์ Blacklist จาก Dee SMS API
$blacklistRes = getDeeSmsBlacklist();
$blacklistedNumbers = $blacklistRes['data'] ?? [];
$apiError = $blacklistRes['status'] ? null : $blacklistRes['message'];
?>

<div class="container-fluid py-3">
    <!-- หัวข้อหน้าเว็บ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="fa-solid fa-chart-column text-primary me-2"></i>สรุปภาพรวมและการจัดการ Blacklist
        </h4>
    </div>

    <!-- ส่วนที่ 1: การ์ดสรุปข้อมูลเครดิต -->
    <div class="row g-3 mb-4">
        <!-- ยอดเครดิตที่ใช้ไปทั้งหมด -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-primary border-4">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                        <i class="fa-solid fa-coins fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small">เครดิตที่ใช้ไปทั้งหมด</div>
                        <h3 class="fw-bold text-primary mb-0"><?= number_format($totalCreditUsed) ?> <span class="fs-6 text-muted">เครดิต</span></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- จำนวนรายการส่งทั้งหมด -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-info border-4">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                        <i class="fa-solid fa-list-check fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small">รายการส่งข้อความทั้งหมด</div>
                        <h3 class="fw-bold text-info mb-0"><?= number_format($totalLogsCount) ?> <span class="fs-6 text-muted">รายการ</span></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- จำนวนเบอร์ใน Blacklist -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-danger border-4">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3">
                        <i class="fa-solid fa-user-slash fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small">เบอร์โทรศัพท์ติด Blacklist</div>
                        <h3 class="fw-bold text-danger mb-0"><?= number_format(count($blacklistedNumbers)) ?> <span class="fs-6 text-muted">เบอร์</span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($apiError): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($apiError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ส่วนที่ 2: ตารางแสดงรายการเบอร์ Blacklist -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">
                <i class="fa-solid fa-ban text-danger me-2"></i>รายการเบอร์โทรศัพท์ติด Blacklist (Dee SMS)
            </h5>
            <input type="text" id="searchBlacklist" class="form-control form-control-sm w-auto" placeholder="ค้นหาเบอร์โทรศัพท์...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="blacklistTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">ลำดับ</th>
                            <th>เบอร์โทรศัพท์ (Recipient)</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blacklistedNumbers)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-shield-halved fa-2x mb-2 d-block text-success"></i>
                                    ไม่พบรายการเบอร์ติด Blacklist ในระบบ
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($blacklistedNumbers as $index => $phone): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-bold text-dark">
                                        <i class="fa-solid fa-mobile-screen-button me-2 text-muted"></i><?= htmlspecialchars($phone) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>ติด Blacklist</span>
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

<!-- JavaScript สำหรับการค้นหาเบอร์ในตาราง Blacklist -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchBlacklist');
    const tableBody = document.querySelector('#blacklistTable tbody');

    if (searchInput && tableBody) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.trim().toLowerCase();
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const phoneCell = rows[i].getElementsByTagName('td')[1];
                if (phoneCell) {
                    const textValue = phoneCell.textContent || phoneCell.innerText;
                    if (textValue.toLowerCase().indexOf(filter) > -1) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            }
        });
    }
});
</script>
