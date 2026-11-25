<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class LogReader
{
    public const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'];
    private const MAX_BYTES = 50 * 1048576;
    private const LINE = '/^(\S+) \[([A-Z]+)\] \[([0-9a-f]+)\] (.*?)(?: (\{.*\}))?$/s';

    public static function files(): array
    {
        $out = [];
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . 'app-*.log') ?: [] as $path) {
            $out[] = ['name' => basename($path), 'size' => (int) filesize($path), 'modified' => (int) filemtime($path)];
        }
        usort($out, static fn(array $a, array $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    public static function path(string $name): string
    {
        if (preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $name) !== 1) {
            throw new NotFoundException('Not a log file name: ' . $name);
        }
        $path = self::dir() . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            throw new NotFoundException('Log file does not exist: ' . $name);
        }
        return $path;
    }

    public static function read(string $name, string $level, string $query, string $request, int $limit): array
    {
        $path = self::path($name);
        $size = (int) filesize($path);
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('Log file ' . $name . ' is ' . round($size / 1048576, 1) . ' MB, over the ' . (self::MAX_BYTES / 1048576) . ' MB viewer limit. Download it instead.');
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Cannot read ' . $path);
        }
        $min = array_search($level, self::LEVELS, true);
        $rows = [];
        $matched = 0;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $row = self::parse($lines[$i]);
            if (($min !== false && (array_search($row['level'], self::LEVELS, true) ?: 0) < $min)
                || ($request !== '' && $row['request'] !== $request)
                || ($query !== '' && stripos($lines[$i], $query) === false)) {
                continue;
            }
            $matched++;
            if (count($rows) < $limit) {
                $rows[] = $row;
            }
        }
        return ['rows' => $rows, 'matched' => $matched, 'total' => count($lines)];
    }

    public static function counts(string $name): array
    {
        $counts = array_fill_keys(self::LEVELS, 0);
        foreach (file(self::path($name), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = self::parse($line);
            if (isset($counts[$row['level']])) {
                $counts[$row['level']]++;
            }
        }
        return $counts;
    }

    public static function delete(string $name): void
    {
        $path = self::path($name);
        if (!unlink($path)) {
            throw new RuntimeException('Cannot delete ' . $path);
        }
        Log::notice('Log file deleted', ['file' => $name]);
    }

    private static function parse(string $line): array
    {
        if (preg_match(self::LINE, $line, $m) !== 1) {
            return ['time' => '', 'level' => 'INFO', 'request' => '', 'message' => $line, 'context' => ''];
        }
        $context = $m[5] ?? '';
        $pretty = $context !== '' ? json_decode($context, true) : null;
        return ['time' => $m[1], 'level' => $m[2], 'request' => $m[3], 'message' => $m[4],
            'context' => is_array($pretty) ? json_encode($pretty, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $context];
    }

    private static function dir(): string
    {
        return rtrim(Config::string('log.dir'), '/\\');
    }
}
