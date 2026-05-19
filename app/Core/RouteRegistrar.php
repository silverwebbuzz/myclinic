<?php

declare(strict_types=1);

namespace App\Core;

final class RouteRegistrar
{
    /** @var list<string> */
    private array $groupMiddleware = [];

    public function __construct(private readonly Router $router) {}

    public function get(string $path, callable|array $handler, ?string $name = null): void
    {
        $this->add(['GET'], $path, $handler, $name);
    }

    public function post(string $path, callable|array $handler, ?string $name = null): void
    {
        $this->add(['POST'], $path, $handler, $name);
    }

    /** @param list<string> $middleware */
    public function group(array $options, callable $callback): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge(
            $previous,
            $options['middleware'] ?? [],
        );

        $prefix = $options['prefix'] ?? '';
        $callback(new GroupedRouteRegistrar($this, $prefix));

        $this->groupMiddleware = $previous;
    }

    /** @param list<string> $methods */
    public function add(array $methods, string $path, callable|array $handler, ?string $name = null): void
    {
        $this->router->addRoute($methods, $path, $handler, $this->groupMiddleware, $name);
    }
}

final class GroupedRouteRegistrar
{
    public function __construct(
        private readonly RouteRegistrar $parent,
        private readonly string $prefix,
    ) {}

    public function get(string $path, callable|array $handler, ?string $name = null): void
    {
        $this->parent->add(['GET'], $this->prefix . $path, $handler, $name);
    }

    public function post(string $path, callable|array $handler, ?string $name = null): void
    {
        $this->parent->add(['POST'], $this->prefix . $path, $handler, $name);
    }
}
