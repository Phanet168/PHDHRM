<?php
$file = 'modules/HumanResource/Resources/views/employee/pay-promotion/index.blade.php';
$content = file_get_contents($file);
if ($content === false) { fwrite(STDERR, "Cannot read file\n"); exit(1); }

$decode = function(string $s): string {
    if (!preg_match('/(áž|áŸ|Ã|Â|â|Å)/u', $s)) {
        return $s;
    }

    $candidate = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    if (is_string($candidate) && $candidate !== '' && preg_match('/\p{Khmer}/u', $candidate)) {
        return $candidate;
    }

    $candidate2 = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
    if (is_string($candidate2) && $candidate2 !== '' && preg_match('/\p{Khmer}/u', $candidate2)) {
        return $candidate2;
    }

    return $s;
};

$patterns = [
    "/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/u",
    '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/u',
];

$replaced = 0;
foreach ($patterns as $pattern) {
    $content = preg_replace_callback($pattern, function($m) use ($decode, &$replaced) {
        $full = $m[0];
        $body = $m[1];
        $fixed = $decode($body);
        if ($fixed !== $body) {
            $replaced++;
            $quote = $full[0];
            $escaped = str_replace(['\\', $quote], ['\\\\', '\\'.$quote], $fixed);
            return $quote . $escaped . $quote;
        }
        return $full;
    }, $content);
}

file_put_contents($file, $content);
echo "replaced=$replaced\n";
