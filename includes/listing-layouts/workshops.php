<?php
// Workshop: a catalogue of workshops, in the order of data/workshops.json. Each row has the cover, title, summary,
// the speaker and the research or innovation it builds on. The whole row opens the detail page (stretched title link);
// the "builds on" links sit above it and open their own pages. A workshop without a cover simply has no picture column.
// Each workshop's detail page has its own contact box; the button at the top goes to the contact page.
$posts = $fileRecords;
?>
<div class="ws" aria-live="polite">
<?php if ($posts): ?>
  <section aria-labelledby="workshop-posts-heading">
    <div class="page-heading ws-head">
      <div><h2 id="workshop-posts-heading"><?= e($listing['library_heading']) ?></h2><p class="ws-count"><?= count($posts) ?> <?= e($listing['noun']) ?></p></div>
      <a class="button ws-request" href="contact.php">ขอจัดเวิร์กช็อปที่โรงเรียนของคุณ <?= icon_arrow('up-right') ?></a>
    </div>
    <div class="ws-list">
    <?php foreach ($posts as $record):
        $cover = image_path($record);
        $speaker = trim((string) ($record['author'] ?? ''));
        $builtOn = related_records($kind, $record);
        $summary = trim((string) ($record['summary'] ?? ''));
        $href = detail_url($kind, $record['id']);
    ?>
    <article class="ws-row<?= $cover === '' ? ' ws-row--plain' : '' ?>">
      <?php if ($cover !== ''): ?><a class="ws-row__media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($cover) ?>" width="640" height="480" alt="" loading="lazy"></a><?php endif; ?>
      <div class="ws-row__body">
        <?php if (is_dated($record)): ?><p class="ws-row__date"><time datetime="<?= e($record['date']) ?>"><?= e(thai_date($record['date'])) ?></time></p><?php endif; ?>
        <h3 class="ws-row__title"><a class="lib-stretch" href="<?= e($href) ?>"><?= e($record['title']) ?></a></h3>
        <?php if ($summary !== ''): ?><p class="ws-row__summary"><?= e($summary) ?></p><?php endif; ?>
        <?php if ($speaker !== '' || $builtOn): ?><dl class="ws-row__facts">
          <?php if ($speaker !== ''): ?><div><dt>วิทยากร</dt><dd><?= e($speaker) ?></dd></div><?php endif; ?>
          <?php foreach ($builtOn as $source): ?><div><dt>ต่อยอดจาก<?= e(kind_label($source['kind'])) ?></dt><dd><a href="<?= e(detail_url($source['kind'], $source['record']['id'])) ?>"><?= e($source['record']['title']) ?></a></dd></div><?php endforeach; ?>
        </dl><?php endif; ?>
        <span class="ws-row__cta" aria-hidden="true"><?= e($listing['cta']) ?> <?= icon_arrow('up-right') ?></span>
      </div>
    </article>
    <?php endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <p class="listing-none">ยังไม่มีเวิร์กช็อปในขณะนี้</p>
<?php endif; ?>
</div>
