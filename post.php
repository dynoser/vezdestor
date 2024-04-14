<?php

require 'common.php';
require_once 'src/vezdes.php';

if (!\array_key_exists('body', $_REQUEST)) {
    http_response_code(400);
    die("body parameter required");
}

if (isset($_REQUEST['signature'])) {
    $signature = \dynoser\vezdes\VezdesHeader::base64Udecode($_REQUEST['signature']);
    if (\strlen($signature) !== 64) {
        http_response_code(403);
        die("Bad signature parameter format");
    }
    $bodyStr = $_REQUEST['body'];
    $docHashBin = \dynoser\vezdes\VezdesParser::hash512032($bodyStr);
    $pubKeyBin = \dynoser\vezdes\VezdesHeader::base64Udecode($pubKeyB64);
    if (!\dynoser\vezdes\VezdesParser::signVerifyDetached($signature, $docHashBin, $pubKeyBin)) {
        http_response_code(403);
        die("VEZDES Signature does not match the document");
    }
} else {

    $b = new \dynoser\vezdes\VezdesParser(null, $maxBodySize);

    $b->calcHeaderPosition();

    $pubKeyBin = \dynoser\vezdes\VezdesHeader::base64Udecode($pubKeyB64);
    if (!$b->checkSignature(null, null, $pubKeyBin)) {
        http_response_code(403);
        die("VEZDES Signature does not match the document");
    }
    $bodyStr = $b->bodyStr;
}

if (\is_file($sumPath)) {
    $oldVersion = \file_get_contents($sumPath);
    if ($oldVersion === $bodyStr) {
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

$wb = \file_put_contents($sumPath, $bodyStr);
if ($wb !== $b->bodyLen) {
    http_response_code(500);
    die("Write error file $inAccPath for account $accName");
}
http_response_code(200);
echo '{"result": "Successful writed '. $wb.' bytes"}';
