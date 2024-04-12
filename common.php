<?php
$path = $_REQUEST['path'] ?? die("path required");

$path = \strtr($path, '\\', '/');
$i = \strpos($path, '/', 2);
if (!$i || \strpos($path, '..') || \strpos($path, '//')) {
    http_response_code(404);
    die("Not Found");
}

$accName = \trim(\substr($path, 0, $i), '/');
$inAccPath = \substr($path, $i);

//the folder in which the files will be stored
$storagePath = __DIR__ . '/storage';
$accPath = $storagePath . '/' . $accName;

$upubs = [
    //account name (folder in storage) => public key
    'dynoser' => 'aOk1rVVhWoaYZzThCNWiaBMGeaQMJ_hAZT-HTGfZkKY'
];
// maximum allowed file size
$maxBodySize = 1000000;

if (!isset($upubs[$accName])) {
    http_response_code(404);
    die("Account Not Found");
}

$sumPath = $accPath . $inAccPath;
