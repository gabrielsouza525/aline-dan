<?php
/**
 * Aline Dan · "Manter conectado" e a tabela de tokens de usuário.
 *
 * A sessão do PHP morre quando o navegador fecha (o cookie dela não tem
 * validade) e o servidor apaga sessão parada depois de uns 24 minutos. Para a
 * cliente não precisar entrar de novo a cada visita, o login grava um segundo
 * cookie, de 30 dias, com um token aleatório. O banco guarda só o SHA-256
 * dele (user_tokens, kind = "remember"): quem ler o banco não consegue se
 * passar pela cliente.
 *
 * Quando a sessão some mas o cookie existe, start_session() (config.php)
 * chama lembrar_restaurar() e a sessão é refeita sem pedir senha.
 */
declare(strict_types=1);

const LEMBRAR_COOKIE = 'alinedan_lembrar';
const LEMBRAR_DIAS   = 30;

/**
 * Garante a tabela user_tokens, com "remember" entre os tipos.
 *
 * O banco-completo.sql de 10/09 não trazia essa tabela, e sem ela o cadastro,
 * o "esqueci a senha" e a confirmação de e-mail dão erro 500. Em vez de pedir
 * um SQL manual no phpMyAdmin, ela se cria sozinha no primeiro uso.
 */
function tokens_prontos(PDO $pdo): void
{
    static $pronto = false;
    if ($pronto) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_tokens (
           id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
           user_id    INT UNSIGNED NOT NULL,
           kind       ENUM('verify','reset','remember') NOT NULL,
           token_hash CHAR(64)     NOT NULL,
           expires_at DATETIME     NOT NULL,
           used_at    DATETIME     NULL,
           created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
           KEY idx_hash (token_hash),
           CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
         ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
    );
    // Tabelas criadas antes do "manter conectado" só aceitam verify e reset
    $coluna = $pdo->query("SHOW COLUMNS FROM user_tokens LIKE 'kind'")->fetch();
    if ($coluna !== false && strpos((string) $coluna['Type'], "'remember'") === false) {
        $pdo->exec("ALTER TABLE user_tokens MODIFY kind ENUM('verify','reset','remember') NOT NULL");
    }
    $pronto = true;
}

function lembrar_cookie(string $valor, int $expira): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(LEMBRAR_COOKIE, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** O token do cookie deste aparelho, se tiver o formato certo. */
function lembrar_token_do_cookie(): ?string
{
    $token = (string) ($_COOKIE[LEMBRAR_COOKIE] ?? '');
    return preg_match('/^[a-f0-9]{64}$/', $token) ? $token : null;
}

/** Depois de um login bem-sucedido: grava o token deste aparelho. */
function lembrar_emitir(int $userId): void
{
    $pdo = db();
    tokens_prontos($pdo);

    // Limpeza de passagem: os vencidos desta cliente e o antigo deste aparelho
    $pdo->prepare('DELETE FROM user_tokens WHERE user_id = ? AND kind = "remember" AND expires_at <= NOW()')
        ->execute([$userId]);
    $antigo = lembrar_token_do_cookie();
    if ($antigo !== null) {
        $pdo->prepare('DELETE FROM user_tokens WHERE kind = "remember" AND token_hash = ?')
            ->execute([hash('sha256', $antigo)]);
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO user_tokens (user_id, kind, token_hash, expires_at)
         VALUES (?, "remember", ?, DATE_ADD(NOW(), INTERVAL ' . LEMBRAR_DIAS . ' DAY))'
    )->execute([$userId, hash('sha256', $token)]);
    lembrar_cookie($token, time() + LEMBRAR_DIAS * 86400);
}

/**
 * Sessão vazia e cookie presente: refaz o login sem pedir senha.
 * Nunca derruba a página — sem banco ou com token ruim, só segue deslogado.
 */
function lembrar_restaurar(): void
{
    if (!empty($_SESSION['user_id']) || !isset($_COOKIE[LEMBRAR_COOKIE])) {
        return;
    }
    $token = lembrar_token_do_cookie();
    if ($token === null) {
        lembrar_cookie('', time() - 3600);
        return;
    }
    try {
        $pdo = db();
        tokens_prontos($pdo);
        $stmt = $pdo->prepare(
            'SELECT t.id, t.user_id
               FROM user_tokens t JOIN users u ON u.id = t.user_id
              WHERE t.kind = "remember" AND t.token_hash = ? AND t.expires_at > NOW()'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if ($row === false) {
            lembrar_cookie('', time() - 3600);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['user_id'];

        // Janela deslizante: quem continua usando o site não é deslogada
        $pdo->prepare('UPDATE user_tokens SET expires_at = DATE_ADD(NOW(), INTERVAL ' . LEMBRAR_DIAS . ' DAY) WHERE id = ?')
            ->execute([$row['id']]);
        lembrar_cookie($token, time() + LEMBRAR_DIAS * 86400);
    } catch (Throwable $e) {
        return;
    }
}

/** Logout: apaga o token deste aparelho e o cookie. */
function lembrar_esquecer(): void
{
    $token = lembrar_token_do_cookie();
    if ($token !== null) {
        try {
            db()->prepare('DELETE FROM user_tokens WHERE kind = "remember" AND token_hash = ?')
                ->execute([hash('sha256', $token)]);
        } catch (Throwable $e) {
            // sem banco, ao menos o cookie sai
        }
    }
    if (isset($_COOKIE[LEMBRAR_COOKIE])) {
        lembrar_cookie('', time() - 3600);
    }
}

/** Senha trocada ou redefinida: derruba o "manter conectado" em todo aparelho. */
function lembrar_esquecer_todos(int $userId): void
{
    $pdo = db();
    tokens_prontos($pdo);
    $pdo->prepare('DELETE FROM user_tokens WHERE user_id = ? AND kind = "remember"')->execute([$userId]);
}
