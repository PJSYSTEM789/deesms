<?php
// pages/logs.php
if (!defined('BASE_PATH') && !isset($_SESSION['user_id'])) {
    exit('No direct script access allowed');
}

$stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY id DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0"><i class="fa-solid fa-list-check me-2 text-primary"></i>System Activity Logs</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 font-monospace small">
                <thead class="table-dark">
                    <tr>
                        <th>Timestamp</th>
                        <th>User ID</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">ไม่มีบันทึกข้อมูล Log</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?= $l['created_at'] ?></td>
                                <td><?= $l['user_id'] ?? 'System/API' ?></td>
                                <td><?= htmlspecialchars($l['recipient']) ?></td>
                                <td><span class="badge bg-<?= $l['status'] === 'SUCCESS' ? 'success' : 'danger' ?>"><?= $l['status'] ?></span></td>
                                <td><?= htmlspecialchars($l['status_note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
