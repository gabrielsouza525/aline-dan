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

// Máximos = tamanhos das colunas: acima deles o MySQL recusa e viraria erro 500
if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
    json_response(422, ['error' => 'Informe seu nome completo (entre 3 e 120 letras).', 'field' => 'name']);
}
if (strlen($digits) < 10 || strlen($digits) > 11 || strlen($phone) > 20) {
    json_response(422, ['error' => 'Informe um telefone válido com DDD.', 'field' => 'phone']);
}

$stmt = db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
$stmt->execute([$name, $phone, $user['id']]);

json_response(200, ['user' => current_user()]);
