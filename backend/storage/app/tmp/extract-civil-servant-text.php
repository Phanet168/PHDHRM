<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$path = __DIR__ . '/debug-civil-servant-023307.docx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) { echo "ZIP_BAD\n"; exit; }
$name = null;
for ($i=0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (is_string($n) && str_replace('\\','/',$n) === 'word/document.xml') { $name = $n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
libxml_use_internal_errors(true);
$doc = new DOMDocument();
if (!$doc->loadXML($xml)) {
    echo "XML_BAD\n";
    foreach (libxml_get_errors() as $err) { echo trim($err->message) . "\n"; }
    exit;
}
$text = '';
foreach ($doc->getElementsByTagName('t') as $node) {
    $text .= $node->textContent;
}
$pos = mb_strpos($text, 'អាសយដ្ឋានអចិន្ត្រៃយ៍បច្ចុប្បន្ន');
if ($pos === false) { $pos = mb_strpos($text, 'ផ្ទះលេខ'); }
if ($pos === false) { echo "TEXT_NOT_FOUND\n"; exit; }
echo mb_substr($text, $pos, 220) . "\n";
