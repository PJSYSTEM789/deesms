<?php
/**
 * API Endpoint สำหรับดึงข้อมูลสถิติและเครดิต SMS ส่งกลับเป็น JSON
 * ตำแหน่งไฟล์: api/get_dashboard_data.php
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = function_exists('isAdmin') ? isAdmin() : false;

// 1. ดึงเครดิตจริงจาก API
$apiCredit = api/get_Balance.php;

try {
    // 2. ดึงสถิติจากฐานข้อมูล
    $sqlTotal = "SELECT COUNT(*) FROM sms_logs" . ($isAdmin ? "" : " WHERE user_id = :uid");
    $stmtTotal = $pdo->prepare($sqlTotal);
    if (!$isAdmin) $stmtTotal->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmtTotal->execute();
    $totalSent = (int)$stmtTotal->fetchColumn();

    $sqlSuccess = "SELECT COUNT(*) FROM sms_logs WHERE status = 'SUCCESS'" . ($isAdmin ? "" : " AND user_id = :uid");
    $stmtSuccess = $pdo->prepare($sqlSuccess);
    if (!$isAdmin) $stmtSuccess->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmtSuccess->execute();
    $totalSuccess = (int)$stmtSuccess->fetchColumn();

    $sqlFailed = "SELECT COUNT(*) FROM sms_logs WHERE status = 'FAILED'" . ($isAdmin ? "" : " AND user_id = :uid");
    $stmtFailed = $pdo->prepare($sqlFailed);
    if (!$isAdmin) $stmtFailed->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmtFailed->execute();
    $totalFailed = (int)$stmtFailed->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'credit' => $apiCredit,
            'total_sent' => $totalSent,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'updated_at' => date('H:i:s')
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
