"""Validate the public JSON content before uploading. Python 3; no packages."""
import datetime
import hashlib
import json
import re
import sys
from pathlib import Path

base = Path(__file__).resolve().parents[1]
errors = []
LINK_KINDS = ('research', 'innovations', 'workshops')
records_by_kind = {}  # dataset -> ids, filled as files load; links are checked once every file is loaded
links = []  # (dataset, record id, position, kind, id)
hidden = set()  # (dataset, id) of records with "public": false
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
            # Workshops have no category; elsewhere it is required. When present it must be text.
            required = ('id', 'title', 'summary') if name == 'workshops' else ('id', 'title', 'category', 'summary')
            for key in required:
                assert isinstance(record.get(key), str) and record[key].strip(), f'item {index}: {key} is required'
            if 'category' in record:
                assert isinstance(record['category'], str), f'item {index}: category must be text'
            assert re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', record['id']), 'invalid id'
            assert record['id'] not in seen, 'duplicate id ' + record['id']
            seen.add(record['id'])
            assert isinstance(record.get('body'), list), 'body must be an array'
            for position, block in enumerate(record['body']):
                if isinstance(block, str):
                    continue
                where = f'{record["id"]}: body[{position}]'
                assert isinstance(block, dict), where + ' must be text or an image block'
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
except (ValueError, OSError):
    breaks = {}
missing = 0
for name in LINK_KINDS:
    for record in json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8')):
        texts = [record.get('summary')] + [b for b in record.get('body', []) if isinstance(b, str)]
        for text in texts:
            if isinstance(text, str) and re.search('[\u0E00-\u0E7F]', text) and hashlib.sha1(text.encode('utf-8')).hexdigest() not in breaks:
                missing += 1
if missing:
    print(f'Note: {missing} Thai paragraph(s) are not segmented yet. Run: python3 tools/segment_thai.py')
