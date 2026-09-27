<?php
/**
 * ไฟล์: test_env.php (แก้ไขเพิ่มเติม User-Agent สำหรับหลบ Cloudflare Basic Check)
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🧪 ระบบตรวจสอบสภาพแวดล้อม Hosting (Bypass Cloudflare Header)</h2>";

$url = 'https://api.deesms.net/v1/profile/balance';

// กำหนด User-Agent ให้เหมือน Google Chrome บน Windows
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// 1. ทดสอบยิง cURL พร้อม User-Agent
if (function_exists('curl_init')) {
    echo "<h3>1) ทดสอบยิง API ด้วย cURL + Custom User-Agent:</h3>";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => $userAgent,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: th-TH,th;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection: keep-alive'
        ],
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "❌ cURL Error: <span style='color:red;'>" . htmlspecialchars($err) . "</span><br>";
    } else {
        echo "✅ HTTP Response Code: <b>{$httpCode}</b><br>";
        if ($httpCode === 403) {
            echo "❌ <span style='color:red;'>ยังติด Cloudflare Block (403 Forbidden)</span><br>";
        } else {
            echo "🎉 <span style='color:green;'>เชื่อมต่อสำเร็จ! ไม่ติด Cloudflare</span><br>";
        }
        echo "📄 Response Data: <pre>" . htmlspecialchars(substr($res, 0, 500)) . "...</pre>";
    }
}
