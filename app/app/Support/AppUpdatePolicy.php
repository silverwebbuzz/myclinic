<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Patient mobile app "is there an update?" rules, shared by the public endpoint
 * (api/mobile/v1/app_version.php) and the admin preview (/admin/app-versions),
 * so both always give the same answer.
 *
 * Dependency-free on purpose: the endpoint require_once's this file directly
 * (the marketing-side API doesn't boot the portal's autoloader).
 *
 * Settings live in platform_settings, per platform p = android | ios:
 *   app_{p}_latest_version  display string, e.g. "1.2.0"
 *   app_{p}_latest_build    int: Flutter build number (the 7 in 1.2.0+7)
 *   app_{p}_min_build       int: builds below this must update ('' = no minimum)
 *   app_{p}_store_url       Play Store / App Store link
 *   app_{p}_update_title    dialog title ('' = default per update kind)
 *   app_{p}_update_message  dialog text ('' = default per update kind)
 *   app_{p}_update_notes    "what's new", one line per item
 *
 * Builds are compared as integers only, never by version string. With no
 * latest build or no store URL configured the answer is always "none", so an
 * empty or half-filled setup can never lock users out of the app.
 */
final class AppUpdatePolicy
{
    public const PLATFORMS = ['android' => 'Android', 'ios' => 'iOS'];

    public const FIELDS = ['latest_version', 'latest_build', 'min_build', 'store_url', 'update_title', 'update_message', 'update_notes'];

    public const MAX_NOTES = 10;

    /** @return list<string> every platform_settings key this feature uses */
    public static function keys(): array
    {
        $out = [];
        foreach (array_keys(self::PLATFORMS) as $p) {
            foreach (self::FIELDS as $f) {
                $out[] = self::key($p, $f);
            }
        }

        return $out;
    }

    public static function key(string $platform, string $field): string
    {
        return 'app_' . $platform . '_' . $field;
    }

    /**
     * The JSON body (without "ok") the app receives.
     *
     * @param array<string, string|null> $settings platform_settings key => value
     * @return array{platform: string, update: string, latest_version: ?string, latest_build: ?int, min_build: ?int,
     *               store_url: ?string, title: ?string, message: ?string, notes: list<string>}
     */
    public static function answer(array $settings, string $platform, int $build): array
    {
        $get = static fn (string $f): string => trim((string) ($settings[self::key($platform, $f)] ?? ''));
        $latest = self::posInt($get('latest_build'));
        $min = self::posInt($get('min_build'));
        $url = $get('store_url');
        $configured = $latest !== null && $url !== '';

        $update = 'none';
        if ($configured) {
            if ($min !== null && $build < $min) {
                $update = 'required';
            } elseif ($build < $latest) {
                $update = 'optional';
            }
        }
        $title = $get('update_title');
        $message = $get('update_message');

        return [
            'platform' => $platform,
            'update' => $update,
            'latest_version' => $configured ? ($get('latest_version') ?: null) : null,
            'latest_build' => $configured ? $latest : null,
            'min_build' => $configured ? $min : null,
            'store_url' => $configured ? $url : null,
            'title' => match ($update) {
                'required' => $title ?: 'Update required',
                'optional' => $title ?: 'Update available',
                default => null,
            },
            'message' => match ($update) {
                'required' => $message ?: 'This version of eClinicPro is no longer supported. Please update to continue.',
                'optional' => $message ?: 'A new version of eClinicPro is available with improvements and fixes.',
                default => null,
            },
            'notes' => $configured ? self::notes($get('update_notes')) : [],
        ];
    }

    /** @return list<string> non-empty trimmed lines, at most MAX_NOTES */
    public static function notes(string $raw): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $raw) ?: []), static fn ($l) => $l !== ''));

        return array_slice($lines, 0, self::MAX_NOTES);
    }

    /**
     * Validate one platform's admin form.
     *
     * @param array<string, mixed> $in field => posted value
     * @return array{ok: bool, errors: list<string>, values: array<string, string>} values = field => value to store
     */
    public static function validate(string $platform, array $in): array
    {
        $label = self::PLATFORMS[$platform] ?? $platform;
        $v = [];
        foreach (self::FIELDS as $f) {
            $v[$f] = trim(str_replace("\r\n", "\n", (string) ($in[$f] ?? '')));
        }
        $v['update_notes'] = implode("\n", self::notes($v['update_notes']));

        // Everything blank = switch the update check off for this platform.
        if (implode('', $v) === '') {
            return ['ok' => true, 'errors' => [], 'values' => $v];
        }
        $errors = [];
        $latest = self::posInt($v['latest_build']);
        $min = $v['min_build'] === '' ? null : self::posInt($v['min_build']);
        if ($latest === null) {
            $errors[] = "$label: latest build must be a whole number of 1 or more.";
        }
        if ($v['min_build'] !== '' && $min === null) {
            $errors[] = "$label: minimum build must be a whole number of 1 or more (or leave it empty).";
        }
        if ($latest !== null && $min !== null && $min > $latest) {
            $errors[] = "$label: minimum build ($min) can't be higher than the latest build ($latest).";
        }
        if ($v['latest_version'] === '' || mb_strlen($v['latest_version']) > 20) {
            $errors[] = "$label: enter the latest version as shown in the store, e.g. 1.2.0 (max 20 characters).";
        }
        if (!preg_match('#^https://\S+$#', $v['store_url']) || mb_strlen($v['store_url']) > 300) {
            $errors[] = "$label: store URL must be a full https:// link.";
        }
        if (mb_strlen($v['update_title']) > 80) {
            $errors[] = "$label: title is too long (max 80 characters).";
        }
        if (mb_strlen($v['update_message']) > 500) {
            $errors[] = "$label: message is too long (max 500 characters).";
        }
        foreach (self::notes($v['update_notes']) as $line) {
            if (mb_strlen($line) > 120) {
                $errors[] = "$label: each what's-new line must be 120 characters or less.";
                break;
            }
        }
        if ($latest !== null) {
            $v['latest_build'] = (string) $latest;
        }
        if ($min !== null) {
            $v['min_build'] = (string) $min;
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'values' => $v];
    }

    /** "7" → 7; anything that isn't a whole number ≥ 1 → null. */
    public static function posInt(string $s): ?int
    {
        $s = trim($s);
        if (!preg_match('/^\d{1,9}$/', $s) || (int) $s < 1) {
            return null;
        }

        return (int) $s;
    }
}
