"""Find Thai word boundaries in post text so pages wrap lines between words, not inside them.

Browsers break Thai lines with a built-in dictionary that splits unfamiliar words, such as
loanwords and names ("เวิร์|กช็อป"). This script segments every post summary and body
paragraph with PyThaiNLP and saves the word boundaries to data/thai-breaks.json. The detail
page (thai_text() in includes/functions.php) then lets the browser break lines only there.

Usage (once per content change, before uploading; validate_content.py reminds you):
    pip install pythainlp
    python3 tools/segment_thai.py            (texts from data/*.json)
    python3 tools/segment_thai.py --mysql    (texts as the live site reads them, needs private/database.php)
    python3 tools/segment_thai.py --sql      (also write database/thai-breaks.sql for the thai_breaks table)
    python3 tools/segment_thai.py --phpmyadmin-json posts.json
        Texts edited in phpMyAdmin: export the posts table (phpMyAdmin > posts > Export > Format: JSON), then run this.
        Writes only database/thai-breaks.sql; import it in phpMyAdmin (Import tab). data/thai-breaks.json is untouched.

Each entry is keyed by a hash of the paragraph text, so editing a paragraph never shows stale
breaks: until this script runs again, that paragraph renders as plain text, as before.
"""
import hashlib
import json
import re
import sys
from pathlib import Path

try:
    from pythainlp.tokenize import word_tokenize
except ImportError:
    sys.exit('PyThaiNLP is not installed. Run: pip install pythainlp')

base = Path(__file__).resolve().parents[1]
DATASETS = ('research', 'innovations', 'workshops', 'media', 'news')
THAI = re.compile(r'[฀-๿]')


def mysql_records():
    """Records as the live site reads them (MySQL when private/database.php exists, else data/*.json)."""
    import subprocess
    code = ('require "includes/functions.php"; $all = []; '
            'foreach (%s as $n) { $all[$n] = load_data($n); } echo json_encode($all, JSON_UNESCAPED_UNICODE);'
            % json.dumps(list(DATASETS)))
    result = subprocess.run(['php', '-r', code], cwd=base, capture_output=True, text=True)
    if result.returncode != 0:
        sys.exit('Could not read content through PHP: ' + result.stderr.strip())
    return json.loads(result.stdout)


FROM_MYSQL = '--mysql' in sys.argv
WRITE_SQL = '--sql' in sys.argv
PHPMYADMIN = sys.argv[sys.argv.index('--phpmyadmin-json') + 1] if '--phpmyadmin-json' in sys.argv[:-1] else None
_live = None


def phpmyadmin_rows(path):
    """Rows of the posts table from a phpMyAdmin JSON export (which may start with a /** … */ comment)."""
    text = Path(path).read_text(encoding='utf-8')
    # Older phpMyAdmin versions put /** … */ and // comment lines before the JSON array.
    text = re.sub(r'\A(?:\s*(?:/\*.*?\*/|//[^\n]*))*\s*', '', text, flags=re.S)
    rows = []
    for item in json.loads(text):
        if isinstance(item, dict) and item.get('type') == 'table' and item.get('name') == 'posts':
            rows.extend(item.get('data') or [])
    if not rows:
        sys.exit('No rows of the posts table in ' + path + ' (export the posts table as JSON)')
    return rows


def records_for(name):
    """data/*.json by default. With --mysql the texts that the site shows from the database.
    With --phpmyadmin-json the posts table exported from phpMyAdmin."""
    global _live
    if PHPMYADMIN:
        if _live is None:
            _live = {}
            for row in phpmyadmin_rows(PHPMYADMIN):
                body = json.loads(row.get('body') or '[]')
                _live.setdefault(row.get('kind'), []).append({'summary': row.get('summary'), 'body': body if isinstance(body, list) else []})
        return _live.get(name, [])
    if FROM_MYSQL:
        if _live is None:
            _live = mysql_records()
        return _live[name]
    return json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8'))


def post_texts():
    """Every summary and body paragraph that the detail pages render, plus the hero intros."""
    for name in DATASETS:
        for record in records_for(name):
            if isinstance(record.get('summary'), str):
                yield record['summary']
            for block in record.get('body', []):
                if isinstance(block, str):
                    yield block
    # Hero intros from config/heroes.php (page banners, home slides, the closing call and the footer).
    import subprocess
    try:
        heroes = json.loads(subprocess.run(['php', '-r', 'echo json_encode(require "config/heroes.php");'],
                                           cwd=base, capture_output=True, text=True, check=True).stdout)
    except (OSError, subprocess.CalledProcessError, ValueError):
        heroes = {}
    for hero in heroes.values():
        if isinstance(hero.get('intro'), str):
            yield hero['intro']


def text_key(text):
    return hashlib.sha1(text.encode('utf-8')).hexdigest()


def boundaries(text):
    """Character offsets where one word ends and the next begins."""
    offsets, pos = [], 0
    for token in word_tokenize(text, engine='newmm', keep_whitespace=True):
        pos += len(token)
        offsets.append(pos)
    return offsets[:-1]


def main():
    paragraphs = {}
    for text in post_texts():
        if THAI.search(text):
            paragraphs[text_key(text)] = boundaries(text)
    out = {
        'about': 'Generated by tools/segment_thai.py. Do not edit by hand.',
        'engine': 'pythainlp newmm',
        'paragraphs': dict(sorted(paragraphs.items())),
    }
    if not PHPMYADMIN:
        target = base / 'data' / 'thai-breaks.json'
        target.write_text(json.dumps(out, ensure_ascii=False, separators=(',', ':')) + '\n', encoding='utf-8')
        print(f'Segmented {len(paragraphs)} paragraphs into {target.relative_to(base)}')
    if WRITE_SQL or PHPMYADMIN:
        write_sql(out['paragraphs'])


def write_sql(paragraphs):
    """database/thai-breaks.sql: INSERT … ON DUPLICATE KEY UPDATE into thai_breaks. Safe to import again."""
    rows = list(paragraphs.items())
    lines = ['-- Thai line breaks for the thai_breaks table. Generated by tools/segment_thai.py; do not edit by hand.',
             '-- phpMyAdmin > e2imedu > Import > choose this file > Import. Safe to import again; old rows are kept.',
             'SET NAMES utf8mb4;']
    for start in range(0, len(rows), 50):
        values = ',\n  '.join("('%s', '%s')" % (key, json.dumps(value, separators=(',', ':'))) for key, value in rows[start:start + 50])
        lines.append('INSERT INTO thai_breaks (hash, offsets) VALUES\n  ' + values + '\nON DUPLICATE KEY UPDATE offsets = VALUES(offsets);')
    target = base / 'database' / 'thai-breaks.sql'
    target.write_text('\n'.join(lines) + '\n', encoding='utf-8')
    print(f'Wrote {len(rows)} paragraphs to {target.relative_to(base)}')


if __name__ == '__main__':
    main()
