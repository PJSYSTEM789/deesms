<?php
// ================= ================= =================
// 1. ตั้งค่า ป้องกัน Error 500 บน Wasmer
// ================= ================= =================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');

// ฟังก์ชันดักจับ Raw Input รองรับ Wasmer / Edge Server
function getRequestData() {
    $data =$_POST;
    if (empty($data)) {$raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data =$json;
        }
    }
    return $data;
}

$reqData = getRequestData();

// ================= ================= =================
// 2. ฟังก์ชันตรวจสอบค่ายเบอร์โทรศัพท์ (Prefix Check - No SQL)
// ================= ================= =================
function getCarrier($phoneInput) {
    $phone = preg_replace('/[^0-9]/', '', (string)$phoneInput);
    
    if (strpos($phone, '66') === 0) {
        $phone = '0' . substr($phone, 2);
    }

    if (strlen($phone) !== 10 \vert{}\vert{} substr($phone, 0, 1) !== '0') {
        return ['status' => false, 'phone' => $phoneInput, 'carrier' => 'INVALID', 'carrier_name' => 'เบอร์ไม่ถูกต้อง'];
    }

    $prefix3 = substr($phone, 0, 3);$aisPrefixes = ['080', '081', '087', '089', '092', '093', '097', '098', '061', '062', '063', '065'];
    if (in_array($prefix3,$aisPrefixes)) {
        return ['status' => true, 'phone' => $phone, 'carrier' => 'AIS', 'carrier_name' => 'AIS'];
    }

    $truePrefixes = ['083', '084', '086', '091', '095', '096', '064', '099'];
    $dtacPrefixes = ['082', '085', '088', '090', '094', '066'];$otherName = 'ค่ายอื่นๆ';
    if (in_array($prefix3, $truePrefixes))$otherName = 'TRUE';
    elseif (in_array($prefix3, $dtacPrefixes))$otherName = 'DTAC';

    return ['status' => true, 'phone' => $phone, 'carrier' => 'OTHER', 'carrier_name' =>$otherName];
}

// ================= ================= =================
// 3. ระบบดาวน์โหลด CSV
// ================= ================= =================
if (isset($reqData['action']) &&$reqData['action'] === 'download_csv') {
    $type =$reqData['download_type'] ?? '';
    $rawPhones = json_decode($reqData['phones_data'] ?? '[]', true);

    if ($type === 'ais') {$filename = "ais_numbers_" . date('Ymd_His') . ".csv";
        $filtered = array_filter($rawPhones, fn($item) => ($item['carrier'] ?? '') === 'AIS');
    } else {
        $filename = "other_carriers_numbers_" . date('Ymd_His') . ".csv";
        $filtered = array_filter($rawPhones, fn($item) => (($item['carrier'] ?? '') === 'OTHER' \vert{}\vert{} ($item['carrier'] ?? '') === 'INVALID'));
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['เบอร์โทรศัพท์', 'เครือข่าย']);

    foreach ($filtered as$row) {
        fputcsv($output, [$row['phone'] ?? '',$row['carrier_name'] ?? '']);
    }

    fclose($output);
    exit;
}

// ================= ================= =================
// 4. API ประมวลผลผ่าน AJAX (พร้อมดัก Error)
// ================= ================= =================
if (isset($reqData['action']) &&$reqData['action'] === 'process_numbers') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $rawList = json_decode($reqData['numbers'] ?? '[]', true);
        if (!is_array($rawList)) {
            throw new Exception("รูปแบบข้อมูลที่ส่งมาไม่ถูกต้อง");
        }

        $aisList = [];
        $otherList = [];$summary = ['total' => 0, 'ais' => 0, 'others' => 0, 'invalid' => 0];

        foreach ($rawList as$item) {
            if (empty(trim((string)$item))) continue;
            $summary['total']++;
            
            $res = getCarrier($item);
            if (!$res['status']) {$summary['invalid']++;
                $otherList[] =$res;
            } elseif ($res['carrier'] === 'AIS') {$summary['ais']++;
                $aisList[] =$res;
            } else {
                $summary['others']++;
                $otherList[] =$res;
            }
        }

        echo json_encode([
            'status' => true,
            'summary' => $summary,
            'ais' => $aisList,
            'others' => $otherList,
            'all_processed' => array_merge($aisList,$otherList)
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        // ส่งข้อความ Error กลับมาให้หน้าเว็บ alert แสดงผลแทนที่จะปล่อยให้เป็น HTTP 500
        echo json_encode([
            'status' => false,
            'error_msg' => 'PHP Error: ' . $e->getMessage() . ' ในบรรทัดที่ ' . $e->getLine()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบคัดกรองเบอร์โทรศัพท์ (Wasmer Ready)</title>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 20px; }
        h1, h2 { margin-top: 0; color: #2c3e50; }
        
        .upload-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .upload-box { border: 2px dashed #007bff; padding: 20px; border-radius: 8px; text-align: center; background: #f8f9fa; }
        input[type="text"], input[type="file"] { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }

        .btn-main { background-color: #007bff; color: white; border: none; padding: 14px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; margin-top: 10px; }
        .btn-main:hover { background-color: #0056b3; }
        
        .summary-bar { display: flex; justify-content: space-around; background: #2c3e50; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .summary-item h3 { margin: 0; font-size: 26px; color: #f1c40f; }
        .summary-item p { margin: 5px 0 0 0; font-size: 14px; opacity: 0.9; }

        .split-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .column { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .column-ais { border-top: 5px solid #28a745; }
        .column-others { border-top: 5px solid #dc3545; }
        
        .btn-download { padding: 10px; margin-bottom: 15px; font-size: 14px; border: none; color: white; border-radius: 6px; font-weight: bold; width: 100%; cursor: pointer; }
        .btn-ais { background-color: #28a745; }
        .btn-ais:hover { background-color: #218838; }
        .btn-others { background-color: #dc3545; }
        .btn-others:hover { background-color: #c82333; }

        .table-wrapper { max-height: 400px; overflow-y: auto; border: 1px solid #eee; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; position: sticky; top: 0; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; color: white; font-weight: bold; }
        .bg-ais { background-color: #28a745; }
        .bg-other { background-color: #007bff; }
        .bg-invalid { background-color: #6c757d; }

        .loading { display: none; text-align: center; padding: 15px; font-weight: bold; color: #007bff; }
        @media (max-width: 768px) { .upload-section, .split-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>📱 ระบบคัดกรองเบอร์โทรศัพท์ (AIS / ค่ายอื่น)</h1>
        
        <div class="upload-section">
            <div class="upload-box">
                <h3>📁 อัปโหลดไฟล์ (CSV, XLSX)</h3>
                <input type="file" id="fileInput" accept=".csv, .xlsx, .xls">
            </div>
            
            <div class="upload-box">
                <h3>🌐 Google Sheets Link</h3>
                <p style="font-size: 12px; color: #666; margin: 0;">(ตั้งค่าแชร์เป็น "ทุกคนที่มีลิงก์อ่านได้")</p>
                <input type="text" id="gsheetUrl" placeholder="วางลิงก์ Google Sheets ที่นี่...">
            </div>
        </div>

        <button class="btn-main" onclick="processData()">🚀 เริ่มตรวจสอบและประมวลผล</button>
        <div id="loading" class="loading">⏳ กำลังอ่านข้อมูลและประมวลผล...</div>
    </div>

    <div id="resultArea" style="display: none;">
        <div class="summary-bar">
            <div class="summary-item">
                <h3 id="sumTotal">0</h3>
                <p>จำนวนเบอร์ทั้งหมด</p>
            </div>
            <div class="summary-item">
                <h3 id="sumAis" style="color: #2ecc71;">0</h3>
                <p>เบอร์ AIS</p>
            </div>
            <div class="summary-item">
                <h3 id="sumOthers" style="color: #e74c3c;">0</h3>
                <p>เบอร์ค่ายอื่นๆ / ไม่ถูกต้อง</p>
            </div>
        </div>

        <div class="split-container">
            <div class="column column-ais">
                <h2>🟢 เครือข่าย AIS (<span id="countAis">0</span>)</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="download_csv">
                    <input type="hidden" name="download_type" value="ais">
                    <input type="hidden" name="phones_data" id="aisDataInput">
                    <button type="submit" class="btn-download btn-ais">📥 ดาวน์โหลดไฟล์เบอร์ AIS (.csv)</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>#</th><th>เบอร์โทรศัพท์</th><th>เครือข่าย</th></tr>
                        </thead>
                        <tbody id="aisTableBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="column column-others">
                <h2>🔴 เครือข่ายอื่นๆ / ไม่ถูกต้อง (<span id="countOthers">0</span>)</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="download_csv">
                    <input type="hidden" name="download_type" value="others">
                    <input type="hidden" name="phones_data" id="othersDataInput">
                    <button type="submit" class="btn-download btn-others">📥 ดาวน์โหลดไฟล์เบอร์ค่ายอื่นๆ (.csv)</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>#</th><th>เบอร์โทรศัพท์</th><th>เครือข่าย</th></tr>
                        </thead>
                        <tbody id="othersTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function formatGoogleSheetUrl(url) {
    const match = url.match(/\/d\/([a-zA-Z0-9-_]+)/);
    if (match && match[1]) {
        return `https://docs.google.com/spreadsheets/d/${match[1]}/export?format=csv`;
    }
    return url;
}

function extractPhoneNumbers(data) {
    let numbers = [];
    data.forEach(row => {
        if (Array.isArray(row)) {
            row.forEach(cell => {
                if (cell !== null && cell !== undefined) {
                    let str = cell.toString().trim();
                    if (str.length >= 9) numbers.push(str);
                }
            });
        } else if (typeof row === 'object') {
            Object.values(row).forEach(cell => {
                if (cell !== null && cell !== undefined) {
                    let str = cell.toString().trim();
                    if (str.length >= 9) numbers.push(str);
                }
            });
        }
    });
    return numbers;
}

async function processData() {
    const fileInput = document.getElementById('fileInput');
    const gsheetUrl = document.getElementById('gsheetUrl').value.trim();
    const loading = document.getElementById('loading');
    
    let extractedNumbers = [];
    loading.style.display = 'block';

    try {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const data = await file.arrayBuffer();
            const workbook = XLSX.read(data);
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            extractedNumbers = extractPhoneNumbers(jsonData);

        } else if (gsheetUrl !== '') {
            const csvUrl = formatGoogleSheetUrl(gsheetUrl);
            const response = await fetch(csvUrl);
            if (!response.ok) throw new Error('ไม่สามารถดึงข้อมูลจาก Google Sheets ได้ โปรดตรวจสอบการตั้งค่าแชร์ไฟล์');
            const csvText = await response.text();
            const workbook = XLSX.read(csvText, { type: 'string' });
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            extractedNumbers = extractPhoneNumbers(jsonData);

        } else {
            alert('กรุณาเลือกไฟล์ CSV, XLSX หรือวางลิงก์ Google Sheets ก่อนครับ');
            loading.style.display = 'none';
            return;
        }

        if (extractedNumbers.length === 0) {
            alert('ไม่พบข้อมูลเบอร์โทรศัพท์ในไฟล์');
            loading.style.display = 'none';
            return;
        }

        // ส่งแบบ JSON Body เพื่อเลี่ยงปัญหา Wasmer ตัดค่า $_POST
        const res = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'process_numbers',
                numbers: JSON.stringify(extractedNumbers)
            })
        });

        const result = await res.json();
        loading.style.display = 'none';

        if (result.status) {
            renderResults(result);
        } else {
            alert(result.error_msg || 'เกิดข้อผิดพลาดในการประมวลผลข้อมูล');
        }

    } catch (err) {
        loading.style.display = 'none';
        alert('เกิดข้อผิดพลาด: ' + err.message);
    }
}

function renderResults(data) {
    document.getElementById('resultArea').style.display = 'block';

    document.getElementById('sumTotal').innerText = data.summary.total.toLocaleString();
    document.getElementById('sumAis').innerText = data.summary.ais.toLocaleString();
    document.getElementById('sumOthers').innerText = (data.summary.others + data.summary.invalid).toLocaleString();

    document.getElementById('countAis').innerText = data.summary.ais.toLocaleString();
    document.getElementById('countOthers').innerText = (data.summary.others + data.summary.invalid).toLocaleString();

    document.getElementById('aisDataInput').value = JSON.stringify(data.all_processed);
    document.getElementById('othersDataInput').value = JSON.stringify(data.all_processed);

    const aisTbody = document.getElementById('aisTableBody');
    aisTbody.innerHTML = '';
    data.ais.forEach((item, index) => {
        aisTbody.innerHTML += `
            <tr>
                <td>${index + 1}</td>
                <td>${item.phone}</td>
                <td><span class="badge bg-ais">${item.carrier_name}</span></td>
            </tr>`;
    });

    const othersTbody = document.getElementById('othersTableBody');
    othersTbody.innerHTML = '';
    data.others.forEach((item, index) => {
        const badgeClass = item.carrier === 'INVALID' ? 'bg-invalid' : 'bg-other';
        othersTbody.innerHTML += `
            <tr>
                <td>${index + 1}</td>
                <td>${item.phone}</td>
                <td><span class="badge ${badgeClass}">${item.carrier_name}</span></td>
            </tr>`;
    });
}
</script>

</body>
</html>
