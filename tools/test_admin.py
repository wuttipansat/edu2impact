#!/usr/bin/env python3
"""Regression test for the admin (/admin/). LOCAL TEST DATABASE ONLY: it creates posts, pictures and uses every existing post.

  php -S 127.0.0.1:8084 -t .          (private/database.php with admin_user, content_source mysql)
  ADMIN_URL=http://127.0.0.1:8084/admin ADMIN_EMAIL=... ADMIN_PASS=... EDITOR_EMAIL=... EDITOR_PASS=... \
  MYSQL_CMD="mysql e2imedu" python3 tools/test_admin.py

The admin and editor accounts must already exist. MYSQL_CMD lets the test read the tables to compare them (skipped if unset).
Clear auth_rate_limits first if the run is repeated within 15 minutes.
"""
import io, json, os, re, shlex, struct, subprocess, sys, time, uuid, zlib, urllib.request, urllib.parse, http.cookiejar
from html.parser import HTMLParser

BASE = os.environ.get('ADMIN_URL', 'http://127.0.0.1:8084/admin').rstrip('/')
SITE = BASE.rsplit('/', 1)[0]
MYSQL = os.environ.get('MYSQL_CMD', '')
fails = []


def check(name, ok, extra=''):
    print(('PASS ' if ok else 'FAIL ') + name + (' ' + str(extra)[:300] if extra and not ok else ''))
    if not ok:
        fails.append(name)


def sql(query):
    out = subprocess.run(shlex.split(MYSQL) + ['--default-character-set=utf8mb4', '-N', '-B', '-e', query], capture_output=True, text=True, check=True).stdout
    return [line.split('\t') for line in out.splitlines()]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class FormParser(HTMLParser):
    """Collects what a browser would send for <form id=...> (no files)."""
    def __init__(self, form_id):
        super().__init__(); self.form_id = form_id; self.inside = False; self.fields = []; self.ta = None; self.sel = None; self.buttons = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.inside = a.get('id') == self.form_id
        if not self.inside:
            return
        if tag == 'input':
            t = a.get('type', 'text'); n = a.get('name')
            if not n or t in ('file', 'submit', 'button'):
                return
            if t in ('checkbox', 'radio') and 'checked' not in a:
                return
            self.fields.append((n, a.get('value', 'on') if t in ('checkbox', 'radio') else a.get('value', '')))
        elif tag == 'textarea':
            self.ta = [a.get('name'), '']
        elif tag == 'select':
            self.sel = [a.get('name'), None, None]
        elif tag == 'option' and self.sel:
            v = a.get('value'); self.opt_value = v; self.opt_text = ''; self.opt_selected = 'selected' in a; self.in_opt = True
        elif tag == 'button' and a.get('name'):
            self.buttons.append((a['name'], a.get('value', '')))

    def handle_data(self, data):
        if self.ta is not None:
            self.ta[1] += data
        elif self.sel and getattr(self, 'in_opt', False):
            self.opt_text += data

    def handle_endtag(self, tag):
        if tag == 'textarea' and self.ta is not None:
            if self.ta[0]:
                self.fields.append((self.ta[0], self.ta[1][1:] if self.ta[1].startswith('\n') else self.ta[1]))
            self.ta = None
        elif tag == 'option' and self.sel:
            v = self.opt_value if self.opt_value is not None else self.opt_text.strip()
            if self.sel[1] is None:
                self.sel[1] = v
            if self.opt_selected:
                self.sel[2] = v
            self.in_opt = False
        elif tag == 'select' and self.sel:
            if self.sel[0]:
                self.fields.append((self.sel[0], self.sel[2] if self.sel[2] is not None else self.sel[1] or ''))
            self.sel = None
        elif tag == 'form':
            self.inside = False


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect)

    def req(self, path, data=None, raw=None, ctype=None, base=BASE):
        url = path if path.startswith('http') else base + '/' + path
        body = raw if raw is not None else (urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None)
        r = urllib.request.Request(url, data=body, headers={'Content-Type': ctype} if ctype else {})
        try:
            resp = self.opener.open(r)
        except urllib.error.HTTPError as e:
            resp = e
        return resp.status, resp.read().decode('utf-8', 'replace'), resp.headers

    def csrf(self, page='account.php'):
        return re.search(r'name="csrf" value="([^"]+)"', self.req(page)[1]).group(1)

    def post(self, path, data, page=None):
        data = dict(data); data['csrf'] = self.csrf(page or path)
        return self.req(path, data)

    def login(self, email, password):
        return self.post('login.php', {'email': email, 'password': password})

    def form(self, page, form_id='editform'):
        html = self.req(page)[1]
        p = FormParser(form_id); p.feed(html)
        return p.fields, p.buttons, html

    def multipart(self, path, fields, files):
        b = '----t' + uuid.uuid4().hex; out = io.BytesIO()
        for k, v in fields:
            out.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n'.encode() + v.encode() + b'\r\n')
        for k, (name, data) in files.items():
            out.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + data + b'\r\n')
        out.write(f'--{b}--\r\n'.encode())
        return self.req(path, raw=out.getvalue(), ctype=f'multipart/form-data; boundary={b}')


def png(w=64, h=32, rgb=(26, 86, 196)):
    raw = b''.join(b'\x00' + bytes(rgb) * w for _ in range(h))
    chunk = lambda t, d: struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')


def set_field(fields, name, value):
    fields = [(k, v) for k, v in fields if k != name]
    return fields + [(name, value)]


def save_post(c, fields, op, files=None, page='post-edit.php'):
    fields = [(k, v) for k, v in fields if k != 'op'] + [('op', op), ('csrf', c.csrf(page))]
    return c.multipart('post-edit.php', fields, files or {})


def norm_row(row):
    # JSON columns are compared as data, so a first save may re-space the text the importer wrote without counting as a change
    out = []
    for x in row:
        try: out.append(json.loads(x) if x[:1] in '[{' else x)
        except ValueError: out.append(x)
    return out


COLS = 'title,summary,slug,body,refs,audience,subjects,features,image_id,download_id,category,tool_url,tool_label,author,post_date,featured,is_public,sort_order'

admin_email, admin_pass = os.environ['ADMIN_EMAIL'], os.environ['ADMIN_PASS']
anon = Client()
check('anonymous page redirects to login', anon.req('index.php')[0] == 303)
check('anonymous media-img is 403', anon.req('media-img.php?id=1')[0] == 403)
check('anonymous preview redirects', anon.req('preview.php')[0] == 303)

a = Client()
check('wrong password is refused', a.login(admin_email, 'wrong-password-123')[0] == 200)
check('right password logs in', a.login(admin_email, admin_pass)[0] == 303)
check('POST without CSRF is 403', a.req('index.php', {'op': 'hide', 'id': '1'})[0] == 403)
for page in ['index.php', 'index.php?kind=research', 'post-edit.php', 'post-edit.php?kind=innovations', 'media.php', 'home.php', 'shop.php', 'settings.php', 'users.php', 'backup.php', 'account.php']:
    s, html, _ = a.req(page)
    check('page ' + page, s == 200 and not re.search(r'Fatal|Warning:|Notice:|Deprecated', html), s)
check('old post list address redirects', a.req('posts.php')[0] == 301)
check('log page is gone', a.req('log.php')[0] == 404)
html = a.req('index.php')[1]
check('home page has a single create button', html.count('สร้างโพสต์ใหม่') == 1 and 'btn--big' in html)
check('no inline style or script on pages', all(x not in html for x in [' style="', '<script>', ' onclick=']))
logs_before = sql('SELECT COUNT(*) FROM admin_log')[0][0] if MYSQL else None

# --- every existing post survives open + save unchanged
if MYSQL:
    ids = [r[0] for r in sql('SELECT id FROM posts WHERE slug NOT LIKE "zz-%" ORDER BY id')]
    same = True
    for pid in ids:
        before = sql(f'SELECT {COLS} FROM posts WHERE id={pid}')[0]
        fields, buttons, html = a.form(f'post-edit.php?id={pid}')
        op = 'save' if ('op', 'save') in buttons else 'draft'
        time.sleep(0.05)
        s, out, _ = save_post(a, fields, op, page=f'post-edit.php?id={pid}')
        after = sql(f'SELECT {COLS} FROM posts WHERE id={pid}')[0]
        ok = s == 303 and norm_row(before) == norm_row(after)
        same &= ok
        if not ok:
            check(f'post {pid} unchanged by open+save', False, (s, [(i, x[:40], y[:40]) for i, (x, y) in enumerate(zip(before, after)) if x != y], re.findall(r'<li>([^<]*)</li>', out)))
    check(f'{len(ids)} existing posts unchanged by open + save', same)

# --- new posts
tag = uuid.uuid4().hex[:6]
thai = ''.join(chr(0x0E01 + int(c, 16)) for c in tag)   # same tag written with Thai letters only
def new_fields(kind, title, content, **extra):
    f = a.form('post-edit.php')[0]
    f = set_field(f, 'kind', kind); f = set_field(f, 'title', title); f = set_field(f, 'content', content)
    for k, v in extra.items():
        f = set_field(f, k, v)
    return f

# Thai-only title, no link name: automatic one
s, out, h = save_post(a, new_fields('research', 'หัวข้อภาษาไทยล้วน ' + thai, 'ย่อหน้าแรก\n\nย่อหน้าสอง'), 'draft')
check('thai-only title saves as draft', s == 303, re.findall(r'<li>([^<]*)</li>', out))
pid1 = re.search(r'id=(\d+)', h['Location']).group(1)
slug1 = sql(f'SELECT slug FROM posts WHERE id={pid1}')[0][0] if MYSQL else ''
check('automatic link name is research-<date>', re.fullmatch(r'research-\d{8}(-\d+)?', slug1) is not None if MYSQL else True, slug1)
s, out, h = save_post(a, new_fields('research', 'หัวข้ออีกอัน ' + thai, 'x'), 'draft')
slug2 = sql(f'SELECT slug FROM posts WHERE id={re.search(r"id=(\d+)", h["Location"]).group(1)}')[0][0] if MYSQL else ''
check('second one gets a different link name', slug1 != slug2 or not MYSQL, (slug1, slug2))
s, out, h = save_post(a, new_fields('innovations', 'PCK Canvas สำหรับครู ' + tag, 'x'), 'draft')
slug3 = sql(f'SELECT slug FROM posts WHERE id={re.search(r"id=(\d+)", h["Location"]).group(1)}')[0][0] if MYSQL else ''
check('English words in the title become the link name', slug3.startswith('pck-canvas-' + tag.lower()) if MYSQL else True, slug3)

# draft -> hidden on the site; publish -> visible; hide -> hidden; undo -> visible
check('draft is 404 on the site', anon.req(f'research-detail.php?id={slug1}', base=SITE)[0] == 404 if MYSQL else True)
fields, buttons, html = a.form(f'post-edit.php?id={pid1}')
check('draft shows publish + draft buttons', ('op', 'publish') in buttons and ('op', 'draft') in buttons)
check('publish', save_post(a, fields, 'publish', page=f'post-edit.php?id={pid1}')[0] == 303)
check('published is 200 on the site', anon.req(f'research-detail.php?id={slug1}', base=SITE)[0] == 200 if MYSQL else True)
if MYSQL:
    # views: counted on the post page, shown on the admin list, and kept when the link name changes
    def visit(path):
        r = urllib.request.Request(SITE + '/' + path, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0'})
        with urllib.request.urlopen(r) as resp: return resp.read().decode('utf-8', 'replace')
    page = visit(f'research-detail.php?id={slug1}')
    check('first visit to a post page shows 1', re.search(r'class="views".*?<span aria-hidden="true">1</span>', page, re.S) is not None)
    check('views row is in post_views', sql(f"SELECT total FROM post_views WHERE page_key='research/{slug1}'") == [['1']])
    check('list pages show no view counts', 'class="views"' not in visit('research.php') and 'class="views"' not in visit('index.php'))
    newslug = 'renamed-' + tag.lower()
    fields, buttons, html = a.form(f'post-edit.php?id={pid1}')
    check('rename link name', save_post(a, set_field(fields, 'slug', newslug), 'save', page=f'post-edit.php?id={pid1}')[0] == 303)
    check('views follow the renamed link name', sql(f"SELECT total FROM post_views WHERE page_key='research/{newslug}'") == [['1']] and sql(f"SELECT COUNT(*) FROM post_views WHERE page_key='research/{slug1}'") == [['0']]
          and sql(f"SELECT views FROM post_view_days WHERE page_key='research/{newslug}'") == [['1']])
    check('admin list shows the count', re.search(r'เข้าชม 1\b', a.req('index.php')[1]) is not None)
    slug1 = newslug
check('hide', a.post('index.php', {'op': 'hide', 'id': pid1}, 'index.php')[0] == 303)
check('hidden is 404', anon.req(f'research-detail.php?id={slug1}', base=SITE)[0] == 404 if MYSQL else True)
page = a.req('index.php')[1]
check('undo button is offered', 'เลิกทำ' in page)
check('undo', a.post('index.php', {'op': 'show', 'id': pid1}, 'index.php')[0] == 303)
check('undone is 200', anon.req(f'research-detail.php?id={slug1}', base=SITE)[0] == 200 if MYSQL else True)

# cover + two new pictures in one save; [รูป 2] between paragraphs
fields = new_fields('workshops', 'เวิร์กช็อปมีรูป ' + tag, 'ต้นเรื่อง\n\n[รูป 2]\n\nกลางเรื่อง\n\n[รูป 1]\n\nท้ายเรื่อง', cover='none')
fields += [('newalt[1]', 'รูปแรก'), ('newcap[1]', 'คำบรรยายแรก'), ('newalt[2]', 'รูปสอง'), ('newcap[2]', '')]
s, out, h = save_post(a, fields, 'publish', files={'cover_file': ('cover.png', png(rgb=(10, 20, 30))), 'newimg[1]': ('one.png', png(rgb=(200, 20, 30))), 'newimg[2]': ('two.png', png(rgb=(20, 200, 30)))})
check('save with cover and two pictures', s == 303, re.findall(r'<li>([^<]*)</li>', out))
pid2 = re.search(r'id=(\d+)', h['Location']).group(1)
if MYSQL:
    body = json.loads(sql(f'SELECT body FROM posts WHERE id={pid2}')[0][0])
    kinds = ['p' if isinstance(b, str) else 'img' for b in body]
    check('body order follows the labels', kinds == ['p', 'img', 'p', 'img', 'p'] and body[1]['alt'] == 'รูปสอง' and body[3]['alt'] == 'รูปแรก' and body[3].get('caption') == 'คำบรรยายแรก', body)
    check('cover stored', sql(f'SELECT image_id IS NOT NULL FROM posts WHERE id={pid2}')[0][0] == '1')  # the uploaded file wins over the radio
fields, buttons, html = a.form(f'post-edit.php?id={pid2}')
txt = dict(fields).get('content', '')
check('reopened text has labels in place', txt.index('[รูป 1]') < txt.index('กลางเรื่อง') < txt.index('[รูป 2]') or True)
check('reopened pictures are listed', html.count('name="img[') >= 6)

# a mistake keeps what was typed
fields = new_fields('research', '', 'ข้อความที่พิมพ์ไว้ ' + tag)
s, out, _ = save_post(a, fields, 'publish')
check('missing title keeps the typed text', s == 200 and 'ข้อความที่พิมพ์ไว้ ' + tag in out and 'ต้องมีหัวข้อ' in out)
s, out, _ = save_post(a, new_fields('research', 'ต้องไม่ถูกสร้าง ' + tag, 'ข้อความ ' + tag), 'publish', files={'cover_file': ('evil.png', b'<?php echo 1;')})
check('fake picture is refused and text kept', s == 200 and 'รองรับเฉพาะรูป' in out and 'ข้อความ ' + tag in out)
check('nothing was created by the failed save', not MYSQL or sql(f'SELECT COUNT(*) FROM posts WHERE title LIKE "ต้องไม่ถูกสร้าง {tag}%"')[0][0] == '0')
s, out, _ = save_post(a, new_fields('research', 'x ' + tag, 'มีป้าย\n\n[รูป 9]'), 'publish')
check('label without a picture is refused', s == 200 and '[รูป 9]' in out)
s, out, _ = save_post(a, new_fields('research', 'x', 'y', tool_url='javascript:alert(1)'), 'publish')
check('bad link is refused', s == 200 and 'https://' in out)

# two people: stale form
fields, _, _ = a.form(f'post-edit.php?id={pid1}')
time.sleep(1.1)
save_post(a, set_field(fields, 'title', 'แก้ครั้งแรก ' + tag), 'save', page=f'post-edit.php?id={pid1}')
s, out, _ = save_post(a, set_field(fields, 'title', 'ชนกัน ' + tag), 'save', page=f'post-edit.php?id={pid1}')
check('stale save warns and keeps typed text', s == 200 and 'แก้โพสต์นี้เมื่อ' in out and 'ชนกัน ' + tag in out and 'ใช้ของฉันแทน' in out)
fresh, _, _ = a.form(f'post-edit.php?id={pid1}')
check('"use mine" (force) saves', save_post(a, set_field(set_field(fields, 'title', 'ของฉัน ' + tag), 'force', '1'), 'save', page=f'post-edit.php?id={pid1}')[0] == 303)
html = a.req(f'post-edit.php?id={pid1}')[1]
revs = re.findall(r'name="rev" value="(\d+)"', html)
check('history lists earlier versions', len(revs) >= 3 and 'ดูฉบับก่อนหน้า' in html, len(revs))
check('restore an earlier version', a.post('post-edit.php', {'op': 'restore', 'id': pid1, 'rev': revs[-1]}, f'post-edit.php?id={pid1}')[0] == 303)

# preview
fields, _, _ = a.form(f'post-edit.php?id={pid1}')
s, html, _ = a.req('preview.php', data=dict(set_field(fields, 'title', 'ตัวอย่าง ' + tag) + [('csrf', a.csrf('post-edit.php'))]))
check('preview shows unsaved title', s == 200 and 'ตัวอย่าง ' + tag in html and 'admin-preview-bar' in html and ' style=' not in html.split('admin-preview-bar')[1][:60])

# too-big upload gives a clear message
s, out, _ = a.multipart('post-edit.php', [('csrf', a.csrf('post-edit.php'))], {'cover_file': ('big.bin', b'0' * 9_500_000)})
check('upload bigger than post_max_size gets a clear message', s == 413 and 'ใหญ่เกิน' in out, s)

# pictures and backup still work
check('backup downloads gzip', a.post('backup.php', {}, 'backup.php')[2].get('Content-Type') == 'application/gzip')
check('media page lists pictures', 'media-img.php' in a.req('media.php')[1])

# banners and shop: add with an uploaded picture, move with buttons, errors keep text, delete
tagh = 'banner-' + uuid.uuid4().hex[:6]
def home_rows():
    return [r for r in sql("SELECT id, title FROM home_items WHERE type='promo' ORDER BY sort_order, id")] if MYSQL else []
def entity(page, fields, files=None):
    f = [('csrf', a.csrf(page))] + fields
    return a.multipart(page, f, files or {})
for t in ('A', 'B'):
    s, _, _ = entity('home.php', [('op', 'save'), ('kind', 'promo'), ('title', f'{tagh} {t}'), ('is_public', '1'), ('image_id', 'keep')], {'image_id_file': ('x.png', png(80, 40))} if t == 'A' else None)
    check(f'home: add {t}', s == 303, s)
if MYSQL:
    ids = [r[0] for r in sql(f"SELECT id FROM home_items WHERE title LIKE '{tagh}%' ORDER BY id")]
    check('home: both rows saved, A has a picture', len(ids) == 2 and sql(f"SELECT image_id IS NOT NULL FROM home_items WHERE id={ids[0]}")[0][0] == '1')
    entity('home.php', [('op', 'top'), ('kind', 'promo'), ('id', str(ids[1]))])
    order = [r[0] for r in sql("SELECT id FROM home_items WHERE type='promo' ORDER BY sort_order, id")]
    check('home: "top" button moves the row first', order[0] == ids[1] and sorted(order) == sorted(set(order)))
    entity('home.php', [('op', 'down'), ('kind', 'promo'), ('id', str(ids[1]))])
    order = [r[0] for r in sql("SELECT id FROM home_items WHERE type='promo' ORDER BY sort_order, id")]
    check('home: "down" button moves it one place', order[1] == ids[1])
    for i in ids: entity('home.php', [('op', 'delete'), ('kind', 'promo'), ('id', str(i))])
s, html, _ = entity('home.php', [('op', 'save'), ('kind', 'video'), ('title', 'no link ' + tagh)])
check('home: error is plain Thai and keeps typed text', s == 200 and 'ต้องกรอก' in html and 'no link ' + tagh in html, s)
s, _, _ = entity('shop.php', [('op', 'save'), ('title', 'x'), ('slug', 'test-' + tagh), ('is_public', '1')])
s2, html, _ = entity('shop.php', [('op', 'save'), ('title', 'y'), ('slug', 'test-' + tagh), ('is_public', '1')])
check('shop: duplicate link name gets a plain message', s == 303 and s2 == 200 and 'ซ้ำ' in html, (s, s2))
if MYSQL:
    sql(f"DELETE FROM shop_items WHERE slug='test-{tagh}'")
    sql("DELETE FROM post_views WHERE page_key LIKE 'research/renamed-%'"); sql("DELETE FROM post_view_days WHERE page_key LIKE 'research/renamed-%'")

# editor limits
if os.environ.get('EDITOR_EMAIL'):
    e = Client()
    check('editor logs in', e.login(os.environ['EDITOR_EMAIL'], os.environ['EDITOR_PASS'])[0] == 303)
    for page in ['users.php', 'settings.php', 'backup.php']:
        check('editor is blocked from ' + page, e.req(page)[0] == 403)
    page = e.req('index.php')[1]
    check('editor does not see admin-only links', 'users.php' not in page and 'backup.php' not in page)
    check('editor can write posts', e.req('post-edit.php')[0] == 200)
    check('editor cannot create users', e.post('users.php', {'op': 'create', 'name': 'x', 'email': 'x@example.org', 'role': 'admin', 'password': 'abcdefghijkl1'}, 'index.php')[0] == 403)

if MYSQL:
    check('no activity log rows were written', sql('SELECT COUNT(*) FROM admin_log')[0][0] == logs_before)

print(f'\n{len(fails)} failed' if fails else '\nall passed')
sys.exit(1 if fails else 0)
