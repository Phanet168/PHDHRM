<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$src = __DIR__ . '/../public/templates/civil-servant-biography-template-v3.docx';
$dst = __DIR__ . '/../public/templates/civil-servant-biography-template-v4.docx';
if (!is_file($src)) {
    fwrite(STDERR, "Missing source template: $src\n");
    exit(1);
}
if (!copy($src, $dst)) {
    fwrite(STDERR, "Copy failed\n");
    exit(1);
}
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
if ($entryName === null) {
    $zip->close();
    fwrite(STDERR, "document.xml not found\n");
    exit(1);
}
$xml = $zip->getFromName($entryName);
if (!is_string($xml) || $xml === '') {
    $zip->close();
    fwrite(STDERR, "document.xml empty\n");
    exit(1);
}
$sectionStart = mb_strpos($xml, 'អាសយដ្ឋានអចិន្ត្រៃយ៍');
$phoneIndex = mb_strpos($xml, 'លេខទូរស័ព្ទ', $sectionStart !== false ? $sectionStart : 0);
$houseMarker = mb_strpos($xml, '<w:t>ផ្ទះលេខ</w:t>', $sectionStart !== false ? $sectionStart : 0);
$villageMarker = mb_strpos($xml, '<w:t>នៅភូមិ</w:t>', $houseMarker !== false ? $houseMarker : 0);
if ($houseMarker === false || $villageMarker === false || ($phoneIndex !== false && $houseMarker > $phoneIndex)) {
    $zip->close();
    fwrite(STDERR, "Current address markers not found\n");
    exit(1);
}
$segment = mb_substr($xml, $houseMarker, $villageMarker - $houseMarker);
preg_match_all('/<w:t>\.+<\/w:t>/u', $segment, $matches, PREG_OFFSET_CAPTURE);
if (count($matches[0]) < 6) {
    $zip->close();
    fwrite(STDERR, "Expected 6 dot runs, found " . count($matches[0]) . "\n");
    exit(1);
}
$replacements = [
    0 => '<w:t>${current_house_no_inline}</w:t>',
    1 => '<w:t></w:t>',
    2 => '<w:t></w:t>',
    3 => '<w:t>${current_street_no_inline}</w:t>',
    4 => '<w:t></w:t>',
    5 => '<w:t></w:t>',
];
for ($i = 5; $i >= 0; $i--) {
    $offset = $matches[0][$i][1];
    $length = strlen($matches[0][$i][0]);
    $segment = substr_replace($segment, $replacements[$i], $offset, $length);
}
$xml = substr_replace($xml, $segment, $houseMarker, $villageMarker - $houseMarker);
$xml = str_replace('${current_address_prefix} ${current_village}', '${current_village}', $xml);
$zip->addFromString($entryName, $xml);
$zip->close();
echo "CREATED=$dst\n";
