<?php
/** Reenvia o e-mail de confirmação (usuária logada): POST. */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

require_method('POST');
$user = require_user();

if (!empty($user['email_verified'])) {
    json_response(200, ['ok' => true, 'already' => true]);
}

$pdo = db();
$pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND kind = "verify" AND used_at IS NULL')
    ->execute([$user['id']]);

$token = bin2hex(random_bytes(32));
$pdo->prepare(
    'INSERT INTO user_tokens (user_id, kind, token_hash, expires_at)
     VALUES (?, "verify", ?, DATE_ADD(NOW(), INTERVAL 2 DAY))'
)->execute([$user['id'], hash('sha256', $token)]);

send_app_mail(
    $user['email'],
    'Confirme seu e-mail — Aline Dan',
    '<p>Olá, <strong>' . htmlspecialchars($user['name']) . '</strong>!</p>'
    . '<p>Toque no botão abaixo para confirmar o seu e-mail (o link vale por 2 dias):</p>'
    . '<p><a href="' . BASE_URL . 'api/verify_email.php?token=' . $token
    . '" style="display:inline-block;background:#DB2777;color:#fff;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:bold;">Confirmar e-mail</a></p>'
);

json_response(200, ['ok' => true]);
