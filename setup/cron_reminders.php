<?php
/**
 * Dispara os lembretes de amanhã pela linha de comando.
 * Os lembretes já rodam sozinhos quando o painel é aberto; este script
 * é opcional, para agendar no Windows (Agendador de Tarefas):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\aline-dan\setup\cron_reminders.php
 */
declare(strict_types=1);
require dirname(__DIR__) . '/api/config.php';
require dirname(__DIR__) . '/api/mailer.php';

$sent = send_due_reminders(db());
echo 'Lembretes enviados: ' . $sent . PHP_EOL;
