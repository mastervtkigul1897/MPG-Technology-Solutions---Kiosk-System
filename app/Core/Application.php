<?php

declare(strict_types=1);

namespace App\Core;

final class Application
{
    public function run(): void
    {
        SecurityHeaders::apply();

        $req = App::request();

        $general = App::config('security')['rate_limit_general'] ?? [120, 60];
        if (! RateLimiter::hit('ip:'.$req->ip(), $general[0], $general[1])) {
            (new Response('Too Many Requests', 429))->send();
        }

        if (in_array($req->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (! Csrf::validate($req->input('_token'))) {
                (new Response('Page expired', 419))->send();
            }
        }

        $loginRl = App::config('security')['rate_limit_login'] ?? [10, 60];
        if ($req->path === '/login' && $req->method === 'POST') {
            if (! RateLimiter::hit('login:'.$req->ip(), $loginRl[0], $loginRl[1])) {
                (new Response('Too Many Requests', 429))->send();
            }
        }

        $coRl = App::config('security')['rate_limit_checkout'] ?? [20, 60];
        if ($req->path === '/tenant/pos/checkout' && $req->method === 'POST') {
            if (! RateLimiter::hit('checkout:'.$req->ip(), $coRl[0], $coRl[1])) {
                (new Response('Too Many Requests', 429))->send();
            }
        }

        $router = new Router();
        $resp = $router->dispatch($req);

        $resp->send();
    }
}
