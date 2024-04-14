<?php

if (!\function_exists('sodium_crypto_sign_verify_detached')) {
    require \dirname(__DIR__) . '/vendor/autoload.php';
    if (!\function_exists('sodium_crypto_sign_verify_detached')) {
        die("Function 'sodium_crypto_sign_verify_detached' not found, please enable 'sodium' ext. or install polyfill, for example 'paragonie/sodium_compat'");
    }
}
