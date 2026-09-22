<?php
/** Cadastro de cliente: POST {name, email, phone, password}. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';
require_once __DIR__ . '/tentativas.php';

require_method('POST');
start_session();

$body     = read_json_body();
$name     = trim((string) ($body['name'] ?? ''));
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$phone    = trim((string) ($body['phone'] ?? ''));
$password = (string) ($body['password'] ?? '');

$digits = preg_replace('/\D/', '', $phone);

// Os máximos são os tamanhos das colunas: acima deles o MySQL recusa a
// gravação e a pessoa receberia um erro 500 em vez de uma explicação.
if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
    json_response(422, ['error' => 'Informe seu nome completo (entre 3 e 120 letras).', 'field' => 'name']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    json_response(422, ['error' => 'Informe um e-mail válido.', 'field' => 'email']);
}
if (strlen($digits) < 10 || strlen($digits) > 11 || strlen($phone) > 20) {
    json_response(422, ['error' => 'Informe um telefone válido com DDD.', 'field' => 'phone']);
}
if (strlen($password) < 6) {
    json_response(422, ['error' => 'A senha precisa ter pelo menos 6 caracteres.', 'field' => 'password']);
}

$pdo = db();
tokens_prontos($pdo); // a tabela pode não existir em bancos antigos (lembrar.php)

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch() !== false) {
    json_response(409, ['error' => 'Já existe uma conta com esse e-mail. Tente entrar.', 'field' => 'email']);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)')
        ->execute([$name, $email, $phone, $hash]);
} catch (PDOException $e) {
    // Dois cadastros com o mesmo e-mail ao mesmo tempo: os dois passam pela
    // checagem acima e o segundo esbarra no índice único da coluna.
    if ((string) $e->getCode() === '23000') {
        json_response(409, ['error' => 'Já existe uma conta com esse e-mail. Tente entrar.', 'field' => 'email']);
    }
    throw $e;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $pdo->lastInsertId();
sessao_marcar($hash);
dispositivo_marcar($_SESSION['user_id'], $hash);
if (!empty($body['remember'])) {
    lembrar_emitir($_SESSION['user_id']);
}

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
