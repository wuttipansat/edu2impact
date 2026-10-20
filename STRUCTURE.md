# EDU2Impact website structure

The current build is a public content site with six main pages. Authenticated account workflows are not included.

## Navigation

| Menu item | Destination | Key |
| --- | --- | --- |
| Home | index.php | home |
| Research | research.php | research |
| Innovation | innovations.php | innovations |
| Workshop | workshops.php | workshops |
| Partners | partners.php | partners |
| About | about.php | about |

Menu items are defined in config/navigation.php. Each `key` must equal the page's `$currentPage`; for Research, Innovation and Workshop it is also the dataset name, because includes/detail.php sets `$currentPage = $kind` and links back to `$kind.php`. The current page is shown as one flat purple fill. Contact is linked from the footer.

## Pages

| Page | File | Content |
| --- | --- | --- |
| Home | index.php | Hero slider (5 slides), latest research, latest innovation, workshops, upcoming events |
| Research | research.php | "Evidence library": featured study, two picks, text-first list |
| Innovation | innovations.php | "Showcase gallery": card grid (data/innovations.json) |
| Workshop | workshops.php | Workshop posts list (data/workshops.json); each post's detail page has a "request this workshop" contact box |
| Detail pages | research-detail.php, innovation-detail.php, workshop-detail.php (`?id=…`) | Single record; unknown ID returns 404 |
| Partners | partners.php | Six partner categories |
| About | about.php | What EDU2IMPACT is |
| Contact | contact.php | Contact details from data/site.json |
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
| config/partner-groups.php | One per partner group (also read by partners.php) | partners.php#partner-N |

Scoring: matching is a case-insensitive substring search (works for Thai, which has no spaces between words). The query is split on spaces and every term must match (AND). Per term: title 10, category or keywords 5, summary 3, any text 1; the whole query found in the title adds 10. Ties go to the newest date, then the title. Titles of a record's linked records (see `related` below, both directions) are added to its keywords, so searching for an innovation's name also finds the workshops linked to it.

To add a page to the index, add an entry to config/search-pages.php (`title`, `url`, `summary`, `keywords`, and `hero` if the page has one in config/heroes.php). Run `php tools/test_search.php` after changing includes/search.php. Styles for the icon, dialog and results are in assets/css/search.css, loaded on every page.

## Data and permissions

No figures are inferred from demo content. When data/site.json `demo_mode` is true, includes/header.php shows a sample-content notice under the header on every page. It is false now that the placeholder records (`research-example`, `workshop-example`) have been removed; set it back to true only while the site shows sample content.

Content files in data/:

| File | Content |
| --- | --- |
| site.json | Site name, university, contact details, demo_mode |
| research.json, innovations.json, workshops.json | Listing and detail records (`id`, `title`, `category`, `summary`, `image`, `date`, `author`, `featured`, `body`) |
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
