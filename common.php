<?php
if (!isset($_REQUEST['path'])) {
    http_response_code(400);
    die("path required");
}
$path = $_REQUEST['path'];

$path = \strtr($path, '\\', '/');
$i = \strpos($path, '/', 2);
if (!$i || \strpos($path, '..') || \strpos($path, '//')) {
    http_response_code(404);
    die("Not Found");
}

$accName = \trim(\substr($path, 0, $i), '/');
$inAccPath = \substr($path, $i);

// **** CONFIGURATION BEGIN ****

//the folder in which the files will be stored:
// ( It is recommended to replace it in a absolute-path of folder outside web access )
$storagePath = \strtr(__DIR__, '\\', '/') . '/storage';
$accPath = $storagePath . '/' . $accName;

// history mod: true = on, false = off
$historyOn = true;
$historyPath = $accPath . '/history';

// Authorization occurs based on the correspondence of the public key and signature
$upubs = [
    //account name (folder in storage) => public key (ed25519-sign in base64u)
    'dynoser' => 'aOk1rVVhWoaYZzThCNWiaBMGeaQMJ_hAZT-HTGfZkKY'
];
// maximum allowed file size
$maxBodySize = 1000000;

// **** CONFIGURATION END ****


if (!isset($upubs[$accName])) {
    http_response_code(404);
    echo "Account Not Found\n";
    die;
}
$pubKeyB64 = $upubs[$accName];

$sumPath = $accPath . $inAccPath;
