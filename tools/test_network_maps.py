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
  if (ring) { ring.scrollIntoView({block: 'center', behavior: 'instant'}); const r = ring.getBoundingClientRect(); return [r.x + r.width / 2, r.y + r.height / 2]; }
  const p = svg.querySelector('path[data-code="' + code + '"]');
  p.scrollIntoView({block: 'center', behavior: 'instant'});
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
    check(f'{tag} US is the darkest level', page.locator('.netmap__land path[data-code=US]').get_attribute('class').find('is-l4') >= 0)
    check(f'{tag} unknown code ZZ ignored', page.locator('.netarea#list-ZZ').count() == 0)
    check(f'{tag} rings for tiny countries', page.locator('circle.netmap__marker').count() >= 2, page.locator('circle.netmap__marker').count())
    check(f'{tag} one arc per partner country outside Thailand', page.locator('.netmap__arc').count() == 11, page.locator('.netmap__arc').count())
    page.wait_for_timeout(3500)
    check(f'{tag} opening sequence finishes', page.locator('.is-pending').count() == 0, page.locator('.is-pending').count())
    check(f'{tag} list shows every active country', page.locator('.netarea').count() == 11, page.locator('.netarea').count())
    stats = page.locator('.netmap__count').inner_text()
    check(f'{tag} counts text', '11' in stats and '15' in stats, stats)
    menu(page, width, tag)
    start = viewbox_w(page)
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
    page.keyboard.press('Escape'); page.wait_for_timeout(700)  # closing the panel zooms back out
    home = viewbox_w(page)
    check(f'{tag} closing returns to the starting view', abs(home - start) < 1, (home, start))
    page.click('[data-zoom=in]'); page.wait_for_timeout(700)
    check(f'{tag} zoom in', viewbox_w(page) < home - 1)
    page.click('[data-zoom=out]'); page.wait_for_timeout(700)
    page.click('[data-zoom=reset]'); page.wait_for_timeout(700)
    check(f'{tag} reset', abs(viewbox_w(page) - home) < 1)
    chips = page.locator('.netmap__chip').all_inner_texts()
    check(f'{tag} region chips only for regions with data', 'ทั้งหมด' in chips and 'เอเชียตะวันออกเฉียงใต้' in chips and 'แอฟริกา' in chips and 'อเมริกาใต้' in chips and 'เอเชียตะวันตก' not in chips, chips)
    page.locator('.netmap__chip', has_text='เอเชียตะวันออกเฉียงใต้').click(); page.wait_for_timeout(700)
    check(f'{tag} region zooms', abs(viewbox_w(page) - home) > 1)
    check(f'{tag} other regions dimmed', page.locator('path.is-dimmed').count() > 100, page.locator('path.is-dimmed').count())
    shot(page, f'world-{width}-region')
    page.locator('.netmap__chip', has_text='ทั้งหมด').click(); page.wait_for_timeout(700)
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
    check(f'{tag} Phuket has level 4', 'is-l4' in page.locator('.netmap__land path[data-code=TH-83]').get_attribute('class'))
    chips = page.locator('.netmap__chip').all_inner_texts()
    check(f'{tag} six regions + all', len(chips) == 7, chips)
    check(f'{tag} bubbles show the number of projects', page.locator('.netmap__bubble').count() == 7 and '4' in [t.text_content() for t in page.locator('.netmap__bubble text').all()])
    page.wait_for_timeout(3200)
    check(f'{tag} opening sequence finishes', page.locator('.is-pending').count() == 0, page.locator('.is-pending').count())
    thai_home = viewbox_w(page)
    click_area(page, 'TH-50'); page.wait_for_timeout(350)
    panel = page.locator('.netmap__panel')
    text = panel.inner_text()
    check(f'{tag} panel shows project, community and year', 'TEST โครงการเชียงใหม่' in text and 'ชุมชนทดสอบ' in text and '2568' in text, text)
    check(f'{tag} panel has related cards', panel.locator('.netitem__related a').count() == 2, panel.locator('.netitem__related a').count())
    shot(page, f'thailand-{width}-open')
    page.keyboard.press('Escape')
    page.locator('.netmap__chip', has_text='ภาคใต้').click(); page.wait_for_timeout(700)
    dimmed = page.locator('path.is-dimmed').count()
    check(f'{tag} south filter dims the other provinces', 55 < dimmed < 77, dimmed)
    check(f'{tag} south filter zooms', viewbox_w(page) < thai_home - 1)
    shot(page, f'thailand-{width}-south')
    page.locator('.netmap__chip', has_text='ทั้งหมด').click(); page.wait_for_timeout(700)
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
        check(f'no-js {path} panel and tools hidden', page.locator('.netmap__panel').is_hidden() and all(t.is_hidden() for t in page.locator('.netmap__tools').all()))
    ctx.close()


MODELS = ['นิเทศวิทยานิพนธ์ร่วมกับต่างประเทศ', 'วิจัยร่วม', 'สนับสนุนการตีพิมพ์', 'นักวิชาการรับเชิญและผู้เชี่ยวชาญบรรยาย', 'สัมมนาและเวทีวิจัยร่วม', 'เส้นทางสู่การประชุมวิชาการนานาชาติ']
VALUES = ['ยกระดับคุณภาพวิทยานิพนธ์', 'เพิ่มโอกาสการตีพิมพ์ระดับนานาชาติ', 'เปิดมุมมองวิจัยระดับสากล', 'สร้างเครือข่ายวิชาการหลังสำเร็จการศึกษา', 'สนับสนุน PLO2 และ PLO3']


def highlights(browser):
    # The two bands under the world map (network_highlights): content, order, layout, no overflow, with and without JavaScript.
    for width, height in ((1440, 900), (1100, 800), (390, 844)):
        ctx = browser.new_context(viewport={'width': width, 'height': height})
        page = ctx.new_page()
        errors = []
        watch(page, errors)
        page.goto(SITE + '/network-international.php')
        for i in range(page.locator('[data-reveal]').count()):
            page.locator('[data-reveal]').nth(i).evaluate("e=>e.scrollIntoView({block:'center',behavior:'instant'})"); page.wait_for_timeout(120)
        page.wait_for_timeout(900)
        page.evaluate("document.querySelector('.netinfo').scrollIntoView({block:'start',behavior:'instant'})"); page.wait_for_timeout(300)
        models = [t.split('\n')[0].strip() for t in page.locator('.netmodel__th').all_inner_texts()]
        values = [t.strip() for t in page.locator('.netvalue__th').all_inner_texts()]
        check(f'{width}px highlights: six ways of working in order', models == MODELS, models)
        check(f'{width}px highlights: five gains in order', values == VALUES, values)
        check(f'{width}px highlights: every card has an icon', page.locator('.netinfo svg.neticon').count() == 11 and page.locator('.netinfo svg[aria-hidden=true]').count() == 11)
        check(f'{width}px highlights: bands sit between the map and the list', page.evaluate("(()=>{const m=document.querySelector('.netmap').getBoundingClientRect().bottom,i=document.querySelector('.netinfo').getBoundingClientRect().top+scrollY,l=document.querySelector('.netlist').getBoundingClientRect().top+scrollY;return m+scrollY<=i+1&&i<l})()"))
        over = page.evaluate("[...document.querySelectorAll('.netmodel,.netvalue,.netmodel *,.netvalue *')].filter(e=>e.scrollWidth>e.clientWidth+1&&!(e instanceof SVGElement)).map(e=>e.className)")
        check(f'{width}px highlights: no text spills out of a card', not over, over)
        check(f'{width}px highlights: no horizontal scroll', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(f'{width}px highlights: cards revealed after scrolling', page.evaluate("[...document.querySelectorAll('[data-reveal]')].every(e=>+getComputedStyle(e).opacity===1)"))
        if width == 1440:
            tops = page.evaluate("[...document.querySelectorAll('.netvalue')].map(e=>Math.round(e.getBoundingClientRect().top))")
            check('1440px highlights: the five gains share one row', len(tops) == 5 and len(set(tops)) == 1, tops)
        if width == 390:
            last = page.evaluate("(()=>{const v=[...document.querySelectorAll('.netvalue')],l=v[4].getBoundingClientRect(),w=document.querySelector('.netvalues').getBoundingClientRect();return l.width>w.width*0.9})()")
            check('390px highlights: the odd last gain fills its row', last)
        shot(page, f'highlights-{width}')
        check(f'{width}px highlights: no console errors', not errors, errors)
        check(f'{width}px highlights: no CSP violations', not page.evaluate('window.__csp'), page.evaluate('window.__csp'))
        ctx.close()
    ctx = browser.new_context(java_script_enabled=False, viewport={'width': 1440, 'height': 900})
    page = ctx.new_page()
    page.goto(SITE + '/network-international.php')
    check('no-js highlights: all 11 cards are visible', page.locator('.netmodel:visible').count() == 6 and page.locator('.netvalue:visible').count() == 5)
    ctx.close()
    base = os.environ.get('NOHL_SITE', '').rstrip('/')
    if base:
        page = browser.new_context(viewport={'width': 1440, 'height': 900}).new_page()
        page.goto(base + '/network-international.php')
        check('empty highlights: no band and no heading', page.locator('.netinfo').count() == 0 and 'รูปแบบความร่วมมือ' not in page.content())
        page.context.close()
    else:
        print('SKIP empty-highlights check (set NOHL_SITE)')


def thai_data_size(browser):
    for width, height in ((1440, 900), (1901, 931)):
        page = browser.new_context(viewport={'width': width, 'height': height}).new_page()
        page.goto(SITE + '/network-community.php')
        page.evaluate("document.querySelector('.netmap').scrollIntoView({block:'start',behavior:'instant'})"); page.wait_for_timeout(2500)
        thai_size(page, f'data {width}px')
        shot(page, f'data-community-{width}')
        page.context.close()


def titles_and_footer(browser):
    for path, title, short in (('network-international.php', 'เครือข่ายวิจัยและความร่วมมือนานาชาติ', 'เครือข่ายวิจัยนานาชาติ'), ('network-community.php', 'วิจัยและนวัตกรรมเพื่อชุมชนและสังคม', 'นวัตกรรมสู่ชุมชน')):
        page = browser.new_context(viewport={'width': 1440, 'height': 900}).new_page()
        page.goto(f'{SITE}/{path}')
        check(f'{path} h1 and <title> use the new name', page.locator('h1').inner_text().strip() == title and title in page.title(), (page.locator('h1').inner_text(), page.title()))
        check(f'{path} menu and footer use the short name', page.locator('.nav-submenu a', has_text=short).count() == 1 and page.locator('.footer-links a', has_text=short).count() == 1)
        cur = page.evaluate("(()=>{const a=document.querySelector('.footer-links a[aria-current=page]');const c=getComputedStyle(a);return [+c.fontWeight,c.backgroundSize]})()")
        check(f'{path} footer link of this page is bold with no underline', cur[0] >= 600 and cur[1].split(' ')[0] in ('0px', '0%'), cur)
        page.context.close()
    for width in (1280, 1440, 1901):
        page = browser.new_context(viewport={'width': width, 'height': 900}).new_page()
        page.goto(SITE + '/network-community.php')
        tops = page.evaluate("[...document.querySelectorAll('.footer-col')].map(e=>Math.round(e.getBoundingClientRect().top))")
        over = page.evaluate("[...document.querySelectorAll('.footer-col')].some(e=>e.scrollWidth>e.clientWidth+1)")
        check(f'footer groups on one row at {width}px', len(tops) >= 5 and len(set(tops)) == 1 and not over, (tops, over))
        if width == 1440:
            page.locator('.footer').scroll_into_view_if_needed(); shot(page, 'footer-1440')
        page.context.close()
    page = browser.new_context(viewport={'width': 390, 'height': 844}).new_page()
    page.goto(SITE + '/network-community.php')
    page.locator('.footer').scroll_into_view_if_needed(); shot(page, 'footer-390')
    check('footer has no horizontal scroll at 390px', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    page.context.close()


def thai_size(page, tag):
    # Thailand's provinces together must fill most of the stage height and sit inside it.
    r = page.evaluate("""(()=>{const c=document.querySelector('.netmap__canvas').getBoundingClientRect();
      let t=1e9,b=-1e9,l=1e9,rt=-1e9;document.querySelectorAll('path[data-code^="TH-"]').forEach(p=>{const q=p.getBoundingClientRect();t=Math.min(t,q.top);b=Math.max(b,q.bottom);l=Math.min(l,q.left);rt=Math.max(rt,q.right)});
      return [(b-t)/c.height, t>=c.top-1 && b<=c.bottom+1 && l>=c.left-1 && rt<=c.right+1]})()""")
    check(f'{tag} Thailand fills at least 85% of the stage and is not cropped', r[0] >= 0.85 and r[1], r)


def empty_state(browser):
    # Needs a copy of the site whose data/network.json and data/community.json are [] (EMPTY_SITE=http://127.0.0.1:8087).
    base = os.environ.get('EMPTY_SITE', '').rstrip('/')
    if not base:
        print('SKIP empty-state checks (set EMPTY_SITE)')
        return
    for width, height in ((1901, 931), (1440, 900), (390, 844)):
        for path in ('network-community.php', 'network-international.php'):
            page = browser.new_context(viewport={'width': width, 'height': height}).new_page()
            errors = []
            watch(page, errors)
            page.goto(f'{base}/{path}')
            box = page.evaluate("(()=>{const c=document.querySelector('.netmap__canvas').getBoundingClientRect(),s=document.querySelector('.netmap__svg').getBoundingClientRect();return [c.height,s.height,s.width,c.width]})()")
            check(f'empty {path} {width}px fits the stage', page.evaluate("document.querySelector('.netmap').classList.contains('is-ready')") and box[1] <= box[0] + 1 and box[2] <= box[3] + 1, box)
            if path.startswith('network-c'): page.wait_for_timeout(200); thai_size(page, f'empty {width}px')
            check(f'empty {path} {width}px shows the message', page.locator('.netmap__empty').is_visible())
            check(f'empty {path} {width}px no console errors', not errors, errors)
            if path.startswith('network-c'): shot(page, f'empty-community-{width}')
            page.context.close()


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
    for width in (1440, 1100, 390):
        for path in ('network-international.php', 'network-community.php'):
            page = browser.new_context(viewport={'width': width, 'height': 900}).new_page()
            page.goto(f'{SITE}/{path}')
            check(f'{path} {width}px: the page does not scroll sideways', page.evaluate('document.documentElement.scrollWidth <= innerWidth'), page.evaluate('document.documentElement.scrollWidth'))
            page.context.close()
    nav_widths(browser)
    thai_data_size(browser)
    highlights(browser)
    titles_and_footer(browser)
    empty_state(browser)
    no_js(browser)
    browser.close()

print('\n%d failed' % len(fails) if fails else '\nall passed')
sys.exit(1 if fails else 0)
