<?php

declare(strict_types=1);

use App\Core\Database;

/** @var PDO $pdo */
$pdo = Database::connection();

$email = strtolower($_ENV['PLATFORM_ADMIN_EMAIL'] ?? 'admin@manageclinic.com');
$exists = $pdo->prepare('SELECT id FROM platform_admins WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetchColumn()) {
    echo "  Platform admin already exists ({$email}).\n";

    return;
}

$password = $_ENV['PLATFORM_ADMIN_PASSWORD'] ?? 'ChangeMe!Admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare('INSERT INTO platform_admins (email, password_hash, name) VALUES (?, ?, ?)')
    ->execute([$email, $hash, 'Platform Admin']);

echo "  Platform admin created: {$email}\n";
