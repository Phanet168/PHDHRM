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
$sectionStart = mb_strpos($xml, 'អាសយដ្ឋានអចិន្ត្រៃយ៍');
$houseMarker = mb_strpos($xml, '<w:t>ផ្ទះលេខ</w:t>', $sectionStart !== false ? $sectionStart : 0);
$villageMarker = mb_strpos($xml, '<w:t>នៅភូមិ</w:t>', $houseMarker !== false ? $houseMarker : 0);
$segment = mb_substr($xml, $houseMarker, $villageMarker - $houseMarker);
$segment = preg_replace('/<w:t>\.+<\/w:t>/u', '<w:t></w:t>', $segment) ?? $segment;
$xml = substr_replace($xml, $segment, $houseMarker, $villageMarker - $houseMarker);
$zip->addFromString($entryName, $xml);
$zip->close();
echo "PATCHED_V4\n";
