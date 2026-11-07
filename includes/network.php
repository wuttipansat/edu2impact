<?php
// Data and markup for the two network maps: network-international.php (world, data/network.json or network_partners)
// and network-community.php (Thailand, data/community.json or community_projects). Behaviour is in assets/js/network-map.js.
// The SVG maps (assets/maps/*.svg) are inlined so the page works without a request for them and under the strict CSP.
// Without JavaScript the map is coloured and the full text list below it carries all the content.

// Settings of one map. $mode is 'world' or 'thailand'.
function network_config(string $mode): array {
    static $cache = [];
    if (isset($cache[$mode])) { return $cache[$mode]; }
    $labels = require __DIR__ . '/../config/network-regions.php';
    if ($mode === 'world') {
        $names = []; $regionOf = [];
        foreach (require __DIR__ . '/../config/countries.php' as $code => $country) { $names[$code] = $country[0]; $regionOf[$code] = $country[2]; }
        return $cache[$mode] = [
            'dataset' => 'network', 'field' => 'country_code', 'svg' => 'assets/maps/world.svg', 'page' => 'network-international.php',
            'names' => $names, 'region_of' => $regionOf, 'region_labels' => $labels['world'], 'all_regions' => false,
            'area' => 'ประเทศ', 'item' => 'สถาบัน', 'lead' => 'เครือข่ายใน', 'stat_area' => 'ประเทศ', 'stat_item' => 'สถาบัน', 'hint_mouse' => '', 'hint_touch' => '',
            'map_label' => 'แผนที่โลกแสดงประเทศที่มีเครือข่ายวิจัยและความร่วมมือ', 'list_title' => 'เครือข่ายวิจัยและความร่วมมือทั้งหมด',
        ];
    }
    $names = []; $regionOf = [];
    foreach (require __DIR__ . '/../config/thai-provinces.php' as $code => $province) { $names[$code] = $province[0]; $regionOf[$code] = $province[1]; }
    return $cache[$mode] = [
        'dataset' => 'community', 'field' => 'province_code', 'svg' => 'assets/maps/thailand.svg', 'page' => 'network-community.php',
        'names' => $names, 'region_of' => $regionOf, 'region_labels' => $labels['thailand'], 'all_regions' => true,
        'area' => 'จังหวัด', 'item' => 'โครงการ', 'lead' => 'ลงพื้นที่แล้ว', 'stat_area' => 'จังหวัด', 'stat_item' => 'โครงการ', 'hint_mouse' => 'คลิกจังหวัดที่สว่างเพื่อดูโครงการ', 'hint_touch' => 'แตะจังหวัดที่สว่างเพื่อดูโครงการ',
        'map_label' => 'แผนที่ประเทศไทยแสดงจังหวัดที่มีงานวิจัยและนวัตกรรมเพื่อชุมชนและสังคม', 'list_title' => 'โครงการวิจัยและนวัตกรรมเพื่อชุมชนและสังคมทั้งหมด',
    ];
}

// Records grouped by area: code => ['code', 'name', 'region', 'items' => [...]]. Only areas that have a record and a
// known code appear. Areas are sorted by Thai name (Collator when the intl extension is there).
function network_areas(string $mode): array {
    static $cache = [];
    if (isset($cache[$mode])) { return $cache[$mode]; }
    $cfg = network_config($mode);
    $areas = [];
    foreach (load_data($cfg['dataset']) as $record) {
        $code = is_array($record) ? (string) ($record[$cfg['field']] ?? '') : '';
        if (!isset($cfg['names'][$code])) { continue; }
        $areas[$code] ??= ['code' => $code, 'name' => $cfg['names'][$code], 'region' => $cfg['region_of'][$code] ?? '', 'items' => []];
        $areas[$code]['items'][] = $record;
    }
    $collator = class_exists('Collator') ? new Collator('th_TH') : null;
    uasort($areas, static fn (array $a, array $b): int => $collator ? (int) $collator->compare($a['name'], $b['name']) : strcmp($a['name'], $b['name']));
    return $cache[$mode] = $areas;
}

// Cards for the posts a record links to ("related": [{"kind", "id"}]); links to missing or hidden posts are skipped.
function network_related(array $record): array {
    $cards = [];
    foreach ((array) ($record['related'] ?? []) as $link) {
        $kind = is_array($link) ? (string) ($link['kind'] ?? '') : '';
        if (kind_label($kind) === '') { continue; }
        $found = find_record(load_data($kind), (string) ($link['id'] ?? ''));
        if ($found) { $cards[] = ['label' => kind_label($kind), 'title' => (string) $found['title'], 'url' => detail_url($kind, $found['id'])]; }
    }
    return $cards;
}

function network_item_html(string $mode, array $item): string {
    $html = '<li class="netitem">';
    if ($mode === 'world') {
        $html .= '<h4 class="netitem__title">' . e($item['institution']) . '</h4>';
        if (!empty($item['institution_th'])) { $html .= '<p class="netitem__sub">' . e($item['institution_th']) . '</p>'; }
        if (!empty($item['description'])) { $html .= '<p class="netitem__text">' . e($item['description']) . '</p>'; }
        $url = (string) ($item['url'] ?? '');
        if (preg_match('~\Ahttps?://~i', $url)) { $html .= '<p class="netitem__link"><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">เว็บไซต์ของสถาบัน<span class="visually-hidden"> ' . e($item['institution']) . ' (เปิดแท็บใหม่)</span> ' . icon_arrow('up-right') . '</a></p>'; }
    } else {
        $src = image_path($item);
        if ($src !== '') {
            [$w, $h] = image_size($src);
            $html .= '<img class="netitem__img" src="' . e($src) . '"' . ($w && $h ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : '') . ' alt="" loading="lazy">';
        }
        $html .= '<h4 class="netitem__title">' . e($item['title']) . '</h4>';
        if (!empty($item['community'])) { $html .= '<p class="netitem__sub">' . e($item['community']) . '</p>'; }
        if (!empty($item['year'])) { $html .= '<p class="netitem__year">พ.ศ. ' . (int) $item['year'] . '</p>'; }
        if (!empty($item['summary'])) { $html .= '<p class="netitem__text">' . e($item['summary']) . '</p>'; }
    }
    $cards = network_related($item);
    if ($cards) {
        $html .= '<ul class="netitem__related" aria-label="เนื้อหาที่เกี่ยวข้อง">';
        foreach ($cards as $card) { $html .= '<li><a href="' . e($card['url']) . '"><span class="netitem__kind">' . e($card['label']) . '</span> ' . e($card['title']) . '</a></li>'; }
        $html .= '</ul>';
    }
    return $html . '</li>';
}

// Colour step 1..4 from the number of records in an area (4 = four or more).
function network_level(int $count): int { return max(1, min(4, $count)); }

// The inline SVG: areas with records get classes and the data JavaScript needs; every province gets its region.
function network_svg(string $mode, array $areas): string {
    $cfg = network_config($mode);
    $svg = @file_get_contents(__DIR__ . '/../' . $cfg['svg']);
    if ($svg === false) { error_log('edu2impact: ' . $cfg['svg'] . ' cannot be read'); return ''; }
    $svg = preg_replace('~\A<svg ~', '<svg role="group" aria-label="' . e($cfg['map_label']) . '" ', $svg, 1) ?? $svg;
    return preg_replace_callback('~<path data-code="([A-Z0-9-]+)"( data-name="[^"]*")?( data-region="[^"]*")?~', static function (array $m) use ($mode, $cfg, $areas): string {
        $code = $m[1]; $name = $m[2] ?? ''; $region = $m[3] ?? '';
        if ($mode === 'thailand') { $region = ' data-region="' . e($cfg['region_of'][$code] ?? '') . '"'; }
        if (!isset($areas[$code])) { return '<path data-code="' . $code . '"' . $name . $region; }
        $count = count($areas[$code]['items']);
        return '<path class="is-active is-l' . network_level($count) . '" data-code="' . $code . '"' . $name . $region . ' data-th="' . e($areas[$code]['name']) . '" data-count="' . $count . '"';
    }, $svg) ?? $svg;
}

// Everything below the hero: a full-width map stage (counts, region buttons, zoom, legend and the detail panel float
// over the map) and the full text list under it. Without JavaScript the stage is just the coloured map.
function render_network_page(string $mode): void {
    $cfg = network_config($mode);
    $areas = network_areas($mode);
    $items = array_sum(array_map(static fn (array $a): int => count($a['items']), $areas));
    $empty = $areas === [];
    // Region buttons: only regions that have records, each with its number of records; none unless there are two or more.
    $regionCounts = [];
    foreach ($areas as $area) { if ($area['region'] !== '') { $regionCounts[$area['region']] = ($regionCounts[$area['region']] ?? 0) + count($area['items']); } }
    $regions = array_filter($cfg['region_labels'], static fn ($slug) => isset($regionCounts[$slug]), ARRAY_FILTER_USE_KEY);
    if (count($regions) < 2) { $regions = []; }
    ?>
<section class="netmap netmap--<?= e($mode) ?><?= $empty ? ' is-empty' : '' ?>" data-netmap="<?= e($mode) ?>" data-unit="<?= e($cfg['item']) ?>" aria-label="<?= e($cfg['map_label']) ?>">
  <div class="netmap__stage">
    <div class="netmap__canvas"><?= network_svg($mode, $areas) ?><div class="netmap__tip" role="tooltip" hidden></div></div>
<?php if ($empty): ?>
    <div class="netmap__empty" role="status"><p class="netmap__empty-title">กำลังรวบรวมข้อมูลเครือข่าย</p><p>เมื่อเพิ่มรายการแรกแล้ว <?= e($cfg['area']) ?>นั้นจะสว่างขึ้นบนแผนที่นี้</p></div>
<?php else: ?>
    <div class="netmap__hud">
      <p class="visually-hidden"><?= e($cfg['lead']) ?> <?= count($areas) ?> <?= e($cfg['area']) ?> <?= $items ?> <?= e($cfg['item']) ?></p>
      <dl class="netmap__stats" aria-hidden="true">
        <div class="netmap__stat"><dt><?= e($cfg['stat_area']) ?></dt><dd class="netmap__num" data-count="<?= count($areas) ?>"><?= count($areas) ?></dd></div>
        <div class="netmap__stat"><dt><?= e($cfg['stat_item']) ?></dt><dd class="netmap__num" data-count="<?= $items ?>"><?= $items ?></dd></div>
      </dl>
      <ul class="netmap__legend" aria-label="คำอธิบายสีบนแผนที่">
        <li><span class="netmap__origin-key" aria-hidden="true"></span>มหาวิทยาลัยเกษตรศาสตร์ บางเขน</li>
      </ul>
    </div>
    <div class="netmap__tools netmap__zoom" role="group" aria-label="ซูมแผนที่" hidden><button class="netmap__btn" type="button" data-zoom="in" aria-label="ซูมเข้า">+</button><button class="netmap__btn" type="button" data-zoom="out" aria-label="ซูมออก">−</button><button class="netmap__btn netmap__btn--text" type="button" data-zoom="reset">ดูทั้งหมด</button></div>
    <div class="netmap__dock">
<?php if ($regions): ?>
      <div class="netmap__tools netmap__regions" role="group" aria-label="เลือกพื้นที่ที่จะดู" hidden><button class="netmap__chip is-on" type="button" data-region="" aria-pressed="true">ทั้งหมด <span class="netmap__chip-n"><?= $items ?></span></button><?php foreach ($regions as $slug => $label): ?><button class="netmap__chip" type="button" data-region="<?= e($slug) ?>" aria-pressed="false"><?= e($label) ?> <span class="netmap__chip-n"><?= (int) $regionCounts[$slug] ?></span></button><?php endforeach; ?></div>
<?php endif; ?>
    </div>
<?php if ($cfg['hint_mouse'] !== ''): ?>
    <p class="netmap__hint"><svg class="netmap__hint-icon netmap__hint-icon--mouse" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 3.5 11 20l2.4-6.6L20 11z"/></svg><svg class="netmap__hint-icon netmap__hint-icon--touch" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="2.4"/><circle cx="12" cy="12" r="7.5"/></svg><span class="netmap__hint-mouse"><?= e($cfg['hint_mouse']) ?></span><span class="netmap__hint-touch"><?= e($cfg['hint_touch']) ?></span></p>
<?php endif; ?>
    <aside class="netmap__panel" id="netmap-panel" aria-label="รายละเอียดของ<?= e($cfg['area']) ?>ที่เลือก" tabindex="-1">
      <button class="netmap__close" type="button" aria-label="ปิดรายละเอียด"><span aria-hidden="true">×</span></button>
      <div class="netmap__detail" aria-live="polite"></div>
    </aside>
<?php endif; ?>
  </div>
</section>
<?php if ($mode === 'world') { render_network_highlights(); } ?>
<?php if (!$empty): ?>
<section class="netlist" aria-labelledby="netlist-title"><div class="container">
  <h2 id="netlist-title"><?= e($cfg['list_title']) ?></h2>
  <div class="netlist__grid">
<?php foreach ($areas as $code => $area): $n = count($area['items']); ?>
    <section class="netarea" id="<?= e($code) ?>" data-name="<?= e($area['name']) ?>" aria-labelledby="<?= e($code) ?>-title">
      <h3 class="netarea__title" id="<?= e($code) ?>-title"><?= e($area['name']) ?> <span class="netarea__count"><?= $n ?> <?= e($cfg['item']) ?></span></h3>
      <ul class="netarea__items"><?php foreach ($area['items'] as $item) { echo network_item_html($mode, $item); } ?></ul>
    </section>
<?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>
<?php
}

// Two bands of short text with icons under the world map: how the partners work with the programme (kind "model", a
// numbered list) and what the programme and its students gain (kind "value"). Content is network_highlights /
// data/network-highlights.json. A band with no published row is left out, heading included.
function network_highlight_groups(): array {
    $groups = ['model' => [], 'value' => []];
    foreach (load_data('network-highlights') as $row) {
        if (!is_array($row) || !isset($groups[$row['kind'] ?? '']) || trim((string) ($row['title_th'] ?? '')) === '') { continue; }
        $groups[$row['kind']][] = $row;
    }
    return $groups;
}

function render_network_highlights(): void {
    require_once __DIR__ . '/network-icons.php';
    $groups = network_highlight_groups();
    if (!$groups['model'] && !$groups['value']) { return; }
    ?>
<div class="netinfo" id="netinfo">
<?php if ($groups['model']): ?>
  <section class="netband netband--models" aria-labelledby="netmodels-title"><div class="container netband__grid">
    <h2 id="netmodels-title">รูปแบบความร่วมมือ</h2>
    <ol class="netmodels">
<?php foreach ($groups['model'] as $i => $row): ?>
      <li class="netmodel" data-reveal><span class="netmodel__no" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span><span class="netmodel__text"><span class="netmodel__th"><?= e($row['title_th']) ?></span><?php if (!empty($row['title_en'])): ?><span class="netmodel__en" lang="en"><?= e($row['title_en']) ?></span><?php endif; ?></span><span class="netmodel__icon"><?= network_icon_svg((string) ($row['icon'] ?? ''), 24) ?></span></li>
<?php endforeach; ?>
    </ol>
  </div></section>
<?php endif; ?>
<?php if ($groups['value']): ?>
  <section class="netband netband--values" aria-labelledby="netvalues-title"><div class="container">
    <h2 id="netvalues-title">คุณค่าที่เกิดกับหลักสูตรและนิสิต</h2>
    <ol class="netvalues">
<?php foreach ($groups['value'] as $row): ?>
      <li class="netvalue" data-reveal><span class="netvalue__icon"><?= network_icon_svg((string) ($row['icon'] ?? ''), 32) ?></span><span class="netvalue__th"><?= e($row['title_th']) ?></span><?php if (!empty($row['title_en'])): ?><span class="netvalue__en" lang="en"><?= e($row['title_en']) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ol>
  </div></section>
<?php endif; ?>
</div>
<?php
}
