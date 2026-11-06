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
            'area' => 'ประเทศ', 'item' => 'สถาบัน', 'zoom' => true,
            'map_label' => 'แผนที่โลกแสดงประเทศที่มีเครือข่ายวิจัย', 'list_title' => 'เครือข่ายวิจัยทั้งหมด',
        ];
    }
    $names = []; $regionOf = [];
    foreach (require __DIR__ . '/../config/thai-provinces.php' as $code => $province) { $names[$code] = $province[0]; $regionOf[$code] = $province[1]; }
    return $cache[$mode] = [
        'dataset' => 'community', 'field' => 'province_code', 'svg' => 'assets/maps/thailand.svg', 'page' => 'network-community.php',
        'names' => $names, 'region_of' => $regionOf, 'region_labels' => $labels['thailand'], 'all_regions' => true,
        'area' => 'จังหวัด', 'item' => 'โครงการ', 'zoom' => false,
        'map_label' => 'แผนที่ประเทศไทยแสดงจังหวัดที่มีงานนวัตกรรมสู่ชุมชน', 'list_title' => 'โครงการนวัตกรรมสู่ชุมชนทั้งหมด',
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
        $meta = array_filter([(string) ($item['community'] ?? ''), !empty($item['year']) ? 'พ.ศ. ' . (int) $item['year'] : '']);
        if ($meta) { $html .= '<p class="netitem__sub">' . e(implode(' · ', $meta)) . '</p>'; }
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

// Everything below the hero: counts, map with tools and legend, detail panel, and the full text list.
function render_network_page(string $mode): void {
    $cfg = network_config($mode);
    $areas = network_areas($mode);
    $items = array_sum(array_map(static fn (array $a): int => count($a['items']), $areas));
    $empty = $areas === [];
    $regionsWithData = array_values(array_unique(array_filter(array_column($areas, 'region'))));
    $regions = array_filter($cfg['region_labels'], static fn ($slug) => $cfg['all_regions'] || in_array($slug, $regionsWithData, true), ARRAY_FILTER_USE_KEY);
    ?>
<section class="netintro"><div class="container">
<?php if ($empty): ?>
  <p class="netintro__empty" role="status">กำลังรวบรวมข้อมูลเครือข่าย</p>
<?php else: ?>
  <p class="netstats"><span class="netstats__lead">เครือข่ายใน</span> <span class="item-count"><strong><?= count($areas) ?></strong> <?= e($cfg['area']) ?></span> <span class="item-count"><strong><?= $items ?></strong> <?= e($cfg['item']) ?></span></p>
<?php endif; ?>
</div></section>
<section class="netmap<?= $empty ? ' is-empty' : '' ?>" data-netmap="<?= e($mode) ?>" data-unit="<?= e($cfg['item']) ?>" aria-label="<?= e($cfg['map_label']) ?>"><div class="container netmap__layout">
  <div class="netmap__stage">
<?php if (!$empty): ?>
    <div class="netmap__tools" hidden>
<?php if ($regions): ?>      <div class="netmap__regions" role="group" aria-label="เลือกพื้นที่ที่จะดู"><button class="netmap__chip is-on" type="button" data-region="" aria-pressed="true">ทั้งหมด</button><?php foreach ($regions as $slug => $label): ?><button class="netmap__chip" type="button" data-region="<?= e($slug) ?>" aria-pressed="false"><?= e($label) ?></button><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($cfg['zoom']): ?>      <div class="netmap__zoom" role="group" aria-label="ซูมแผนที่"><button class="netmap__btn" type="button" data-zoom="in" aria-label="ซูมเข้า">+</button><button class="netmap__btn" type="button" data-zoom="out" aria-label="ซูมออก">−</button><button class="netmap__btn netmap__btn--text" type="button" data-zoom="reset">รีเซ็ต</button></div>
<?php endif; ?>
    </div>
<?php endif; ?>
    <div class="netmap__canvas"><?= network_svg($mode, $areas) ?><div class="netmap__tip" role="tooltip" hidden></div></div>
<?php if (!$empty): ?>
    <ul class="netmap__legend" aria-label="คำอธิบายสีบนแผนที่"><li><span class="netmap__swatch is-l1"></span>1 <?= e($cfg['item']) ?></li><li><span class="netmap__swatch is-l2"></span>2</li><li><span class="netmap__swatch is-l3"></span>3</li><li><span class="netmap__swatch is-l4"></span>4 ขึ้นไป</li><li><span class="netmap__swatch is-none"></span>ยังไม่มีข้อมูล</li></ul>
<?php endif; ?>
  </div>
<?php if (!$empty): ?>
  <aside class="netmap__panel" id="netmap-panel" aria-label="รายละเอียดของ<?= e($cfg['area']) ?>ที่เลือก" tabindex="-1">
    <button class="netmap__close" type="button" aria-label="ปิดรายละเอียด"><span aria-hidden="true">×</span></button>
    <p class="netmap__hint">เลือก<?= e($cfg['area']) ?>สีม่วงบนแผนที่ เพื่อดูรายละเอียด</p>
    <div class="netmap__detail" aria-live="polite"></div>
  </aside>
<?php endif; ?>
</div></section>
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
