"""Validate the public JSON content before uploading. Python 3; no packages."""
import datetime
import hashlib
import json
import os
import re
import sys
from pathlib import Path

base = Path(__file__).resolve().parents[1]
errors = []
LINK_KINDS = ('research', 'innovations', 'workshops')
records_by_kind = {}  # dataset -> ids, filled as files load; links are checked once every file is loaded
links = []  # (dataset, record id, position, kind, id)
hidden = set()  # (dataset, id) of records with "public": false

# A merge that was committed with its markers still in the file breaks the site without any visible error
# (round 11: data/thai-breaks.json carried "<<<<<<< HEAD", so no Thai line break was ever applied).
CONFLICT = re.compile(r'^(?:<{7}(?: |$)|={7}$|>{7}(?: |$))', re.M)
for folder in ('data', 'config', 'includes', 'database'):
    for path in sorted((base / folder).rglob('*')):
        if path.is_file() and path.suffix in ('.json', '.php', '.sql', '.css', '.js'):
            if CONFLICT.search(path.read_text(encoding='utf-8', errors='replace')):
                errors.append(f'{path.relative_to(base)}: unresolved merge conflict markers (<<<<<<<, =======, >>>>>>>)')
for name in ('site', 'research', 'innovations', 'workshops', 'media', 'news', 'events'):
    try:
        data = json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8'))
        if name == 'site':
            assert isinstance(data, dict), 'must be an object'
            for key in ('name', 'university', 'description', 'email', 'phone', 'address'):
                assert isinstance(data.get(key), str), key + ' must be text'
            assert isinstance(data.get('demo_mode'), bool), 'demo_mode must be true or false'
            site_url = data.get('site_url', '')
            assert isinstance(site_url, str), 'site_url must be text'
            assert site_url == '' or re.fullmatch(r'https://[^\s/]+(?:/[^\s?#]*)?', site_url) and not site_url.endswith('/'), 'site_url must be empty or an https:// address without a trailing slash'
            if site_url == '':
                print('Note: site_url is empty, so share buttons, og:url and og:image are switched off. Set it in data/site.json.')
            continue
        assert isinstance(data, list), 'must be an array'
        if name == 'events':
            seen = set()
            for index, event in enumerate(data):
                assert isinstance(event, dict), 'event must be an object'
                for key in ('id', 'date', 'type', 'title', 'meta'):
                    assert isinstance(event.get(key), str) and event[key].strip(), f'event {index}: {key} is required'
                assert re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', event['id']), 'invalid id ' + event['id']
                assert event['id'] not in seen, 'duplicate id ' + event['id']
                seen.add(event['id'])
                assert re.fullmatch(r'\d{4}-\d{2}-\d{2}', event['date']), f'event {index}: date must be YYYY-MM-DD'
                datetime.date.fromisoformat(event['date'])
                assert isinstance(event.get('featured'), bool), 'featured must be boolean'
                if 'public' in event:
                    assert isinstance(event['public'], bool), f'event {index}: public must be true or false'
            continue
        seen = set()
        for index, record in enumerate(data):
            assert isinstance(record, dict), 'record must be an object'
            # Workshops and research have no required category (research uses the innovation categories when it has one); innovations require it.
            required = ('id', 'title', 'summary') if name in ('workshops', 'research') else ('id', 'title', 'category', 'summary')
            for key in required:
                assert isinstance(record.get(key), str) and record[key].strip(), f'item {index}: {key} is required'
            if 'category' in record:
                assert isinstance(record['category'], str), f'item {index}: category must be text'
                if name == 'research':
                    allowed = re.findall(r"'([^']+)'", open(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'config', 'innovation-categories.php'), encoding='utf-8').read().split('return', 1)[1])
                    assert record['category'] in allowed, f'item {index}: research category must be one of the innovation categories'
            assert re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', record['id']), 'invalid id'
            assert record['id'] not in seen, 'duplicate id ' + record['id']
            seen.add(record['id'])
            assert isinstance(record.get('body'), list), 'body must be an array'
            for position, block in enumerate(record['body']):
                if isinstance(block, str):
                    continue
                where = f'{record["id"]}: body[{position}]'
                assert isinstance(block, dict), where + ' must be text, a heading, a table or an image block'
                if 'heading' in block:
                    assert set(block) == {'heading', 'text'}, where + ' a heading allows only heading and text'
                    assert block['heading'] in (1, 2, 3) and isinstance(block['text'], str) and block['text'].strip(), where + ': heading is 1, 2 or 3 and needs text'
                    continue
                if 'table' in block:
                    assert set(block) == {'table'} and isinstance(block['table'], dict), where + ' a table block holds only "table"'
                    table = block['table']
                    assert set(table) <= {'head', 'rows', 'caption'}, where + ': table allows only head, rows and caption'
                    rows = table.get('rows')
                    assert isinstance(rows, list) and rows and len(rows) <= 100, where + ': table rows must be a list of 1-100 rows'
                    for row in ([table['head']] if table.get('head') else []) + rows:
                        assert isinstance(row, list) and 1 <= len(row) <= 12 and all(isinstance(c, str) and len(c) <= 500 for c in row), where + ': a table row is 1-12 cells of text, 500 characters at most'
                    assert isinstance(table.get('caption', ''), str), where + ': caption must be text'
                    continue
                assert set(block) <= {'image', 'alt', 'caption'}, where + ' allows only image, alt and caption'
                image = block.get('image')
                assert isinstance(image, str) and re.fullmatch(r'assets/images/[a-zA-Z0-9_/-]+\.(?:png|jpg|jpeg|webp)', image), where + ': image must be assets/images/<name>.png|jpg|jpeg|webp'
                assert (base / image).is_file(), where + ': image file not found: ' + image
                assert isinstance(block.get('alt'), str) and block['alt'].strip(), where + ': alt text is required'
                if 'caption' in block:
                    assert isinstance(block['caption'], str), where + ': caption must be text'
            assert isinstance(record.get('featured'), bool), 'featured must be boolean'
            assert isinstance(record.get('public'), bool), f'{record["id"]}: public must be true or false'
            if not record['public']:
                hidden.add((name, record['id']))
            for key in ('date', 'author', 'image'):
                assert isinstance(record.get(key), str), key + ' must be text'
            if record['date']:
                datetime.date.fromisoformat(record['date'])
            if record['image']:
                assert re.fullmatch(r'assets/images/[a-zA-Z0-9_/-]+\.(png|jpg|jpeg|webp)', record['image']), 'invalid image path'
                assert (base / record['image']).is_file(), 'missing image ' + record['image']
            # Optional fields (used by innovations, e.g. a web tool). Absent fields are fine.
            if 'tool_url' in record:
                url = record['tool_url']
                assert isinstance(url, str) and url, f'{record["id"]}: tool_url must be text'
                if not url.startswith('https://'):
                    assert re.fullmatch(r'apps/[a-z0-9-]+/', url), f'{record["id"]}: tool_url must be https://… or apps/<name>/'
                    assert (base / url / 'index.html').is_file(), 'missing ' + url + 'index.html'
            if 'tool_label' in record:
                assert isinstance(record['tool_label'], str) and record['tool_label'].strip(), f'{record["id"]}: tool_label must be text'
                assert 'tool_url' in record, f'{record["id"]}: tool_label needs tool_url'
            if 'download_url' in record:
                url = record['download_url']
                assert isinstance(url, str) and re.fullmatch(r'assets/files/[a-z0-9-]+\.pdf', url), f'{record["id"]}: download_url must be assets/files/<name>.pdf'
                assert (base / url).is_file(), f'{record["id"]}: missing ' + url
            if 'download_label' in record:
                assert isinstance(record['download_label'], str) and record['download_label'].strip(), f'{record["id"]}: download_label must be text'
                assert 'download_url' in record, f'{record["id"]}: download_label needs download_url'
            # Optional links to other records: "related": [{"kind": "research", "id": "…"}, …]
            if 'related' in record:
                related = record['related']
                assert isinstance(related, list), f'{record["id"]}: related must be an array'
                pairs = set()
                for position, link in enumerate(related):
                    where = f'{record["id"]}: related[{position}]'
                    assert isinstance(link, dict), where + ' must be an object {"kind": …, "id": …}'
                    assert set(link) == {'kind', 'id'}, where + ' must have exactly kind and id'
                    assert link['kind'] in LINK_KINDS, where + ': kind must be research, innovations or workshops'
                    assert isinstance(link['id'], str) and re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', link['id']), where + ': invalid id'
                    assert not (link['kind'] == name and link['id'] == record['id']), where + ': a record cannot link to itself'
                    assert (link['kind'], link['id']) not in pairs, where + f': duplicate link {link["kind"]}/{link["id"]}'
                    pairs.add((link['kind'], link['id']))
                    links.append((name, record['id'], position, link['kind'], link['id']))
            for key in ('audience', 'subjects', 'features', 'references'):
                if key in record:
                    value = record[key]
                    assert isinstance(value, list) and all(isinstance(v, str) and v.strip() for v in value), f'{record["id"]}: {key} must be an array of text'
        records_by_kind[name] = seen
    except (ValueError, AssertionError, OSError) as error:
        errors.append(f'{name}.json: {error}')
# Network maps (round 14): data/network.json (world map) and data/community.json (map of Thailand). Both may be empty.
# Records name an area by code (config/countries.php, config/thai-provinces.php); several records may share one area.
def config_codes(name):
    text = (base / 'config' / name).read_text(encoding='utf-8')
    return set(re.findall(r"^ '([A-Z0-9-]+)' =>", text, re.M))

MAP_DATASETS = {
    # file: (code key, valid codes, required text keys, optional text keys, allowed related kinds)
    'network': ('country_code', config_codes('countries.php'), ('institution',), ('institution_th', 'description', 'url'), ('research', 'innovations')),
    'community': ('province_code', config_codes('thai-provinces.php'), ('title',), ('community', 'summary', 'image'), ('innovations', 'workshops')),
}
for name, (code_key, valid_codes, required_keys, optional_keys, related_kinds) in MAP_DATASETS.items():
    try:
        data = json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8'))
        assert isinstance(data, list), 'must be an array'
        assert valid_codes, f'config codes for {name} could not be read'
        for index, record in enumerate(data):
            where = f'record {index}'
            assert isinstance(record, dict), f'{where}: must be an object'
            assert record.get(code_key) in valid_codes, f'{where}: {code_key} {record.get(code_key)!r} is not a code in the map'
            for key in required_keys:
                assert isinstance(record.get(key), str) and record[key].strip(), f'{where}: {key} is required'
            for key in optional_keys:
                assert key not in record or isinstance(record[key], str), f'{where}: {key} must be text'
            if record.get('url'):
                assert re.fullmatch(r'https?://\S+', record['url']), f'{where}: url must start with https://'
            if 'year' in record:
                assert isinstance(record['year'], int) and 2500 <= record['year'] <= 2700, f'{where}: year must be a Buddhist-era year (2500-2700)'
            if 'public' in record:
                assert isinstance(record['public'], bool), f'{where}: public must be true or false'
            for position, link in enumerate(record.get('related', [])):
                assert isinstance(link, dict) and link.get('kind') in related_kinds and isinstance(link.get('id'), str), f'{where}: related[{position}] needs kind ({" or ".join(related_kinds)}) and id'
                links.append((name, f'record {index}', position, link['kind'], link['id']))
    except (ValueError, AssertionError, OSError) as error:
        errors.append(f'{name}.json: {error}')
# Network highlights (round 16): the two bands under the world map. kind and icon must be known values.
try:
    icons = set(re.findall(r"^        '([a-z-]+)' => \[", (base / 'includes' / 'network-icons.php').read_text(encoding='utf-8'), re.M))
    assert icons, 'icon keys of includes/network-icons.php could not be read'
    data = json.loads((base / 'data' / 'network-highlights.json').read_text(encoding='utf-8'))
    assert isinstance(data, list), 'must be an array'
    for index, record in enumerate(data):
        where = f'record {index}'
        assert isinstance(record, dict), f'{where}: must be an object'
        assert record.get('kind') in ('model', 'value'), f'{where}: kind must be model or value'
        assert isinstance(record.get('title_th'), str) and record['title_th'].strip(), f'{where}: title_th is required'
        assert 'title_en' not in record or isinstance(record['title_en'], str), f'{where}: title_en must be text'
        assert record.get('icon') in icons, f'{where}: icon {record.get("icon")!r} is not one of {sorted(icons)}'
        assert 'public' not in record or isinstance(record['public'], bool), f'{where}: public must be true or false'
except (ValueError, AssertionError, OSError) as error:
    errors.append(f'network-highlights.json: {error}')
# Every link must point at a record that exists (checked after all files are loaded).
for source, record_id, position, kind, target in links:
    if kind not in records_by_kind:
        continue  # that file already failed above
    if target not in records_by_kind[kind]:
        errors.append(f'{source}.json: {record_id}: related[{position}] points to {kind}/{target}, which does not exist in {kind}.json')
if errors:
    print('\n'.join(errors))
    sys.exit(1)
print('All content files passed validation.')

hidden_links = sorted({(kind, target) for _, _, _, kind, target in links if (kind, target) in hidden})
if hidden:
    print(f'Hidden from the website ("public": false): ' + ', '.join(f'{k}/{i}' for k, i in sorted(hidden)))
for kind, target in hidden_links:
    print(f'Note: {kind}/{target} is hidden, so related cards that link to it are not shown.')

# Reminder only (never fails): Thai paragraphs that tools/segment_thai.py has not segmented yet
# still show on the page, but the browser may break their lines mid-word.
try:
    breaks = json.loads((base / 'data' / 'thai-breaks.json').read_text(encoding='utf-8')).get('paragraphs', {})
except (ValueError, OSError) as exc:
    breaks = {}
    errors.append(f'data/thai-breaks.json cannot be read as JSON ({exc}). Run: python3 tools/segment_thai.py')
missing = 0
for name in LINK_KINDS:
    for record in json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8')):
        texts = [record.get('summary')] + [b for b in record.get('body', []) if isinstance(b, str)]
        for text in texts:
            if isinstance(text, str) and re.search('[\u0E00-\u0E7F]', text) and hashlib.sha1(text.encode('utf-8')).hexdigest() not in breaks:
                missing += 1
if missing:
    print(f'Note: {missing} Thai paragraph(s) are not segmented yet. Run: python3 tools/segment_thai.py')
if errors:  # raised after the first report, such as an unreadable data/thai-breaks.json
    print('\n'.join(errors))
    sys.exit(1)
