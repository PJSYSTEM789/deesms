<?php
/**
 * ไฟล์: api/get_dashboard_data.php
 * วัตถุประสงค์: ดึงข้อมูลสรุปสถิติจาก DB และเช็กยอดเครดิตจาก Dee SMS API (/v1/profile/balance)
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

// 2. ฟังก์ชันดึงค่าการตั้งค่า
if (!function_exists('safeGetSetting')) {
    function safeGetSetting($key, $default = '') {
        global $pdo;
        if (function_exists('getSetting')) {
            return getSetting($key, $default);
        }
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? $row['setting_value'] : $default;
            } catch (PDOException $e) {
                return $default;
            }
        }
        return $default;
    }
}

// 3. ดึงยอดเครดิตจาก Dee SMS API (/v1/profile/balance)
$baseUrl = safeGetSetting('api_url', 'https://api.deesms.net');
$apiKey  = safeGetSetting('api_key', '');

$creditBalance = '0.00';
$apiStatus = 'offline';

if (!empty($apiKey)) {
    $apiUrl = rtrim($baseUrl, '/') . '/v1/profile/balance';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . trim($apiKey),
            'Accept: application/json'
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $resData = json_decode($response, true);
        // ดึงค่า balance จาก Response Structure ของ Dee SMS
        $creditBalance = $resData['balance'] ?? $resData['credit'] ?? $resData['data']['balance'] ?? '0.00';
        $apiStatus = 'online';
    }
}

// 4. สรุปสถิติจากฐานข้อมูล sms_logs
$stats = [
    'total_sent' => 0,
    'success'    => 0,
    'failed'     => 0
];
$recentLogs = [];

if (isset($pdo)) {
    try {
        $stmtStats = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status != 'success' THEN 1 ELSE 0 END) as failed
            FROM sms_logs");
        $rowStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        if ($rowStats) {
            $stats['total_sent'] = (int)$rowStats['total'];
            $stats['success']    = (int)$rowStats['success'];
            $stats['failed']     = (int)$rowStats['failed'];
        }

        $stmtLogs = $pdo->query("SELECT phone_number, message, status, created_at FROM sms_logs ORDER BY id DESC LIMIT 5");
        $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // กรณีตารางยังไม่ถูกสร้าง
    }
}

// 5. ส่งผลลัพธ์ JSON
echo json_encode([
    'status'         => 'success',
    'api_status'     => $apiStatus,
    'credit_balance' => number_format((float)$creditBalance, 2),
    'stats'          => $stats,
    'recent_logs'    => $recentLogs
], JSON_UNESCAPED_UNICODE);
exit;
