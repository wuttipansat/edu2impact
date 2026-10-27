<?php
require_once dirname(__DIR__) . '/includes/admin/posts.php';
$user = admin_boot();
$db = admin_db();
$id = in_int('id') ?: get_int('id');
$errors = []; $lock = null; $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($isPost && in_str('op') === 'restore' && $id) {
    $rev = $db->prepare('SELECT * FROM post_revisions WHERE id = ? AND post_id = ?'); $rev->execute([(int) in_int('rev'), $id]); $rev = $rev->fetch();
    $snap = $rev ? json_decode($rev['snapshot'], true) : null;
    if (!$snap) { admin_flash('ไม่พบฉบับนี้', 'error'); admin_go('post-edit.php?id=' . $id); }
    $p = admin_post_state($snap['post'] + ['loaded_at' => ''], array_map(fn($l) => ['to_kind' => $l['to_kind'], 'to_slug' => $l['to_slug']], $snap['links'] ?? []));
    $p['force'] = 1; $p['id'] = $id;
    [, $errors] = admin_post_save($p, $id, 'restore');
    if (!$errors) { admin_flash('ใช้ฉบับวันที่ ' . admin_time($rev['created_at']) . ' แล้ว ฉบับก่อนหน้านี้ยังอยู่ใน “ดูฉบับก่อนหน้า”'); admin_go('post-edit.php?id=' . $id); }
    $isPost = false; $p = admin_post_load($id);
} elseif ($isPost) {
    $p = admin_post_from_input(); $p['id'] = $id;
    if ($id) {
        $cur = $db->prepare('SELECT kind FROM posts WHERE id = ?'); $cur->execute([$id]); $k = $cur->fetchColumn();
        if (!$k) { admin_fail('ไม่พบโพสต์นี้', 404); }
        $p['kind'] = $k;
    }
    if ($p['upload_errors']) { $errors = ['upload' => implode(' / ', $p['upload_errors'])]; }
    else {
        [$savedId, $errors] = admin_post_save($p, $id);
        if (!$errors) { admin_go('post-edit.php?id=' . $savedId . '&saved=1'); }
        if (isset($errors['LOCK'])) {
            $lock = $errors['LOCK']; $errors = [];
            $fresh = $db->prepare('SELECT COALESCE(updated_at, created_at) FROM posts WHERE id = ?'); $fresh->execute([$id]); $p['loaded_at'] = (string) $fresh->fetchColumn();
        }
    }
} elseif ($id) {
    $p = admin_post_load($id);
    if (!$p) { admin_fail('ไม่พบโพสต์นี้', 404); }
} else {
    $p = admin_post_blank(isset(ADMIN_KINDS[get_str('kind')]) ? get_str('kind') : '');
}
$kind = $p['kind']; $isNew = !$id;
$cats = array_values(require dirname(__DIR__) . '/config/innovation-categories.php');
$lastOp = in_str('op') ?: 'save';

$err = fn(string $k) => isset($errors[$k]) ? '<span class="err" role="alert">' . e($errors[$k]) . '</span>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' bad' : '';
$moreErr = (bool) array_intersect(array_keys($errors), ['post_date', 'category', 'tool_url', 'download', 'slug']);

// pictures used here (for thumbnails)
$ids = array_filter(array_merge(array_column($p['images'], 'media'), [ctype_digit((string) $p['image_choice']) ? (int) $p['image_choice'] : 0, (int) $p['download_id']]));
$info = [];
if ($ids) {
    $m = $db->query('SELECT id, filename, mime, LEFT(sha256, 8) AS v FROM media WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
    foreach ($m as $r) { $info[(int) $r['id']] = $r; }
}
$thumb = fn(?int $mid, string $cls = 'thumb') => $mid && isset($info[$mid]) ? '<img class="' . $cls . '" src="media-img.php?id=' . $mid . '&amp;v=' . e($info[$mid]['v']) . '" alt="">' : '';

admin_head($isNew ? 'สร้างโพสต์ใหม่' : 'แก้ไขโพสต์', '');
if (get_str('saved') === '1' && !$isPost) {
    echo '<p class="notice notice--ok" role="status">บันทึกแล้ว' . ($p['is_public'] ? ' · <a href="../' . e(detail_url($kind, $p['slug'])) . '" target="_blank" rel="noopener">ดูบนเว็บ →</a>' : ' · ยังเป็นร่าง ยังไม่แสดงบนเว็บ') . '</p>';
}
if ($lock): ?><div class="notice notice--error" role="alert"><p><strong><?= $lock['who'] !== '' ? 'คุณ ' . e($lock['who']) : 'มีคนอื่น' ?> แก้โพสต์นี้เมื่อ <?= e($lock['when']) ?> หลังจากคุณเปิดหน้านี้</strong> ยังไม่ได้บันทึกอะไร สิ่งที่คุณพิมพ์ยังอยู่ในฟอร์มด้านล่าง</p>
<p><button class="btn btn--small" type="submit" form="editform" name="op" value="<?= e($lastOp) ?>" id="force-btn">ใช้ของฉันแทน</button> <a href="post-edit.php?id=<?= (int) $id ?>">ดูของเขา (ของฉันจะหาย)</a></p></div><?php endif;
if ($errors && !$lock): ?><div class="notice notice--error" role="alert"><p><strong>ยังบันทึกไม่ได้ ตรวจสิ่งต่อไปนี้</strong></p><ul><?php foreach ($errors as $m) { echo '<li>' . e(is_array($m) ? '' : $m) . '</li>'; } ?></ul></div><?php endif;
?>
<form id="editform" method="post" action="post-edit.php" enctype="multipart/form-data" data-dirty-guard>
<?php auth_csrf_field(); ?>
<input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="loaded_at" value="<?= e($p['loaded_at']) ?>"><input type="hidden" name="was_public" value="<?= (int) $p['was_public'] ?>">
<?php if ($lock): ?><input type="hidden" name="force" value="1"><?php endif; ?>
<?php if ($isNew): ?>
<fieldset class="types"><legend>โพสต์นี้เป็นประเภทไหน</legend>
<?php foreach (ADMIN_KINDS as $k => $label): ?><label><input type="radio" name="kind" value="<?= e($k) ?>"<?= $kind === $k ? ' checked' : '' ?> required><span><?= e($label) ?></span></label><?php endforeach; ?></fieldset><?= $err('kind') ?>
<?php else: ?><p class="muted">ประเภท: <strong><?= e(ADMIN_KINDS[$kind]) ?></strong><input type="hidden" name="kind" value="<?= e($kind) ?>"></p><?php endif; ?>

<div class="field"><label for="title">หัวข้อ</label><input id="title" name="title" value="<?= e($p['title']) ?>" maxlength="400" required class="<?= trim($bad('title')) ?>" data-slug-from><?= $err('title') ?></div>

<div class="field"><label for="cover_file">รูปปก (ไม่บังคับ)</label>
<?php $curCover = ctype_digit((string) $p['image_choice']) ? (int) $p['image_choice'] : 0; $legacy = $p['image_choice'] === 'keep'; ?>
<?php if ($curCover || $legacy): ?><label class="check"><input type="radio" name="cover" value="<?= $legacy ? 'keep' : $curCover ?>" checked> ใช้รูปปกนี้ต่อ<?= $legacy ? ' (รูปเดิมของเว็บ)' : $thumb($curCover) ?></label>
<label class="check"><input type="radio" name="cover" value="none"> ไม่ใช้รูปปก</label>
<?php else: ?><input type="hidden" name="cover" value="none"><?php endif; ?>
<label class="check" for="cover_file">เลือกรูปใหม่จากเครื่อง (<?= ($curCover || $legacy) ? 'แทนรูปเดิม' : 'จะเป็นรูปปก' ?>)</label>
<input id="cover_file" type="file" name="cover_file" accept="image/*" data-preview="cover-prev"><div id="cover-prev" class="pending" hidden></div>
<label class="check">คำอธิบายรูปปก (ไม่บังคับ) <small>บอกว่าในรูปมีอะไร สำหรับผู้ใช้โปรแกรมอ่านหน้าจอ</small><input name="cover_alt" maxlength="400"></label>
<details><summary>หรือเลือกจากรูปที่อัปโหลดไว้แล้ว</summary><div><?php admin_media_pics('cover', $curCover ?: null, 'image', $curCover ? [$curCover] : []); ?></div></details><?= $err('cover') ?></div>

<div class="field"><label for="summary">สรุปสั้น 1-2 ประโยค</label><textarea id="summary" name="summary" maxlength="4000"><?= e($p['summary']) ?></textarea></div>

<div class="field"><label for="content">เนื้อหา <small>เว้นบรรทัดว่างหนึ่งบรรทัดเพื่อขึ้นย่อหน้าใหม่ ถ้าจะแทรกรูป ให้พิมพ์ป้ายรูป เช่น [รูป 1] ไว้ในบรรทัดของตัวเองตรงที่อยากให้รูปอยู่</small></label>
<textarea id="content" name="content" class="tall<?= $bad('content') ?>"><?= e($p['content']) ?></textarea><?= $err('content') ?></div>

<h2>รูปในเนื้อหา</h2>
<?php foreach (array_values($p['images']) as $i => $im): ?><div class="pic"><div><?= $thumb($im['media']) ?></div><div>
<strong>[รูป <?= (int) $im['n'] ?>]</strong> <button type="button" class="btn btn--small btn--ghost" data-insert="[รูป <?= (int) $im['n'] ?>]" hidden>แทรกตรงเคอร์เซอร์</button>
<input type="hidden" name="img[<?= $i ?>][n]" value="<?= (int) $im['n'] ?>"><input type="hidden" name="img[<?= $i ?>][media]" value="<?= (int) $im['media'] ?>">
<label>คำอธิบายรูป <small>บอกว่าในรูปมีอะไร สำหรับผู้ใช้โปรแกรมอ่านหน้าจอ</small><input name="img[<?= $i ?>][alt]" value="<?= e($im['alt']) ?>" maxlength="400"></label>
<label>คำบรรยายใต้รูป (ไม่บังคับ)<input name="img[<?= $i ?>][caption]" value="<?= e($im['caption']) ?>" maxlength="400"></label>
<label class="check"><input type="checkbox" name="img[<?= $i ?>][drop]" value="1"> เอารูปนี้ออกจากโพสต์</label></div></div><?php endforeach; ?>
<?php $nextN = max(array_merge([0], array_column($p['images'], 'n'))); ?>
<details<?= $p['images'] ? '' : ' open' ?>><summary>เพิ่มรูปในเนื้อหา (สูงสุด 3 รูปต่อครั้ง)</summary><div>
<?php foreach ([1, 2, 3] as $slot): ?><div class="field"><label for="newimg<?= $slot ?>">รูปใหม่ <?= $slot ?> <small>จะได้ป้าย [รูป <?= $nextN + $slot ?>] ถ้าไม่ใส่ป้ายในเนื้อหา รูปจะต่อท้ายเนื้อหา</small></label>
<input id="newimg<?= $slot ?>" type="file" name="newimg[<?= $slot ?>]" accept="image/*" data-preview="newprev<?= $slot ?>"><div id="newprev<?= $slot ?>" class="pending" hidden></div>
<input name="newalt[<?= $slot ?>]" placeholder="คำอธิบายรูป: ในรูปมีอะไร" maxlength="400" aria-label="คำอธิบายรูปใหม่ <?= $slot ?>"><input name="newcap[<?= $slot ?>]" placeholder="คำบรรยายใต้รูป (ไม่บังคับ)" maxlength="400" aria-label="คำบรรยายใต้รูปใหม่ <?= $slot ?>"></div><?php endforeach; ?>
<p class="hint">ต้องใส่รูปมากกว่า 3 รูป? บันทึกก่อนแล้วกลับมาเพิ่มได้อีกครั้ง</p></div></details>
<?= isset($errors['upload']) ? '<p class="notice notice--error" role="alert">' . e($errors['upload']) . '</p>' : '' ?>

<details<?= $moreErr ? ' open' : '' ?>><summary>ตัวเลือกเพิ่มเติม (ไม่จำเป็น)</summary><div>
<div class="field"><label for="author">ผู้เขียน (ไม่บังคับ)</label><input id="author" name="author" value="<?= e($p['author']) ?>" maxlength="255"></div>
<div class="field"><label for="post_date">วันที่เผยแพร่ (ไม่บังคับ)</label><input id="post_date" type="date" name="post_date" value="<?= e($p['post_date'] ?: ($isNew ? date('Y-m-d') : '')) ?>" class="<?= trim($bad('post_date')) ?>"><?= $err('post_date') ?></div>
<?php if ($kind !== 'innovations' && $p['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($p['category']) ?>"><?php endif; ?>
<?php if ($kind === 'innovations' || $isNew): ?><div class="field"><label for="category">หมวด (เฉพาะนวัตกรรม)</label><select id="category" name="category"><option value="">— ไม่ระบุ —</option><?php foreach ($cats as $c): ?><option<?= $c === $p['category'] ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select><?= $err('category') ?></div><?php endif; ?>
<div class="field"><label for="audience">กลุ่มเป้าหมาย (ไม่บังคับ) <small>หนึ่งรายการต่อบรรทัด</small></label><textarea id="audience" name="audience"><?= e($p['audience_text']) ?></textarea></div>
<div class="field"><label for="subjects">วิชาหรือสาระที่เกี่ยวข้อง (ไม่บังคับ) <small>หนึ่งรายการต่อบรรทัด</small></label><textarea id="subjects" name="subjects"><?= e($p['subjects_text']) ?></textarea></div>
<div class="field"><label for="features">จุดเด่น (ไม่บังคับ) <small>หนึ่งรายการต่อบรรทัด</small></label><textarea id="features" name="features"><?= e($p['features_text']) ?></textarea></div>
<div class="field"><label for="refs">แหล่งอ้างอิง (ไม่บังคับ) <small>หนึ่งรายการต่อบรรทัด</small></label><textarea id="refs" name="refs"><?= e($p['refs_text']) ?></textarea></div>
<div class="field"><label for="tool_url">ลิงก์เครื่องมือหรือเว็บที่เกี่ยวข้อง (ไม่บังคับ) <small>ขึ้นต้นด้วย https:// หรือชื่อโฟลเดอร์ในเว็บนี้ เช่น apps/my-tool/</small></label><input id="tool_url" name="tool_url" value="<?= e($p['tool_url']) ?>" maxlength="500" class="<?= trim($bad('tool_url')) ?>"><?= $err('tool_url') ?></div>
<div class="field"><label for="tool_label">ข้อความบนปุ่มลิงก์ (ไม่บังคับ)</label><input id="tool_label" name="tool_label" value="<?= e($p['tool_label']) ?>" maxlength="150"></div>
<div class="field"><label for="pdf_file">ไฟล์ PDF ให้ดาวน์โหลด (ไม่บังคับ)</label>
<?php if ($p['download_id'] && isset($info[(int) $p['download_id']])): ?><p class="muted">ตอนนี้: <?= e(basename($info[(int) $p['download_id']]['filename'])) ?></p><label class="check"><input type="checkbox" name="download_id" value="<?= (int) $p['download_id'] ?>" checked> ใช้ไฟล์นี้ต่อ</label><?php endif; ?>
<input id="pdf_file" type="file" name="pdf_file" accept="application/pdf"><?= $err('download') ?></div>
<div class="field"><label for="download_label">ข้อความบนปุ่มดาวน์โหลด (ไม่บังคับ)</label><input id="download_label" name="download_label" value="<?= e($p['download_label']) ?>" maxlength="150"></div>
<?php $others = $db->prepare('SELECT kind, slug, title FROM posts WHERE NOT (kind = ? AND slug = ?) ORDER BY title'); $others->execute([$kind, $p['slug']]); $others = $others->fetchAll(); if ($others): ?>
<div class="field"><label for="relsearch">โพสต์ที่เกี่ยวข้อง (ไม่บังคับ) <small>ติ๊กโพสต์ที่อยากให้แสดงเป็นลิงก์ท้ายโพสต์นี้</small></label><input id="relsearch" type="search" data-filter="rel" placeholder="ค้นหาหัวข้อ"><div class="picks" data-filter-list="rel">
<?php foreach ($others as $o): $v = $o['kind'] . '/' . $o['slug']; ?><label data-filter-text="<?= e(mb_strtolower($o['title'])) ?>"><input type="checkbox" name="related[]" value="<?= e($v) ?>"<?= in_array($v, $p['related'], true) ? ' checked' : '' ?>><?= e($o['title']) ?> <span class="muted">(<?= e(ADMIN_KINDS[$o['kind']]) ?>)</span></label><?php endforeach; ?></div></div><?php endif; ?>
<label class="check"><input type="checkbox" name="featured" value="1"<?= $p['featured'] ? ' checked' : '' ?>> เป็นโพสต์แนะนำ</label>
<label class="check"><input type="checkbox" name="to_top" value="1"<?= $p['to_top'] ? ' checked' : '' ?>> ย้ายมาไว้บนสุดของประเภทนี้<?= $isNew ? ' <small>โพสต์ใหม่อยู่บนสุดอยู่แล้ว</small>' : '' ?></label>
<div class="field"><label for="slug">ที่อยู่ลิงก์ของโพสต์ <small><?= $isNew ? 'ปล่อยว่างไว้ ระบบตั้งให้เอง' : 'เปลี่ยนแล้วลิงก์เก่าที่แชร์ไปแล้วจะใช้ไม่ได้' ?> ใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข และ -</small></label>
<input id="slug" name="slug" value="<?= e($p['slug']) ?>" maxlength="120" class="<?= trim($bad('slug')) ?>"><?= $err('slug') ?></div>
</div></details>

<div class="bar">
<?php if ($isNew || !$p['was_public']): ?><button class="btn btn--big" type="submit" name="op" value="publish">เผยแพร่</button><button class="btn btn--ghost btn--big" type="submit" name="op" value="draft"><?= $isNew ? 'เก็บเป็นร่าง' : 'บันทึกเป็นร่าง' ?></button>
<?php else: ?><button class="btn btn--big" type="submit" name="op" value="save">บันทึกการแก้ไข</button><button class="btn btn--danger btn--big" type="submit" form="hideform">ซ่อนจากเว็บ</button><?php endif; ?>
<button class="btn btn--ghost btn--big" type="submit" name="op" value="preview" formaction="preview.php" formtarget="_blank" formnovalidate>ดูตัวอย่าง</button>
</div>
</form>
<?php if (!$isNew): ?>
<form id="hideform" method="post" action="index.php"><?php auth_csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="op" value="hide"></form>
<?php $revs = $db->prepare('SELECT id, user_name, created_at FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 20'); $revs->execute([$id]); $revs = $revs->fetchAll(); if ($revs): ?>
<details><summary>ดูฉบับก่อนหน้า</summary><div><p class="muted">ทุกครั้งที่บันทึก ระบบเก็บฉบับก่อนบันทึกไว้ให้ กด “ใช้ฉบับนี้” เพื่อย้อนกลับ (ฉบับปัจจุบันจะถูกเก็บไว้ด้วย)</p>
<?php foreach ($revs as $r): ?><form class="row inline" method="post" action="post-edit.php" data-confirm="ใช้ฉบับนี้แทนฉบับปัจจุบัน?"><?php auth_csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="rev" value="<?= (int) $r['id'] ?>">
<span><?= e(admin_time($r['created_at'])) ?> · <?= e((string) $r['user_name']) ?></span> <button class="btn btn--small btn--ghost" name="op" value="restore">ใช้ฉบับนี้</button></form><?php endforeach; ?></div></details>
<?php endif; endif;
admin_foot();
