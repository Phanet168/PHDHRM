<?php
$path = 'c:/xampp/htdocs/PHDHRM/modules/HumanResource/Http/Controllers/EmployeeRetirementController.php';
$content = file_get_contents($path);

$content = preg_replace_callback(
    "/\\$unitName = trim\\(\\(string\\) \\$unit->department_name\\);\\R\\s*if \\(\\$unitName !== ''\\) \\{\\R\\s*\\$defaults\\['unit_text'\\] = \\$unitName;\\R\\s*if \\(str_contains\\(\\$unitName, '([^']+)'\\)\\) \\{\\R\\s*\\$defaults\\['location_text'\\] = '([^']+)';\\R\\s*\\}\\R\\s*\\}/u",
    function ($m) {
        $contains = $m[1];
        $location = $m[2];
        return "\\$unitName = trim((string) \\$unit->department_name);\n"
            . "        if (\\$unitName !== '') {\n"
            . "            \\$normalizedUnitName = \\$this->normalizeRetirementUnitText(\\$unitName);\n"
            . "            \\$defaults['unit_text'] = \\$normalizedUnitName;\n"
            . "            if (str_contains(\\$normalizedUnitName, '{$contains}')) {\n"
            . "                \\$defaults['location_text'] = '{$location}';\n"
            . "            }\n"
            . "        }";
    },
    $content,
    1
);

$marker = "    protected function resolveApprovalTitleByUnitType(string \\$typeCode): string\n    {";
if (strpos($content, 'normalizeRetirementUnitText') === false) {
    $insert = "    protected function normalizeRetirementUnitText(string \\$unitName): string\n"
        . "    {\n"
        . "        \\$normalized = trim(\\$unitName);\n"
        . "        if (\\$normalized === '') {\n"
        . "            return \\$normalized;\n"
        . "        }\n\n"
        . "        if (str_starts_with(\\$normalized, 'មន្ទីរសុខាភិបាលខេត្ត')) {\n"
        . "            return 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត';\n"
        . "        }\n\n"
        . "        return \\$normalized;\n"
        . "    }\n\n"
        . $marker;

    $content = str_replace($marker, $insert, $content);
}

file_put_contents($path, $content);
echo "patched\n";
