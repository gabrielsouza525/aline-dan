<?php
/** Redefine a senha com o token do e-mail: POST {token, new_password}. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/tentativas.php';

require_method('POST');

$body  = read_json_body();
$token = (string) ($body['token'] ?? '');
$new   = (string) ($body['new_password'] ?? '');

if (strlen($new) < 6) {
    json_response(422, ['error' => 'A nova senha precisa ter pelo menos 6 caracteres.']);
}
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    json_response(422, ['error' => 'Link inválido. Peça uma nova redefinição.']);
}

$pdo  = db();
tokens_prontos($pdo); // a tabela pode não existir em bancos antigos (lembrar.php)
$stmt = $pdo->prepare(
    'SELECT id, user_id FROM user_tokens
      WHERE kind = "reset" AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
);
$stmt->execute([hash('sha256', $token)]);
$row = $stmt->fetch();

if ($row === false) {
    json_response(422, ['error' => 'Este link expirou ou já foi usado. Peça uma nova redefinição.']);
}

$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
    ->execute([password_hash($new, PASSWORD_DEFAULT), $row['user_id']]);
$pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = ?')
    ->execute([$row['id']]);

// Quem redefine a senha pode estar tirando alguém da conta: nenhum aparelho
// continua logado com a senha antiga. As sessões abertas caem sozinhas na
// próxima requisição (a marca de senha delas deixou de bater — lembrar.php),
// e os aparelhos conhecidos deixam de ser (o cookie deriva da senha).
lembrar_esquecer_todos((int) $row['user_id']);

// Provou que é dona do e-mail: o bloqueio de login da conta não vale mais.
// Sem isso, quem redefiniu depois de errar a senha dava de cara com "espere".
$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$row['user_id']]);
$email = (string) $stmt->fetchColumn();
if ($email !== '') {
    limite_zerar($pdo, login_chave($email, false));
    limite_zerar($pdo, login_chave($email, true));
}

json_response(200, ['ok' => true]);
