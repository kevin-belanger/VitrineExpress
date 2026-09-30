<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Routeur minimal : chemins avec paramètres {nom}, un gestionnaire [Classe, méthode] par route.
 */
final class Router
{
    /** @var list<array{string, string, array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    /**
     * @return array{array, array<string, string>} le gestionnaire et les paramètres extraits
     */
    public function match(string $method, string $path): array
    {
        $allowed = false;
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $allowed = true;
            if ($routeMethod === $method || ($routeMethod === 'GET' && $method === 'HEAD')) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, array_map('rawurldecode', $params)];
            }
        }
        throw new HttpException($allowed ? 405 : 404);
    }
}
