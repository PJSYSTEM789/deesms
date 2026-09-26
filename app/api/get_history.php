<?php
/**
 * ไฟล์: api/get_history.php
 * วัตถุประสงค์: ดึงข้อมูลประวัติการส่ง SMS รวมจากทั้ง API จริง และ MySQL Database
 */

header('Content-Type: application/json; charset=utf-8');

// ดึงไฟล์ตั้งค่ากลางเข้ามาใช้งาน
require_once __DIR__ . '/../config.php';

// รับค่าจาก Query Parameters
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$search    = trim($_GET['search'] ?? '');

$allLogs = [];

// ==========================================
// ส่วนที่ 1: ดึงข้อมูลจากฐานข้อมูล MySQL
// ==========================================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5
    ]);

    $sql = "SELECT id, recipient, message, sender_name, status, status_note, credit_used, sent_at FROM sms_logs WHERE 1=1";
    $params = [];

    if (!empty($startDate)) {
        $sql .= " AND DATE(sent_at) >= ?";
        $params[] = $startDate;
    }
    if (!empty($endDate)) {
        $sql .= " AND DATE(sent_at) <= ?";
        $params[] = $endDate;
    }
    if (!empty($search)) {
        $sql .= " AND (recipient LIKE ? OR message LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY sent_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dbLogs = $stmt->fetchAll();

    foreach ($dbLogs as $row) {
        $allLogs[] = [
            'id'          => 'DB_' . $row['id'],
            'source'      => 'DATABASE',
            'sent_at'     => $row['sent_at'],
            'recipient'   => $row['recipient'],
            'sender_name' => $row['sender_name'] ?? '-',
            'message'     => $row['message'],
            'credit_used' => (int)$row['credit_used'],
            'status'      => $row['status'],
            'status_note' => $row['status_note'] ?? ''
        ];
    }
} catch (PDOException $e) {
    error_log("Database Error in get_history.php: " . $e->getMessage());
}

// ==========================================
// ส่วนที่ 2: ดึงข้อมูลจาก SMS Gateway API จริง
// ==========================================
$queryParams = ['page' => 1, 'limit' => 100];
if (!empty($startDate)) $queryParams['start_date'] = $startDate;
if (!empty($endDate))   $queryParams['end_date']   = $endDate;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => DEESMS_BASE_URL . '?' . http_build_query($queryParams),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'api-key: ' . DEESMS_API_KEY
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $apiRes = json_decode($response, true);
    $apiItems = $apiRes['data'] ?? [];

    foreach ($apiItems as $item) {
        $phone   = $item['recipient'] ?? '';
        $msgText = $item['message'] ?? '';
        
        if (!empty($search)) {
            $matchPhone   = mb_strpos($phone, $search) !== false;
            $matchMessage = mb_strpos($msgText, $search) !== false;
            if (!$matchPhone && !$matchMessage) {
                continue;
            }
        }

        $allLogs[] = [
            'id'          => 'API_' . ($item['id'] ?? uniqid()),
            'source'      => 'API',
            'sent_at'     => $item['created_at'] ?? $item['send_at'] ?? date('Y-m-d H:i:s'),
            'recipient'   => $phone,
            'sender_name' => $item['sender']['name'] ?? $item['send_from'] ?? '-',
            'message'     => $msgText,
            'credit_used' => (int)($item['credit_used'] ?? 1),
            'status'      => $item['gateway_status'] ?? $item['status'] ?? 'SUCCESS',
            'status_note' => 'ส่งผ่าน Gateway API'
        ];
    }
} else if ($curlError) {
    error_log("cURL Error in get_history.php: " . $curlError);
}

// ==========================================
// ส่วนที่ 3: เรียงลำดับข้อมูลทั้งหมดตามเวลาล่าสุด (sent_at DESC)
// ==========================================
usort($allLogs, function ($a, $b) {
    return strtotime($b['sent_at']) - strtotime($a['sent_at']);
});

// ส่งผลลัพธ์กลับเป็น JSON
echo json_encode([
    'success' => true,
    'total'   => count($allLogs),
    'data'    => $allLogs
], JSON_UNESCAPED_UNICODE);
