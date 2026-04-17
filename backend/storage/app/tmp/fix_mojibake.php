<?php

$files = [
    'modules/HumanResource/Exports/RetirementListExport.php',
    'modules/HumanResource/Http/Controllers/EmployeeRetirementController.php',
];

$pattern = '/([\x{00A0}-\x{024F}\x{2010}-\x{203A}]{2,})/u';

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        echo "skip {$file}\n";
        continue;
    }

    $count = 0;
    $fixed = preg_replace_callback($pattern, function ($m) use (&$count) {
        $chunk = $m[1];

        $bytes = @iconv('UTF-8', 'Windows-1252//IGNORE', $chunk);
        if ($bytes === false || $bytes === '') {
            return $chunk;
        }

        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return $chunk;
        }

        $decoded = $bytes;
        if (!preg_match('/\p{Khmer}/u', $decoded)) {
            return $chunk;
        }

        $count++;
        return $decoded;
    }, $content);

    if (!is_string($fixed)) {
        echo "failed {$file}\n";
        continue;
    }

    file_put_contents($file, $fixed);
    echo "fixed {$file}: {$count} chunk(s)\n";
}

