<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []];

    public function get(string $path, callable $handler): void    { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable $handler): void   { $this->routes['POST'][$path] = $handler; }
    public function put(string $path, callable $handler): void    { $this->routes['PUT'][$path] = $handler; }
    public function delete(string $path, callable $handler): void { $this->routes['DELETE'][$path] = $handler; }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path   = rtrim($path, '/') ?: '/';

        // Method override for PUT/DELETE via _method field
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }

        http_response_code(404);
        echo '<h1>404 - Página não encontrada</h1>';
    }
}
