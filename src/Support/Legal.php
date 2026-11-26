<?php
declare(strict_types=1);

namespace App\Support;

final class Legal
{
    public const PAGES = [
        'terms' => 'Terms and conditions',
        'privacy' => 'Privacy policy',
        'refunds' => 'Cancellation and refund policy',
    ];

    public static function title(string $slug): string
    {
        return self::PAGES[$slug];
    }

    public static function text(string $slug): string
    {
        return trim(Config::string('legal.' . $slug));
    }

    public static function available(): array
    {
        return array_filter(self::PAGES, static fn(string $title, string $slug) => self::text($slug) !== '', ARRAY_FILTER_USE_BOTH);
    }

    public static function html(string $text): string
    {
        $out = '';
        foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if (str_starts_with($block, '## ')) {
                $lines = preg_split('/\R/', $block, 2) ?: [$block];
                $out .= '<h2>' . e(trim(substr($lines[0], 3))) . '</h2>';
                $block = trim($lines[1] ?? '');
                if ($block === '') {
                    continue;
                }
            }
            $out .= '<p>' . nl2br(e($block), false) . '</p>';
        }
        return $out;
    }
}
