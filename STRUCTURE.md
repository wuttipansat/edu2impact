# EDU2Impact website structure

This build is a demo web app focused on a simpler public navigation and a clearer editorial experience. It preserves the PHP/JSON architecture and existing detail URLs while redesigning the public pages around five entry points: Home, News, Research, Partners and About. Authenticated account workflows are not included.

## Navigation

| Header item | Destination |
| --- | --- |
| Home | index.php |
| News | news.php |
| Research | research.php |
| Partners | partners.php |
| About | about.php |

Contact remains accessible from the footer and collaboration calls to action.

## Demo page experience

| Page | Implementation | Experience |
| --- | --- | --- |
| Home | index.php | Existing hero-led landing page |
| News | news.php | Date-sorted one-column listing with keyword and category filters |
| Research | research.php | Auto-rotating popular research slider plus one-column research library with filters |
| Partners | partners.php | Six partner pathways, collaboration journey and demo-directory notice |
| About | about.php | Purpose, Research-to-Impact journey, principles and demo scope |

Legacy detail and section routes remain available for future expansion: research-detail.php, news-detail.php, innovation-detail.php, explore-impact.php, adoption-scale.php, impact-stories.php, enterprise.php, innovations.php, resources.php and request-use.php.

## Data and permissions

The current site is marked as demo mode in data/site.json. News, research and partner records are illustrative content for demonstrating layout and interaction. No figures are presented as verified impact data. The global demo notice is shown from data/site.json demo_mode.

Impact submission and verified dashboard data are not implemented in this public build. Public content remains backed by the existing JSON files; no account credentials or authentication database files are required.

Future integration should join Project ID, Researcher ID and Innovation ID; maintain one organization master; append adoption updates without overwriting history; verify records before public publication; calculate Home and Dashboard metrics from eligible records. KUforest/KURDI and RDI integrations are not implemented.

## Upload and verification

Back up the existing website. Upload this directory's PHP files plus config/, includes/ and assets/ to a PHP-enabled staging directory first. Preserve the production data/ directory if it has newer real content; the supplied data files are unchanged demo data from the source repository. Do not upload tools/, backup/, this guide or Git metadata to the public document root.

Run on a PHP host:

    find . -name '*.php' -not -path './backup/*' -exec php -l {} \;
    php -S 127.0.0.1:8080

Open every route above, test portfolio search and detail links, then verify mobile navigation, keyboard focus and Escape behavior. Test an unknown innovation ID and confirm HTTP 404.

Local checks completed: existing JSON validation, JavaScript syntax validation, local PHP link target checks and git diff whitespace checks. PHP runtime checks require a PHP-enabled environment; PHP was unavailable in the editing environment.
