<?php
// Small list editor shared by the home banner and shop pages (and the unlinked events page).
// A $spec maps column => field: type text|textarea|date|url|youtube|slug|flag|image|fixed, label, max, required, hint, value (fixed), default.
require_once __DIR__ . '/media-lib.php';
require_once __DIR__ . '/posts.php';

function admin_entity_values(array $spec, ?array $current): array {
    $errors = []; $v = [];
    foreach ($spec as $col => $f) {
        $t = $f['type'] ?? 'text'; $label = $f['label'] ?? $col;
        switch ($t) {
            case 'fixed': $v[$col] = $f['value']; break;
            case 'flag': $v[$col] = in_flag($col); break;
            case 'date': $x = in_str($col); $v[$col] = $x === '' ? null : in_date($col); if ($x !== '' && $v[$col] === null) { $errors[] = "$label ไม่ถูกต้อง"; } break;
            case 'url': $x = clean_url(in_line($col, $f['max'] ?? 500)); if ($x === null) { $errors[] = "$label ต้องขึ้นต้นด้วย https:// (หรือเป็นหน้าในเว็บนี้)"; $x = ''; } $v[$col] = $x === '' ? null : $x; break;
            case 'youtube': $x = in_line($col, 500); if ($x !== '' && !preg_match('~\Ahttps://(www\.|m\.)?(youtube\.com|youtube-nocookie\.com|youtu\.be)/[\w\-./?=&%]+\z~', $x)) { $errors[] = "$label ต้องเป็นลิงก์ YouTube"; $x = ''; } $v[$col] = $x === '' ? null : $x; break;
            case 'slug': $x = clean_slug(in_line($col, 120)); $v[$col] = $x === '' ? null : $x; break;
            case 'image':
                [$up, $upErr] = admin_post_upload(admin_file_at($col . '_file'));
                $pick = in_str($col . '_pick'); $choice = in_str($col);
                $id = $up ?: (ctype_digit($pick) ? (int) $pick : null);
                if ($upErr) { $errors[] = $upErr; }
                elseif ($id) { $v['image_id'] = $id; $v['image'] = null; }
                elseif ($choice === 'none') { $v['image_id'] = null; $v['image'] = null; }
                $has = isset($v['image_id']) ? (bool) $v['image_id'] : (!empty($current['image_id']) || !empty($current['image']));
                if (!empty($f['required']) && !$has) { $errors[] = "$label: ต้องเลือกรูป"; }
                break;
            case 'textarea': $x = in_str($col, $f['max'] ?? 4000); $v[$col] = $x === '' ? null : $x; break;
            default: $x = in_line($col, $f['max'] ?? 255); $v[$col] = $x === '' ? null : $x;
        }
        if (!empty($f['required']) && $t !== 'image' && $t !== 'flag' && $t !== 'fixed' && ($v[$col] ?? null) === null) { $errors[] = "$label: ต้องกรอก"; }
    }
    if (!empty($v['image_id'])) { $m = admin_db()->prepare('SELECT mime FROM media WHERE id = ? AND og_of IS NULL'); $m->execute([$v['image_id']]); if (strpos((string) $m->fetchColumn(), 'image/') !== 0) { $errors[] = 'รูปที่เลือกไม่มีในคลังรูป'; unset($v['image_id'], $v['image']); } }
    return [$errors, $v];
}

// Moves one row up/down/to the top among the rows that share $where (e.g. "type = 'promo'") and renumbers them 0..n.
function admin_entity_move(string $table, int $id, string $op, string $where = '1=1', array $args = []): void {
    $db = admin_db();
    $s = $db->prepare("SELECT id FROM $table WHERE $where ORDER BY sort_order, id"); $s->execute($args);
    $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    $i = array_search($id, $ids, true);
    if ($i === false) { return; }
    array_splice($ids, $i, 1);
    $to = $op === 'top' ? 0 : ($op === 'up' ? max(0, $i - 1) : min(count($ids), $i + 1));
    array_splice($ids, $to, 0, [$id]);
    $u = $db->prepare("UPDATE $table SET sort_order = ? WHERE id = ?");
    foreach ($ids as $n => $rid) { $u->execute([$n, $rid]); }
}

// Handles POST ops save/delete/show/hide/up/down/top. Returns an error list (empty = done and flashed).
function admin_entity_post(string $table, array $spec, string $where = '1=1', array $args = []): array {
    $db = admin_db(); $op = in_str('op'); $id = in_int('id');
    if (!in_array($table, ['home_items', 'shop_items', 'events'], true)) { return ['ตารางไม่ถูกต้อง']; }
    if ($op === 'delete' && $id) { $db->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]); admin_flash('ลบแล้ว'); return []; }
    if (in_array($op, ['show', 'hide'], true) && $id) {
        $db->prepare("UPDATE $table SET is_public = ? WHERE id = ?")->execute([$op === 'show' ? 1 : 0, $id]); admin_flash($op === 'show' ? 'แสดงบนเว็บแล้ว' : 'ซ่อนจากเว็บแล้ว'); return [];
    }
    if (in_array($op, ['up', 'down', 'top'], true) && $id) { admin_entity_move($table, $id, $op, $where, $args); return []; }
    if ($op !== 'save') { return []; }
    $cur = null;
    if ($id) { $s = $db->prepare("SELECT * FROM $table WHERE id = ?"); $s->execute([$id]); $cur = $s->fetch() ?: null; if (!$cur) { return ['ไม่พบรายการนี้ (อาจถูกลบไปแล้ว)']; } }
    [$errors, $v] = admin_entity_values($spec, $cur);
    if ($errors) { return $errors; }
    try {
        if ($cur) { $db->prepare("UPDATE $table SET " . implode(', ', array_map(fn($c) => "$c = ?", array_keys($v))) . ' WHERE id = ?')->execute([...array_values($v), $id]); }
        else {
            $v['sort_order'] = (int) $db->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM $table")->fetchColumn();
            $db->prepare("INSERT INTO $table (" . implode(',', array_keys($v)) . ') VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')')->execute(array_values($v));
        }
        admin_flash('บันทึกแล้ว');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { return ['ชื่อลิงก์ (slug) ซ้ำกับรายการอื่น ลองเปลี่ยนเป็นชื่ออื่น']; }
        error_log('edu2impact admin entity: ' . $e->getMessage()); return ['บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง'];
    }
    return [];
}

// $posted = the submitted $_POST when this form failed validation, so typed text is kept.
function admin_entity_form(string $action, array $spec, ?array $row, array $hidden = [], string $submit = 'บันทึก', ?array $posted = null): void {
    $uid = 'f' . substr(md5($action . json_encode($hidden) . ($row['id'] ?? 'new')), 0, 8);
    admin_form_open($action, 'enctype="multipart/form-data"');
    echo '<input type="hidden" name="op" value="save">';
    if ($row) { echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '">'; }
    foreach ($hidden as $k => $val) { echo '<input type="hidden" name="' . e($k) . '" value="' . e($val) . '">'; }
    foreach ($spec as $col => $f) {
        $t = $f['type'] ?? 'text';
        if ($t === 'fixed') { continue; }
        $val = $posted !== null ? ($posted[$col] ?? '') : ($row[$col] ?? ($f['default'] ?? ''));
        $hint = !empty($f['hint']) ? '<small>' . e($f['hint']) . '</small>' : '';
        $req = !empty($f['required']) ? ' required' : '';
        if ($t === 'flag') {
            $on = $posted !== null ? isset($posted[$col]) : (bool) $val;
            echo '<label class="check"><input type="checkbox" name="' . e($col) . '" value="1"' . ($on ? ' checked' : '') . '> ' . e($f['label']) . $hint . '</label>';
        } elseif ($t === 'textarea') {
            echo '<label>' . e($f['label']) . $hint . '<textarea name="' . e($col) . '">' . e((string) $val) . '</textarea></label>';
        } elseif ($t === 'image') {
            $cur = (int) ($row['image_id'] ?? 0); $legacy = $row && !$cur && !empty($row['image']);
            echo '<div class="field"><strong>' . e($f['label']) . '</strong>' . $hint;
            if ($cur) { echo '<img class="thumb" src="media-img.php?id=' . $cur . '" alt="">'; }
            elseif ($legacy) { echo '<p class="muted">ตอนนี้ใช้รูปเดิมของเว็บ (ไม่ต้องเลือกใหม่ถ้าไม่ต้องการเปลี่ยน)</p>'; }
            echo '<label class="check"><input type="radio" name="' . e($col) . '" value="keep" checked> ' . ($cur || $legacy ? 'ใช้รูปนี้ต่อ' : 'ยังไม่ใส่รูป') . '</label>';
            if ($cur || $legacy) { echo '<label class="check"><input type="radio" name="' . e($col) . '" value="none"> เอารูปออก</label>'; }
            echo '<label>อัปโหลดรูปใหม่<input type="file" name="' . e($col) . '_file" accept="image/*"></label>';
            echo '<details><summary>หรือเลือกจากคลังรูป</summary><div>'; admin_media_pics($col . '_pick', null); echo '</div></details></div>';
        } else {
            $type = $t === 'date' ? 'date' : 'text';
            echo '<label>' . e($f['label']) . $hint . '<input type="' . $type . '" name="' . e($col) . '" value="' . e((string) $val) . '" maxlength="' . (int) ($f['max'] ?? 500) . '"' . $req . '></label>';
        }
    }
    echo '<button class="btn" type="submit">' . e($submit) . '</button></form>';
}

// One row of a list: thumbnail + title + status + move/hide/delete buttons, with the edit form folded underneath.
function admin_entity_row(string $action, array $spec, array $r, string $title, bool $first, bool $last, array $hidden = [], ?array $posted = null, string $sub = ''): void {
    echo '<div class="row">';
    echo '<p><strong>' . e($title) . '</strong> ' . ($r['is_public'] ? '<span class="tag tag--live">แสดงบนเว็บ</span>' : '<span class="tag">ซ่อนอยู่</span>') . ($sub !== '' ? '<br><span class="muted">' . e($sub) . '</span>' : '') . '</p>';
    if (!empty($r['image_id'])) { echo '<img class="thumb thumb--small" src="media-img.php?id=' . (int) $r['image_id'] . '" alt="">'; }
    echo '<div class="inline">';
    foreach ([['top', 'บนสุด', $first], ['up', '↑ ขึ้น', $first], ['down', '↓ ลง', $last]] as [$op, $label, $off]) {
        if ($off) { continue; }
        admin_form_open($action, 'class="inline"'); foreach ($hidden as $k => $val) { echo '<input type="hidden" name="' . e($k) . '" value="' . e($val) . '">'; }
        echo '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="btn btn--small btn--ghost" name="op" value="' . $op . '">' . $label . '</button></form>';
    }
    admin_form_open($action, 'class="inline"'); foreach ($hidden as $k => $val) { echo '<input type="hidden" name="' . e($k) . '" value="' . e($val) . '">'; }
    echo '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="btn btn--small btn--ghost" name="op" value="' . ($r['is_public'] ? 'hide' : 'show') . '">' . ($r['is_public'] ? 'ซ่อนจากเว็บ' : 'แสดงบนเว็บ') . '</button></form>';
    admin_form_open($action, 'class="inline" data-confirm="ลบรายการนี้ถาวร?"'); foreach ($hidden as $k => $val) { echo '<input type="hidden" name="' . e($k) . '" value="' . e($val) . '">'; }
    echo '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="btn btn--small btn--danger" name="op" value="delete">ลบ</button></form></div>';
    echo '<details class="edit"' . ($posted !== null ? ' open' : '') . '><summary>แก้ไขรายละเอียด</summary><div>';
    admin_entity_form($action, $spec, $r, $hidden, 'บันทึกการแก้ไข', $posted);
    echo '</div></details></div>';
}
