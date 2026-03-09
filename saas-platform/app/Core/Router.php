<?php

declare(strict_types=1);

namespace Saas\Core;

class Router
{
    private array $routes = [];

    public function __construct(private Container $container) {}

    public function get(string $path, array|callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function loadRoutes(string $file): void
    {
        $router = $this;
        require $file;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Strip base path if app is in subdirectory
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method && !($method === 'HEAD' && $routeMethod === 'GET')) {
                continue;
            }

            $pattern = $this->buildPattern($routePath);
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callHandler($handler, $params);
                return;
            }
        }

        http_response_code(404);
        $view = $this->container->get(View::class);
        echo $view->render('errors/404.twig', ['message' => 'Seite nicht gefunden']);
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function callHandler(array|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($params);
            return;
        }

        [$class, $method] = $handler;
        $controller = $this->container->make($class);
        $controller->$method($params);
    }
}
