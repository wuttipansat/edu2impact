<?php
// Icons for the two text bands under the world map (network_highlights.icon). Line icons on a 24 px grid that take the
// text colour (currentColor). The admin page offers only these keys, so no markup ever comes from a form.
function network_icons(): array {
    return [
        'pair-talk' => ['คนสองคนคุยกัน', '<circle cx="7" cy="8" r="2.5"/><path d="M2.5 19v-1.5A3.5 3.5 0 0 1 6 14h2a3.5 3.5 0 0 1 3.5 3.5V19"/><circle cx="17" cy="8" r="2.5"/><path d="M12.5 19v-1.5a3.5 3.5 0 0 1 3.5-3.5h2a3.5 3.5 0 0 1 3.5 3.5V19"/>'],
        'magnifier' => ['แว่นขยาย', '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5.5 5.5"/>'],
        'document' => ['เอกสาร', '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>'],
        'podium' => ['ผู้บรรยายที่โพเดียม', '<circle cx="12" cy="6" r="2.5"/><path d="M7.5 14.5a4.5 4.5 0 0 1 9 0"/><path d="M6 21v-4.5h12V21z"/>'],
        'group' => ['กลุ่มคน', '<circle cx="12" cy="7" r="2.5"/><path d="M7.5 19v-1.5a4.5 4.5 0 0 1 9 0V19"/><circle cx="5.5" cy="10" r="2"/><path d="M2 18.5v-1a3 3 0 0 1 3-3"/><circle cx="18.5" cy="10" r="2"/><path d="M22 18.5v-1a3 3 0 0 0-3-3"/>'],
        'globe' => ['ลูกโลก', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9z"/>'],
        'mortarboard' => ['หมวกบัณฑิต', '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/>'],
        'globe-chart' => ['ลูกโลกกับกราฟขาขึ้น', '<circle cx="10" cy="10" r="6.5"/><path d="M3.5 10h13"/><path d="M10 3.5c1.8 1.8 2.5 3.8 2.5 6.5s-.7 4.7-2.5 6.5c-1.8-1.8-2.5-3.8-2.5-6.5s.7-4.7 2.5-6.5z"/><path d="m13 20.5 3.5-3.5 2.5 2 3-4.5"/><path d="M18.5 14.5H22V18"/>'],
        'bulb' => ['หลอดไฟ', '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>'],
        'network' => ['ผังเครือข่ายคน', '<circle cx="12" cy="5" r="2.2"/><circle cx="5" cy="18" r="2.2"/><circle cx="19" cy="18" r="2.2"/><circle cx="12" cy="12" r="1.3"/><path d="M12 7.2v3.5M11 13l-4.4 3.4M13 13l4.4 3.4"/>'],
        'target' => ['เป้าธนู', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5.5"/><circle cx="12" cy="12" r="1.8"/><path d="m12 12 7-7M16.5 5H19v2.5"/>'],
    ];
}
function network_icon_options(): array {
    return array_map(static fn (array $icon): string => $icon[0], network_icons());
}
// Inline SVG for a known icon key ('' for anything else). Decorative: hidden from screen readers.
function network_icon_svg(string $key, int $size = 28): string {
    $icons = network_icons();
    if (!isset($icons[$key])) { return ''; }
    return '<svg class="neticon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[$key][1] . '</svg>';
}
