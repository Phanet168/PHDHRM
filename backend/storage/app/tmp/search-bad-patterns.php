<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307.docx';
$zip->open($path);
$name = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name = $n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
$patterns = ['<w:sz w:val="22"<w:t>', '<w:lang w:val="ca-ES" w:bidi="km-KH"/></w:rPr><w:t>', '</w:r ???', '<w:t>..</w:t><w:r'];
foreach ($patterns as $pattern) {
    $pos = strpos($xml, $pattern);
    echo $pattern . ' => ' . ($pos === false ? 'NO' : $pos) . PHP_EOL;
}
