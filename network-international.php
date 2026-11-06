<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/network.php';
$currentPage = 'network-international'; $pageTitle = 'เครือข่ายวิจัยและความร่วมมือนานาชาติ | ' . $site['name'];
$networkAssets = true; // network.css and network-map.js, loaded by includes/header.php
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--network"><?php render_page_hero('network-international'); render_network_page('world'); ?></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
