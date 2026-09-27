<?php
/**
 * ไฟล์: api/get_senders.php
 * วัตถุประสงค์: ดึง Sender Name ทั้งหมดจาก DeeSMS API (GET /v1/senders)
 */

header('Content-Type: application/json; charset=utf-8');

// ซ่อน Warning/Error ของ PHP ไม่ให้ปนออกมากับ JSON
error_reporting(0);
ini_set('display_errors', 0);

$apiKey  = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$baseUrl = 'https://api.deesms.net/v1/senders';

// เริ่มต้น cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // ตั้งเวลา Timeout 10 วินาที
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'api-key: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ตรวจสอบว่าเรียก API สำเร็จหรือไม่ (HTTP 200)
if ($httpCode === 200 && $response) {
    $res = json_decode($response, true);
    
    // ดึงรายการ sender จาก DeeSMS (หากไม่มีให้เป็น array ว่าง)
    $sendersData = $res['data'] ?? [];

    echo json_encode([
        'success' => true,
        'senders' => $sendersData
    ], JSON_UNESCAPED_UNICODE);
} else {
    // กรณียิง API ไม่สำเร็จ (เช่น API Key ผิด, Server ล่ม หรือ Timeout)
    // ใช้ Mock Data เป็นข้อมูลสำรอง เพื่อให้ระบบหน้าบ้านยังทำงานได้
    $fallbackSenders = [
        [
            'id' => 'SMS_INFO',
            'name' => 'SMS_INFO',
            'is_active' => true,
            'default' => true
        ],
        [
            'id' => 'MY_STORE',
            'name' => 'MY_STORE',
            'is_active' => true,
            'default' => false
        ]
    ];

    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถเชื่อมต่อ DeeSMS API ได้ (HTTP ' . $httpCode . ') ใช้ข้อมูลสำรองแทน',
        'senders' => $fallbackSenders,
        'error_detail' => $curlError
    ], JSON_UNESCAPED_UNICODE);
}
exit;
