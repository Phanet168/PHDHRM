<?php

namespace App\Services;

class CssScoper
{
    private const DANGEROUS_PATTERNS = [
        '/@import[^;]*;?/i',
        '/expression\s*\([^)]*\)/i',
        '/behavior\s*:[^;]*;?/i',
        '/-moz-binding\s*:[^;]*;?/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/<\/?style[^>]*>/i',
        '/<\/?script[^>]*>/i',
    ];

    private const UNSCOPED_AT_RULES = ['keyframes', 'font-face', 'page', 'charset', 'import', 'namespace'];

    /**
     * Scopes every selector in the given CSS under #{$idPrefix}-{id} (default
     * "content-block", matching the wrapper div content blocks render into),
     * so an editor's custom CSS can only ever affect its own block and can
     * never reach the header, footer, nav, or other blocks on the page.
     * Page blocks pass "page-block" to match their own wrapper div's id.
     */
    public static function scope(?string $css, int $blockId, string $idPrefix = 'content-block'): string
    {
        if (! $css) {
            return '';
        }

        $clean = self::stripDangerousConstructs($css);
        $scopeSelector = "#{$idPrefix}-{$blockId}";

        return self::processBlock($clean, $scopeSelector);
    }

    public static function stripDangerousConstructs(string $css): string
    {
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            $css = preg_replace($pattern, '', $css) ?? $css;
        }

        return $css;
    }

    private static function processBlock(string $css, string $scopeSelector): string
    {
        $output = '';
        $length = strlen($css);
        $i = 0;

        while ($i < $length) {
            // Skip comments
            if ($css[$i] === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                $i = $end === false ? $length : $end + 2;
                continue;
            }

            $braceOrSemi = self::findNextRuleBoundary($css, $i);
            if ($braceOrSemi === null) {
                break;
            }

            [$boundaryPos, $boundaryChar] = $braceOrSemi;

            $prelude = trim(substr($css, $i, $boundaryPos - $i));

            if ($boundaryChar === ';') {
                // Stray at-rule statement with no block (e.g. leftover @import); drop it.
                $i = $boundaryPos + 1;
                continue;
            }

            // Find matching closing brace for this block.
            $blockEnd = self::findMatchingBrace($css, $boundaryPos);
            $innerCss = substr($css, $boundaryPos + 1, $blockEnd - $boundaryPos - 1);

            if (str_starts_with($prelude, '@')) {
                $atRuleName = strtolower(trim(explode(' ', substr($prelude, 1), 2)[0] ?? ''));

                if (in_array($atRuleName, self::UNSCOPED_AT_RULES, true)) {
                    // Drop unsafe/irrelevant at-rules entirely (font-face, import, etc.)
                    // but keep harmless ones like @page unscoped, without their content
                    // touching outside styles.
                    if (in_array($atRuleName, ['keyframes'], true)) {
                        $output .= $prelude . '{' . $innerCss . '}';
                    }
                } else {
                    // @media / @supports etc: keep the condition, recurse into the body.
                    $output .= $prelude . '{' . self::processBlock($innerCss, $scopeSelector) . '}';
                }
            } else {
                $scoped = self::scopeSelectorList($prelude, $scopeSelector);
                $output .= $scoped . '{' . $innerCss . '}';
            }

            $i = $blockEnd + 1;
        }

        return $output;
    }

    private static function scopeSelectorList(string $selectorList, string $scopeSelector): string
    {
        if ($selectorList === '') {
            return $scopeSelector;
        }

        $selectors = array_map('trim', explode(',', $selectorList));

        $scoped = array_map(function (string $selector) use ($scopeSelector) {
            if ($selector === '' || $selector === '&') {
                return $scopeSelector;
            }

            if (str_starts_with($selector, ':root') || str_starts_with($selector, 'html') || str_starts_with($selector, 'body')) {
                return $scopeSelector;
            }

            return $scopeSelector . ' ' . $selector;
        }, $selectors);

        return implode(', ', $scoped);
    }

    /**
     * @return array{0:int,1:string}|null [position, '{' or ';']
     */
    private static function findNextRuleBoundary(string $css, int $start): ?array
    {
        $length = strlen($css);
        for ($i = $start; $i < $length; $i++) {
            if ($css[$i] === '{') {
                return [$i, '{'];
            }
            if ($css[$i] === ';') {
                return [$i, ';'];
            }
        }

        return null;
    }

    private static function findMatchingBrace(string $css, int $openPos): int
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $openPos; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $length - 1;
    }
}
