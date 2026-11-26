# Company Name (PHP)

Server-rendered PHP 8.0+ site over the FunEx Concourse staging API: attraction browsing, dated calendar, booking, orders.

## Run

    C:\Users\SIVA\tools\php\php.exe bin\sync.php --all
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

Incremental refresh: `php bin/sync.php --updates` reads the supplier change feed `GET /v1/events?after=<sequence>&limit=100` (the API documentation's catalogue change feed), re-fetches only the attractions whose attraction or products changed, and saves the sequence number in the catalog meta. Run `--all` once first; that records where the feed starts. If the feed answers `410 EVENTS_CURSOR_EXPIRED` the cursor is cleared and the command asks for a full sync. The same refresh is available to a web cron as `GET /cron/updates?key=YOUR_CRON_KEY` (JSON reply). Recommended schedule, following the API guide (content is safe to mirror, re-sync daily): `--updates` or `/cron/updates` every 5 to 15 minutes, and one full sync a day. Prices, availability and orders are never synced; they are read live.

SleekDB 2.15 (MIT) is bundled in `lib/SleekDB`; see `lib/SleekDB/SOURCE.txt` for the two small compatibility edits.

## Settings page and payments

Open `/admin/settings`. On first visit you are sent to `/admin/setup`: copy the token from `storage/setup-token.txt` (read it by FTP or the host file manager), choose an admin password and you are in. All settings (site, API key, catalog storage, Cashfree, logging) live on that one page and are saved to `storage/settings.json`. Each section has a "Test saved settings" button.

Cashfree: enter the App ID and Secret key, pick sandbox or production, and turn on "Take payment before booking". Checkout then works like this: the visitor pays on the Cashfree page, the site confirms the payment with Cashfree, and only then creates the booking with the supplier (with the payment reference as the idempotency key, so a double confirmation cannot book twice). The webhook URL `https://your-site/pay/webhook` can be added in the Cashfree dashboard as a backup confirmation. Ticket prices are in the supplier currency (USD on staging) while Cashfree charges INR, so set the exchange rate on the settings page.

Order pages (`/orders`) now require the admin login.

## Install on shared hosting (GoDaddy, Hostinger and similar)

Needs PHP 8.0 or newer with curl, json, mbstring and openssl (all standard). MySQL is optional; without it the catalog is stored in JSON files (SleekDB). No Composer, no SSH and no cron are required.

1. In the hosting panel pick PHP 8.0 or newer ("Select PHP Version" or "PHP Configuration") and turn on the free SSL certificate.
2. Upload the contents of the zip to the web folder (`public_html`) with the file manager or FTP. Install on a domain or sub-domain root, not in a sub-folder. Hidden files such as `.htaccess` must be uploaded too.
3. Open `https://your-site/admin`. The first visit asks for the setup token: open `storage/setup-token.txt` with the file manager, paste the token and choose an admin password (10 characters or more).
4. On `/admin/settings` enter the public URL (`https://your-site`), the FunEx API key and, for payments, the Cashfree keys. For MySQL fill in the database details from the hosting panel and pick MySQL as the storage driver. Use the test buttons.
5. Open `/admin/status` (System). Fix every red row, then press "Run sync". It loads the catalog in small steps and works inside short time limits.
6. Keep the catalog fresh. With cron: `php /home/USER/public_html/bin/sync.php --updates` every 10 minutes and `php /home/USER/public_html/bin/sync.php --all` once a night. Without cron: set a "Web cron key" on the settings page and have a free cron service call `https://your-site/cron/updates?key=YOUR_KEY` every 10 minutes and `https://your-site/cron/sync?key=YOUR_KEY` every 15 minutes. The second URL only does work when a daily full sync is due or one is still in progress; otherwise it answers `skipped`. Add `&force=1` to run a full sync now.

Security notes. The root `.htaccess` sends every request to `public/` and blocks `src`, `config`, `storage`, `bin`, `lib`, `views` and `logs`; each of those folders also has its own deny file. The System page tests this from the server. On nginx send every request that is not a file to `public/index.php` and deny those folders. Never put the API key in a file inside `public/`.

## API guide coverage

The app follows the FunEx Concourse developer guide (`api-1.json`):

- Catalogue: `GET /v1/attractions` (`q`, `near`, paging), attraction and product detail, `images[].role` (logo, card and mobile banner images are not shown in galleries), `contentVersion`, `goodToKnow`.
- Change feed: `GET /v1/events` (see the sync notes above). Content is mirrored locally; prices, availability and orders are always read live.
- Availability: the API caps a window at 30 days, so calendars ask one month at a time and the "next available date" lookahead walks consecutive 30 day windows.
- Checkout is built from each offer's `bookingContract`: traveller fields, question types, fixed options, `maxLength`, `units`, conditional questions (`showWhen`) and lookup questions (`valuesFrom: lookup`, searched through `GET /api/question-values/...`, which calls `GET .../questions/{key}/values`).
- Purchase uses an `Idempotency-Key`. Calls that are safe to repeat (all reads, and purchases that carry a key) are retried up to twice on a network failure, `429` (waiting `Retry-After`, at most 5 seconds), `502`, `503` or `504`.
- Orders: all statuses (`confirmed`, `pending`, `partial`, `cancelled`, `refund_requested`, `refunded`, `failed`) are shown, `items[]` of multi-product orders are listed, and the cancel button only appears for `confirmed` and `partial`.
- Search near a place: the attractions page has a "Show attractions near me" button that calls `GET /v1/attractions?near=lat,lng`.
- Not built: a cart for `POST /v1/attractions/{id}/orders` (multi-product orders). The API method `Concourse::purchaseMany()` exists for it.

## Logs manager

`/admin/logs` (admin login required, link "Logs" in the header). Pick a daily file, filter by minimum level, search text or request id, expand a line to see its JSON details, download or delete a file. Click a request id to see every line of that request. Files larger than 50 MB must be downloaded. Retention is set in settings (default 14 days).

## Tests

- `python bin/monkey.py http://localhost:8080 <seed> <rounds>` sends random paths, query strings and POST bodies (injection strings, huge values, bad ids) and flags any 5xx response, PHP warning or unescaped output. Upstream 502s for malformed attraction ids come from the supplier API.
- Payment test (sandbox): use Cashfree test keys, enable payments, book a ticket and pay with card 4111 1111 1111 1111, any future expiry, any CVV, OTP 111000, then choose SUCCESS in the simulator.
