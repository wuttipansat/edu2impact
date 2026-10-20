"""Check WCAG AA contrast for the colour pairs the site uses. Python 3; no packages.

Reads token values from assets/css/minimal-palette.css so the check follows the CSS.
Exit code 1 if any pair is below its threshold.
"""
import re
import sys
from pathlib import Path

css = (Path(__file__).resolve().parents[1] / 'assets/css/minimal-palette.css').read_text(encoding='utf-8')
tokens = dict(re.findall(r'--(purple-\d+|accent-warm|minimal-ink|minimal-muted|minimal-surface|minimal-line):\s*(#[0-9a-fA-F]{6})', css))


def colour(name):
    return name if name.startswith('#') else tokens[name]


def luminance(hex_value):
    channels = [int(hex_value[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    channels = [c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4 for c in channels]
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]


def ratio(a, b):
    la, lb = luminance(colour(a)), luminance(colour(b))
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)


def blend(fg_hex, alpha, bg):
    """Colour of a translucent fill over a solid background."""
    fg = [int(fg_hex[i:i + 2], 16) for i in (1, 3, 5)]
    base = [int(colour(bg)[i:i + 2], 16) for i in (1, 3, 5)]
    return '#' + ''.join(f'{round(f * alpha + b * (1 - alpha)):02x}' for f, b in zip(fg, base))


TEXT, LARGE, UI = 4.5, 3.0, 3.0
nav_hover = blend('#ffffff', .10, 'purple-800')
pairs = [
    ('Body text on white', 'minimal-ink', '#ffffff', TEXT),
    ('Muted text on white', 'minimal-muted', '#ffffff', TEXT),
    ('Links on white', 'purple-700', '#ffffff', TEXT),
    ('Link hover on white', 'purple-900', '#ffffff', TEXT),
    ('Kickers / labels on white', 'purple-600', '#ffffff', TEXT),
    ('Kickers on lavender section', 'purple-600', 'purple-100', TEXT),
    ('Links on lavender section', 'purple-700', 'purple-100', TEXT),
    ('Links on hover row (purple-50)', 'purple-700', 'purple-50', TEXT),
    ('White on primary button', '#ffffff', 'purple-800', TEXT),
    ('White on button hover', '#ffffff', 'purple-700', TEXT),
    ('White on button pressed', '#ffffff', 'purple-900', TEXT),
    ('Secondary button text', 'purple-800', '#ffffff', TEXT),
    ('Secondary button hover', 'purple-800', 'purple-50', TEXT),
    ('Selected item text', 'purple-800', 'purple-100', TEXT),
    ('Event badge day (white)', '#ffffff', 'purple-800', LARGE),
    ('Event badge month', 'purple-200', 'purple-800', TEXT),
    ('Focus ring on white', 'purple-600', '#ffffff', UI),
    ('Focus ring on lavender', 'purple-600', 'purple-100', UI),
    ('Input focus border on white', 'purple-600', '#ffffff', UI),
    ('Nav text on header', '#ffffff', 'purple-800', TEXT),
    ('Nav text on hover fill', '#ffffff', nav_hover, TEXT),
    ('White text on nav current (purple-600)', '#ffffff', 'purple-600', TEXT),
    ('Focus ring on header/footer', 'purple-300', 'purple-800', UI),
    ('Hero eyebrow / editorial eyebrow', 'purple-200', 'purple-800', TEXT),
    ('Contact hero title', '#ffffff', 'purple-800', TEXT),
    ('Hero button text', 'purple-800', '#ffffff', TEXT),
    ('Hero button hover', 'purple-800', 'purple-200', TEXT),
    ('Accent: footer headings', 'accent-warm', 'purple-800', TEXT),
    ('Demo notice text', '#5c3d12', '#fff3e8', TEXT),
    ('Header search icon on header', '#ffffff', 'purple-800', UI),
    ('Header search icon on hover fill', '#ffffff', blend('#ffffff', 0.12, 'purple-800'), UI),
    ('Header search icon on search.php (current)', '#ffffff', 'purple-600', UI),
    ('Search highlight <mark> text', 'minimal-ink', 'purple-100', TEXT),
    ('Search result title', 'minimal-ink', '#ffffff', TEXT),
    ('Search result title hover', 'purple-800', '#ffffff', TEXT),
    ('Search result type label', 'purple-700', '#ffffff', TEXT),
    ('Search result URL (muted)', 'minimal-muted', '#ffffff', TEXT),
    ('Search jump-link count', 'purple-600', '#ffffff', TEXT),
    ('Search dialog submit button', '#ffffff', 'purple-800', TEXT),
    ('Search dialog submit hover', '#ffffff', 'purple-700', TEXT),
    ('Search dialog placeholder', 'minimal-muted', '#ffffff', TEXT),
    ('Search dialog close button', 'minimal-muted', '#ffffff', UI),
    ('Search dialog hint', 'minimal-muted', '#ffffff', TEXT),
    ('Category pill on agenda row', 'purple-800', 'purple-100', TEXT),
    ('Showcase badge on image', 'purple-800', '#ffffff', TEXT),
    ('Workshop icon badge', 'purple-800', 'purple-100', TEXT),
    ('Workshop row arrow', 'purple-800', '#ffffff', TEXT),
    ('Related card kind pill', 'purple-800', 'purple-100', TEXT),
    ('Related card title', 'minimal-ink', '#ffffff', TEXT),
    ('Related card title hover', 'purple-800', 'purple-50', TEXT),
    ('Related card summary', 'minimal-muted', '#ffffff', TEXT),
    ('Related card summary on hover', 'minimal-muted', 'purple-50', TEXT),
]
failed = False
for label, fg, bg, need in pairs:
    value = ratio(fg, bg)
    ok = value >= need
    failed |= not ok
    print(f"{'PASS' if ok else 'FAIL'}  {value:5.2f}:1  (needs {need}:1)  {label}")
print('Disabled buttons are exempt from WCAG contrast; they use purple-500 on purple-100.')
sys.exit(1 if failed else 0)
