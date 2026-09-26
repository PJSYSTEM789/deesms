<?php
/**
 * ไฟล์: reset_pass.php
 * วัตถุประสงค์: รีเซ็ตรหัสผ่านบัญชี 'admin' ให้เป็น '123456'
 */

// ดึงไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config.php';

$newPassword = '123456';
$username = 'admin';

try {
    // 1. เข้ารหัส password ด้วย Bcrypt
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // 2. อัปเดตรหัสผ่านลงในตาราง users สำหรับ username 'admin'
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = ?");
    $stmt->execute([$hashedPassword, $username]);

    if ($stmt->rowCount() > 0) {
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 8px; font-family: sans-serif; max-width: 500px; margin: 50px auto;'>";
        echo "<h2>✅ รีเซ็ตรหัสผ่านสำเร็จ!</h2>";
        echo "<p>ผู้ใช้งาน: <strong>admin</strong></p>";
        echo "<p>รหัสผ่านใหม่: <strong>123456</strong></p>";
        echo "<hr>";
        echo "<a href='login.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>ไปยังหน้า Login</a>";
        echo "</div>";
    } else {
        // หากไม่พบบัญชี admin ให้ทำการสร้างบัญชีใหม่ให้อัตโนมัติ
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, 'admin')");
        $stmtInsert->execute([$username, $hashedPassword, 'Administrator']);

        echo "<div style='padding: 20px; background: #cce5ff; color: #004085; border-radius: 8px; font-family: sans-serif; max-width: 500px; margin: 50px auto;'>";
        echo "<h2>✅ สร้างบัญชี admin ใหม่สำเร็จ!</h2>";
        echo "<p>ผู้ใช้งาน: <strong>admin</strong></p>";
        echo "<p>รหัสผ่าน: <strong>123456</strong></p>";
        echo "<hr>";
        echo "<a href='login.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>ไปยังหน้า Login</a>";
        echo "</div>";
    }

} catch (PDOException $e) {
    echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px; font-family: sans-serif; max-width: 500px; margin: 50px auto;'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
