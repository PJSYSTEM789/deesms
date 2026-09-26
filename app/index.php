<?php
// index.php
require_once 'config.php';
checkLogin();

$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Gateway Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Sarabun) -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- SheetJS -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; overflow-x: hidden; }
        
        /* Sidebar Style */
        #sidebar {
            width: 260px;
            min-height: 100vh;
            background: #1e293b;
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        #sidebar.collapsed { left: -260px; }
        #sidebar .nav-link {
            color: #94a3b8;
            padding: 11px 18px;
            font-size: 0.92rem;
            border-radius: 8px;
            margin: 3px 12px;
            display: flex;
            align-items: center;
        }
        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            color: #ffffff;
            background: #2563eb;
        }
        #sidebar .nav-link i { width: 25px; }

        /* Main Content Style */
        #main-content {
            margin-left: 260px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        #main-content.expanded { margin-left: 0; }
        
        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
        }
        .phone-badge { background-color: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .menu-header { font-size: 0.72rem; text-transform: uppercase; color: #64748b; padding: 10px 20px 4px 20px; font-weight: 700; }
    </style>
</head>
<body>

<!-- Sidebar Menu -->
<div id="sidebar">
    <div class="p-3 text-white border-bottom border-secondary d-flex align-items-center justify-content-between">
        <span class="fw-bold fs-5 text-truncate"><i class="fa-solid fa-paper-plane me-2 text-primary"></i>SMS Gateway</span>
        <button class="btn btn-sm btn-dark d-md-none" onclick="toggleSidebar()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <div class="py-2">
        <div class="menu-header">เมนูทั่วไป</div>
        <a href="index.php?page=dashboard" class="nav-link <?= $page==='dashboard'?'active':'' ?>">
            <i class="fa-solid fa-chart-line"></i> แดชบอร์ด
        </a>
        <a href="index.php?page=send_sms" class="nav-link <?= $page==='send_sms'?'active':'' ?>">
            <i class="fa-solid fa-paper-plane"></i> เมนูการส่ง SMS
        </a>
        <a href="index.php?page=history" class="nav-link <?= $page==='history'?'active':'' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> รายการประวัติ
        </a>
        <a href="index.php?page=summary" class="nav-link <?= $page==='summary'?'active':'' ?>">
            <i class="fa-solid fa-chart-pie"></i> สรุปการส่งทั้งหมด
        </a>
        <a href="index.php?page=contacts" class="nav-link <?= $page==='contacts'?'active':'' ?>">
            <i class="fa-solid fa-address-book"></i> รายชื่อเบอร์
        </a>
        <a href="index.php?page=templates" class="nav-link <?= $page==='templates'?'active':'' ?>">
            <i class="fa-solid fa-message"></i> ข้อความใช้บ่อย
        </a>
        <a href="index.php?page=packages" class="nav-link <?= $page==='packages'?'active':'' ?>">
            <i class="fa-solid fa-box-archive"></i> แพ็คเกจและการชำระเงิน
        </a>
        <a href="index.php?page=logs" class="nav-link <?= $page==='logs'?'active':'' ?>">
            <i class="fa-solid fa-list-check"></i> Log การทำงาน
        </a>

        <?php if (isAdmin()): ?>
            <div class="menu-header mt-2">ส่วนผู้ดูแลระบบ (Admin)</div>
            <a href="index.php?page=users" class="nav-link <?= $page==='users'?'active':'' ?>">
                <i class="fa-solid fa-users-gear"></i> จัดการสมาชิก
            </a>
            <a href="index.php?page=api_settings" class="nav-link <?= $page==='api_settings'?'active':'' ?>">
                <i class="fa-solid fa-sliders"></i> ตั้งค่า API
            </a>
            <a href="index.php?page=api_status" class="nav-link <?= $page==='api_status'?'active':'' ?>">
                <i class="fa-solid fa-heart-pulse"></i> สถานะของ API
            </a>
        <?php endif; ?>

        <hr class="text-secondary mx-3 my-2">
        <a href="logout.php" class="nav-link text-danger">
            <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
        </a>
    </div>
</div>

<!-- Main Content Area -->
<div id="main-content">
    <div class="top-navbar d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-secondary btn-sm" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary px-2 py-1"><?= strtoupper($_SESSION['role'] ?? 'USER') ?></span>
            <span class="small text-dark fw-bold"><i class="fa-solid fa-user me-1 text-muted"></i><?= htmlspecialchars($_SESSION['fullname']) ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger" title="ออกจากระบบ"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </div>

    <div class="p-4">
        <?php
        $allowed_pages = ['dashboard', 'send_sms', 'history', 'summary', 'contacts', 'templates', 'packages', 'logs', 'users', 'api_settings', 'api_status'];
        $target_page = in_array($page, $allowed_pages) ? $page : 'dashboard';
        
        // กรองหน้าสำหรับ Admin
        if (in_array($target_page, ['users', 'api_settings', 'api_status']) && !isAdmin()) {
            echo '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
        } else {
            include "pages/{$target_page}.php";
        }
        ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('expanded');
}
</script>
</body>
</html>
