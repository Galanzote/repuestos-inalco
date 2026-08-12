<?php
/**
 * Arranque de sesion compartido por login.php, logout.php y auth.php.
 * Cookie de sesion con httponly + secure (si hay HTTPS) + samesite estricto.
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/admin',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();
