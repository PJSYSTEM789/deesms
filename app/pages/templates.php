<?php
/**
 * หน้าจัดการแม่แบบข้อความ (SMS Templates)
 * ตำแหน่งไฟล์: pages/templates.php
 */

if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    exit('No direct script access allowed');
}

$userId = $_SESSION['user_id'] ?? 0;
$msg = '';
$msgType = '';

// 1. จัดการการเพิ่มแม่แบบข้อความใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_template') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sms_templates (user_id, title, content) VALUES (:uid, :title, :content)");
            $stmt->execute([
                ':uid'     => $userId,
                ':title'   => $title,
                ':content' => $content
            ]);
            $msg = "บันทึกแม่แบบข้อความเรียบร้อยแล้ว";
            $msgType = "success";
        } catch (PDOException $e) {
            $msg = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
            $msgType = "danger";
        }
    } else {
        $msg = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $msgType = "warning";
    }
}

// 2. จัดการการลบแม่แบบข้อความ
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $deleteId, ':uid' => $userId]);
        header("Location: index.php?page=templates");
        exit;
    } catch (PDOException $e) {
        $msg = "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
        $msgType = "danger";
    }
}

// 3. ดึงรายการแม่แบบข้อความทั้งหมดของผู้ใช้
$templates = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sms_templates WHERE user_id = :uid ORDER BY id DESC");
    $stmt->execute([':uid' => $userId]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch templates error: " . $e->getMessage());
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="fa-solid fa-file-lines text-primary me-2"></i>จัดการแม่แบบข้อความ (SMS Templates)
        </h4>
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
            <i class="fa-solid fa-plus me-1"></i>เพิ่มแม่แบบใหม่
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ตารางแสดงรายการแม่แบบข้อความ -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-list me-2 text-muted"></i>รายการแม่แบบที่บันทึกไว้</h5>
            <input type="text" id="searchTemplate" class="form-control form-control-sm w-auto" placeholder="ค้นหาแม่แบบ...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="templateTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">ลำดับ</th>
                            <th style="width: 200px;">ชื่อแม่แบบ</th>
                            <th>ข้อความที่บันทึกไว้</th>
                            <th style="width: 160px;">วันที่บันทึก</th>
                            <th class="text-center" style="width: 150px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i>
                                    ยังไม่มีข้อความแม่แบบที่บันทึกไว้
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $index => $t): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($t['title']) ?></td>
                                    <td>
                                        <div class="text-break small bg-light p-2 rounded border">
                                            <?= nl2br(htmlspecialchars($t['content'])) ?>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($t['created_at']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- ปุ่มไปหน้าส่ง SMS พร้อมแนบข้อความนี้ไป -->
                                            <a href="index.php?page=send_sms&template_id=<?= $t['id'] ?>" class="btn btn-outline-primary" title="นำไปใช้ส่ง SMS">
                                                <i class="fa-solid fa-paper-plane"></i>
                                            </a>
                                            <!-- ปุ่มคัดลอกข้อความ -->
                                            <button type="button" class="btn btn-outline-secondary btn-copy" data-content="<?= htmlspecialchars($t['content']) ?>" title="คัดลอกข้อความ">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                            <!-- ปุ่มลบ -->
                                            <a href="index.php?page=templates&delete_id=<?= $t['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('ยืนยันการลบแม่แบบนี้?');" title="ลบ">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่มแม่แบบใหม่ -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <input type="hidden" name="action" value="add_template">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-circle-plus me-2 text-primary"></i>เพิ่มแม่แบบข้อความใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อแม่แบบ <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="เช่น โปรโมชันประจำเดือน, แจ้งเตือนยอดชำระ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ข้อความแม่แบบ <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="5" placeholder="กรอกข้อความที่ต้องการบันทึก..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript สำหรับค้นหา และคัดลอกข้อความ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. ระบบค้นหาแม่แบบในตาราง
    const searchInput = document.getElementById('searchTemplate');
    const tableBody = document.querySelector('#templateTable tbody');

    if (searchInput && tableBody) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.trim().toLowerCase();
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent || rows[i].innerText;
                rows[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
            }
        });
    }

    // 2. ระบบคัดลอกข้อความไปยัง Clipboard
    const copyButtons = document.querySelectorAll('.btn-copy');
    copyButtons.forEach(button => {
        button.addEventListener('click', function () {
            const content = this.getAttribute('data-content');
            navigator.clipboard.writeText(content).then(() => {
                alert('คัดลอกข้อความเรียบร้อยแล้ว!');
            }).catch(err => {
                console.error('ไม่สามารถคัดลอกได้: ', err);
            });
        });
    });
});
</script>
