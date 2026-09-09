<?php
/**
 * Aline Dan · Envio de e-mails e notificações.
 *
 * MAIL_MODE (em config.php):
 *   'file' — grava cada e-mail como um .html em storage/outbox/ (padrão
 *            em desenvolvimento: dá para abrir e ver exatamente o que
 *            seria enviado, sem precisar de servidor de e-mail).
 *   'smtp' — envia de verdade (preencha SMTP_* em config.php).
 *
 * Todo envio, com sucesso ou não, entra em storage/mail.log. Quando o SMTP
 * recusa, a resposta do servidor vai junto — é por ali que se descobre se o
 * problema foi senha, porta ou remetente.
 *
 * Para testar as credenciais sem depender do site:
 *   php setup/testar_email.php seu@email.com
 */
declare(strict_types=1);

function storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
        // storage fica dentro da pasta pública: bloqueia acesso via navegador
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

function mail_template(string $title, string $bodyHtml): string
{
    // Cores iguais às do site (--vinho #360D29). E-mail não entende variável
    // de CSS nem folha externa: tudo precisa vir escrito no atributo style.
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;background:#F7F4F5;font-family:Georgia,\'Times New Roman\',serif;color:#360D29;">'
        . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:20px;overflow:hidden;border:1px solid #E8DDE3;">'
        . '<div style="background:#360D29;color:#fff;padding:24px 28px;text-align:center;">'
        . '<div style="font-size:26px;font-style:italic;">Aline Dan</div>'
        . '<div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:.8;">Espaço Lounge</div>'
        . '</div><div style="padding:28px;">'
        . '<h1 style="font-size:20px;color:#360D29;margin:0 0 16px;">' . htmlspecialchars($title) . '</h1>'
        . $bodyHtml
        . '<p style="font-size:12px;color:#8A7480;margin-top:28px;border-top:1px solid #E8DDE3;padding-top:16px;">Rua Professora Chiquita Fernandes, 366 — Vila São Paulo · Araçatuba/SP · (18) 99665-5263</p>'
        . '</div></div></body></html>';
}

/** Uma linha em storage/mail.log. */
function mail_log(string $linha): void
{
    @file_put_contents(
        storage_dir() . '/mail.log',
        date('Y-m-d H:i:s') . "\t" . $linha . "\n",
        FILE_APPEND
    );
}

/**
 * Envia (ou registra) um e-mail. Nunca lança exceção: um problema no
 * servidor de e-mail não pode derrubar um agendamento.
 */
function send_app_mail(string $to, string $subject, string $bodyHtml): bool
{
    if ($to === '') {
        return false;
    }
    try {
        $html = mail_template($subject, $bodyHtml);

        if (MAIL_MODE === 'smtp' && SMTP_HOST !== '') {
            $erro = '';
            $ok = smtp_deliver($to, $subject, $html, $erro);
            mail_log(($ok ? 'enviado' : 'FALHOU') . "\tsmtp\t" . $to . "\t" . $subject
                . ($ok ? '' : "\t=> " . $erro));
            return $ok;
        }
        mail_log("gravado\tfile\t" . $to . "\t" . $subject);

        // Modo arquivo: caixa de saída em storage/outbox
        $outbox = storage_dir() . '/outbox';
        if (!is_dir($outbox)) {
            mkdir($outbox, 0775, true);
        }
        $slug = substr(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $subject) ?: 'email')), 0, 40);
        $file = $outbox . '/' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '-' . $slug . '.html';
        $meta = '<!-- PARA: ' . htmlspecialchars($to) . ' | ASSUNTO: ' . htmlspecialchars($subject) . ' -->' . "\n";
        file_put_contents($file, $meta . $html);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Segurança da conexão: 'ssl' (porta 465) ou 'tls' (STARTTLS, porta 587). */
function smtp_security(): string
{
    if (defined('SMTP_SECURITY') && SMTP_SECURITY !== '') {
        return strtolower(SMTP_SECURITY);
    }
    return ((int) SMTP_PORT === 465) ? 'ssl' : 'tls';
}

function smtp_timeout(): int
{
    return defined('SMTP_TIMEOUT') ? (int) SMTP_TIMEOUT : 15;
}

/** Versão em texto puro do e-mail — sem ela o filtro de spam desconfia. */
function mail_plain_text(string $html): string
{
    $t = preg_replace('#<br\s*/?>#i', "\n", $html);
    $t = preg_replace('#</(p|div|h1|h2|h3|tr)>#i', "\n\n", (string) $t);
    $t = strip_tags((string) $t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/\n{3,}/", "\n\n", (string) $t);
    return trim((string) $t);
}

/**
 * Entrega uma mensagem por SMTP. Devolve false e preenche $erro com a
 * resposta do servidor — é essa string que aparece em storage/mail.log.
 *
 * Fala SSL implícito (465) e STARTTLS (587), autentica em PLAIN ou LOGIN,
 * e manda o corpo em base64 para não estourar o limite de linha do SMTP
 * nem embaralhar os acentos.
 */
function smtp_deliver(string $to, string $subject, string $html, string &$erro = ''): bool
{
    $seguranca = smtp_security();
    $endereco  = ($seguranca === 'ssl' ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT;

    $fp = @stream_socket_client($endereco, $errno, $errstr, smtp_timeout());
    if (!$fp) {
        $erro = 'não conectou em ' . $endereco . ' (' . $errstr . ')';
        return false;
    }
    stream_set_timeout($fp, smtp_timeout());

    // Uma resposta pode vir em várias linhas; a última traz espaço na 4ª posição.
    $ler = static function () use ($fp): string {
        $resposta = '';
        while (($linha = fgets($fp, 1024)) !== false) {
            $resposta .= $linha;
            if (strlen($linha) >= 4 && $linha[3] === ' ') {
                break;
            }
        }
        return $resposta;
    };
    $enviar = static function (string $comando) use ($fp, $ler): string {
        fwrite($fp, $comando . "\r\n");
        return $ler();
    };
    $codigo = static fn(string $r): int => (int) substr(trim($r), 0, 3);
    $desistir = static function (string $motivo) use ($fp, &$erro): bool {
        $erro = $motivo;
        @fclose($fp);
        return false;
    };

    $dominio = substr(strrchr(MAIL_FROM, '@') ?: '@localhost', 1);

    if ($codigo($ler()) !== 220) {
        return $desistir('o servidor não respondeu ao abrir a conexão');
    }
    $ehlo = $enviar('EHLO ' . $dominio);
    if ($codigo($ehlo) !== 250) {
        return $desistir('EHLO recusado: ' . trim($ehlo));
    }

    if ($seguranca === 'tls') {
        if ($codigo($enviar('STARTTLS')) !== 220) {
            return $desistir('o servidor não aceitou STARTTLS — tente a porta 465 com SMTP_SECURITY = \'ssl\'');
        }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $desistir('não foi possível criptografar a conexão (TLS)');
        }
        $ehlo = $enviar('EHLO ' . $dominio);   // o EHLO se repete depois do TLS
    }

    if (SMTP_USER !== '') {
        $mecanismos = '';
        foreach (preg_split('/\r?\n/', $ehlo) ?: [] as $linha) {
            if (stripos($linha, 'AUTH ') !== false) {
                $mecanismos = strtoupper($linha);
                break;
            }
        }
        if (strpos($mecanismos, 'PLAIN') !== false) {
            $r = $enviar('AUTH PLAIN ' . base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASS));
        } else {
            $enviar('AUTH LOGIN');
            $enviar(base64_encode(SMTP_USER));
            $r = $enviar(base64_encode(SMTP_PASS));
        }
        if ($codigo($r) !== 235) {
            return $desistir('usuário ou senha recusados: ' . trim($r));
        }
    }

    if ($codigo($enviar('MAIL FROM:<' . MAIL_FROM . '>')) !== 250) {
        return $desistir('remetente recusado — o MAIL_FROM (' . MAIL_FROM
            . ') precisa ser o mesmo endereço que autenticou, ou um apelido autorizado dele');
    }
    $r = $enviar('RCPT TO:<' . $to . '>');
    if ($codigo($r) !== 250 && $codigo($r) !== 251) {
        return $desistir('destinatário recusado (' . $to . '): ' . trim($r));
    }
    if ($codigo($enviar('DATA')) !== 354) {
        return $desistir('o servidor não aceitou iniciar a mensagem');
    }

    $limite  = '=_alinedan_' . bin2hex(random_bytes(8));
    $assunto = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $de      = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>';

    $mensagem = 'Date: ' . date('r') . "\r\n"
        . 'From: ' . $de . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Reply-To: <' . ADMIN_EMAIL . ">\r\n"
        . 'Subject: ' . $assunto . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . ">\r\n"
        . "MIME-Version: 1.0\r\n"
        . 'Content-Type: multipart/alternative; boundary="' . $limite . "\"\r\n\r\n"
        . '--' . $limite . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode(mail_plain_text($html)), 76, "\r\n")
        . '--' . $limite . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . '--' . $limite . "--\r\n";

    $r = $enviar($mensagem . "\r\n.");
    $ok = $codigo($r) === 250;
    if (!$ok) {
        $erro = 'a mensagem foi recusada no envio: ' . trim($r);
    }
    $enviar('QUIT');
    @fclose($fp);
    return $ok;
}

/** Notificação para a administração do salão. */
function notify_admin(string $subject, string $bodyHtml): void
{
    send_app_mail(ADMIN_EMAIL, $subject, $bodyHtml);
}

/**
 * Lembretes automáticos: clientes com horário AMANHÃ que ainda não
 * receberam aviso. Chamado ao abrir o painel (e por setup/cron_reminders.php).
 */
function send_due_reminders(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        'SELECT b.id, DATE_FORMAT(b.booking_date, "%d/%m/%Y") AS d,
                TIME_FORMAT(b.booking_time, "%H:%i") AS t,
                COALESCE(s.name, b.service_id) AS service_name,
                COALESCE(u.name, b.guest_name) AS client_name,
                u.email
           FROM bookings b
           LEFT JOIN users u ON u.id = b.user_id
           LEFT JOIN services s ON s.id = b.service_id
          WHERE b.booking_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND b.status = "confirmado"
            AND b.reminder_sent_at IS NULL'
    );
    $stmt->execute();
    $due = $stmt->fetchAll();
    if (!$due) {
        return 0;
    }

    $mark = $pdo->prepare('UPDATE bookings SET reminder_sent_at = NOW() WHERE id = ?');
    $sent = 0;
    foreach ($due as $b) {
        if (!empty($b['email'])) {
            $ok = send_app_mail(
                $b['email'],
                'Lembrete: seu horário é amanhã!',
                '<p>Olá, <strong>' . htmlspecialchars($b['client_name'] ?? '') . '</strong>!</p>'
                . '<p>Passando para lembrar do seu horário no salão:</p>'
                . '<p style="font-size:17px;"><strong>' . htmlspecialchars($b['service_name'])
                . '</strong><br>' . $b['d'] . ' às <strong>' . $b['t'] . '</strong></p>'
                . '<p>Se precisar remarcar, é só cancelar pelo site e escolher um novo horário. Até amanhã! 💕</p>'
            );
            if ($ok) { $sent++; }
        }
        // Marca mesmo sem e-mail (agendamento de balcão) para não reprocessar.
        $mark->execute([$b['id']]);
    }
    return $sent;
}
