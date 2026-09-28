<?php

declare(strict_types=1);

namespace App\Services\Store;

/**
 * Encrypts vendor secrets at rest (bank account numbers, PAN) with libsodium
 * secretbox. Key: STORE_DATA_KEY in app/.env — 32 random bytes, base64-encoded:
 *
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 *
 * The key must never change once data is stored (old rows would become
 * unreadable). Without a key, isConfigured() is false and callers refuse to
 * save secrets rather than storing them in plain text.
 */
final class StoreCrypto
{
    public static function isConfigured(): bool
    {
        return self::key() !== null;
    }

    /**
     * Returns base64(nonce . ciphertext). Base64 rather than raw bytes so the
     * value survives a utf8mb4 connection in strict mode (raw binary can be
     * rejected as an invalid character string).
     */
    public static function encrypt(string $plain): string
    {
        $key = self::key() ?? throw new \RuntimeException('STORE_DATA_KEY is not configured.');
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
        $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        return $plain === false ? null : $plain;
    }

    private static function key(): ?string
    {
        $raw = (string) ($_ENV['STORE_DATA_KEY'] ?? getenv('STORE_DATA_KEY') ?: '');
        if ($raw === '') {
            return null;
        }
        $key = base64_decode($raw, true);

        return ($key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) ? $key : null;
    }
}
