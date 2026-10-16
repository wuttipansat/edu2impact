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
| Workshop | workshops.php | "Agenda": next-workshop banner, upcoming and past groups (data/workshops.json) |
| Detail pages | research-detail.php, innovation-detail.php, workshop-detail.php (`?id=…`) | Single record; unknown ID returns 404 |
| Partners | partners.php | Six partner categories |
| About | about.php | What EDU2IMPACT is |
| Contact | contact.php | Contact details from data/site.json |
| Search | search.php (`?q=…`) | Site-wide search results; not in the nav, opened from the header icon |

Hero text (eyebrow, title, highlighted words, one-line intro) for every page and for the home slider lives in config/heroes.php; edit it there and both places stay in sync. Upload config/ with the PHP files.

Research, Innovation and Workshop share includes/editorial-listing.php, which loads the dataset (newest first) for the layout. Each page file sets `$kind` and a `$listing` array of labels, including `layout` and `cta`. The content area is rendered from a partial chosen by `$listing['layout']`:

| `layout` | Page | Partial |
| --- | --- | --- |
| `research` | Research | includes/listing-layouts/research.php |
| `innovations` | Innovation | includes/listing-layouts/innovations.php |
| `workshops` | Workshop | includes/listing-layouts/workshops.php |

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

Scoring: matching is a case-insensitive substring search (works for Thai, which has no spaces between words). The query is split on spaces and every term must match (AND). Per term: title 10, category or keywords 5, summary 3, any text 1; the whole query found in the title adds 10. Ties go to the newest date, then the title.

To add a page to the index, add an entry to config/search-pages.php (`title`, `url`, `summary`, `keywords`, and `hero` if the page has one in config/heroes.php). Run `php tools/test_search.php` after changing includes/search.php. Styles for the icon, dialog and results are in assets/css/search.css, loaded on every page.

## Data and permissions

No figures are inferred from demo content. When data/site.json `demo_mode` is true, includes/header.php shows a sample-content notice under the header on every page; set it to false once real content is published.

Content files in data/:

| File | Content |
| --- | --- |
| site.json | Site name, university, contact details, demo_mode |
| research.json, innovations.json, workshops.json | Listing and detail records (`id`, `title`, `category`, `summary`, `image`, `date`, `author`, `featured`, `body`) |
| events.json | Home-page events (`id`, `date` YYYY-MM-DD, `type`, `title`, `meta`, `featured`). Only events dated today or later (Asia/Bangkok) appear, soonest first, max three |

Run `python3 tools/validate_content.py` after editing any of these files.

Optional record fields (any listing dataset; currently used by the `pck-canvas` innovation). Omit them and the detail page looks as before:

| Field | Type | Shown on the detail page as |
| --- | --- | --- |
| `tool_url` | `https://…` or a local `apps/<name>/` folder containing index.html | An "open the tool" button under the summary |
| `tool_label` | text (needs `tool_url`) | The button text; defaults to "เปิดใช้เครื่องมือ" |
| `audience`, `subjects`, `features` | arrays of text | "เหมาะกับ", "ใช้ได้กับกลุ่มสาระ", "ความสามารถหลัก" lists; also indexed by search as keywords |
| `references` | array of text | "อ้างอิง" list |

## Web tools in apps/

apps/ holds self-contained web tools linked from records via `tool_url`. Upload apps/ with the PHP files.

| Folder | Tool | Record |
| --- | --- | --- |
| apps/pck-canvas/ | PCK Canvas, a single-file HTML app for designing lessons (six zones, adaptive scaffolding). Work is saved in the teacher's browser (localStorage) and can be exported/imported as JSON. It loads Google Fonts; everything else is inline. Its colours follow the site's purple palette; the six zone colours are part of the tool's design. | innovations.json `pck-canvas` |

Other checks in tools/: `python3 tools/check_contrast.py` (WCAG AA contrast of the palette pairs, including the search icon, dialog, results and agenda badges), `php tools/test_search.php` (search index, scoring, highlighting and excerpts) and `python3 tools/check_colours.py` (fails if any green or teal colour appears in assets/css/*.css or assets/images/*.svg).

Impact submission and verified dashboard data are not implemented in this public build. Public content remains backed by the existing JSON files; no account credentials or authentication database files are required.

Future integration should join Project ID, Researcher ID and Innovation ID; maintain one organization master; append adoption updates without overwriting history; verify records before public publication; calculate Home and Dashboard metrics from eligible records. KUforest/KURDI and RDI integrations are not implemented.

## Upload and verification

Back up the existing website. Upload this directory's PHP files plus config/, includes/, assets/ and apps/ to a PHP-enabled staging directory first. Preserve the production data/ directory if it has newer real content; the supplied data files are unchanged demo data from the source repository. Do not upload tools/, backup/, this guide or Git metadata to the public document root. Hero images are WebP files (max 1920px wide) in assets/images/; the old hero PNGs were removed, so delete them from the server only after the new files are live.

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
