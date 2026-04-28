<?php

declare(strict_types=1);

namespace App\Core;

final class Application
{
    public function run(): void
    {
        SecurityHeaders::apply();

        $req = App::request();
        $maxRequestBytes = (int) (App::config('security')['max_request_bytes'] ?? (2 * 1024 * 1024));
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($maxRequestBytes > 0 && $contentLength > $maxRequestBytes) {
            (new Response('Payload Too Large', 413))->send();
        }

        $general = App::config('security')['rate_limit_general'] ?? [120, 60];
        if (! RateLimiter::hit('ip:'.$req->ip(), $general[0], $general[1])) {
            (new Response('Too Many Requests', 429))->send();
        }

        if (
            in_array($req->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $this->isCsrfExemptPath($req->path)
        ) {
            if (! Csrf::validate($req->input('_token'))) {
                if ($req->wantsJson()) {
                    (new Response(
                        json_encode(['success' => false, 'message' => 'Page expired. Please refresh and try again.'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        419,
                        ['Content-Type' => 'application/json; charset=UTF-8']
                    ))->send();
                    return;
                }
                (new Response('Page expired', 419))->send();
                return;
            }
        }

        $loginRl = App::config('security')['rate_limit_login'] ?? [10, 60];
        if ($req->path === '/login' && $req->method === 'POST') {
            if (! RateLimiter::hit('login:'.$req->ip(), $loginRl[0], $loginRl[1])) {
                (new Response('Too Many Requests', 429))->send();
            }
        }
        if ($req->path === '/forgot-password' && $req->method === 'POST') {
            if (! RateLimiter::hit('password-reset:'.$req->ip(), 6, 60)) {
                (new Response('Too Many Requests', 429))->send();
            }
        }
        if ($req->path === '/email/verification-notification' && $req->method === 'POST') {
            if (! RateLimiter::hit('verification-resend:'.$req->ip(), 6, 60)) {
                (new Response('Too Many Requests', 429))->send();
            }
        }

        $coRl = App::config('security')['rate_limit_checkout'] ?? [20, 60];
        if ($req->path === '/tenant/pos/checkout' && $req->method === 'POST') {
            if (! RateLimiter::hit('checkout:'.$req->ip(), $coRl[0], $coRl[1])) {
                (new Response('Too Many Requests', 429))->send();
            }
        }

        TrafficTracker::track($req);

        $router = new Router();
        $resp = $router->dispatch($req);
        if ($this->shouldConvertRedirectToJson($req, $resp)) {
            $resp = $this->redirectResponseToJson($resp);
        }

        $resp->send();
    }

    private function shouldConvertRedirectToJson(Request $req, Response $resp): bool
    {
        return $req->ajax()
            && in_array($req->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && isset($resp->headers['Location']);
    }

    private function redirectResponseToJson(Response $resp): Response
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        $errors = $this->normalizeFlashMessages($flash['errors'] ?? []);
        $successMessages = array_merge(
            $this->normalizeFlashMessages($flash['success'] ?? []),
            $this->normalizeFlashMessages($flash['status'] ?? [])
        );
        $ok = $errors === [];
        $message = $ok
            ? ($successMessages[0] ?? 'Saved successfully.')
            : ($errors[0] ?? 'Could not complete action.');

        return new Response(
            json_encode([
                'success' => $ok,
                'ok' => $ok,
                'message' => $message,
                'messages' => [
                    'success' => $successMessages,
                    'errors' => $errors,
                ],
                'redirect' => (string) ($resp->headers['Location'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $ok ? 200 : 422,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * @return array<int, string>
     */
    private function normalizeFlashMessages(mixed $value): array
    {
        if ($value === null || $value === false || $value === '') {
            return [];
        }
        if (is_scalar($value)) {
            $text = trim((string) $value);

            return $text === '' ? [] : [$text];
        }
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        array_walk_recursive($value, static function (mixed $item) use (&$out): void {
            if (! is_scalar($item)) {
                return;
            }
            $text = trim((string) $item);
            if ($text !== '') {
                $out[] = $text;
            }
        });

        return $out;
    }

    private function isCsrfExemptPath(string $path): bool
    {
        // Bearer-protected API endpoints should be callable by external clients without CSRF session token.
        if (str_starts_with($path, '/api/sms/')) {
            return true;
        }

        return false;
    }
}
