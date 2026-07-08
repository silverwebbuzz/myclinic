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
     *
     * @return array{0: string, 1: string} [email, displayName]
     */
    private static function fromFor(string $template): array
    {
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
            'password_reset',
            'register_verify',
            'welcome',
            'telemedicine_link',
            'appointment_reminder',
            'appointment_cancelled',
            'appointment_notification',
            'invoice_paid',
            'subscription_invoice',
            'prescription_ready',
            'rx_delivery',
            'follow_up_reminder',
            'diet_plan_shared',
            'claim_received' => $notify,

            'staff_invite',
            'churn_outreach',
            'doctor_approved',
            'doctor_rejected' => $care,

            'health_tip',
            'newsletter' => $healthTips,

            'support',
            'billing_question' => $support,

            default => $notify,
        };
    }

    /**
     * Send immediately (registration, password reset, staff invite, etc.).
     * Optionally writes an audit row on the clinic's notifications table.
     *
     * @param array<string, mixed> $payload
     */
    public static function send(string $toEmail, string $template, array $payload, ?int $clinicId = null): void
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (!Database::ping()) {
            self::logToFile($toEmail, $template, $payload);

            return;
        }

        $ok = self::deliver($toEmail, $template, $payload);

        if ($clinicId !== null && $clinicId > 0) {
            $composed = self::compose($template, $payload);
            QueryBuilder::table('notifications')->insert([
                'clinic_id' => $clinicId,
                'channel' => 'email',
                'template' => $template,
                'to_email' => $toEmail,
                'payload' => json_encode(array_merge($payload, [
                    'subject' => $composed['subject'],
                    'body' => $composed['body'],
                ])),
                'status' => $ok ? 'sent' : 'failed',
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'error_log' => $ok ? null : 'smtp_delivery_failed',
            ]);
        }
    }

    /**
     * Render + deliver one email. Used by the notification worker for queued rows.
     *
     * @param array<string, mixed> $payload
     */
    public static function deliver(string $toEmail, string $template, array $payload): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $composed = self::compose($template, $payload);

        return self::dispatch(
            $toEmail,
            $composed['subject'],
            $composed['body'],
            $composed['fromEmail'],
            $composed['fromName'],
            $template,
        );
    }

    /** @param array<string, mixed> $payload @return array{subject: string, body: string, fromEmail: string, fromName: string} */
    private static function compose(string $template, array $payload): array
    {
        $clinicName = (string) ($payload['clinic_name'] ?? 'your clinic');
        $subject = match ($template) {
            'password_reset' => 'Reset your eClinicPro password',
            'register_verify' => 'Verify your email to start your eClinicPro clinic',
            'welcome' => 'Welcome to eClinicPro',
            'staff_invite' => 'You are invited to join ' . ($payload['clinic_name'] ?? 'a clinic'),
            'churn_outreach' => 'We are here to help — ' . ($payload['clinic_name'] ?? 'your clinic'),
            'appointment_reminder' => 'Appointment reminder — ' . $clinicName,
            'appointment_cancelled' => 'Appointment cancelled — ' . $clinicName,
            'appointment_notification' => 'Appointment request received — ' . $clinicName,
            'telemedicine_link' => 'Your online consultation link — ' . $clinicName,
            'invoice_paid' => 'Payment received — ' . $clinicName,
            'subscription_invoice' => 'Your eClinicPro invoice ' . (string) ($payload['invoice_no'] ?? ''),
            'doctor_approved' => 'Your clinic is now listed on eClinicPro',
            'doctor_rejected' => 'About your eClinicPro listing request',
            'claim_received' => 'New listing request: ' . (string) ($payload['clinic_name'] ?? 'a clinic')
                . (!empty($payload['source_label']) ? ' (' . $payload['source_label'] . ')' : ''),
            'rx_delivery', 'prescription_ready' => 'Your prescription from ' . $clinicName,
            'follow_up_reminder' => 'Follow-up reminder — ' . $clinicName,
            'diet_plan_shared' => 'Your diet plan from ' . $clinicName,
            default => 'eClinicPro notification',
        };

        // Admin-edited subject (from email_templates) overrides the default.
        $subjectOverride = EmailTemplateService::subject($template, $payload);
        if ($subjectOverride !== null && trim($subjectOverride) !== '') {
            $subject = $subjectOverride;
        }

        [$fromEmail, $fromName] = self::fromFor($template);

        return [
            'subject' => $subject,
            'body' => self::renderTemplate($template, $payload),
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
        ];
    }

    /** Send a test message using the same routing as production (for admin diagnostics). */
    public static function sendTest(string $toEmail, string $template = 'welcome'): array
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Enter a valid email address', 'steps' => [], 'provider' => 'none'];
        }

        $payload = match ($template) {
            'welcome' => [
                'clinic_name' => 'Test Clinic',
                'login_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/login',
            ],
            'password_reset' => [
                'reset_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/reset-password/test-token',
            ],
            'staff_invite' => [
                'name' => 'Test User',
                'clinic_name' => 'Test Clinic',
                'role' => 'receptionist',
                'accept_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/accept-invite/test',
            ],
            'appointment_reminder' => [
                'patient_name' => 'Test Patient',
                'clinic_name' => 'Test Clinic',
                'scheduled_at' => date('Y-m-d H:i'),
            ],
            'rx_delivery' => [
                'patient_name' => 'Test Patient',
                'clinic_name' => 'Test Clinic',
                'rx_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/uploads/rx/test.pdf',
            ],
            'invoice_paid' => [
                'patient_name' => 'Test Patient',
                'clinic_name' => 'Test Clinic',
                'invoice_number' => 'INV-TEST-001',
                'total' => '500.00',
            ],
            'doctor_approved' => [
                'doctor_name' => 'Dr Test Doctor',
                'clinic_name' => 'Test Clinic',
                'phone' => '+91 99999 99999',
                'login_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/doctor/login',
            ],
            default => ['clinic_name' => 'Test Clinic'],
        };

        if (!empty($_ENV['MAILGUN_API_KEY']) && !empty($_ENV['MAILGUN_DOMAIN'])) {
            $composed = self::compose($template, $payload);
            self::sendViaMailgun($toEmail, $composed['subject'], $composed['body'], $composed['fromEmail'], $composed['fromName']);

            return [
                'ok' => true,
                'error' => null,
                'steps' => ['Sent via Mailgun API'],
                'provider' => 'mailgun',
            ];
        }

        if (SmtpMailService::isConfigured()) {
            $composed = self::compose($template, $payload);
            $replyTo = $_ENV['WECARE_FROM'] ?? 'wecare@eclinicpro.com';
            $result = SmtpMailService::send(
                $toEmail,
                $composed['subject'],
                $composed['body'],
                $composed['fromEmail'],
                $composed['fromName'],
                $replyTo,
                true,
            );
            $result['provider'] = 'smtp';

            return $result;
        }

        return [
            'ok' => false,
            'error' => 'No mail provider configured. Set SMTP_* or MAILGUN_* in app/.env — otherwise emails only go to storage/logs/mail.log',
            'steps' => [],
            'provider' => 'log',
        ];
    }

    private static function dispatch(
        string $toEmail,
        string $subject,
        string $body,
        string $fromEmail,
        string $fromName,
        string $template,
    ): bool {
        if (!empty($_ENV['MAILGUN_API_KEY']) && !empty($_ENV['MAILGUN_DOMAIN'])) {
            self::sendViaMailgun($toEmail, $subject, $body, $fromEmail, $fromName);

            return true;
        }

        if (SmtpMailService::isConfigured()) {
            $replyTo = $_ENV['WECARE_FROM'] ?? 'wecare@eclinicpro.com';
            $result = SmtpMailService::send($toEmail, $subject, $body, $fromEmail, $fromName, $replyTo, true);
            if (!$result['ok']) {
                error_log('[MailService] SMTP failed (' . $template . '): ' . ($result['error'] ?? 'unknown'));
                self::logToFile($toEmail, $template . '_smtp_failed', [
                    'error' => $result['error'] ?? 'unknown',
                    'subject' => $subject,
                    'steps' => $result['steps'] ?? [],
                ]);
            }

            return (bool) ($result['ok'] ?? false);
        }

        $logPayload = ['subject' => $subject, 'body' => $body, 'from' => $fromEmail];
        $archive = self::archiveBcc($toEmail);
        if ($archive !== null) {
            $logPayload['archive_bcc'] = $archive;
        }
        self::logToFile($toEmail, $template, $logPayload);

        return false;
    }

    private static function archiveBcc(?string $primaryTo = null): ?string
    {
        $archive = trim((string) ($_ENV['MAIL_ARCHIVE_BCC'] ?? 'eclinicpro.com@gmail.com'));
        if ($archive === '' || !filter_var($archive, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if ($primaryTo !== null && strcasecmp(trim($primaryTo), $archive) === 0) {
            return null;
        }

        return $archive;
    }

    /**
     * Branded HTML email layout (matches the eClinicPro look: centered card,
     * green wordmark header, body content, optional CTA button + bullet list,
     * muted footer). Every outbound email is wrapped in this so messages look
     * finished instead of blank plain text.
     *
     * @param array<string, mixed> $content {
     *   greeting?: string, paragraphs?: list<string>, bullets?: list<string>,
     *   bullets_intro?: string, cta?: array{label: string, url: string},
     *   sign_off?: string, raw?: string
     * }
     */
    private static function htmlLayout(array $content): string
    {
        $brand = 'eClinicPro';
        $year = date('Y');
        $support = $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com';
        $sales = $_ENV['WECARE_FROM'] ?? 'wecare@eclinicpro.com';
        // Email clients can't render SVG and need an absolute URL (the message
        // is opened off-site), so use the PNG logo on the public marketing site.
        $logoUrl = $_ENV['EMAIL_LOGO_URL'] ?? 'https://eclinicpro.com/assets/img/logos/logo.png';
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // Convert URLs in free text into links and preserve line breaks.
        $linkify = static function (string $s) use ($esc): string {
            $s = $esc($s);
            $s = preg_replace(
                '~(https?://[^\s<]+)~',
                '<a href="$1" style="color:#15803d;text-decoration:underline;">$1</a>',
                $s,
            ) ?? $s;

            return nl2br($s);
        };

        $body = '';

        if (!empty($content['greeting'])) {
            $body .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#111827;">'
                . $linkify((string) $content['greeting']) . '</p>';
        }

        foreach (($content['paragraphs'] ?? []) as $p) {
            $p = (string) $p;
            if ($p === '') {
                continue;
            }
            $body .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#111827;">'
                . $linkify($p) . '</p>';
        }

        if (!empty($content['bullets_intro'])) {
            $body .= '<p style="margin:0 0 8px;font-size:15px;line-height:1.6;color:#111827;">'
                . $linkify((string) $content['bullets_intro']) . '</p>';
        }
        if (!empty($content['bullets'])) {
            $body .= '<ul style="margin:0 0 16px;padding-left:20px;font-size:15px;line-height:1.7;color:#111827;">';
            foreach ($content['bullets'] as $li) {
                $body .= '<li style="margin:0 0 4px;">' . $linkify((string) $li) . '</li>';
            }
            $body .= '</ul>';
        }

        if (!empty($content['cta']['url']) && !empty($content['cta']['label'])) {
            $url = (string) $content['cta']['url'];
            $label = $esc((string) $content['cta']['label']);
            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">'
                . '<tr><td style="border-radius:8px;background:#16a34a;">'
                . '<a href="' . $esc($url) . '" target="_blank" '
                . 'style="display:inline-block;padding:13px 30px;font-size:15px;font-weight:700;'
                . 'color:#ffffff;text-decoration:none;border-radius:8px;">' . $label . '</a>'
                . '</td></tr></table>';
        }

        if (!empty($content['sign_off'])) {
            $body .= '<p style="margin:24px 0 0;font-size:15px;line-height:1.6;color:#111827;">'
                . $linkify((string) $content['sign_off']) . '</p>';
        }

        // Fallback: raw text only (templates not yet structured).
        if ($body === '' && !empty($content['raw'])) {
            $body = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#111827;">'
                . $linkify((string) $content['raw']) . '</p>';
        }

        return '<!DOCTYPE html><html lang="en"><head>'
            . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '</head>'
            . '<body style="margin:0;padding:0;background:#f3f4f6;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" '
            . 'style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
            // Header (logo image; falls back to alt text if it can't load)
            . '<tr><td style="padding:24px;text-align:center;border-bottom:1px solid #eef0f2;">'
            . '<img src="' . $esc($logoUrl) . '" alt="' . $brand . '" height="40" '
            . 'style="height:40px;width:auto;max-width:200px;display:inline-block;border:0;outline:none;text-decoration:none;">'
            . '</td></tr>'
            // Body
            . '<tr><td style="padding:32px;">' . $body . '</td></tr>'
            // Footer
            . '<tr><td style="padding:20px 24px;border-top:1px solid #eef0f2;text-align:center;">'
            . '<p style="margin:0 0 6px;font-size:12px;color:#9ca3af;line-height:1.5;">This is an automated message from ' . $brand . '.</p>'
            . '<p style="margin:0 0 6px;font-size:12px;color:#9ca3af;line-height:1.5;">Need help? '
            . '<a href="mailto:' . $esc($support) . '" style="color:#9ca3af;">' . $esc($support) . '</a> · Sales: '
            . '<a href="mailto:' . $esc($sales) . '" style="color:#9ca3af;">' . $esc($sales) . '</a></p>'
            . '<p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5;">© ' . $year . ' ' . $brand
            . ' — a brand of <a href="https://silverwebbuzz.com" target="_blank" rel="noopener" style="color:#9ca3af;">Silver Webbuzz Pvt Ltd</a>'
            . ' · Made with care for clinics across India 🌿</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /**
     * Structured content for templates that benefit from buttons/bullets.
     * Returns null for templates that should just flow their plain text.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private static function structuredContent(string $template, array $payload): ?array
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/');

        // Admin override from the email_templates table takes precedence over
        // the code default below. Empty/absent => fall through to code.
        $override = EmailTemplateService::override($template);
        if ($override !== null) {
            return EmailTemplateService::toStructured($override, $payload);
        }

        return match ($template) {
            'welcome' => [
                'greeting' => 'Hello,',
                'paragraphs' => [
                    'Welcome to eClinicPro — your clinic "' . ($payload['clinic_name'] ?? '') . '" is ready.',
                    'Manage appointments, prescriptions, patient records and billing — all from your dashboard.',
                ],
                'cta' => ['label' => 'Open dashboard', 'url' => $appUrl . '/login'],
                'sign_off' => "Best regards,\nThe eClinicPro Team",
            ],
            'register_verify' => [
                'greeting' => 'Hello,',
                'paragraphs' => [
                    'Thanks for starting your eClinicPro clinic. Please verify this email address to continue registration.',
                    'This link expires in 24 hours. If you did not request this, you can ignore this email.',
                ],
                'cta' => ['label' => 'Verify email & continue', 'url' => (string) ($payload['verify_url'] ?? '')],
                'sign_off' => "Best regards,\nThe eClinicPro Team",
            ],
            'doctor_approved' => [
                'greeting' => 'Hello ' . ($payload['doctor_name'] ?? 'Doctor') . ',',
                'paragraphs' => [
                    'Your eClinicPro account has been successfully approved and activated.',
                    'You can sign in to your clinic portal with your verified phone number ('
                        . ($payload['phone'] ?? '') . '). No password is needed — we\'ll send you a one-time code by SMS.',
                    'We would be happy to provide a personalized demo of the platform and help you get started with features such as:',
                ],
                'bullets' => [
                    'Online Appointment Management',
                    'Digital Prescriptions',
                    'Electronic Medical Records (EMR)',
                    'Patient Management',
                    'Clinic Profile & Online Presence',
                ],
                'cta' => ['label' => 'Sign in to your portal', 'url' => (string) ($payload['login_url'] ?? $appUrl . '/doctor/login')],
                'sign_off' => "Please let us know your preferred date and time for a short demo session, and our team will arrange it accordingly. "
                    . "We look forward to supporting your practice.\n\n"
                    . "You can connect with us on WhatsApp or call: +91 9998010029\n\n"
                    . "Best regards,\nThe eClinicPro Team",
            ],
            'doctor_rejected' => [
                'greeting' => 'Hello ' . ($payload['doctor_name'] ?? 'Doctor') . ',',
                'paragraphs' => array_values(array_filter([
                    'Thank you for submitting ' . ($payload['clinic_name'] ?? 'your clinic') . ' to be listed on eClinicPro.',
                    'After reviewing your request, we were unable to approve the listing at this time.',
                    trim((string) ($payload['reason'] ?? '')) !== ''
                        ? 'Reason: ' . $payload['reason']
                        : null,
                    'You can update your details and re-apply using the button below. If you believe this was a mistake, just reply to this email.',
                ])),
                'cta' => ['label' => 'Review & re-apply', 'url' => (string) ($payload['reapply_url'] ?? $appUrl . '/listing')],
                'sign_off' => "Best regards,\nThe eClinicPro Team",
            ],
            'claim_received' => [
                'greeting' => 'New listing request',
                'paragraphs' => array_values(array_filter([
                    'A new ' . ($payload['type_label'] ?? 'listing') . ' request needs review'
                        . (!empty($payload['source_label']) ? ' (via ' . $payload['source_label'] . ')' : '') . '.',
                    'Clinic: ' . ($payload['clinic_name'] ?? '—'),
                    'Doctor: ' . ($payload['doctor_name'] ?? '—'),
                    'Phone: ' . ($payload['phone'] ?? '—'),
                    !empty($payload['applicant_email']) ? 'Email: ' . $payload['applicant_email'] : null,
                    !empty($payload['location']) ? 'Location: ' . $payload['location'] : null,
                    !empty($payload['specialty']) ? 'Specialty: ' . $payload['specialty'] : null,
                ])),
                'cta' => ['label' => 'Review in admin', 'url' => (string) ($payload['review_url'] ?? $appUrl . '/admin/claims')],
                'sign_off' => "— eClinicPro",
            ],
            'password_reset' => [
                'greeting' => 'Hello,',
                'paragraphs' => ['Use the button below to reset your password. This link is valid for 1 hour.'],
                'cta' => ['label' => 'Reset password', 'url' => (string) ($payload['reset_url'] ?? '')],
                'sign_off' => "If you didn't request this, you can safely ignore this email.\n\n— The eClinicPro Team",
            ],
            'staff_invite' => [
                'greeting' => 'Hello ' . ($payload['name'] ?? '') . ',',
                'paragraphs' => [
                    ($payload['clinic_name'] ?? 'A clinic') . ' has invited you to join as '
                        . ($payload['role'] ?? 'a team member') . ' on eClinicPro.',
                    'This invitation expires in 7 days.',
                ],
                'cta' => ['label' => 'Accept invitation', 'url' => (string) ($payload['accept_url'] ?? '')],
                'sign_off' => "Best regards,\nThe eClinicPro Team",
            ],
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private static function renderTemplate(string $template, array $payload): string
    {
        // Templates with rich structure (buttons, bullets) get a tailored layout.
        $structured = self::structuredContent($template, $payload);
        if ($structured !== null) {
            return self::htmlLayout($structured);
        }

        // Everything else: flow its existing plain text into the branded layout.
        $text = self::renderPlainText($template, $payload);

        return self::htmlLayout(['raw' => $text]);
    }

    /** @param array<string, mixed> $payload */
    private static function renderPlainText(string $template, array $payload): string
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/');

        return match ($template) {
            'password_reset' => "Hello,\n\nReset your password using this link (valid 1 hour):\n"
                . ($payload['reset_url'] ?? '') . "\n\nIf you did not request this, ignore this email.",
            'register_verify' => "Hello,\n\nVerify your email to continue setting up your eClinicPro clinic:\n"
                . ($payload['verify_url'] ?? '') . "\n\nThis link expires in 24 hours. If you did not request this, ignore this email.",
            'welcome' => "Hello,\n\nWelcome to eClinicPro — your clinic \""
                . ($payload['clinic_name'] ?? '') . "\" is ready.\n\n"
                . "Log in anytime at {$appUrl}/login\n\n— Team eClinicPro",
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
            'appointment_notification' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "We've received your appointment request with "
                . ($payload['doctor_name'] ?? 'the doctor') . " at "
                . ($payload['clinic_name'] ?? 'the clinic')
                . " for " . ($payload['scheduled_at'] ?? 'your chosen time') . ".\n\n"
                . "The clinic will confirm shortly. For urgent questions, call "
                . ($payload['clinic_phone'] ?? 'the clinic') . ".\n",
            'invoice_paid' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "We've received your payment at " . ($payload['clinic_name'] ?? 'the clinic')
                . ". Invoice " . ($payload['invoice_number'] ?? '') . " — total "
                . ($payload['total'] ?? '') . ".\n\n"
                . (!empty($payload['pdf_url']) ? "Receipt: " . $payload['pdf_url'] . "\n\n" : '')
                . "Thank you.",
            'subscription_invoice' => "Hello " . ($payload['clinic_name'] ?? '') . ",\n\n"
                . "Thank you for subscribing to eClinicPro "
                . ucfirst((string) ($payload['plan_id'] ?? '')) . ".\n\n"
                . "Invoice " . ($payload['invoice_no'] ?? '') . " — "
                . ($payload['currency'] ?? 'INR') . ' ' . ($payload['amount'] ?? '')
                . " (tax invoice available in Settings → Subscription).\n\n"
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
            'doctor_rejected' => "Hello " . ($payload['doctor_name'] ?? 'Doctor') . ",\n\n"
                . "Thank you for submitting " . ($payload['clinic_name'] ?? 'your clinic')
                . " to be listed on eClinicPro. After review, we were unable to approve the listing at this time.\n\n"
                . (trim((string) ($payload['reason'] ?? '')) !== '' ? "Reason: " . $payload['reason'] . "\n\n" : '')
                . "You can update your details and re-apply here:\n"
                . ($payload['reapply_url'] ?? '') . "\n\n"
                . "— Team eClinicPro",
            'claim_received' => "New " . ($payload['type_label'] ?? 'listing') . " request needs review"
                . (!empty($payload['source_label']) ? " (via " . $payload['source_label'] . ")" : '') . ".\n\n"
                . "Clinic: " . ($payload['clinic_name'] ?? '—') . "\n"
                . "Doctor: " . ($payload['doctor_name'] ?? '—') . "\n"
                . "Phone: " . ($payload['phone'] ?? '—') . "\n"
                . (!empty($payload['applicant_email']) ? "Email: " . $payload['applicant_email'] . "\n" : '')
                . (!empty($payload['location']) ? "Location: " . $payload['location'] . "\n" : '')
                . (!empty($payload['specialty']) ? "Specialty: " . $payload['specialty'] . "\n" : '')
                . "\nReview: " . ($payload['review_url'] ?? '') . "\n\n"
                . "— eClinicPro",
            'rx_delivery', 'prescription_ready' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "Your prescription from " . ($payload['clinic_name'] ?? 'the clinic') . " is ready.\n\n"
                . (!empty($payload['rx_url']) ? "Download: " . $payload['rx_url'] . "\n" : ''),
            'follow_up_reminder' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "This is a reminder from " . ($payload['clinic_name'] ?? 'your clinic')
                . " to schedule your follow-up"
                . (!empty($payload['due_date']) ? ' (due ' . $payload['due_date'] . ')' : '')
                . ".\n\n"
                . (!empty($payload['reason']) ? "Reason: {$payload['reason']}\n\n" : '')
                . "Please contact the clinic to book your visit.",
            'diet_plan_shared' => "Hello " . ($payload['patient_name'] ?? '') . ",\n\n"
                . "Your diet plan from " . ($payload['clinic_name'] ?? 'the clinic') . " is ready.\n\n"
                . (!empty($payload['pdf_url']) ? "Download: " . $payload['pdf_url'] . "\n" : ''),
            default => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
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

        // $text is our branded HTML body. Send it as html, with a plain-text
        // fallback (tags stripped) for clients that don't render HTML.
        $isHtml = stripos($text, '<html') !== false || stripos($text, '<table') !== false;
        $ch = curl_init("https://api.mailgun.net/v3/{$domain}/messages");
        $fields = [
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'h:Reply-To' => $_ENV['WECARE_FROM'] ?? 'wecare@eclinicpro.com',
        ];
        if ($isHtml) {
            $fields['html'] = $text;
            $fields['text'] = trim(html_entity_decode(strip_tags(
                preg_replace('~<br\s*/?>~i', "\n", $text) ?? $text,
            ), ENT_QUOTES, 'UTF-8'));
        } else {
            $fields['text'] = $text;
        }
        $archive = self::archiveBcc($to);
        if ($archive !== null) {
            $fields['bcc'] = $archive;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => 'api:' . $_ENV['MAILGUN_API_KEY'],
            CURLOPT_POSTFIELDS => $fields,
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
