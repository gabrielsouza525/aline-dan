<?php
/**
 * Testa o envio de e-mail sem depender do site.
 *
 *   C:\xampp\php\php.exe setup\testar_email.php seu@email.com
 *
 * Mostra a configuração em uso (nunca a senha), tenta enviar uma mensagem e,
 * quando falha, imprime a resposta exata do servidor — que é o que diz se o
 * problema foi a senha, a porta ou o remetente.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este teste só roda pela linha de comando.\n");
}

require dirname(__DIR__) . '/api/config.php';
require dirname(__DIR__) . '/api/mailer.php';

$para = $argv[1] ?? ADMIN_EMAIL;

echo "Configuração atual\n";
echo "  MAIL_MODE     : " . MAIL_MODE . "\n";
echo "  SMTP_HOST     : " . (SMTP_HOST !== '' ? SMTP_HOST : '(vazio)') . "\n";
echo "  SMTP_PORT     : " . SMTP_PORT . "\n";
echo "  SMTP_SECURITY : " . smtp_security() . "\n";
echo "  SMTP_USER     : " . (SMTP_USER !== '' ? SMTP_USER : '(vazio)') . "\n";
echo "  SMTP_PASS     : " . (SMTP_PASS !== '' ? '(preenchida, ' . strlen(SMTP_PASS) . " caracteres)" : '(VAZIA)') . "\n";
echo "  MAIL_FROM     : " . MAIL_FROM . "\n";
echo "  Enviando para : " . $para . "\n\n";

if (MAIL_MODE !== 'smtp') {
    echo "MAIL_MODE está em '" . MAIL_MODE . "'. A mensagem vai para storage/outbox,\n";
    echo "não para a caixa de entrada. Troque para 'smtp' em api/config.php.\n\n";
}
if (MAIL_FROM !== SMTP_USER && SMTP_USER !== '') {
    echo "Atenção: MAIL_FROM (" . MAIL_FROM . ") é diferente do SMTP_USER (" . SMTP_USER . ").\n";
    echo "A maioria dos servidores recusa enviar em nome de um endereço que não autenticou.\n\n";
}

$corpo = '<p>Se você está lendo isto, o envio de e-mail do site está funcionando.</p>'
    . '<p>Teste feito em ' . date('d/m/Y \à\s H:i') . '.</p>';

if (MAIL_MODE === 'smtp' && SMTP_HOST !== '') {
    $erro = '';
    $inicio = microtime(true);
    $ok = smtp_deliver($para, 'Teste de envio · Aline Dan', mail_template('Teste de envio', $corpo), $erro);
    $tempo = round((microtime(true) - $inicio) * 1000);

    if ($ok) {
        echo "OK — mensagem aceita pelo servidor em {$tempo} ms.\n";
        echo "Confira a caixa de entrada de {$para} (e o spam, no primeiro envio).\n";
        exit(0);
    }
    echo "FALHOU depois de {$tempo} ms.\n";
    echo "Motivo: {$erro}\n";
    exit(1);
}

echo send_app_mail($para, 'Teste de envio · Aline Dan', $corpo)
    ? "Mensagem gravada em storage/outbox — abra o .html mais recente para ver.\n"
    : "Não foi possível nem gravar o arquivo. Confira as permissões da pasta storage/.\n";
