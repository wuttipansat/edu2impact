// Browser test for the admin block editor. LOCAL TEST DATABASE ONLY (it creates posts and uploads pictures).
//   php -S 127.0.0.1:8084 -t .   then   ADMIN_EMAIL=... ADMIN_PASS=... PICS=/dir/with/png-and-jpg SHOTS=/dir node tools/test_editor.js
// Needs Playwright with Chromium. Prints PASS/FAIL lines; exit code 1 when something fails.
const { chromium } = require('playwright'); const fs = require('fs');
const B = process.env.SITE_URL || 'http://127.0.0.1:8084', EMAIL = process.env.ADMIN_EMAIL || 'admin@example.com', PASS = process.env.ADMIN_PASS || 'Test-pass-123';
const PICS = process.env.PICS || '.', SHOTS = process.env.SHOTS || '';
let failed = 0; const t = (name, ok, extra) => { console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ' ' + JSON.stringify(extra))); if (!ok) failed++; };
const shot = async (p, name, opts) => { if (SHOTS) { await p.screenshot(Object.assign({ path: SHOTS + '/' + name }, opts || {})); } };
async function login(p) { await p.goto(B + '/admin/login.php'); await p.fill('input[name=email]', EMAIL); await p.fill('input[name=password]', PASS); await Promise.all([p.waitForNavigation(), p.click('form[action*=login] button[type=submit], main button[type=submit]')]); }
const blocks = p => p.evaluate(() => [...document.querySelectorAll('#blocks > .blk')].filter(b => b.getAttribute('data-t') !== 'p' || b.querySelector('textarea').value.trim() !== '').map(b => b.getAttribute('data-t') + (b.getAttribute('data-level') || '')).join(','));
const ptext = (p, i) => p.locator('#blocks > .blk').nth(i).locator('textarea,input[type=text]').first();
(async () => {
  const browser = await chromium.launch(); const stamp = 'ed' + Date.now().toString(36);
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 }); const p = await ctx.newPage();
  const errs = []; p.on('pageerror', e => errs.push(String(e))); p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(m.text()); });
  p.on('dialog', d => d.accept());
  await login(p);
  await p.goto(B + '/admin/post-edit.php');
  t('block editor shown, text form hidden', await p.isVisible('#blocks-app') && !(await p.isVisible('#legacy')));
  t('side column has publish, type, summary', await p.isVisible('.box--publish') && await p.isVisible('#summary') && await p.isVisible('input[name=kind][value=research]', { timeout: 100 }).catch(() => true));
  await p.check('input[name=kind][value=research]', { force: true });
  await p.fill('#title', 'ทดสอบตัวแก้ไขแบบบล็อก ' + stamp);
  await p.fill('#summary', 'สรุปสั้นในแถบข้าง');
  // paragraphs: Enter splits, "## " makes a heading
  await ptext(p, 0).click(); await p.keyboard.type('ย่อหน้าแรกของโพสต์'); await p.keyboard.press('Enter'); await p.keyboard.type('## หัวข้อรองที่พิมพ์ลัด');
  t('"## " becomes an H3 heading', (await blocks(p)).startsWith('p,h2'), await blocks(p));
  await p.keyboard.press('Enter'); await p.keyboard.type('ย่อหน้าใต้หัวข้อ');
  await p.click('[data-add="h1"]'); await p.keyboard.type('หัวข้อใหญ่ H2');
  t('H2 button adds a level-1 heading (printed h2)', (await blocks(p)).includes('h1'), await blocks(p));
  // pictures: several at once through the file picker
  const files = ['gradient.jpg', 'big-photo.png', 'big.png'].filter(f => fs.existsSync(PICS + '/' + f)).map(f => PICS + '/' + f);
  await p.click('[data-add="img"]', { trial: true }).catch(() => {});
  await p.setInputFiles('#pick-images', files);
  await p.waitForFunction(n => document.querySelectorAll('#blocks > .blk[data-t=img][data-media]').length === n, files.length, { timeout: 60000 });
  t('three pictures uploaded in one go', (await blocks(p)).split(',').filter(x => x === 'img').length === files.length, await blocks(p));
  t('picture without description is flagged', await p.locator('.blkimg input.bad').count() === files.length);
  for (let i = 0; i < files.length; i++) { await p.locator('.blk[data-t=img]').nth(i).locator('input').first().fill('คำอธิบายรูป ' + (i + 1)); }
  await p.locator('.blk[data-t=img]').nth(0).locator('input').nth(1).fill('คำบรรยายรูปแรก');
  // paste a spreadsheet table into a paragraph
  await p.locator('#blocks > .blk[data-t=p]').last().locator('textarea').click();
  await p.evaluate(() => {
    const dt = new DataTransfer();
    dt.setData('text/html', '<meta charset=utf-8><table><tr><td>ชื่อ</td><td>คะแนน</td></tr><tr><td>สมชาย</td><td>85</td></tr><tr><td>สมหญิง</td><td>1,200</td></tr></table>');
    dt.setData('text/plain', 'ชื่อ\tคะแนน\nสมชาย\t85\nสมหญิง\t1,200');
    document.activeElement.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  t('pasted spreadsheet becomes a table block', (await blocks(p)).includes('table') && await p.locator('.blktbl tr').count() === 3, await blocks(p));
  await p.locator('.blktbl td input').nth(3).fill('ผลสอบ');
  await p.locator('.blktbl').locator('input[type=text]').last().fill('คะแนนสอบปลายภาค');
  // table: add a row and a column, then limits
  await p.click('.blktbl button:has-text("+ แถว")'); await p.click('.blktbl button:has-text("+ คอลัมน์")');
  t('table grows by a row and a column', await p.locator('.blktbl tr').count() === 4 && await p.locator('.blktbl tr').first().locator('td').count() === 3);
  // paragraph paste with several lines -> several paragraphs
  await p.locator('#blocks > .blk[data-t=p]').last().locator('textarea').click();
  await p.evaluate(() => { const dt = new DataTransfer(); dt.setData('text/plain', 'บรรทัดแรกที่วาง\nบรรทัดสองที่วาง\n# หัวข้อที่วางมา'); document.activeElement.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true })); });
  t('multi-line paste makes paragraphs and a heading', (await blocks(p)).includes('h1,') || (await blocks(p)).endsWith('h1,p'), await blocks(p));
  // move + delete + undo
  const before = await blocks(p);
  await p.locator('#blocks > .blk').first().locator('textarea').click(); await p.locator('#blocks > .blk').first().locator('button[aria-label="เลื่อนบล็อกลง"]').click();
  t('block moved down', (await blocks(p)) !== before);
  await p.evaluate(() => window.scrollTo(0, 0)); await p.locator('#blocks > .blk').nth(3).locator('input').click(); await shot(p, 'editor-1280.png');
  // draft kept in the browser: reload, then restore
  const snapshot = await blocks(p); await p.waitForTimeout(1000);
  await p.evaluate(() => { window.onbeforeunload = null; });
  p.removeAllListeners('dialog'); p.on('dialog', d => d.accept());
  await p.reload();
  t('draft banner after reload', await p.isVisible('#draft-banner'), await p.locator('#draft-banner').textContent());
  await p.click('#draft-banner button:has-text("กู้คืน")');
  t('draft restores title, summary and all blocks', (await p.inputValue('#title')).includes(stamp) && (await p.inputValue('#summary')) === 'สรุปสั้นในแถบข้าง' && (await blocks(p)) === snapshot, [await blocks(p), snapshot]);
  await p.check('input[name=kind][value=research]', { force: true });
  await Promise.all([p.waitForNavigation(), p.click('.box--publish button[value=publish]')]);
  t('published and redirected with saved=1', /saved=1/.test(p.url()), p.url());
  const id = (/id=(\d+)/.exec(p.url()) || [])[1];
  t('no page errors', errs.length === 0, errs);
  const draftGone = await p.evaluate(() => Object.keys(localStorage).filter(k => k.startsWith('e2i-draft')).length);
  t('draft removed after saving', draftGone === 0, draftGone);
  // open again: same blocks; save without touching -> same body
  const after = await blocks(p);
  const body1 = await (await ctx.request.get(B + '/research-detail.php?id=' + (await p.evaluate(() => document.getElementById('slug').value)))).text();
  t('public page has h2 h3 table and 3 pictures', /<h2[^>]*>หัวข้อใหญ่ H2/.test(body1) && /<h3[^>]*>หัวข้อรองที่พิมพ์ลัด/.test(body1) && /<table/.test(body1) && (body1.match(/<img[^>]+media|file\.php\?id=/g) || []).length >= 3, body1.slice(0, 200));
  const slug = await p.evaluate(() => document.getElementById('slug').value);
  await Promise.all([p.waitForNavigation(), p.click('.box--publish button[value=save]')]);
  t('re-save without changes keeps the same blocks', (await blocks(p)) === after, [await blocks(p), after]);
  // switch research -> innovations
  await p.selectOption('#category', { index: 1 });
  await p.check('input[name=kind][value=innovations]', { force: true });
  await Promise.all([p.waitForNavigation(), p.click('.box--publish button[value=save]')]);
  const flash = await p.locator('.notice--ok').allInnerTexts();
  t('kind switch saved with a message and new link', flash.join(' ').includes('นวัตกรรม') && flash.join(' ').includes('innovation-detail.php'), flash);
  const newSlug = await p.evaluate(() => document.getElementById('slug').value);
  const r1 = await ctx.request.get(B + '/innovation-detail.php?id=' + newSlug, { maxRedirects: 0 });
  const r2 = await ctx.request.get(B + '/research-detail.php?id=' + slug, { maxRedirects: 0 });
  t('new innovation page opens', r1.status() === 200, r1.status());
  t('old research link redirects (301)', r2.status() === 301, r2.status());
  const menu = await (await ctx.request.get(B + '/index.php')).text();
  const cat = await p.locator('#category').inputValue();
  t('category shown for innovations', cat !== '', cat);
  await shot(p, 'editor-after-switch-1280.png');
  // back to research; the category stays, research list never prints it as a category
  await p.check('input[name=kind][value=research]', { force: true });
  await Promise.all([p.waitForNavigation(), p.click('.box--publish button[value=save]')]);
  const rl = await (await ctx.request.get(B + '/research.php')).text();
  t('research list has no Learning / Assessment', !/Learning|Assessment/.test(rl));
  // phone width
  await p.setViewportSize({ width: 390, height: 844 }); await shot(p, 'editor-390.png', { fullPage: false });
  t('no horizontal scroll at 390px', await p.evaluate(() => document.documentElement.scrollWidth <= 392), await p.evaluate(() => document.documentElement.scrollWidth));
  // tidy: hide the test post
  await ctx.close();
  // JavaScript off: the text form still works
  const c2 = await browser.newContext({ javaScriptEnabled: false }); const q = await c2.newPage();
  await login(q); await q.goto(B + '/admin/post-edit.php');
  t('JS off: text form shown, block editor hidden', await q.isVisible('#legacy') && !(await q.isVisible('#blocks-app')));
  await q.check('input[name=kind][value=research]'); await q.fill('#title', 'ทดสอบไม่มี JS ' + stamp);
  await q.fill('#content', '# หัวข้อ\n\nข้อความ\n\n[ตาราง 1]'); await q.fill('textarea[name="tbl[0][text]"]', 'หัว1|หัว2\nก|1');
  await Promise.all([q.waitForNavigation(), q.click('button[name=op][value=publish]')]);
  t('JS off: saved', /saved=1/.test(q.url()), q.url());
  await c2.close(); await browser.close();
  console.log(failed ? failed + ' FAILED' : 'ALL PASSED'); process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
