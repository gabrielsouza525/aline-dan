<?php
/** Troca de senha: POST {current_password, new_password}. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/tentativas.php';

require_method('POST');
$user = require_user();

$body    = read_json_body();
$current = (string) ($body['current_password'] ?? '');
$new     = (string) ($body['new_password'] ?? '');

if (strlen($new) < 6) {
    json_response(422, ['error' => 'A nova senha precisa ter pelo menos 6 caracteres.', 'field' => 'new_password']);
}

$pdo = db();

// Quem pegar uma sessão aberta não pode chutar a senha atual sem limite
$chave  = 'senha-atual|' . $user['id'];
$espera = limite_espera($pdo, $chave, TENTATIVAS_MAX, TENTATIVAS_JANELA);
if ($espera !== null) {
    header('Retry-After: ' . ($espera * 60));
    json_response(429, ['error' => 'Muitas tentativas com a senha atual. Espere '
        . minutos_texto($espera) . ' e tente de novo.', 'field' => 'current_password']);
}

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

if ($row === false || !password_verify($current, $row['password_hash'])) {
    limite_registrar($pdo, $chave);
    json_response(401, ['error' => 'A senha atual não confere.', 'field' => 'current_password']);
}
limite_zerar($pdo, $chave);

$novo = password_hash($new, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$novo, $user['id']]);

// Senha nova derruba o "manter conectado" dos outros aparelhos; este continua
$lembrava = lembrar_token_do_cookie() !== null;
lembrar_esquecer_todos((int) $user['id']);
if ($lembrava) {
    lembrar_emitir((int) $user['id']);
}

// As sessões abertas com a senha antiga caem na próxima requisição (lembrar.php);
// esta é atualizada para a senha nova e continua valendo.
session_regenerate_id(true);
sessao_marcar($novo);
dispositivo_marcar((int) $user['id'], $novo);

json_response(200, ['ok' => true]);
