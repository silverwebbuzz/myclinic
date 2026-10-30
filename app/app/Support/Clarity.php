<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Microsoft Clarity session recording for app.eclinicpro.com.
 * Injected into every HTML response just before </head>; skipped locally
 * so dev sessions don't pollute the recordings.
 */
final class Clarity
{
    private const PROJECT_ID = 'z5siatdae0';

    public static function inject(string $html): string
    {
        if (strtolower((string) ($_ENV['APP_ENV'] ?? 'local')) === 'local') {
            return $html;
        }

        $pos = stripos($html, '</head>');
        if ($pos === false || str_contains($html, 'clarity.ms/tag/')) {
            return $html;
        }

        $snippet = <<<HTML
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "%s");
</script>

HTML;

        return substr_replace($html, sprintf($snippet, self::PROJECT_ID), $pos, 0);
    }
}
