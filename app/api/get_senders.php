<?php
/**
 * ไฟล์: api/get_senders.php
 * วัตถุประสงค์: ดึงรายการ Sender Names ผ่าน Cloudflare Worker Proxy
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$proxyDomain = 'https://deesms-proxy.psingtoroon.workers.dev';
$apiKey      = safeGetSetting('api_key', '');

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า API Key']);
    exit;
}

$apiUrl = rtrim($proxyDomain, '/') . '/v1/senders';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
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
    $senders = $resData['senders'] ?? $resData['data'] ?? $resData ?? [];
    echo json_encode(['status' => 'success', 'data' => $senders], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถดึงข้อมูล Sender Name ได้'], JSON_UNESCAPED_UNICODE);
}
exit;
