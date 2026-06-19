<?php
// =====================================================================
// mailer.php — dependency-free SMTP mailer for the marketing site.
//
// The marketing root has no Composer autoloader, so this speaks SMTP over
// a raw socket (no PHPMailer dependency). It supports:
//   - implicit TLS  (SMTP_SECURE=ssl,  typically port 465)
//   - STARTTLS      (SMTP_SECURE=tls,  typically port 587)
//   - AUTH LOGIN with the configured username/password
//
// Credentials are read from app/.env (the same file db.php uses) so nothing
// secret lives in committed code. Sending fails *silently* (logs + returns
// false) so a mail outage never breaks a page render.
// =====================================================================

declare(strict_types=1);

/**
 * Read SMTP settings from app/.env. Cached per request.
 *
 * @return array<string,string>
 */
function ecp_smtp_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [];
    $envPath = __DIR__ . '/../app/.env';
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $cfg[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }

    return $cfg;
}

/**
 * Send an HTML email via SMTP.
 *
 * @param string      $toEmail  recipient address
 * @param string      $subject  subject line
 * @param string      $htmlBody full HTML body (use ecp_email_template())
 * @param string|null $toName   optional recipient display name
 * @param string|null $replyTo  optional Reply-To address
 *
 * @return bool true on a clean 250 from the server after DATA
 */
function ecp_send_mail(string $toEmail, string $subject, string $htmlBody, ?string $toName = null, ?string $replyTo = null): bool
{
    $cfg = ecp_smtp_config();

    $host = $cfg['SMTP_HOST'] ?? '';
    $port = (int) ($cfg['SMTP_PORT'] ?? 465);
    $secure = strtolower($cfg['SMTP_SECURE'] ?? 'ssl');     // ssl | tls
    $peerName = trim($cfg['SMTP_PEER_NAME'] ?? '') ?: $host;
    $verify = !in_array(strtolower($cfg['SMTP_VERIFY_PEER'] ?? '1'), ['0', 'false', 'no'], true);
    $user = $cfg['SMTP_USERNAME'] ?? '';
    $pass = $cfg['SMTP_PASSWORD'] ?? '';
    $fromEmail = $cfg['SMTP_FROM_EMAIL'] ?? ($user ?: 'wecare@eclinicpro.com');
    $fromName = $cfg['SMTP_FROM_NAME'] ?? 'eClinicPro Care Team';

    if ($host === '' || $user === '' || $pass === '') {
        error_log('[mailer] SMTP not configured (missing host/username/password in app/.env)');
        return false;
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('[mailer] invalid recipient: ' . $toEmail);
        return false;
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
            'peer_name' => $peerName,
            'SNI_enabled' => true,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        error_log("[mailer] connect failed to {$remote}: {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($fp, 20);

    // --- tiny SMTP conversation helpers (closures keep state local) ---
    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Last line of a reply has a space at position 4 (e.g. "250 OK").
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $command) use ($fp, $read): string {
        fwrite($fp, $command . "\r\n");
        return $read();
    };
    $ok = static fn (string $reply, string $code): bool => str_starts_with($reply, $code);

    $fail = static function (string $why) use ($fp): bool {
        error_log('[mailer] ' . $why);
        @fclose($fp);
        return false;
    };

    $greeting = $read();
    if (!$ok($greeting, '220')) {
        return $fail('bad greeting: ' . trim($greeting));
    }

    $ehloHost = $cfg['APP_BASE_DOMAIN'] ?? 'eclinicpro.com';
    if (!$ok($cmd('EHLO ' . $ehloHost), '250')) {
        return $fail('EHLO rejected');
    }

    // STARTTLS upgrade for port 587.
    if ($secure === 'tls') {
        if (!$ok($cmd('STARTTLS'), '220')) {
            return $fail('STARTTLS rejected');
        }
        foreach ([
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
            'peer_name' => $peerName,
            'SNI_enabled' => true,
        ] as $k => $v) {
            stream_context_set_option($fp, 'ssl', $k, $v);
        }
        $tlsMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $tlsMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!stream_socket_enable_crypto($fp, true, $tlsMethod)) {
            return $fail('TLS handshake failed (try SMTP_PEER_NAME=hostname from 220 banner, or port 465 + ssl)');
        }
        if (!$ok($cmd('EHLO ' . $ehloHost), '250')) {
            return $fail('EHLO after STARTTLS rejected');
        }
    }

    // AUTH LOGIN
    if (!$ok($cmd('AUTH LOGIN'), '334')) {
        return $fail('AUTH LOGIN not accepted');
    }
    if (!$ok($cmd(base64_encode($user)), '334')) {
        return $fail('username rejected');
    }
    if (!$ok($cmd(base64_encode($pass)), '235')) {
        return $fail('authentication failed (check SMTP_PASSWORD)');
    }

    // Envelope
    if (!$ok($cmd('MAIL FROM:<' . $fromEmail . '>'), '250')) {
        return $fail('MAIL FROM rejected');
    }
    if (!$ok($cmd('RCPT TO:<' . $toEmail . '>'), '250')) {
        return $fail('RCPT TO rejected');
    }
    $archiveBcc = trim($cfg['MAIL_ARCHIVE_BCC'] ?? 'eclinicpro.com@gmail.com');
    if ($archiveBcc !== ''
        && filter_var($archiveBcc, FILTER_VALIDATE_EMAIL)
        && strcasecmp($archiveBcc, $toEmail) !== 0
        && !$ok($cmd('RCPT TO:<' . $archiveBcc . '>'), '250')) {
        return $fail('archive BCC RCPT TO rejected');
    }
    if (!$ok($cmd('DATA'), '354')) {
        return $fail('DATA rejected');
    }

    // Headers + body. Encode names/subject as UTF-8 (RFC 2047) to be safe.
    $encode = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $fromHeader = $encode($fromName) . ' <' . $fromEmail . '>';
    $toHeader = $toName !== null ? $encode($toName) . ' <' . $toEmail . '>' : $toEmail;

    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromHeader,
        'To: ' . $toHeader,
        'Subject: ' . $encode($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    // Base64 body in 76-char lines (RFC compliant). Dot-stuffing isn't needed
    // because base64 never produces a lone leading dot.
    $body = chunk_split(base64_encode($htmlBody), 76, "\r\n");
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

    fwrite($fp, $message . "\r\n.\r\n");
    $final = $read();
    if (!$ok($final, '250')) {
        return $fail('message not accepted: ' . trim($final));
    }

    $cmd('QUIT');
    @fclose($fp);

    return true;
}

/**
 * Wrap content in the branded eClinicPro HTML email shell.
 * Inline styles only — email clients ignore <style>/external CSS.
 *
 * @param string $heading  the H1 shown at the top of the card
 * @param string $bodyHtml inner HTML (paragraphs, tables, etc.)
 */
function ecp_email_template(string $heading, string $bodyHtml): string
{
    $year = date('Y');
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeHeading}</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1c1c1e;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.05);">
          <!-- header -->
          <tr>
            <td style="background:#0F9B6E; padding:24px 32px;">
              <span style="color:#ffffff; font-size:20px; font-weight:600; letter-spacing:-0.3px;">eClinicPro</span>
            </td>
          </tr>
          <!-- body -->
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 16px; font-size:22px; font-weight:600; color:#0a0a0a; letter-spacing:-0.4px;">{$safeHeading}</h1>
              {$bodyHtml}
            </td>
          </tr>
          <!-- footer -->
          <tr>
            <td style="padding:24px 32px; border-top:1px solid rgba(0,0,0,0.06); color:#6e6e73; font-size:12px; line-height:1.6;">
              eClinicPro — a brand of Silver Webbuzz Pvt Ltd<br>
              Need help? Just reply to this email or write to
              <a href="mailto:wecare@eclinicpro.com" style="color:#0F9B6E; text-decoration:none;">wecare@eclinicpro.com</a>.<br>
              <span style="color:#9a9aa0;">&copy; {$year} eClinicPro. All rights reserved.</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
