# EDU2Impact website structure

This update reorganizes the existing PHP website using the supplied menu document. It preserves the public JSON content and existing detail URLs. The current build is a public content site; authenticated account workflows are not included.

## Navigation

| Header group | Destination |
| --- | --- |
| Home | index.php |
| Impact | explore-impact.php, adoption-scale.php, impact-stories.php |
| Innovations | innovations.php, enterprise.php |
| Partners | partners.php |
| Research | research.php, resources.php |

About, news and contact remain accessible from the footer.

## All 15 document pages

| Document page | Implementation | Status |
| --- | --- | --- |
| Home | index.php | Journey, unavailable KPI states, featured demo innovations, stories and partners entry points |
| Explore Impact | explore-impact.php | Public empty state; map, filters and verified records require real data |
| Innovation Portfolio | innovations.php | Existing searchable, category-filtered JSON listing |
| Innovation Profile | innovation-detail.php?id=… | Existing content plus problem, users, evidence, readiness, adoption, impact and IP sections |
| Adoption & Scale | adoption-scale.php | Stage journey and empty adoption history |
| Impact Stories | impact-stories.php | Story journey and empty verified-story state |
| Enterprise & Spin-off | enterprise.php | Enterprise journey and support entry |
| Partners & Users | partners.php | Organization categories and empty public directory |
| Research & Evidence | research.php | Existing searchable research listing |
| Request to Use / Collaborate | request-use.php?id=… | Innovation context and contact entry; online lead submission unavailable |
| Resources & Downloads | resources.php | Empty resource library; no invented downloads |

## Data and permissions

There are currently no verified impact, adoption, partner, resource or story datasets. No figures are inferred from demo content. Home metrics display an unavailable marker, not a hardcoded zero. The global sample-content notice follows data/site.json demo_mode.

Impact submission and verified dashboard data are not implemented in this public build. Public content remains backed by the existing JSON files; no account credentials or authentication database files are required.

Future integration should join Project ID, Researcher ID and Innovation ID; maintain one organization master; append adoption updates without overwriting history; verify records before public publication; calculate Home and Dashboard metrics from eligible records. KUforest/KURDI and RDI integrations are not implemented.

## Upload and verification

Back up the existing website. Upload this directory's PHP files plus config/, includes/ and assets/ to a PHP-enabled staging directory first. Preserve the production data/ directory if it has newer real content; the supplied data files are unchanged demo data from the source repository. Do not upload tools/, backup/, this guide or Git metadata to the public document root.

Run on a PHP host:

    find . -name '*.php' -not -path './backup/*' -exec php -l {} \;
    php -S 127.0.0.1:8080

Open every route above, test portfolio search and detail links, then verify mobile navigation, keyboard focus and Escape behavior. Test an unknown innovation ID and confirm HTTP 404.

Local checks completed: existing JSON validation, JavaScript syntax validation, local PHP link target checks and git diff whitespace checks. PHP runtime checks require a PHP-enabled environment; PHP was unavailable in the editing environment.
