<?php
/**
 * ไฟล์: test_proxy.php
 * วัตถุประสงค์: ทดสอบการยิง API ผ่าน Cloudflare Worker Proxy และตรวจสอบ Debug Response
 */

header('Content-Type: text/html; charset=utf-8');

// 1. ระบุ URL ของ Cloudflare Worker Proxy
$proxyUrl = 'https://deesms-proxy.psingtoroon.workers.dev/v1/profile/balance';

// 2. ระบุ API Key ของคุณจาก Dee SMS (กรุณาเปลี่ยนเป็น API Key จริงของคุณ)
$apiKey = 'YOUR_DEESMS_API_KEY_HERE'; 

echo "<h2>🧪 ทดสอบการเชื่อมต่อ Dee SMS ผ่าน Cloudflare Worker Proxy</h2>";

if ($apiKey === 'YOUR_DEESMS_API_KEY_HERE') {
    echo "<p style='color:red;'>⚠️ กรุณาแก้ไขตัวแปร \$apiKey ในไฟล์ test_proxy.php ให้เป็น API Key จริงก่อนทดสอบครับ</p>";
    exit;
}

// 3. เริ่มต้นการส่ง cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $proxyUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'api-key: ' . trim($apiKey),
        'Accept: application/json',
        'Content-Type: application/json'
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// 4. แสดงผลลัพธ์การทดสอบ
echo "<b>Target Proxy URL:</b> " . htmlspecialchars($proxyUrl) . "<br>";
echo "<b>HTTP Status Code:</b> " . $httpCode . "<br><br>";

if ($curlErr) {
    echo "❌ <b>cURL Error:</b> " . htmlspecialchars($curlErr) . "<br>";
} else {
    echo "<b>Response Raw Data:</b>";
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($response) . "</pre>";

    $json = json_decode($response, true);
    if ($httpCode === 200) {
        echo "🎉 <b style='color:green;'>เชื่อมต่อ成功! ดึงข้อมูลสำเร็จ</b><br>";
    } elseif ($httpCode === 403) {
        echo "❌ <b style='color:red;'>ติด 403 Forbidden จาก Dee SMS</b> (โปรดเช็กว่า API Key ถูกต้องหรือไม่ หรือ Account ถูกระงับ)<br>";
    } else {
        echo "⚠️ <b style='color:orange;'>HTTP Status: {$httpCode}</b><br>";
    }
}
