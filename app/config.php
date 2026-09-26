<?php
/**
 * ไฟล์: config.php
 * วัตถุประสงค์: ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL และฟังก์ชันส่วนกลางสำหรับระบบ SMS
 */

// เริ่มต้น Session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------
// 1. กำหนดค่าและเชื่อมต่อฐานข้อมูล MySQL
// ----------------------------------------------------------------------
$host = 'db.fr-roub1.bengt.wasmernet.com';
$port = 20184;
$db   = 'db_6e7f2975';
$user = 'user_47246347';
$pass = 'pw_olNIAybwfxvV6FADzz4pE60Q8uX9fzmW';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

// ค่าเริ่มต้นสำหรับ SMS API
define('DEFAULT_API_URL', 'https://api.deesms.net');
define('DEFAULT_API_KEY', '921a0dfd1e78655369019ba60e0c2b9bc91c9a58c99321a5dadb7d49cae320a3');

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // ปิดหน้าเว็บและแสดงข้อผิดพลาด (ใน Production ควรบันทึกเป็น Log แทนการ echo ตรงๆ)
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// ----------------------------------------------------------------------
// 2. ฟังก์ชันตรวจสอบสิทธิ์และผู้ใช้งาน
// ----------------------------------------------------------------------

// ตรวจสอบการเข้าสู่ระบบ
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// ตรวจสอบสิทธิ์ Admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
