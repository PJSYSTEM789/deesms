<?php
/**
 * ไฟล์: api/get_senders.php
 * วัตถุประสงค์: ดึง Sender Name ทั้งหมดจาก Dee SMS API (GET)
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check: ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: กรุณาล็อกอินเข้าสู่ระบบก่อนใช้งาน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. ตั้งค่า API Key และ Endpoint URL
$apiKey  = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$baseUrl = 'https://api.deesms.net/api/v1/senders';

// รับค่า Query Parameter (ถ้ามี)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$apiUrl = $baseUrl . '?limit=' . $limit . '&page=' . $page;

// 3. เริ่มต้น cURL Request (GET)
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

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'cURL Error: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$responseData = json_decode($response, true);

// 4. ประมวลผล Response ตาม Documentation
if ($httpCode === 200 && isset($responseData['data'])) {
    echo json_encode([
        'success' => true,
        'senders' => $responseData['data'],
        'message' => $responseData['message'] ?? 'ดึงข้อมูลผู้ส่งสำเร็จ'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code($httpCode >= 400 ? $httpCode : 400);
    echo json_encode([
        'success' => false,
        'senders' => [],
        'message' => $responseData['message'] ?? ('HTTP Error Status: ' . $httpCode)
    ], JSON_UNESCAPED_UNICODE);
}
exit;
