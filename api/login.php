<?php
/** Login: POST {email, password, remember}. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/tentativas.php';

require_method('POST');
start_session();

$body     = read_json_body();
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(422, ['error' => 'Preencha e-mail e senha.']);
}

$pdo = db();

// Senhas erradas demais para este e-mail? Nem chega a conferir a senha.
$espera = tentativas_bloqueio($pdo, $email);
if ($espera !== null) {
    header('Retry-After: ' . ($espera * 60));
    json_response(429, ['error' => 'Muitas tentativas com este e-mail. Por segurança, espere '
        . minutos_texto($espera) . ' e tente de novo.']);
}

$stmt = $pdo->prepare(
    'SELECT id, name, email, phone, password_hash, role,
            DATE_FORMAT(created_at, "%d/%m/%Y") AS member_since
       FROM users WHERE email = ?'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// E-mail sem conta confere a senha contra um hash qualquer: assim o tempo de
// resposta não denuncia quem é e quem não é cliente.
$hash = $user !== false ? $user['password_hash'] : '$2y$10$cXPqtk2uHaiOlDs3SROfw.npJasPUYBAjETXHPJC8dpgSuQ2igeaa';
if (!password_verify($password, $hash) || $user === false) {
    $restam = tentativas_falhou($pdo, $email);
    if ($restam === 0) {
        $espera = tentativas_bloqueio($pdo, $email) ?? TENTATIVAS_JANELA;
        header('Retry-After: ' . ($espera * 60));
        json_response(429, ['error' => 'E-mail ou senha incorretos ' . TENTATIVAS_MAX . ' vezes. Por segurança, '
            . 'este e-mail fica bloqueado por ' . minutos_texto($espera) . '.']);
    }
    // Mensagem genérica de propósito: não revela se o e-mail existe.
    $aviso = $restam <= 2
        ? ' Mais ' . ($restam === 1 ? '1 tentativa errada' : $restam . ' tentativas erradas')
          . ' e o acesso fica bloqueado por ' . TENTATIVAS_JANELA . ' minutos.'
        : '';
    json_response(401, ['error' => 'E-mail ou senha incorretos.' . $aviso]);
}

tentativas_zerar($pdo, $email);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

// "Manter conectado" marcado: este aparelho fica logado por 30 dias
if (!empty($body['remember'])) {
    lembrar_emitir((int) $user['id']);
}

json_response(200, ['user' => [
    'id'           => (int) $user['id'],
    'name'         => $user['name'],
    'email'        => $user['email'],
    'phone'        => $user['phone'],
    'role'         => $user['role'],
    'member_since' => $user['member_since'],
]]);
