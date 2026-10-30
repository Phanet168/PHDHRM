<?php

namespace Modules\Report\Services;

use InvalidArgumentException;

/**
 * Evaluates the small formula language used by import mappings (ported from PHDReport):
 *
 *   HC("Sheet!A1")  HOSP("Sheet!B2:B9")   value (or range sum) read from the HC / HOSP workbook
 *   HC  HOSP                                the already-resolved HC / HOSP value of the indicator
 *   NUM(x)  SUM(..)  AVG(..)  MIN(..)  MAX(..)  ROUND(x, d)
 *   numbers, + - * /, parentheses
 *
 * Missing values count as 0 inside an expression; division by zero yields null.
 * Parsed with a recursive-descent parser — nothing is passed to eval().
 */
class MappingFormula
{
    private array $tokens = [];
    private int $pos = 0;

    /**
     * @param  callable(string $source, string $ref): ?float  $reader  reads "Sheet!A1" from source "HC" or "HOSP"
     */
    public function __construct(private $reader = null)
    {
    }

    public function evaluate(?string $expression, ?float $hc = null, ?float $hosp = null): ?float
    {
        $expression = trim((string) $expression);
        if ($expression === '') {
            return ($hc === null && $hosp === null) ? null : ($hc ?? 0) + ($hosp ?? 0);
        }

        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;
        $result = $this->parseExpression($hc, $hosp);
        if ($this->peek() !== null) {
            throw new InvalidArgumentException('Unexpected "' . $this->peek()['v'] . '" in formula');
        }

        return ($result === null || !is_finite($result)) ? null : round($result, 6);
    }

    /** Parse a value the way the Excel cells are written: Khmer digits, thousands separators, %, (negative). */
    public static function toNumber(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || is_bool($raw)) {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return is_finite((float) $raw) ? (float) $raw : null;
        }

        $s = strtr((string) $raw, ['០' => '0', '១' => '1', '២' => '2', '៣' => '3', '៤' => '4', '៥' => '5', '៦' => '6', '៧' => '7', '៨' => '8', '៩' => '9']);
        $s = trim(preg_replace('/[\x{00A0}\x{200B}\x{202F}\x{2009}\x{2007}]/u', ' ', $s));
        $percent = str_ends_with($s, '%');
        $negative = (bool) preg_match('/^\(.*\)$/', $s);
        $s = trim($s, "%() ");

        if (!preg_match('/-?\d{1,3}(?:[ ,.]\d{3})+(?:[.,]\d+)?|-?\d+(?:[.,]\d+)?/', $s, $m)) {
            return null;
        }
        $n = $m[0];
        if (str_contains($n, ',') && str_contains($n, '.')) {
            $n = preg_match('/,\d{1,2}$/', $n) ? str_replace(',', '.', str_replace('.', '', $n)) : str_replace(',', '', $n);
        } elseif (str_contains($n, ',')) {
            $n = str_replace(',', '', $n);
        } elseif (substr_count($n, '.') > 1 || preg_match('/\.\d{3}$/', $n)) {
            $n = str_replace('.', '', $n);
        }
        $value = (float) str_replace(' ', '', $n);
        if ($percent) {
            $value /= 100;
        }

        return $negative ? -$value : $value;
    }

    private function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        for ($i = 0; $i < $len;) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;
            } elseif (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                preg_match('/\d*\.?\d+(?:[eE][-+]?\d+)?/A', $s, $m, 0, $i);
                $tokens[] = ['t' => 'num', 'v' => (float) $m[0]];
                $i += strlen($m[0]);
            } elseif (ctype_alpha($c) || $c === '_') {
                preg_match('/[A-Za-z_][A-Za-z0-9_]*/A', $s, $m, 0, $i);
                $tokens[] = ['t' => 'id', 'v' => strtoupper($m[0])];
                $i += strlen($m[0]);
            } elseif ($c === '"' || $c === "'") {
                $end = strpos($s, $c, $i + 1);
                if ($end === false) {
                    throw new InvalidArgumentException('Unclosed string in formula');
                }
                $tokens[] = ['t' => 'str', 'v' => substr($s, $i + 1, $end - $i - 1)];
                $i = $end + 1;
            } elseif (str_contains('+-*/(),', $c)) {
                $tokens[] = ['t' => $c, 'v' => $c];
                $i++;
            } else {
                throw new InvalidArgumentException("Unexpected character \"{$c}\" in formula");
            }
        }

        return $tokens;
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function accept(string $type): bool
    {
        if (($this->tokens[$this->pos]['t'] ?? null) === $type) {
            $this->pos++;

            return true;
        }

        return false;
    }

    private function expect(string $type): array
    {
        $token = $this->peek();
        if (($token['t'] ?? null) !== $type) {
            throw new InvalidArgumentException("Expected \"{$type}\" in formula");
        }
        $this->pos++;

        return $token;
    }

    private function parseExpression(?float $hc, ?float $hosp): ?float
    {
        $acc = $this->parseTerm($hc, $hosp);
        while (in_array($this->peek()['t'] ?? null, ['+', '-'], true)) {
            $op = $this->tokens[$this->pos++]['t'];
            $rhs = $this->parseTerm($hc, $hosp);
            $acc = $op === '+' ? ($acc ?? 0) + ($rhs ?? 0) : ($acc ?? 0) - ($rhs ?? 0);
        }

        return $acc;
    }

    private function parseTerm(?float $hc, ?float $hosp): ?float
    {
        $acc = $this->parseFactor($hc, $hosp);
        while (in_array($this->peek()['t'] ?? null, ['*', '/'], true)) {
            $op = $this->tokens[$this->pos++]['t'];
            $rhs = $this->parseFactor($hc, $hosp) ?? 0;
            if ($op === '*') {
                $acc = ($acc ?? 0) * $rhs;
            } else {
                $acc = $rhs == 0 ? null : ($acc ?? 0) / $rhs;
            }
        }

        return $acc;
    }

    private function parseFactor(?float $hc, ?float $hosp): ?float
    {
        if ($this->accept('-')) {
            return -($this->parseFactor($hc, $hosp) ?? 0);
        }
        if ($this->accept('+')) {
            return $this->parseFactor($hc, $hosp);
        }
        if ($this->accept('(')) {
            $v = $this->parseExpression($hc, $hosp);
            $this->expect(')');

            return $v;
        }

        $token = $this->peek();
        if ($token === null) {
            throw new InvalidArgumentException('Formula ends unexpectedly');
        }
        $this->pos++;

        if ($token['t'] === 'num') {
            return $token['v'];
        }
        if ($token['t'] === 'str') {
            return self::toNumber($token['v']);
        }
        if ($token['t'] !== 'id') {
            throw new InvalidArgumentException("Unexpected \"{$token['v']}\" in formula");
        }

        $name = $token['v'];
        if (!$this->accept('(')) {
            return match ($name) {
                'HC' => $hc,
                'HOSP' => $hosp,
                default => throw new InvalidArgumentException("Unknown name \"{$name}\" in formula"),
            };
        }

        if ($name === 'HC' || $name === 'HOSP') {
            $ref = $this->expect('str')['v'];
            $this->expect(')');

            return $this->reader ? ($this->reader)($name, $ref) : null;
        }

        $args = [];
        if (!$this->accept(')')) {
            do {
                $args[] = $this->parseExpression($hc, $hosp);
            } while ($this->accept(','));
            $this->expect(')');
        }
        $nums = array_map(fn ($a) => $a ?? 0.0, $args);

        return match ($name) {
            'NUM' => $nums[0] ?? 0.0,
            'SUM' => array_sum($nums),
            'AVG' => $nums ? array_sum($nums) / count($nums) : 0.0,
            'MIN' => $nums ? min($nums) : 0.0,
            'MAX' => $nums ? max($nums) : 0.0,
            'ROUND' => round($nums[0] ?? 0.0, max(0, (int) ($nums[1] ?? 0))),
            default => throw new InvalidArgumentException("Unknown function \"{$name}\" in formula"),
        };
    }
}
