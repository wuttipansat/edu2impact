"""Validate the public JSON content before uploading. Python 3; no packages."""
import datetime
import json
import re
import sys
from pathlib import Path

base = Path(__file__).resolve().parents[1]
errors = []
for name in ('site', 'research', 'innovations', 'workshops', 'events'):
    try:
        data = json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8'))
        if name == 'site':
            assert isinstance(data, dict), 'must be an object'
            for key in ('name', 'university', 'description', 'email', 'phone', 'address'):
                assert isinstance(data.get(key), str), key + ' must be text'
            assert isinstance(data.get('demo_mode'), bool), 'demo_mode must be true or false'
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
            continue
        seen = set()
        for index, record in enumerate(data):
            assert isinstance(record, dict), 'record must be an object'
            for key in ('id', 'title', 'category', 'summary'):
                assert isinstance(record.get(key), str) and record[key].strip(), f'item {index}: {key} is required'
            assert re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', record['id']), 'invalid id'
            assert record['id'] not in seen, 'duplicate id ' + record['id']
            seen.add(record['id'])
            assert isinstance(record.get('body'), list) and all(isinstance(p, str) for p in record['body']), 'body must be an array of text'
            assert isinstance(record.get('featured'), bool), 'featured must be boolean'
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
            for key in ('audience', 'subjects', 'features', 'references'):
                if key in record:
                    value = record[key]
                    assert isinstance(value, list) and all(isinstance(v, str) and v.strip() for v in value), f'{record["id"]}: {key} must be an array of text'
    except (ValueError, AssertionError, OSError) as error:
        errors.append(f'{name}.json: {error}')
if errors:
    print('\n'.join(errors))
    sys.exit(1)
print('All content files passed validation.')
