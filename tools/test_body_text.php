<?php
// Tests how the admin turns the content box into body blocks (includes/admin/posts.php). No database needed.
// Run: php tools/test_body_text.php
require __DIR__ . '/../includes/admin/posts.php';
$fail = 0;
$imgs = [['n' => 1, 'media' => 11, 'alt' => 'a', 'caption' => ''], ['n' => 2, 'media' => 12, 'alt' => 'b', 'caption' => '']];
$show = static fn (array $b): array => array_map(static fn ($x) => $x['t'] === 'p' ? $x['text'] : 'รูป' . $x['n'], $b);
$cases = [
    'labels on their own lines' => ["ก\n\n[รูป 1]\n\nข\n\n[รูป 2]", ['ก', 'รูป1', 'ข', 'รูป2']],
    'no blank lines around a label' => ["ก\n[รูป 1]\nข", ['ก', 'รูป1', 'ข', 'รูป2']],
    'two labels on one line' => ["ก\n\n[รูป 1] [รูป 2]\n\nข", ['ก', 'รูป1', 'รูป2', 'ข']],
    'label inside a sentence, รูปที่, Thai digits, spaces' => ["ก [รูปที่ 1] ข\n\n[ รูป ๒ ]", ['ก', 'รูป1', 'ข', 'รูป2']],
    'no space and full-width brackets' => ["ก\n\n［รูป1］", ['ก', 'รูป1', 'รูป2']],
    'a picture used twice appears once' => ["[รูป 1]\n\nก\n\n[รูป 1]", ['รูป1', 'ก', 'รูป2']],
];
foreach ($cases as $name => [$text, $want]) {
    [$blocks, $err] = admin_body_from_text($text, $imgs);
    $ok = $err === null && $show($blocks) === $want;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $name . ($ok ? '' : ' got ' . json_encode($show($blocks), JSON_UNESCAPED_UNICODE)) . "\n"; $fail += $ok ? 0 : 1;
}
[$blocks, $err] = admin_body_from_text("ก\n\n[รูป 3]", $imgs);
$ok = $blocks === [] && is_string($err) && str_contains($err, 'รูปที่ 3');
echo ($ok ? 'ok    ' : 'FAIL  ') . "a label without a picture gives a clear error instead of showing the label as text\n"; $fail += $ok ? 0 : 1;
[$text, $list] = admin_body_to_text([['t' => 'p', 'text' => 'ก'], ['t' => 'img', 'media' => 11, 'alt' => 'a', 'caption' => '']]);
[$back] = admin_body_from_text($text, $list);
$ok = $show($back) === ['ก', 'รูป1'];
echo ($ok ? 'ok    ' : 'FAIL  ') . "text made from blocks reads back the same\n"; $fail += $ok ? 0 : 1;

// ---- headings ----
$flat = static fn (array $b): array => array_map(static fn ($x) => match ($x['t']) { 'p' => $x['text'], 'img' => 'รูป' . $x['n'], 'h' => 'H' . $x['level'] . ':' . $x['text'], default => 'ตาราง' }, $b);
$check = static function (string $name, bool $ok, string $extra = '') use (&$fail): void { echo ($ok ? 'ok    ' : 'FAIL  ') . $name . ($ok ? '' : ' ' . $extra) . "\n"; $fail += $ok ? 0 : 1; };
[$blocks, $err] = admin_body_from_text("# ใหญ่\n\nก\n\n## รอง\nข้อความใต้หัวข้อ\n### ย่อย\n\nข", $imgs);
$check('three heading levels, a heading line inside a paragraph splits it', $err === null && $flat($blocks) === ['H1:ใหญ่', 'ก', 'H2:รอง', 'ข้อความใต้หัวข้อ', 'H3:ย่อย', 'ข', 'รูป1', 'รูป2'], json_encode($flat($blocks), JSON_UNESCAPED_UNICODE));
[$blocks] = admin_body_from_text("#hashtag ไม่ใช่หัวข้อ\n\n#### สี่ระดับไม่ใช่หัวข้อ\n\n#\n\nข้อความ # กลางบรรทัด", []);
$check('#word, #### and a lone # stay as text', $flat($blocks) === ['#hashtag ไม่ใช่หัวข้อ', '#### สี่ระดับไม่ใช่หัวข้อ', '#', 'ข้อความ # กลางบรรทัด'], json_encode($flat($blocks), JSON_UNESCAPED_UNICODE));

// ---- tables ----
$tabs = [['n' => 1, 'head' => true, 'text' => "ชื่อ\tคะแนน\nก\t10\nข\t20", 'caption' => 'ผลสอบ'], ['n' => 2, 'head' => false, 'text' => "| a | b |\n|---|---|\n| 1 | 2 |", 'caption' => '']];
[$blocks, $err] = admin_body_from_text("ก\n\n[ตาราง 1]\n\n[ ตารางที่ ๒ ] [รูป 1]", $imgs, $tabs);
$t1 = $blocks[1] ?? null; $t2 = $blocks[2] ?? null;
$check('tables with Tab and | separators, labels in several styles', $err === null && count($blocks) === 5 && $t1['t'] === 'table' && $t1['head'] === true && $t1['rows'] === [['ชื่อ', 'คะแนน'], ['ก', '10'], ['ข', '20']] && $t1['caption'] === 'ผลสอบ'
    && $t2['head'] === false && $t2['rows'] === [['a', 'b'], ['1', '2']] && $blocks[3]['t'] === 'img', json_encode($blocks, JSON_UNESCAPED_UNICODE));
[$blocks, $err] = admin_body_from_text('ก', $imgs, $tabs);
$check('a table the text never mentions goes at the end', $err === null && array_column($blocks, 't') === ['p', 'img', 'img', 'table', 'table']);
[$blocks, $err] = admin_body_from_text("ก\n\n[ตาราง 3]", $imgs, $tabs);
$check('a table label without a table gives a clear error', $blocks === [] && is_string($err) && str_contains($err, 'ตารางที่ 3'));
$limit = static fn (string $text): ?string => admin_body_from_text('', [], [['n' => 1, 'head' => true, 'text' => $text, 'caption' => '']])[1];
$check('101 rows are refused, 100 are fine', str_contains((string) $limit(implode("\n", array_fill(0, 101, "a\tb"))), '101 แถว') && $limit(implode("\n", array_fill(0, 100, "a\tb"))) === null);
$check('13 columns are refused, 12 are fine', str_contains((string) $limit(implode("\t", range(1, 13))), '13 คอลัมน์') && $limit(implode("\t", range(1, 12))) === null);
$check('a 501-character cell is refused, 500 is fine', str_contains((string) $limit("ก\t" . str_repeat('ข', 501)), '501 ตัวอักษร') && $limit("ก\t" . str_repeat('ข', 500)) === null);
[$blocks] = admin_body_from_text('', [], [['n' => 1, 'head' => false, 'text' => "<b>x</b>\t<script>", 'caption' => '']]);
$check('cells stay plain text (HTML is escaped only when shown)', $blocks[0]['rows'] === [['<b>x</b>', '<script>']]);
$check('a one-row table with a header row keeps its header', ($r = admin_body_from_text('', [], [['n' => 1, 'head' => true, 'text' => "a\tb", 'caption' => '']])[0][0] ?? null) && $r['head'] === true && $r['rows'] === [['a', 'b']]);

// ---- blocks -> text -> blocks, with every kind ----
$orig = [['t' => 'h', 'level' => 2, 'text' => 'หัวข้อรอง'], ['t' => 'p', 'text' => 'ก'], ['t' => 'table', 'head' => true, 'rows' => [['ชื่อ', 'ค่า'], ['x', '1']], 'caption' => 'ตารางทดสอบ'],
    ['t' => 'img', 'media' => 11, 'alt' => 'a', 'caption' => ''], ['t' => 'h', 'level' => 3, 'text' => 'ย่อย'], ['t' => 'table', 'head' => false, 'rows' => [['p', 'q']], 'caption' => '']];
[$text, $list, $tbl] = admin_body_to_text($orig);
[$back, $err] = admin_body_from_text($text, $list, $tbl);
$strip = static fn (array $bs): array => array_map(static function ($b) { unset($b['n']); return $b; }, $bs);
$check('headings, tables and pictures survive text and back unchanged', $err === null && $strip($back) === $strip($orig), json_encode($back, JSON_UNESCAPED_UNICODE));
$check('the text shows # marks and labels', str_contains($text, "## หัวข้อรอง\n\nก\n\n[ตาราง 1]\n\n[รูป 1]\n\n### ย่อย\n\n[ตาราง 2]"), $text);

// ---- public side: stored shape -> HTML ----
require_once __DIR__ . '/../includes/functions.php';
$check('body_heading reads levels 1-3 only', body_heading(['heading' => 2, 'text' => 'x']) === [2, 'x'] && body_heading(['heading' => 4, 'text' => 'x']) === null && body_heading(['heading' => 1, 'text' => ' ']) === null);
$html = render_body_table(body_table(['table' => ['head' => ['ชื่อ', 'ค่า'], 'rows' => [['<b>ก</b>', '1,200'], ['ข', '3.5%']], 'caption' => 'ผล']]));
$check('table HTML: caption, th scope, numbers right-aligned, text escaped', str_contains($html, '<caption>ผล</caption>') && str_contains($html, '<th scope="col">ชื่อ</th>') && str_contains($html, '<th scope="col" class="num">ค่า</th>')
    && str_contains($html, '<td class="num">1,200</td>') && str_contains($html, '&lt;b&gt;ก&lt;/b&gt;') && !str_contains($html, '<b>'), $html);
$check('search text includes headings and table cells', in_array('หัวข้อ', body_text([['heading' => 1, 'text' => 'หัวข้อ']]), true) && in_array('ก 1', body_text([['table' => ['head' => null, 'rows' => [['ก', '1']]]]]), true));
$check('reading time counts headings and tables', reading_time([['heading' => 2, 'text' => str_repeat('ก', 600)]]) === 2 && reading_time([['table' => ['head' => null, 'rows' => [[str_repeat('ก', 400)]]]], 'ข']) === 1);
echo $fail ? "\n$fail FAILED\n" : "\nall passed\n"; exit($fail ? 1 : 0);
