<?php
$root = dirname(__DIR__, 3);
$path = $root . '/modules/HumanResource/Exports/RetirementListExport.php';
$content = file_get_contents($path);

$hasMojibake = static function (string $s): bool {
    return (bool) preg_match('/Ã|Å|áŸ|áž/u', $s);
};

$hasKhmer = static function (string $s): bool {
    return (bool) preg_match('/[\x{1780}-\x{17FF}]/u', $s);
};

$fixed = preg_replace_callback("/'((?:\\\\'|[^'])*)'/u", function ($m) use ($hasMojibake, $hasKhmer) {
    $original = $m[1];
    if (!$hasMojibake($original)) {
        return $m[0];
    }

    $candidate = $original;
    for ($i = 0; $i < 3; $i++) {
        $next = @mb_convert_encoding($candidate, 'Windows-1252', 'UTF-8');
        if (!is_string($next) || $next === '') {
            break;
        }

        $candidate = $next;

        if ($hasKhmer($candidate)) {
            break;
        }

        if (!$hasMojibake($candidate)) {
            break;
        }
    }

    $candidate = str_replace("'", "\\'", $candidate);

    return "'" . $candidate . "'";
}, $content);

file_put_contents($path, $fixed);
echo "converted\n";
