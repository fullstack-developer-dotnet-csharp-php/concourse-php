# FunEx Tickets (PHP)

Server-rendered PHP 8.2+ site over the FunEx Concourse staging API: attraction browsing, dated calendar, booking, orders.

## Run

    C:\Users\SIVA\tools\php\php.exe bin\warm-cache.php
    run.bat

Open http://localhost:8080. Output is logged to `logs\output.log`.

## Configuration

`config/config.php` holds defaults. `config/config.local.php` overrides them and holds the API key (keep it out of version control). The key can also come from the `FUNEX_API_KEY` environment variable.

## Structure

- `public/index.php` front controller, `public/assets` CSS, JS and icon
- `src/Api` HTTP client and `ApiException`
- `src/Services` typed API wrapper and catalog (all attractions cached for facets, filters and sitemap)
- `src/Models` typed Attraction, Product, Offer, Order
- `src/Controllers`, `views/` pages

## SEO and AI

- Clean URLs `/attractions/{id}/{slug}`, 301 to the canonical slug
- Title, description, canonical, Open Graph, Twitter, robots meta per page
- JSON-LD: WebSite with SearchAction, Organization, ItemList, TouristAttraction, Product with Offer, FAQPage, BreadcrumbList
- `/sitemap.xml` (every attraction with lastmod), `/robots.txt` (AI crawlers allowed), `/llms.txt`
- Server-rendered semantic HTML, one H1 per page, lazy images with dimensions, no render-blocking scripts
- Search, ordering and booking pages are `noindex`

## Errors

API and application errors render a detailed error page (status, code, URL, response body, trace). Nothing is hidden and no fallback data is used.

## Notes

- API responses are cached on disk (catalog 6h, details 1h, availability and pricing 5 min) to stay under the API rate limit. Orders are never cached.
- The staging API ignores the `city` query parameter, so destination and category filters run on the cached catalog.
- The Orders area has no login. Do not expose it publicly; set `site.orders_enabled` to `false` to remove it.

## Catalog storage

Attractions are stored locally and refreshed by a sync, so pages and search do not wait for the API.

| Setting | Values |
|---|---|
| `CATALOG_STORE` | `sleek` (default, JSON files in `storage/db`, no database needed) or `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | MySQL connection, used only when `CATALOG_STORE=mysql` |

Load or refresh the catalog with `php bin/sync.php --all` (all pages in one go) or `php bin/sync.php` (5 pages per run, resumes where it stopped, for hosts with short time limits). Run it from cron to keep content fresh.

SleekDB 2.15 (MIT) is bundled in `lib/SleekDB`; see `lib/SleekDB/SOURCE.txt` for the two small compatibility edits.

## Settings page and payments

Open `/admin/settings`. On first visit you are sent to `/admin/setup`: copy the token from `storage/setup-token.txt` (read it by FTP or the host file manager), choose an admin password and you are in. All settings (site, API key, catalog storage, Cashfree, logging) live on that one page and are saved to `storage/settings.json`. Each section has a "Test saved settings" button.

Cashfree: enter the App ID and Secret key, pick sandbox or production, and turn on "Take payment before booking". Checkout then works like this: the visitor pays on the Cashfree page, the site confirms the payment with Cashfree, and only then creates the booking with the supplier (with the payment reference as the idempotency key, so a double confirmation cannot book twice). The webhook URL `https://your-site/pay/webhook` can be added in the Cashfree dashboard as a backup confirmation. Ticket prices are in the supplier currency (USD on staging) while Cashfree charges INR, so set the exchange rate on the settings page.

Order pages (`/orders`) now require the admin login.
