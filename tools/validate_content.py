"""Validate the public JSON content before uploading. Python 3; no packages."""
import datetime
import json
import re
import sys
from pathlib import Path

base = Path(__file__).resolve().parents[1]
errors = []
for name in ('site', 'research', 'innovations', 'news'):
    try:
        data = json.loads((base / 'data' / (name + '.json')).read_text(encoding='utf-8'))
        if name == 'site':
            assert isinstance(data, dict), 'must be an object'
            for key in ('name', 'university', 'description', 'email', 'phone', 'address'):
                assert isinstance(data.get(key), str), key + ' must be text'
            assert isinstance(data.get('demo_mode'), bool), 'demo_mode must be true or false'
            continue
        assert isinstance(data, list), 'must be an array'
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
    except (ValueError, AssertionError, OSError) as error:
        errors.append(f'{name}.json: {error}')
if errors:
    print('\n'.join(errors))
    sys.exit(1)
print('All four content files passed validation.')
