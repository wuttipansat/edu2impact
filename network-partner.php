<?php
// One institution of the international research network with all its activities: network-partner.php?id=<network_partners.id>
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/network.php';
$currentPage = 'network-international';
$networkAssets = true; // network.css and network-map.js, loaded by includes/header.php
$partnerId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$partner = null; $countryName = '';
foreach (network_areas('world') as $area) {
    foreach ($area['items'] as $item) {
        if ((int) ($item['id'] ?? 0) === $partnerId) { $partner = $item; $countryName = $area['name']; break 2; }
    }
}
if (!$partner) {
    http_response_code(404);
    $pageTitle = 'ไม่พบสถาบัน | ' . $site['name'];
    require __DIR__ . '/includes/header.php';
    ?><main id="main" class="page-shell page-shell--network"><section class="container section empty-state"><h1>ไม่พบสถาบันที่ต้องการ</h1><p>ลิงก์นี้อาจเก่าหรือสถาบันถูกซ่อนจากเว็บแล้ว เลือกจากเครือข่ายทั้งหมดแทนได้</p><a class="button" href="network-international.php">ไปหน้าเครือข่ายวิจัยนานาชาติ</a></section></main><?php
    require __DIR__ . '/includes/footer.php';
    return;
}
$pageTitle = $partner['institution'] . ' | ' . $site['name'];
$pageDescription = trim((string) ($partner['summary'] ?? '')) ?: 'ความร่วมมือระหว่างคณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์ กับ ' . $partner['institution'];
$pagePath = network_partner_url((int) $partner['id']);
$ogImage = image_path($partner) ?: null;
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--network"><?php render_network_partner($partner, $countryName); ?></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
