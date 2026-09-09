<?php
/**
 * "Esqueci minha senha": POST {email}.
 * Resposta sempre genérica (não revela se o e-mail existe).
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

require_method('POST');

$body  = read_json_body();
$email = strtolower(trim((string) ($body['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(422, ['error' => 'Informe um e-mail válido.']);
}

$pdo  = db();
$stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user !== false) {
    // Invalida pedidos anteriores e cria um novo token (validade: 1 hora)
    $pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND kind = "reset" AND used_at IS NULL')
        ->execute([$user['id']]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO user_tokens (user_id, kind, token_hash, expires_at)
         VALUES (?, "reset", ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
    )->execute([$user['id'], hash('sha256', $token)]);

    $link = BASE_URL . 'redefinir-senha.html?token=' . $token;
    send_app_mail(
        $email,
        'Redefinição de senha — Aline Dan',
        '<p>Olá, <strong>' . htmlspecialchars($user['name']) . '</strong>!</p>'
        . '<p>Recebemos um pedido para redefinir a sua senha. Se foi você, toque no botão abaixo (o link vale por 1 hora):</p>'
        . '<p><a href="' . $link . '" style="display:inline-block;background:#DB2777;color:#fff;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:bold;">Criar nova senha</a></p>'
        . '<p style="font-size:13px;color:#7A5B6B;">Se não foi você, pode ignorar este e-mail — sua senha continua a mesma.</p>'
    );
}

json_response(200, ['ok' => true, 'message' => 'Se este e-mail estiver cadastrado, enviamos um link para redefinir a senha.']);
