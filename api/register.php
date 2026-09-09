<?php
/** Cadastro de cliente: POST {name, email, phone, password}. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

require_method('POST');
start_session();

$body     = read_json_body();
$name     = trim((string) ($body['name'] ?? ''));
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$phone    = trim((string) ($body['phone'] ?? ''));
$password = (string) ($body['password'] ?? '');

$digits = preg_replace('/\D/', '', $phone);

if (mb_strlen($name) < 3) {
    json_response(422, ['error' => 'Informe seu nome completo (mínimo 3 letras).', 'field' => 'name']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(422, ['error' => 'Informe um e-mail válido.', 'field' => 'email']);
}
if (strlen($digits) < 10 || strlen($digits) > 11) {
    json_response(422, ['error' => 'Informe um telefone válido com DDD.', 'field' => 'phone']);
}
if (strlen($password) < 6) {
    json_response(422, ['error' => 'A senha precisa ter pelo menos 6 caracteres.', 'field' => 'password']);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch() !== false) {
    json_response(409, ['error' => 'Já existe uma conta com esse e-mail. Tente entrar.', 'field' => 'email']);
}

$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $pdo->lastInsertId();

// E-mail de confirmação (a conta funciona mesmo antes de confirmar)
$token = bin2hex(random_bytes(32));
$pdo->prepare(
    'INSERT INTO user_tokens (user_id, kind, token_hash, expires_at)
     VALUES (?, "verify", ?, DATE_ADD(NOW(), INTERVAL 2 DAY))'
)->execute([$_SESSION['user_id'], hash('sha256', $token)]);
send_app_mail(
    $email,
    'Confirme seu e-mail — Aline Dan',
    '<p>Olá, <strong>' . htmlspecialchars($name) . '</strong>! Que bom ter você aqui. 🌸</p>'
    . '<p>Toque no botão abaixo para confirmar o seu e-mail (o link vale por 2 dias):</p>'
    . '<p><a href="' . BASE_URL . 'api/verify_email.php?token=' . $token
    . '" style="display:inline-block;background:#DB2777;color:#fff;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:bold;">Confirmar e-mail</a></p>'
);

json_response(201, ['user' => [
    'id'           => $_SESSION['user_id'],
    'name'         => $name,
    'email'        => $email,
    'phone'        => $phone,
    'role'         => 'client',
    'member_since' => date('d/m/Y'),
]]);
