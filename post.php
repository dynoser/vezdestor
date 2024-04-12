<?php

require_once 'src/vezdes.php';
require 'common.php';

$data = null;
$b = new dynoser\vezdes\VezdesParser($data, $maxBodySize);

$headerPos = $b->getHeaderPosition();

if (!$b->checkSignature()) {
    http_response_code(403);
    die("VEZDES Signature does not match the document");
}

if (is_file($sumPath)) {
    $oldVersion = \file_get_contents($sumPath);
    if ($oldVersion === $b->bodyStr) {
        http_response_code(200); // or 204
        echo '{"result": "Not modified"}';
        die;
    }
} else {
    $accBasePath = \realpath($accPath);
    if (!$accBasePath) {
        if (\mkdir($accPath, 0777, true)) {
            $accBasePath = \realpath($accPath);
        } else {
            http_response_code(404);
            die("Not found account path for account $accName , can't create");
        }
    }
    $fullPath = \strtr($accBasePath, '\\', '/') . $inAccPath;
    $subDirs = \dirname($fullPath);
    $haveSubDirs = \strlen($subDirs)>\strlen($accBasePath);
    if ($haveSubDirs && !\is_dir($subDirs) && !\mkdir($subDirs, 0777, true)) {
        http_response_code(404);
        die("Not found account sub-dirs for account $accName");
    }
}

$wb = \file_put_contents($sumPath, $b->bodyStr);
if ($wb !== $b->bodyLen) {
    http_response_code(500);
    die("Write error file $inAccPath for account $accName");
}
http_response_code(200);
echo '{"result": "Successful writed '. $wb.' bytes"}';
