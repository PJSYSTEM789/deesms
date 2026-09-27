<?php
// pages/users.php
if (!isAdmin()) exit;
?>
<div class="card p-4 border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold text-primary mb-0"><i class="fa-solid fa-users-gear me-2"></i>จัดการสมาชิก (User Management)</h4>
            <p class="text-muted small mb-0">เพิ่ม แก้ไข กำหนดสิทธิ์ และลบผู้ใช้งานระบบ</p>
        </div>
        <button class="btn btn-primary btn-sm fw-bold" onclick="openUserModal()">
            <i class="fa-solid fa-user-plus me-1"></i> เพิ่มสมาชิกใหม่
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>ชื่อผู้ใช้ (Username)</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>สิทธิ์ใช้งาน (Role)</th>
                    <th>วันที่สร้าง</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                <tr><td colspan="6" class="text-center py-4 text-muted">กำลังโหลดข้อมูลผู้ใช้งาน...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไข ผู้ใช้ -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="userModalTitle">เพิ่มสมาชิกใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อ-นามสกุล</label>
                        <input type="text" id="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password <span class="text-muted fw-normal">(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</span></label>
                        <input type="password" id="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">สิทธิ์ใช้งาน (Role)</label>
                        <select id="role" class="form-select">
                            <option value="user">User (ผู้ใช้ทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary btn-sm fw-bold" onclick="saveUser()">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>
</div>

<script>
let userModal;
document.addEventListener("DOMContentLoaded", function () {
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
    loadUsers();
});

async function loadUsers() {
    let tbody = document.getElementById('userTableBody');
    try {
        let res = await fetch('api/manage_users.php?action=list');
        let data = await res.json();
        
        if (data.success && data.users.length > 0) {
            tbody.innerHTML = '';
            data.users.forEach(u => {
                let roleBadge = u.role === 'admin' ? '<span class="badge bg-danger">ADMIN</span>' : '<span class="badge bg-secondary">USER</span>';
                tbody.innerHTML += `
                    <tr>
                        <td>${u.id}</td>
                        <td class="fw-bold">${u.username}</td>
                        <td>${u.fullname}</td>
                        <td>${roleBadge}</td>
                        <td>${u.created_at}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-warning me-1" onclick='editUser(${JSON.stringify(u)})'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${u.id})"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
        }
    } catch(e) {}
}

function openUserModal() {
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('fullname').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = 'user';
    document.getElementById('userModalTitle').innerText = 'เพิ่มสมาชิกใหม่';
    userModal.show();
}

function editUser(u) {
    document.getElementById('userId').value = u.id;
    document.getElementById('username').value = u.username;
    document.getElementById('fullname').value = u.fullname;
    document.getElementById('password').value = '';
    document.getElementById('role').value = u.role;
    document.getElementById('userModalTitle').innerText = 'แก้ไขข้อมูลสมาชิก';
    userModal.show();
}

async function saveUser() {
    let payload = {
        action: 'save',
        id: document.getElementById('userId').value,
        username: document.getElementById('username').value,
        fullname: document.getElementById('fullname').value,
        password: document.getElementById('password').value,
        role: document.getElementById('role').value
    };

    let res = await fetch('api/manage_users.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    let data = await res.json();
    if(data.success) {
        userModal.hide();
        loadUsers();
    } else {
        alert(data.message || 'เกิดข้อผิดพลาด');
    }
}

async function deleteUser(id) {
    if(!confirm("ยืนยันการลบผู้ใช้นี้หรือไม่?")) return;
    let res = await fetch('api/manage_users.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: id})
    });
    let data = await res.json();
    if(data.success) loadUsers();
    else alert(data.message);
}
</script>
