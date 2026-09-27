<?php
/**
 * หน้าตั้งค่า SMS Gateway API (ส่งออกจริง 100%)
 * ตำแหน่งไฟล์: pages/api_settings.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Security Check)
$isAdminUser = false;
if (function_exists('isAdmin')) {
    $isAdminUser = isAdmin();
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $isAdminUser = true;
} elseif (isset($_SESSION['user_id'])) {
    $isAdminUser = true; 
}

if (!$isAdminUser) {
    echo '<div class="alert alert-danger m-4"><i class="fa-solid fa-triangle-exclamation me-2"></i>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะ Admin เท่านั้น)</div>';
    return;
}

// 2. กำหนดตัวแปรสำหรับแจ้งเตือน
$msg = null;
$msgType = '';

// 3. ฟังก์ชันดึงค่าการตั้งค่าจากฐานข้อมูล
if (!function_exists('safeGetSetting')) {
    function safeGetSetting($key, $default = '') {
        global $pdo;
        if (function_exists('getSetting')) {
            return getSetting($key, $default);
        }
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? $row['setting_value'] : $default;
            } catch (PDOException $e) {
                return $default;
            }
        }
        return $default;
    }
}

// 4. จัดการเมื่อมีการบันทึกฟอร์ม (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiUrl = rtrim(trim($_POST['api_url'] ?? ''), '/');
    $apiKey = trim($_POST['api_key'] ?? '');

    if (empty($apiUrl) || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        $msg = 'กรุณากรอก API Gateway URL ให้ถูกต้อง (เช่น https://api.deesms.net)';
        $msgType = 'danger';
    } elseif (empty($apiKey)) {
        $msg = 'กรุณากรอก API Key';
        $msgType = 'danger';
    } else {
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                                       VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                
                $settingsData = [
                    'api_url' => $apiUrl,
                    'api_key' => $apiKey
                ];

                foreach ($settingsData as $key => $val) {
                    $stmt->execute([$key, $val]);
                }

                $msg = "บันทึกการตั้งค่าระบบ SMS Gateway เรียบร้อยแล้ว!";
                $msgType = 'success';
            } catch (PDOException $e) {
                error_log("Save Settings Error: " . $e->getMessage());
                $msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = "ไม่พบการเชื่อมต่อฐานข้อมูล (\$pdo)";
            $msgType = 'warning';
        }
    }
}

// 5. ดึงค่าปัจจุบันจากฐานข้อมูลมาแสดงในฟอร์ม
$currentUrl = safeGetSetting('api_url', 'https://api.deesms.net');
$currentKey = safeGetSetting('api_key', '');
?>

<div class="container-fluid py-2">
    <div class="card p-4 border-0 shadow-sm mx-auto" style="max-width: 800px; border-radius: 12px;">
        <h4 class="fw-bold text-primary mb-2">
            <i class="fa-solid fa-sliders me-2"></i>ตั้งค่า SMS Gateway API
        </h4>
        <p class="text-muted small mb-4">จัดการ API Key และ Gateway URL สำหรับการส่ง SMS ออกจริง</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> alert-dismissible fade show small" role="alert">
                <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> me-2"></i>
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
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleKeyVisibility()" id="toggleBtn" title="แสดง/ซ่อน รหัส">
                        <i class="fa-solid fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                <div class="form-text small text-muted">API Key นี้จะถูกนำไปใช้เชื่อมต่อกับ SMS Gateway เพื่อส่งข้อความออกจริงทุกรายการ</div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary fw-bold px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกการตั้งค่า
                </button>
            </div>
        </form>
    </div>
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
