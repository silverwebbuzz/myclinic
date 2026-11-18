<?php

declare(strict_types=1);

namespace App\Services\Corporate;

/**
 * Encrypts company-portal secrets at rest (MFA secrets now; national IDs later)
 * with libsodium secretbox — same scheme as StoreCrypto.
 *
 * Key: CORP_DATA_KEY in app/.env (32 random bytes, base64). Falls back to
 * STORE_DATA_KEY so the portal works on a server that already has the store
 * key. Generate one with:
 *
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 *
 * The key must never change once data is stored (old rows become unreadable).
 */
final class CorpCrypto
{
    public static function isConfigured(): bool
    {
        return self::key() !== null;
    }

    /** base64(nonce . ciphertext) — survives a utf8mb4 connection in strict mode. */
    public static function encrypt(string $plain): string
    {
        $key = self::key() ?? throw new \RuntimeException('CORP_DATA_KEY (or STORE_DATA_KEY) is not configured.');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public static function decrypt(?string $stored): ?string
    {
        $key = self::key();
        $blob = $stored !== null ? base64_decode($stored, true) : false;
        if ($key === null || $blob === false || strlen($blob) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $key,
        );

        return $plain === false ? null : $plain;
    }

    private static function key(): ?string
    {
        foreach (['CORP_DATA_KEY', 'STORE_DATA_KEY'] as $name) {
            $raw = (string) ($_ENV[$name] ?? getenv($name) ?: '');
            if ($raw === '') {
                continue;
            }
            $key = base64_decode($raw, true);
            if ($key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $key;
            }
        }

        return null;
    }
}
