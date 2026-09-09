<?php
/** Login: POST {email, password}. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('POST');
start_session();

$body     = read_json_body();
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(422, ['error' => 'Preencha e-mail e senha.']);
}

$stmt = db()->prepare(
    'SELECT id, name, email, phone, password_hash, role,
            DATE_FORMAT(created_at, "%d/%m/%Y") AS member_since
       FROM users WHERE email = ?'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user === false || !password_verify($password, $user['password_hash'])) {
    // Mensagem genérica de propósito: não revela se o e-mail existe.
    json_response(401, ['error' => 'E-mail ou senha incorretos.']);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

json_response(200, ['user' => [
    'id'           => (int) $user['id'],
    'name'         => $user['name'],
    'email'        => $user['email'],
    'phone'        => $user['phone'],
    'role'         => $user['role'],
    'member_since' => $user['member_since'],
]]);
