<?php
$base = getcwd();
$files = [
    'modules/HumanResource/Resources/views/employee/employee_pdf.blade.php',
    'modules/HumanResource/Resources/views/employee/employee_detail_pdf.blade.php',
];

foreach ($files as $file) {
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        echo "Skip (not found): $file\n";
        continue;
    }

    $original = file_get_contents($path);
    if ($original === false) {
        echo "Skip (read fail): $file\n";
        continue;
    }

    $lines = preg_split('/\R/u', $original);
    if (!is_array($lines)) {
        echo "Skip (split fail): $file\n";
        continue;
    }

    $changed = 0;
    foreach ($lines as $idx => $line) {
        if ($line === '') {
            continue;
        }

        $hasKhmer = (bool) preg_match('/\p{Khmer}/u', $line);
        $looksMojibake = (bool) preg_match('/[ÃÂÅáâ]/u', $line);

        if ($hasKhmer || !$looksMojibake) {
            continue;
        }

        $decoded = @iconv('UTF-8', 'Windows-1252//IGNORE', $line);
        if (!is_string($decoded) || $decoded === '') {
            continue;
        }

        if ((bool) preg_match('/\p{Khmer}/u', $decoded)) {
            $lines[$idx] = $decoded;
            $changed++;
        }
    }

    if ($changed > 0) {
        $new = implode(PHP_EOL, $lines);
        file_put_contents($path, $new);
        echo "Updated $file : $changed lines\n";
    } else {
        echo "No mojibake lines fixed: $file\n";
    }
}
