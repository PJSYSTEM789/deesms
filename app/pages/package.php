<?php
/**
 * หน้าแสดงแพ็กเกจราคา SMS พร้อมระบบ Modal แจ้งชำระเงิน
 * ตำแหน่งไฟล์: pages/package.php
 */

// 1. ข้อมูลแพ็กเกจราคา SMS ทั้งหมด
$packages = [
    ['price' => 3000,    'sms' => 7200],
    ['price' => 5000,    'sms' => 14000],
    ['price' => 7000,    'sms' => 20000],
    ['price' => 10000,   'sms' => 30000],
    ['price' => 20000,   'sms' => 63500],
    ['price' => 30000,   'sms' => 100000],
    ['price' => 50000,   'sms' => 200000],
    ['price' => 100000,  'sms' => 450000],
    ['price' => 200000,  'sms' => 1000000],
    ['price' => 350000,  'sms' => 2000000],
    ['price' => 500000,  'sms' => 3125000],
    ['price' => 700000,  'sms' => 4400000],
    ['price' => 1000000, 'sms' => 7600000],
];
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="text-center mb-5">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fs-6 mb-2">
            <i class="fa-solid fa-clock-rotate-left me-1"></i> ทุกแพ็กเกจไม่มีวันหมดอายุ
        </span>
        <h2 class="fw-bold text-dark">แพ็กเกจราคา SMS สุดคุ้ม</h2>
        <p class="text-muted">เลือกแพ็กเกจที่เหมาะกับธุรกิจของคุณ ส่งตรงถึงลูกค้าด้วยราคาที่คุ้มค่าที่สุด</p>
    </div>

    <!-- Package Grid -->
    <div class="row g-4 justify-content-center">
        <?php foreach ($packages as $pkg): 
            $pricePerSms = $pkg['price'] / $pkg['sms'];
            $hasFreeWebsite = ($pkg['price'] >= 100000);
            $hasFreeSender = ($pkg['price'] >= 200000);
            $isEnterprise = ($pkg['price'] >= 100000);
        ?>
            <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden <?= $isEnterprise ? 'border-top border-warning border-4' : '' ?>">
                    
                    <?php if ($isEnterprise): ?>
                        <div class="position-absolute top-0 end-0 bg-warning text-dark fw-bold px-3 py-1 small rounded-start-pill shadow-sm" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-crown me-1"></i> VIP PACKAGE
                        </div>
                    <?php endif; ?>

                    <div class="card-body p-4 d-flex flex-column">
                        <!-- ราคา -->
                        <div class="text-center mb-3">
                            <h3 class="fw-bold text-primary mb-0">฿<?= number_format($pkg['price']) ?></h3>
                            <span class="text-muted small">ชำระครั้งเดียว</span>
                        </div>

                        <hr class="text-muted opacity-25">

                        <!-- จำนวน SMS & ราคาต่อ SMS -->
                        <div class="text-center my-2">
                            <div class="display-6 fw-bold text-dark mb-1"><?= number_format($pkg['sms']) ?></div>
                            <div class="text-secondary fw-semibold">ข้อความ (SMS)</div>
                            <div class="mt-2">
                                <span class="badge bg-success bg-opacity-10 text-success fs-6 px-3 py-2 rounded-3">
                                    เฉลี่ย ฿<?= number_format($pricePerSms, 4) ?> / SMS
                                </span>
                            </div>
                        </div>

                        <!-- รายละเอียดสิทธิประโยชน์ -->
                        <ul class="list-unstyled mt-4 mb-4 small flex-grow-1">
                            <li class="mb-2 d-flex align-items-center">
                                <i class="fa-solid fa-circle-check text-success me-2"></i>
                                <span><strong>ไม่มีวันหมดอายุ</strong> (ใช้ได้ตลอดชีพ)</span>
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="fa-solid fa-circle-check text-success me-2"></i>
                                <span>รายงานผลการส่งแบบ Real-time</span>
                            </li>
                            <?php if ($hasFreeWebsite): ?>
                                <li class="mb-2 d-flex align-items-center text-primary fw-bold">
                                    <i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i>
                                    <span>ฟรี! หน้าเว็บออกแบบพร้อมใช้งาน</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($hasFreeSender): ?>
                                <li class="mb-2 d-flex align-items-center text-danger fw-bold">
                                    <i class="fa-solid fa-shield-halved text-danger me-2"></i>
                                    <span>ฟรี! Sender ทะลุบล็อก (1 User)</span>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <!-- ปุ่มสั่งซื้อ (ผูก event กับ Modal) -->
                        <div class="mt-auto">
                            <button type="button" 
                                    class="btn <?= $isEnterprise ? 'btn-warning text-dark fw-bold' : 'btn-primary' ?> w-100 py-2 rounded-3 btn-buy-package"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#paymentModal"
                                    data-price="<?= number_format($pkg['price']) ?>"
                                    data-sms="<?= number_format($pkg['sms']) ?>">
                                <i class="fa-solid fa-cart-shopping me-1"></i> สั่งซื้อแพ็กเกจนี้
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- Modal แสดงข้อมูลบัญชีสำหรับการชำระเงิน -->
<!-- ========================================== -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="paymentModalLabel">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i>รายละเอียดการชำระเงิน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- สรุปแพ็กเกจที่เลือก -->
                <div class="alert alert-light border rounded-3 text-center mb-4">
                    <span class="text-muted d-block small">แพ็กเกจที่เลือก:</span>
                    <h4 class="fw-bold text-primary mb-0">
                        <span id="modal-sms-count">0</span> SMS (<span id="modal-package-price">0</span> บาท)
                    </h4>
                </div>

                <h6 class="fw-bold mb-3"><i class="fa-solid fa-building-columns text-primary me-2"></i>โอนเงินผ่านบัญชีธนาคาร</h6>

                <!-- บัตรข้อมูลธนาคาร -->
                <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; font-weight: bold;">
                            KBANK
                        </div>
                        <div>
                            <div class="fw-bold text-dark">ธนาคารกสิกรไทย</div>
                            <div class="text-muted small">ชื่อบัญชี: ร้านสมาร์ท ออนไลน์ ดีเวลลอปเมนท์</div>
                        </div>
                    </div>
                    
                    <div class="bg-white border rounded-3 p-2 d-flex justify-content-between align-items-center">
                        <span class="fs-5 fw-bold text-dark ms-2" id="account-number">696-202-1954</span>
                        <button class="btn btn-sm btn-outline-secondary rounded-2" onclick="copyAccountNumber()">
                            <i class="fa-regular fa-copy me-1"></i>คัดลอก
                        </button>
                    </div>
                </div>

                <!-- คำแนะนำการยืนยันการโอนเงิน -->
                <div class="text-center">
                    <p class="small text-muted mb-3">
                        เมื่อโอนเงินเรียบร้อยแล้ว โปรดส่งสลิปโอนเงินผ่านทาง Telegram เพื่อยืนยันและเปิดใช้งานระบบ
                    </p>
                    <a href="https://t.me/pjsystem789" target="_blank" class="btn btn-info text-white w-100 py-2 fw-bold rounded-3">
                        <i class="fa-brands fa-telegram me-2 fs-5 align-middle"></i>ส่งสลิปโอนเงินผ่าน Telegram
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-4 justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- JavaScript ทำงานฝั่ง Client -->
<!-- ========================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentModal = document.getElementById('paymentModal');
    
    // ดึงค่าราคาและ SMS มาแสดงใน Modal เมื่อกดปุ่มสั่งซื้อ
    paymentModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const price = button.getAttribute('data-price');
        const sms = button.getAttribute('data-sms');

        document.getElementById('modal-package-price').textContent = price;
        document.getElementById('modal-sms-count').textContent = sms;
    });
});

// ฟังก์ชันสำหรับคัดลอกเลขบัญชี
function copyAccountNumber() {
    const accNum = document.getElementById('account-number').textContent;
    navigator.clipboard.writeText(accNum).then(() => {
        alert('คัดลอกหมายเลขบัญชีเรียบร้อยแล้ว: ' + accNum);
    }).catch(err => {
        console.error('ไม่สามารถคัดลอกข้อความได้: ', err);
    });
}
</script>
