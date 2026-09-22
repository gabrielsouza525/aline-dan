<?php
/**
 * Aline Dan · Limite de tentativas de login.
 *
 * Sem limite, dava para testar senhas sem parar — inclusive na conta da
 * administradora, que abre o nome e o WhatsApp de todas as clientes. Agora,
 * 5 senhas erradas para o mesmo e-mail em 15 minutos bloqueiam esse e-mail
 * até a quinta tentativa mais recente completar 15 minutos.
 *
 * O limite é por e-mail, não por IP: atrás do proxy da hospedagem não dá
 * para garantir que o PHP enxergue o IP real do visitante, e um limite por IP
 * que na verdade fosse do proxy deixaria um único atacante bloquear o login
 * de todo mundo. O e-mail é guardado só como SHA-256, e as tentativas somem
 * depois de um dia.
 */
declare(strict_types=1);

const TENTATIVAS_MAX    = 5;
const TENTATIVAS_JANELA = 15;   // minutos

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

/**
 * Minutos até o e-mail poder tentar de novo, ou null se está liberado.
 * Bloqueado enquanto houver TENTATIVAS_MAX falhas na janela; libera quando
 * a quinta mais recente sai dela.
 */
function tentativas_bloqueio(PDO $pdo, string $email): ?int
{
    tentativas_prontas($pdo);
    $stmt = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, NOW(), created_at + INTERVAL ' . TENTATIVAS_JANELA . ' MINUTE)
           FROM login_attempts
          WHERE email_hash = ? AND created_at > NOW() - INTERVAL ' . TENTATIVAS_JANELA . ' MINUTE
          ORDER BY created_at DESC
          LIMIT 1 OFFSET ' . (TENTATIVAS_MAX - 1)
    );
    $stmt->execute([hash('sha256', $email)]);
    $segundos = $stmt->fetchColumn();
    if ($segundos === false) {
        return null;
    }
    return max(1, (int) ceil(((int) $segundos) / 60));
}

/** Registra uma senha errada e devolve quantas tentativas ainda restam. */
function tentativas_falhou(PDO $pdo, string $email): int
{
    tentativas_prontas($pdo);
    $hash = hash('sha256', $email);
    $pdo->prepare('INSERT INTO login_attempts (email_hash) VALUES (?)')->execute([$hash]);
    $pdo->exec('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE email_hash = ? AND created_at > NOW() - INTERVAL ' . TENTATIVAS_JANELA . ' MINUTE'
    );
    $stmt->execute([$hash]);
    return max(0, TENTATIVAS_MAX - (int) $stmt->fetchColumn());
}

/** Login certo: zera a contagem desse e-mail. */
function tentativas_zerar(PDO $pdo, string $email): void
{
    tentativas_prontas($pdo);
    $pdo->prepare('DELETE FROM login_attempts WHERE email_hash = ?')->execute([hash('sha256', $email)]);
}

function minutos_texto(int $m): string
{
    return $m . ($m === 1 ? ' minuto' : ' minutos');
}
