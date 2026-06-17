<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class MailService
{
    /**
     * Per-purpose sender mailboxes. Each template routes to the inbox that
     * owns that kind of message, with a friendly display name.
     * Override any address via env (WECARE_FROM / HELP_FROM / …).
     *
     * @return array{0: string, 1: string} [email, displayName]
     */
    private static function fromFor(string $template): array
    {
        // Support & account/system flows.
        $support = [
            $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com',
            'eClinicPro Support',
        ];
        $notify = [
            $_ENV['NOREPLY_FROM'] ?? 'noreply@eclinicpro.com',
            'eClinicPro Notifications',
        ];
        $care = [
            $_ENV['WECARE_FROM'] ?? 'wecare@eclinicpro.com',
            'eClinicPro Care Team',
        ];
        $healthTips = [
            $_ENV['HEALTHTIPS_FROM'] ?? 'healthtips@eclinicpro.com',
            'eClinicPro Health Tips',
        ];

        return match ($template) {
            // Automated/system mail → noreply
            'password_reset',
            'welcome',
            'telemedicine_link',
            'appointment_reminder',
            'appointment_notification',
            'invoice_paid',
            'subscription_invoice',
            'prescription_ready' => $notify,

            // Human, relationship mail → care team
            'staff_invite',
            'churn_outreach',
            'doctor_approved' => $care,

            // Newsletters / health content → health tips
            'health_tip',
            'newsletter' => $healthTips,

            // Anything support-flavoured → help
            'support',
            'billing_question' => $support,

            // Safe default: noreply (system).
            default => $notify,
        };
    }

    /** @param array<string, mixed> $payload */
    public static function send(string $toEmail, string $template, array $payload, ?int $clinicId = null): void
    {
        // Email is optional across the app (patients/staff may have none).
        // Never attempt to send to a blank/invalid address — silently skip so
        // callers don't have to guard, and we don't log noisy failures.
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (!Database::ping()) {
            self::logToFile($toEmail, $template, $payload);

            return;
        }

        $clinicName = (string) ($payload['clinic_name'] ?? 'your clinic');
        $subject = match ($template) {
            'password_reset' => 'Reset your eClinicPro password',
            'welcome' => 'Welcome to eClinicPro',
            'staff_invite' => 'You are invited to join ' . ($payload['clinic_name'] ?? 'a clinic'),
            'churn_outreach' => 'We are here to help — ' . ($payload['clinic_name'] ?? 'your clinic'),
            'appointment_reminder' => 'Appointment reminder — ' . $clinicName,
            'appointment_cancelled' => 'Appointment cancelled — ' . $clinicName,
            'telemedicine_link' => 'Your online consultation link — ' . $clinicName,
            'invoice_paid' => 'Payment received — ' . $clinicName,
            'subscription_invoice' => 'Your eClinicPro invoice ' . (string) ($payload['invoice_no'] ?? ''),
            'doctor_approved' => 'Your clinic is now listed on eClinicPro',
            default => 'eClinicPro notification',
        };

        [$fromEmail, $fromName] = self::fromFor($template);

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
            self::sendViaMailgun($toEmail, $subject, $body, $fromEmail, $fromName);
        } else {
            self::logToFile($toEmail, $template, $payload + ['subject' => $subject, 'body' => $body, 'from' => $fromEmail]);
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
            'appointment_reminder' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "This is a reminder of your appointment at " . ($payload['clinic_name'] ?? 'the clinic')
                . " on " . ($payload['scheduled_at'] ?? '') . ".\n\n"
                . "Please arrive a few minutes early. To reschedule, contact the clinic.",
            'appointment_cancelled' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "Your appointment at " . ($payload['clinic_name'] ?? 'the clinic')
                . " on " . ($payload['scheduled_at'] ?? '') . " has been cancelled.\n\n"
                . "Please contact the clinic to book a new time.",
            'invoice_paid' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "We've received your payment at " . ($payload['clinic_name'] ?? 'the clinic')
                . ". Invoice " . ($payload['invoice_number'] ?? '') . " — total "
                . ($payload['total'] ?? '') . ".\n\nThank you.",
            'subscription_invoice' => "Hello " . ($payload['clinic_name'] ?? '') . ",\n\n"
                . "Thank you for subscribing to eClinicPro "
                . ucfirst((string) ($payload['plan_id'] ?? '')) . ".\n\n"
                . "Invoice " . ($payload['invoice_no'] ?? '') . " — "
                . ($payload['currency'] ?? 'INR') . ' ' . ($payload['amount'] ?? '')
                . " (tax invoice attached/available in your billing page).\n\n"
                . "— Team eClinicPro, SILVER WEBBUZZ PRIVATE LIMITED",
            'doctor_approved' => "Hello " . ($payload['doctor_name'] ?? 'Doctor') . ",\n\n"
                . "Good news — your listing request for "
                . ($payload['clinic_name'] ?? 'your clinic')
                . " has been approved.\n\n"
                . "You can sign in to your clinic portal with your verified phone number ("
                . ($payload['phone'] ?? '') . "):\n"
                . ($payload['login_url'] ?? '') . "\n\n"
                . "No password is needed — we'll send you a one-time code by SMS.\n\n"
                . "— Team eClinicPro",
            default => json_encode($payload),
        };
    }

    private static function sendViaMailgun(
        string $to,
        string $subject,
        string $text,
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): void {
        $domain = $_ENV['MAILGUN_DOMAIN'];
        $fromEmail ??= ($_ENV['MAILGUN_FROM'] ?? "noreply@{$domain}");
        $from = $fromName !== null && $fromName !== ''
            ? sprintf('%s <%s>', $fromName, $fromEmail)
            : $fromEmail;

        $ch = curl_init("https://api.mailgun.net/v3/{$domain}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => 'api:' . $_ENV['MAILGUN_API_KEY'],
            CURLOPT_POSTFIELDS => [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'text' => $text,
                // Replies go to the care team rather than an unwatched noreply box.
                'h:Reply-To' => $_ENV['WECARE_FROM'] ?? 'wecare@eclinicpro.com',
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
