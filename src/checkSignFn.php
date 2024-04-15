<?php

if (!\function_exists('sodium_crypto_sign_verify_detached')) {
    require \dirname(__DIR__) . '/vendor/autoload.php';
    if (!\function_exists('sodium_crypto_sign_verify_detached')) {
        http_response_code(500);
        echo "Function 'sodium_crypto_sign_verify_detached' not found, please enable extension = sodium or install polyfill (for example, composer require paragonie/sodium_compat)\n";
        die;
    }
}
