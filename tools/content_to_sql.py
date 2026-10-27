"""Build the SQL files that move post images and the rest of the post content into MySQL.

Writes, into database/updates/:
  2026-10-27-02-media-NN.sql   every image that a post, a home promo/video or a shop item uses, its 1200x630 share
                               twin from assets/images/og/, and every PDF named by download_url. One INSERT per file,
                               the bytes as a hex literal. Files are split so none is larger than --max-kb (1500 KB),
                               which stays under phpMyAdmin's usual upload limit; a single file bigger than that gets
                               an SQL file of its own.
  2026-10-27-03-content.sql    links posts to their images, fills the post columns added in round 13 (audience,
                               subjects, features, download), copies the home promos/videos (config/home.php) and the
                               shop (data/shop.json), events extras, and the Thai line breaks (data/thai-breaks.json).

Every statement is INSERT IGNORE, INSERT ... ON DUPLICATE KEY UPDATE, or an UPDATE that only fills empty values, so
running a file again never overwrites what was edited in phpMyAdmin.

Design images (logo, favicon, hero-*, home-hero-campus, og-default, icons, content-placeholder) stay as files.

Usage:  pip install pillow
        python3 tools/content_to_sql.py [--max-kb 1500] [--prefix 2026-10-27]
"""
import argparse
import json
import subprocess
import sys
from pathlib import Path

try:
    from PIL import Image
except ImportError:
    sys.exit('Pillow is not installed. Run: pip install pillow')

BASE = Path(__file__).resolve().parents[1]
DATA = BASE / 'data'
OUT = BASE / 'database' / 'updates'
DATASETS = ('research', 'innovations', 'workshops', 'media', 'news')
MIME = {'.webp': 'image/webp', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.pdf': 'application/pdf'}


def q(value):
    if value is None:
        return 'NULL'
    if isinstance(value, bool):
        return '1' if value else '0'
    if isinstance(value, (int, float)):
        return str(value)
    text = str(value).replace('\\', '\\\\').replace("'", "\\'").replace('\n', '\\n').replace('\r', '\\r').replace('\x00', '')
    return "'" + text + "'"


def jq(value):
    return q(None if value in (None, [], '') else json.dumps(value, ensure_ascii=False))


def load(name):
    return json.loads((DATA / (name + '.json')).read_text(encoding='utf-8'))


def php_config(path):
    result = subprocess.run(['php', '-r', 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE);', path],
                            cwd=BASE, capture_output=True, text=True, check=True)
    return json.loads(result.stdout)


HEADER = """-- {title}
-- สร้างโดย tools/content_to_sql.py จากไฟล์ใน repo ห้ามแก้ด้วยมือ
--
-- วิธีรัน: phpMyAdmin > คลิก e2imedu > แท็บ Import > Choose file > เลือกไฟล์นี้ > Import
--          (ไฟล์ใหญ่ วางในแท็บ SQL อาจช้าหรือค้าง ให้ใช้แท็บ Import)
-- ลำดับ: 2026-10-27-01-structure.sql > 2026-10-27-02-media-01.sql, -02.sql, … (ทุกไฟล์) > 2026-10-27-03-content.sql
-- รันซ้ำได้ ไม่เขียนทับสิ่งที่แก้ไว้ใน phpMyAdmin
SET NAMES utf8mb4;
"""


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--max-kb', type=int, default=1500)
    parser.add_argument('--prefix', default='2026-10-27')
    args = parser.parse_args()

    records = {name: load(name) for name in DATASETS}
    shop = load('shop')
    home = php_config('config/home.php')

    # ---- which files are content ----
    used = {}   # path -> alt text found in a body block ('' if none)

    def use(path, alt=''):
        if isinstance(path, str) and path.startswith(('assets/images/', 'assets/files/')):
            if not used.get(path):
                used[path] = alt or used.get(path, '')

    for name, rows in records.items():
        for record in rows:
            use(record.get('image'))
            use(record.get('download_url'))
            for block in record.get('body', []):
                if isinstance(block, dict):
                    use(block.get('image'), block.get('alt', ''))
    for item in shop:
        use(item.get('image'))
    for row in home.get('promos', []) + home.get('videos', []):
        use(row.get('image'))

    files = []
    for path in sorted(used):
        source = BASE / path
        if not source.is_file():
            sys.exit('missing file: ' + path)
        files.append((path, source, used[path], None))
        twin = BASE / 'assets' / 'images' / 'og' / (source.stem + '.jpg')
        if path.startswith('assets/images/') and twin.is_file():
            files.append(('assets/images/og/' + twin.name, twin, '', path))

    # ---- media files ----
    for old in OUT.glob(args.prefix + '-02-media-*.sql'):
        old.unlink()
    chunks, current, size = [], [], 0
    limit = args.max_kb * 1024
    for path, source, alt, og_of in files:
        raw = source.read_bytes()
        mime = MIME[source.suffix.lower()]
        width = height = None
        if mime.startswith('image/'):
            with Image.open(source) as image:
                width, height = image.size
        statement = ('INSERT IGNORE INTO media (filename, mime, width, height, alt_default, data) VALUES ('
                     + ', '.join([q(path), q(mime), q(width), q(height), q(alt or None), '0x' + raw.hex()]) + ');')
        cost = len(statement.encode()) + 200
        if current and size + cost > limit:
            chunks.append(current)
            current, size = [], 0
        current.append((path, statement))
        size += cost
    if current:
        chunks.append(current)
    total = len(chunks)
    for number, chunk in enumerate(chunks, 1):
        name = '%s-02-media-%02d.sql' % (args.prefix, number)
        text = HEADER.format(title='Round 13 (2/3): รูปภาพของโพสต์ ไฟล์ %d จาก %d' % (number, total))
        text += ('-- ถ้า Import แล้วขึ้น "MySQL server has gone away" หรือ "Got a packet bigger than max_allowed_packet":\n'
                 '--   ไฟล์นี้มีรูปที่ใหญ่เกินค่า max_allowed_packet ของ server ให้รัน\n'
                 '--   python3 tools/content_to_sql.py --max-kb 700 เพื่อแบ่งไฟล์ให้เล็กลง หรือขอผู้ดูแล hosting เพิ่มค่านี้\n'
                 '--   (รูปที่ Import สำเร็จแล้วจะไม่ถูกเพิ่มซ้ำ)\n')
        text += '-- ในไฟล์นี้: ' + ', '.join(path for path, _ in chunk) + '\n\n'
        text += '\n'.join(statement for _, statement in chunk) + '\n'
        (OUT / name).write_text(text, encoding='utf-8')
        print('wrote', name, '%d KB' % (len(text.encode()) // 1024), len(chunk), 'files')

    # ---- content ----
    out = [HEADER.format(title='Round 13 (3/3): ผูกโพสต์กับรูป, ประชาสัมพันธ์/Scoop, ร้านค้า และการตัดคำไทย')]
    out.append('-- 1. ภาพแชร์ 1200x630 รู้ว่าเป็นของรูปไหน')
    out.append("UPDATE media t JOIN media s ON t.filename = CONCAT('assets/images/og/', SUBSTRING_INDEX(SUBSTRING_INDEX(s.filename, '/', -1), '.', 1), '.jpg')\n"
               "  SET t.og_of = s.id WHERE t.og_of IS NULL AND s.og_of IS NULL AND t.id <> s.id AND s.filename LIKE 'assets/images/%' AND s.filename NOT LIKE 'assets/images/og/%';")
    out.append('\n-- 2. รูปปกของโพสต์: image_id จากชื่อไฟล์เดิม (เฉพาะแถวที่ยังไม่ได้ตั้ง)')
    out.append('UPDATE posts p JOIN media m ON m.filename = p.image SET p.image_id = m.id WHERE p.image_id IS NULL;')
    out.append('\n-- 3. คอลัมน์ใหม่ของโพสต์ (เติมเฉพาะช่องที่ยังว่าง)')
    filled = 0
    for name, rows in records.items():
        for record in rows:
            where = ' WHERE kind = %s AND slug = %s' % (q(name), q(record['id']))
            for key in ('audience', 'subjects', 'features'):
                if record.get(key):
                    out.append('UPDATE posts SET %s = %s%s AND %s IS NULL;' % (key, jq(record[key]), where, key))
                    filled += 1
            if record.get('download_url'):
                out.append('UPDATE posts p JOIN media m ON m.filename = %s SET p.download_id = m.id%s AND p.download_id IS NULL;'
                           % (q(record['download_url']), where.replace('kind', 'p.kind').replace('slug', 'p.slug')))
                filled += 1
            if record.get('download_label'):
                out.append('UPDATE posts SET download_label = %s%s AND download_label IS NULL;' % (q(record['download_label']), where))
                filled += 1
    if not filled:
        out.append('-- (ยังไม่มีโพสต์ที่ใช้ audience, subjects, features หรือ download_url)')

    out.append('\n-- 4. ประชาสัมพันธ์และ Scoop จาก config/home.php (เพิ่มเฉพาะรายการที่ยังไม่มีชื่อนี้)')
    for kind, rows in (('promo', home.get('promos', [])), ('video', home.get('videos', []))):
        for n, row in enumerate(rows):
            image = row.get('image') or None
            values = ', '.join([q(kind), q(row.get('title', '')), q(row.get('date') or None), q(row.get('label') or None),
                                '(SELECT id FROM media WHERE filename = %s)' % q(image) if image else 'NULL', q(image),
                                q(row.get('url') or None), q(row.get('youtube') or None), '1', str(n)])
            out.append('INSERT INTO home_items (type, title, item_date, label, image_id, image, url, youtube, is_public, sort_order)\n'
                       '  SELECT %s FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM home_items WHERE type = %s AND title = %s);'
                       % (values, q(kind), q(row.get('title', ''))))

    out.append('\n-- 5. ร้านค้าจาก data/shop.json')
    for n, item in enumerate(shop):
        image = item.get('image') or None
        out.append('INSERT IGNORE INTO shop_items (slug, title, summary, image_id, image, price, order_url, is_public, sort_order) VALUES ('
                   + ', '.join([q(item['id']), q(item['title']), q(item.get('summary')),
                                '(SELECT id FROM media WHERE filename = %s)' % q(image) if image else 'NULL', q(image),
                                q(None if item.get('price') in (None, '') else str(item['price'])), q(item.get('order_url') or None),
                                q(item.get('public', True) is not False), str(n)]) + ');')
    if not shop:
        out.append('-- (data/shop.json ยังว่าง)')

    out.append('\n-- 6. กิจกรรม: slug, featured และ is_public จาก data/events.json')
    events = load('events')
    for event in events:
        out.append('UPDATE events SET slug = %s, featured = %s, is_public = %s WHERE title = %s AND slug IS NULL;'
                   % (q(event.get('id')), q(bool(event.get('featured'))), q(event.get('public', True) is not False), q(event.get('title'))))
    if not events:
        out.append('-- (data/events.json ยังว่าง)')

    out.append('\n-- 7. การตัดคำไทยจาก data/thai-breaks.json (ใช้ข้อมูลใหม่ทับเสมอ เพราะผูกกับข้อความแต่ละย่อหน้า)')
    breaks = json.loads((DATA / 'thai-breaks.json').read_text(encoding='utf-8')).get('paragraphs', {})
    out.extend(thai_break_statements(breaks))

    out.append('\n-- ตรวจผล')
    out.append('SELECT kind, COUNT(*) AS posts, SUM(image_id IS NOT NULL) AS with_image_id, SUM(image IS NOT NULL AND image_id IS NULL) AS cover_not_linked FROM posts GROUP BY kind;')
    out.append('SELECT type, COUNT(*) FROM home_items GROUP BY type;')
    out.append('SELECT COUNT(*) AS media_files, ROUND(SUM(bytes) / 1048576, 1) AS mb, SUM(og_of IS NOT NULL) AS share_images FROM media;')
    name = args.prefix + '-03-content.sql'
    (OUT / name).write_text('\n'.join(out) + '\n', encoding='utf-8')
    print('wrote', name, len(files), 'media files', len(breaks), 'thai paragraphs')


def thai_break_statements(breaks):
    rows = sorted(breaks.items())
    lines = []
    for start in range(0, len(rows), 50):
        part = rows[start:start + 50]
        lines.append('INSERT INTO thai_breaks (hash, offsets) VALUES\n  '
                     + ',\n  '.join('(%s, %s)' % (q(key), q(json.dumps(value, separators=(',', ':')))) for key, value in part)
                     + '\nON DUPLICATE KEY UPDATE offsets = VALUES(offsets);')
    return lines


if __name__ == '__main__':
    main()
