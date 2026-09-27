<?php
/**
 * ไฟล์: api/get_history.php
 * วัตถุประสงค์: ดึงข้อมูลประวัติการส่ง SMS จาก Dee SMS API (Send History Endpoint)
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

// 2. รับและตรวจสอบ Query Parameters
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
$page      = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;

if ($limit > 5000) {
    $limit = 5000;
}

// 3. ตั้งค่า API Key และ Endpoint URL
$apiKey  = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$baseUrl = 'https://api.deesms.net/api/v1/history'; // URL Endpoint สำหรับ Send History

$queryParams = [
    'page'  => $page,
    'limit' => $limit
];

if (!empty($startDate)) {
    $queryParams['start_date'] = $startDate;
}
if (!empty($endDate)) {
    $queryParams['end_date'] = $endDate;
}

$apiUrl = $baseUrl . '?' . http_build_query($queryParams);

// 4. เริ่มต้น cURL Request (GET)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
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

// 5. ส่งผลลัพธ์กลับไปยัง Client
if ($httpCode === 200) {
    // กรณีข้อมูลอยู่ใน data
    $historyData = $responseData['data'] ?? [];
    
    echo json_encode([
        'success'    => true,
        'data'       => $historyData,
        'page'       => $responseData['page'] ?? $page,
        'limit'      => $responseData['limit'] ?? $limit,
        'total_item' => $responseData['total_item'] ?? count((array)$historyData),
        'total_page' => $responseData['total_page'] ?? 1,
        'message'    => $responseData['message'] ?? 'ดึงข้อมูลประวัติสำเร็จ'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code($httpCode >= 400 ? $httpCode : 400);
    echo json_encode([
        'success' => false,
        'message' => $responseData['message'] ?? ('HTTP Error Status: ' . $httpCode)
    ], JSON_UNESCAPED_UNICODE);
}
exit;
