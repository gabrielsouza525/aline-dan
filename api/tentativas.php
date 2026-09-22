<?php
/**
 * Aline Dan · Limites de tentativas: login, senha atual, "esqueci a senha",
 * reenvio de confirmação e novas tentativas de lembrete.
 *
 * Cada tentativa vira uma linha em login_attempts, sob uma "chave" (a coluna
 * email_hash guarda o SHA-256 da chave, nunca o dado puro). Os nomes da tabela
 * e da coluna ficaram da primeira versão, que só limitava o login; trocá-los
 * exigiria migrar o banco publicado sem ganho nenhum.
 *
 * Nenhum limite usa o IP: na InfinityFree o PHP pode enxergar o IP do proxy em
 * vez do visitante, e um limite por IP que na verdade fosse do proxy deixaria
 * um único atacante bloquear todo mundo.
 *
 * LOGIN — o problema de limitar só pelo e-mail é que qualquer pessoa que saiba
 * o e-mail consegue travar a conta. A saída (recomendação do OWASP) é o
 * aparelho conhecido: quem já entrou nesta conta neste navegador recebe o
 * cookie alinedan_disp, e as tentativas desse aparelho contam separado das de
 * estranhos. Um atacante trava a conta para aparelhos desconhecidos; os da
 * dona continuam entrando. A janela cresce com a insistência ao longo do dia
 * (15 min, 1 h, 6 h), o que derruba de ~480 para ~35 os chutes diários.
 */
declare(strict_types=1);

const TENTATIVAS_MAX    = 5;    // senhas erradas antes de bloquear
const TENTATIVAS_JANELA = 15;   // minutos, na primeira rodada
const HISTORICO_HORAS   = 48;   // quanto tempo uma tentativa fica guardada
const DISPOSITIVO_COOKIE = 'alinedan_disp';
const DISPOSITIVO_DIAS   = 365;

/** Cria a tabela no primeiro uso, como a user_tokens (lembrar.php). */
function tentativas_prontas(PDO $pdo): void
{
    static $pronto = false;
    if ($pronto) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
           id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
           email_hash CHAR(64)  NOT NULL,
           created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
           KEY idx_email_hora (email_hash, created_at),
           KEY idx_hora (created_at)
         ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
    );
    $pronto = true;
}

// ---------- Limitador genérico ----------

/**
 * Minutos até a chave poder tentar de novo, ou null se está liberada.
 * Bloqueada enquanto houver $max registros dentro da janela; libera quando o
 * $max-ésimo mais recente sai dela.
 */
function limite_espera(PDO $pdo, string $chave, int $max, int $janelaMin): ?int
{
    tentativas_prontas($pdo);
    $stmt = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, NOW(), created_at + INTERVAL ' . $janelaMin . ' MINUTE)
           FROM login_attempts
          WHERE email_hash = ? AND created_at > NOW() - INTERVAL ' . $janelaMin . ' MINUTE
          ORDER BY created_at DESC
          LIMIT 1 OFFSET ' . ($max - 1)
    );
    $stmt->execute([hash('sha256', $chave)]);
    $segundos = $stmt->fetchColumn();
    if ($segundos === false) {
        return null;
    }
    return max(1, (int) ceil(((int) $segundos) / 60));
}

/** Quantos registros a chave tem dentro da janela. */
function limite_contar(PDO $pdo, string $chave, int $janelaMin): int
{
    tentativas_prontas($pdo);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE email_hash = ? AND created_at > NOW() - INTERVAL ' . $janelaMin . ' MINUTE'
    );
    $stmt->execute([hash('sha256', $chave)]);
    return (int) $stmt->fetchColumn();
}

function limite_registrar(PDO $pdo, string $chave): void
{
    tentativas_prontas($pdo);
    $pdo->prepare('INSERT INTO login_attempts (email_hash) VALUES (?)')->execute([hash('sha256', $chave)]);
    $pdo->exec('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL ' . HISTORICO_HORAS . ' HOUR');
}

function limite_zerar(PDO $pdo, string $chave): void
{
    tentativas_prontas($pdo);
    $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = ?')->execute([hash('sha256', $chave)]);
}

/** "1 minuto", "12 minutos", "1 hora", "6 horas". */
function minutos_texto(int $m): string
{
    if ($m < 60) {
        return $m . ($m === 1 ? ' minuto' : ' minutos');
    }
    $h = (int) ceil($m / 60);
    return $h . ($h === 1 ? ' hora' : ' horas');
}

// ---------- Login ----------

/** Chave de contagem: aparelho conhecido e estranhos contam separado. */
function login_chave(string $email, bool $aparelhoConhecido): string
{
    return ($aparelhoConhecido ? 'login-aparelho|' : 'login|') . $email;
}

/**
 * Janela progressiva: quem acumula erros ao longo do dia espera mais a cada
 * rodada de bloqueio — 15 minutos, depois 1 hora, depois 6 horas.
 */
function login_janela(PDO $pdo, string $chave): int
{
    $dia = limite_contar($pdo, $chave, 24 * 60);
    if ($dia >= 3 * TENTATIVAS_MAX) {
        return 360;
    }
    if ($dia >= 2 * TENTATIVAS_MAX) {
        return 60;
    }
    return TENTATIVAS_JANELA;
}

/**
 * Valor do cookie de aparelho conhecido. Deriva da senha atual: trocar ou
 * redefinir a senha invalida todos os aparelhos de uma vez, sem tabela nova.
 * O hash guardado no banco é bcrypt com sal, então o cookie não serve para
 * adivinhar a senha fora do site.
 */
function dispositivo_valor(int $userId, string $passwordHash): string
{
    return hash('sha256', 'aparelho|' . $userId . '|' . $passwordHash);
}

function dispositivo_conhecido(int $userId, string $passwordHash): bool
{
    $cookie = (string) ($_COOKIE[DISPOSITIVO_COOKIE] ?? '');
    return $cookie !== '' && hash_equals(dispositivo_valor($userId, $passwordHash), $cookie);
}

/** Depois de entrar (ou criar a conta, ou trocar a senha): este aparelho passa a ser conhecido. */
function dispositivo_marcar(int $userId, string $passwordHash): void
{
    setcookie(DISPOSITIVO_COOKIE, dispositivo_valor($userId, $passwordHash), [
        'expires'  => time() + DISPOSITIVO_DIAS * 86400,
        'path'     => '/',
        'secure'   => function_exists('site_https') && site_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
