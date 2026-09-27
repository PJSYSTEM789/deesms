<?php
/**
 * ไฟล์: api/send_sms.php
 * วัตถุประสงค์: ส่ง Quick SMS ผ่าน Dee SMS API (POST)
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

// 2. ตรวจสอบ HTTP Method ต้องเป็น POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed: ต้องใช้ POST เท่านั้น'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. อ่านและตรวจสอบ payload
$input = json_decode(file_get_contents('php://input'), true);

$message   = isset($input['message']) ? trim($input['message']) : '';
$recipient = isset($input['recipient']) ? trim($input['recipient']) : '';
$senderId  = isset($input['sender_id']) ? trim($input['sender_id']) : '';

if (empty($message) || empty($recipient) || empty($senderId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุ message, recipient และ sender_id'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. ตั้งค่า API Key และ Endpoint URL
$apiKey = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$apiUrl = 'https://api.deesms.net/api/v1/send'; // หรือ Endpoint สำหรับ Send Quick SMS

$payload = [
    'message'   => $message,
    'recipient' => $recipient,
    'sender_id' => $senderId
];

// 5. เริ่มต้น cURL Request (POST)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_POSTFIELDS     => json_encode($payload),
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'cURL Error: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$responseData = json_decode($response, true);

// 6. ส่งผลลัพธ์กลับไปยัง Client
if ($httpCode === 200 || $httpCode === 201) {
    echo json_encode([
        'success' => true,
        'data'    => $responseData['data'] ?? [],
        'message' => $responseData['message'] ?? 'ส่ง SMS สำเร็จ'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code($httpCode >= 400 ? $httpCode : 400);
    echo json_encode([
        'success' => false,
        'message' => $responseData['message'] ?? ('HTTP Error Status: ' . $httpCode)
    ], JSON_UNESCAPED_UNICODE);
}
exit;
