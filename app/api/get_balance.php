<?php
/**
 * ไฟล์: api/get_balance.php
 * วัตถุประสงค์: ดึงยอดเครดิตคงเหลือจาก Dee SMS API ตาม API Specification ล่าสุด
 */

// 1. ตั้งค่า Header ให้ส่งคืนข้อมูลในรูปแบบ JSON
header('Content-Type: application/json; charset=utf-8');

// ซ่อน PHP Warning/Error ไม่ให้รบกวนการส่งคืน JSON
error_reporting(0);
ini_set('display_errors', 0);

// 2. นำเข้าไฟล์ตั้งค่าระบบและฐานข้อมูล (ถ้ามี)
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php';
}

// 3. ตรวจสอบการเข้าสู่ระบบ (Security Check)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'credit'  => 0,
        'message' => 'Unauthorized: กรุณาล็อกอินเข้าสู่ระบบก่อนใช้งาน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. ดึง API Key
$defaultApiKey = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$apiKey = function_exists('getSetting') ? getSetting('api_key', $defaultApiKey) : $defaultApiKey;

if (empty($apiKey)) {
    $apiKey = $defaultApiKey;
}

// 5. ส่ง Request ไปยัง Dee SMS Get Balance Endpoint
$apiUrl = 'https://api.deesms.net/api/v1/credit'; // หรือ Endpoint Get Balance ของ Dee SMS

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

// 6. ตรวจสอบ cURL Error
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'credit'  => 0,
        'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อ cURL: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 7. ถอดรหัส JSON และตรวจสอบตาม โครงสร้าง Doc (data -> credit)
$responseData = json_decode($response, true);

// ตรวจสอบตามโครงสร้าง: data.credit หรือ credit
$creditValue = null;
if (isset($responseData['data']['credit'])) {
    $creditValue = $responseData['data']['credit'];
} elseif (isset($responseData['credit'])) {
    $creditValue = $responseData['credit'];
}

if ($httpCode === 200 && $creditValue !== null) {
    $msg = $responseData['data']['message'] ?? ($responseData['message'] ?? 'ดึงข้อมูลสำเร็จ');
    echo json_encode([
        'success' => true,
        'credit'  => (int)$creditValue,
        'message' => $msg
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code($httpCode >= 400 ? $httpCode : 400);
    $errorMsg = $responseData['data']['message'] ?? ($responseData['message'] ?? 'HTTP Error Status: ' . $httpCode);
    echo json_encode([
        'success' => false,
        'credit'  => 0,
        'message' => $errorMsg
    ], JSON_UNESCAPED_UNICODE);
}
exit;
