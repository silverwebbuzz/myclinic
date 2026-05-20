<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Request;
use App\Http\Response;
use Dotenv\Dotenv;

final class Application
{
    private static ?self $instance = null;

    private Router $router;

    private function __construct(private readonly string $basePath)
    {
        $this->loadEnvironment();
        $this->router = new Router(require $this->basePath . '/routes/web.php');
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }

        return self::$instance;
    }

    public static function basePath(): string
    {
        return self::$instance->basePath;
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->router->dispatch($request);
        $response->send();
    }

    private function loadEnvironment(): void
    {
        if (is_file($this->basePath . '/.env')) {
            Dotenv::createImmutable($this->basePath)->safeLoad();
        }
    }
}
