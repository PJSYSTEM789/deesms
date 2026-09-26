<?php
// api/manage_users.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $stmt = $pdo->query("SELECT id, username, fullname, role, created_at FROM users ORDER BY id DESC");
    echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $input['action'] ?? '';

    if ($act === 'save') {
        $id       = $input['id'] ?? '';
        $username = trim($input['username'] ?? '');
        $fullname = trim($input['fullname'] ?? '');
        $password = trim($input['password'] ?? '');
        $role     = $input['role'] ?? 'user';

        if (empty($username) || empty($fullname)) {
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
            exit;
        }

        if (!empty($id)) {
            // แก้ไข
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, fullname=?, password=?, role=? WHERE id=?");
                $stmt->execute([$username, $fullname, $hash, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, fullname=?, role=? WHERE id=?");
                $stmt->execute([$username, $fullname, $role, $id]);
            }
        } else {
            // เพิ่มใหม่
            if (empty($password)) {
                echo json_encode(['success' => false, 'message' => 'กรุณาระบุรหัสผ่าน']);
                exit;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, fullname, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $fullname, $hash, $role]);
        }
        echo json_encode(['success' => true]);
        exit;

    } elseif ($act === 'delete') {
        $id = $input['id'] ?? 0;
        if ($id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบตัวเองได้']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid Request']);
