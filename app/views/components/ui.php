<?php

declare(strict_types=1);

/**
 * Reusable UI primitives for the app shell. These render the enterprise
 * design tokens defined in layouts/base.php (.ui-* classes) so every page
 * shares the same card, button, field, toggle and badge styling.
 *
 * These are presentation-only string builders — they never touch logic,
 * routes or form submission. All caller-provided text is escaped.
 *
 * Examples:
 *   echo ui_card('Profile', $bodyHtml, ['subtitle' => 'Manage your details']);
 *   echo ui_button('Save changes', ['variant' => 'primary', 'type' => 'submit']);
 *   echo ui_field('First name', 'first_name', $clinic['first_name'] ?? '');
 *   echo ui_toggle('notify', '1', (bool) $on, ['label' => 'Email & SMS']);
 *   echo ui_badge('Paid', 'success');
 */

require_once __DIR__ . '/icons.php';

if (!function_exists('ui_e')) {
    function ui_e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ui_card')) {
    /**
     * @param string $title Section title (escaped). Empty = no header.
     * @param string $bodyHtml Raw HTML body (caller is responsible for escaping its contents).
     * @param array{subtitle?: string, action?: string, class?: string, bodyClass?: string} $opts
     *   action = raw HTML rendered on the right of the header (e.g. a button).
     */
    function ui_card(string $title, string $bodyHtml, array $opts = []): string
    {
        $subtitle = $opts['subtitle'] ?? '';
        $action = $opts['action'] ?? '';
        $class = $opts['class'] ?? '';
        $bodyClass = $opts['bodyClass'] ?? 'ui-card-pad';

        $header = '';
        if ($title !== '' || $action !== '') {
            $header = '<div class="ui-card-header"><div class="min-w-0">'
                . '<h3 class="ui-section-title">' . ui_e($title) . '</h3>'
                . ($subtitle !== '' ? '<p class="ui-section-sub mt-0.5">' . ui_e($subtitle) . '</p>' : '')
                . '</div>'
                . ($action !== '' ? '<div class="shrink-0">' . $action . '</div>' : '')
                . '</div>';
        }

        return '<section class="ui-card ' . ui_e($class) . '">'
            . $header
            . '<div class="' . ui_e($bodyClass) . '">' . $bodyHtml . '</div>'
            . '</section>';
    }
}

if (!function_exists('ui_button')) {
    /**
     * @param array{variant?: string, size?: string, type?: string, href?: string, icon?: string, attrs?: string, class?: string} $opts
     */
    function ui_button(string $label, array $opts = []): string
    {
        $variant = $opts['variant'] ?? 'primary';
        $size = $opts['size'] ?? 'md';
        $icon = $opts['icon'] ?? '';
        $attrs = $opts['attrs'] ?? '';
        $extra = $opts['class'] ?? '';

        $cls = 'ui-btn ui-btn-' . preg_replace('/[^a-z]/', '', $variant)
            . ($size === 'sm' ? ' ui-btn-sm' : '')
            . ($extra !== '' ? ' ' . $extra : '');

        $inner = ($icon !== '' ? ui_icon($icon, 16) : '') . '<span>' . ui_e($label) . '</span>';

        if (!empty($opts['href'])) {
            return '<a href="' . ui_e($opts['href']) . '" class="' . ui_e($cls) . '" ' . $attrs . '>' . $inner . '</a>';
        }

        $type = $opts['type'] ?? 'button';
        return '<button type="' . ui_e($type) . '" class="' . ui_e($cls) . '" ' . $attrs . '>' . $inner . '</button>';
    }
}

if (!function_exists('ui_field')) {
    /**
     * @param array{type?: string, placeholder?: string, help?: string, required?: bool, attrs?: string, name_html?: string} $opts
     */
    function ui_field(string $label, string $name, string $value = '', array $opts = []): string
    {
        $type = $opts['type'] ?? 'text';
        $placeholder = $opts['placeholder'] ?? '';
        $help = $opts['help'] ?? '';
        $required = !empty($opts['required']);
        $attrs = $opts['attrs'] ?? '';
        $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);

        return '<div>'
            . '<label for="' . ui_e($id) . '" class="ui-label mb-1 block">' . ui_e($label)
            . ($required ? ' <span class="text-red-500">*</span>' : '') . '</label>'
            . '<input type="' . ui_e($type) . '" id="' . ui_e($id) . '" name="' . ui_e($name) . '"'
            . ' value="' . ui_e($value) . '" class="ui-input"'
            . ($placeholder !== '' ? ' placeholder="' . ui_e($placeholder) . '"' : '')
            . ($required ? ' required' : '') . ' ' . $attrs . '>'
            . ($help !== '' ? '<p class="ui-help mt-1">' . ui_e($help) . '</p>' : '')
            . '</div>';
    }
}

if (!function_exists('ui_toggle')) {
    /**
     * Renders an iOS-style switch. The hidden checkbox keeps the form
     * submission behavior identical to a normal checkbox input.
     *
     * @param array{label?: string, sub?: string, attrs?: string} $opts
     */
    function ui_toggle(string $name, string $value, bool $checked, array $opts = []): string
    {
        $label = $opts['label'] ?? '';
        $sub = $opts['sub'] ?? '';
        $attrs = $opts['attrs'] ?? '';

        $switch = '<label class="ui-toggle">'
            . '<input type="checkbox" name="' . ui_e($name) . '" value="' . ui_e($value) . '"'
            . ($checked ? ' checked' : '') . ' ' . $attrs . '>'
            . '<span class="ui-toggle-track"></span><span class="ui-toggle-thumb"></span>'
            . '</label>';

        if ($label === '' && $sub === '') {
            return $switch;
        }

        return '<div class="flex items-center justify-between gap-4 py-1">'
            . '<div class="min-w-0"><p class="ui-label">' . ui_e($label) . '</p>'
            . ($sub !== '' ? '<p class="ui-help">' . ui_e($sub) . '</p>' : '') . '</div>'
            . $switch . '</div>';
    }
}

if (!function_exists('ui_badge')) {
    /** @param string $tone success|danger|warning|neutral */
    function ui_badge(string $label, string $tone = 'neutral'): string
    {
        $tone = in_array($tone, ['success', 'danger', 'warning', 'neutral'], true) ? $tone : 'neutral';
        return '<span class="ui-badge ui-badge-' . $tone . '">' . ui_e($label) . '</span>';
    }
}
