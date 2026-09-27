<?php
// ================= ================= =================
// 1. ฟังก์ชันหลักสำหรับตรวจสอบค่ายโทรศัพท์ (Prefix Lookup)
// ================= ================= =================
function checkCarrierByPrefix($phoneInput) {
    // ทำความสะอาดตัวเลข (ลบช่องว่าง ขีด และแปลง +66 เป็น 0)
    $phone = preg_replace('/[^0-9]/', '', $phoneInput);
    
    if (strpos($phone, '66') === 0) {
        $phone = '0' . substr($phone, 2);
    }

    // ตรวจสอบความถูกต้องของเบอร์มือถือไทย (10 หลัก ขึ้นต้นด้วย 0)
    if (strlen($phone) !== 10 || substr($phone, 0, 1) !== '0') {
        return [
            'status' => false,
            'message' => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นเบอร์มือถือ 10 หลัก)'
        ];
    }

    $prefix3 = substr($phone, 0, 3);

    // หมวดหมู่ Prefix ดั้งเดิม
    $aisPrefixes  = ['080', '081', '087', '089', '092', '093', '097', '098', '061', '062', '063', '065'];
    $truePrefixes = ['083', '084', '086', '091', '095', '096', '064', '099'];
    $dtacPrefixes = ['082', '085', '088', '090', '094', '066'];

    $carrier = 'UNKNOWN';
    $carrierName = 'ไม่ทราบค่าย / ค่ายอื่นๆ';
    $color = '#6c757d';

    if (in_array($prefix3, $aisPrefixes)) {
        $carrier = 'AIS';
        $carrierName = 'AIS';
        $color = '#28a745';
    } elseif (in_array($prefix3, $truePrefixes)) {
        $carrier = 'TRUE';
        $carrierName = 'TRUE';
        $color = '#dc3545';
    } elseif (in_array($prefix3, $dtacPrefixes)) {
        $carrier = 'DTAC';
        $carrierName = 'DTAC';
        $color = '#007bff';
    }

    return [
        'status' => true,
        'phone' => $phone,
        'carrier' => $carrier,
        'carrier_name' => $carrierName,
        'color' => $color,
        'note' => 'ตรวจสอบจาก Prefix ดั้งเดิม (หากมีการย้ายค่ายเบอร์เดิม ค่ายจริงอาจเปลี่ยนแปลง)'
    ];
}

// ================= ================= =================
// 2. ระบบตรวจจับ Request และประมวลผล
// ================= ================= =================

// เช็คว่าเป็นการเรียกใช้งาน API หรือไม่
$isJsonRequest = (
    isset($_GET['api']) || 
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
);

// หากส่งมาเป็น JSON Body (เช่น จาก fetch/axios) ให้แปลงเข้า $_POST
if ($isJsonRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (isset($jsonData['phone_number'])) {
        $_REQUEST['phone_number'] = $jsonData['phone_number'];
    }
}

// รับค่าเบอร์โทรศัพท์ (รองรับทั้ง GET และ POST)
$phoneNumber = $_REQUEST['phone_number'] ?? null;

// ถ้าเป็นการเรียก API ให้ตอบกลับเป็น JSON ทันที
if ($isJsonRequest) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$phoneNumber) {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'โปรดระบุพารามิเตอร์ phone_number'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $result = checkCarrierByPrefix($phoneNumber);
    if (!$result['status']) {
        http_response_code(400);
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit; // จบการทำงาน ไม่ต้องแสดงผล HTML
}

// ถ้าเป็น Web Page ปกติ ให้ประมวลผลส่งเข้า HTML
$webResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $phoneNumber) {
    $webResult = checkCarrierByPrefix($phoneNumber);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบตรวจสอบค่ายเบอร์โทรศัพท์ + API</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 480px;
        }
        h2 { margin-top: 0; color: #333; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background-color: #0056b3; }
        .result-box {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            background-color: #f8f9fa;
            border-left: 5px solid #ccc;
        }
        .carrier-badge {
            display: inline-block;
            padding: 4px 12px;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 18px;
        }
        .api-info {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
        }
        code {
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            color: #d63384;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>📱 ตรวจสอบค่ายมือถือ</h2>
    
    <!-- ฟอร์มสำหรับหน้าเว็บปกติ -->
    <form method="POST" action="">
        <div class="form-group">
            <label for="phone_number">กรอกเบอร์โทรศัพท์มือถือ:</label>
            <input type="text" id="phone_number" name="phone_number" placeholder="เช่น 0812345678" 
                   value="<?= htmlspecialchars($phoneNumber ?? '') ?>" required>
        </div>
        <button type="submit">ตรวจสอบค่าย</button>
    </form>

    <!-- แสดงผลบนหน้าเว็บ -->
    <?php if ($webResult !== null): ?>
        <div class="result-box" style="<?= $webResult['status'] ? 'border-left-color: '.$webResult['color'] : 'border-left-color: #dc3545;' ?>">
            <?php if ($webResult['status']): ?>
                <p><strong>เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($webResult['phone']) ?></p>
                <p><strong>เครือข่าย:</strong> 
                    <span class="carrier-badge" style="background-color: <?= $webResult['color'] ?>;">
                        <?= htmlspecialchars($webResult['carrier_name']) ?>
                    </span>
                </p>
                <p style="font-size: 12px; color: #777; margin-top: 8px;"><?= htmlspecialchars($webResult['note']) ?></p>
            <?php else: ?>
                <p style="color: #dc3545; font-weight: bold;">⚠️ <?= htmlspecialchars($webResult['message']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- คู่มือการเรียกใช้ API -->
    <div class="api-info">
        <strong>💡 วิธีใช้เป็น API:</strong>
        <p>ส่ง Request แบบ GET หรือ POST โดยเพิ่ม <code>?api=1</code></p>
        <p><strong>ตัวอย่าง:</strong><br>
        <code>GET index.php?api=1&phone_number=0812345678</code></p>
    </div>
</div>

</body>
</html>
