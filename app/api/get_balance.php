<?php
/**
 * API Endpoint สำหรับดึงยอดเครดิตคงเหลือจาก Dee SMS
 * ตำแหน่งไฟล์: api/get_balance.php
 */

// กำหนด Header ให้ส่งคืนข้อมูลในรูปแบบ JSON
header('Content-Type: application/json; charset=utf-8');

// นำเข้าไฟล์ตั้งค่าระบบและฐานข้อมูล (ปรับ Path ตามโครงสร้างโปรเจกต์ของคุณ)
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php';
}

// 1. ตรวจสอบการเข้าสู่ระบบ (Security Check)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: กรุณาล็อกอินก่อนใช้งาน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. ดึง API Key จากการตั้งค่าระบบ (กำหนดค่า API Key ที่ระบุไว้เป็น Fallback)
$defaultApiKey = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$apiKey = function_exists('getSetting') ? getSetting('api_key', $defaultApiKey) : $defaultApiKey;

// หากค่าที่ได้ยังคงเป็นค่าว่าง ให้ระบุค่าสำรองโดยตรงอีกครั้งเพื่อความถูกต้อง
if (empty($apiKey)) {
    $apiKey = $defaultApiKey;
}

if (empty($apiKey)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'credit'  => 0,
        'message' => 'ยังไม่ได้ตั้งค่า Dee SMS API Key ในระบบ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. กำหนด Endpoint ของ Dee SMS API
$apiUrl = 'https://api.deesms.net/api/v1/credit';

// 4. ตั้งค่า cURL ส่ง Request ไปยัง Dee SMS
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
$curlError = curl_error($ch);
curl_close($ch);

// 5. ตรวจสอบข้อผิดพลาดจากการเชื่อมต่อ cURL
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'credit'  => 0,
        'message' => 'cURL Error: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. แปลงข้อมูลและส่งผลลัพธ์กลับ
$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['credit'])) {
    echo json_encode([
        'success' => true,
        'credit'  => (int)$responseData['credit'],
        'message' => $responseData['message'] ?? 'ดึงข้อมูลสำเร็จ'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code($httpCode >= 400 ? $httpCode : 400);
    echo json_encode([
        'success' => false,
        'credit'  => 0,
        'message' => $responseData['message'] ?? ('HTTP Error Code: ' . $httpCode)
    ], JSON_UNESCAPED_UNICODE);
}
