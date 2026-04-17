<?php
$html = file_get_contents(__DIR__ . '/department_rendered.html');
if ($html === false) { echo "NO_HTML\n"; exit(1); }

preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $matches, PREG_SET_ORDER);
$idx = 0;
foreach ($matches as $m) {
    $attrs = $m[1] ?? '';
    $body = $m[2] ?? '';
    if (stripos($attrs, 'src=') !== false) {
        continue;
    }
    $idx++;
    $file = __DIR__ . '/inline_script_' . $idx . '.js';
    file_put_contents($file, $body);
    echo basename($file) . "\n";
}
