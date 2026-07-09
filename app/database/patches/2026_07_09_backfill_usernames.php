<?php
/**
 * Backfill users.username from phone for legacy accounts.
 *
 *   php app/database/patches/2026_07_09_backfill_usernames.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2)))->safeLoad();

use App\Core\Database;
use App\Services\UsernameService;

$pdo = Database::connection();
$rows = $pdo->query(
    "SELECT id, phone, name FROM users WHERE username IS NULL OR username = '' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($rows as $row) {
    $phone = (string) ($row['phone'] ?? '');
    if ($phone === '') {
        continue;
    }
    $username = UsernameService::resolveForRegistration('', $phone, (string) ($row['name'] ?? 'Doctor'));
    $stmt = $pdo->prepare('UPDATE users SET username = :u WHERE id = :id');
    $stmt->execute(['u' => $username, 'id' => (int) $row['id']]);
    $updated++;
    echo "user #{$row['id']} => {$username}\n";
}

echo "Done. Updated {$updated} user(s).\n";
