<?php
// Shared listing page (Research, Innovation, Workshop): one data pipeline, three layouts. Search is site-wide (search.php).
// Callers set $kind (dataset name = nav key = page file) and $listing (labels, 'layout', 'cta'), then require this file.
// The content area comes from includes/listing-layouts/<layout>.php.
// Old per-page search links (?q=) go to the site-wide search.
$legacyQuery = query_string('q');
if ($legacyQuery !== '') { header('Location: search.php?q=' . rawurlencode(mb_substr($legacyQuery, 0, 100)), true, 302); exit; }
$currentPage = $kind; $pageTitle = $listing['page_title'] . ' | ' . $site['name']; $listingStyles = true;
// Research leads with featured records (the featured study); the other listings are newest first.
// $fileRecords keeps the order in the JSON file (workshop posts are listed in that order).
$fileRecords = load_data($kind);
$records = sort_records($fileRecords, $kind === 'research');
// Innovations can be narrowed to one category with ?category=slug. An unknown slug shows every record.
$categorySlug = '';
$categoryName = '';
if ($kind === 'innovations') {
    $requested = query_string('category');
    $categoryName = innovation_categories()[$requested] ?? '';
    if ($categoryName !== '') {
        $categorySlug = $requested;
        $records = array_values(array_filter($records, static fn (array $record): bool => trim((string) ($record['category'] ?? '')) === $categoryName));
        $pageTitle = $categoryName . ' | ' . $listing['page_title'] . ' | ' . $site['name'];
        $navKey = 'innovations-' . $categorySlug;
    }
}
$heroOverride = $categoryName !== '' ? ['eyebrow' => 'นวัตกรรม / ' . $categoryName, 'title' => 'นวัตกรรม' . $categoryName, 'highlight' => $categoryName, 'intro' => 'นวัตกรรมการศึกษาในหมวด' . $categoryName . ' ที่ทดลองใช้ในห้องเรียนได้'] : [];
$layout = in_array($listing['layout'] ?? '', ['research', 'innovations', 'workshops', 'media', 'news'], true) ? $listing['layout'] : 'research';
$fallbackCategory = $listing['fallback_category']; $fallbackAuthor = $listing['fallback_author'];
require __DIR__ . '/header.php';
?>
<main id="main" class="page-shell page-shell--<?= e($kind) ?>">
  <?php render_page_hero($kind, $heroOverride); ?>
  <section class="container editorial-canvas editorial-canvas--<?= e($layout) ?>">
    <?php require __DIR__ . '/listing-layouts/' . $layout . '.php'; ?>
  </section>
</main><?php require __DIR__ . '/footer.php'; ?>
