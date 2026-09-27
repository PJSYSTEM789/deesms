<?php
/**
 * ไฟล์: api/get_senders.php
 * วัตถุประสงค์: ดึงรายการ Sender Names ที่ได้รับอนุมัติจาก Dee SMS API
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
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

$baseUrl = safeGetSetting('api_url', 'https://api.deesms.net');
$apiKey  = safeGetSetting('api_key', '');

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า API Key']);
    exit;
}

// 2. ยิง cURL ไปยัง Endpoint /v1/senders
$apiUrl = rtrim($baseUrl, '/') . '/v1/senders';

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
    // แปลงโครงสร้างข้อมูล Sender ให้เป็น Array เรียบง่าย
    $senders = $resData['senders'] ?? $resData['data'] ?? $resData ?? [];
    echo json_encode(['status' => 'success', 'data' => $senders], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถดึงข้อมูล Sender Name ได้'], JSON_UNESCAPED_UNICODE);
}
exit;
