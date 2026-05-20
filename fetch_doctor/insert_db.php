<?php
// =====================================================================
// fetch_doctor/insert_db.php
// Lists every JSON file in fetch_doctor/json/, lets you pick which to
// import, and upserts into the directory_doctors table on the portal DB.
// Existing rows are UPDATED, not duplicated (uniqueness on place_id).
//
// Usage:
//   https://eclinicpro.com/fetch_doctor/insert_db.php
// =====================================================================

declare(strict_types=1);

// ---------- DB connection (reads portal /app/.env automatically) ----------
function db(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $envFiles = [__DIR__ . '/../app/.env', __DIR__ . '/.env'];
    $env = [];
    foreach ($envFiles as $path) {
        if (!is_file($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\"'");
        }
    }
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $name = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    if ($name === '' || $user === '') return null;
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function file_summary(string $path): array {
    $raw = json_decode((string) file_get_contents($path), true);
    return [
        'name'       => basename($path),
        'city'       => $raw['city']    ?? '?',
        'state'      => $raw['state']   ?? '?',
        'count'      => (int) ($raw['count'] ?? 0),
        'updated_at' => $raw['updated_at'] ?? null,
        'size'       => filesize($path),
    ];
}

$jsonDir = __DIR__ . '/json';
$files = glob($jsonDir . '/*.json') ?: [];
sort($files);

$dbReady = (db() !== null);

// =====================================================================
// IMPORT — POST a list of file names, upsert each row
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['files'])) {
    @set_time_limit(0);
    header('Content-Type: text/plain; charset=utf-8');
    echo "fetch_doctor — importing\n" . str_repeat('=', 60) . "\n\n";

    $pdo = db();
    if (!$pdo) {
        echo "❌ DB connection failed. Check fetch_doctor/.env or ../app/.env\n";
        exit;
    }

    // Quick sanity: directory_doctors table exists?
    try {
        $pdo->query('SELECT 1 FROM directory_doctors LIMIT 1');
    } catch (Throwable $e) {
        echo "❌ Table 'directory_doctors' missing. Run the SQL at the bottom of this file first.\n";
        echo "   See: " . __DIR__ . "/migration.sql\n";
        exit;
    }

    $sql = "INSERT INTO directory_doctors
        (place_id, source, name, specialty, country, city, state, area, address, lat, lng, plus_code,
         phone, intl_phone, website, gmaps_url, status, rating, reviews, price_level, last_review_at,
         types, opening_hours, photo_reference, quality_score, is_active, dropped_reason,
         fetched_at, refreshed_at)
        VALUES
        (:place_id, 'google', :name, :specialty, :country, :city, :state, :area, :address, :lat, :lng, :plus_code,
         :phone, :intl_phone, :website, :gmaps_url, :status, :rating, :reviews, :price_level, :last_review_at,
         :types, :opening_hours, :photo_reference, :quality_score, :is_active, :dropped_reason,
         :fetched_at, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            specialty = VALUES(specialty),
            state = VALUES(state),
            address = VALUES(address),
            lat = VALUES(lat), lng = VALUES(lng), plus_code = VALUES(plus_code),
            phone = VALUES(phone), intl_phone = VALUES(intl_phone),
            website = VALUES(website), gmaps_url = VALUES(gmaps_url),
            status = VALUES(status),
            rating = VALUES(rating), reviews = VALUES(reviews),
            price_level = VALUES(price_level),
            last_review_at = VALUES(last_review_at),
            types = VALUES(types), opening_hours = VALUES(opening_hours),
            photo_reference = VALUES(photo_reference),
            quality_score = VALUES(quality_score),
            -- Don't overwrite is_active if a human deactivated this row.
            -- Only re-activate when we see fresh OPERATIONAL data.
            is_active = IF(VALUES(status) = 'OPERATIONAL', 1, 0),
            dropped_reason = VALUES(dropped_reason),
            refreshed_at = NOW()";
    $stmt = $pdo->prepare($sql);

    // Quality scoring — higher is better. Used to rank results on the public page.
    // Formula: reviews (capped at 100) + rating*20 - flag penalty - staleness penalty.
    $scoreRow = function (array $d): int {
        $reviews = (int) ($d['reviews'] ?? 0);
        $rating  = (float) ($d['rating'] ?? 0);
        $score   = min($reviews, 100) + (int) round($rating * 20);

        // Penalize flagged rows.
        $reason = $d['dropped_reason'] ?? null;
        if ($reason === 'low_reviews')    $score -= 40;
        if ($reason === 'no_hours')       $score -= 20;
        if ($reason === 'stale_reviews')  $score -= 30;

        // Staleness — months since most recent review.
        if (!empty($d['last_review_at'])) {
            $months = (time() - strtotime((string) $d['last_review_at'])) / (30 * 86400);
            if ($months > 6) $score -= (int) round(min($months, 36));
        }

        return max(0, min(200, $score));
    };

    $totalInserted = 0;
    $totalUpdated  = 0;
    $totalFailed   = 0;

    foreach ((array) $_POST['files'] as $fileName) {
        $fileName = basename((string) $fileName); // strip any path attempt
        $path = $jsonDir . '/' . $fileName;
        if (!is_file($path)) { echo "  skip missing: {$fileName}\n"; continue; }

        $raw = json_decode((string) file_get_contents($path), true);
        $doctors = $raw['doctors'] ?? null;
        if (!is_array($doctors)) { echo "  skip invalid JSON: {$fileName}\n"; continue; }

        echo "📄 {$fileName} — " . count($doctors) . " doctors\n";

        $pdo->beginTransaction();
        $ins = 0; $upd = 0; $fail = 0;
        foreach ($doctors as $d) {
            $pid = $d['place_id'] ?? null;
            if (!$pid) { $fail++; continue; }
            try {
                // Check if the place_id already exists so we can report insert vs update
                $check = $pdo->prepare('SELECT id FROM directory_doctors WHERE place_id = ?');
                $check->execute([$pid]);
                $existed = (bool) $check->fetchColumn();

                $score = $scoreRow($d);
                $status = $d['status'] ?? 'OPERATIONAL';
                $isActive = ($status === 'OPERATIONAL') ? 1 : 0;

                $stmt->execute([
                    ':place_id'        => $pid,
                    ':name'            => (string) ($d['name'] ?? ''),
                    ':specialty'       => $d['specialty'] ?? null,
                    ':country'         => $d['country']   ?? 'IN',
                    ':city'            => $d['city']      ?? '',
                    ':state'           => $d['state']     ?? null,
                    ':area'            => $d['area']      ?? null,
                    ':address'         => $d['address']   ?? null,
                    ':lat'             => $d['lat']       ?? null,
                    ':lng'             => $d['lng']       ?? null,
                    ':plus_code'       => $d['plus_code'] ?? null,
                    ':phone'           => $d['phone']     ?? null,
                    ':intl_phone'      => $d['intl_phone']?? null,
                    ':website'         => $d['website']   ?? null,
                    ':gmaps_url'       => $d['gmaps_url'] ?? null,
                    ':status'          => $status,
                    ':rating'          => $d['rating']    ?? null,
                    ':reviews'         => $d['reviews']   ?? 0,
                    ':price_level'     => $d['price_level'] ?? null,
                    ':last_review_at'  => $d['last_review_at'] ?? null,
                    ':types'           => isset($d['types']) ? json_encode($d['types'], JSON_UNESCAPED_UNICODE) : null,
                    ':opening_hours'   => isset($d['opening_hours']) ? json_encode($d['opening_hours'], JSON_UNESCAPED_UNICODE) : null,
                    ':photo_reference' => $d['photo_reference'] ?? null,
                    ':quality_score'   => $score,
                    ':is_active'       => $isActive,
                    ':dropped_reason'  => $d['dropped_reason'] ?? null,
                    ':fetched_at'      => $d['fetched_at']?? null,
                ]);
                $existed ? $upd++ : $ins++;
            } catch (Throwable $e) {
                $fail++;
                echo "  ⚠ row fail [{$pid}]: " . $e->getMessage() . "\n";
            }
        }
        $pdo->commit();

        echo "  ✅ inserted: {$ins} · updated: {$upd}" . ($fail ? " · failed: {$fail}" : '') . "\n\n";
        $totalInserted += $ins;
        $totalUpdated  += $upd;
        $totalFailed   += $fail;
    }

    echo str_repeat('=', 60) . "\n";
    echo "TOTAL — inserted: {$totalInserted} · updated: {$totalUpdated}" . ($totalFailed ? " · failed: {$totalFailed}" : '') . "\n";
    echo "\nDone. Visit https://eclinicpro.com/find-a-doctor to see them live.\n";
    exit;
}

// =====================================================================
// PICKER UI
// =====================================================================
$rowsInDb = 0;
if ($dbReady) {
    try { $rowsInDb = (int) db()->query('SELECT COUNT(*) FROM directory_doctors')->fetchColumn(); }
    catch (Throwable $e) { $rowsInDb = -1; } // table missing
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Import Doctors to DB — eClinicPro</title>
<style>
:root { --teal: #0F9B6E; --line: rgba(0,0,0,0.08); --bg: #f8fafc; --mute: #64748b; --ink: #0f172a; }
* { box-sizing: border-box; }
body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; margin: 0; background: var(--bg); color: var(--ink); }
.wrap { max-width: 880px; margin: 0 auto; padding: 32px 20px 80px; }
h1 { font-size: 24px; font-weight: 600; margin: 0 0 8px; }
.lede { color: var(--mute); margin: 0 0 24px; }
.warn { padding: 12px 16px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
.warn pre { background: #fff; padding: 10px; border-radius: 6px; margin: 8px 0 0; font-size: 12px; overflow-x: auto; }
.toolbar { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.toolbar button, .toolbar a {
  font: inherit; background: #fff; border: 1px solid var(--line); padding: 7px 14px;
  border-radius: 8px; cursor: pointer; color: var(--ink); text-decoration: none;
}
.toolbar button:hover, .toolbar a:hover { border-color: var(--teal); color: var(--teal); }
.summary { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--mute); margin-bottom: 18px; }
.summary strong { color: var(--ink); }
.list { background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.row { display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--line); cursor: pointer; gap: 14px; }
.row:last-child { border-bottom: 0; }
.row:hover { background: #f1f5f9; }
.row input { margin: 0; cursor: pointer; }
.row .file { flex: 1; min-width: 0; }
.row .nm { font-weight: 500; }
.row .meta { font-size: 12px; color: var(--mute); margin-top: 2px; }
.row .count { font-size: 12px; color: var(--teal); font-weight: 500; }
.empty { padding: 60px 20px; text-align: center; color: var(--mute); background: #fff; border: 1px dashed var(--line); border-radius: 12px; }
.empty .glyph { font-size: 32px; margin-bottom: 8px; }
.submit {
  position: sticky; bottom: 16px; margin-top: 24px; background: var(--ink); color: #fff;
  padding: 14px 18px; border: 0; border-radius: 12px; font-size: 14px; font-weight: 500;
  cursor: pointer; width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.submit:hover { background: var(--teal); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }
details { margin-top: 24px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px 18px; }
details summary { cursor: pointer; font-weight: 500; }
details pre { font-size: 11px; line-height: 1.5; background: #f1f5f9; padding: 14px; border-radius: 8px; overflow-x: auto; }
</style>
</head>
<body>
<div class="wrap">

<h1>💾 Import to Database</h1>
<p class="lede">Pick JSON files saved by <a href="index.php">index.php</a>. Each file is upserted into <code>directory_doctors</code>. Existing rows (matched on Google <code>place_id</code>) are <strong>updated</strong>, not duplicated.</p>

<?php if (!$dbReady): ?>
    <div class="warn">⚠ <strong>DB connection failed.</strong> Make sure <code>../app/.env</code> has DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD set.</div>
<?php elseif ($rowsInDb === -1): ?>
    <div class="warn">
        ⚠ <strong>Table <code>directory_doctors</code> not found.</strong> Run this SQL once in your database:
        <pre><?= htmlspecialchars(file_get_contents(__DIR__ . '/migration.sql') ?: 'See fetch_doctor/migration.sql') ?></pre>
    </div>
<?php endif; ?>

<div class="summary">
    <span>JSON files: <strong><?= count($files) ?></strong></span>
    <span>Doctors already in DB: <strong><?= $rowsInDb >= 0 ? number_format($rowsInDb) : '—' ?></strong></span>
    <a href="index.php" style="margin-left:auto;color:var(--teal);">← Back to fetcher</a>
</div>

<?php if (empty($files)): ?>
    <div class="empty">
        <div class="glyph">📄</div>
        <p>No JSON files yet.</p>
        <p style="font-size:12px;">Run <a href="index.php" style="color:var(--teal);">index.php</a> to fetch a city first.</p>
    </div>
<?php else: ?>

<form method="post">
<div class="toolbar">
    <button type="button" onclick="toggleAll(true)">Select all</button>
    <button type="button" onclick="toggleAll(false)">Clear all</button>
</div>

<div class="list">
<?php foreach ($files as $path): $s = file_summary($path); ?>
<label class="row">
    <input type="checkbox" name="files[]" value="<?= htmlspecialchars($s['name']) ?>">
    <div class="file">
        <div class="nm"><?= htmlspecialchars($s['city']) ?> <small style="color:var(--mute);font-weight:400;">— <?= htmlspecialchars($s['state']) ?></small></div>
        <div class="meta">
            <?= htmlspecialchars($s['name']) ?>
            · <?= htmlspecialchars($s['updated_at'] ?? '—') ?>
            · <?= number_format($s['size'] / 1024, 1) ?> KB
        </div>
    </div>
    <span class="count"><?= number_format($s['count']) ?> doctors</span>
</label>
<?php endforeach; ?>
</div>

<button type="submit" class="submit" <?= !$dbReady || $rowsInDb === -1 ? 'disabled' : '' ?>>
    💾 Import selected · <span id="count">0</span> file(s)
</button>
</form>
<?php endif; ?>

<details>
    <summary>📋 SQL — first-time table setup</summary>
    <p style="font-size:12px;color:var(--mute);margin:8px 0 12px;">
        Run this once via phpMyAdmin → SQL tab (or <code>mysql -u USER -p DB &lt; migration.sql</code>):
    </p>
    <pre><?= htmlspecialchars(file_get_contents(__DIR__ . '/migration.sql') ?: 'migration.sql not found') ?></pre>
</details>

</div>

<script>
function toggleAll(on) {
    document.querySelectorAll('input[name="files[]"]').forEach(i => i.checked = on);
    updateCount();
}
function updateCount() {
    document.getElementById('count').textContent =
        document.querySelectorAll('input[name="files[]"]:checked').length;
}
document.addEventListener('change', e => { if (e.target.matches('input[name="files[]"]')) updateCount(); });
</script>
</body>
</html>
