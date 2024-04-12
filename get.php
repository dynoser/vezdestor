<?php

require_once 'common.php';

$absPath = \realpath($sumPath);
if (!$absPath) {
    http_response_code(404);
    die("Not Found file in account $accName");
}

Header("Content-type: text/plain");
readfile($absPath);
