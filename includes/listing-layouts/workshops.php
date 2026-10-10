<?php
// Workshop: "Agenda" - next workshop banner, search, then upcoming and past groups.
// Upcoming soonest first, then past most recent first.
$today = listing_today();
$upcomingAll = array_values(array_filter($records, static fn (array $r): bool => is_string($r['date'] ?? null) && $r['date'] >= $today));
usort($upcomingAll, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
$next = $upcomingAll[0] ?? null;
$nextDate = $next ? listing_date_parts($next['date']) : null;

$upcoming = array_values(array_filter($records, static fn (array $r): bool => is_string($r['date'] ?? null) && $r['date'] >= $today));
$past = array_values(array_filter($records, static fn (array $r): bool => !(is_string($r['date'] ?? null) && $r['date'] >= $today)));
usort($upcoming, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
usort($past, static fn (array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
$groups = [['กำลังจะมาถึง', 'UPCOMING', $upcoming, false], ['ที่ผ่านมา', 'PAST', $past, true]];
?>
<?php if ($next && $nextDate): ?>
<section class="next-workshop" aria-labelledby="next-workshop-title">
  <div class="next-workshop__date" aria-hidden="true"><strong><?= e($nextDate['day']) ?></strong><span><?= e($nextDate['month']) ?> <?= e($nextDate['year']) ?></span><em><?= e($nextDate['weekday']) ?></em></div>
  <div class="next-workshop__body">
    <span class="section-kicker">NEXT WORKSHOP · เวิร์กช็อปถัดไป</span>
    <h2 id="next-workshop-title"><?= e($next['title']) ?></h2>
    <p class="lib-meta"><span><?= e($next['category'] ?? $fallbackCategory) ?></span><span><?= e($next['author'] ?? $fallbackAuthor) ?></span></p>
  </div>
  <a class="button" href="<?= e(detail_url($kind, $next['id'])) ?>">ดูรายละเอียด</a>
</section>
<?php endif; ?>
<div class="agenda" aria-live="polite">
<?php foreach ($groups as [$label, $kicker, $items, $isPast]): if (!$items) { continue; } ?>
  <section class="agenda-group<?= $isPast ? ' agenda-group--past' : '' ?>" aria-label="<?= e($label) ?>">
    <div class="page-heading page-heading--all"><div><span class="section-kicker"><?= e($kicker) ?></span><h2><?= e($label) ?></h2></div><span class="item-count"><?= count($items) ?> <?= e($listing['noun']) ?></span></div>
    <?php foreach ($items as $record): $parts = listing_date_parts($record['date'] ?? null); ?>
    <article class="agenda-row<?= $isPast ? ' is-past' : '' ?>">
      <div class="event-date agenda-date" aria-hidden="true"><strong><?= e($parts['day'] ?? '–') ?></strong><span><?= e($parts['month'] ?? '') ?></span></div>
      <div class="agenda-info">
        <h3><a class="lib-stretch" href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
        <p class="lib-meta"><span class="agenda-cat"><?= e($record['category'] ?? $fallbackCategory) ?></span><span><?= e($record['author'] ?? $fallbackAuthor) ?></span><?php if ($parts): ?><span><?= e($parts['weekday']) ?> <?= e($parts['year']) ?></span><?php endif; ?></p>
      </div>
      <span class="agenda-cta"><?php if ($isPast): ?><span class="agenda-done">จบแล้ว</span><?php endif; ?><span aria-hidden="true"><?= e($listing['cta']) ?> →</span></span>
    </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>
<?php if (!$records): ?><p class="listing-none">ยังไม่มีรายการในขณะนี้</p><?php endif; ?>
