"""Make the 1200x630 JPEG share images that Facebook, LINE and X show when a page is shared.

Facebook reads JPEG and PNG reliably but not always WebP, so every cover in assets/images/*.webp gets a
JPEG twin at assets/images/og/<same name>.jpg, and assets/images/og-default.jpg is made from the campus
photo for pages without a cover. share_image_path() in includes/functions.php picks the twin by file name.

Usage (after adding or changing a cover image, before uploading):
    pip install pillow
    python3 tools/make_og_images.py

A cover that would have to be enlarged by more than 1.4x to fill the card is placed whole on the dark
purple of the theme instead of being cropped and stretched, so it stays sharp.
"""
import sys
from pathlib import Path

try:
    from PIL import Image
except ImportError:
    sys.exit('Pillow is not installed. Run: pip install pillow')

base = Path(__file__).resolve().parents[1]
images = base / 'assets' / 'images'
W, H = 1200, 630
PURPLE = (44, 16, 56)
SKIP = {'header-logo', 'og-default'}
SKIP_PREFIXES = ('hero-', 'home-hero')  # page banners, never a post cover
DEFAULT_SOURCE = 'home-hero-campus'  # the faculty building: recognisable on every page that has no cover
MAX_BYTES = 300 * 1024


def card(source: Path) -> Image.Image:
    image = Image.open(source).convert('RGB')
    scale = max(W / image.width, H / image.height)
    if scale <= 1.4:  # crop to fill
        resized = image.resize((round(image.width * scale), round(image.height * scale)), Image.LANCZOS)
        left, top = (resized.width - W) // 2, (resized.height - H) // 2
        return resized.crop((left, top, left + W, top + H))
    fit = min(W / image.width, H / image.height, 1.0)  # keep whole, never enlarge
    resized = image.resize((round(image.width * fit), round(image.height * fit)), Image.LANCZOS)
    canvas = Image.new('RGB', (W, H), PURPLE)
    canvas.paste(resized, ((W - resized.width) // 2, (H - resized.height) // 2))
    return canvas


def save(image: Image.Image, target: Path) -> int:
    for quality in (88, 82, 76, 70, 62):
        image.save(target, 'JPEG', quality=quality, optimize=True, progressive=True)
        if target.stat().st_size <= MAX_BYTES:
            break
    return target.stat().st_size


def main() -> None:
    (images / 'og').mkdir(exist_ok=True)
    size = save(card(images / (DEFAULT_SOURCE + '.webp')), images / 'og-default.jpg')
    print(f'og-default.jpg {size // 1024} KB')
    for source in sorted(images.glob('*.webp')):
        if source.stem in SKIP or source.stem.startswith(SKIP_PREFIXES):
            continue
        size = save(card(source), images / 'og' / (source.stem + '.jpg'))
        print(f'og/{source.stem}.jpg {size // 1024} KB')


main()
