<?php
/** Troca de senha: POST {current_password, new_password}. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('POST');
$user = require_user();

$body    = read_json_body();
$current = (string) ($body['current_password'] ?? '');
$new     = (string) ($body['new_password'] ?? '');

if (strlen($new) < 6) {
    json_response(422, ['error' => 'A nova senha precisa ter pelo menos 6 caracteres.', 'field' => 'new_password']);
}

$stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

if ($row === false || !password_verify($current, $row['password_hash'])) {
    json_response(401, ['error' => 'A senha atual não confere.', 'field' => 'current_password']);
}

$stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

session_regenerate_id(true);

json_response(200, ['ok' => true]);
