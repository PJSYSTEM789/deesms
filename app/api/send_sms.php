<?php
/**
 * ไฟล์: api/send_sms.php
 * วัตถุประสงค์: รับ request จาก Dashboard แล้วยิงส่ง SMS ผ่าน DeeSMS API (POST /v1/messages/send)
 */

header('Content-Type: application/json; charset=utf-8');

// ปิดการแสดง Error/Warning ของ PHP ไม่ให้รบกวนโครงสร้าง JSON
error_reporting(0);
ini_set('display_errors', 0);

// ตรวจสอบว่าเป็น POST Request เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed: ต้องใช้ POST เท่านั้น'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// อ่านข้อมูล JSON ที่ส่งมาจาก Fetch/Axios ฝั่ง Client
$input = json_decode(file_get_contents('php://input'), true);

$phone    = isset($input['phone']) ? trim($input['phone']) : '';
$message  = isset($input['message']) ? trim($input['message']) : '';
$senderId = isset($input['sender_id']) ? trim($input['sender_id']) : '';

// 1. Validation Check: ตรวจสอบความถูกต้องของข้อมูลเบื้องต้น
if (empty($phone) || empty($message) || empty($senderId)) {
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุเบอร์โทร ข้อความ และ Sender ID'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. ตั้งค่า API Key และ Endpoint ของ DeeSMS
$apiKey  = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$apiUrl  = 'https://api.deesms.net/v1/messages/send';

// 3. เตรียม Payload สำหรับส่งไปยัง DeeSMS API
$payload = [
    'sender'     => $senderId,
    'recipients' => [$phone],
    'message'    => $message
];

// 4. เริ่มส่ง Request ด้วย cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // กำหนด Timeout 15 วินาที
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'api-key: ' . $apiKey
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. จัดการ Response และตอบกลับไปยัง Dashboard
if ($httpCode === 200 || $httpCode === 201) {
    $res = json_decode($response, true);
    
    echo json_encode([
        'success'    => true,
        'message'    => 'ส่ง SMS สำเร็จ',
        'api_result' => $res
    ], JSON_UNESCAPED_UNICODE);
} else {
    $res = json_decode($response, true);
    $errorMessage = isset($res['message']) ? $res['message'] : 'เกิดข้อผิดพลาดจาก SMS Gateway (HTTP ' . $httpCode . ')';

    echo json_encode([
        'success'    => false,
        'message'    => $errorMessage,
        'error_code' => $httpCode,
        'curl_error' => $curlError
    ], JSON_UNESCAPED_UNICODE);
}
exit;
