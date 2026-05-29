<?php

declare(strict_types=1);

/**
 * Inline SVG icon registry. Replaces emoji across the app for a clean,
 * consistent enterprise look. All icons share the same minimal line style
 * (stroke=currentColor) so they inherit text color and brand states.
 *
 * Usage:
 *   ui_icon('patients');                 // default 1em (text-base) size
 *   ui_icon('settings', 18, 'shrink-0'); // explicit px size + extra classes
 *
 * Unknown names fall back to a neutral dot so a typo never breaks layout.
 */

if (!function_exists('ui_icon')) {
    /**
     * @param string $name  Registry key
     * @param int    $size  Pixel size for width/height (default 20)
     * @param string $class Extra CSS classes for the <svg>
     */
    function ui_icon(string $name, int $size = 20, string $class = ''): string
    {
        static $paths = null;
        if ($paths === null) {
            $paths = [
                // Core nav / shell
                'dashboard'    => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
                'patients'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'emr'          => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
                'prescription' => '<path d="M4 4h6a4 4 0 0 1 0 8H4z"/><path d="M4 12v8"/><path d="M10 12l8 8"/><path d="M14 16l4-4"/>',
                'vitals'       => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
                'lab'          => '<path d="M9 3h6"/><path d="M10 3v6.5L5.5 18A2 2 0 0 0 7.3 21h9.4a2 2 0 0 0 1.8-2.9L14 9.5V3"/><line x1="7" y1="15" x2="17" y2="15"/>',
                'radiology'    => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18"/><path d="M3 12h18"/><path d="M7.5 7.5l9 9"/><path d="M16.5 7.5l-9 9"/>',
                'pharmacy'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 13h6"/><path d="M12 13v4"/>',
                'appointments' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'scheduling'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/>',
                'billing'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16l3-2 3 2 3-2 3 2V8z"/><line x1="8" y1="8" x2="14" y2="8"/><line x1="8" y1="12" x2="14" y2="12"/>',
                'whatsapp'     => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/>',
                'qr'           => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><line x1="14" y1="14" x2="14" y2="17"/><line x1="17" y1="14" x2="21" y2="14"/><line x1="21" y1="17" x2="21" y2="21"/><line x1="14" y1="21" x2="17" y2="21"/>',
                'analytics'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
                'crm'          => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
                'staff'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                // Account
                'bell'         => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
                'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
                'help'         => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                // Generic UI
                'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
                'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
                'check'        => '<polyline points="20 6 9 17 4 12"/>',
                'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
            ];
        }

        $body = $paths[$name] ?? '<circle cx="12" cy="12" r="2"/>';
        $cls = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '"'
            . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $body . '</svg>';
    }
}
