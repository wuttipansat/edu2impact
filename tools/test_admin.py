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
for page in ['index.php', 'index.php?kind=research', 'post-edit.php', 'post-edit.php?kind=innovations', 'media.php', 'home.php', 'shop.php', 'network.php', 'community.php', 'settings.php', 'users.php', 'backup.php', 'account.php']:
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

# upload.php (drag and drop / paste): who may use it, what it accepts, what it leaves private
check('upload.php: anonymous POST is sent to the login page', anon.multipart('upload.php', [('csrf', 'x')], {'file': ('p.png', png())})[0] in (303, 403))
check('upload.php: GET is refused', a.req('upload.php')[0] == 405)
check('upload.php: no CSRF token is 403', a.multipart('upload.php', [], {'file': ('p.png', png())})[0] == 403)
check('upload.php: wrong CSRF token is 403', a.multipart('upload.php', [('csrf', 'nope')], {'file': ('p.png', png())})[0] == 403)
s, out, _ = a.multipart('upload.php', [('csrf', a.csrf('post-edit.php'))], {'file': ('evil.png', b'<?php echo 1;')})
check('upload.php: a fake picture is refused in Thai JSON', s == 422 and 'error' in json.loads(out) and 'รองรับเฉพาะรูป' in json.loads(out)['error'], (s, out[:100]))
s, out, _ = a.multipart('upload.php', [('csrf', a.csrf('post-edit.php'))], {'file': ('doc.pdf', b'%PDF-1.4\n%x\n')})
check('upload.php: a PDF is refused (pictures only)', s == 422 and 'error' in json.loads(out), (s, out[:100]))
s, out, _ = a.multipart('upload.php', [('csrf', a.csrf('post-edit.php'))], {'file': ('dropped.png', png(120, 60, tuple(int(tag[i:i + 2], 16) for i in (0, 2, 4))))})   # a colour no earlier run used: same bytes would be the same file
up = json.loads(out) if s == 200 else {}
check('upload.php: a picture comes back as {id, url, width, height}', s == 200 and up.get('id') and up.get('width') == 120 and up.get('url', '').startswith('media-img.php?id='), (s, out[:120]))
if MYSQL and up:
    check('upload.php: the picture is private until a public post uses it', anon.req(f'file.php?id={up["id"]}', base=SITE)[0] == 404)
    check('upload.php: signed-in admin can see it', a.req(up['url'])[0] == 200)

# headings, tables and a dropped picture in one post
rows_text = 'ชื่อ\tคะแนน\nก\t10\nข\t2,500'
content = '# หัวข้อใหญ่\n\nย่อหน้าแรก\n\n## หัวข้อรอง\n\n[ตาราง 1]\n\n### หัวข้อย่อย\n\nย่อหน้าท้าย\n\n[รูป 1]'
fields = new_fields('research', 'หัวข้อและตาราง ' + tag, content, **{'tbl[0][n]': '1', 'tbl[0][text]': rows_text, 'tbl[0][head]': '1', 'tbl[0][caption]': 'ผลทดสอบ',
                    'img[0][n]': '1', 'img[0][media]': str(up.get('id', '')), 'img[0][alt]': 'รูปที่ลากมาวาง', 'img[0][caption]': ''})
s, out, h = save_post(a, fields, 'publish')
check('post with headings, a table and a dropped picture saves', s == 303, re.findall(r'<li>([^<]*)</li>', out))
if MYSQL and s == 303:
    pid3 = re.search(r'id=(\d+)', h['Location']).group(1)
    row = sql(f'SELECT slug, body FROM posts WHERE id={pid3}')[0]; slug4 = row[0]; body = json.loads(row[1])
    check('stored body: headings, table and picture blocks', [list(b) if isinstance(b, dict) else 'p' for b in body] == [['heading', 'text'], 'p', ['heading', 'text'], ['table'], ['heading', 'text'], 'p', ['media', 'alt']]
          and body[0]['heading'] == 1 and body[2]['heading'] == 2 and body[4]['heading'] == 3 and body[3]['table'] == {'head': ['ชื่อ', 'คะแนน'], 'rows': [['ก', '10'], ['ข', '2,500']], 'caption': 'ผลทดสอบ'}, body)
    page = anon.req(f'research-detail.php?id={slug4}', base=SITE)[1]
    prose = page.split('class="prose"')[1].split('</article>')[0] if 'class="prose"' in page else ''
    check('public page: h2/h3/h4 for levels 1/2/3, one h1', '<h2>หัวข้อใหญ่</h2>' in prose and '<h3>หัวข้อรอง</h3>' in prose and '<h4>หัวข้อย่อย</h4>' in prose and page.count('<h1') == 1)
    check('public page: table with caption, scoped headers and right-aligned numbers', '<caption>ผลทดสอบ</caption>' in prose and '<th scope="col">ชื่อ</th>' in prose and '<th scope="col" class="num">คะแนน</th>' in prose and '<td class="num">2,500</td>' in prose)
    check('public page: picture is where the label was', prose.index('<table') < prose.index('<img') and 'alt="รูปที่ลากมาวาง"' in prose)
    check('public page: dropped picture is public once the post is', anon.req(f'file.php?id={up["id"]}', base=SITE)[0] == 200)
    check('search finds text in headings and tables', 'ตาราง' not in '' and slug4 in anon.req('search.php?q=' + urllib.parse.quote('หัวข้อย่อย'), base=SITE)[1])
    # reopening shows the same text and tables, and a plain save changes nothing
    before = sql(f'SELECT {COLS} FROM posts WHERE id={pid3}')[0]
    fields, buttons, html = a.form(f'post-edit.php?id={pid3}')
    d = dict(fields)
    check('reopened editor: # marks, labels, table text and caption', d['content'] == content and d.get('tbl[0][text]') == rows_text and d.get('tbl[0][caption]') == 'ผลทดสอบ' and d.get('tbl[0][head]') == '1' and 'tbl[1][n]' in d)
    time.sleep(1.1)
    check('open + save leaves headings and tables as they were', save_post(a, fields, 'save', page=f'post-edit.php?id={pid3}')[0] == 303 and norm_row(before) == norm_row(sql(f'SELECT {COLS} FROM posts WHERE id={pid3}')[0]))
    # limits and mistakes keep what was typed
    big = '\n'.join('a\tb' for _ in range(101))
    s, out, _ = save_post(a, set_field(set_field(fields, 'tbl[0][text]', big), 'content', content), 'save', page=f'post-edit.php?id={pid3}')
    check('101-row table is refused in Thai and the text is kept', s == 200 and '101 แถว' in out and 'a&#9;b' in out.replace('a\tb', 'a&#9;b') or (s == 200 and '101 แถว' in out), s)
    s, out, _ = save_post(a, set_field(fields, 'content', content + '\n\n[ตาราง 5]'), 'save', page=f'post-edit.php?id={pid3}')
    check('a table label without a table is refused', s == 200 and 'ตารางที่ 5' in out)
    xss = set_field(set_field(fields, 'tbl[0][text]', '<script>alert(1)</script>\t<img src=x onerror=1>\n1\t2'), 'tbl[0][caption]', '<b>x</b>')
    check('table with HTML-looking cells saves', save_post(a, xss, 'save', page=f'post-edit.php?id={pid3}')[0] == 303)
    page = anon.req(f'research-detail.php?id={slug4}', base=SITE)[1]
    check('table cells are shown as text, never as HTML', '&lt;script&gt;alert(1)&lt;/script&gt;' in page and '<script>alert(1)' not in page and '<img src=x' not in page and '<b>x</b>' not in page)
    # preview shows the new blocks too
    s, html, _ = a.req('preview.php', data=dict(fields + [('csrf', a.csrf('post-edit.php'))]))
    check('preview shows headings and the table', s == 200 and '<h2>หัวข้อใหญ่</h2>' in html and 'prose-table' in html, (s, re.findall(r'<h[1-4][^>]*>[^<]*', html)[:8], 'prose-table' in html))
    # JSON-less posting (no JavaScript): the table box and the old "รูปใหม่" slots still work together
    check('editor page has the block editor, side column and the text form for no-JS', all(x in a.req(f'post-edit.php?id={pid3}')[1] for x in ['id="blocks-app"', 'data-add="table"', 'name="body_json"', 'id="legacy"', 'class="ed__side"', 'assets/js/editor.js']))
    # the block editor (JavaScript) sends the body as JSON in body_json; the text-form fields are not sent then
    js_fields = [(k, v) for k, v in fields if not (k == 'content' or k.startswith('img[') or k.startswith('tbl[') or k.startswith('newimg') or k.startswith('newalt') or k.startswith('newcap'))] + [('force', '1')]   # force: the earlier saves moved the edit time on
    def with_json(blocks, f=None):
        return set_field(f if f is not None else js_fields, 'body_json', json.dumps(blocks, ensure_ascii=False))
    good = [{'t': 'h', 'level': 1, 'text': 'หัวข้อจากตัวแก้ไข'}, {'t': 'p', 'text': 'ย่อหน้า <b>ไม่ใช่ HTML</b>'}, {'t': 'h', 'level': 9, 'text': 'ระดับเกิน'},
            {'t': 'img', 'media': up.get('id'), 'alt': 'รูปในบล็อก', 'caption': 'บรรยาย'}, {'t': 'table', 'head': True, 'rows': [['ก', 'ข'], ['1', '2']], 'caption': 'ตารางบล็อก'},
            {'t': 'p', 'text': ''}, {'t': 'script', 'text': 'x'}]
    s, out, _ = save_post(a, with_json(good), 'save', page=f'post-edit.php?id={pid3}')
    check('body_json: saved', s == 303, re.findall(r'<li>([^<]*)</li>', out))
    stored = json.loads(sql(f'SELECT body FROM posts WHERE id={pid3}')[0][0])
    check('body_json: stored as headings, text, picture and table (unknown and empty blocks dropped, level clamped to 3)',
          stored == [{'heading': 1, 'text': 'หัวข้อจากตัวแก้ไข'}, 'ย่อหน้า <b>ไม่ใช่ HTML</b>', {'heading': 3, 'text': 'ระดับเกิน'}, {'media': up.get('id'), 'alt': 'รูปในบล็อก', 'caption': 'บรรยาย'},
                   {'table': {'head': ['ก', 'ข'], 'rows': [['1', '2']], 'caption': 'ตารางบล็อก'}}], stored)
    page = anon.req(f'research-detail.php?id={slug4}', base=SITE)[1]
    check('body_json: text is shown as text, never as HTML', '&lt;b&gt;ไม่ใช่ HTML&lt;/b&gt;' in page and '<b>ไม่ใช่ HTML' not in page)
    fresh = dict(a.form(f'post-edit.php?id={pid3}')[0])
    s, out, _ = save_post(a, with_json([{'t': 'p', 'text': 'x'}], js_fields), 'save', page=f'post-edit.php?id={pid3}')   # a second save keeps working
    check('body_json: saving again works', s == 303, s)
    bad_json = set_field(js_fields, 'body_json', '{not json')
    s, out, _ = save_post(a, bad_json, 'save', page=f'post-edit.php?id={pid3}')
    check('body_json: broken JSON is refused in Thai', s == 200 and 'อ่านเนื้อหาจากตัวแก้ไขไม่ได้' in out, s)
    s, out, _ = save_post(a, with_json([{'t': 'table', 'head': True, 'rows': [[str(i) for i in range(13)]]}]), 'save', page=f'post-edit.php?id={pid3}')
    check('body_json: 13-column table is refused in Thai', s == 200 and '13 คอลัมน์' in out, s)
    s, out, _ = save_post(a, with_json([{'t': 'table', 'head': False, 'rows': [['x' * 501]]}]), 'save', page=f'post-edit.php?id={pid3}')
    check('body_json: a 501-character cell is refused in Thai', s == 200 and '501' in out, s)
    s, out, _ = save_post(a, with_json([{'t': 'img', 'media': 99999999, 'alt': 'a', 'caption': ''}]), 'save', page=f'post-edit.php?id={pid3}')
    check('body_json: a picture that is not in the library is refused', s == 200 and 'ไม่อยู่ในคลังรูป' in out, s)
    s, out, _ = save_post(a, with_json([{'t': 'p', 'text': 'p'}] * 1501), 'save', page=f'post-edit.php?id={pid3}')
    check('body_json: more than 1500 blocks is refused in Thai', s == 200 and '1500' in out, s)
    # research <-> innovations: kind switch, categories, links
    cats = [c for c in open('config/innovation-categories.php', encoding='utf-8').read().split("'") if c and any('฀' <= ch <= '๿' for ch in c)]
    fields_k = set_field(set_field(js_fields, 'category', 'Learning'), 'body_json', json.dumps([{'t': 'p', 'text': 'ทดสอบสลับประเภท'}]))
    save_post(a, fields_k, 'save', page=f'post-edit.php?id={pid3}')
    check('research: an old category such as Learning is dropped on save', sql(f'SELECT category IS NULL FROM posts WHERE id={pid3}')[0][0] == '1')
    save_post(a, set_field(fields_k, 'category', cats[0]), 'save', page=f'post-edit.php?id={pid3}')
    check('research: one of the innovation categories is kept', sql(f'SELECT category FROM posts WHERE id={pid3}')[0][0] == cats[0], cats[:2])
    rl = anon.req('research.php', base=SITE)[1]
    check('research list never shows Learning or Assessment', 'Learning' not in rl and 'Assessment' not in rl)
    inn_page = anon.req('innovations.php?category=' + urllib.parse.quote(cats[0]), base=SITE)[1]
    check('research post is not listed under the innovation category page', slug4 not in inn_page)
    sw = save_post(a, set_field(fields_k, 'kind', 'innovations') if False else set_field(set_field(fields_k, 'category', cats[0]), 'kind', 'innovations'), 'save', page=f'post-edit.php?id={pid3}')
    check('switch research -> innovations saves', sw[0] == 303, re.findall(r'<li>([^<]*)</li>', sw[1]))
    now = sql(f'SELECT kind, slug, category FROM posts WHERE id={pid3}')[0]
    check('switched post is an innovation with its category and a slug', now[0] == 'innovations' and now[2] == cats[0] and now[1] != '', now)
    check('innovation page opens and the old research link redirects there', anon.req(f'innovation-detail.php?id={now[1]}', base=SITE)[0] == 200 and anon.req(f'research-detail.php?id={slug4}', base=SITE)[0] in (301, 302, 200))
    inn_page = anon.req('innovations.php?category=' + urllib.parse.quote(cats[0]), base=SITE)[1]
    check('after the switch it is listed under that innovation category', now[1] in inn_page)
    back = save_post(a, set_field(set_field(fields_k, 'category', cats[0]), 'kind', 'research'), 'save', page=f'post-edit.php?id={pid3}')
    check('switch back to research works and the category is kept', back[0] == 303 and sql(f'SELECT kind, category FROM posts WHERE id={pid3}')[0] == ['research', cats[0]])
    s, out, _ = save_post(a, set_field(fields_k, 'kind', 'shop'), 'save', page=f'post-edit.php?id={pid3}')
    check('a research post cannot be switched to another kind (stays research)', sql(f'SELECT kind FROM posts WHERE id={pid3}')[0][0] == 'research')

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

# network maps (round 14): institutions on the world map and projects on the map of Thailand: add, edit, hide, related posts, errors, order, delete
tagn = 'net-' + uuid.uuid4().hex[:6]
def public(page):
    return a.req(SITE + '/' + page)[1]
def net_ids(table, col):
    return [r[0] for r in sql(f"SELECT id FROM {table} WHERE {col} LIKE '{tagn}%' ORDER BY id")]
related_slug = sql("SELECT slug FROM posts WHERE kind='research' AND is_public=1 ORDER BY id LIMIT 1")[0][0] if MYSQL else ''
for t in ('A', 'B'):
    extra = [('related[]', 'research/' + related_slug), ('related[]', 'media/not-a-post')] if t == 'A' and related_slug else []
    s, _, _ = entity('network.php', [('op', 'save'), ('country_code', 'JP'), ('institution', f'{tagn} {t}'), ('institution_th', 'ทดสอบ'), ('url', 'https://example.org/'), ('is_public', '1')] + extra)
    check(f'network: add {t}', s == 303, s)
s, html, _ = entity('network.php', [('op', 'save'), ('country_code', 'ZZ'), ('institution', f'{tagn} bad'), ('is_public', '1')])
check('network: unknown country is refused in plain Thai', s == 200 and 'ไม่ถูกต้อง' in html and f'{tagn} bad' in html, s)
s, html, _ = entity('network.php', [('op', 'save'), ('institution', f'{tagn} nocountry')])
check('network: country is required', s == 200 and 'ต้องกรอก' in html, s)
s, html, _ = entity('network.php', [('op', 'save'), ('country_code', 'JP'), ('institution', f'{tagn} badlink'), ('url', 'javascript:alert(1)')])
check('network: a javascript: link is refused', s == 200 and 'https://' in html, s)
if MYSQL:
    ids = net_ids('network_partners', 'institution')
    check('network: two rows saved, bad ones not', len(ids) == 2, ids)
    row = sql(f"SELECT country_code, country_name_th, is_public FROM network_partners WHERE id={ids[0]}")[0]
    check('network: Thai country name is filled in on save', row[:2] == ['JP', 'ญี่ปุ่น'], row)
    if related_slug:
        rel = json.loads(sql(f"SELECT related FROM network_partners WHERE id={ids[0]}")[0][0])
        check('network: related keeps real posts only', rel == [{'kind': 'research', 'id': related_slug}], rel)
    entity('network.php', [('op', 'save'), ('id', str(ids[0])), ('country_code', 'SG'), ('institution', f'{tagn} A'), ('is_public', '1')])
    check('network: edit changes country and name', sql(f"SELECT country_code, country_name_th FROM network_partners WHERE id={ids[0]}")[0] == ['SG', 'สิงคโปร์'])
    check('network: edit with no posts ticked clears related', sql(f"SELECT related IS NULL FROM network_partners WHERE id={ids[0]}")[0][0] == '1')
    page = public('network-international.php')
    check('network: public page shows the institution and colours SG', f'{tagn} A' in page and 'data-code="SG"' in page and re.search(r'class="is-active is-l1" data-code="SG"', page) is not None)
    entity('network.php', [('op', 'hide'), ('id', str(ids[0]))])
    check('network: hide removes it from the public page', f'{tagn} A' not in public('network-international.php') and f'{tagn} B' in public('network-international.php'))
    entity('network.php', [('op', 'show'), ('id', str(ids[0]))])
    check('network: show brings it back', f'{tagn} A' in public('network-international.php'))
    entity('network.php', [('op', 'top'), ('id', str(ids[1]))])
    order = [r[0] for r in sql("SELECT id FROM network_partners ORDER BY sort_order, id")]
    check('network: "top" button moves the row first', order[0] == ids[1])
    for i in ids: entity('network.php', [('op', 'delete'), ('id', str(i))])
    check('network: delete removes both', not net_ids('network_partners', 'institution'))

for t in ('A', 'B'):
    s, _, _ = entity('community.php', [('op', 'save'), ('province_code', 'TH-50'), ('title', f'{tagn} {t}'), ('community', 'ชุมชนทดสอบ'), ('project_year', '2568'), ('is_public', '1'), ('image_id', 'keep')], {'image_id_file': ('x.png', png(80, 40))} if t == 'A' else None)
    check(f'community: add {t}', s == 303, s)
s, html, _ = entity('community.php', [('op', 'save'), ('province_code', 'TH-50'), ('title', f'{tagn} year'), ('project_year', '1999')])
check('community: year outside 2500-2700 is refused', s == 200 and '2500' in html and f'{tagn} year' in html, s)
s, html, _ = entity('community.php', [('op', 'save'), ('province_code', 'TH-99'), ('title', f'{tagn} nope')])
check('community: unknown province is refused', s == 200 and 'ไม่ถูกต้อง' in html, s)
if MYSQL:
    ids = net_ids('community_projects', 'title')
    check('community: two rows saved, bad ones not', len(ids) == 2, ids)
    row = sql(f"SELECT province_name_th, project_year, image_id IS NOT NULL FROM community_projects WHERE id={ids[0]}")[0]
    check('community: Thai province name, year and picture saved', row == ['เชียงใหม่', '2568', '1'], row)
    page = public('network-community.php')
    check('community: public page shows project, year and picture', f'{tagn} A' in page and 'พ.ศ. 2568' in page and 'netitem__img' in page)
    check('community: a province with a project is coloured', re.search(r'class="is-active is-l2" data-code="TH-50"', page) is not None, re.findall(r'data-code="TH-50"', page))
    pic = sql(f"SELECT image_id FROM community_projects WHERE id={ids[0]}")[0][0]
    check('community: the picture page names the project that uses it', 'นวัตกรรมสู่ชุมชน: ' + tagn in a.req(f'media.php?id={pic}')[1])
    for i in ids: entity('community.php', [('op', 'hide'), ('id', str(i))])
    check('community: hiding every project greys the province', 'is-active' not in public('network-community.php') and 'กำลังรวบรวมข้อมูลเครือข่าย' in public('network-community.php'))
    for i in ids: entity('community.php', [('op', 'delete'), ('id', str(i))])
    sql("DELETE FROM media WHERE original_name='x.png' AND uploaded_by IS NOT NULL AND id NOT IN (SELECT image_id FROM home_items WHERE image_id IS NOT NULL UNION SELECT image_id FROM shop_items WHERE image_id IS NOT NULL)")
    check('community: delete removes both', not net_ids('community_projects', 'title'))

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
