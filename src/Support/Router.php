<?php
declare(strict_types=1);

namespace App\Support;

use Closure;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, Closure $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '/?$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): ?Response
    {
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod === $method && preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $handler(...array_map('urldecode', $params));
            }
        }
        return null;
    }
}
