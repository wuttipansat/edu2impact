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
check('partner group is indexed', (bool) search_run('Government')['groups']['pages']);
check('event is indexed', (bool) search_run('Research to Practice Forum')['groups']['events']);
check('nonsense finds nothing', search_run('zzzz')['total'] === 0);
exit($failed ? 1 : 0);
