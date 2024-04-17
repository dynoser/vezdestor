<?php

// $sumPath - path of file
// $oldDataStr - previous data of file content for save to history

// $historyPath - base path for history files
$historyLogFile = $historyPath . $inAccPath . '.log';
$historyDbFile = $historyPath . $inAccPath . '.sqlite3';
$histFileExist = \is_file($historyDbFile);
// check history path (if need)
if (!$histFileExist) {
    $historyFolder = \dirname($historyLogFile);
    if (!\is_dir($historyFolder) && !\mkdir($historyFolder, 0777, true)) {
        http_response_code(500);
        echo "Can't create history folder: " . $historyFolder . "\n";
        die;
    }
}

require 'HashHistorySQLite3.php';

$db = new \dynoser\vezdes\HashHistorySQLite3($historyDbFile);

if (!$histFileExist) {
    $db->initStorage();
}
$dtm = time();
$hashHex = $db->saveContent($oldDataStr, '', $dtm);
if ($hashHex) {
    $st = $hashHex . ',' . $dtm . "\n";
    file_put_contents($historyLogFile, $st, \FILE_APPEND);
}