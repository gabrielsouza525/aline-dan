<?php
/** Login: POST {email, password, remember}. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/tentativas.php';

require_method('POST');
start_session();

$body     = read_json_body();
$email    = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(422, ['error' => 'Preencha e-mail e senha.']);
}

$pdo  = db();
$stmt = $pdo->prepare(
    'SELECT id, name, email, phone, password_hash, role,
            DATE_FORMAT(created_at, "%d/%m/%Y") AS member_since
       FROM users WHERE email = ?'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// Aparelho onde esta conta já entrou conta as tentativas separado: quem só
// sabe o e-mail não consegue travar a dona fora dos aparelhos dela.
$conhecido = $user !== false && dispositivo_conhecido((int) $user['id'], $user['password_hash']);
$chave     = login_chave($email, $conhecido);
$janela    = login_janela($pdo, $chave);

// Senhas erradas demais? Nem chega a conferir a senha. (Vale igual para
// e-mail com e sem conta: a resposta não denuncia quem é cliente.)
$espera = limite_espera($pdo, $chave, TENTATIVAS_MAX, $janela);
if ($espera !== null) {
    header('Retry-After: ' . ($espera * 60));
    json_response(429, ['error' => 'Muitas tentativas com este e-mail. Por segurança, espere '
        . minutos_texto($espera) . ' e tente de novo.']);
}

// E-mail sem conta confere a senha contra um hash qualquer: assim o tempo de
// resposta não denuncia quem é e quem não é cliente.
$hash = $user !== false ? $user['password_hash'] : '$2y$10$cXPqtk2uHaiOlDs3SROfw.npJasPUYBAjETXHPJC8dpgSuQ2igeaa';
if (!password_verify($password, $hash) || $user === false) {
    limite_registrar($pdo, $chave);
    // Erro vindo de aparelho conhecido também pesa na conta: um aparelho
    // roubado não vira atalho para chutar senhas sem limite.
    if ($conhecido) {
        limite_registrar($pdo, login_chave($email, false));
    }
    $janela = login_janela($pdo, $chave);
    $restam = TENTATIVAS_MAX - limite_contar($pdo, $chave, $janela);
    if ($restam <= 0) {
        $espera = limite_espera($pdo, $chave, TENTATIVAS_MAX, $janela) ?? $janela;
        header('Retry-After: ' . ($espera * 60));
        json_response(429, ['error' => 'E-mail ou senha incorretos ' . TENTATIVAS_MAX . ' vezes. Por segurança, '
            . 'este e-mail fica bloqueado por ' . minutos_texto($espera) . '.']);
    }
    // Mensagem genérica de propósito: não revela se o e-mail existe.
    $aviso = $restam <= 2
        ? ' Mais ' . ($restam === 1 ? '1 tentativa errada' : $restam . ' tentativas erradas')
          . ' e o acesso fica bloqueado por ' . minutos_texto($janela) . '.'
        : '';
    json_response(401, ['error' => 'E-mail ou senha incorretos.' . $aviso]);
}

limite_zerar($pdo, $chave);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
sessao_marcar($user['password_hash']);          // lembrar.php: cai se a senha mudar
dispositivo_marcar((int) $user['id'], $user['password_hash']);

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
