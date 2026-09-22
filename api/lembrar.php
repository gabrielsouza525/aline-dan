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
 *
 * Esse mesmo gancho, que roda em toda requisição com sessão, também:
 *   - encerra sessões abertas com uma senha que já foi trocada ou redefinida
 *     (sessao_conferir); e
 *   - recusa pedidos que mudam dados sem vir como JSON (sessao_bloquear_csrf).
 * Ficou tudo aqui porque o config.php é o arquivo que não se altera: ele só
 * chama lembrar_restaurar(), e é daqui para frente que a sessão é cuidada.
 */
declare(strict_types=1);

const LEMBRAR_COOKIE = 'alinedan_lembrar';
const LEMBRAR_DIAS   = 30;

/**
 * O site publicado é HTTPS? Decidido pelo BASE_URL do config, e não pela
 * requisição: atrás do proxy da hospedagem o PHP nem sempre enxerga que a
 * conexão veio cifrada. No localhost (http://) continua tudo como antes.
 */
function site_https(): bool
{
    return defined('BASE_URL') && strpos(BASE_URL, 'https://') === 0;
}

// Cookie de sessão marcado Secure: o navegador nunca o manda por HTTP, nem
// no primeiro acesso a http://, antes do redirecionamento para https://.
// Vale porque este arquivo carrega antes de qualquer start_session().
if (site_https()) {
    ini_set('session.cookie_secure', '1');
}

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
    setcookie(LEMBRAR_COOKIE, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => site_https(),
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

// ---------- Sessão ----------

/**
 * Chamado pelo start_session() do config.php em toda requisição com sessão.
 * O nome ficou do tempo em que só restaurava o "manter conectado".
 */
function lembrar_restaurar(): void
{
    sessao_bloquear_csrf();
    sessao_conferir();
    lembrar_refazer_login();
}

/**
 * Pedido que muda dados precisa chegar como JSON. O site sempre manda assim;
 * um formulário de outro site só consegue mandar texto, formulário comum ou
 * multipart — é por aí que viria um CSRF. O SameSite=Lax dos cookies já barra
 * isso nos navegadores atuais; esta checagem cobre os antigos. Pedido sem
 * corpo (sair, cancelar, reenviar confirmação) segue livre: para esses o
 * navegador não deixa outro site escolher o método nem o formato.
 */
function sessao_bloquear_csrf(): void
{
    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $tipo = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? ''))));
    if ($tipo === '' || strpos($tipo, 'application/json') === 0) {
        return;
    }
    json_response(415, ['error' => 'Formato de pedido não aceito.']);
}

/** Marca da senha que a sessão guarda. Trocar a senha muda a marca. */
function sessao_marca(string $passwordHash): string
{
    return hash('sha256', 'sessao|' . $passwordHash);
}

/** Login, cadastro, troca de senha: a sessão passa a valer para a senha atual. */
function sessao_marcar(string $passwordHash): void
{
    $_SESSION['sessao_marca'] = sessao_marca($passwordHash);
}

/**
 * Sessão aberta com uma senha que já não vale — trocada em outro aparelho ou
 * redefinida pelo e-mail: encerra, e a pessoa precisa entrar de novo. Sessões
 * de antes desta checagem (sem marca) também encerram, uma única vez; quem
 * marcou "manter conectado" volta sozinho logo em seguida.
 * Sem banco, não mexe: current_user() vai falhar do mesmo jeito.
 */
function sessao_conferir(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    try {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();
    } catch (Throwable $e) {
        return;
    }
    $marca = (string) ($_SESSION['sessao_marca'] ?? '');
    if ($hash === false || !hash_equals(sessao_marca((string) $hash), $marca)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

/**
 * Sessão vazia e cookie presente: refaz o login sem pedir senha.
 * Nunca derruba a página — sem banco ou com token ruim, só segue deslogado.
 */
function lembrar_refazer_login(): void
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
            'SELECT t.id, t.user_id, u.password_hash
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
        sessao_marcar($row['password_hash']);

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
