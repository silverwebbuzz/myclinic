<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MessagingSettings;

final class RecaptchaService
{
    public static function enabled(): bool
    {
        return MessagingSettings::get('recaptcha_enabled', '0') === '1'
            && self::siteKey() !== ''
            && self::secretKey() !== '';
    }

    public static function siteKey(): string
    {
        return (string) (MessagingSettings::get('recaptcha_site_key', '') ?? '');
    }

    public static function secretKey(): string
    {
        return (string) (MessagingSettings::get('recaptcha_secret_key', '') ?? '');
    }

    public static function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!self::enabled()) {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $post = [
            'secret' => self::secretKey(),
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $post['remoteip'] = $remoteIp;
        }

        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => http_build_query($post),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response) || $response === '') {
            return false;
        }

        $json = json_decode($response, true);
        return is_array($json) && !empty($json['success']);
    }
}
