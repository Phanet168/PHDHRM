<?php
$file = __DIR__ . '/../modules/HumanResource/Http/Requests/EmployeeCreateRequest.php';
$lines = file($file, FILE_IGNORE_NEW_LINES);
foreach ($lines as $idx => $line) {
    if (strpos($line, "if ($salutation !== '' && in_array($memberGender, ['male', 'female'], true))") !== false) {
        echo "MATCH at line " . ($idx + 1) . PHP_EOL;
    }
}
?>
