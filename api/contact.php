<?php
// =====================================================================
// api/contact.php — public "Contact us" inquiry submission.
//
//   POST /api/contact?action=submit    (anonymous, public form)
//     body: {
//       name:    "Riya Mehta",
//       email:   "riya@example.com",
//       phone:   "+91 98XXXXXXXX"   (optional),
//       subject: "Sales" | "Support" | ...   (optional),
//       message: "…",
//       company: ""                 (honeypot — must stay empty),
//       g-recaptcha-response: "…"   (if captcha enabled)
//     }
//     → emails the inquiry to hello@eclinicpro.com, sent FROM
//       noreply@eclinicpro.com with Reply-To set to the visitor so a
//       reply lands back in their inbox.
//
// No DB write — this is a pure notification form. Sending failures are
// surfaced so the visitor knows to try again / email directly.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../partials/mailer.php';
require_once __DIR__ . '/../partials/patient_auth.php';   // for ecp_recaptcha_config/verify

// Where inquiries land, and who they appear to come from.
const ECP_CONTACT_TO   = 'hello@eclinicpro.com';
const ECP_CONTACT_FROM = 'noreply@eclinicpro.com';

set_exception_handler(function (Throwable $e) {
    error_log('[api/contact] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

function out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_starts_with(trim($raw), '{')) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method !== 'POST')      out(405, ['ok' => false, 'error' => 'method_not_allowed']);
if ($action !== 'submit')    out(400, ['ok' => false, 'error' => 'unknown_action']);

$in = input_json();

// --- Honeypot: real users never fill "company". Bots do. Pretend success. ---
if (trim((string) ($in['company'] ?? '')) !== '') {
    out(200, ['ok' => true, 'message' => "Thanks — we'll be in touch soon."]);
}

$name    = trim((string) ($in['name'] ?? ''));
$email   = trim((string) ($in['email'] ?? ''));
$phone   = trim((string) ($in['phone'] ?? ''));
$subject = trim((string) ($in['subject'] ?? 'General enquiry'));
$message = trim((string) ($in['message'] ?? ''));

// --- Validation ---
if ($name === '' || mb_strlen($name) > 120) {
    out(400, ['ok' => false, 'error' => 'name_required']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(400, ['ok' => false, 'error' => 'invalid_email']);
}
if ($message === '' || mb_strlen($message) < 10) {
    out(400, ['ok' => false, 'error' => 'message_too_short']);
}
if (mb_strlen($message) > 5000) {
    out(400, ['ok' => false, 'error' => 'message_too_long']);
}
// Guard against header-injection via the subject choice.
$subject = preg_replace('/[\r\n]+/', ' ', $subject);
$subject = mb_substr($subject, 0, 80);
if ($subject === '') $subject = 'General enquiry';

// --- Captcha (only enforced if configured) ---
$captchaCfg = ecp_recaptcha_config();
if (!empty($captchaCfg['enabled'])) {
    $token = (string) ($in['g-recaptcha-response'] ?? '');
    if (!ecp_recaptcha_verify($token, $_SERVER['REMOTE_ADDR'] ?? null)) {
        out(400, ['ok' => false, 'error' => 'captcha_failed']);
    }
}

// --- Compose the notification email ---
// Heredoc can't call functions, so escape into plain vars first.
$esc     = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$eName    = $esc($name);
$eEmail   = $esc($email);
$eSubject = $esc($subject);
$eMessage = $esc($message);
$phoneHtml = $phone !== '' ? '<p style="margin:0 0 8px;"><strong>Phone:</strong> ' . $esc($phone) . '</p>' : '';
$ip      = $esc((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$when    = date('D, d M Y H:i');

$body = <<<HTML
<p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
  A new enquiry was submitted through the eClinicPro website contact form.
</p>
<p style="margin:0 0 8px;"><strong>Name:</strong> {$eName}</p>
<p style="margin:0 0 8px;"><strong>Email:</strong> <a href="mailto:{$eEmail}" style="color:#0F9B6E;">{$eEmail}</a></p>
{$phoneHtml}
<p style="margin:0 0 8px;"><strong>Subject:</strong> {$eSubject}</p>
<p style="margin:16px 0 6px;"><strong>Message:</strong></p>
<div style="white-space:pre-wrap; font-size:15px; line-height:1.6; background:#f5f5f7; border-radius:10px; padding:14px 16px;">{$eMessage}</div>
<p style="margin:20px 0 0; font-size:12px; color:#9a9aa0;">
  Sent {$when} · IP {$ip}. Reply directly to this email to respond to {$eName}.
</p>
HTML;

$html = ecp_email_template('New enquiry: ' . $subject, $body);

// From noreply@, Reply-To the visitor so hitting "Reply" reaches them.
$sent = ecp_send_mail(
    ECP_CONTACT_TO,               // to
    'Contact form: ' . $subject,  // subject
    $html,                        // html body
    'eClinicPro',                 // to name
    $email,                       // reply-to = visitor
    ECP_CONTACT_FROM,             // from
    $name . ' via eClinicPro'     // from name
);

if (!$sent) {
    // Don't lose the lead silently — tell the visitor to email directly.
    out(502, ['ok' => false, 'error' => 'send_failed']);
}

out(200, ['ok' => true, 'message' => "Thanks, {$name}! We've received your message and will reply to {$email} soon."]);
