<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$src = __DIR__ . '/../public/templates/civil-servant-biography-template-v3.docx';
$dst = __DIR__ . '/../public/templates/civil-servant-biography-template-v4.docx';
if (!copy($src, $dst)) {
    fwrite(STDERR, "COPY_FAILED\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($dst) !== true) {
    fwrite(STDERR, "OPEN_FAILED\n");
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
    fwrite(STDERR, "DOC_XML_NOT_FOUND\n");
    exit(1);
}
$xml = $zip->getFromName($entryName);
$sectionStart = strpos($xml, 'អាសយដ្ឋានអចិន្ត្រៃយ៍');
$houseMarker = strpos($xml, '<w:t>ផ្ទះលេខ</w:t>', $sectionStart);
$villageToken = '${current_address_prefix} ${current_village}';
$villageTokenPos = strpos($xml, $villageToken, $houseMarker);
if ($sectionStart === false || $houseMarker === false || $villageTokenPos === false) {
    fwrite(STDERR, "MARKERS_NOT_FOUND\n");
    exit(1);
}
$segment = substr($xml, $houseMarker, $villageTokenPos - $houseMarker);
preg_match_all('/<w:t>\.+<\/w:t>/u', $segment, $matches, PREG_OFFSET_CAPTURE);
if (count($matches[0]) !== 6) {
    fwrite(STDERR, 'DOT_COUNT=' . count($matches[0]) . "\n");
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
$xml = substr_replace($xml, $segment, $houseMarker, $villageTokenPos - $houseMarker);
$xml = str_replace($villageToken, '${current_village}', $xml);
$zip->addFromString($entryName, $xml);
$zip->close();

$verify = new ZipArchive();
$verify->open($dst);
$verifyXml = $verify->getFromName($entryName);
$verify->close();
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$ok = $doc->loadXML($verifyXml);
echo $ok ? "V4_XML_OK\n" : "V4_XML_BAD\n";
if (!$ok) {
    foreach (libxml_get_errors() as $err) {
        echo trim($err->message) . "\n";
    }
    exit(1);
}
