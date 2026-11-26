<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Settings
{
    private const FILE = __DIR__ . '/../../storage/settings.json';

    public static function schema(): array
    {
        return [
            'Site' => [
                ['site.name', 'Site name', 'text', 'Shown in the header, page titles and emails.'],
                ['site.tagline', 'Tagline', 'text', 'Short line used in the footer and meta description.'],
                ['site.public_url', 'Public URL', 'url', 'Full address of the site, for example https://tickets.example.com. Used for sitemap, canonical links and payment return links. Leave blank to use the address of the current request.'],
                ['site.locale', 'Locale', 'text', 'For example en_US or en_IN.'],
                ['site.orders_enabled', 'Enable order pages', 'bool', 'Admin-only order list, voucher and cancel pages.'],
            ],
            'FunEx Concourse API' => [
                ['api.base_url', 'API base URL', 'url', 'Staging: https://api-staging.funexconcourse.com/v1'],
                ['api.key', 'API key', 'secret', 'Sent as a Bearer token. Leave blank to keep the saved key.'],
                ['api.timeout', 'Timeout (seconds)', 'int', 'Per API call.', 5, 300],
            ],
            'Catalog storage' => [
                ['store.driver', 'Storage driver', 'select', 'SleekDB keeps JSON files and needs no database. MySQL is faster and is included in most shared hosting plans.', ['sleek' => 'SleekDB (JSON files)', 'mysql' => 'MySQL']],
                ['store.mysql.host', 'MySQL host', 'text', 'Used only when the driver is MySQL.'],
                ['store.mysql.port', 'MySQL port', 'int', '', 1, 65535],
                ['store.mysql.name', 'MySQL database', 'text', ''],
                ['store.mysql.user', 'MySQL user', 'text', ''],
                ['store.mysql.password', 'MySQL password', 'secret', 'Leave blank to keep the saved password.'],
                ['sync.page_size', 'Sync page size', 'int', 'Attractions fetched per API call.', 10, 100],
                ['sync.pages_per_run', 'Sync pages per run', 'int', 'Lower this if your host stops long scripts. The sync resumes where it stopped.', 1, 100],
            ],
            'Payments (Cashfree)' => [
                ['payments.enabled', 'Take payment before booking', 'bool', 'When off, the booking form sends the order straight to the supplier with no payment.'],
                ['payments.cashfree.mode', 'Mode', 'select', 'Use sandbox with test keys first.', ['sandbox' => 'Sandbox (test)', 'production' => 'Production (live)']],
                ['payments.cashfree.app_id', 'App ID (client id)', 'text', 'From the Cashfree dashboard, Developers, API Keys.'],
                ['payments.cashfree.secret_key', 'Secret key', 'secret', 'Also used to verify webhooks. Leave blank to keep the saved key.'],
                ['payments.cashfree.api_version', 'API version', 'text', 'Sent as x-api-version, for example 2023-08-01.'],
                ['payments.cashfree.currency', 'Charge currency', 'text', 'Normally INR.'],
                ['payments.fx_rate', 'Exchange rate', 'decimal', 'Used only when ticket prices are in another currency. Enter how many units of the charge currency equal 1 unit of the ticket currency, for example 1 USD = 83.50 INR. Leave blank if both currencies match; checkout is refused if they differ and this is blank.'],
            ],
            'Hosting' => [
                ['cron.key', 'Web cron key', 'secret', 'For hosts without cron. Call https://your-site/cron/sync?key=THIS_KEY every 15 minutes from a free cron service to keep the catalog fresh. Leave blank to keep the saved key; the URL stays disabled until a key is saved.'],
            ],
            'Legal pages' => [
                ['legal.company', 'Business name', 'text', 'The legal name of the business that sells the tickets.'],
                ['legal.email', 'Support email', 'text', 'Support email address.'],
                ['legal.terms', 'Terms and conditions', 'textarea', 'Plain text. Start a line with ## for a heading and leave a blank line between paragraphs. Blank hides the page, its footer link and its checkout agreement.'],
                ['legal.privacy', 'Privacy policy', 'textarea', 'Same format as above.'],
                ['legal.refunds', 'Cancellation and refund policy', 'textarea', 'Same format as above. Supplier cancellation terms from the API are shown on each product page.'],
            ],
            'Logging and debug' => [
                ['log.level', 'Log level', 'select', 'Use info in production. Debug logs every cache hit.', ['debug' => 'debug', 'info' => 'info', 'notice' => 'notice', 'warning' => 'warning', 'error' => 'error']],
                ['log.retention_days', 'Keep logs (days)', 'int', '', 1, 365],
                ['debug', 'Show detailed errors', 'bool', 'Turn off on a live site.'],
            ],
        ];
    }

    public static function overrides(): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }
        $data = json_decode((string) file_get_contents(self::FILE), true);
        if (!is_array($data)) {
            throw new RuntimeException('storage/settings.json is not valid JSON. Fix or delete the file.');
        }
        return $data;
    }

    public static function applyTo(array $config): array
    {
        foreach (self::overrides() as $path => $value) {
            if (str_starts_with((string) $path, 'admin.')) {
                continue;
            }
            $node = &$config;
            foreach (explode('.', (string) $path) as $part) {
                if (!isset($node[$part]) || !is_array($node[$part])) {
                    $node[$part] = $node[$part] ?? [];
                    if (!is_array($node[$part])) {
                        $node[$part] = [];
                    }
                }
                $node = &$node[$part];
            }
            $node = $value;
            unset($node);
        }
        return $config;
    }

    public static function set(array $values): void
    {
        $current = self::overrides();
        foreach ($values as $path => $value) {
            if ($value === null) {
                unset($current[$path]);
            } else {
                $current[$path] = $value;
            }
        }
        self::write($current);
    }

    public static function save(array $input): array
    {
        $errors = [];
        $changes = [];
        foreach (self::schema() as $fields) {
            foreach ($fields as $f) {
                [$path, $label, $type] = $f;
                $raw = $input[$path] ?? null;
                if ($type === 'bool') {
                    $changes[$path] = $raw === '1';
                    continue;
                }
                $value = is_string($raw) ? trim($raw) : '';
                if ($type === 'secret') {
                    if ($value !== '') {
                        $changes[$path] = $value;
                    }
                    continue;
                }
                if ($type === 'int') {
                    if (!preg_match('/^\d+$/', $value) || (int) $value < $f[4] || (int) $value > $f[5]) {
                        $errors[$path] = $label . ' must be a whole number between ' . $f[4] . ' and ' . $f[5] . '.';
                        continue;
                    }
                    $changes[$path] = (int) $value;
                } elseif ($type === 'decimal') {
                    if ($value === '') {
                        $changes[$path] = '';
                    } elseif (!is_numeric($value) || (float) $value <= 0) {
                        $errors[$path] = $label . ' must be a positive number or blank.';
                    } else {
                        $changes[$path] = $value;
                    }
                } elseif ($type === 'select') {
                    if (!array_key_exists($value, $f[4])) {
                        $errors[$path] = $label . ' has an unknown value.';
                        continue;
                    }
                    $changes[$path] = $value;
                } elseif ($type === 'url') {
                    if ($value !== '' && !preg_match('#^https?://[^\s]+$#i', $value)) {
                        $errors[$path] = $label . ' must start with http:// or https://.';
                        continue;
                    }
                    $changes[$path] = rtrim($value, '/');
                } else {
                    $changes[$path] = $value;
                }
            }
        }
        if (($changes['payments.enabled'] ?? false) === true) {
            $needs = ['payments.cashfree.app_id' => 'App ID', 'payments.cashfree.currency' => 'Charge currency', 'payments.cashfree.api_version' => 'API version'];
            foreach ($needs as $path => $label) {
                if (($changes[$path] ?? '') === '') {
                    $errors[$path] = $label . ' is required when payments are on.';
                }
            }
            $hasSecret = ($changes['payments.cashfree.secret_key'] ?? '') !== '' || self::hasSaved('payments.cashfree.secret_key');
            if (!$hasSecret) {
                $errors['payments.cashfree.secret_key'] = 'Secret key is required when payments are on.';
            }
        }
        if ($errors === []) {
            self::set($changes);
        }
        return $errors;
    }

    public static function hasSaved(string $path): bool
    {
        return (self::overrides()[$path] ?? '') !== '';
    }

    public static function value(string $path): mixed
    {
        return Config::get($path);
    }

    private static function write(array $data): void
    {
        $dir = dirname(self::FILE);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create ' . $dir);
        }
        $tmp = self::FILE . '.tmp';
        if (file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write settings file ' . $tmp . '. Check folder permissions.');
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, self::FILE)) {
            throw new RuntimeException('Cannot replace settings file ' . self::FILE);
        }
    }
}
