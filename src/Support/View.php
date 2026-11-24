<?php
declare(strict_types=1);

namespace App\Support;

final class View
{
    private static array $shared = [];

    public static function share(string $key, \Closure $resolver): void
    {
        self::$shared[$key] = $resolver;
    }

    public static function shared(string $key): mixed
    {
        return isset(self::$shared[$key]) ? (self::$shared[$key])() : null;
    }

    public static function render(string $template, array $data = [], bool $layout = true): string
    {
        $content = self::capture($template, $data);
        if (!$layout) {
            return $content;
        }
        return self::capture('layout', ['content' => $content, 'seo' => Seo::current()]);
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    private static function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/../../views/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
