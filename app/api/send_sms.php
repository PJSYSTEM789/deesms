<?php
/**
 * ไฟล์: api/send_sms.php
 * วัตถุประสงค์: API Endpoint สำหรับส่ง SMS ผ่าน Cloudflare Worker Proxy
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

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

$phone   = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$sender  = trim($_POST['sender'] ?? 'SMS');

if (empty($phone) || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกเบอร์โทรศัพท์และข้อความให้ครบถ้วน'], JSON_UNESCAPED_UNICODE);
    exit;
}

// กำหนด URL ของ Cloudflare Worker Proxy
$proxyDomain = 'https://deesms-proxy.psingtoroon.workers.dev';
$apiKey      = safeGetSetting('api_key', '');

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า API Key'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ยิงผ่าน Proxy ไปที่ Path /v1/quicksms/sent
$apiUrl = rtrim($proxyDomain, '/') . '/v1/quicksms/sent';

$payload = json_encode([
    'sender'  => $sender,
    'phone'   => $phone,
    'message' => $message
], JSON_UNESCAPED_UNICODE);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'api-key: ' . trim($apiKey),
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    saveSmsLog($pdo, $phone, $message, 'failed', 'cURL Error: ' . $curlError);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อ cURL: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

$resData = json_decode($response, true);
$resCode = $resData['code'] ?? $resData['status'] ?? '';

if ($httpCode === 200 && ($resCode === '000' || $resCode === 'success' || isset($resData['id']))) {
    saveSmsLog($pdo, $phone, $message, 'success', 'ส่งข้อความสำเร็จ');
    echo json_encode(['status' => 'success', 'message' => 'ส่ง SMS สำเร็จเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
} else {
    $errorMsg = match ($resCode) {
        '001'   => 'รูปแบบข้อมูลไม่ถูกต้อง (Content Type Not Valid)',
        '003'   => 'ยืนยันตัวตนไม่สำเร็จ (API Key ไม่ถูกต้อง)',
        '004'   => 'บริการถูกระงับ (Service Blocked)',
        '006'   => 'Sender Name นี้ไม่ได้รับอนุญาตให้ใช้งาน (Sender Not Allowed)',
        default => $resData['description'] ?? $resData['message'] ?? ('HTTP Status Error: ' . $httpCode)
    };

    saveSmsLog($pdo, $phone, $message, 'failed', $errorMsg);
    echo json_encode(['status' => 'error', 'code' => $resCode, 'message' => 'ส่ง SMS ไม่สำเร็จ: ' . $errorMsg], JSON_UNESCAPED_UNICODE);
}

function saveSmsLog($pdo, $phone, $message, $status, $remark = '') {
    if (isset($pdo)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone_number VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL,
                remark TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status, remark) VALUES (?, ?, ?, ?)");
            $stmt->execute([$phone, $message, $status, $remark]);
        } catch (PDOException $e) {
            error_log("Save SMS Log Error: " . $e->getMessage());
        }
    }
}
exit;
