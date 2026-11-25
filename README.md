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

Incremental refresh: `php bin/sync.php --updates` reads the supplier feed `GET /content/updates`, re-fetches only the attractions that changed (and removes deleted ones), and saves the feed cursor in the catalog meta. It starts from the time of the last full sync, so run `--all` once first. Run it from cron every few minutes. The feed's query parameter is sent as `cursor` (the response field is `nextCursor`); the staging API currently answers 404 for this feed, in which case the command prints the API error and exits with code 1. Keep the periodic full sync as well, because it is what removes attractions the feed missed.

SleekDB 2.15 (MIT) is bundled in `lib/SleekDB`; see `lib/SleekDB/SOURCE.txt` for the two small compatibility edits.

## Settings page and payments

Open `/admin/settings`. On first visit you are sent to `/admin/setup`: copy the token from `storage/setup-token.txt` (read it by FTP or the host file manager), choose an admin password and you are in. All settings (site, API key, catalog storage, Cashfree, logging) live on that one page and are saved to `storage/settings.json`. Each section has a "Test saved settings" button.

Cashfree: enter the App ID and Secret key, pick sandbox or production, and turn on "Take payment before booking". Checkout then works like this: the visitor pays on the Cashfree page, the site confirms the payment with Cashfree, and only then creates the booking with the supplier (with the payment reference as the idempotency key, so a double confirmation cannot book twice). The webhook URL `https://your-site/pay/webhook` can be added in the Cashfree dashboard as a backup confirmation. Ticket prices are in the supplier currency (USD on staging) while Cashfree charges INR, so set the exchange rate on the settings page.

Order pages (`/orders`) now require the admin login.

## Install on a server

1. Upload the folder to a host with PHP 8.2+ (extensions: curl, json, mbstring, openssl; pdo_mysql only for MySQL storage).
2. Point the web root at `public/`. On Apache without access to the vhost, the root `.htaccess` forwards to `public/` and blocks `src`, `config`, `storage`, `bin`, `lib`, `views` and `logs`. On nginx send every request that is not a file to `public/index.php`.
3. Make `storage/` writable by the web user.
4. Run `php bin/check.php`. It lists every missing requirement.
5. Open `/admin/setup`, paste the token from `storage/setup-token.txt`, set the admin password, then fill in `/admin/settings` (API key, public URL, Cashfree keys).
6. Run `php bin/sync.php --all`, then add it to cron for refreshes.

## Logs manager

`/admin/logs` (admin login required, link "Logs" in the header). Pick a daily file, filter by minimum level, search text or request id, expand a line to see its JSON details, download or delete a file. Click a request id to see every line of that request. Files larger than 50 MB must be downloaded. Retention is set in settings (default 14 days).

## Tests

- `python bin/monkey.py http://localhost:8080 <seed> <rounds>` sends random paths, query strings and POST bodies (injection strings, huge values, bad ids) and flags any 5xx response, PHP warning or unescaped output. Upstream 502s for malformed attraction ids come from the supplier API.
- Payment test (sandbox): use Cashfree test keys, enable payments, book a ticket and pay with card 4111 1111 1111 1111, any future expiry, any CVV, OTP 111000, then choose SUCCESS in the simulator.
