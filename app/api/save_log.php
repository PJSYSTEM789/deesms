<?php
/**
 * ไฟล์: api/save_log.php
 * วัตถุประสงค์: บันทึกประวัติการส่ง (ทั้งส่งจริงและจำลอง) + ระบบ Auto-Cleanup หลังบ้าน
 */

header('Content-Type: application/json; charset=utf-8');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$db_host = 'localhost';
$db_user = 'fifacom_sms';
$db_pass = '5FsYa7ExawQBhGbsCHPz';
$db_name = 'fifacom_sms';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Connection Failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!empty($input['phone'])) {
    // 1. บันทึกข้อมูล Log
    $stmt = $pdo->prepare("INSERT INTO sms_logs (recipient, message, sender_name, status, status_note, credit_used) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $input['phone'] ?? '',
        $input['message'] ?? '',
        $input['sender_name'] ?? '',
        $input['status'] ?? 'PENDING',
        $input['status_note'] ?? '',
        $input['credit_used'] ?? 0
    ]);

    // -------------------------------------------------------------
    // 2. AUTO CLEANUP หลังบ้าน (ทำงานอัตโนมัติ ผู้ใช้ไม่เห็นปุ่มใดๆ)
    // -------------------------------------------------------------
    
    // เงื่อนไข A: ลบข้อมูลที่เก่ากว่า 30 วันทิ้งอัตโนมัติ
    $pdo->exec("DELETE FROM sms_logs WHERE sent_at < NOW() - INTERVAL 30 DAY");

    // เงื่อนไข B: จำกัดจำนวนแถวรวมในตารางไม่ให้เกิน 10,000 รายการล่าสุด
    // (ถ้าเกิน 10,000 รายการ จะลบรายการที่เก่าที่สุดออกทันที)
    $maxRows = 10000;
    $pdo->exec("DELETE FROM sms_logs WHERE id NOT IN (SELECT id FROM (SELECT id FROM sms_logs ORDER BY id DESC LIMIT $maxRows) AS temp)");

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
