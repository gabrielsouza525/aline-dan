<?php
/**
 * Aline Dan · Tabela de tokens de usuário.
 *
 * A confirmação de e-mail e a redefinição de senha guardam seus tokens em
 * user_tokens. O banco-completo.sql de 10/09 não trazia essa tabela, e sem
 * ela o cadastro, o "esqueci a senha" e a confirmação de e-mail dão erro 500.
 */
declare(strict_types=1);

/**
 * Garante a tabela user_tokens. Em vez de pedir um SQL manual no
 * phpMyAdmin, ela se cria sozinha no primeiro uso.
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
           kind       ENUM('verify','reset') NOT NULL,
           token_hash CHAR(64)     NOT NULL,
           expires_at DATETIME     NOT NULL,
           used_at    DATETIME     NULL,
           created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
           KEY idx_hash (token_hash),
           CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
         ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
    );
    $pronto = true;
}
