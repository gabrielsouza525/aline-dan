<?php
/**
 * Aline Dan · Envio de e-mails e notificações.
 *
 * MAIL_MODE (em config.php):
 *   'file' — grava cada e-mail como um .html em storage/outbox/ (padrão
 *            em desenvolvimento: dá para abrir e ver exatamente o que
 *            seria enviado, sem precisar de servidor de e-mail).
 *   'smtp' — envia de verdade via SMTP (preencha SMTP_* em config.php).
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
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;background:#FDF2F8;font-family:Arial,Helvetica,sans-serif;color:#4A2B3B;">'
        . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #FBCFE8;">'
        . '<div style="background:#DB2777;color:#fff;padding:20px 28px;">'
        . '<div style="font-size:24px;font-weight:bold;">Aline Dan</div>'
        . '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.85;">Salão de Beleza</div>'
        . '</div><div style="padding:28px;">'
        . '<h1 style="font-size:20px;color:#831843;margin:0 0 16px;">' . htmlspecialchars($title) . '</h1>'
        . $bodyHtml
        . '<p style="font-size:12px;color:#7A5B6B;margin-top:28px;">Rua Professora Chiquita Fernandes, 366 — Vila São Paulo · Araçatuba/SP · (18) 99665-5263</p>'
        . '</div></div></body></html>';
}

/** Envia (ou registra) um e-mail. Nunca lança exceção. */
function send_app_mail(string $to, string $subject, string $bodyHtml): bool
{
    if ($to === '') {
        return false;
    }
    try {
        $html = mail_template($subject, $bodyHtml);
        @file_put_contents(
            storage_dir() . '/mail.log',
            date('Y-m-d H:i:s') . "\t" . MAIL_MODE . "\t" . $to . "\t" . $subject . "\n",
            FILE_APPEND
        );

        if (MAIL_MODE === 'smtp' && SMTP_HOST !== '') {
            return smtp_send($to, $subject, $html);
        }

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

/** Cliente SMTP mínimo (SSL implícito, porta 465, AUTH LOGIN). */
function smtp_send(string $to, string $subject, string $html): bool
{
    $fp = @stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) {
        return false;
    }
    $read = function () use ($fp): string {
        $line = '';
        while (($l = fgets($fp, 515)) !== false) { $line = $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return $line;
    };
    $cmd = function (string $c) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); return $read(); };

    $read();
    $cmd('EHLO localhost');
    $cmd('AUTH LOGIN');
    $cmd(base64_encode(SMTP_USER));
    if (strpos($cmd(base64_encode(SMTP_PASS)), '235') !== 0) { fclose($fp); return false; }
    $cmd('MAIL FROM:<' . MAIL_FROM . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');
    $headers = 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . "?= <" . MAIL_FROM . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
    $ok = strpos($cmd($headers . $html . "\r\n."), '250') === 0;
    $cmd('QUIT');
    fclose($fp);
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
