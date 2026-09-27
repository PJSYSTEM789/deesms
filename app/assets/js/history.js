/**
 * ไฟล์: assets/js/history.js
 * วัตถุประสงค์: ดึงข้อมูลประวัติการส่ง SMS และจัดการตารางแสดงผล
 */

let currentPage = 1;

document.addEventListener("DOMContentLoaded", function () {
    // ตั้งค่าวันที่เริ่มต้น ให้เป็นวันที่ปัจจุบัน
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;

    loadHistory(1);
});

// 1. ดึงข้อมูลประวัติจาก api/get_history.php
async function loadHistory(page = 1) {
    currentPage = page;
    const tableBody = document.getElementById('historyTableBody');
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const limit = document.getElementById('limitSelect').value;

    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4 text-muted">
                <i class="fa-solid fa-spinner fa-spin me-2"></i>กำลังโหลดข้อมูล...
            </td>
        </tr>
    `;

    try {
        let url = `../api/get_history.php?page=${page}&limit=${limit}`;
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;

        let response = await fetch(url);
        let result = await response.json();

        if (response.ok && result.success) {
            renderTable(result.data, page, limit);
            renderPagination(result.page || page, result.total_page || 1, result.total_item || 0, limit);
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> ${result.message || 'ไม่สามารถดึงข้อมูลได้'}
                    </td>
                </tr>
            `;
            updatePaginationInfo(0, 0, 0);
        }
    } catch (error) {
        console.error("Load history error:", error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-danger">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> เกิดข้อผิดพลาดในการเชื่อมต่อระบบ
                </td>
            </tr>
        `;
    }
}

// 2. แสดงผลข้อมูลลงตาราง HTML
function renderTable(data, page, limit) {
    const tableBody = document.getElementById('historyTableBody');
    tableBody.innerHTML = '';

    let items = Array.isArray(data) ? data : (data.items || []);

    if (items.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-inbox me-1"></i> ไม่พบประวัติการส่ง SMS
                </td>
            </tr>
        `;
        return;
    }

    let startIndex = (page - 1) * limit;

    items.forEach((item, index) => {
        let senderName = item.send_from || (item.sender ? (item.sender.name || item.sender) : '-');
        let statusBadge = getStatusBadge(item.dr_status || item.gateway_status || item.status);
        let timeFormatted = item.send_at || item.created_at || '-';

        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${startIndex + index + 1}</td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(senderName)}</span></td>
            <td class="fw-bold">${escapeHtml(item.recipient || '-')}</td>
            <td style="max-width: 300px;" class="text-truncate" title="${escapeHtml(item.message || '')}">
                ${escapeHtml(item.message || '-')}
            </td>
            <td class="text-center"><span class="badge bg-info text-dark">${item.credit_used ?? 1}</span></td>
            <td class="text-center">${statusBadge}</td>
            <td class="text-center small text-muted">${escapeHtml(timeFormatted)}</td>
        `;
        tableBody.appendChild(tr);
    });
}

// 3. จัดการ Badge แสดงสถานะ
function getStatusBadge(status) {
    if (!status) return '<span class="badge bg-secondary">Unknown</span>';
    
    let st = String(status).toUpperCase();
    if (st === 'SUCCESS' || st === 'DELIVERED' || st === 'COMPLETED' || st === '200') {
        return '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>สำเร็จ</span>';
    } else if (st === 'PENDING' || st === 'PROCESSING') {
        return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>รอดำเนินการ</span>';
    } else {
        return `<span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>${escapeHtml(st)}</span>`;
    }
}

// 4. สร้าง Pagination Control
function renderPagination(currentPage, totalPages, totalItems, limit) {
    const nav = document.getElementById('paginationNav');
    nav.innerHTML = '';

    updatePaginationInfo(currentPage, totalItems, limit);

    if (totalPages <= 1) return;

    // ปุ่ม Previous
    let prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); loadHistory(${currentPage - 1});">ก่อนหน้า</a>`;
    nav.appendChild(prevLi);

    // ปุ่มตัวเลขหน้า
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            let li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); loadHistory(${i});">${i}</a>`;
            nav.appendChild(li);
        }
    }

    // ปุ่ม Next
    let nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); loadHistory(${currentPage + 1});">ถัดไป</a>`;
    nav.appendChild(nextLi);
}

function updatePaginationInfo(currentPage, totalItems, limit) {
    const info = document.getElementById('paginationInfo');
    if (totalItems === 0) {
        info.innerText = 'แสดง 0 ถึง 0 จาก 0 รายการ';
        return;
    }
    let start = (currentPage - 1) * limit + 1;
    let end = Math.min(currentPage * limit, totalItems);
    info.innerText = `แสดง ${start} ถึง ${end} จาก ${totalItems} รายการ`;
}

// 5. ล้างการค้นหา (Reset Filter)
function resetFilter() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;
    document.getElementById('limitSelect').value = '25';
    loadHistory(1);
}

// Utility ป้องกัน XSS
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
