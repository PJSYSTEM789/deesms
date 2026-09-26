<?php
/**
 * ไฟล์: api/get_senders.php
 * วัตถุประสงค์: ดึง Sender Name ทั้งหมดจาก DeeSMS API (GET /v1/senders)
 */

header('Content-Type: application/json; charset=utf-8');

$apiKey  = '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3';
$baseUrl = 'https://api.deesms.net/v1/senders';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'api-key: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $res = json_decode($response, true);
    // ส่งข้อมูลในกระเป๋า data กลับไป
    echo json_encode([
        'success' => true,
        'senders' => $res['data'] ?? []
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'senders' => []
    ]);
}
