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
$layout = in_array($listing['layout'] ?? '', ['research', 'innovations', 'workshops', 'media', 'news'], true) ? $listing['layout'] : 'research';
$fallbackCategory = $listing['fallback_category']; $fallbackAuthor = $listing['fallback_author'];
require __DIR__ . '/header.php';
?>
<main id="main" class="page-shell page-shell--<?= e($kind) ?>">
  <?php render_page_hero($kind); ?>
  <section class="container editorial-canvas editorial-canvas--<?= e($layout) ?>">
    <?php require __DIR__ . '/listing-layouts/' . $layout . '.php'; ?>
  </section>
</main><?php require __DIR__ . '/footer.php'; ?>
