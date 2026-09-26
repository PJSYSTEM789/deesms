<?php
/**
 * หน้าแดชบอร์ดสรุปภาพรวม โปรไฟล์ และดึงเครดิตคงเหลือจาก Dee SMS API
 * ตำแหน่งไฟล์: pages/dashboard.php
 */

// 1. ตรวจสอบการเข้าถึงไฟล์ตรงและการตรวจสอบ Session
if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    die('<div style="padding:20px; color:red;">ไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้ (Direct Access Denied)</div>');
}

$userId = $_SESSION['user_id'] ?? 0;

// ตัวแปรสำหรับเก็บข้อมูล
$userProfile = null;
$stats = [
    'total_sent' => 0,
    'success_sent' => 0,
    'failed_sent' => 0,
    'remaining_credits' => 0
];
$recentLogs = [];

/**
 * ฟังก์ชันดึงยอดเครดิตคงเหลือจาก Dee SMS API
 * 
 * @param string $apiKey API Key ของ Dee SMS
 * @return array ผลลัพธ์พร้อมสถานะ [success => bool, credit => int, message => string]
 */
function getDeeSmsCredit(string $apiKey): array {
    if (empty($apiKey)) {
        return [
            'success' => false,
            'credit'  => 0,
            'message' => 'ยังไม่ได้ตั้งค่า API Key'
        ];
    }

    // Endpoint สำหรับดึงข้อมูลยอดเครดิตของ Dee SMS
    $apiUrl = 'https://api.deesms.net/api/v1/credit'; // ปรับ URL Endpoint ตามคู่มือของคุณหากต่างจากนี้

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10, // กำหนด Timeout 10 วินาทีป้องกันหน้าเว็บค้าง
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . trim($apiKey),
            'Accept: application/json'
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // ตรวจสอบ cURL Error
    if ($curlError) {
        return [
            'success' => false,
            'credit'  => 0,
            'message' => 'cURL Error: ' . $curlError
        ];
    }

    // แปลงผลลัพธ์ JSON
    $responseData = json_decode($response, true);

    // ตรวจสอบตามโครงสร้าง Response ของ Dee SMS ( status == 200 หรือมี key credit )
    if ($httpCode === 200 && isset($responseData['credit'])) {
        return [
            'success' => true,
            'credit'  => (int)$responseData['credit'],
            'message' => $responseData['message'] ?? 'ดึงข้อมูลสำเร็จ'
        ];
    }

    return [
        'success' => false,
        'credit'  => 0,
        'message' => $responseData['message'] ?? ('HTTP Error Code: ' . $httpCode)
    ];
}

// 2. ดึง API Key จากระบบ (ปรับตามวิธีเก็บ API Key ของคุณ)
$deesmsApiKey = function_exists('getSetting') ? getSetting('api_key', '') : 'YOUR_DEESMS_API_KEY_HERE';

// ดึงเครดิตคงเหลือจริงจาก Dee SMS API
$deeSmsApiResult = getDeeSmsCredit($deesmsApiKey);

// 3. ดึงข้อมูลจากฐานข้อมูลท้องถิ่น
if (isset($pdo) && $userId > 0) {
    try {
        // ดึงข้อมูลโปรไฟล์ผู้ใช้
        $stmtUser = $pdo->prepare("SELECT id, username, fullname, email, role, credits, created_at FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userProfile = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // ดึงข้อมูลสถิติตัวเลขรวม
        if ($userProfile && $userProfile['role'] === 'admin') {
            // สถิติสำหรับ Admin (ดูภาพรวมทั้งระบบ)
            $stmtStats = $pdo->query("
                SELECT 
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as success_sent,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_sent
                FROM sms_logs
            ");
        } else {
            // สถิติสำหรับ User ทั่วไป (ดูเฉพาะของตนเอง)
            $stmtStats = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as success_sent,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_sent
                FROM sms_logs 
                WHERE user_id = ?
            ");
            $stmtStats->execute([$userId]);
        }
        
        $dbStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        if ($dbStats) {
            $stats['total_sent']   = (int)($dbStats['total_sent'] ?? 0);
            $stats['success_sent'] = (int)($dbStats['success_sent'] ?? 0);
            $stats['failed_sent']  = (int)($dbStats['failed_sent'] ?? 0);
        }

        // ดึงรายการส่ง SMS ล่าสุด 5 รายการ
        if ($userProfile && $userProfile['role'] === 'admin') {
            $stmtRecent = $pdo->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 5");
        } else {
            $stmtRecent = $pdo->prepare("SELECT * FROM sms_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmtRecent->execute([$userId]);
        }
        $recentLogs = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Dashboard Data Fetch Error: " . $e->getMessage());
    }
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="mb-4">
        <h4 class="fw-bold text-primary mb-1">
            <i class="fa-solid fa-gauge-high me-2"></i>ภาพรวมระบบ (Dashboard)
        </h4>
        <p class="text-muted small mb-0">ยินดีต้อนรับกลับมา! สรุปข้อมูลโปรไฟล์และสถิติการใช้งานของคุณ</p>
    </div>

    <div class="row g-3 mb-4">
        <!-- ข้อมูลโปรไฟล์ (Profile Card) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <div class="avatar-circle mx-auto bg-primary text-white d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 80px; height: 80px; font-size: 32px;">
                            <i class="fa-solid fa-user"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($userProfile['fullname'] ?? $userProfile['username'] ?? 'ผู้ใช้งาน') ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($userProfile['email'] ?? '-') ?></p>
                    
                    <div class="mb-3">
                        <span class="badge <?= ($userProfile['role'] ?? '') === 'admin' ? 'bg-danger' : 'bg-secondary' ?> px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-user-shield me-1"></i><?= strtoupper($userProfile['role'] ?? 'USER') ?>
                        </span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between text-start small mb-2">
                        <span class="text-muted">ชื่อผู้ใช้ (Username):</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($userProfile['username'] ?? '-') ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-start small mb-2">
                        <span class="text-muted">เครดิตผู้ใช้งาน (Local):</span>
                        <span class="fw-bold text-success"><?= number_format($userProfile['credits'] ?? 0) ?> เครดิต</span>
                    </div>
                    <div class="d-flex justify-content-between text-start small">
                        <span class="text-muted">วันที่สมัครสมาชิก:</span>
                        <span class="text-dark"><?= isset($userProfile['created_at']) ? date('d/m/Y', strtotime($userProfile['created_at'])) : '-' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- สรุปตัวเลขสถิติ (Stat Cards) -->
        <div class="col-lg-8">
            <div class="row g-3">
                <!-- การ์ดที่ 1: การส่งทั้งหมด -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 border-start border-primary border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-bold text-uppercase">จำนวนการส่งทั้งหมด</span>
                                <h3 class="fw-bold text-primary mb-0 mt-1"><?= number_format($stats['total_sent']) ?></h3>
                            </div>
                            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                                <i class="fa-solid fa-paper-plane fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- การ์ดที่ 2: ส่งสำเร็จ -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 border-start border-success border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-bold text-uppercase">ส่งสำเร็จ (Success)</span>
                                <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($stats['success_sent']) ?></h3>
                            </div>
                            <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
                                <i class="fa-solid fa-circle-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- การ์ดที่ 3: ส่งล้มเหลว -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 border-start border-danger border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-bold text-uppercase">ส่งล้มเหลว (Failed)</span>
                                <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($stats['failed_sent']) ?></h3>
                            </div>
                            <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle">
                                <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- การ์ดที่ 4: เครดิตคงเหลือจาก Dee SMS API -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 border-start border-warning border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-bold text-uppercase">เครดิต Dee SMS API</span>
                                <?php if ($deeSmsApiResult['success']): ?>
                                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($deeSmsApiResult['credit']) ?></h3>
                                    <span class="text-success small"><i class="fa-solid fa-circle-check me-1"></i>เชื่อมต่อ API สำเร็จ</span>
                                <?php else: ?>
                                    <h5 class="fw-bold text-danger mb-0 mt-1">ไม่สามารถดึงข้อมูลได้</h5>
                                    <span class="text-muted small" title="<?= htmlspecialchars($deeSmsApiResult['message']) ?>">
                                        <?= htmlspecialchars(mb_strimwidth($deeSmsApiResult['message'], 0, 25, '...')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle">
                                <i class="fa-solid fa-coins fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายการล่าสุด -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-list me-1 text-primary"></i>ประวัติการส่ง SMS ล่าสุด
                    </h6>
                    <a href="?page=history" class="btn btn-link btn-sm p-0 text-decoration-none">ดูทั้งหมด <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="card-body p-0 mt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">เวลา</th>
                                    <th>เบอร์ผู้รับ</th>
                                    <th>ข้อความ</th>
                                    <th class="text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLogs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">ยังไม่มีประวัติการส่ง SMS</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <?php 
                                            $badgeClass = ($log['status'] ?? '') === 'SUCCESS' ? 'bg-success' : 'bg-danger';
                                            $msgShort = mb_strimwidth($log['message'] ?? '', 0, 30, '...');
                                        ?>
                                        <tr>
                                            <td class="ps-3 text-nowrap"><?= date('H:i d/m/Y', strtotime($log['created_at'] ?? 'now')) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($log['recipient'] ?? '-') ?></td>
                                            <td title="<?= htmlspecialchars($log['message'] ?? '') ?>"><?= htmlspecialchars($msgShort) ?></td>
                                            <td class="text-center"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($log['status'] ?? 'UNKNOWN') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
