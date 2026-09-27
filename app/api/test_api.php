<?php
/**
 * ไฟล์: api/test_api.php
 * ตำแหน่งจัดเก็บ: api/test_api.php
 * วัตถุประสงค์: ตรวจสอบการเชื่อมต่อ API Gateway ผ่าน Cloudflare Worker Proxy และดึงรายการ Sender Names
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบการล็อกอินเข้าใช้งาน
if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'Unauthorized: กรุณาล็อกอินเข้าสู่ระบบก่อนใช้งาน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. นำเข้าไฟล์ตั้งค่าระบบ/ฐานข้อมูล
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

// 3. ฟังก์ชันดึงค่าการตั้งค่าจากฐานข้อมูล
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

// 4. กำหนด URL สำหรับ Cloudflare Worker Proxy และดึง API Key
$proxyDomain = 'https://deesms-proxy.psingtoroon.workers.dev';
$apiKey      = safeGetSetting('api_key', '');

if (empty($apiKey)) {
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'ยังไม่ได้ตั้งค่า API Key กรุณาตั้งค่าในหน้า api_settings.php ก่อน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// รวม Proxy URL เข้ากับ Endpoint สำหรับดึงข้อมูล Sender Name (/v1/senders)
$apiUrl = rtrim($proxyDomain, '/') . '/v1/senders';

// 5. ตั้งค่า cURL Request ส่งผ่าน Proxy
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'api-key: ' . trim($apiKey),
        'Accept: application/json',
        'Content-Type: application/json'
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 6. วิเคราะห์ข้อผิดพลาด และส่งผลลัพธ์กลับ
if ($curlError) {
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อ cURL: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบแยกกรณีตาม HTTP Status Code
if ($httpCode === 403) {
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'code'    => 403,
        'message' => 'เกิดข้อผิดพลาด 403 Forbidden: API Key ไม่ถูกต้อง หรือ Sender Name นี้ยังไม่ได้รับอนุมัติให้ใช้งาน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode === 401) {
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'code'    => 401,
        'message' => 'เกิดข้อผิดพลาด 401 Unauthorized: API Key ไม่ถูกต้องหรือหมดอายุ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    // ตรวจสอบรายการ Senders ที่ส่งกลับมาจาก API
    $sendersList = [];
    if (isset($data['senders']) && is_array($data['senders'])) {
        $sendersList = $data['senders'];
    } elseif (isset($data['data']) && is_array($data['data'])) {
        $sendersList = $data['data'];
    } elseif (is_array($data)) {
        $sendersList = $data;
    }

    echo json_encode([
        'status'       => 'connected',
        'success'      => true,
        'proxy_target' => $apiUrl,
        'senders'      => $sendersList,
        'message'      => 'เชื่อมต่อกับ API Gateway ผ่าน Proxy สำเร็จ'
    ], JSON_UNESCAPED_UNICODE);
} else {
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['message'] ?? $errorData['description'] ?? ('HTTP Error Status Code: ' . $httpCode);
    
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'code'    => $httpCode,
        'message' => $errorMessage
    ], JSON_UNESCAPED_UNICODE);
}
exit;
