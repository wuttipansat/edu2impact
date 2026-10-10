"""Fail if any green or teal colour remains in the CSS or SVG files. Python 3; no packages.

A colour counts as green/teal when its hue is between 70 and 215 degrees (yellow-green
through teal), its HSL saturation is above 4% and its lightness is below 99%. That also
catches green-tinted greys and near-whites such as #f7faf8, which look green next to
purple. Pure neutral greys, white, purples and the warm accent are fine. Alpha is
ignored: rgba(0,77,50,.2) is still green.
"""
import colorsys
import re
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
token = re.compile(r'#[0-9a-fA-F]{8}\b|#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{4}\b|#[0-9a-fA-F]{3}\b|rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+[^)]*\)')


def channels(value):
    if value.startswith('#'):
        h = value[1:]
        h = ''.join(c * 2 for c in h) if len(h) in (3, 4) else h
        return [int(h[i:i + 2], 16) for i in (0, 2, 4)]
    return [int(n) for n in re.findall(r'\d+', value)[:3]]


def is_green(value):
    r, g, b = [c / 255 for c in channels(value)]
    hue, light, sat = colorsys.rgb_to_hls(r, g, b)
    return 70 <= hue * 360 <= 215 and sat > 0.04 and light < 0.99


def selector_before(text, pos):
    start = max(text.rfind('}', 0, pos), text.rfind('{', 0, pos) - 1)
    brace = text.rfind('{', 0, pos)
    start = text.rfind('}', 0, brace) + 1
    return ' '.join(text[start:brace].split())[-70:]


hits = []
files = sorted((root / 'assets/css').glob('*.css')) + sorted((root / 'assets/images').glob('*.svg'))
for path in files:
    text = path.read_text(encoding='utf-8')
    for match in token.finditer(text):
        if is_green(match.group(0)):
            hits.append((path.name, match.group(0), selector_before(text, match.start())))

for name, value, selector in hits:
    print(f'{name:22} {value:24} {selector}')
print(f'{len(hits)} green/teal colour(s) found in {len(files)} files')
sys.exit(1 if hits else 0)
