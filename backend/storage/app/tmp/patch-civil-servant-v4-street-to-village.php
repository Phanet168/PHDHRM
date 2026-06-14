<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$dst = __DIR__ . '/../public/templates/civil-servant-biography-template-v4.docx';
$zip = new ZipArchive();
if ($zip->open($dst) !== true) {
    fwrite(STDERR, "Open zip failed\n");
    exit(1);
}
$entryName = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (is_string($name) && str_replace('\\', '/', $name) === 'word/document.xml') {
        $entryName = $name;
        break;
    }
}
$xml = $zip->getFromName($entryName);
$streetPos = mb_strpos($xml, '${current_street_no_inline}');
$villagePos = $streetPos !== false ? mb_strpos($xml, '${current_village}', $streetPos) : false;
if ($streetPos === false || $villagePos === false) {
    $zip->close();
    fwrite(STDERR, "Street/current_village markers not found\n");
    exit(1);
}
$segment = mb_substr($xml, $streetPos, $villagePos - $streetPos);
$segment = preg_replace('/<w:t>\.+<\/w:t>/u', '<w:t></w:t>', $segment) ?? $segment;
$xml = substr_replace($xml, $segment, $streetPos, $villagePos - $streetPos);
$zip->addFromString($entryName, $xml);
$zip->close();
echo "PATCHED_V4_STREET_TO_VILLAGE\n";
