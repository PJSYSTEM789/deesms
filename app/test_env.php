<?php
/**
 * ไฟล์: test_env.php
 * วัตถุประสงค์: ตรวจสอบว่า Hosting (Wasmer) รองรับ cURL และการยิง Outbound HTTP หรือไม่
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🧪 ระบบตรวจสอบสภาพแวดล้อม Hosting</h2>";

// 1. ตรวจสอบ cURL Extension
if (function_exists('curl_version')) {
    echo "✅ <b>cURL Extension:</b> เปิดใช้งานอยู่<br>";
    $curlInfo = curl_version();
    echo "ℹ️ cURL Version: " . $curlInfo['version'] . "<br>";
} else {
    echo "❌ <b>cURL Extension:</b> <span style='color:red;'>ไม่ถูกเปิดใช้งานบน Server นี้!</span><br>";
}

echo "<hr>";

// 2. ทดสอบการยิง Request ไปยัง Dee SMS ด้วย cURL
echo "<h3>1) ทดสอบยิง API ด้วย cURL:</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.deesms.net/v1/profile/balance');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        echo "❌ cURL Error: <span style='color:red;'>" . htmlspecialchars($err) . "</span><br>";
    } else {
        echo "✅ HTTP Response Code: <b>{$httpCode}</b><br>";
        echo "📄 Response Data: <pre>" . htmlspecialchars($res) . "</pre>";
    }
} else {
    echo "⚠️ ไม่สามารถทดสอบ cURL ได้เนื่องจากฟังก์ชันถูกปิดใช้งาน<br>";
}

echo "<hr>";

// 3. ทดสอบการยิง API ด้วย file_get_contents (Stream Context)
echo "<h3>2) ทดสอบยิง API ด้วย file_get_contents (Alternative):</h3>";
$options = [
    'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$streamRes = @file_get_contents('https://api.deesms.net/v1/profile/balance', false, $context);

if ($streamRes !== false) {
    echo "✅ stream_get_contents สำเร็จ!<br>";
    echo "📄 Response Data: <pre>" . htmlspecialchars($streamRes) . "</pre>";
} else {
    echo "❌ stream_get_contents ล้มเหลว (Server อาจจะบล็อก outbound connections)<br>";
}
