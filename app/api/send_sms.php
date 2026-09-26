<?php
/**
 * ไฟล์: api/send_sms.php
 * วัตถุประสงค์: ส่ง SMS ผ่าน SMS Quick SMS API (POST /v1/quicksms/sent)
 */

header('Content-Type: application/json; charset=utf-8');

// 1. รับข้อมูลจาก Frontend (JSON Payload)
$rawInput  = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$phone    = $inputData['phone'] ?? $_POST['phone'] ?? '';
$message  = $inputData['message'] ?? $_POST['message'] ?? '';
$senderId = $inputData['sender_id'] ?? $_POST['sender_id'] ?? ''; // Sender ID ที่เลือกจากดรอปดาวน์

if (empty($phone) || empty($message) || empty($senderId)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุเบอร์โทรศัพท์ ข้อความ และ Sender ID']);
    exit;
}

// 2. จัดรูปแบบเบอร์โทรศัพท์ให้อยู่ในฟอร์แมตที่ถูกต้อง (เช่น 0812345678)
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);

// 3. ตั้งค่า SMS Quick SMS API
$apiKey = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$url    = 'https://api.deesms.net/v1/quicksms/sent';

// Payload ตรงตาม Specification
$payload = [
    'message'   => $message,
    'recipient' => $cleanPhone,
    'sender_id' => $senderId
];

// 4. ส่ง HTTP POST Request ด้วย cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'api-key: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $error]);
    exit;
}

$resData = json_decode($response, true);

// 5. ตอบกลับไปยัง Frontend
if ($httpCode === 200 || $httpCode === 201) {
    echo json_encode([
        'success'     => true,
        'message'     => "ส่ง SMS หาเบอร์ {$cleanPhone} สำเร็จ",
        'credit_used' => $resData['data'][0]['credit_used'] ?? 0,
        'sms_id'      => $resData['data'][0]['sms_id'] ?? '',
        'data'        => $resData
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success'   => false,
        'message'   => $resData['message'] ?? 'ส่ง SMS ไม่สำเร็จ',
        'http_code' => $httpCode,
        'details'   => $resData
    ], JSON_UNESCAPED_UNICODE);
}
