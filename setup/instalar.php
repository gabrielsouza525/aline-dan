<?php
/**
 * Aline Dan · Conferência e instalação do banco.
 *
 * Pela linha de comando:
 *   php setup/instalar.php                 → diagnostica e popula o que faltar
 *   php setup/instalar.php --admin=e@mail  → cria (ou promove) a administradora
 *
 * Pelo navegador (hospedagem sem terminal):
 *   https://seusite.com/setup/instalar.php?chave=SUA_CHAVE
 *
 *   Só funciona com SETUP_TOKEN preenchido em api/config.php. Enquanto estiver
 *   vazio — que é o padrão — a página recusa qualquer acesso. É de propósito:
 *   esta ferramenta cria conta de administradora, então não pode ficar aberta.
 *   Terminado o serviço, esvazie o SETUP_TOKEN de novo.
 *
 * Existe por causa de um sintoma difícil de ler: quando o banco tem as tabelas
 * mas está vazio, a API responde 200 com uma lista vazia, o site carrega
 * inteiro e não mostra serviço nenhum. Parece PHP quebrado e não é.
 */
declare(strict_types=1);

$raiz = dirname(__DIR__);
require $raiz . '/api/config.php';

const NIVEL_OK = 'ok';
const NIVEL_AVISO = 'aviso';
const NIVEL_ERRO = 'erro';

$web = PHP_SAPI !== 'cli';
$linhas = [];      // [nivel, texto, detalhe]
$problemas = 0;
$fatal = null;

function anota(string $nivel, string $texto, string $detalhe = ''): void
{
    global $linhas, $problemas;
    $linhas[] = [$nivel, $texto, $detalhe];
    if ($nivel === NIVEL_ERRO) {
        $problemas++;
    }
}

// ---------- Porta de entrada do modo navegador ----------
if ($web) {
    $configurado = defined('SETUP_TOKEN') && SETUP_TOKEN !== '';
    $enviada = (string) ($_GET['chave'] ?? $_POST['chave'] ?? '');
    if (!$configurado || $enviada === '' || !hash_equals(SETUP_TOKEN, $enviada)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<title>Acesso negado</title></head><body style="font:16px system-ui;padding:40px">'
            . '<h1 style="font-size:20px">Acesso negado</h1>'
            . '<p>Para liberar, preencha <code>SETUP_TOKEN</code> em <code>api/config.php</code> '
            . 'e abra esta página com <code>?chave=</code> mais o valor escolhido.</p>'
            . '<p style="color:#666;font-size:14px">Esvazie o token de novo assim que terminar.</p>'
            . '</body></html>');
    }
}

// ---------- 1. Conexão ----------
try {
    $pdo = db();
    anota(NIVEL_OK, 'Conectado em ' . DB_NAME . '@' . DB_HOST . ' como ' . DB_USER);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    $fatal = str_contains($msg, 'Unknown database')
        ? 'O banco "' . DB_NAME . '" não existe. Crie-o pelo painel da hospedagem e importe, '
          . 'nesta ordem: schema.sql, migrate-v2.sql, migrate-v3.sql, migrate-v4.sql.'
        : 'Confira DB_HOST, DB_USER e DB_PASS em api/config.php. O servidor respondeu: ' . $msg;
    anota(NIVEL_ERRO, 'Não conectou ao banco', $fatal);
}

if ($fatal === null) {
    // ---------- 2. Tabelas ----------
    $esperadas = ['users', 'services', 'bookings', 'schedule_blocks'];
    $existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $faltando = array_diff($esperadas, $existentes);

    if ($faltando) {
        anota(NIVEL_ERRO, 'Faltam tabelas: ' . implode(', ', $faltando),
            'Importe setup/schema.sql e as migrations pelo phpMyAdmin da hospedagem.');
        $fatal = 'tabelas';
    } else {
        anota(NIVEL_OK, 'As ' . count($esperadas) . ' tabelas existem');
    }
}

if ($fatal === null) {
    // ---------- 3. Migrations ----------
    $colunas = array_column($pdo->query('DESCRIBE bookings')->fetchAll(), 'Field');
    if (!in_array('status', $colunas, true)) {
        anota(NIVEL_ERRO, 'Falta a coluna "status" em bookings',
            'Importe setup/migrate-v4.sql — sem ela o cancelamento quebra.');
    } else {
        anota(NIVEL_OK, 'Migrations aplicadas');
    }

    // ---------- 4. Catálogo ----------
    $qtd = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    if ($qtd === 0) {
        $arquivo = $raiz . '/setup/servicos.sql';
        if (!is_readable($arquivo)) {
            anota(NIVEL_ERRO, 'Catálogo vazio e setup/servicos.sql não foi encontrado',
                'Envie o arquivo para o servidor e recarregue esta página.');
        } else {
            $sem = implode('', array_filter(
                file($arquivo),
                static fn($l) => !str_starts_with(ltrim($l), '--')
            ));
            foreach (array_filter(array_map('trim', explode(";\n", $sem))) as $comando) {
                if ($comando !== '') {
                    $pdo->exec($comando);
                }
            }
            $qtd = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
            anota($qtd > 0 ? NIVEL_OK : NIVEL_ERRO,
                $qtd > 0 ? 'Catálogo populado agora: ' . $qtd . ' serviços' : 'O catálogo continua vazio');
        }
    } else {
        anota(NIVEL_OK, 'Catálogo com ' . $qtd . ' serviços');
    }

    // ---------- 5. Administradora ----------
    $emailAdmin = null;
    $senhaAdmin = null;

    if ($web && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $emailAdmin = trim((string) ($_POST['email'] ?? ''));
        $senhaAdmin = (string) ($_POST['senha'] ?? '');
    } elseif (!$web) {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--admin=')) {
                $emailAdmin = trim(substr($arg, 8));
            }
        }
        if ($emailAdmin !== null) {
            echo "\n  Senha para " . $emailAdmin . ': ';
            $senhaAdmin = trim((string) fgets(STDIN));
        }
    }

    $admins = $pdo->query('SELECT email FROM users WHERE role = "admin"')->fetchAll(PDO::FETCH_COLUMN);

    if ($emailAdmin !== null && $emailAdmin !== '') {
        if (!filter_var($emailAdmin, FILTER_VALIDATE_EMAIL)) {
            anota(NIVEL_ERRO, 'E-mail inválido: ' . $emailAdmin);
        } elseif (strlen((string) $senhaAdmin) < 8) {
            anota(NIVEL_ERRO, 'A senha precisa de pelo menos 8 caracteres');
        } else {
            $hash = password_hash((string) $senhaAdmin, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$emailAdmin]);
            $id = $stmt->fetchColumn();

            if ($id) {
                $pdo->prepare('UPDATE users SET password_hash = ?, role = "admin" WHERE id = ?')
                    ->execute([$hash, $id]);
                anota(NIVEL_OK, 'Conta existente promovida a administradora: ' . $emailAdmin);
            } else {
                $pdo->prepare(
                    'INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "admin")'
                )->execute(['Aline', $emailAdmin, '', $hash]);
                anota(NIVEL_OK, 'Administradora criada: ' . $emailAdmin);
            }
            $admins[] = $emailAdmin;
        }
    } elseif (!$admins) {
        anota(NIVEL_ERRO, 'Nenhuma administradora cadastrada',
            $web ? 'Use o formulário abaixo — o painel não abre sem ela.'
                 : 'Crie com: php setup/instalar.php --admin=aline@exemplo.com');
    } else {
        anota(NIVEL_OK, 'Administradora: ' . implode(', ', array_unique($admins)));
    }
}

// ---------- 6. Avisos de produção ----------
if (MAIL_MODE !== 'smtp') {
    anota(NIVEL_AVISO, 'MAIL_MODE = "' . MAIL_MODE . '"',
        'Nenhum e-mail sai, nem o de recuperar senha. Preencha o SMTP em api/config.php.');
}
if (DB_PASS === '') {
    anota(NIVEL_AVISO, 'O banco está sem senha', 'Aceitável no XAMPP, não em hospedagem.');
}
if (str_contains(BASE_URL, 'localhost')) {
    anota(NIVEL_AVISO, 'BASE_URL ainda aponta para localhost',
        'Os links dos e-mails sairão errados. Troque pelo endereço do site.');
}
anota(NIVEL_OK, 'Fuso: ' . date_default_timezone_get() . ' (agora ' . date('d/m/Y H:i') . ')');

// ---------- Saída ----------
if (!$web) {
    $marca = [NIVEL_OK => '  [ok]   ', NIVEL_AVISO => '  [!]    ', NIVEL_ERRO => '  [ERRO] '];
    echo "\nAline Dan · conferência do banco\n", str_repeat('-', 52), "\n";
    foreach ($linhas as [$nivel, $texto, $detalhe]) {
        echo $marca[$nivel], $texto, "\n";
        if ($detalhe !== '') {
            echo '         ', $detalhe, "\n";
        }
    }
    echo str_repeat('-', 52), "\n";
    echo $problemas === 0
        ? 'Tudo pronto. Abra ' . BASE_URL . "\n\n"
        : $problemas . " ponto(s) precisam de atenção acima.\n\n";
    exit($problemas === 0 ? 0 : 1);
}

$chave = htmlspecialchars((string) ($_GET['chave'] ?? $_POST['chave'] ?? ''), ENT_QUOTES);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalação · Aline Dan</title>
<style>
  :root { --vinho:#360D29; --ok:#1F7A4C; --aviso:#8A6A12; --erro:#A3282A; }
  * { box-sizing: border-box; }
  body { margin:0; padding:40px 20px; background:#F7F4F5; color:var(--vinho);
         font:16px/1.6 system-ui, -apple-system, sans-serif; }
  main { max-width:660px; margin:0 auto; background:#fff; border:1px solid #E8DDE3;
         border-radius:16px; padding:32px; }
  h1 { font-size:22px; margin:0 0 4px; }
  .sub { color:#8A7480; font-size:14px; margin:0 0 24px; }
  ul { list-style:none; padding:0; margin:0 0 24px; }
  li { display:grid; grid-template-columns:auto 1fr; gap:12px; padding:12px 0;
       border-top:1px solid #F0E7EB; align-items:start; }
  .tag { font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
         padding:3px 8px; border-radius:99px; white-space:nowrap; }
  .ok    { background:#E8F5EE; color:var(--ok); }
  .aviso { background:#FDF6E3; color:var(--aviso); }
  .erro  { background:#FBEDED; color:var(--erro); }
  .det { display:block; color:#8A7480; font-size:14px; margin-top:2px; }
  .resumo { padding:14px 16px; border-radius:10px; font-weight:500; margin-bottom:24px; }
  .resumo.bom { background:#E8F5EE; color:var(--ok); }
  .resumo.ruim { background:#FBEDED; color:var(--erro); }
  form { border-top:1px solid #F0E7EB; padding-top:24px; }
  h2 { font-size:16px; margin:0 0 4px; }
  label { display:block; font-size:12px; letter-spacing:.08em; text-transform:uppercase;
          color:#8A7480; margin:16px 0 6px; }
  input { width:100%; padding:12px 14px; border:1px solid #E8DDE3; border-radius:10px;
          font:inherit; color:var(--vinho); background:#fff; }
  input:focus { outline:2px solid rgba(54,13,41,.2); border-color:var(--vinho); }
  button { margin-top:20px; padding:13px 26px; border:0; border-radius:99px;
           background:var(--vinho); color:#fff; font:inherit; font-weight:500;
           letter-spacing:.08em; text-transform:uppercase; font-size:13px; cursor:pointer; }
  .nota { margin-top:24px; padding-top:20px; border-top:1px solid #F0E7EB;
          color:#8A7480; font-size:14px; }
  code { background:#F7F4F5; padding:2px 6px; border-radius:4px; font-size:13px; }
</style>
</head>
<body>
<main>
  <h1>Instalação do banco</h1>
  <p class="sub">Aline Dan · Espaço Lounge</p>

  <div class="resumo <?= $problemas === 0 ? 'bom' : 'ruim' ?>">
    <?= $problemas === 0
        ? 'Tudo pronto — o site pode ser aberto.'
        : $problemas . ' ponto(s) precisam de atenção.' ?>
  </div>

  <ul>
    <?php foreach ($linhas as [$nivel, $texto, $detalhe]): ?>
      <li>
        <span class="tag <?= $nivel ?>"><?= $nivel === NIVEL_OK ? 'ok' : ($nivel === NIVEL_AVISO ? 'atenção' : 'erro') ?></span>
        <span>
          <?= htmlspecialchars($texto) ?>
          <?php if ($detalhe !== ''): ?><span class="det"><?= htmlspecialchars($detalhe) ?></span><?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($fatal === null): ?>
  <form method="post">
    <h2>Criar ou redefinir a administradora</h2>
    <p class="sub">O cadastro do site só cria conta de cliente. A senha é enviada
       direto para o servidor e guardada com criptografia — ela não aparece nesta tela.</p>
    <input type="hidden" name="chave" value="<?= $chave ?>">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" required placeholder="aline@exemplo.com" autocomplete="off">
    <label for="senha">Senha (mínimo 8 caracteres)</label>
    <input type="password" id="senha" name="senha" required minlength="8" autocomplete="new-password">
    <button type="submit">Salvar administradora</button>
  </form>
  <?php endif; ?>

  <p class="nota">
    Terminado o serviço, volte em <code>api/config.php</code> e deixe o
    <code>SETUP_TOKEN</code> vazio. Enquanto ele tiver valor, quem souber o
    endereço e a chave consegue trocar a senha da administradora.
  </p>
</main>
</body>
</html>
