<?php
/** Logout: POST, encerra a sessão. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('POST');
start_session();
lembrar_esquecer();   // sair é sair: o "manter conectado" deste aparelho vai junto

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

json_response(200, ['ok' => true]);
