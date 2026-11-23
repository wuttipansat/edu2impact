<?php
// Data and markup for the two network maps: network-international.php (world, data/network.json or network_partners)
// and network-community.php (Thailand, data/community.json or community_projects). Behaviour is in assets/js/network-map.js.
// The SVG maps (assets/maps/*.svg) are inlined so the page works without a request for them and under the strict CSP.
// Without JavaScript the map is coloured and the full text list below it carries all the content.

// Codes for partners that are networks of several countries rather than one place: code => [name, line on the card].
// XA is from the ISO 3166 user-assigned range, so it never clashes with a real country.
const NETWORK_NO_MAP = ['XA' => ['อาเซียน', 'เครือข่าย 10 ประเทศอาเซียน']];

// Settings of one map. $mode is 'world' or 'thailand'.
function network_config(string $mode): array {
    static $cache = [];
    if (isset($cache[$mode])) { return $cache[$mode]; }
    $labels = require __DIR__ . '/../config/network-regions.php';
    if ($mode === 'world') {
        $names = []; $regionOf = [];
        foreach (require __DIR__ . '/../config/countries.php' as $code => $country) { $names[$code] = $country[0]; $regionOf[$code] = $country[2]; }
        // Networks of several countries (AsTEN) use a user-assigned code: a card in the deck, no place on the map.
        foreach (NETWORK_NO_MAP as $code => $label) { $names[$code] = $label[0]; $regionOf[$code] = ''; }
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
        // Heading over the one-school projects that link to no post (see network_programs()). The rows on the site now
        // all come from the คลังความรู้อาชีพ project (moveef.ku.ac.th); change it here if other one-school rows are added.
        'single_title' => 'คลังความรู้อาชีพ สำหรับโรงเรียนห่างไกล',
    ];
}

// The list under the map of Thailand, project first (network-community.php). Rows with the same title in two or more
// provinces are one programme run in many places (MOVE and Scaffolding with each province's Red Cross chapter): one
// block each, its provinces grouped by region. Rows whose title is used in one province only are one-school projects:
// they are grouped by the post they link to (its title is the heading), and the ones that link to nothing go under
// network_config()['single_title']. Everything is counted from the records; nothing is filled in.
// Returns ['programs' => [...], 'singles' => [...]], programmes with the most provinces first.
function network_programs(array $areas): array {
    $cfg = network_config('thailand');
    $byTitle = [];
    foreach ($areas as $code => $area) {
        foreach ($area['items'] as $item) {
            $title = trim((string) (preg_replace('~\s+~u', ' ', (string) ($item['title'] ?? '')) ?? ''));
            if ($title === '') { continue; }
            $byTitle[$title][] = ['code' => $code, 'area' => $area, 'item' => $item];
        }
    }
    $programs = []; $singles = [];
    foreach ($byTitle as $title => $rows) {
        $codes = array_unique(array_column($rows, 'code'));
        if (count($codes) < 2) {
            foreach ($rows as $row) {
                $post = network_related($row['item'])[0] ?? null;
                $key = $post ? $post['url'] : '';
                $singles[$key] ??= ['title' => $post ? $post['title'] : $cfg['single_title'], 'url' => $post ? $post['url'] : '', 'rows' => []];
                $singles[$key]['rows'][] = $row;
            }
            continue;
        }
        $places = []; $years = []; $partners = []; $summaries = []; $related = [];
        foreach ($rows as $row) {
            $code = $row['code']; $name = $row['area']['name'];
            $places[$code] ??= ['code' => $code, 'name' => $name, 'region' => $row['area']['region'], 'years' => []];
            $year = (int) ($row['item']['year'] ?? 0);
            if ($year > 0) { $places[$code]['years'][$year] = $year; $years[$year] = $year; }
            // "เหล่ากาชาดจังหวัดกระบี่" minus the province's name: the same partner in every province when all agree.
            $partners[trim(str_replace($name, '', (string) ($row['item']['community'] ?? '')))] = true;
            $summaries[trim((string) ($row['item']['summary'] ?? ''))] = true;
            foreach (network_related($row['item']) as $card) { $related[$card['url']] = $card; }
        }
        $regions = [];
        foreach ($cfg['region_labels'] as $slug => $label) {
            $in = array_filter($places, static fn (array $p): bool => $p['region'] === $slug);
            if ($in) { $regions[$slug] = ['label' => $label, 'places' => $in]; }
        }
        $partner = count($partners) === 1 ? (string) array_key_first($partners) : '';
        $summary = count($summaries) === 1 ? (string) array_key_first($summaries) : '';
        ksort($years);
        $programs[] = ['title' => $title, 'provinces' => count($places), 'years' => array_values($years), 'partner' => $partner,
            'summary' => $summary, 'related' => array_values($related), 'regions' => $regions];
    }
    usort($programs, static fn (array $a, array $b): int => $b['provinces'] <=> $a['provinces']);
    return ['programs' => $programs, 'singles' => array_values($singles)];
}

// "พ.ศ. 2565–2569", "พ.ศ. 2568" or '' from a sorted list of years.
function network_year_span(array $years): string {
    if (!$years) { return ''; }
    $first = reset($years); $last = end($years);
    return 'พ.ศ. ' . $first . ($last !== $first ? '–' . $last : '');
}

// A province name that opens it on the map. The first link to each province on the page also carries the id (#TH-50)
// so that the address still lands in the list without JavaScript; network-map.js takes the ids away.
function network_place_link(array $place, array &$anchored): string {
    $id = '';
    if (!isset($anchored[$place['code']])) { $anchored[$place['code']] = true; $id = ' id="' . e($place['code']) . '"'; }
    return '<a class="netprog__place"' . $id . ' href="#' . e($place['code']) . '" data-map="' . e($place['code']) . '">'
        . e($place['name']) . '<span class="visually-hidden"> (ดูบนแผนที่)</span></a>';
}

// Province outlines of assets/maps/thailand.svg as code => path data, for the small maps on the project cards.
function network_thai_shapes(): array {
    static $shapes = null;
    if ($shapes !== null) { return $shapes; }
    $shapes = [];
    $svg = @file_get_contents(__DIR__ . '/../assets/maps/thailand.svg');
    if ($svg === false) { return $shapes; }
    preg_match_all('~<path data-code="(TH-[0-9]+)"[^>]*? d="([^"]+)"~', $svg, $m, PREG_SET_ORDER);
    foreach ($m as $hit) { $shapes[$hit[1]] = $hit[2]; }
    return $shapes;
}

// Each province once, as <defs>, so a card's map is 77 short <use> tags instead of a second copy of the whole map.
function network_shape_defs(): string {
    $html = '<svg class="netprog__defs" width="0" height="0" aria-hidden="true" focusable="false"><defs>';
    foreach (network_thai_shapes() as $code => $d) { $html .= '<path id="tp-' . e($code) . '" d="' . e($d) . '"/>'; }
    return $html . '</defs></svg>';
}

// The small map of Thailand on a card: the provinces in $lit are bright. Decorative; the list beside it says the same.
function network_mini_map(array $lit): string {
    $html = '<svg class="netprog__map" viewBox="19.3 0 481.5 880" aria-hidden="true" focusable="false">';
    foreach (array_keys(network_thai_shapes()) as $code) {
        $html .= '<use href="#tp-' . e($code) . '" data-code="' . e($code) . '"' . (isset($lit[$code]) ? ' class="is-on"' : '') . '/>';
    }
    return $html . '</svg>';
}

// The project-first list under the map of Thailand: one card per project, its reach drawn on a small map. The
// per-province sections the big map's panel shows are printed after it, hidden (network-map.js copies one into the
// panel when a province is chosen).
function render_network_programs(array $areas): void {
    $cfg = network_config('thailand');
    ['programs' => $programs, 'singles' => $singles] = network_programs($areas);
    $anchored = [];
    $n = 0;
    ?>
<section class="netlist netlist--thailand" aria-labelledby="netlist-title"><div class="container">
  <div class="netlist__top"><h2 id="netlist-title"><?= e($cfg['list_title']) ?></h2></div>
  <?= network_shape_defs() ?>
  <div class="netprogs">
<?php foreach ($programs as $program): $n++; $span = network_year_span($program['years']);
    $lit = [];
    foreach ($program['regions'] as $region) { foreach ($region['places'] as $code => $place) { $lit[$code] = true; } }
    ?>
    <article class="netprog" aria-labelledby="netprog-<?= $n ?>">
      <div class="netprog__reach">
        <?= network_mini_map($lit) ?>
        <p class="netprog__count"><span class="netprog__big"><?= (int) $program['provinces'] ?></span> <?= e($cfg['area']) ?><?php if ($span !== ''): ?><span class="netprog__span"><?= e($span) ?></span><?php endif; ?></p>
      </div>
      <div class="netprog__body">
        <h3 class="netprog__title" id="netprog-<?= $n ?>"><?= e($program['title']) ?></h3>
<?php if ($program['partner'] !== ''): ?>
        <ul class="netitem__tags"><li>ร่วมกับ<?= e($program['partner']) ?></li></ul>
<?php endif; ?>
<?php if ($program['summary'] !== ''): ?>
        <p class="netitem__summary"><?= e($program['summary']) ?></p>
<?php endif; ?>
<?php if ($program['related']): ?>
        <ul class="netitem__related" aria-label="เนื้อหาที่เกี่ยวข้อง"><?php foreach ($program['related'] as $card): ?><li><a href="<?= e($card['url']) ?>"><span class="netitem__kind"><?= e($card['label']) ?></span> <?= e($card['title']) ?></a></li><?php endforeach; ?></ul>
<?php endif; ?>
        <div class="netprog__regions">
<?php foreach ($program['regions'] as $slug => $region): ?>
          <section class="netprog__region" aria-labelledby="netprog-<?= $n ?>-<?= e($slug) ?>">
            <h4 class="netprog__region-title" id="netprog-<?= $n ?>-<?= e($slug) ?>"><?= e($region['label']) ?> <span class="netprog__n"><?= count($region['places']) ?></span></h4>
            <ul class="netprog__places"><?php foreach ($region['places'] as $place): ksort($place['years']); ?><li><?= network_place_link($place, $anchored) ?><?php if ($place['years']): ?> <span class="netprog__yr"><?= e(implode(', ', $place['years'])) ?></span><?php endif; ?></li><?php endforeach; ?></ul>
          </section>
<?php endforeach; ?>
        </div>
      </div>
    </article>
<?php endforeach; ?>
<?php foreach ($singles as $group): $n++;
    $lit = array_fill_keys(array_column($group['rows'], 'code'), true);
    $years = array_values(array_unique(array_filter(array_map(static fn (array $r): int => (int) ($r['item']['year'] ?? 0), $group['rows']))));
    sort($years);
    $span = network_year_span($years);
    // Provinces in the order of the map's regions, then by name, so neighbouring schools sit together.
    $order = array_flip(array_keys($cfg['region_labels']));
    $rows = $group['rows'];
    $collator = class_exists('Collator') ? new Collator('th_TH') : null;
    usort($rows, static function (array $a, array $b) use ($order, $collator): int {
        $r = ($order[$a['area']['region']] ?? 9) <=> ($order[$b['area']['region']] ?? 9);
        return $r ?: ($collator ? (int) $collator->compare($a['area']['name'], $b['area']['name']) : strcmp($a['area']['name'], $b['area']['name']));
    });
    ?>
    <article class="netprog netprog--schools" aria-labelledby="netprog-<?= $n ?>">
      <div class="netprog__reach">
        <?= network_mini_map($lit) ?>
        <p class="netprog__count"><span class="netprog__big"><?= count($rows) ?></span> <?= e($cfg['item']) ?> ใน <?= count($lit) ?> <?= e($cfg['area']) ?><?php if ($span !== ''): ?><span class="netprog__span"><?= e($span) ?></span><?php endif; ?></p>
      </div>
      <div class="netprog__body">
        <h3 class="netprog__title" id="netprog-<?= $n ?>"><?php if ($group['url'] !== ''): ?><a class="netitem__page" href="<?= e($group['url']) ?>"><?= e($group['title']) ?></a><?php else: ?><?= e($group['title']) ?><?php endif; ?></h3>
        <ul class="netprog__schools">
<?php foreach ($rows as $row): $item = $row['item']; $place = ['code' => $row['code'], 'name' => $row['area']['name']]; ?>
          <li class="netprog__school">
            <p class="netprog__at"><?= network_place_link($place, $anchored) ?><?php if (!empty($item['year'])): ?> <span class="netprog__yr"><?= (int) $item['year'] ?></span><?php endif; ?></p>
            <h4 class="netprog__school-title"><?= e($item['title']) ?></h4>
<?php if (!empty($item['community'])): ?>
            <p class="netprog__school-where"><?= e($item['community']) ?></p>
<?php endif; ?>
<?php if (!empty($item['summary'])): ?>
            <p class="netitem__text"><?= e($item['summary']) ?></p>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
      </div>
    </article>
<?php endforeach; ?>
  </div>
  <div class="netlist__areas" hidden>
<?php foreach ($areas as $code => $area): $count = count($area['items']); ?>
    <section class="netarea" data-code="<?= e($code) ?>" data-name="<?= e($area['name']) ?>">
      <h3 class="netarea__title"><?= e($area['name']) ?> <span class="netarea__count"><span class="netarea__n"><?= $count ?></span> <?= e($cfg['item']) ?></span></h3>
      <ul class="netarea__items"><?php foreach ($area['items'] as $item) { echo network_item_html('thailand', $item); } ?></ul>
    </section>
<?php endforeach; ?>
  </div>
</div></section>
<?php
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

// Tags of a world record as slug => label, in the order of config/network-tags.php. Unknown slugs are dropped.
// The database gives an array; data/network.json may hold an array or a comma-separated string.
function network_tags_of(array $item): array {
    static $known = null;
    $known ??= require __DIR__ . '/../config/network-tags.php';
    $raw = $item['tags'] ?? [];
    $slugs = is_array($raw) ? $raw : explode(',', (string) $raw);
    $slugs = array_flip(array_filter(array_map(static fn ($s): string => trim((string) $s), $slugs)));
    return array_filter($known, static fn ($slug): bool => isset($slugs[$slug]), ARRAY_FILTER_USE_KEY);
}

// Description lines (one point per line), at most $max. Empty lines are skipped.
function network_points(string $text, int $max = 3): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('~\R~u', $text) ?: []), static fn (string $l): bool => $l !== ''));
    return array_slice(array_map(static fn (string $l): string => preg_replace('~\A[-•*·]\s*~u', '', $l) ?? $l, $lines), 0, $max);
}

// $tag: 'li' in lists, 'div' inside a card of the deck under the world map.
function network_item_html(string $mode, array $item, string $tag = 'li'): string {
    $tag = $tag === 'div' ? 'div' : 'li';
    if ($mode === 'world') {
        $tags = network_tags_of($item);
        $html = '<' . $tag . ' class="netitem" data-tags="' . e(implode(' ', array_keys($tags))) . '">';
        if ($tags) {
            $html .= '<ul class="netitem__tags" aria-label="รูปแบบความร่วมมือ">';
            foreach ($tags as $label) { $html .= '<li>' . e($label) . '</li>'; }
            $html .= '</ul>';
        }
        // The name opens the institution's page on this site (activities, photos, its website). On a deck card the link
        // covers the whole card. Without an id (data/network.json written by hand) the name is plain text.
        $name = e($item['institution']);
        if (!empty($item['id'])) {
            $name = '<a class="netitem__page" href="' . e(network_partner_url((int) $item['id'])) . '">' . $name . '</a>';
        }
        $html .= '<h4 class="netitem__title">' . $name . '</h4>';
        if (!empty($item['institution_th'])) { $html .= '<p class="netitem__sub">' . e($item['institution_th']) . '</p>'; }
        if (trim((string) ($item['summary'] ?? '')) !== '') { $html .= '<p class="netitem__summary">' . e(trim((string) $item['summary'])) . '</p>'; }
        if (trim((string) ($item['people'] ?? '')) !== '') { $html .= '<p class="netitem__people">' . e(trim((string) $item['people'])) . '</p>'; }
        $points = network_points((string) ($item['description'] ?? ''));
        if (count($points) > 1) {
            $html .= '<ul class="netitem__points">';
            foreach ($points as $point) { $html .= '<li>' . e($point) . '</li>'; }
            $html .= '</ul>';
        } elseif ($points) {
            $html .= '<p class="netitem__text">' . e($points[0]) . '</p>';
        }
    } else {
        $html = '<li class="netitem">';
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
    return $html . '</' . $tag . '>';
}

// Filter buttons over the list under the world map: "ทั้งหมด" and each tag that at least one institution has, with
// counts. Hidden until network-map.js wires them, so visitors without JavaScript never see buttons that do nothing.
function network_tag_filter(array $areas): string {
    $counts = []; $total = 0;
    foreach ($areas as $area) {
        foreach ($area['items'] as $item) {
            $total++;
            foreach (array_keys(network_tags_of($item)) as $slug) { $counts[$slug] = ($counts[$slug] ?? 0) + 1; }
        }
    }
    if (!$counts) { return ''; }
    $html = '<div class="netfilter" role="group" aria-label="กรองตามรูปแบบความร่วมมือ" hidden>';
    $html .= '<button class="netfilter__chip" type="button" data-tag="" aria-pressed="true">ทั้งหมด <span class="netfilter__n">' . $total . '</span></button>';
    foreach (require __DIR__ . '/../config/network-tags.php' as $slug => $label) {
        if (empty($counts[$slug])) { continue; }
        $html .= '<button class="netfilter__chip" type="button" data-tag="' . e($slug) . '" aria-pressed="false">' . e($label) . ' <span class="netfilter__n">' . (int) $counts[$slug] . '</span></button>';
    }
    return $html . '</div>';
}

// Activities of one partner (network_activities / data/network-activities.json), in their order. [] for id 0.
function network_activities_of(int $partnerId): array {
    static $byPartner = null;
    if ($byPartner === null) {
        $byPartner = [];
        foreach (load_data('network-activities') as $activity) {
            if (is_array($activity) && !empty($activity['partner_id'])) { $byPartner[(int) $activity['partner_id']][] = $activity; }
        }
    }
    return $partnerId > 0 ? ($byPartner[$partnerId] ?? []) : [];
}

function network_partner_url(int $id): string { return 'network-partner.php?id=' . $id; }

// Who went where: outbound = the faculty's people went abroad, inbound = guests came to the faculty, joint = together.
function network_direction_label(string $direction): string {
    return ['outbound' => 'คณะไปเยือน', 'inbound' => 'มาเยือนคณะ'][$direction] ?? 'ทำร่วมกัน';
}

// One photo that opens the large view (assets/js/network-map.js, netbox). $meta: title, institution, period, href.
function network_photo_button(string $src, string $alt, array $meta, string $class, string $extra = ''): string {
    [$w, $h] = image_size($src);
    $data = ' data-netbox data-full="' . e($src) . '" data-title="' . e((string) (($meta['title'] ?? '') !== '' ? $meta['title'] : $alt)) . '"';
    foreach (['institution' => 'inst', 'period' => 'period', 'href' => 'href'] as $key => $attr) {
        if (($meta[$key] ?? '') !== '') { $data .= ' data-' . $attr . '="' . e((string) $meta[$key]) . '"'; }
    }
    return '<button class="' . e($class) . '" type="button"' . $data . ' aria-label="ดูภาพขยาย: ' . e($alt) . '"><img src="' . e($src) . '"'
        . ($w && $h ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : '') . ' alt="" loading="lazy" decoding="async">' . $extra . '</button>';
}

// The large view of a photo, once per page, filled by JavaScript.
function network_lightbox(): string {
    return '<dialog class="netbox" aria-labelledby="netbox-title"><div class="netbox__frame">'
        . '<button class="netbox__close" type="button" data-netbox-close aria-label="ปิดภาพขยาย"><span aria-hidden="true">×</span></button>'
        . '<figure class="netbox__figure"><div class="netbox__stage"><img class="netbox__img" src="data:," alt=""><button class="hero-ctrl netbox__nav netbox__nav--prev" type="button" data-netbox-step="-1" aria-label="ภาพก่อนหน้า">' . icon_arrow('chevron-left') . '</button><button class="hero-ctrl netbox__nav netbox__nav--next" type="button" data-netbox-step="1" aria-label="ภาพถัดไป">' . icon_arrow('chevron-right') . '</button></div>'
        . '<figcaption class="netbox__cap"><p class="netbox__inst"></p><p class="netbox__title" id="netbox-title"></p><a class="netbox__link" href="#" hidden></a></figcaption></figure>'
        . '</div></dialog>';
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
    $placed = count(array_diff_key($areas, NETWORK_NO_MAP)); // countries on the map (a multi-country network is not one)
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
      <p class="visually-hidden"><?= e($cfg['lead']) ?> <?= $placed ?> <?= e($cfg['area']) ?> <?= $items ?> <?= e($cfg['item']) ?></p>
      <dl class="netmap__stats" aria-hidden="true">
        <div class="netmap__stat"><dt><?= e($cfg['stat_area']) ?></dt><dd class="netmap__num" data-count="<?= $placed ?>"><?= $placed ?></dd></div>
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
<?php if (!$empty && $mode === 'world'): $deckTotal = array_sum(array_map(static fn (array $a): int => count($a['items']), $areas)); ?>
<section class="netlist netlist--world" aria-labelledby="netlist-title">
  <div class="container"><div class="netlist__top"><h2 id="netlist-title"><?= e($cfg['list_title']) ?></h2><?= network_tag_filter($areas) ?></div></div>
  <div class="netdeck" aria-label="สถาบันในเครือข่าย">
    <div class="netdeck__viewport">
      <ul class="netdeck__track">
<?php $first = []; foreach ($areas as $code => $area): foreach ($area['items'] as $item): $anchor = !isset($first[$code]); $first[$code] = true; $src = image_path($item); ?>
        <li class="netdeck__card"<?= $anchor ? ' id="' . e($code) . '"' : '' ?> data-code="<?= e($code) ?>" data-country="<?= e($area['name']) ?>" data-tags="<?= e(implode(' ', array_keys(network_tags_of($item)))) ?>">
          <div class="netdeck__media<?= $src === '' ? ' is-empty' : '' ?>"><?php if ($src !== ''): [$w, $h] = image_size($src); ?><img src="<?= e($src) ?>"<?= $w && $h ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : '' ?> alt="" loading="lazy" decoding="async"><?php endif; ?></div>
          <div class="netdeck__body">
<?php if (isset(NETWORK_NO_MAP[$code])): ?>
            <p class="netdeck__where"><span><?= e(NETWORK_NO_MAP[$code][1]) ?></span><?php $acts = count(network_activities_of((int) ($item['id'] ?? 0))); if ($acts): ?><span class="netdeck__acts"><?= $acts ?> กิจกรรม</span><?php endif; ?></p>
<?php else: ?>
            <p class="netdeck__where"><span><?= e($area['name']) ?></span><?php $acts = count(network_activities_of((int) ($item['id'] ?? 0))); if ($acts): ?><span class="netdeck__acts"><?= $acts ?> กิจกรรม</span><?php endif; ?></p>
<?php endif; ?>
            <?= network_item_html($mode, $item, 'div') ?>
          </div>
        </li>
<?php endforeach; endforeach; ?>
      </ul>
      <div class="netdeck__ctrls" hidden><button class="hero-ctrl netdeck__ctrl" type="button" data-deck-prev aria-label="สถาบันก่อนหน้า"><?= icon_arrow('chevron-left') ?></button><button class="hero-ctrl netdeck__ctrl" type="button" data-deck-next aria-label="สถาบันถัดไป"><?= icon_arrow('chevron-right') ?></button></div>
    </div>
    <p class="netdeck__count" aria-live="polite" hidden><span data-deck-count>1</span> / <span data-deck-total><?= $deckTotal ?></span></p>
  </div>
</section>
<?php elseif (!$empty): render_network_programs($areas); endif; ?>
<?php if ($mode === 'world') { render_network_photos(); } ?>
<?php
}

// Photo strip under the deck of the world page: activity photos (network_photos / data/network-photos.json, those with
// in_strip) moving sideways without stop, once network-map.js has set it going. A row stops while the pointer is on it
// or a photo has focus; each photo shows what event it is from and opens a large view. With ten photos or more they are
// split over two rows that move in opposite directions. Without JavaScript, or with reduced motion, each row is a strip
// that scrolls by hand.
function render_network_photos(): void {
    $photos = [];
    foreach (load_data('network-photos') as $row) {
        if (!is_array($row) || (array_key_exists('in_strip', $row) && !$row['in_strip'])) { continue; }
        $src = image_path($row);
        if ($src === '') { continue; }
        $meta = ['title' => (string) ($row['title'] ?? ''), 'institution' => (string) ($row['institution'] ?? ''), 'period' => (string) ($row['period'] ?? '')];
        if (!empty($row['partner_id'])) { $meta['href'] = network_partner_url((int) $row['partner_id']); }
        $photos[] = ['src' => $src, 'alt' => trim((string) ($row['caption'] ?? '')), 'meta' => $meta];
    }
    if (!$photos) { return; }
    $rows = count($photos) >= 10 ? [[], []] : [[]];
    foreach ($photos as $i => $photo) { $rows[$i % count($rows)][] = $photo; }
    ?>
<section class="netphotos" aria-label="ภาพกิจกรรมกับเครือข่าย">
  <div class="netphotos__rows" data-netbox-group>
<?php foreach ($rows as $r => $set): ?>
    <div class="netphotos__row" data-dir="<?= $r ? 'right' : 'left' ?>">
      <ul class="netphotos__strip"><?php foreach ($set as $photo):
          $cap = $photo['meta']['title'] !== '' ? '<span class="netphotos__cap"><span class="netphotos__inst">' . e($photo['meta']['institution']) . '</span><span class="netphotos__title">' . e($photo['meta']['title']) . '</span></span>' : '';
          ?><li><?= network_photo_button($photo['src'], $photo['alt'], $photo['meta'], 'netphotos__item', $cap) ?></li><?php endforeach; ?></ul>
    </div>
<?php endforeach; ?>
  </div>
  <?= network_lightbox() ?>
</section>
<?php
}

// network-partner.php: one institution with every activity the faculty did with it, in the order set in the admin,
// each with its photos (which open the same large view as the strip).
function render_network_partner(array $partner, string $countryName): void {
    $activities = network_activities_of((int) $partner['id']);
    $tags = network_tags_of($partner);
    $src = image_path($partner);
    $code = (string) $partner['country_code'];
    $url = (string) ($partner['url'] ?? '');
    $onMap = !isset(NETWORK_NO_MAP[$code]);
    $where = $onMap ? $countryName : NETWORK_NO_MAP[$code][1];
    ?>
<section class="netpartner"><div class="container">
  <a class="text-link back-link" href="network-international.php<?= $onMap ? '#' . e($code) : '' ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg><span>เครือข่ายวิจัยนานาชาติ</span></a>
  <header class="netpartner__head">
    <div class="netpartner__media<?= $src === '' ? ' is-empty' : '' ?>"><?php if ($src !== ''): [$w, $h] = image_size($src); ?><img src="<?= e($src) ?>"<?= $w && $h ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : '' ?> alt=""><?php endif; ?></div>
    <div class="netpartner__intro">
      <p class="netpartner__where"><?= e($where) ?></p>
      <h1><?= e($partner['institution']) ?></h1>
<?php if (!empty($partner['institution_th'])): ?>
      <p class="netpartner__th"><?= e($partner['institution_th']) ?></p>
<?php endif; ?>
<?php if (trim((string) ($partner['summary'] ?? '')) !== ''): ?>
      <p class="netpartner__summary"><?= e(trim((string) $partner['summary'])) ?></p>
<?php endif; ?>
      <dl class="netpartner__facts">
<?php if ($tags): ?>
        <div><dt>รูปแบบความร่วมมือ</dt><dd><ul class="netitem__tags"><?php foreach ($tags as $label): ?><li><?= e($label) ?></li><?php endforeach; ?></ul></dd></div>
<?php endif; ?>
<?php if (trim((string) ($partner['people'] ?? '')) !== ''): ?>
        <div><dt>ผู้ประสานงานและนักวิชาการ</dt><dd><?= e(trim((string) $partner['people'])) ?></dd></div>
<?php endif; ?>
<?php if (preg_match('~\Ahttps?://~i', $url)): ?>
        <div><dt>เว็บไซต์</dt><dd><a class="netitem__site" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) preg_replace('~\Ahttps?://(www\.)?|/\z~i', '', $url)) ?><span class="visually-hidden"> (เปิดแท็บใหม่)</span> <?= icon_arrow('up-right') ?></a></dd></div>
<?php endif; ?>
      </dl>
    </div>
  </header>
  <section class="netpartner__acts" aria-labelledby="netpartner-acts">
    <h2 id="netpartner-acts">กิจกรรม <span class="netpartner__n"><?= count($activities) ?></span></h2>
<?php if (!$activities): ?>
    <p class="netpartner__none">ยังไม่มีรายละเอียดกิจกรรมของสถาบันนี้</p>
<?php else: ?>
    <ul class="nettl">
<?php foreach ($activities as $activity): ?>
      <li class="nettl__item<?= empty($activity['photos']) ? ' is-text' : '' ?>">
        <div class="nettl__text">
<?php if (($activity['period'] ?? '') !== ''): ?>
        <p class="nettl__meta"><span class="nettl__period"><?= e($activity['period']) ?></span></p>
<?php endif; ?>
        <h3 class="nettl__title"><?= e($activity['title']) ?></h3>
<?php if (trim((string) ($activity['body'] ?? '')) !== ''): ?>
        <p class="nettl__body"><?= e(trim((string) $activity['body'])) ?></p>
<?php endif; ?>
        </div>
<?php if (!empty($activity['photos'])): ?>
<?php $shown = array_values(array_filter($activity['photos'], static fn ($ph): bool => is_array($ph) && image_path($ph) !== '')); ?>
        <ul class="nettl__photos nettl__photos--<?= min(4, count($shown)) ?>" data-netbox-group><?php foreach ($activity['photos'] as $photo): $psrc = image_path($photo); if ($psrc === '') { continue; } ?><li><?= network_photo_button($psrc, (string) ($photo['caption'] ?? $activity['title']), ['title' => $activity['title'], 'institution' => $partner['institution'], 'period' => (string) ($activity['period'] ?? '')], 'nettl__photo') ?></li><?php endforeach; ?></ul>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
</div></section>
<?= network_lightbox() ?>
<?php
}
