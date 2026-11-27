<?php

declare(strict_types=1);

// Cron: once a day, e.g. 8 AM IST — php workers/wellness_camp_alerts.php
//   (--dry-run prints what would be sent and sends nothing)
//
// Wellness Hub Phase 5: emails each "notify me" subscriber the camps / events
// newly listed in their city since they subscribed (max 10 per email, each
// listing at most once per subscriber). Email only — no WhatsApp / SMS.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\MailService;
use App\Services\Wellness\CreatorService;
use App\Support\ClinicTime;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}
ClinicTime::bootstrap();   // camp times in the email are IST
$dry = in_array('--dry-run', $argv, true);
$db = Database::connection();

try {
    $alerts = $db->query('SELECT * FROM wh_camp_alerts WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "wh_camp_alerts missing — run the Wellness Hub Phase 5 patch.\n";
    exit(0);
}

$find = $db->prepare(
    "SELECT e.id, e.kind, e.slug, e.title, e.start_at, e.end_at, e.venue_name, e.city_name, e.fee_amount
     FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id AND w.status = 'active'
     WHERE e.kind = :k AND e.city_slug = :c AND e.status = 'published' AND e.is_deleted = 0
       AND e.end_at >= NOW() AND e.published_at >= :since
       AND NOT EXISTS (SELECT 1 FROM wh_camp_alert_sent s WHERE s.alert_id = :a AND s.event_id = e.id)
     ORDER BY e.start_at ASC LIMIT 10"
);
$mark = $db->prepare('INSERT IGNORE INTO wh_camp_alert_sent (alert_id, event_id) VALUES (:a, :e)');
$touch = $db->prepare('UPDATE wh_camp_alerts SET last_sent_at = NOW() WHERE id = :a');

$sent = 0;
foreach ($alerts as $al) {
    $find->execute(['k' => $al['kind'], 'c' => $al['city_slug'], 'since' => $al['created_at'], 'a' => (int) $al['id']]);
    $events = $find->fetchAll(PDO::FETCH_ASSOC);
    if (!$events) {
        continue;
    }
    $what = ($al['kind'] === 'event' ? 'health events' : 'free health camps') . ' in ' . $al['city_name'];
    $lines = [];
    foreach ($events as $ev) {
        $s = strtotime((string) $ev['start_at']);
        $lines[] = '• ' . date('D, d M', $s) . ', ' . date('h:i A', $s) . ' — ' . $ev['title']
            . ($ev['venue_name'] ? ' (' . $ev['venue_name'] . ')' : '')
            . ((float) $ev['fee_amount'] > 0 ? ' · ₹' . number_format((float) $ev['fee_amount'], 0) : ' · Free')
            . ': ' . CreatorService::siteUrl('/wellness/' . ($ev['kind'] === 'free_camp' ? 'camp' : 'event') . '/' . $ev['slug']);
    }
    if ($dry) {
        echo "[dry-run] {$al['email']} — " . count($events) . " × {$what}\n  " . implode("\n  ", $lines) . "\n";
        continue;
    }
    MailService::send((string) $al['email'], 'wellness_camp_alert', [
        'subject' => count($events) . ' new ' . $what,
        'what' => $what,
        'lines' => $lines,
        'list_url' => CreatorService::siteUrl('/wellness/' . ($al['kind'] === 'event' ? 'events' : 'camps') . '/' . $al['city_slug']),
        'unsubscribe_url' => CreatorService::siteUrl('/api/wellness_alerts?unsubscribe=' . $al['token']),
    ]);
    foreach ($events as $ev) {
        $mark->execute(['a' => (int) $al['id'], 'e' => (int) $ev['id']]);
    }
    $touch->execute(['a' => (int) $al['id']]);
    $sent++;
}

echo ($dry ? '[dry-run] ' : '') . "Wellness camp alerts: {$sent} email(s) sent, " . count($alerts) . " active subscription(s).\n";
