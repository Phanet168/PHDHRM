<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

class HtmlSanitizer
{
    /**
     * Sanitizes rich content HTML (text/html blocks) with HTMLPurifier's
     * whitelist-based 'cms' profile, then strips any script/inline-JS
     * constructs that could somehow survive as defense in depth.
     */
    public static function clean(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $clean = Purifier::clean($html, 'cms');

        return self::stripDangerousConstructs($clean);
    }

    /**
     * Removes <script> tags, javascript: URIs, and inline on* event handler
     * attributes. HTMLPurifier already excludes these via its whitelist, but
     * this is enforced again explicitly since the security requirement is
     * that these must never appear in stored or rendered content.
     */
    public static function stripDangerousConstructs(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*\/?>/is', '', $html) ?? $html;
        $html = preg_replace('/\bon[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/is', '', $html) ?? $html;
        $html = preg_replace('/vbscript\s*:/is', '', $html) ?? $html;

        return $html;
    }

    public static function containsDangerousConstructs(?string $html): bool
    {
        if (! $html) {
            return false;
        }

        return (bool) preg_match('/<script\b|on[a-z]+\s*=|javascript\s*:|vbscript\s*:/is', $html);
    }
}
