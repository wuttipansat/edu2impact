# EDU2Impact website structure

The current build is a public content site. Authenticated account workflows are not included.

## Navigation

| Menu item | Destination | Key |
| --- | --- | --- |
| หน้าแรก | index.php | home |
| คลังความรู้ → งานวิจัยและบทความ, สื่อและคู่มือ, เครื่องมือออนไลน์ | research.php, media.php, tools.php | research, media, tools |
| นวัตกรรม → สะเต็มศึกษา, เทคโนโลยีดิจิทัล, พัฒนาครู, ปฐมวัยบูรณาการ, สุขภาวะและพลศึกษา, ศึกษาอาชีวะ | innovations.php?category=… | innovations-stem, innovations-digital, innovations-teacher, innovations-early-childhood, innovations-health-pe, innovations-vocational |
| บริการของเรา → อบรมและเวิร์กช็อป, ร้านค้า | workshops.php, shop.php | workshops, shop |
| เกี่ยวกับเรา → เกี่ยวกับเรา, ติดต่อเรา | about.php, contact.php | about, contact |

Menu items are defined in config/navigation.php: five top-level items, four of them dropdown groups. The six innovation children are built from config/innovation-categories.php (slug => name), the one list that the menu, the listing filter and the badges read. A page whose menu entry differs from `$currentPage` sets `$navKey` (innovations.php?category=… and innovation detail pages do); a group can also list `also` pages that keep it active. Each child has `label`, `file` and `key`; dropdowns show titles only. A child `key` must equal the page's `$currentPage`; for research, innovations, workshops, media and news it is also the dataset name, because includes/detail.php sets `$currentPage = $kind`. The group holding the current page, and the current page itself, are shown in bold on a soft pill (no underline). Dropdowns open on click, Enter or Space, close with Esc or an outside click, and become a full-screen accordion at 1100px and below (assets/js/main.js). The header has a search button and a "ติดต่อเรา" button. At the very top of a page it has no bar: on pages with a hero (`.home-hero` or `.page-hero`) it sits transparent over the hero, which pads its top by `--header-h`; on pages without one (detail pages, 404) it uses dark text on the light page. Once the page scrolls, `.header--scrolled` fades in the floating purple bar (theme.css "Round 5: header"; all of it sits inside `:has()`, so an old browser keeps the solid bar). The mobile menu and open dropdowns always have solid backgrounds.

partners.php was removed: it now answers 301 and sends old links to about.php. config/partner-groups.php was deleted with it.

Pages added in 2026: `tools.php` lists every research or innovation record that has a `tool_url` (`tool_records()` in includes/functions.php). `media.php` / `media-detail.php` read `data/media.json`, and `news.php` / `news-detail.php` read `data/news.json`; both start empty (same fields as the other datasets: id, title, date, summary, body, image, category, public). The footer sitemap is built from the same navigation config; contact details come from data/site.json and are hidden when empty.

Home page (index.php) sections, in order: hero slider, "ผลงานใหม่ล่าสุด" (one main post plus up to six more from every public post type, newest first; posts without a real date follow the dated ones; nothing is invented to fill it), latest news (only when there is news), "งานที่ต่อยอดเป็นเวิร์กช็อปแล้ว" (each workshop's `related` source as a photo card, a connector, and the workshop as a solid card, plus a button to contact.php), latest innovations, online tools, workshops. The hero slider has no index rail: previous, next and pause buttons sit bottom right (bottom left on phones), a hidden status line tells screen readers "สไลด์ N จาก M", and auto-advance stays off when the visitor prefers reduced motion. Any section with no data is left out, and the light sections alternate white and tinted around the dark one. News, research, innovations and workshops use the same expanding card (`.xcard` in theme.css): equal size at rest; hover or keyboard focus widens one card and slides up its summary; touch screens and narrow windows show the summary all the time.

## Styles

Stylesheets load in this order (includes/header.php): `main.css` (base components), `home.css` (home only), `listing.css` (listing pages), `post-byline.css` (detail pages), `search.css`, then `theme.css` last. theme.css holds the colour tokens (`--purple-50` … `--purple-900` and the role tokens built on them), the type stack (Schibsted Grotesk for Latin, Noto Sans Thai for Thai) and the whole visual layer, organised by page section. Change colours and type there, not in the base files, and avoid `!important`. Thai text never gets negative letter-spacing.

The closing contact call above the footer (includes/footer.php) takes its title and line from the `contact` entry in config/heroes.php and is hidden on contact.php. Listing counts are written as `<span class="item-count"><strong>N</strong> label</span>` so the number can be set large.

## Pages

| Page | File | Content |
| --- | --- | --- |
| Home | index.php | Hero slider (5 slides), latest posts, news, research-to-workshop pairs, innovations, tools, workshops |
| Research | research.php | "Evidence library": featured study, two picks, text-first list |
| Innovation | innovations.php | "Showcase gallery": card grid (data/innovations.json) |
| Workshop | workshops.php | Workshop posts list (data/workshops.json); each post's detail page has a "request this workshop" contact box |
| Detail pages | research-detail.php, innovation-detail.php, workshop-detail.php (`?id=…`) | Single record; unknown ID returns 404 |
| News | news.php | News list (data/news.json) |
| Online tools | tools.php | Records with a `tool_url` |
| Media and guides | media.php | Media list (data/media.json) |
| About | about.php | What EDU2IMPACT is, content counts taken from the data files, and a three-step guide |
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

Each workshop is one that schools, communities and organisations can ask EDU2IMPACT to run, usually adapted from a research or innovation post and linked back to it with `related` (data/workshops.json has Hemo-Link and the Nonthaburi curriculum). On a workshop's detail page, includes/detail.php adds a single "ติดต่อทีมงาน" button linking to contact.php, after the facts and before the related cards (style `.workshop-contact` in assets/css/main.css); nothing is needed in the data.

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

Back up the existing website. Upload this directory's PHP files plus config/, includes/, assets/ (including assets/files/, the PDFs linked by `download_url`) and apps/ to a PHP-enabled staging directory first. Preserve the production data/ directory if it has newer real content. The supplied data files hold real records only (no placeholder records) and have `demo_mode` set to false. Do not upload tools/, templates/, backup/, this guide or Git metadata to the public document root. Hero images are WebP files (max 1920px wide) in assets/images/; the old hero PNGs were removed, so delete them from the server only after the new files are live.

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

**Footer and back to top.** The footer is one row on desktop (about half of a 1440×900 screen): the logo, the tagline from data/site.json and the contact details (address, phone, email) on the left, the four sitemap columns on the right. Below them `.footer-bar` sits outside the `.container`, so its top line runs across the whole screen; the copyright and total visits sit in a `.container` inside it, in line with the content above. Below 1100px the logo sits above the columns; below 760px the columns become two and the links grow to 44px. The "กลับขึ้นด้านบน" control is a round floating `.to-top` button with an arrow icon, bottom right on every page (includes/footer.php). It shows after about one screen of scrolling, is a plain `#top` link without JavaScript, and moves focus to the start of the page content after scrolling (instantly when the visitor prefers reduced motion).

**Page width.** Wide layouts (header, hero, home sections, card lists, footer) use `--wrap: 1440px` inside a gutter that grows with the screen (`--gutter: clamp(20px, 4vw, 64px)`), both in theme.css. Both are tokens: change them there to widen or narrow the whole site. The floating header bar narrows by 96px once the page is scrolled. Article text does not follow `--wrap`: detail pages stay in `.article-layout` (860px) and `.prose` (72ch) so lines remain readable.

**Link underline.** Text links share one drawn underline (theme.css, "Round 6 / 1"): a background gradient grows from left to right on hover and keyboard focus and withdraws on leaving. It follows text across several lines. The colour is `--draw-line` (the link colour; a light purple inside dark sections); the offset is `--draw-offset`. To give a new link the effect, add its selector to the lists in that block. Links inside article text (`.prose a`) keep a thin line all the time and thicken on hover. With reduced motion the line appears at once. Whole-card links (research-to-workshop, latest posts, xcards) draw the line under the title when the card is hovered or focused.

**Research-to-workshop section (home).** `.pair` rows come from each workshop's `related` list (index.php, at most three). The source (photo, then kind and title) sits on the dark section; the workshop sits on a solid light slab that overlaps the photo's corner, and rows alternate sides. No text is placed on a photo. Hover on the slab wipes it to white; hover on the source zooms the photo slightly. Below 900px the pair stacks. Each side is one link.

**Slider buttons.** `.hero-ctrl` buttons have a solid `--purple-900` fill and a 2px white ring, so they read on any slide; hover turns them `--purple-200`, press shrinks them. The slider never moves by itself (no pause button is needed); slides change only on previous/next or the arrow keys. Focus shows a white ring with a dark gap.

## Content in MySQL (optional)

Content can be read from the MySQL database `e2imedu` instead of data/*.json. `load_data()` is the only place that reads content, so no page changes.

| File | Purpose |
| --- | --- |
| database/content-schema.sql | Tables `posts` (research, innovations, workshops, media, news), `post_links` (the old `related` field), `events`, `site_settings`, plus every current record. Import it in phpMyAdmin. Importing it again replaces those four tables with data/*.json |
| tools/json_to_sql.py | Rebuilds database/content-schema.sql from data/*.json |
| includes/content-db.php | Reads the tables and returns records in the same shape as the JSON files |
| tools/compare_sources.php | `php tools/compare_sources.php` checks that MySQL and data/*.json hold the same content (exit code 1 when not) |

**Switch.** In private/database.php (copy of private/database.example.php, never committed) set `'content_source' => 'mysql'`. The default is `'json'`. The same file holds the credentials that includes/db.php uses for the future account pages. The database user for the public site only needs `SELECT`.

**When MySQL fails.** If the connection or a query fails, `load_data()` uses data/*.json and writes one line per request to the PHP error log ("edu2impact: MySQL unavailable, using data/*.json instead"). Once content is edited in the database, the JSON files fall behind, so a failure shows the older JSON content until MySQL is back.

**Mapping.** `slug` is the old `id` (URLs stay the same), `post_date` is `date` (NULL when empty), `is_public` is `public`, `body` and `refs` hold JSON arrays (`refs` is `references`), `sort_order` keeps the order of the JSON file. Hidden posts (`is_public = 0`) are left out everywhere, like `"public": false`.

**Backups.** The university does not back up hosted data. Export the database from phpMyAdmin (Export tab, SQL) regularly. tools/validate_content.py, tools/segment_thai.py and data/thai-breaks.json still work from data/*.json; while content is edited in MySQL, they check the JSON copy only.

## Round 7 notes

- **Innovation categories.** config/innovation-categories.php lists the six categories. A post belongs to one when its `category` equals the name exactly (data/innovations.json and `posts.category`). `innovations.php?category=slug` filters the list, changes the hero, and shows an empty state with a link to all innovations when the category has no post; an unknown slug shows everything. To add a post to a category, set its `category` to the exact name.
- **Shop.** shop.php reads `data/shop.json` (`id`, `title`, `summary`, `image`, `price`, `order_url`, `public`; `price` is a number or text, `order_url` must start with http:// or https://, otherwise the button goes to the contact page). `load_data('shop')` always reads the JSON file and skips MySQL, because the `posts` table has no price column yet. With no products the page shows "สินค้ากำลังจะมาเร็วๆ นี้".
- **News removed.** News is gone from the menu, footer, home, about and search. news.php and news-detail.php redirect (301) to the home page. data/news.json and the news rows in MySQL are kept; `load_data('news')` still works.
- **Contact details.** data/site.json and `site_settings` hold the address, phone ("02-942-8268 ต่อ 731000") and email. `phone_href()` builds the `tel:` link from the digits before "ต่อ", so the extension is shown but not dialled.
- **Hero slider.** It no longer advances by itself and has no pause button; slides change on previous/next or arrow keys.
- **Updating a live MySQL database.** Do not import database/content-schema.sql again (it drops the tables). Run database/updates/2026-10-round7.sql in phpMyAdmin (SQL tab); it holds only UPDATE statements and can be run more than once.
