<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {}

    public function send(): void
    {
        if (isset($this->headers['Location'])) {
            http_response_code($this->status);
            header('Location: '.$this->headers['Location'], true, $this->status);
            exit;
        }
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k.': '.$v);
        }
        echo $this->body;
    }
}
