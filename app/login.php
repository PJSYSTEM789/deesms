<?php
// login.php
require_once 'config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role']     = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - SMS Gateway API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #1e293b; font-family: 'Sarabun', sans-serif; }
        .login-card { max-width: 400px; border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
<div class="container">
    <div class="card login-card mx-auto p-4 bg-white">
        <div class="text-center mb-4">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px;">
                <i class="fa-solid fa-paper-plane fa-xl"></i>
            </div>
            <h4 class="fw-bold text-dark">SMS Gateway API</h4>
            <p class="text-muted small mb-0">ลงชื่อเข้าใช้งานระบบบริหารจัดการ SMS</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control" placeholder="admin" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                <i class="fa-solid fa-right-to-bracket me-1"></i> เข้าสู่ระบบ
            </button>
        </form>
    </div>
</div>
</body>
</html>
