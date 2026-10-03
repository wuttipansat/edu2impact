<?php
require __DIR__ . '/includes/bootstrap.php';
$record = find_record(load_data('innovations'), query_string('id'));
$currentPage = 'innovations';
$pageTitle = 'Request to Use / Collaborate | ' . $site['name'];
if (!$record) { http_response_code(404); }
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--request"><section class="section container article-layout request-panel"><p class="eyebrow">COLLABORATION / EDU2IMPACT</p><h1>Request to Use / Collaborate</h1>
<?php if ($record): ?><h2><?= e($record['title']) ?></h2><p>สนใจนำไปใช้ ทดลองใช้ ร่วมขยายผล หรือขออนุญาตใช้สิทธิ กรุณาติดต่อฝ่ายวิจัย พร้อมแจ้งชื่อองค์กร ช่องทางติดต่อ ลักษณะการใช้ จำนวนผู้ใช้ และการสนับสนุนที่ต้องการ</p><p>แบบฟอร์มส่งคำขอออนไลน์ยังไม่เปิดให้บริการ</p><div class="actions"><a class="button" href="contact.php">ดูช่องทางติดต่อ</a><a class="button secondary" href="<?= e(detail_url('innovations', $record['id'])) ?>">กลับไปยังนวัตกรรม</a></div>
<?php else: ?><p>ไม่พบนวัตกรรมที่ต้องการ กรุณาเลือกจากรายการก่อนขอใช้หรือร่วมงาน</p><a class="button" href="innovations.php">Innovation Portfolio</a><?php endif; ?>
</section></main><?php require __DIR__ . '/includes/footer.php'; ?>
