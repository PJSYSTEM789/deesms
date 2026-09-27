<?php
/**
 * หน้าตั้งค่า SMS Gateway API
 * ตำแหน่งไฟล์: pages/api_settings.php
 */

// 1. ตรวจสอบการเข้าถึงไฟล์ตรงและการเช็กสิทธิ์ Admin
if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    // หากเข้าตรงๆ โดยไม่มี BASE_PATH หรือ SESSION ให้แจ้งเตือนแทนการ exit เงียบๆ
    die('<div style="padding:20px; color:red;">ไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้ (Direct Access Denied)</div>');
}

// ตรวจสอบสิทธิ์ Admin
$isAdminUser = false;
if (function_exists('isAdmin')) {
    $isAdminUser = isAdmin();
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {$isAdminUser = true;
}

if (!$isAdminUser) {
    echo '<div class="alert alert-danger m-4">คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะ Admin เท่านั้น)</div>';
    return; // ใช้ return แทน exit เพื่อไม่ให้กระทบ Layout หลักของหน้าเว็บ
}

// 2. กำหนดตัวแปรเริ่มต้น
$msg = null;
$msgType = '';

// ฟังก์ชันเซฟช่วยเช็ก getSetting กัน Fatal Error
if (!function_exists('safeGetSetting')) {
    function safeGetSetting($key,$default = '') {
        if (function_exists('getSetting')) {
            return getSetting($key,$default);
        }
        return $default;
    }
}

// 3. จัดการเมื่อมีการบันทึกฟอร์ม (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiUrl    = rtrim(trim($_POST['api_url'] ?? ''), '/');
    $apiKey    = trim($_POST['api_key'] ?? '');
    $threshold = (int)($_POST['throttle_threshold'] ?? 200);
    $ratio     = (int)($_POST['throttle_ratio'] ?? 30);

    if (empty($apiUrl) || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {$msg = 'กรุณากรอก API Gateway URL ให้ถูกต้อง';
        $msgType = 'danger';
    } elseif (empty($apiKey)) {$msg = 'กรุณากรอก API Key';
        $msgType = 'danger';
    } elseif ($ratio < 1 || $ratio > 100) {$msg = 'สัดส่วนการส่งออกจริงต้องอยู่ระหว่าง 1 ถึง 100%';
        $msgType = 'danger';
    } else {
        if (isset($pdo)) {
            try {
                $stmt =$pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                
                $settingsData = [
                    'api_url'            => $apiUrl,
                    'api_key'            => $apiKey,
                    'throttle_threshold' => $threshold,
                    'throttle_ratio'     => $ratio
                ];

                foreach ($settingsData as$key => $val) {$stmt->execute([$key,$val]);
                }

                $msg = "บันทึกการตั้งค่าเรียบร้อยแล้ว!";
                $msgType = 'success';
            } catch (PDOException $e) {
                error_log("Save Settings Error: " . $e->getMessage());$msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();$msgType = 'danger';
            }
        } else {
            $msg = "ไม่พบการเชื่อมต่อฐานข้อมูล (\$pdo)";
            $msgType = 'warning';
        }
    }
}

// 4. ดึงค่าปัจจุบันมาแสดงผลในฟอร์ม
$currentUrl    = safeGetSetting('api_url', 'https://api.deesms.net');$currentKey    = safeGetSetting('api_key', '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3');
$currentThresh = safeGetSetting('throttle_threshold', '200');$currentRatio  = safeGetSetting('throttle_ratio', '30');
?>

<div class="card p-4 border-0 shadow-sm" style="max-width: 800px;">
    <h4 class="fw-bold text-primary mb-3">
        <i class="fa-solid fa-sliders me-2"></i>ตั้งค่า SMS Gateway API
    </h4>
    <p class="text-muted small">จัดการ API Key และเงื่อนไขการส่งข้อมูลจำลองผ่านเกณฑ์เงื่อนไข</p>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show small" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label fw-bold small">API Gateway URL Base</label>
            <input type="url" name="api_url" class="form-control" value="<?= htmlspecialchars($currentUrl) ?>" placeholder="https://api.deesms.net" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold small">API Key (Token)</label>
            <div class="input-group">
                <input type="password" name="api_key" id="apiKeyInput" class="form-control" value="<?= htmlspecialchars($currentKey) ?>" placeholder="กรอก API Key" required>
                <button class="btn btn-outline-secondary" type="button" onclick="toggleKeyVisibility()" id="toggleBtn">
                    <i class="fa-solid fa-eye" id="toggleIcon"></i>
                </button>
            </div>
            <div class="form-text small">API Key จะถูกนำไปใช้ในส่วนหัว `api-key` สำหรับเชื่อมต่อกับ SMS Gateway</div>
        </div>

        <hr class="my-4">
        <h6 class="fw-bold text-dark mb-3">
            <i class="fa-solid fa-filter me-1"></i>เงื่อนไขการกระจายคิวส่ง (Volume Routing Rule)
        </h6>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold small">จำนวนเบอร์ขั้นต่ำที่เริ่มจำลอง (Threshold)</label>
                <input type="number" name="throttle_threshold" class="form-control" value="<?= htmlspecialchars($currentThresh) ?>" min="0" required>
                <div class="form-text small">เช่น 200 หมายถึงถ้าน้อยกว่า 200 เบอร์ จะส่งออก 100%</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small">สัดส่วนการส่งออก API จริง (%)</label>
                <div class="input-group">
                    <input type="number" name="throttle_ratio" class="form-control" value="<?= htmlspecialchars($currentRatio) ?>" min="1" max="100" required>
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text small">เช่น 30 หมายถึงจะส่งออกจริง 30% อีก 70% จะบันทึกเป็น Log ไม่สำเร็จ</div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกการตั้งค่า
            </button>
        </div>
    </form>
</div>

<script>
function toggleKeyVisibility() {
    const input = document.getElementById('apiKeyInput');
    const icon = document.getElementById('toggleIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
