<?php
// Bringing the international network's content into the database from the admin, without FTP or phpMyAdmin
// (admin/network-import.php):
// - network_import_package(): a package made outside the site, one zip (or a JSON file plus pictures) holding
//   network-import.json and its pictures. Pictures go into the media library like any upload; partners, activities and
//   photos are added or filled in. A package may be split over several uploads: each carries the whole JSON and some of
//   the pictures, and running it again is harmless.
// - network_import_folder(): pictures the rows point to by path in the site's folder (assets/images/...) are moved
//   into the media library, so they are edited, backed up and removed like every other picture.
// Table structure still comes from phpMyAdmin (database/updates); the admin's database account cannot change it.
require_once __DIR__ . '/media-lib.php';
require_once dirname(__DIR__) . '/network.php';

const NETWORK_IMPORT_DIRECTIONS = ['outbound', 'inbound', 'joint'];
const NETWORK_IMPORT_FOLDER_BATCH = 15;   // pictures re-encoded per click when moving from the folder (GD is slow)

// A php.ini size such as 8M or 64K in bytes; 0 means no limit, read as a large number.
function admin_ini_bytes(string $key): int {
    $v = trim((string) ini_get($key));
    $n = (int) $v;
    $unit = strtolower(substr($v, -1));
    $n *= ['g' => 1073741824, 'm' => 1048576, 'k' => 1024][$unit] ?? 1;
    return $n > 0 ? $n : PHP_INT_MAX;
}

// '' when every table and column the import writes exists; otherwise what to import first, in Thai.
function network_import_ready(): string {
    try {
        admin_db()->query('SELECT image_id, image, tags, summary, people FROM network_partners LIMIT 0');
        admin_db()->query('SELECT id FROM network_activities LIMIT 0');
        admin_db()->query('SELECT activity_id, in_strip, image_id, image FROM network_photos LIMIT 0');
    } catch (PDOException $e) {
        return 'ฐานข้อมูลยังไม่มีตารางหรือคอลัมน์ที่ต้องใช้ ให้นำเข้า database/updates/2026-11-16-01-network-tags.sql, 2026-11-16-03-network-photos.sql และ 2026-11-16-05-network-activities.sql ด้วย phpMyAdmin ก่อน';
    }
    return '';
}

// A path the rows may hold for a picture in the site's own folder, checked like media_image_src() does.
function network_import_local_path(?string $path): string {
    $path = (string) $path;
    if (!preg_match('~\Aassets/images/[a-zA-Z0-9_/-]+\.(?:png|jpe?g|webp)\z~', $path)) { return ''; }
    $full = dirname(__DIR__, 2) . '/' . $path;
    return is_file($full) ? $full : '';
}

// Stores picture bytes in the media library (same bytes are stored once). Returns [id, error].
function network_import_store(string $bytes, string $name, string $alt): array {
    $prep = admin_media_prepare_bytes($bytes);
    if (is_string($prep)) { return [0, $name . ': ' . $prep]; }
    try {
        return [admin_media_store($prep, $name, $alt, (int) (auth_user()['id'] ?? 0) ?: null), ''];
    } catch (Throwable $e) {
        error_log('edu2impact network import: ' . $e->getMessage());
        return [0, $name . ': ฐานข้อมูลไม่รับไฟล์ (อาจใหญ่เกินไป)'];
    }
}

// Files sent with the form: a zip (field "package") and/or loose files (field "files[]"). Returns
// [json text or null, [basename => bytes], [errors]]. Only the base name of a file counts, so folders in the zip do not.
function network_import_collect(): array {
    $json = null; $pictures = []; $errors = [];
    $take = static function (string $name, string $bytes) use (&$json, &$pictures): void {
        $base = basename(str_replace('\\', '/', $name));
        if ($base === '' || $base[0] === '.') { return; }
        if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) === 'json') { $json = $bytes; return; }
        if (preg_match('~\.(?:webp|jpe?g|png)\z~i', $base)) { $pictures[$base] = $bytes; }
    };
    $zip = $_FILES['package'] ?? null;
    if ($zip && ($zip['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($zip['error'] ?? 0) !== UPLOAD_ERR_OK) {
            $errors[] = 'อัปโหลด zip ไม่สำเร็จ' . (in_array($zip['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับได้ (' . ini_get('upload_max_filesize') . ') ให้ใช้ชุดที่แบ่งเล็กลง' : '');
        } elseif (!class_exists('ZipArchive')) {
            $errors[] = 'เซิร์ฟเวอร์นี้แตกไฟล์ zip ไม่ได้ ให้แตกไฟล์ในเครื่องก่อน แล้วเลือกไฟล์ JSON และรูปทั้งหมดในช่องด้านล่างแทน';
        } elseif (is_uploaded_file($zip['tmp_name']) || defined('ADMIN_TESTING')) {
            $archive = new ZipArchive();
            if ($archive->open($zip['tmp_name']) !== true) {
                $errors[] = 'เปิดไฟล์ zip ไม่ได้';
            } else {
                for ($i = 0; $i < $archive->numFiles; $i++) {
                    $stat = $archive->statIndex($i);
                    if (!$stat || str_ends_with($stat['name'], '/') || $stat['size'] > ADMIN_UPLOAD_MAX) { continue; }
                    $bytes = $archive->getFromIndex($i);
                    if (is_string($bytes)) { $take($stat['name'], $bytes); }
                }
                $archive->close();
            }
        }
    }
    $loose = $_FILES['files'] ?? null;
    if ($loose && is_array($loose['name'] ?? null)) {
        foreach ($loose['name'] as $i => $name) {
            if (($loose['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
            if (!is_uploaded_file($loose['tmp_name'][$i]) && !defined('ADMIN_TESTING')) { continue; }
            $bytes = file_get_contents($loose['tmp_name'][$i]);
            if (is_string($bytes)) { $take((string) $name, $bytes); }
        }
    }
    return [$json, $pictures, $errors];
}

// Runs one package. Returns a report: counts of what changed, pictures still missing, errors.
function network_import_package(string $jsonText, array $pictures): array {
    $r = ['partners_new' => 0, 'partners_filled' => 0, 'activities_new' => 0, 'photos_new' => 0, 'photos_moved' => 0, 'photos_updated' => 0, 'cards' => 0, 'waiting' => [], 'errors' => []];
    $data = json_decode($jsonText, true);
    if (!is_array($data) || ($data['format'] ?? '') !== 'kuedu2impact-network' ) { $r['errors'][] = 'ไฟล์ JSON ไม่ใช่ชุดข้อมูลเครือข่าย (ต้องมี "format": "kuedu2impact-network")'; return $r; }
    $db = admin_db();
    @set_time_limit(300);
    $countries = require dirname(__DIR__, 2) . '/config/countries.php';
    $tagsKnown = require dirname(__DIR__, 2) . '/config/network-tags.php';
    $mediaIds = [];   // picture name => media id, stored at most once per run
    $picture = static function (string $file, string $alt) use (&$mediaIds, $pictures, &$r): int {
        if (isset($mediaIds[$file])) { return $mediaIds[$file]; }
        $bytes = $pictures[$file] ?? null;
        if ($bytes === null) {
            $local = network_import_local_path('assets/images/network/' . $file);   // already on the server from an earlier upload
            $bytes = $local !== '' ? (string) file_get_contents($local) : null;
        }
        if ($bytes === null) {
            // Sent in an earlier part of the package: the library already has it under this name.
            $known = admin_db()->prepare('SELECT id FROM media WHERE original_name = ? AND og_of IS NULL ORDER BY id DESC LIMIT 1');
            $known->execute([$file]);
            if ($id = (int) $known->fetchColumn()) { return $mediaIds[$file] = $id; }
            $r['waiting'][$file] = true;
            return 0;
        }
        [$id, $error] = network_import_store($bytes, $file, $alt);
        if ($error !== '') { $r['errors'][] = $error; return 0; }
        return $mediaIds[$file] = $id;
    };

    // Partners: added when missing (matched by name); for those already there only empty fields are filled in, so
    // what was edited in the admin stays. The card picture is set only when the partner has none in the library.
    $partnerId = [];
    $find = $db->prepare('SELECT * FROM network_partners WHERE institution = ? LIMIT 1');
    foreach ((array) ($data['partners'] ?? []) as $p) {
        $name = trim((string) ($p['institution'] ?? ''));
        $code = strtoupper(trim((string) ($p['country_code'] ?? '')));
        if ($name === '') { continue; }
        $find->execute([$name]);
        $row = $find->fetch();
        $tags = implode(',', array_values(array_filter(array_map('trim', explode(',', (string) ($p['tags'] ?? ''))), static fn ($t): bool => isset($tagsKnown[$t]))));
        $fields = ['institution_th' => $p['institution_th'] ?? null, 'tags' => $tags !== '' ? $tags : null, 'summary' => $p['summary'] ?? null, 'people' => $p['people'] ?? null, 'url' => $p['url'] ?? null];
        if (!$row) {
            $countryName = $countries[$code][0] ?? (NETWORK_NO_MAP[$code][0] ?? '');
            if ($countryName === '') { $r['errors'][] = $name . ': รหัสประเทศ ' . $code . ' ไม่ถูกต้อง'; continue; }
            $ins = $db->prepare('INSERT INTO network_partners (country_code, country_name_th, institution, institution_th, tags, summary, people, url, is_public, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
            $ins->execute([$code, $countryName, $name, $fields['institution_th'], $fields['tags'], $fields['summary'], $fields['people'], $fields['url'], (int) ($p['sort_order'] ?? 900)]);
            $find->execute([$name]);
            $row = $find->fetch();
            $r['partners_new']++;
        } else {
            $set = []; $vals = [];
            foreach ($fields as $col => $val) {
                if ($val !== null && $val !== '' && ($row[$col] === null || $row[$col] === '')) { $set[] = "$col = ?"; $vals[] = $val; }
            }
            if ($set) { $vals[] = $row['id']; $db->prepare('UPDATE network_partners SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals); $r['partners_filled']++; }
        }
        $partnerId[$name] = (int) $row['id'];
        $card = trim((string) ($p['card_image'] ?? ''));
        if ($card !== '' && empty($row['image_id'])) {
            $id = $picture($card, $name);
            if ($id) { $db->prepare('UPDATE network_partners SET image_id = ?, image = NULL WHERE id = ?')->execute([$id, $row['id']]); $r['cards']++; }
        }
    }

    // Activities: added when the partner has no activity of that title yet; existing ones are left as edited.
    $activityId = [];
    $findAct = $db->prepare('SELECT id FROM network_activities WHERE partner_id = ? AND title = ? LIMIT 1');
    foreach ((array) ($data['activities'] ?? []) as $a) {
        $pid = $partnerId[(string) ($a['institution'] ?? '')] ?? 0;
        $title = trim((string) ($a['title'] ?? ''));
        if (!$pid || $title === '') { continue; }
        $findAct->execute([$pid, $title]);
        $id = (int) $findAct->fetchColumn();
        if (!$id) {
            $dir = in_array($a['direction'] ?? '', NETWORK_IMPORT_DIRECTIONS, true) ? $a['direction'] : 'joint';
            $db->prepare('INSERT INTO network_activities (partner_id, direction, period, title, body, is_public, sort_order) VALUES (?, ?, ?, ?, ?, 1, ?)')
               ->execute([$pid, $dir, ($a['period'] ?? '') !== '' ? $a['period'] : null, $title, $a['body'] ?? null, (int) ($a['sort_order'] ?? 0)]);
            $id = (int) $db->lastInsertId();
            $r['activities_new']++;
        }
        $activityId[$pid . '|' . $title] = $id;
    }

    // Photos: a row that still points to the same file in the folder is moved to the library; a row already holding the
    // same library picture keeps what it has (an empty activity is filled in); otherwise a new row.
    $byPath = $db->prepare('SELECT id FROM network_photos WHERE image = ? AND image_id IS NULL LIMIT 1');
    $byMedia = $db->prepare('SELECT id FROM network_photos WHERE image_id = ? LIMIT 1');
    foreach ((array) ($data['photos'] ?? []) as $ph) {
        $file = basename((string) ($ph['file'] ?? ''));
        if ($file === '') { continue; }
        $inst = (string) ($ph['institution'] ?? '');
        $act = $activityId[($partnerId[$inst] ?? 0) . '|' . (string) ($ph['activity'] ?? '')] ?? null;
        $caption = mb_substr(trim((string) ($ph['caption'] ?? $file)), 0, 300);
        $id = $picture($file, $caption);
        if (!$id) { continue; }
        $vals = [$act, $caption, !empty($ph['in_strip']) ? 1 : 0, (int) ($ph['sort_order'] ?? 0)];
        $byPath->execute(['assets/images/network/' . $file]);
        if ($row = (int) $byPath->fetchColumn()) {
            $db->prepare('UPDATE network_photos SET image_id = ?, image = NULL, activity_id = ?, caption = ?, in_strip = ?, sort_order = ? WHERE id = ?')->execute(array_merge([$id], $vals, [$row]));
            $r['photos_moved']++;
            continue;
        }
        $byMedia->execute([$id]);
        if ($row = (int) $byMedia->fetchColumn()) {
            // Already in: only an activity it lacks is filled in; a caption edited in the admin stays.
            $db->prepare('UPDATE network_photos SET activity_id = COALESCE(activity_id, ?) WHERE id = ?')->execute([$act, $row]);
            $r['photos_updated']++;
            continue;
        }
        $db->prepare('INSERT INTO network_photos (image_id, activity_id, caption, in_strip, sort_order, is_public) VALUES (?, ?, ?, ?, ?, 1)')->execute(array_merge([$id], $vals));
        $r['photos_new']++;
    }
    $r['waiting'] = array_keys($r['waiting']);
    return $r;
}

// Rows still pointing to a picture in the site's folder: [table, id, path, label].
function network_import_folder_rows(): array {
    $rows = [];
    foreach (['network_partners' => 'institution', 'network_photos' => 'caption'] as $table => $label) {
        try {
            foreach (admin_db()->query("SELECT id, image, $label AS label FROM $table WHERE image_id IS NULL AND image IS NOT NULL AND image <> '' ORDER BY id") as $row) {
                $rows[] = [$table, (int) $row['id'], (string) $row['image'], (string) $row['label']];
            }
        } catch (PDOException $e) { /* column not there yet */ }
    }
    return $rows;
}

// Moves up to NETWORK_IMPORT_FOLDER_BATCH folder pictures into the media library. Returns [moved, missing paths, errors].
function network_import_folder(): array {
    @set_time_limit(300);
    $moved = 0; $missing = []; $errors = [];
    foreach (network_import_folder_rows() as [$table, $id, $path, $label]) {
        if ($moved + count($errors) >= NETWORK_IMPORT_FOLDER_BATCH) { break; }
        $full = network_import_local_path($path);
        if ($full === '') { $missing[] = $path; continue; }
        [$mediaId, $error] = network_import_store((string) file_get_contents($full), basename($path), $label);
        if ($error !== '') { $errors[] = $error; continue; }
        admin_db()->prepare("UPDATE $table SET image_id = ?, image = NULL WHERE id = ?")->execute([$mediaId, $id]);
        $moved++;
    }
    return [$moved, $missing, $errors];
}
