<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use RuntimeException;

final class Mailer
{
    public static function send(string $to, string $subject, string $html, ?string $text = null): bool
    {
        $cfg = App::config('mail', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $mailer = strtolower(trim((string) ($cfg['mailer'] ?? 'log')));
        if ($mailer === '' || $mailer === 'log') {
            error_log('[mail:log] To: '.$to.' Subject: '.$subject.' Body: '.($text ?? strip_tags($html)));
            return true;
        }

        if ($mailer === 'mail') {
            return self::sendUsingPhpMail($cfg, $to, $subject, $html, $text);
        }

        if ($mailer === 'smtp') {
            self::sendUsingSmtp($cfg, $to, $subject, $html, $text);
            return true;
        }

        throw new RuntimeException('Unsupported mailer: '.$mailer);
    }

    /** @param array<string,mixed> $cfg */
    private static function sendUsingPhpMail(array $cfg, string $to, string $subject, string $html, ?string $text): bool
    {
        $fromAddress = self::cleanAddress((string) ($cfg['from_address'] ?? ''));
        $fromName = self::cleanHeader((string) ($cfg['from_name'] ?? ''));
        $replyToAddress = self::cleanAddress((string) ($cfg['reply_to_address'] ?? ''));
        $replyToName = self::cleanHeader((string) ($cfg['reply_to_name'] ?? ''));
        $returnPath = self::resolveEnvelopeFrom($cfg, $fromAddress, (string) ($cfg['username'] ?? ''));
        $messageId = self::messageId($cfg, $fromAddress);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: '.self::formatAddress($fromAddress, $fromName),
            'Reply-To: '.self::formatAddress($replyToAddress !== '' ? $replyToAddress : $fromAddress, $replyToAddress !== '' ? $replyToName : $fromName),
            'Message-ID: '.$messageId,
            'Auto-Submitted: auto-generated',
            'X-Mailer: MPG-Laundry-Mailer/1.1',
        ];

        return mail(
            $to,
            self::encodeHeader($subject),
            self::quotedPrintableBody($html),
            implode("\r\n", $headers)."\r\nContent-Transfer-Encoding: quoted-printable",
            '-f'.$returnPath
        );
    }

    /** @param array<string,mixed> $cfg */
    private static function sendUsingSmtp(array $cfg, string $to, string $subject, string $html, ?string $text): void
    {
        $host = trim((string) ($cfg['host'] ?? ''));
        $port = (int) ($cfg['port'] ?? 587);
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $encryption = strtolower(trim((string) ($cfg['encryption'] ?? '')));
        $fromAddress = self::cleanAddress((string) ($cfg['from_address'] ?? $username));
        $fromName = self::cleanHeader((string) ($cfg['from_name'] ?? ''));
        $envelopeFrom = self::resolveEnvelopeFrom($cfg, $fromAddress, $username);

        if ($host === '' || $port < 1 || $fromAddress === '') {
            throw new RuntimeException('SMTP host, port, and from address are required.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '').$host.':'.$port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (! is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: '.$errstr);
        }

        stream_set_timeout($socket, 20);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO '.self::smtpHostname($cfg, $fromAddress), [250]);
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (! stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }
                self::command($socket, 'EHLO '.self::smtpHostname($cfg, $fromAddress), [250]);
            }
            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                self::command($socket, base64_encode($password), [235]);
            }

            self::command($socket, 'MAIL FROM:<'.$envelopeFrom.'>', [250]);
            self::command($socket, 'RCPT TO:<'.self::cleanAddress($to).'>', [250, 251]);
            self::command($socket, 'DATA', [354]);
            fwrite($socket, self::message($cfg, $to, $subject, $html, $text)."\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param array<string,mixed> $cfg */
    private static function message(array $cfg, string $to, string $subject, string $html, ?string $text): string
    {
        $fromAddress = self::cleanAddress((string) ($cfg['from_address'] ?? ''));
        $fromName = self::cleanHeader((string) ($cfg['from_name'] ?? ''));
        $replyToAddress = self::cleanAddress((string) ($cfg['reply_to_address'] ?? ''));
        $replyToName = self::cleanHeader((string) ($cfg['reply_to_name'] ?? ''));
        $returnPath = self::resolveEnvelopeFrom($cfg, $fromAddress, (string) ($cfg['username'] ?? ''));
        $messageId = self::messageId($cfg, $fromAddress);
        $boundary = 'mpg_'.bin2hex(random_bytes(12));
        $plain = $text;
        if ($plain === null) {
            $plain = trim(preg_replace('/[ \t]+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))) ?? '');
        }

        $headers = [
            'Date: '.date(DATE_RFC2822),
            'From: '.self::formatAddress($fromAddress, $fromName),
            'To: <'.self::cleanAddress($to).'>',
            'Subject: '.self::encodeHeader($subject),
            'Message-ID: '.$messageId,
            'Reply-To: '.self::formatAddress($replyToAddress !== '' ? $replyToAddress : $fromAddress, $replyToAddress !== '' ? $replyToName : $fromName),
            'Auto-Submitted: auto-generated',
            'X-Mailer: MPG-Laundry-Mailer/1.1',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];

        return implode("\r\n", $headers)
            ."\r\n\r\n--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            .self::dotStuff(self::quotedPrintableBody($plain))
            ."\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            .self::dotStuff(self::quotedPrintableBody($html))
            ."\r\n--{$boundary}--";
    }

    /** @param resource $socket @param list<int> $ok */
    private static function command($socket, string $command, array $ok): string
    {
        fwrite($socket, $command."\r\n");
        return self::expect($socket, $ok);
    }

    /** @param resource $socket @param list<int> $ok */
    private static function expect($socket, array $ok): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (! in_array($code, $ok, true)) {
            throw new RuntimeException('SMTP error: '.trim($response));
        }

        return $response;
    }

    /** @param array<string,mixed> $cfg */
    private static function smtpHostname(array $cfg, string $fromAddress): string
    {
        $configured = trim((string) ($cfg['ehlo_domain'] ?? ''));
        if ($configured !== '') {
            return self::cleanHeader($configured);
        }

        $fromDomain = self::domainFromAddress($fromAddress);
        if ($fromDomain !== '') {
            return $fromDomain;
        }

        $host = gethostname();
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /** @param array<string,mixed> $cfg */
    private static function resolveEnvelopeFrom(array $cfg, string $fromAddress, string $username): string
    {
        $configured = self::cleanAddress((string) ($cfg['return_path'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        if ($fromAddress !== '') {
            return $fromAddress;
        }

        return self::cleanAddress($username);
    }

    /** @param array<string,mixed> $cfg */
    private static function messageId(array $cfg, string $fromAddress): string
    {
        $configuredDomain = trim((string) ($cfg['message_id_domain'] ?? ''));
        if ($configuredDomain !== '') {
            $domain = self::cleanHeader($configuredDomain);
        } else {
            $domain = self::domainFromAddress($fromAddress);
        }
        if ($domain === '') {
            $domain = 'localhost';
        }

        return '<'.bin2hex(random_bytes(12)).'@'.$domain.'>';
    }

    private static function domainFromAddress(string $email): string
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return '';
        }

        return strtolower(trim(substr($email, $atPos + 1)));
    }

    private static function formatAddress(string $email, string $name = ''): string
    {
        return $name !== '' ? self::encodeHeader($name).' <'.$email.'>' : '<'.$email.'>';
    }

    private static function encodeHeader(string $value): string
    {
        $value = self::cleanHeader($value);
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?'.base64_encode($value).'?=' : $value;
    }

    private static function cleanHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private static function cleanAddress(string $value): string
    {
        return trim(str_replace(["\r", "\n", "<", ">"], '', $value));
    }

    private static function dotStuff(string $value): string
    {
        return preg_replace('/^\./m', '..', $value) ?? $value;
    }

    private static function quotedPrintableBody(string $value): string
    {
        return quoted_printable_encode($value);
    }
}
