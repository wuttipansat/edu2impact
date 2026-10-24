<?php
// Workshop: a list of workshops (title only), in the order of data/workshops.json.
// Each workshop's detail page has its own contact box.
$posts = $fileRecords;
?>
<div class="agenda" aria-live="polite">
<?php if ($posts): ?>
  <section class="agenda-group agenda-group--post" aria-labelledby="workshop-posts-heading">
    <div class="page-heading page-heading--all"><div><h2 id="workshop-posts-heading">เวิร์กช็อป</h2></div><span class="item-count"><strong><?= count($posts) ?></strong> เวิร์กช็อป</span></div>
    <?php foreach ($posts as $record): ?>
    <article class="agenda-row is-post">
      <div class="agenda-date agenda-post" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></div>
      <div class="agenda-info">
        <h3><a class="lib-stretch" href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
      </div>
      <span class="agenda-cta" aria-hidden="true"><?= icon_arrow() ?></span>
    </article>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <p class="listing-none">ยังไม่มีเวิร์กช็อปในขณะนี้</p>
<?php endif; ?>
</div>
