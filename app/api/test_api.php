<?php
/**
 * ไฟล์: api/test_api.php
 * วัตถุประสงค์: ตัวกลางทดสอบสถานะการเชื่อมต่อ และดึงรายชื่อ Sender Names จาก Dee SMS
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'Unauthorized: กรุณาล็อกอินเข้าสู่ระบบก่อนใช้งาน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. นำเข้าไฟล์ตั้งค่า (ถ้ามี)
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

// 3. ดึง API Key และ Base URL จากการตั้งค่า
$apiKey = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$baseUrl = 'https://api.deesms.net';

if (function_exists('getSetting')) {
    $apiKey = getSetting('api_key', $apiKey);
    $baseUrl = getSetting('api_url', $baseUrl);
}

$apiUrl = rtrim($baseUrl, '/') . '/api/v1/senders?limit=50';

// 4. ทดสอบการเชื่อมต่อ cURL ไปยัง Dee SMS Gateway
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

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. ประมวลผลและส่งผลลัพธ์คืนให้ Frontend
if ($curlError) {
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อ cURL: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['data'])) {
    echo json_encode([
        'status'  => 'connected',
        'success' => true,
        'senders' => $responseData['data'],
        'message' => 'เชื่อมต่อกับ API Gateway สำเร็จ'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'senders' => [],
        'message' => $responseData['message'] ?? ('HTTP Error Status: ' . $httpCode)
    ], JSON_UNESCAPED_UNICODE);
}
exit;
