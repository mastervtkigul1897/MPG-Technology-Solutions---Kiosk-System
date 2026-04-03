<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
        public readonly array $files,
    ) {}

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim($path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $post = $_POST;
        if ($method === 'POST' && isset($post['_method'])) {
            $m = strtoupper((string) $post['_method']);
            if (in_array($m, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $m;
            }
        }

        return new self($method, $path, $_GET, $post, $_SERVER, $_FILES);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $merged = array_merge($this->query, $this->post);
        if (! str_contains($key, '.')) {
            return $merged[$key] ?? $default;
        }

        return data_get($merged, $key, $default);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->query);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Query string parameters only (GET). Laravel-style: $request->query('page', 1), or query() with no args for the full array.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        if (! str_contains($key, '.')) {
            return $this->query[$key] ?? $default;
        }

        return data_get($this->query, $key, $default);
    }

    public function ajax(): bool
    {
        $xrw = $this->server['HTTP_X_REQUESTED_WITH'] ?? '';

        return strtolower((string) $xrw) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';

        return str_contains((string) $accept, 'application/json') || $this->ajax();
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) ($this->input($key, $default));
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->input($key), FILTER_VALIDATE_BOOL);
    }
}
