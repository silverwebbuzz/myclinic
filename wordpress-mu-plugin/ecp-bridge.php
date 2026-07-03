<?php
/**
 * Plugin Name: eClinicPro Bridge
 * Description: Secures WordPress REST API access from eClinicPro using HMAC bridge tokens.
 * Version: 1.0.0
 * Author: eClinicPro
 *
 * Install: copy to wp-content/mu-plugins/ecp-bridge.php
 * Set WORDPRESS_BRIDGE_SECRET in app/.env to the same value as ECP_BRIDGE_SECRET below.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ECP_BRIDGE_SECRET')) {
    define('ECP_BRIDGE_SECRET', getenv('ECP_BRIDGE_SECRET') ?: '');
}

add_filter('rest_authentication_errors', static function ($result) {
    if ($result !== null) {
        return $result;
    }

    if (ECP_BRIDGE_SECRET === '') {
        return $result;
    }

    $header = $_SERVER['HTTP_X_ECP_BRIDGE_TOKEN'] ?? '';
    if ($header === '' || !str_contains($header, '.')) {
        return $result;
    }

    [$ts, $sig] = explode('.', $header, 2);
    if (!ctype_digit($ts) || $sig === '') {
        return new WP_Error('ecp_bridge_invalid', 'Invalid bridge token.', ['status' => 401]);
    }

    $age = abs(time() - (int) $ts);
    if ($age > 300) {
        return new WP_Error('ecp_bridge_expired', 'Bridge token expired.', ['status' => 401]);
    }

    $expected = hash_hmac('sha256', $ts, ECP_BRIDGE_SECRET);
    if (!hash_equals($expected, $sig)) {
        return new WP_Error('ecp_bridge_denied', 'Bridge token rejected.', ['status' => 401]);
    }

    // Valid bridge token — allow request (Application Password still required for user context).
    return $result;
}, 15);
