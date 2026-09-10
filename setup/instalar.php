<?php
/**
 * Aline Dan · Conferência e instalação do banco.
 *
 *   php setup/instalar.php                 → diagnostica e popula o que faltar
 *   php setup/instalar.php --admin=e@mail  → cria (ou promove) a administradora
 *
 * Existe por causa de um sintoma difícil de ler: quando o banco tem as tabelas
 * mas está vazio, a API responde 200 com uma lista vazia, o site carrega
 * inteiro e não mostra serviço nenhum. Parece PHP quebrado e não é.
 * Este script diz exatamente o que está faltando, em vez de deixar adivinhar.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este instalador só roda pela linha de comando.\n");
}

$raiz = dirname(__DIR__);
require $raiz . '/api/config.php';

$ok = "  [ok]   ";
$aviso = "  [!]    ";
$erro = "  [ERRO] ";
$problemas = 0;

echo "\nAline Dan · conferência do banco\n";
echo str_repeat('-', 52), "\n";

// ---------- 1. Conexão ----------
try {
    $pdo = db();
    echo $ok, "conectado em ", DB_NAME, "@", DB_HOST, " como ", DB_USER, "\n";
} catch (Throwable $e) {
    echo $erro, "não conectou: ", $e->getMessage(), "\n\n";
    if (str_contains($e->getMessage(), 'Unknown database')) {
        echo "  O banco '", DB_NAME, "' não existe. Crie-o e importe, nesta ordem:\n";
        echo "    setup/schema.sql, migrate-v2.sql, migrate-v3.sql, migrate-v4.sql\n";
        echo "  Depois rode este script de novo.\n\n";
    } else {
        echo "  Confira DB_HOST, DB_USER e DB_PASS em api/config.php.\n\n";
    }
    exit(1);
}

// ---------- 2. Tabelas ----------
$esperadas = ['users', 'services', 'bookings', 'schedule_blocks'];
$existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$faltando = array_diff($esperadas, $existentes);

if ($faltando) {
    echo $erro, "faltam tabelas: ", implode(', ', $faltando), "\n\n";
    echo "  Importe setup/schema.sql e as migrations antes de continuar.\n\n";
    exit(1);
}
echo $ok, "as ", count($esperadas), " tabelas existem\n";

// ---------- 3. Colunas das migrations ----------
$colunas = array_column($pdo->query('DESCRIBE bookings')->fetchAll(), 'Field');
if (!in_array('status', $colunas, true)) {
    echo $erro, "falta a coluna 'status' em bookings — importe setup/migrate-v4.sql\n";
    $problemas++;
} else {
    echo $ok, "migrations aplicadas (bookings.status presente)\n";
}

// ---------- 4. Catálogo ----------
$qtd = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
if ($qtd === 0) {
    echo $aviso, "catálogo vazio — populando com setup/servicos.sql\n";
    $sql = file_get_contents($raiz . '/setup/servicos.sql');
    $sem = implode('', array_filter(file($raiz . '/setup/servicos.sql'), static fn($l) => !str_starts_with(ltrim($l), '--')));
    foreach (array_filter(array_map('trim', explode(";\n", $sem))) as $comando) {
        if ($comando !== '') {
            $pdo->exec($comando);
        }
    }
    $qtd = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    echo $ok, "catálogo populado: ", $qtd, " serviços\n";
} else {
    echo $ok, "catálogo com ", $qtd, " serviços\n";
}

if ($qtd === 0) {
    echo $erro, "o catálogo continua vazio — o site vai carregar sem nenhum serviço\n";
    $problemas++;
}

// ---------- 5. Administradora ----------
$emailAdmin = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--admin=')) {
        $emailAdmin = trim(substr($arg, 8));
    }
}

$admins = $pdo->query('SELECT email FROM users WHERE role = "admin"')->fetchAll(PDO::FETCH_COLUMN);

if ($emailAdmin !== null) {
    if (!filter_var($emailAdmin, FILTER_VALIDATE_EMAIL)) {
        echo $erro, "e-mail inválido: ", $emailAdmin, "\n";
        exit(1);
    }
    // A senha é digitada por quem roda o script; não fica no histórico do shell
    echo "\n  Senha para ", $emailAdmin, ": ";
    $senha = trim((string) fgets(STDIN));
    if (strlen($senha) < 8) {
        echo $erro, "a senha precisa de pelo menos 8 caracteres.\n";
        exit(1);
    }
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$emailAdmin]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $pdo->prepare('UPDATE users SET password_hash = ?, role = "admin" WHERE id = ?')
            ->execute([$hash, $id]);
        echo $ok, "conta existente promovida a administradora\n";
    } else {
        $pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "admin")'
        )->execute(['Aline', $emailAdmin, '', $hash]);
        echo $ok, "administradora criada\n";
    }
    $admins[] = $emailAdmin;
} elseif (!$admins) {
    echo $aviso, "nenhuma administradora — admin.html não vai abrir\n";
    echo "         crie com: php setup/instalar.php --admin=aline@exemplo.com\n";
    $problemas++;
} else {
    echo $ok, "administradora: ", implode(', ', array_unique($admins)), "\n";
}

// ---------- 6. Avisos de produção ----------
echo str_repeat('-', 52), "\n";
if (MAIL_MODE !== 'smtp') {
    echo $aviso, "MAIL_MODE = '", MAIL_MODE, "': nenhum e-mail sai, nem o de recuperar senha\n";
}
if (DB_PASS === '') {
    echo $aviso, "o banco está sem senha — aceitável no XAMPP, não em hospedagem\n";
}
if (str_contains(BASE_URL, 'localhost')) {
    echo $aviso, "BASE_URL ainda aponta para localhost: os links dos e-mails sairão errados\n";
}
echo $ok, "fuso: ", date_default_timezone_get(), " (agora ", date('d/m/Y H:i'), ")\n";

echo str_repeat('-', 52), "\n";
echo $problemas === 0
    ? "Tudo pronto. Abra " . BASE_URL . "\n\n"
    : $problemas . " ponto(s) precisam de atenção acima.\n\n";
exit($problemas === 0 ? 0 : 1);
