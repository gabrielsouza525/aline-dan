<?php
/** Confirma o e-mail via link: GET ?token=... e redireciona para o site. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('GET');

$token = (string) ($_GET['token'] ?? '');
$dest  = BASE_URL . 'login.html?verified=0';

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, user_id FROM user_tokens
          WHERE kind = "verify" AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();

    if ($row !== false) {
        $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')
            ->execute([$row['user_id']]);
        $pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);
        $dest = BASE_URL . 'login.html?verified=1';
    }
}

header('Location: ' . $dest);
exit;
