<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class MailService
{
    /** @param array<string, mixed> $payload */
    public static function send(string $toEmail, string $template, array $payload, ?int $clinicId = null): void
    {
        if (!Database::ping()) {
            self::logToFile($toEmail, $template, $payload);

            return;
        }

        $subject = match ($template) {
            'password_reset' => 'Reset your ManageClinic password',
            'welcome' => 'Welcome to ManageClinic',
            'staff_invite' => 'You are invited to join ' . ($payload['clinic_name'] ?? 'a clinic'),
            'churn_outreach' => 'We are here to help — ' . ($payload['clinic_name'] ?? 'your clinic'),
            default => 'ManageClinic notification',
        };

        $body = self::renderTemplate($template, $payload);

        if ($clinicId === null || $clinicId < 1) {
            self::logToFile($toEmail, $template, $payload);

            return;
        }

        QueryBuilder::table('notifications')->insert([
            'clinic_id' => $clinicId,
            'channel' => 'email',
            'template' => $template,
            'to_email' => $toEmail,
            'payload' => json_encode(array_merge($payload, ['subject' => $subject, 'body' => $body])),
            'status' => 'queued',
            'scheduled_at' => date('Y-m-d H:i:s'),
        ]);

        if (!empty($_ENV['MAILGUN_API_KEY']) && !empty($_ENV['MAILGUN_DOMAIN'])) {
            self::sendViaMailgun($toEmail, $subject, $body);
        } else {
            self::logToFile($toEmail, $template, $payload + ['subject' => $subject, 'body' => $body]);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function renderTemplate(string $template, array $payload): string
    {
        return match ($template) {
            'password_reset' => "Hello,\n\nReset your password using this link (valid 1 hour):\n"
                . ($payload['reset_url'] ?? '') . "\n\nIf you did not request this, ignore this email.",
            'welcome' => 'Welcome to ManageClinic, ' . ($payload['clinic_name'] ?? '') . '!',
            'staff_invite' => "Hello {$payload['name']},\n\n"
                . ($payload['clinic_name'] ?? 'A clinic') . " invited you as {$payload['role']}.\n\n"
                . "Accept invitation:\n" . ($payload['accept_url'] ?? '') . "\n\nExpires in 7 days.",
            'telemedicine_link' => "Hello {$payload['patient_name']},\n\nYour online consultation with "
                . ($payload['clinic_name'] ?? 'the clinic') . " is scheduled for {$payload['scheduled_at']}.\n\n"
                . "Join Google Meet: " . ($payload['meet_link'] ?? '') . "\n",
            'churn_outreach' => "Hello,\n\nWe noticed: " . ($payload['reason'] ?? 'lower activity on your account') . ".\n\n"
                . "Log in to keep your clinic running smoothly:\n" . ($payload['support_url'] ?? '') . "\n\n"
                . "Reply to this email if you need help from our team.",
            default => json_encode($payload),
        };
    }

    private static function sendViaMailgun(string $to, string $subject, string $text): void
    {
        $domain = $_ENV['MAILGUN_DOMAIN'];
        $ch = curl_init("https://api.mailgun.net/v3/{$domain}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => 'api:' . $_ENV['MAILGUN_API_KEY'],
            CURLOPT_POSTFIELDS => [
                'from' => $_ENV['MAILGUN_FROM'] ?? "noreply@{$domain}",
                'to' => $to,
                'subject' => $subject,
                'text' => $text,
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /** @param array<string, mixed> $payload */
    private static function logToFile(string $to, string $template, array $payload): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $line = date('c') . " | {$to} | {$template} | " . json_encode($payload) . PHP_EOL;
        file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
    }
}
