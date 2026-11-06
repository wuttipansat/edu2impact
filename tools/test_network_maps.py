#!/usr/bin/env python3
"""Browser test for the two network maps (network-international.php, network-community.php).

Needs Playwright for Python and chromium. Run it against a LOCAL COPY of the site whose data/network.json and
data/community.json hold test records (never put test records in the repo):

  python3 tools/make_network_testdata.py <copy-of-site>    (fake records: JP x2, SG, US x4, HK ... and TH-50, TH-83 x4, TH-90 x2 ...)
  php -S 127.0.0.1:8090 -t <copy-of-site>
  SITE=http://127.0.0.1:8090 SHOTS=/tmp/shots python3 tools/test_network_maps.py

It checks, at 1440, 1100 and 390 px: the menu, that the maps are coloured, tooltip, panel (click, keyboard, Esc, #CODE),
zoom and region buttons, the text list, no console errors and no CSP violations; and the pages with JavaScript off.
"""
import os, sys
from playwright.sync_api import sync_playwright

SITE = os.environ.get('SITE', 'http://127.0.0.1:8090').rstrip('/')
SHOTS = os.environ.get('SHOTS', '')
fails = []


def check(name, ok, extra=''):
    print(('PASS ' if ok else 'FAIL ') + name + ('' if ok or extra == '' else '  ' + str(extra)[:240]))
    if not ok:
        fails.append(name)


def watch(page, sink):
    # Only errors from the site itself: the sandbox has no route to Google Fonts and reports that as a failed request.
    page.on('console', lambda m: sink.append(m.text) if m.type == 'error' and m.location.get('url', SITE).startswith(SITE) else None)
    page.on('pageerror', lambda e: sink.append('pageerror: ' + str(e)))
    page.add_init_script("window.__csp=[];document.addEventListener('securitypolicyviolation',e=>window.__csp.push(e.violatedDirective+' '+e.blockedURI));")


def shot(page, name):
    if SHOTS:
        os.makedirs(SHOTS, exist_ok=True)
        page.screenshot(path=f'{SHOTS}/{name}.png', full_page=False)


AREA_XY = """(code) => {
  const svg = document.querySelector('.netmap__svg');
  const ring = svg.querySelector('circle[data-code="' + code + '"]');
  if (ring) { ring.scrollIntoView({block: 'center'}); const r = ring.getBoundingClientRect(); return [r.x + r.width / 2, r.y + r.height / 2]; }
  const p = svg.querySelector('path[data-code="' + code + '"]');
  p.scrollIntoView({block: 'center'});
  const b = p.getBBox(), pt = svg.createSVGPoint(), m = svg.getScreenCTM();
  for (let i = 1; i < 30; i++) for (let j = 1; j < 30; j++) {
    pt.x = b.x + b.width * i / 30; pt.y = b.y + b.height * j / 30;
    if (p.isPointInFill(pt)) { const s = pt.matrixTransform(m); return [s.x, s.y]; }
  }
  return null;
}"""


def area_xy(page, code):
    # A real visitor points at land: the middle of a bounding box can be sea (Japan, Indonesia).
    xy = page.evaluate(AREA_XY, code)
    assert xy, 'no point inside ' + code
    return xy


def hover_area(page, code):
    page.mouse.move(*area_xy(page, code))


def click_area(page, code):
    page.mouse.click(*area_xy(page, code))


def viewbox_w(page):
    return float(page.evaluate("document.querySelector('.netmap__svg').getAttribute('viewBox').split(' ')[2]"))


def menu(page, width, tag):
    if width <= 1100:
        page.click('.menu-toggle')
    groups = page.locator('.nav-group > summary span').all_inner_texts()
    check(f'{tag} menu has the เครือข่าย group', 'เครือข่าย' in groups, groups)
    check(f'{tag} menu has six top-level entries', page.locator('#main-nav > a, #main-nav > details').count() == 6, page.locator('#main-nav > a, #main-nav > details').count())
    page.locator('.nav-group > summary', has_text='เครือข่าย').click()
    links = page.locator('.nav-group[open] .nav-submenu a').all_inner_texts()
    check(f'{tag} dropdown lists both pages', links == ['เครือข่ายวิจัยนานาชาติ', 'นวัตกรรมสู่ชุมชน'], links)
    if width > 1100:
        tops = page.evaluate("[...document.querySelectorAll('#main-nav > a, #main-nav > details')].map(e=>Math.round(e.getBoundingClientRect().top))")
        check(f'{tag} menu stays on one line', len(set(tops)) == 1, tops)
        header = page.evaluate("(()=>{const n=document.querySelector('#main-nav').getBoundingClientRect();return n.right<=window.innerWidth && n.height<80})()")
        check(f'{tag} menu fits the header', header)
    page.keyboard.press('Escape')
    if width <= 1100 and page.locator('.menu-toggle').get_attribute('aria-expanded') == 'true':
        page.click('.menu-toggle')  # the open menu covers the page


def world(page, width):
    tag = f'world@{width}'
    page.goto(SITE + '/network-international.php')
    page.wait_for_selector('.netmap.is-ready')
    check(f'{tag} 11 countries are active', page.locator('path.is-active').count() == 11, page.locator('path.is-active').count())
    check(f'{tag} US is the darkest level', page.locator('path[data-code=US]').get_attribute('class').find('is-l4') >= 0)
    check(f'{tag} unknown code ZZ ignored', page.locator('.netarea#list-ZZ').count() == 0)
    check(f'{tag} rings for tiny countries', page.locator('circle.netmap__marker').count() >= 2, page.locator('circle.netmap__marker').count())
    check(f'{tag} list shows every active country', page.locator('.netarea').count() == 11, page.locator('.netarea').count())
    stats = page.locator('.netstats').inner_text()
    check(f'{tag} counts text', '11' in stats and '15' in stats, stats)
    menu(page, width, tag)
    if width > 900:
        hover_area(page, 'JP')
        check(f'{tag} tooltip', 'ญี่ปุ่น' in page.locator('.netmap__tip').inner_text() and not page.locator('.netmap__tip').is_hidden())
    click_area(page, 'JP'); page.wait_for_timeout(350)  # the sheet slides up on phones
    panel = page.locator('.netmap__panel')
    check(f'{tag} click opens panel', panel.get_attribute('class').find('is-open') >= 0 and 'TEST Kyoto University' in panel.inner_text())
    check(f'{tag} panel has related cards', panel.locator('.netitem__related a').count() == 2, panel.locator('.netitem__related a').count())
    check(f'{tag} hash set', page.evaluate('location.hash') == '#JP')
    check(f'{tag} panel copy has no duplicate ids', page.locator('.netmap__detail [id]').count() == 0)
    shot(page, f'world-{width}-open')
    page.keyboard.press('Escape')
    check(f'{tag} Esc closes and clears hash', 'is-open' not in panel.get_attribute('class') and page.evaluate('location.hash') == '')
    check(f'{tag} focus returns to the area', page.evaluate("document.activeElement.dataset.code") == 'JP')
    page.keyboard.press('Enter')
    check(f'{tag} Enter opens from keyboard', 'is-open' in panel.get_attribute('class'))
    page.keyboard.press('Escape')
    home = viewbox_w(page)
    page.click('[data-zoom=in]'); page.wait_for_timeout(400)
    check(f'{tag} zoom in', viewbox_w(page) < home - 1)
    page.click('[data-zoom=out]'); page.wait_for_timeout(400)
    page.click('[data-zoom=reset]'); page.wait_for_timeout(400)
    check(f'{tag} reset', abs(viewbox_w(page) - home) < 1)
    chips = page.locator('.netmap__chip').all_inner_texts()
    check(f'{tag} region chips only for regions with data', 'ทั้งหมด' in chips and 'เอเชียตะวันออกเฉียงใต้' in chips and 'แอฟริกา' in chips and 'อเมริกาใต้' in chips and 'เอเชียตะวันตก' not in chips, chips)
    page.locator('.netmap__chip', has_text='เอเชียตะวันออกเฉียงใต้').click(); page.wait_for_timeout(400)
    check(f'{tag} region zooms', viewbox_w(page) < home - 1)
    check(f'{tag} other regions dimmed', page.locator('path.is-dimmed').count() > 100, page.locator('path.is-dimmed').count())
    shot(page, f'world-{width}-region')
    page.locator('.netmap__chip', has_text='ทั้งหมด').click(); page.wait_for_timeout(400)
    page.goto(SITE + '/network-international.php#SG')
    page.wait_for_selector('.netmap.is-ready'); page.wait_for_timeout(500)
    check(f'{tag} #SG opens the tiny country', 'TEST National Institute' in page.locator('.netmap__panel').inner_text() and viewbox_w(page) < home - 1)
    shot(page, f'world-{width}-sg')


def thailand(page, width):
    tag = f'thailand@{width}'
    page.goto(SITE + '/network-community.php')
    page.wait_for_selector('.netmap.is-ready')
    check(f'{tag} 77 provinces drawn', page.locator('.netmap__svg path[data-code]').count() == 77, page.locator('.netmap__svg path[data-code]').count())
    check(f'{tag} 7 provinces active', page.locator('path.is-active').count() == 7, page.locator('path.is-active').count())
    check(f'{tag} Phuket has level 4', 'is-l4' in page.locator('path[data-code=TH-83]').get_attribute('class'))
    chips = page.locator('.netmap__chip').all_inner_texts()
    check(f'{tag} six regions + all', len(chips) == 7, chips)
    click_area(page, 'TH-50'); page.wait_for_timeout(350)
    panel = page.locator('.netmap__panel')
    text = panel.inner_text()
    check(f'{tag} panel shows project, community and year', 'TEST โครงการเชียงใหม่' in text and 'ชุมชนทดสอบ' in text and '2568' in text, text)
    check(f'{tag} panel has related cards', panel.locator('.netitem__related a').count() == 2, panel.locator('.netitem__related a').count())
    shot(page, f'thailand-{width}-open')
    page.keyboard.press('Escape')
    page.locator('.netmap__chip', has_text='ภาคใต้').click(); page.wait_for_timeout(400)
    dimmed = page.locator('path.is-dimmed').count()
    check(f'{tag} south filter dims the other provinces', 55 < dimmed < 77, dimmed)
    check(f'{tag} south filter zooms', viewbox_w(page) < 480)
    shot(page, f'thailand-{width}-south')
    page.locator('.netmap__chip', has_text='ทั้งหมด').click(); page.wait_for_timeout(400)
    page.goto(SITE + '/network-community.php#TH-83')
    page.wait_for_selector('.netmap.is-ready'); page.wait_for_timeout(500)
    check(f'{tag} #TH-83 opens Phuket', 'ภูเก็ต' in page.locator('.netmap__panel').inner_text() and page.locator('.netmap__detail .netitem').count() == 4)
    menu(page, width, tag)


def nav_widths(browser):
    # Six menu entries must stay on one line from 1101 px (where the full menu starts) up.
    for width in (1101, 1180, 1299, 1440):
        page = browser.new_context(viewport={'width': width, 'height': 600}).new_page()
        page.goto(SITE + '/network-international.php')
        tops = page.evaluate("[...document.querySelectorAll('#main-nav > a, #main-nav > details')].map(e=>Math.round(e.getBoundingClientRect().top))")
        cta = page.evaluate("document.querySelector('.header-cta').getBoundingClientRect().right")
        check(f'menu on one line at {width}px', len(set(tops)) == 1 and cta <= width, (tops, cta))
        page.context.close()


def no_js(browser):
    ctx = browser.new_context(java_script_enabled=False, viewport={'width': 1440, 'height': 900})
    page = ctx.new_page()
    for path, areas in (('network-international.php', 11), ('network-community.php', 7)):
        page.goto(f'{SITE}/{path}')
        check(f'no-js {path} coloured areas', page.locator('path.is-active').count() == areas, page.locator('path.is-active').count())
        check(f'no-js {path} full list', page.locator('.netarea').count() == areas)
        check(f'no-js {path} panel and tools hidden', page.locator('.netmap__panel').is_hidden() and page.locator('.netmap__tools').is_hidden())
    ctx.close()


with sync_playwright() as p:
    browser = p.chromium.launch()
    for width, height in ((1440, 900), (1100, 800), (390, 844)):
        ctx = browser.new_context(viewport={'width': width, 'height': height}, has_touch=width < 700)
        page = ctx.new_page()
        errors = []
        watch(page, errors)
        world(page, width)
        thailand(page, width)
        csp = page.evaluate('window.__csp')
        check(f'{width}px no console errors', not errors, errors)
        check(f'{width}px no CSP violations', not csp, csp)
        ctx.close()
    nav_widths(browser)
    no_js(browser)
    browser.close()

print('\n%d failed' % len(fails) if fails else '\nall passed')
sys.exit(1 if fails else 0)
