<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Dependency-free SMTP sender for the PHP app (reads app/.env via $_ENV).
 * Mirrors partials/mailer.php used by the marketing site.
 */
final class SmtpMailService
{
    public static function isConfigured(): bool
    {
        $host = trim((string) ($_ENV['SMTP_HOST'] ?? ''));
        $user = trim((string) ($_ENV['SMTP_USERNAME'] ?? ''));
        $pass = (string) ($_ENV['SMTP_PASSWORD'] ?? '');

        return $host !== '' && $user !== '' && $pass !== '';
    }

    /** @return array<string, mixed> */
    public static function status(): array
    {
        $mailgun = trim((string) ($_ENV['MAILGUN_API_KEY'] ?? '')) !== ''
            && trim((string) ($_ENV['MAILGUN_DOMAIN'] ?? '')) !== '';
        $smtp = self::isConfigured();

        $active = $mailgun ? 'mailgun' : ($smtp ? 'smtp' : 'log');

        return [
            'active' => $active,
            'mailgun_set' => $mailgun,
            'smtp_set' => $smtp,
            'smtp_host' => trim((string) ($_ENV['SMTP_HOST'] ?? '')),
            'smtp_port' => (int) ($_ENV['SMTP_PORT'] ?? 465),
            'smtp_secure' => strtolower((string) ($_ENV['SMTP_SECURE'] ?? 'ssl')),
            'smtp_username' => trim((string) ($_ENV['SMTP_USERNAME'] ?? '')),
            'smtp_from_email' => trim((string) ($_ENV['SMTP_FROM_EMAIL'] ?? '')),
            'smtp_from_name' => trim((string) ($_ENV['SMTP_FROM_NAME'] ?? '')),
            'archive_bcc' => trim((string) ($_ENV['MAIL_ARCHIVE_BCC'] ?? '')),
            'log_path' => dirname(__DIR__, 2) . '/storage/logs/mail.log',
        ];
    }

    /**
     * @return list<string>
     */
    public static function recentLogLines(int $limit = 25): array
    {
        $path = dirname(__DIR__, 2) . '/storage/logs/mail.log';
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        if ($lines === []) {
            return [];
        }

        return array_slice($lines, -$limit);
    }

    /**
     * @return array{ok: bool, error: ?string, steps: list<string>}
     */
    public static function send(
        string $toEmail,
        string $subject,
        string $body,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?string $replyTo = null,
        bool $html = false,
    ): array {
        $steps = [];

        if (!self::isConfigured()) {
            return [
                'ok' => false,
                'error' => 'SMTP not configured (set SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD in app/.env)',
                'steps' => $steps,
            ];
        }

        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email', 'steps' => $steps];
        }

        $host = trim((string) $_ENV['SMTP_HOST']);
        $port = (int) ($_ENV['SMTP_PORT'] ?? 465);
        $secure = strtolower((string) ($_ENV['SMTP_SECURE'] ?? 'ssl'));
        $user = trim((string) $_ENV['SMTP_USERNAME']);
        $pass = (string) $_ENV['SMTP_PASSWORD'];
        $fromEmail = trim($fromEmail ?? (string) ($_ENV['SMTP_FROM_EMAIL'] ?? $user));
        $fromName = trim($fromName ?? (string) ($_ENV['SMTP_FROM_NAME'] ?? 'eClinicPro'));

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $steps[] = "Connect {$remote} (secure={$secure})";

        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            return [
                'ok' => false,
                'error' => "Connect failed: {$errstr} ({$errno})",
                'steps' => $steps,
            ];
        }
        stream_set_timeout($fp, 20);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }

            return $data;
        };
        $cmd = static function (string $command, bool $secret = false) use ($fp, $read, &$steps): string {
            $steps[] = '→ ' . ($secret ? '[redacted]' : $command);
            fwrite($fp, $command . "\r\n");
            $reply = $read();
            $steps[] = '← ' . trim(str_replace("\r\n", ' | ', $reply));

            return $reply;
        };
        $ok = static fn (string $reply, string $code): bool => str_starts_with($reply, $code);

        $greeting = $read();
        $steps[] = '← ' . trim($greeting);
        if (!$ok($greeting, '220')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'Bad SMTP greeting', 'steps' => $steps];
        }

        $ehloHost = $_ENV['APP_BASE_DOMAIN'] ?? 'eclinicpro.com';
        if (!$ok($cmd('EHLO ' . $ehloHost), '250')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'EHLO rejected', 'steps' => $steps];
        }

        if ($secure === 'tls') {
            if (!$ok($cmd('STARTTLS'), '220')) {
                fclose($fp);

                return ['ok' => false, 'error' => 'STARTTLS rejected', 'steps' => $steps];
            }
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);

                return ['ok' => false, 'error' => 'TLS handshake failed', 'steps' => $steps];
            }
            if (!$ok($cmd('EHLO ' . $ehloHost), '250')) {
                fclose($fp);

                return ['ok' => false, 'error' => 'EHLO after STARTTLS rejected', 'steps' => $steps];
            }
        }

        if (!$ok($cmd('AUTH LOGIN'), '334')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'AUTH LOGIN not accepted', 'steps' => $steps];
        }
        if (!$ok($cmd(base64_encode($user), true), '334')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'SMTP username rejected', 'steps' => $steps];
        }
        if (!$ok($cmd(base64_encode($pass), true), '235')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'SMTP authentication failed (check SMTP_PASSWORD)', 'steps' => $steps];
        }

        if (!$ok($cmd('MAIL FROM:<' . $fromEmail . '>'), '250')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'MAIL FROM rejected', 'steps' => $steps];
        }
        if (!$ok($cmd('RCPT TO:<' . $toEmail . '>'), '250')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'RCPT TO rejected for recipient', 'steps' => $steps];
        }

        $archiveBcc = trim((string) ($_ENV['MAIL_ARCHIVE_BCC'] ?? ''));
        if ($archiveBcc !== ''
            && filter_var($archiveBcc, FILTER_VALIDATE_EMAIL)
            && strcasecmp($archiveBcc, $toEmail) !== 0) {
            if (!$ok($cmd('RCPT TO:<' . $archiveBcc . '>'), '250')) {
                fclose($fp);

                return ['ok' => false, 'error' => 'Archive BCC RCPT TO rejected', 'steps' => $steps];
            }
            $steps[] = "BCC archive: {$archiveBcc}";
        }

        if (!$ok($cmd('DATA'), '354')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'DATA rejected', 'steps' => $steps];
        }

        $encode = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $fromHeader = $encode($fromName) . ' <' . $fromEmail . '>';
        $contentType = $html ? 'text/html' : 'text/plain';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . $toEmail,
            'Subject: ' . $encode($subject),
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType . '; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;

        fwrite($fp, $message . "\r\n.\r\n");
        $final = $read();
        $steps[] = '← ' . trim($final);
        if (!$ok($final, '250')) {
            fclose($fp);

            return ['ok' => false, 'error' => 'Message not accepted by server', 'steps' => $steps];
        }

        $cmd('QUIT');
        fclose($fp);

        $steps[] = 'Done — message accepted';

        return ['ok' => true, 'error' => null, 'steps' => $steps];
    }
}
