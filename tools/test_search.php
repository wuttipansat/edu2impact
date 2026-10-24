<?php
// Unit checks for includes/search.php. Run: php tools/test_search.php (exit 1 on failure).
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/search.php';
$failed = 0;
function check($label, $ok) { global $failed; echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . PHP_EOL; $failed += $ok ? 0 : 1; }

// Highlighting escapes first, then marks.
check('script is escaped', search_highlight('<script>alert(1)</script>', 'script') === '&lt;<mark>script</mark>&gt;alert(1)&lt;/<mark>script</mark>&gt;');
check('ampersand is escaped', search_highlight('R&D lab', '&') === 'R<mark>&amp;</mark>D lab');
check('quotes are escaped', search_highlight('say "hi" \'x\'', '"hi"') === 'say <mark>&quot;hi&quot;</mark> &#039;x&#039;');
check('query markup never appears raw', strpos(search_highlight('abc', '<img src=x>'), '<img') === false);
check('Thai substring', search_highlight('การพัฒนาครูในโรงเรียน', 'ครู') === 'การพัฒนา<mark>ครู</mark>ในโรงเรียน');
check('case-insensitive', search_highlight('STEM and stem', 'Stem') === '<mark>STEM</mark> and <mark>stem</mark>');
check('two terms, overlapping ranges merge', search_highlight('abcdef', 'abc cde') === '<mark>abcde</mark>f');
check('no match = plain escaped text', search_highlight('a<b', 'zz') === 'a&lt;b');

// Normalising.
check('collapse spaces and trim', search_normalise("  STEM \t  ครู  ") === 'STEM ครู');
check('cut to 100 characters', mb_strlen(search_normalise(str_repeat('ก', 500))) === 100);
check('invalid UTF-8 becomes empty', search_normalise("\xff\xfe") === '');

// Excerpts.
$entry = ['summary' => '', 'body' => str_repeat('ข้อความ ', 40) . 'คำค้นพิเศษ ' . str_repeat('ต่อท้าย ', 40)];
$excerpt = search_excerpt($entry, 'คำค้นพิเศษ');
check('excerpt contains the match', mb_strpos($excerpt, 'คำค้นพิเศษ') !== false);
check('excerpt marks cuts with …', str_starts_with($excerpt, '…') && str_ends_with($excerpt, '…'));
check('excerpt is valid UTF-8', mb_check_encoding($excerpt, 'UTF-8'));
check('excerpt does not start with a combining mark', !preg_match('/^…?\p{M}/u', $excerpt));
check('excerpt falls back to summary', search_excerpt(['summary' => 'สรุปสั้น', 'body' => 'x'], 'zzz') === 'สรุปสั้น');

// Searching.
$all = search_run('STEM');
check('STEM finds research, innovations and workshops', $all['groups']['research'] && $all['groups']['innovations'] && $all['groups']['workshops']);
check('empty query returns nothing', search_run('   ')['total'] === 0);
check('AND logic narrows results', search_run('STEM ครู')['total'] <= min(search_run('STEM')['total'], search_run('ครู')['total']));
check('event is indexed', (bool) search_run('Research to Practice Forum')['groups']['events']);
check('nonsense finds nothing', search_run('zzzz')['total'] === 0);
// Optional innovation fields (audience, subjects, features) are searchable.
$pck = search_run('PCK Canvas')['groups']['innovations'] ?? [];
check('PCK Canvas is the top innovation for its name', $pck && $pck[0]['url'] === 'innovation-detail.php?id=pck-canvas');
// The post body is indexed, so words that appear only in the body find the post.
$byBody = search_run('ข้อมูลย้อนกลับ')['groups']['innovations'] ?? [];
check('PCK Canvas body text is searchable', (bool) array_filter($byBody, static fn ($r) => $r['url'] === 'innovation-detail.php?id=pck-canvas'));
// The pck-canvas post no longer has audience/features; their words must not linger in the index.
$byAudience = search_run('ศึกษานิเทศก์')['groups']['innovations'] ?? [];
check('removed audience text is not indexed', !array_filter($byAudience, static fn ($r) => $r['url'] === 'innovation-detail.php?id=pck-canvas'));
// Optional list fields still count as keywords when a record has them.
$fake = search_join([['ครูทดสอบ'], ['ฟีเจอร์ทดสอบ']]);
check('list fields join into keyword text', $fake === 'ครูทดสอบ ฟีเจอร์ทดสอบ');
// Image blocks in a body: the caption is searchable, the file path and alt text are not.
$blocks = body_text(['ย่อหน้า', ['image' => 'assets/images/content-placeholder.png', 'alt' => 'คำอธิบายภาพ', 'caption' => 'คำบรรยายภาพ']]);
check('body image caption is indexed, path and alt are not', $blocks === ['ย่อหน้า', 'คำบรรยายภาพ']);
// Links ("related"): helpers skip bad entries, and a linked record's title finds the record that links to it.
check('is_dated: real date', is_dated(['date' => '2026-11-20']));
check('is_dated: empty date is a post', !is_dated(['date' => '']) && !is_dated([]) && !is_dated(['date' => '2026-02-30']));
check('kind_label maps the three datasets', kind_label('research') === 'งานวิจัย' && kind_label('innovations') === 'นวัตกรรม' && kind_label('workshops') === 'เวิร์กช็อป' && kind_label('events') === '');
$someResearch = linkable_records('research')[0] ?? null;
if ($someResearch) {
    $probe = ['id' => 'probe', 'related' => [['kind' => 'research', 'id' => $someResearch['id']], ['kind' => 'research', 'id' => $someResearch['id']], ['kind' => 'research', 'id' => 'no-such-record'], ['kind' => 'events', 'id' => 'x'], ['kind' => 'workshops', 'id' => 'probe'], 'junk']];
    $probeLinks = related_records('workshops', $probe);
    check('related_records keeps valid links once and skips missing, unknown and self links', count($probeLinks) === 1 && $probeLinks[0]['record']['id'] === $someResearch['id']);
}
foreach (linkable_records('workshops') as $workshop) {
    foreach (related_records('workshops', $workshop) as $link) {
        $hits = search_run($link['record']['title'])['groups']['workshops'] ?? [];
        check('linked title finds workshop ' . $workshop['id'], (bool) array_filter($hits, static fn ($r) => $r['url'] === detail_url('workshops', $workshop['id'])));
        $back = linked_records($link['kind'], $link['record']);
        check($link['kind'] . ' ' . $link['record']['id'] . ' lists workshop ' . $workshop['id'] . ' back', (bool) array_filter($back, static fn ($l) => $l['kind'] === 'workshops' && $l['record']['id'] === $workshop['id']));
    }
}
exit($failed ? 1 : 0);
