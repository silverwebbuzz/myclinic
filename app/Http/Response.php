<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        private string $body,
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {}

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status);
    }

    public static function empty(int $status = 204): self
    {
        return new self('', $status);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function download(string $filePath, string $downloadName): self
    {
        if (!is_file($filePath)) {
            return self::html('File not found', 404);
        }

        return new self(
            (string) file_get_contents($filePath),
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . addslashes($downloadName) . '"',
                'Content-Length' => (string) filesize($filePath),
            ],
        );
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
