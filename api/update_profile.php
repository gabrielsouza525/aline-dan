<?php
/** Atualiza nome e telefone do usuário logado: POST {name, phone}. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('POST');
$user = require_user();

$body  = read_json_body();
$name  = trim((string) ($body['name'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));

$digits = preg_replace('/\D/', '', $phone);

if (mb_strlen($name) < 3) {
    json_response(422, ['error' => 'Informe seu nome completo (mínimo 3 letras).', 'field' => 'name']);
}
if (strlen($digits) < 10 || strlen($digits) > 11) {
    json_response(422, ['error' => 'Informe um telefone válido com DDD.', 'field' => 'phone']);
}

$stmt = db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
$stmt->execute([$name, $phone, $user['id']]);

json_response(200, ['user' => current_user()]);
