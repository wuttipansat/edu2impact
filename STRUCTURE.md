# KUEDU2IMPACT website structure

The current build is a public content site. Authenticated account workflows are not included.

## Navigation

| Menu item | Destination | Key |
| --- | --- | --- |
| หน้าแรก | index.php | home |
| คลังความรู้ → งานวิจัยและบทความ, สื่อและคู่มือ | research.php, media.php | research, media |
| นวัตกรรม → สะเต็มศึกษา, เทคโนโลยีดิจิทัล, พัฒนาครู, ปฐมวัยบูรณาการ, สุขภาวะและพลศึกษา, ศึกษาอาชีวะ | innovations.php?category=… | innovations-stem, innovations-digital, innovations-teacher, innovations-early-childhood, innovations-health-pe, innovations-vocational |
| บริการของเรา → อบรมและเวิร์กช็อป, ร้านค้า | workshops.php, shop.php | workshops, shop |
| เกี่ยวกับเรา → เกี่ยวกับเรา, ติดต่อเรา | about.php, contact.php | about, contact |

Menu items are defined in config/navigation.php: five top-level items, four of them dropdown groups. The six innovation children are built from config/innovation-categories.php (slug => name), the one list that the menu, the listing filter and the badges read. A page whose menu entry differs from `$currentPage` sets `$navKey` (innovations.php?category=… and innovation detail pages do); a group can also list `also` pages that keep it active. Each child has `label`, `file` and `key`; dropdowns show titles only. A child `key` must equal the page's `$currentPage`; for research, innovations, workshops, media and news it is also the dataset name, because includes/detail.php sets `$currentPage = $kind`. The group holding the current page, and the current page itself, are shown in bold on a soft pill (no underline). Dropdowns open on click, Enter or Space, close with Esc or an outside click, and become a full-screen accordion at 1100px and below (assets/js/main.js). The header has a search button and a "ติดต่อเรา" button. At the very top of a page it has no bar: on pages with a hero (`.home-hero` or `.page-hero`) it sits transparent over the hero, which pads its top by `--header-h`; on pages without one (detail pages, 404) it uses dark text on the light page. Once the page scrolls, `.header--scrolled` fades in the floating purple bar (theme.css "Round 5: header"; all of it sits inside `:has()`, so an old browser keeps the solid bar). The mobile menu and open dropdowns always have solid backgrounds.

partners.php was removed: it now answers 301 and sends old links to about.php. config/partner-groups.php was deleted with it.

Pages added in 2026: The online tools list was removed in round 8: tools.php and resources.php answer 301 (to innovations.php and media.php). A post's own tool button (`tool_url`, shown on its detail page by `tool_url()` in includes/functions.php) stays. `media.php` / `media-detail.php` read `data/media.json`, and `news.php` / `news-detail.php` read `data/news.json`; both start empty (same fields as the other datasets: id, title, date, summary, body, image, category, public). The footer sitemap is built from the same navigation config; contact details come from data/site.json and are hidden when empty.

Home page (index.php) sections, in order: hero slider, "ผลงานใหม่ล่าสุด" (one main post plus up to six more from every public post type, newest first; posts without a real date follow the dated ones; nothing is invented to fill it), latest news (only when there is news), "งานที่ต่อยอดเป็นเวิร์กช็อปแล้ว" (each workshop's `related` source as a photo card, a connector, and the workshop as a solid card, plus a button to contact.php), latest innovations, workshops. The hero slider has no index rail: previous, next and pause buttons sit bottom right (bottom left on phones), a hidden status line tells screen readers "สไลด์ N จาก M", and auto-advance stays off when the visitor prefers reduced motion. Any section with no data is left out, and the light sections alternate white and tinted around the dark one. News, research, innovations and workshops use the same expanding card (`.xcard` in theme.css): equal size at rest; hover or keyboard focus widens one card and slides up its summary; touch screens and narrow windows show the summary all the time.

## Styles

Stylesheets load in this order (includes/header.php): `main.css` (base components), `home.css` (home only), `listing.css` (listing pages), `post-byline.css` (detail pages), `search.css`, then `theme.css` last. theme.css holds the colour tokens (`--purple-50` … `--purple-900` and the role tokens built on them), the type stack (Schibsted Grotesk for Latin, Noto Sans Thai for Thai) and the whole visual layer, organised by page section. Change colours and type there, not in the base files, and avoid `!important`. Thai text never gets negative letter-spacing.

The closing contact call above the footer (includes/footer.php) takes its title and line from the `contact` entry in config/heroes.php and is hidden on contact.php. Listing counts are written as `<span class="item-count"><strong>N</strong> label</span>` so the number can be set large.

## Pages

| Page | File | Content |
| --- | --- | --- |
| Home | index.php | Hero slider (5 slides), latest posts, research-to-workshop pairs, innovations, workshops |
| Research | research.php | "Evidence library": featured study, two picks, text-first list |
| Innovation | innovations.php | "Showcase gallery": card grid (data/innovations.json) |
| Workshop | workshops.php | Workshop posts list (data/workshops.json); each post's detail page has a "request this workshop" contact box |
| Detail pages | research-detail.php, innovation-detail.php, workshop-detail.php (`?id=…`) | Single record; unknown ID returns 404 |
| News | news.php | News list (data/news.json) |
| Media and guides | media.php | Media list (data/media.json) |
| About | about.php | What KUEDU2IMPACT is, content counts taken from the data files, and a three-step guide |
| Contact | contact.php | Phone, email and address from data/site.json only (the ones filled in); a short note shows while all three are empty |
| Search | search.php (`?q=…`) | Site-wide search results; not in the nav, opened from the header icon |

Hero text (eyebrow, title, highlighted words, one-line intro) for every page and for the home slider lives in config/heroes.php; edit it there and both places stay in sync. Upload config/ with the PHP files.

Research, Innovation and Workshop share includes/editorial-listing.php, which loads the dataset (newest first) for the layout. On Research, and in the home page's latest-research row, records with `"featured": true` come first (then newest first), so the first featured study is the lead; set `featured` to false on everything else. Each page file sets `$kind` and a `$listing` array of labels, including `layout` and `cta`. The content area is rendered from a partial chosen by `$listing['layout']`:

| `layout` | Page | Partial |
| --- | --- | --- |
| `research` | Research | includes/listing-layouts/research.php |
| `innovations` | Innovation | includes/listing-layouts/innovations.php |
| `workshops` | Workshop | includes/listing-layouts/workshops.php |

### Workshop page: workshop posts and request invitation

includes/listing-layouts/workshops.php lists every record in data/workshops.json, in file order (move a record up to show it first). Each row shows only a 68×68 document-icon badge and the title, linking to the detail page; there are no upcoming/past groups, no next-workshop banner, and no category, summary or link chips on the list. includes/editorial-listing.php passes the records both sorted (`$records`) and in file order (`$fileRecords`, used here).

`date` stays a required key: set it to the day the workshop was held, or to an empty string when it has no single date. `is_dated()` in includes/functions.php is the one test for "has a real Y-m-d date". With an empty date the detail page shows no date and the home page card shows no date. Workshops have no `category` (the validator allows it to be left out for workshops only); the detail page eyebrow then reads "WORKSHOP / EDU2IMPACT" and the home card shows "Workshop".

Each workshop is one that schools, communities and organisations can ask KUEDU2IMPACT to run, usually adapted from a research or innovation post and linked back to it with `related` (data/workshops.json has Hemo-Link and the Nonthaburi curriculum). On a workshop's detail page, includes/detail.php adds a single "ติดต่อทีมงาน" button linking to contact.php, after the facts and before the related cards (style `.workshop-contact` in assets/css/main.css); nothing is needed in the data.

Listing pages have no search, category or sort controls and ignore `category` and `sort`. A non-empty `?q=` on research.php, innovations.php or workshops.php redirects (302) to `search.php?q=…`, so old links keep working. Layout styles are in assets/css/listing.css, loaded only on these pages.

To add another listing page, add its JSON file, its key to `load_data()`, `detail_url()` and tools/validate_content.py, a two-line detail page, a navigation item, and a layout partial (or reuse one). Add the dataset to `search_index()` in includes/search.php to make it searchable.

## Site search

Every page has a magnifying-glass icon at the end of the header (next to the menu button on mobile). Without JavaScript it is a link to search.php. With JavaScript it opens a search dialog (`<dialog id="site-search">` in includes/header.php) that submits to search.php. Ctrl K, ⌘ K and "/" open the dialog; Esc, the × button or a click outside close it. On search.php the shortcuts focus the page's own field.

search.php takes one parameter, `q` (trimmed, spaces collapsed, cut to 100 characters). Results are grouped as งานวิจัย, นวัตกรรม, เวิร์กช็อป, กิจกรรม and หน้าเว็บไซต์ (at most 20 per group, with a link to the listing page when there are more), with matches highlighted. The page sends `noindex`.

What is indexed (includes/search.php, `search_index()`):

| Source | Entries | Link |
| --- | --- | --- |
| data/research.json, innovations.json, workshops.json | One per record: title, summary, category, author, body | The detail page |
| data/events.json | One per event: title, meta, type | index.php#events |
| config/search-pages.php | One per page: title, summary, keywords, plus that page's hero text from config/heroes.php | The page |

Scoring: matching is a case-insensitive substring search (works for Thai, which has no spaces between words). The query is split on spaces and every term must match (AND). Per term: title 10, category or keywords 5, summary 3, any text 1; the whole query found in the title adds 10. Ties go to the newest date, then the title. Titles of a record's linked records (see `related` below, both directions) are added to its keywords, so searching for an innovation's name also finds the workshops linked to it.

To add a page to the index, add an entry to config/search-pages.php (`title`, `url`, `summary`, `keywords`, and `hero` if the page has one in config/heroes.php). Run `php tools/test_search.php` after changing includes/search.php. Styles for the icon, dialog and results are in assets/css/search.css, loaded on every page.

## Data and permissions

No figures are inferred from demo content. When data/site.json `demo_mode` is true, includes/header.php shows a sample-content notice under the header on every page. It is false now that the placeholder records (`research-example`, `workshop-example`) have been removed; set it back to true only while the site shows sample content.

Content files in data/:

| File | Content |
| --- | --- |
| site.json | Site name, university, contact details, demo_mode |
| research.json, innovations.json, workshops.json | Listing and detail records (`id`, `title`, `category`, `summary`, `image`, `date`, `author`, `featured`, `public`, `body`) |
| events.json | Home-page events (`id`, `date` YYYY-MM-DD, `type`, `title`, `meta`, `featured`). Only events dated today or later (Asia/Bangkok) appear, soonest first, max three |

Run `python3 tools/validate_content.py` after editing any of these files.

Optional record fields (any listing dataset; `tool_url`/`tool_label` and the list fields are used by the `pck-canvas` innovation, `download_url` by a research post). Omit them and the detail page looks as before:

| Field | Type | Shown on the detail page as |
| --- | --- | --- |
| `tool_url` | `https://…` or a local `apps/<name>/` folder containing index.html | An "open the tool" button under the summary |
| `tool_label` | text (needs `tool_url`) | The button text; defaults to "เปิดใช้เครื่องมือ" |
| `audience`, `subjects`, `features` | arrays of text | "เหมาะกับ", "ใช้ได้กับกลุ่มสาระ", "ความสามารถหลัก" lists; also indexed by search as keywords |
| `references` | array of text | "อ้างอิง" list |
| `download_url` | local `assets/files/<name>.pdf` (lowercase letters, digits and hyphens; the file must exist) | An "เอกสารฉบับเต็ม" section at the end of the post with a download button showing the file size. External URLs and other file types are ignored |
| `download_label` | text (needs `download_url`) | The button text; defaults to "ดาวน์โหลด PDF" |
| `related` | array of `{"kind": "research" \| "innovations" \| "workshops", "id": "…"}` | Cards before "อ้างอิง", one per linked record (type label, title, summary), linking to its detail page; the cards have no visible heading. See below |

### Hiding a record (`public`)

Every record in research.json, innovations.json and workshops.json has `"public": true` or `false` (events may have it too). `load_data()` in includes/functions.php drops records with `"public": false` before any page sees them, so a hidden record disappears from listings, the home page, search and related cards, and its detail URL returns 404. A record without the key is treated as public. `tools/validate_content.py` requires the key on the three datasets, lists hidden records, and notes any related links that point at one.

### Links between records (`related`)

```json
"related": [
  { "kind": "research", "id": "nonthaburi-learning-city-wellbeing-curriculum" },
  { "kind": "innovations", "id": "hemo-link" }
]
```

Any listing dataset may have `related`; in practice the links live on workshops. Links are shown in both directions, so a link only needs to be entered once, on the workshop:

| Helper (includes/functions.php) | Returns |
| --- | --- |
| `kind_label($kind)` | งานวิจัย, นวัตกรรม or เวิร์กช็อป ('' for other datasets) |
| `related_records($kind, $record)` | What the record links to, in list order; unknown kinds, missing targets, self links and duplicates are skipped |
| `linked_records($kind, $record)` | `related_records()` plus every workshop whose `related` points at this record |

The detail page and search use `linked_records()`, so a research or innovation page lists the workshops that link to it without any change to research.json or innovations.json. The Workshop list does not show links. Card styles (`.record-related`) are in assets/css/main.css.

`tools/validate_content.py` checks that each link is an object with exactly `kind` and `id`, that `kind` is one of the three datasets, that the record does not link to itself or repeat a link, and, after all files are loaded, that the target exists. A broken link is skipped on the page but fails validation.

### Content templates

templates/ holds copy-and-edit examples for the content team, with a Thai guide (templates/README.md). templates/workshops.template.json has a dated workshop and a workshop adapted from research and innovation posts (`"date": ""`), both without `category`, with an image block, `audience` and `related` links to the Nonthaburi curriculum and Hemo-Link. The site does not read templates/.

### Images between paragraphs

`body` items are normally paragraphs (text). To put an image between two paragraphs, use an image block in its place in the array:

```json
{ "image": "assets/images/<name>.webp", "alt": "What the image shows", "caption": "Optional caption under the image" }
```

| Key | Required | Notes |
| --- | --- | --- |
| `image` | yes | A local file in assets/images/ (png, jpg, jpeg or webp) that exists; same rule as the record's `image` |
| `alt` | yes | Text for screen readers; describe what the image shows |
| `caption` | no | Shown under the image and indexed by site search |

The detail page renders the block as a `<figure class="prose-figure">` (styles in assets/css/main.css). A block with a missing file or no alt text is skipped on the page and fails `tools/validate_content.py`. Image paths and alt text are not indexed by search; captions are. `assets/images/content-placeholder.png` can stand in until the real image is ready.

### Thai line breaking

Browsers break Thai lines with a built-in dictionary that splits unfamiliar words (loanwords, names) mid-word. `tools/segment_thai.py` (needs `pip install pythainlp`) segments every summary and body paragraph of research, innovations, workshops, media and news, plus the hero intros in config/heroes.php (shown on page banners, home slides, the closing call and the footer), and writes word boundaries to `data/thai-breaks.json`, keyed by a SHA-1 of the paragraph text. On detail pages, `thai_text()` in includes/functions.php wraps each Thai word in `<span class="tw">` (`white-space:nowrap`, assets/css/post-byline.css) with `<wbr>` between Thai words, so lines break only between words while paragraphs stay justified on wide screens. A paragraph with no entry, because it was added or edited after the last run, is escaped as plain text exactly as before, so stale breaks never appear; `tools/validate_content.py` prints a reminder with the count. The JSON content itself is never modified, so search and copy-paste are unaffected.

## Web tools in apps/

apps/ holds self-contained web tools linked from records via `tool_url`. Upload apps/ with the PHP files.

| Folder | Tool | Record |
| --- | --- | --- |
| apps/pck-canvas/ | PCK Canvas, a single-file HTML app for designing lessons (six zones, adaptive scaffolding). Work is saved in the teacher's browser (localStorage) and can be exported/imported as JSON. It loads Google Fonts; everything else is inline. Its colours follow the site's purple palette; the six zone colours are part of the tool's design. | innovations.json `pck-canvas` |

Other checks in tools/: `python3 tools/check_contrast.py` (WCAG AA contrast of the palette pairs, including the search icon, dialog, results and agenda badges), `php tools/test_search.php` (search index, scoring, highlighting, excerpts, and the date and link helpers) and `python3 tools/check_colours.py` (fails if any green or teal colour appears in assets/css/*.css or assets/images/*.svg).

Impact submission and verified dashboard data are not implemented in this public build. Public content remains backed by the existing JSON files; no account credentials or authentication database files are required.

Future integration should join Project ID, Researcher ID and Innovation ID; maintain one organization master; append adoption updates without overwriting history; verify records before public publication; calculate Home and Dashboard metrics from eligible records. KUforest/KURDI and RDI integrations are not implemented.

## Upload and verification

Back up the existing website. Upload this directory's PHP files (including file.php) plus config/, includes/, assets/ (including assets/files/, the PDFs linked by `download_url`) and apps/ to a PHP-enabled staging directory first. Preserve the production data/ directory if it has newer real content. The supplied data files hold real records only (no placeholder records) and have `demo_mode` set to false. Do not upload tools/, templates/, backup/, this guide or Git metadata to the public document root. Hero images are WebP files (max 1920px wide) in assets/images/; the old hero PNGs were removed, so delete them from the server only after the new files are live.

Run on a PHP host:

    find . -name '*.php' -not -path './backup/*' -exec php -l {} \;
    php -S 127.0.0.1:8080

Open every page above, test the header search icon and dialog, search.php and the detail links on Research, Innovation and Workshop, then verify mobile navigation and keyboard focus. Test an unknown ID on each detail page and confirm HTTP 404. Run `python3 tools/check_contrast.py` after any colour change.

Local checks completed: PHP lint on every file, JSON validation for all five data files, contrast and colour checks, every page requested on the PHP built-in server (unknown IDs return 404), the header search dialog (mouse, keyboard and without JavaScript), search.php queries in Thai and English, the `?q=` redirect on the listing pages, demo notice on and off, and the current menu item marked on each page.

## Reserved for future phase

These files prepare an authenticated researcher/reviewer workflow. No public page loads them, and each folder blocks web access with `Require all denied` in its `.htaccess`.

| File | Purpose |
| --- | --- |
| includes/auth.php | Session, CSRF, sign-in state and role checks |
| includes/auth-page.php | Shared bootstrap for future account pages (no-store cache, HTTPS check) |
| includes/db.php | PDO connection that reads credentials from private/database.php or EDU2IMPACT_DB_CONFIG |
| database/auth-schema.sql | MySQL tables `users` (roles: researcher, reviewer, executive, admin) and `auth_rate_limits` |
| private/database.example.php | Credentials template; copy to private/database.php on the server only |

`Require all denied` needs Apache 2.4. On Nginx or another server, block includes/, config/, database/ and private/ in the server configuration, or keep them outside the document root.

## Visit counter, share buttons and social meta

**Visit counter.** `includes/visits.php`: `record_visit($pageKey)` runs from includes/header.php on every page; `visit_count($pageKey)`, `visit_total()` and `visit_label()` read the result. Page keys are the page name (`home`, `research`) or `kind/id` on detail pages. A visitor counts once per page per day (Thailand time): the file keeps only an HMAC hash of IP + User-Agent + page + date + secret for today, never the IP or User-Agent, and drops yesterday's hashes. Bots, non-GET, HEAD, prefetch, 404 and redirects are not counted. Counts live in `storage/visits.json` (read-modify-write under `flock`; per-day counts kept for 90 days). If the file cannot be written the page loads normally and nothing is counted. Counts are shown as an eye icon and a number (`visit_eye()`; screen readers hear "เข้าชม N ครั้ง"): in the detail-page byline, on post cards in the listings, the home "latest" list and the expanding cards, and in the footer ("ทั้งหมด N"); all are hidden at 0. The footer total adds up each day's distinct visitors, so it counts visits, not people. If the storage folder is missing, `visits_prepare()` creates it (with a .htaccess); if it cannot be written, one line a day goes to the PHP error log and the page loads without counts. Run `php tools/check_visits.php` on the server to see what is wrong and how to fix it.

Set up on the server: make `storage/` writable by PHP and closed to the web (`storage/.htaccess` does this on Apache; on Nginx add `location /storage { deny all; }`). Put a long random string in `config/visits.local.php` (copy config/visits.example.php; the file is not committed). Without it, a secret is created once in `storage/.visits-secret`. Run `php tools/test_visits.php` after changing includes/visits.php.

**Share buttons.** includes/share.php (`render_share()`), called from includes/detail.php before "งานที่เกี่ยวข้อง". Two round 44px icon buttons (Facebook and X) with no heading or app names; each link has `aria-label` and `title` "แชร์ไปที่ …" and the section has `aria-label="แชร์"`. Plain links, no SDK, no counts. With `site_url` in data/site.json (`https://…`, no trailing slash) the links are built on the server. Without it the buttons stay hidden until assets/js/main.js fills in the address of the page being read (page plus `?id=`); without JavaScript they stay hidden. The server never guesses a domain from the request. Canonical, `og:url`, `og:image` and `twitter:image` need `site_url` (Facebook reads them without running JavaScript), so no preview image appears until it is set. `absolute_url($path)` in includes/functions.php builds the full URL.

**Open Graph / Twitter Card.** includes/header.php writes `og:site_name`, `og:locale`, `og:type` (article on detail pages), `og:title`, `og:description` (summary, or the page's hero intro) and `twitter:card` on every page. Detail pages use the record image for `og:image`; other pages use `assets/images/hero-innovation.webp`. A 1200×630 image works best for sharing.

**Footer and back to top.** The footer is one row on desktop (about half of a 1440×900 screen): the logo and the contact details (address, phone, email) on the left, the four sitemap columns on the right. Below them `.footer-bar` sits outside the `.container`, so its top line runs across the whole screen; the copyright and total visits sit in a `.container` inside it, in line with the content above. Below 1100px the logo sits above the columns; below 760px the columns become two and the links grow to 44px. The "กลับขึ้นด้านบน" control is a round floating `.to-top` button with an arrow icon, bottom right on every page (includes/footer.php). It shows after about one screen of scrolling, is a plain `#top` link without JavaScript, and moves focus to the start of the page content after scrolling (instantly when the visitor prefers reduced motion).

**Page width.** Wide layouts (header, hero, home sections, card lists, footer) use `--wrap: 1440px` inside a gutter that grows with the screen (`--gutter: clamp(20px, 4vw, 64px)`), both in theme.css. Both are tokens: change them there to widen or narrow the whole site. The floating header bar narrows by 96px once the page is scrolled. Article text does not follow `--wrap`: detail pages stay in `.article-layout` (860px) and `.prose` (72ch) so lines remain readable.

**Link underline.** Text links share one drawn underline (theme.css, "Round 6 / 1"): a background gradient grows from left to right on hover and keyboard focus and withdraws on leaving. It follows text across several lines. The colour is `--draw-line` (the link colour; a light purple inside dark sections); the offset is `--draw-offset`. To give a new link the effect, add its selector to the lists in that block. Links inside article text (`.prose a`) keep a thin line all the time and thicken on hover. With reduced motion the line appears at once. Whole-card links (research-to-workshop, latest posts, xcards) draw the line under the title when the card is hovered or focused.

**Research-to-workshop section (home).** `.pair` rows come from each workshop's `related` list (index.php, at most three). The source (photo, then kind and title) sits on the dark section; the workshop sits on a solid light slab that overlaps the photo's corner, and rows alternate sides. No text is placed on a photo. Hover on the slab wipes it to white; hover on the source zooms the photo slightly. Below 900px the pair stacks. Each side is one link.

**Slider buttons.** `.hero-ctrl` buttons have a solid `--purple-900` fill and a 2px white ring, so they read on any slide; hover turns them `--purple-200`, press shrinks them. The slider never moves by itself (no pause button is needed); slides change only on previous/next or the arrow keys. Focus shows a white ring with a dark gap.

## Content in MySQL (optional)

Content can be read from the MySQL database `e2imedu` instead of the files. `load_data()` is the only place that reads content, so pages do not change. Since round 13 everything that belongs to a post lives in MySQL, images and PDFs included, so posts can be added, edited, hidden and given pictures in phpMyAdmin without FTP. The Thai guide for the content team is templates/README-phpmyadmin.md.

| In MySQL | Was | Table |
| --- | --- | --- |
| Posts (research, innovations, workshops, media, news), every field including `audience`, `subjects`, `features`, `download_url`, `download_label` | data/*.json | `posts` |
| Links between posts (`related`) | data/*.json | `post_links` |
| Cover images, images between paragraphs, 1200×630 share images, PDFs | assets/images/, assets/images/og/, assets/files/ | `media` |
| "ประชาสัมพันธ์" banners and "Scoop" videos | config/home.php `promos`, `videos` | `home_items` (`type` promo / video) |
| Shop | data/shop.json | `shop_items` |
| Events, site settings | data/events.json, data/site.json | `events`, `site_settings` |
| Thai line breaks | data/thai-breaks.json | `thai_breaks` |

Design images stay as files and are not in the database: header-logo, favicon, apple-touch-icon, hero-*.webp, home-hero-campus.webp, og-default.jpg, icon-*.svg, content-placeholder.png. So do apps/, config/navigation.php, config/innovation-categories.php, config/heroes.php and the other config files.

| File | Purpose |
| --- | --- |
| database/content-schema.sql | First install only: tables `posts`, `post_links`, `events`, `site_settings` with every record. It drops and rebuilds those four tables, so never import it after content has been edited in phpMyAdmin |
| database/updates/2026-10-27-01-structure.sql | Round 13 structure: tables `media`, `home_items`, `shop_items`, `thai_breaks`, the new `posts` and `events` columns, foreign keys, Thai column comments, the views `v_posts` and `v_media` |
| database/updates/2026-10-27-02-media-NN.sql | Round 13 images: every file that content uses plus its share twin, one INSERT per file (hex literal), split so no file is over ~1.5 MB |
| database/updates/2026-10-27-03-content.sql | Round 13 data: links posts to their cover rows, fills the new post columns, copies promos/videos, shop, event extras and Thai breaks |
| database/thai-breaks.sql | Written by `tools/segment_thai.py --sql` or `--phpmyadmin-json`; import after adding or editing paragraphs |
| tools/content_to_sql.py | Rebuilds the round 13 media and content files from the repo (`--max-kb` for smaller files) |
| tools/json_to_sql.py | Rebuilds database/content-schema.sql from data/*.json |
| includes/content-db.php | Reads the tables and returns records in the same shape as the files; the 503 page |
| includes/media-store.php | Resolves image and PDF references to addresses, sizes and share images |
| file.php | Sends one file from `media` (see below) |
| tools/compare_sources.php | `php tools/compare_sources.php` checks that MySQL and the files hold the same content, comparing images and PDFs by the SHA-256 of their bytes (exit code 1 when not) |

**Upload order on the server.** All SQL files are safe to import more than once: they never drop a table and never overwrite what was edited in phpMyAdmin. They work on MySQL 5.7+ and MariaDB 10.2+ (tested on MySQL 8.0.46 and MariaDB 10.11).

1. Upload the PHP files (file.php, includes/, tools/ stays off the server). With `content_source` still `'json'` nothing changes. With `'mysql'` and the round 13 tables not yet created, promos, videos, the shop and Thai breaks read their old files and the error log says which file to import; everything else already works.
2. phpMyAdmin > e2imedu > Import: `2026-10-27-01-structure.sql`, then `2026-10-27-02-media-01.sql` … `-07.sql` in order, then `2026-10-27-03-content.sql`. Use the Import tab, not the SQL tab, for the media files. If an import stops with "max_allowed_packet" or "MySQL server has gone away", rebuild smaller files with `python3 tools/content_to_sql.py --max-kb 700` and import again (rows already imported are skipped).
3. Run `php tools/compare_sources.php` (locally against a copy of the database, or on the server if PHP CLI is available). Every line must say ok.
4. Set `'content_source' => 'mysql'` in private/database.php if it is not set yet, open every page and check the pictures.
5. Only after that, the content image files can be removed from the server (they stay in the repo): the 15 files in assets/images/ and 15 in assets/images/og/ listed at the top of each `2026-10-27-02-media-NN.sql` ("ในไฟล์นี้: …"). `assets/images/clil-completed-repertory-grid.webp` and its share twin are used by nothing and were not migrated. Keep every design image listed above.

**References.** A record names a file as `media:<id>` (a row of `media`) or by its old path. content-db.php returns `media:<id>` for `posts.image_id`, `posts.download_id`, `home_items.image_id`, `shop_items.image_id` and body blocks written `{"media": <id>, "alt": "…", "caption": "…"}`; when an id column is empty, the old `image` path is used. An old path also resolves to the `media` row whose `filename` is that path (the migration keeps the old paths as filenames), and to the file on disk only when no row has it. `image_path()`, `body_image()`, `download_url()`, `file_size_label()` and `share_image_path()` in includes/functions.php do this through includes/media-store.php, so templates still print whatever `image_path()` returns. A body image needs alt text: its own `alt`, or the row's `alt_default`.

**file.php.** `file.php?id=12&v=<first 16 hex of the SHA-256>`; pages get the address from `media_url()` and never build it by hand. `sha256` and `bytes` are generated columns, so MySQL recomputes them whenever `data` changes, the address changes with them, and file.php can send `Cache-Control: public, max-age=31536000, immutable` (five minutes without a matching `v`). `ETag` is the SHA-256 and `If-None-Match` answers 304 without reading the bytes. The type is checked from the bytes with finfo (webp, jpeg, png, pdf only); images get a sandbox CSP, PDFs `Content-Disposition: inline` with a cleaned file name. It answers 404 for unknown ids, for other types, while content comes from JSON, and for files used only by hidden posts or hidden home/shop items (a file that something public also uses is still sent; share twins follow their cover). It loads no page header and counts no visits. Rows added in phpMyAdmin with only the file chosen work too: mime, width and height are read from the bytes for that request.

**Share images.** `og:image` is always a JPEG: the cover's stored twin (`media.og_of` = cover id, made from assets/images/og/ by the migration), otherwise `file.php?id=<cover>&og=1`, which draws a 1200×630 card with GD (crop to fill, or the whole picture on the theme's dark purple when it would be enlarged more than 1.4×, like tools/make_og_images.py), otherwise og-default.jpg when this server's GD cannot read the cover's format.

**Switch.** In private/database.php (copy of private/database.example.php, never committed) set `'content_source' => 'mysql'`. The default is `'json'`. The same file holds the credentials that includes/db.php uses for the future account pages. The database user for the public site only needs `SELECT`.

**When MySQL fails.** With `'mysql'` switched on there is no fallback to data/*.json any more: once content is edited in phpMyAdmin the files are out of date, and showing them would publish old text and pictures without anyone noticing. If the connection or a query fails, every page answers `503` with `Retry-After: 300`, `Cache-Control: no-store` and a short Thai notice ("ปิดปรับปรุงชั่วคราว"), file.php answers 503 too, and the PHP error log says what failed. includes/bootstrap.php buffers the page so a failure halfway through never shows half a page. The single exception is a table that a migration has not created yet (MySQL error 1146): that dataset reads its old file and the log names the migration to import. Thai line breaks never cause a 503; on an error they are just left out.

**Mapping.** `slug` is the old `id` (URLs stay the same), `post_date` is `date` (NULL when empty), `is_public` is `public`, `body`, `refs`, `audience`, `subjects` and `features` hold JSON arrays (`refs` is `references`), `image_id` and `download_id` point at `media`, `sort_order` keeps the order of the JSON file. Hidden posts (`is_public = 0`) are left out everywhere, like `"public": false`. In `shop_items`, a `price` that is a plain number is formatted as baht, any other text is shown as written.

**Thai line breaks.** In MySQL mode `thai_text()` reads the `thai_breaks` table. A paragraph added or edited in phpMyAdmin shows as plain text (lines break where the browser decides) until its breaks are added: export the `posts` table from phpMyAdmin as JSON, run `python3 tools/segment_thai.py --phpmyadmin-json posts.json`, and import the resulting database/thai-breaks.sql.

**Backups.** The university does not back up hosted data, and with images in the database a lost database means lost pictures too. Export the whole database from phpMyAdmin (Export > Custom > all tables, Format SQL, "Dump binary columns in hexadecimal notation" on, compression gzipped) at least after every editing session and keep copies off the server. tools/validate_content.py still checks data/*.json only.

## Round 7 notes

- **Innovation categories.** config/innovation-categories.php lists the six categories. A post belongs to one when its `category` equals the name exactly (data/innovations.json and `posts.category`). `innovations.php?category=slug` filters the list, changes the hero, and shows an empty state with a link to all innovations when the category has no post; an unknown slug shows everything. To add a post to a category, set its `category` to the exact name.
- **Shop.** shop.php reads `data/shop.json` (`id`, `title`, `summary`, `image`, `price`, `order_url`, `public`; `price` is a number or text, `order_url` must start with http:// or https://, otherwise the button goes to the contact page). `load_data('shop')` always reads the JSON file and skips MySQL, because the `posts` table has no price column yet. With no products the page shows "สินค้ากำลังจะมาเร็วๆ นี้".
- **News removed.** News is gone from the menu, footer, home, about and search. news.php and news-detail.php redirect (301) to the home page. data/news.json and the news rows in MySQL are kept; `load_data('news')` still works.
- **Contact details.** data/site.json and `site_settings` hold the address, phone ("02-942-8268 ต่อ 731000") and email. Since round 8 contact.php and the footer show them as plain text (no `tel:`, `mailto:` or map links), so visitors select and copy them.
- **Hero slider.** It no longer advances by itself and has no pause button; slides change on previous/next or arrow keys.
- **Updating a live MySQL database.** Do not import database/content-schema.sql again (it drops the tables). Run database/updates/2026-10-round7.sql in phpMyAdmin (SQL tab); it holds only UPDATE statements and can be run more than once.

## Round 8 notes

**หน้าแรก: ประชาสัมพันธ์ และ Scoop** (อยู่ใน `index.php`, ข้อมูลใน `config/home.php`, ตัวช่วยใน `includes/rails.php`)
- `promos` = แบนเนอร์ประชาสัมพันธ์ (ดูหัวข้อ Round 9 ด้านล่าง) ช่อง: `title`, `image`, `url` (`#` = ไม่ให้คลิก), `date` (ค.ศ. `YYYY-MM-DD` ไม่บังคับ) ไม่มีรายการ = ซ่อนทั้งส่วน ไม่มีหน้ารายการและไม่มีเมนู
- `videos` = วิดีโอ Scoop ช่อง: `title`, `youtube` (ลิงก์ watch, youtu.be หรือ shorts), `date`, และ `image` (ภาพปกในเว็บ ไม่ใส่ก็ได้) ถ้ารหัสวิดีโอไม่ครบ 11 ตัวอักษรจะข้ามรายการนั้น ไม่มีรายการ = ซ่อนทั้งส่วน
- แถวเลื่อนใช้ CSS scroll-snap + ปุ่มก่อนหน้า/ถัดไป (`data-rail*` ใน `assets/js/main.js`) ไม่เล่นเอง; ปุ่มจะ `disabled` เมื่อเลื่อนต่อไม่ได้
- วิดีโอ: คลิกปกแล้วโหลด iframe จาก `youtube-nocookie.com` ถ้าไม่มี JavaScript ปกเป็นลิงก์ไป YouTube
- CSP (`includes/bootstrap.php`) เพิ่ม `frame-src https://www.youtube-nocookie.com` (ตัวเล่น) และ `img-src ... https://i.ytimg.com` (ปกจาก YouTube) ไม่ได้แตะ `script-src` ถ้าใส่ `image` เป็นภาพในเว็บทุกรายการ จะลบ `i.ytimg.com` ออกได้

**ลบเครื่องมือออนไลน์**: ออกจากเมนู หน้าแรก about search และ heroes; `tools.php` redirect 301 ไป `innovations.php`, `resources.php` redirect 301 ไป `media.php`; ปุ่ม `tool_url` ในหน้ารายละเอียดยังอยู่

**ติดต่อเป็นข้อความธรรมดา**: เบอร์โทร ที่อยู่ อีเมล ใน contact.php และ footer ไม่เป็นลิงก์ (ลบ `phone_href()`) **Footer** ไม่มีคำโปรยแล้ว (ยังเก็บใน site.json/MySQL)

**research.php**: ลูกศรตอน hover เดิมสีเดียวกับวงกลม แก้ใน theme.css (`.lib-row:hover::before/::after`)

**หมายเหตุ**: `index.php` ยังมี `style="background-image"` ของสไลด์หลัก ซึ่ง CSP บล็อก (มีอยู่ก่อนรอบนี้ ไม่ได้แก้)

## Round 9 notes: ประชาสัมพันธ์ และ Scoop เป็นสไลด์ตรงกลาง

**Design plan ที่ใช้** (ใช้สีม่วงและฟอนต์เดิมของเว็บ ความต่างอยู่ที่เลย์เอาต์เท่านั้น)
- สี: `--purple-900` ปุ่มและปกวิดีโอ, `--purple-100` พื้นรอโหลดภาพ, `--ink-muted` ข้อความรอง, พื้นขาวของส่วน
- ตัวอักษร: ชื่อวิดีโอใช้ฟอนต์ไทยเดิมน้ำหนัก 600; วันที่และตัวนับใช้ฟอนต์ UI เดิม
- โครง: สไลด์เดียวอยู่กลาง สไลด์ข้างเคียงโผล่ที่ขอบสองฝั่งและจาง (opacity .4); ปุ่มกลมแบบ `.hero-ctrl` ทับขอบซ้าย/ขวาของภาพ (มือถือย้ายไปใต้ภาพ ชิดขวา) (round 10: เต็มจอ ไม่มีตัวนับ วนรอบ ดูด้านล่าง)
- ไม่ใช้จุด (dots), เงา, gradient หรือเลื่อนเอง
- ความโดดเด่นอยู่ที่เดียวคือการวางสไลด์กลางกับขอบที่โผล่ ส่วนอื่นเงียบ

**ขนาดภาพแบนเนอร์ประชาสัมพันธ์**: อัตราส่วน 2:1 (แนะนำ 1920 × 960 px, ไฟล์ .webp ไม่เกิน ~400 KB) ข้อความทั้งหมดอยู่ในภาพ เว็บแสดงเต็มความกว้างประมาณ 84% ของพื้นที่เนื้อหา และ crop ด้วย `object-fit: cover` จึงควรเว้นขอบปลอดภัยรอบข้อความ ภาพตัวอย่างปัจจุบันเป็นภาพเดิมของเว็บ (บางภาพใหญ่มาก เช่น 8334 px) ให้เปลี่ยนเป็นแบนเนอร์จริง

**พฤติกรรม**: ทั้งสองส่วนใช้ฟังก์ชัน `[data-stage]` ใน `assets/js/main.js` ตัวเดียว
- หาสไลด์ที่อยู่กลางจากตำแหน่งเลื่อน (`.is-active`, `aria-current`), ปุ่มก่อนหน้า/ถัดไปเป็น `disabled` ที่หัวและท้าย ไม่วนรอบ
- คลิกหรือ Tab ไปที่สไลด์ข้างเคียง = เลื่อนมากลางก่อน (ยังไม่เปิดลิงก์/ไม่เล่น) กดอีกครั้งจึงเปิดหรือเล่น
- ลูกศรซ้าย/ขวาบนคีย์บอร์ดเลื่อนทีละสไลด์; `prefers-reduced-motion` เลื่อนทันที
- ตัวนับใช้ `aria-live="polite"` อ่านชื่อสไลด์เมื่อเปลี่ยน
- Scoop: เล่นได้เฉพาะวิดีโอกลาง เลื่อนออกแล้ว iframe ถูกเอาออกและคืนปก (หยุดเสียง) ชื่อและวันที่แสดงเฉพาะวิดีโอกลาง
- ลบคำอธิบายใต้หัวข้อ Scoop และ CSS/JS ของแถวการ์ดรอบ 8 แล้ว (ฟิลด์ `label` ของ promos ยังรับแต่ไม่แสดง)

## Round 10 notes: เต็มจอ ไม่มีตัวเลข และวนรอบ

- **เต็มจอ**: หัวข้อยังอยู่ใน `.container` ส่วนแถว `.stage` อยู่นอก container (ใน `index.php` ปิด container ก่อนแถว) และกว้างเท่า section จึงไม่รวม scrollbar (ไม่ใช้ `100vw`) ขนาดคำนวณด้วย container query (`container-type:inline-size`, หน่วย `cqw`)
  - ประชาสัมพันธ์: สไลด์กลาง `min(76cqw, 1320px)`; Scoop: `min(60cqw, 1040px)`; จอ ≤640px: 87% ทั้งคู่
  - ช่องว่างซ้าย/ขวาของแถว = (ความกว้างจอ − สไลด์) / 2 สไลด์ข้างเคียงยื่นถึงขอบจอและถูกตัดโดยขอบจอเท่านั้น
  - ปุ่มวางกลางความสูงภาพของสไลด์กลาง (`--ctrl-band`) บนมือถือย้ายไปใต้ภาพ ชิดขวาแนวเดียวกับสไลด์
- **ไม่มีตัวเลข**: ลบ "N / M" ที่มองเห็น เหลือ `<p class="visually-hidden" aria-live="polite" data-stage-count>` ประกาศ "สไลด์ 2 จาก 6: ชื่อ" / "วิดีโอ 2 จาก 4: ชื่อ" ให้โปรแกรมอ่านจอ
- **วนรอบ (clone)**: JS คัดลอกสไลด์ 2 ใบท้ายไปหน้าแถวและ 2 ใบแรกไปท้ายแถว
  - ตัวคัดลอกมี `aria-hidden="true"`, `inert`, ลิงก์ `tabindex="-1"`, ไม่มี `id`, ไม่นับใน "N จาก M" และไม่มี iframe
  - เมื่อแถวหยุดบนตัวคัดลอก (`scrollend`, หรือ timeout 140 ms ถ้าเบราว์เซอร์ไม่รองรับ) จะกระโดดไปสไลด์จริงที่หน้าตาเหมือนกันทันที โดยปิด snap และ transition ชั่วคราว (`data-stage-jumping`)
  - ปุ่มไม่ disabled แล้ว; สไลด์แรกเห็นสไลด์สุดท้ายทางซ้ายตั้งแต่โหลด
  - คลิกตัวคัดลอกที่ขอบ = เลื่อนมากลางแล้วกระโดดไปสไลด์จริง ไม่เปิดลิงก์/ไม่เล่น
  - Tab ผ่านเฉพาะสไลด์จริง ลูกศรซ้าย/ขวาวนรอบได้; reduced-motion เปลี่ยนทันทีแต่ยังวนรอบ
  - มีสไลด์ใบเดียว: ไม่คัดลอก ซ่อนปุ่ม ไม่วน; ไม่มี JS: แถวเลื่อนธรรมดาเต็มจอ
- เลือกใช้ clone แทนการย้ายลำดับ DOM เพราะการย้ายสไลด์ระหว่างเลื่อนทำให้ focus และ iframe ที่กำลังเล่นหลุดได้

## Round 11 notes

### Thai line breaks (thai-breaks.json)
- `data/thai-breaks.json` had leftover merge-conflict markers (`<<<<<<< HEAD`), so it was not valid JSON and `thai_text()` showed plain text on every page; the browser then split words such as "เวิร์|กช็อป".
- Rebuilt with `python3 tools/segment_thai.py`. Run it again after any change to Thai text that passes through `thai_text()` (summaries, body paragraphs, `config/heroes.php` intros).
- `python3 tools/segment_thai.py --mysql` segments the texts as the live site reads them (MySQL when `private/database.php` exists, otherwise `data/*.json`). Use it when content was edited in MySQL only. The default mode reads `data/*.json` and keeps records marked `"public": false`.
- `tools/validate_content.py` now fails when a file under data/, config/, includes/ or database/ contains `<<<<<<<`, `=======` or `>>>>>>>` at the start of a line, or when `data/thai-breaks.json` is not valid JSON. `thai_breaks()` also writes one `error_log` line if the file cannot be read.

### Site name: KUEDU2IMPACT
- Every visible name comes from `data/site.json` / `site_settings.name` (title, og:site_name, logo label, footer) plus the strings in index.php, about.php, config/heroes.php and config/search-pages.php. Search keywords keep the old spelling `edu2impact` next to `kuedu2impact`.
- Internal names are kept on purpose, because renaming them has no visible benefit and would break things: env vars `EDU2IMPACT_DB_CONFIG` and `EDU2IMPACT_DATA_SOURCE` (the host may already set them), session name `edu2impact_session` (renaming logs every admin out), the prefixes in `error_log` lines, temp-file names, and the database name.

### Faculty logo
- `assets/images/header-logo.webp` (466×160, lossless, transparent) replaces `header-logo.png`. It is the faculty's white-on-transparent logo, trimmed to its artwork and sits directly on the purple header; the white plate, padding and radius were removed from `.brand-logo`. Height 40px on desktop, 36px at 1100px and below; the link keeps a 44px tap target.
- The untouched original (2000×600 PNG) is kept in `assets/images/source/faculty-logo.png` and is not loaded by any page. Do not recolour the logo file.

### Facebook share picture
- Cause 1: `site_url` is empty in `data/site.json` and `site_settings`, so `includes/header.php` prints no `og:image`, `og:url` or canonical (it never guesses a domain from the request). Set it (SQL is in `database/updates/2026-10-25-01.sql`, commented until the domain is known).
- Cause 2: covers were WebP. `share_image_path()` (includes/functions.php) now returns the JPEG twin `assets/images/og/<cover name>.jpg`, or `assets/images/og-default.jpg` (campus photo, 1200×630) when a post has no cover or no twin.
- `python3 tools/make_og_images.py` makes the twins (1200×630, under 300 KB, covers that would need enlarging by more than 1.4x are placed whole on the theme purple). Run it next to `segment_thai.py` whenever a cover image is added. Page banners (`hero-*`, `home-hero*`) get no twin.
- Extra tags: `og:image:secure_url`, `og:image:type` (image/jpeg), `og:image:width/height`, `og:image:alt`. The share buttons already build their links on the server once `site_url` is set.
- After uploading: run the SQL, open https://developers.facebook.com/tools/debug/, paste the home and one post link, press "Scrape Again" (Facebook keeps the old card), and if it reports HTTP 403 check that the host does not block the `facebookexternalhit` user agent.

### Owner names (author)
- The table of names to correct was empty in round 11, so no name was changed. Only the style was unified: `รศ. ดร.ศศิเทพ` and `รศ. ดร.ชาตรี` had a space after "ดร."; now no name has one. Done in `data/innovations.json`, the seed in `database/content-schema.sql`, and as `UPDATE posts … REPLACE()` lines in `database/updates/2026-10-25-01.sql` (author, summary and body).
- To correct a name later: change it in `data/*.json` and add `UPDATE posts SET author = '…' WHERE author = '…';` to the SQL file, then run `python3 tools/segment_thai.py` (bodies are hashed).

### Text changes (round 11)
- Home and research intros in `config/heroes.php` (the home slide, the research slide and the research.php banner all read `research.intro`). The footer line comes from `site.name` and `site.university` (now "คณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์"). about.php names the faculty before the university.

### Page scale (round 11)
At 100% zoom the pages looked too big. Sizes were cut with the type tokens and clamp() values in `assets/css/theme.css` only (no `zoom`, `transform: scale` or `html{font-size:%}`).

I could not measure other sites (edu.ku.ac.th, ku.ac.th, edu.chula.ac.th, ed.stanford.edu, gse.harvard.edu): the sandbox proxy refuses those hosts. The target values below come from common practice for Thai university and education sites at 1440px (body 16–17px, h1 under 64px, header about 64px, hero not taller than three quarters of the window). Re-measure on a normal connection if you want to tune them.

| Part (1440×900) | Before | After |
|---|---|---|
| body text | 18px | 17px (16px on small screens) |
| meta / labels | 14–15px | 14px |
| lead under the title | 22px | 19px |
| home hero title | 95px | 63px |
| inner page title | 76px | 50px |
| section heading (h2) | 48px | 34px |
| card headings (h3) | 20–30px | 18–20px (feature 24px) |
| header | 74px | 62px (tap targets stay 44px) |
| logo | 48px | 40px |
| home hero height | 860px | 666px (`clamp(520px, 74svh, 680px)`) |
| inner hero height | 437–522px | 302–334px |
| section padding | 104px | 72px (48px on mobile) |
| list counter `.item-count strong` | 80px | 48px |
| container `--wrap` / gutter | 1440px / 64px | 1280px / 56px |
| promo slide / Scoop slide max | 1320px / 1040px | 1120px / 880px |

- The home hero keeps one height for every slide (the copy is bottom-aligned), and the long intros fit at 1280×720, 1366×768, 390 and 360px (checked slide by slide).
- The hero lead may be up to 58ch wide; inner-page leads 62ch.

### Contact map
- contact.php shows a Google Maps embed under the three contact cards: `https://www.google.com/maps?q=<search text>&output=embed&hl=th` (no API key). The pin comes from the search text "คณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์ บางเขน" (variable `$mapQuery`). If Google puts the pin on the wrong building, replace the text with coordinates, for example `13.84,100.57` (check them in Google Maps first).
- The iframe is lazy-loaded and titled; below it is a text link "เปิดใน Google Maps" (new tab, 44px high). Height 400px on desktop, 300px on phones, 12px corners, 1px border, no shadow.
- CSP: only `https://www.google.com` was added to `frame-src` in includes/bootstrap.php. No other directive changed.

### Home: links to the faculty's other sites
- Last section of the home page (id `faculty-sites`, heading "เว็บไซต์ของคณะ"), before the closing call in the footer. Data: `config/faculty-links.php` (title, https url, optional note). `home_faculty_links()` in includes/rails.php drops rows without a title or an https address. Tone follows the alternating system (this one comes out light).
- Two designs were built and compared in screenshots:
  1. a two-column directory with hairline rows and a ring arrow, which was calm but read as a list rather than as buttons;
  2. six solid purple tiles, which read as buttons but made a heavy block right above the dark closing call.
  The chosen design is in between: a 3×2 grid of outlined buttons (white, 1px hairline, 10px corners, no shadow). Each shows the site name, its web address (taken from the url) and a ring with an arrow; hover and keyboard focus fill the whole button purple. 2 columns at 1024px and below, 1 column at 560px and below. At rest it stays quiet so it does not compete with the content above.
- Every link opens in a new tab (`target="_blank" rel="noopener"`), says "(เปิดในแท็บใหม่)" to screen readers, is at least 88px high, and uses our own inline arrow icon (the CSP does not allow the target sites' favicons). The longest name fits on one line down to 360px.

### Workshops page (round 11)
- Problem: a bare list of titles with a document icon (it looked like a list of files), a large gap above the heading and a giant "2". Nothing said who teaches, what it is, or what it builds on.
- Design plan (frontend-design). Colour: existing purple tokens plus the lilac `--purple-200` block already used by the home "From Innovation to Workshop" pairs. Type: Noto Sans Thai, titles 20–24px/600, summary 16–17px muted, small labels 14–16px. Layout: rows with the cover on the left (4:3, 220–320px) and text on the right; one pattern for 1, 2 or many items, no zigzag. Memorable element: the cover with a lilac block offset behind it, borrowed from the home page so the pages belong together. Everything else is hairlines and type.
- Checked against the "AI default" list: no identical shadowed cards, no gradient, no capital-letter labels above headings, no 01/02/03, no arrow on every link, no scroll animation.
- Each row: cover, title (stretched link to the detail page), summary (3 lines), speaker (วิทยากร), what it builds on ("ต่อยอดจากนวัตกรรม/งานวิจัย" from `related`, its own link above the stretched one so there are no nested links), date only when set, and "ดูรายละเอียด". A workshop with no cover drops the picture column; one without a speaker or `related` drops those lines.
- A button "ขอจัดเวิร์กช็อปที่โรงเรียนของคุณ" (to contact.php, same wording as the home page) sits in the heading row, visible before any scrolling; the closing call is below the list. No steps, prices, group sizes or durations were invented.
- The count is now a small "N รายการ" under the heading instead of a 48px number. Tested with 8 temporary records (with and without cover, speaker, source and date) at 1440, 1024, 390 and 360px; the mock data was not committed.
