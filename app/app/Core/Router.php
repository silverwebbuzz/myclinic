<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\MiddlewareInterface;

final class Router
{
    /** @var list<array{methods: string[], path: string, handler: callable|array, middleware: string[], name: ?string}> */
    private array $routes = [];

    /** @param callable(RouteRegistrar): void $routeDefinitions */
    public function __construct(private readonly \Closure $routeDefinitions)
    {
        $this->registerDefinitions();
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if (!in_array($request->method, $route['methods'], true)) {
                continue;
            }

            $params = $this->match($route['path'], $request->uri);
            if ($params === null) {
                continue;
            }

            $handler = $this->resolveHandler($route['handler']);
            $pipeline = array_merge($route['middleware'], ['core.handler']);

            return $this->runPipeline($pipeline, $request, static function () use ($handler, $params, $request) {
                return $handler($request, ...array_values($params));
            });
        }

        return Response::json(['error' => 'Not Found'], 404);
    }

    /** @param list<string> $middleware */
    private function runPipeline(array $middleware, Request $request, callable $destination): Response
    {
        $next = $destination;
        foreach (array_reverse($middleware) as $name) {
            $mw = $this->resolveMiddleware($name);
            $next = static function () use ($mw, $request, $next) {
                return $mw->handle($request, $next);
            };
        }

        return $next();
    }

    private function match(string $pattern, string $uri): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function registerDefinitions(): void
    {
        $registrar = new RouteRegistrar($this);
        ($this->routeDefinitions)($registrar);
    }

    /** @internal */
    public function addRoute(
        array $methods,
        string $path,
        callable|array $handler,
        array $middleware = [],
        ?string $name = null,
    ): void {
        $this->routes[] = compact('methods', 'path', 'handler', 'middleware', 'name');
    }

    private function resolveHandler(callable|array $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        [$class, $method] = $handler;

        return [new $class(), $method];
    }

    private function resolveMiddleware(string $name): MiddlewareInterface
    {
        if ($name === 'core.handler') {
            return new class implements MiddlewareInterface {
                public function handle(Request $request, callable $next): Response
                {
                    return $next();
                }
            };
        }

        $map = require Application::basePath() . '/config/middleware.php';
        $class = $map[$name] ?? throw new \RuntimeException("Unknown middleware: {$name}");

        return new $class();
    }
}
