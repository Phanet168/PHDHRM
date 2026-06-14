<?php
$path = __DIR__ . '/../public/templates/civil-servant-biography-template-v3.docx';
$zip = new ZipArchive();
$zip->open($path);
$name = null;
for ($i=0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (is_string($n) && str_replace('\\','/',$n) === 'word/document.xml') { $name = $n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
$sectionStart = strpos($xml, 'អាសយដ្ឋានអចិន្ត្រៃយ៍');
$houseMarker = strpos($xml, '<w:t>ផ្ទះលេខ</w:t>', $sectionStart);
$villageTokenPos = strpos($xml, '${current_address_prefix} ${current_village}', $houseMarker);
$segment = substr($xml, $houseMarker, $villageTokenPos - $houseMarker);
preg_match_all('/<w:t>\.+<\/w:t>/u', $segment, $matches, PREG_OFFSET_CAPTURE);
echo 'COUNT=' . count($matches[0]) . PHP_EOL;
foreach ($matches[0] as $i => $m) {
    echo $i . ':' . $m[0] . PHP_EOL;
}
