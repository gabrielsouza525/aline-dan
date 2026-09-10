<?php
/**
 * Aline Dan · Instalação e conferência do banco.
 *
 * Pela linha de comando:
 *   php setup/instalar.php                 → diagnostica e popula o que faltar
 *   php setup/instalar.php --admin=e@mail  → cria (ou promove) a administradora
 *
 * Pelo navegador (hospedagem sem terminal):
 *   https://seusite.com/setup/instalar.php
 *
 *   Sem api/config.php, ou com ele apontando para um banco que não responde,
 *   a página abre o formulário de configuração — é o único momento em que ela
 *   dispensa a chave, porque ainda não há nada a proteger.
 *
 *   Com o banco de pé, ela exige SETUP_TOKEN preenchido em api/config.php e
 *   a mesma chave em ?chave=. Terminado o serviço, esvazie o token: esta
 *   ferramenta cria conta de administradora e não pode ficar aberta.
 */
declare(strict_types=1);

$raiz = dirname(__DIR__);
$caminhoConfig = $raiz . '/api/config.php';
$temConfig = is_file($caminhoConfig);
if ($temConfig) {
    require $caminhoConfig;
}

const NIVEL_OK = 'ok';
const NIVEL_AVISO = 'aviso';
const NIVEL_ERRO = 'erro';

$web = PHP_SAPI !== 'cli';
$linhas = [];
$problemas = 0;
$fatal = null;
$pdo = null;

function anota(string $nivel, string $texto, string $detalhe = ''): void
{
    global $linhas, $problemas;
    $linhas[] = [$nivel, $texto, $detalhe];
    if ($nivel === NIVEL_ERRO) {
        $problemas++;
    }
}

/** Monta o conteúdo do config.php a partir do modelo. */
function montar_config(string $modelo, array $d): string
{
    $subs = [
        "/const DB_HOST = '[^']*';/"  => "const DB_HOST = '" . addslashes($d['host']) . "';",
        "/const DB_NAME = '[^']*';/"  => "const DB_NAME = '" . addslashes($d['nome']) . "';",
        "/const DB_USER = '[^']*';/"  => "const DB_USER = '" . addslashes($d['usuario']) . "';",
        "/const DB_PASS = '[^']*';/"  => "const DB_PASS = '" . addslashes($d['senha']) . "';",
        "/const BASE_URL = '[^']*';/" => "const BASE_URL = '" . addslashes($d['url']) . "';",
    ];
    return preg_replace(array_keys($subs), array_values($subs), $modelo, 1);
}

// =====================================================================
// Modo configuração: sem config.php, ou com banco que não responde
// =====================================================================
$precisaConfigurar = !$temConfig;
if ($temConfig) {
    try {
        $pdo = db();
    } catch (Throwable $e) {
        $precisaConfigurar = true;
        $erroConexao = $e->getMessage();
    }
}

if ($web && $precisaConfigurar) {
    // Com config.php de pé e token definido, a chave continua valendo.
    if ($temConfig && defined('SETUP_TOKEN') && SETUP_TOKEN !== '') {
        $enviada = (string) ($_GET['chave'] ?? $_POST['chave'] ?? '');
        if (!hash_equals(SETUP_TOKEN, $enviada)) {
            http_response_code(403);
            exit('<!DOCTYPE html><meta charset="utf-8"><title>Acesso negado</title>'
                . '<body style="font:16px system-ui;padding:40px"><h1 style="font-size:20px">Acesso negado</h1>'
                . '<p>Abra esta página com <code>?chave=</code> mais o valor do '
                . '<code>SETUP_TOKEN</code> de <code>api/config.php</code>.</p>');
        }
    }

    $modelo = @file_get_contents($raiz . '/api/config.example.php');
    $dados = [
        'host'    => trim((string) ($_POST['host'] ?? (defined('DB_HOST') ? DB_HOST : 'localhost'))),
        'nome'    => trim((string) ($_POST['nome'] ?? (defined('DB_NAME') ? DB_NAME : ''))),
        'usuario' => trim((string) ($_POST['usuario'] ?? (defined('DB_USER') ? DB_USER : ''))),
        'senha'   => (string) ($_POST['senha'] ?? ''),
        'url'     => trim((string) ($_POST['url'] ?? '')),
    ];
    if ($dados['url'] === '') {
        $esquema = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        $base = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/setup/instalar.php'));
        $dados['url'] = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . rtrim($base, '/') . '/';
    }

    $aviso = null;
    $conteudo = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['nome'])) {
        if ($modelo === false) {
            $aviso = 'Não achei api/config.example.php no servidor. Envie a pasta api/ inteira por FTP.';
        } elseif ($dados['nome'] === '' || $dados['usuario'] === '') {
            $aviso = 'Preencha ao menos o nome do banco e o usuário.';
        } else {
            try {
                new PDO(
                    'mysql:host=' . $dados['host'] . ';dbname=' . $dados['nome'] . ';charset=utf8mb4',
                    $dados['usuario'],
                    $dados['senha'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $conteudo = montar_config($modelo, $dados);
                if (@file_put_contents($caminhoConfig, $conteudo) !== false) {
                    // Recarrega já com o config novo em vigor
                    header('Location: ' . basename(__FILE__));
                    exit;
                }
                $aviso = 'A conexão funcionou, mas o servidor não deixou gravar o arquivo. '
                    . 'Copie o conteúdo abaixo e salve como api/config.php pelo gerenciador de arquivos.';
            } catch (Throwable $e) {
                $aviso = 'Não consegui conectar com esses dados. O MySQL respondeu: ' . $e->getMessage();
            }
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex, nofollow">
      <title>Configurar o banco · Aline Dan</title>
      <style>
        :root { --vinho:#360D29; --erro:#A3282A; }
        * { box-sizing:border-box; }
        body { margin:0; padding:40px 20px; background:#F7F4F5; color:var(--vinho);
               font:16px/1.6 system-ui,-apple-system,sans-serif; }
        main { max-width:600px; margin:0 auto; background:#fff; border:1px solid #E8DDE3;
               border-radius:16px; padding:32px; }
        h1 { font-size:22px; margin:0 0 4px; }
        .sub { color:#8A7480; font-size:14px; margin:0 0 24px; }
        .alerta { background:#FBEDED; color:var(--erro); padding:14px 16px;
                  border-radius:10px; font-size:14px; margin-bottom:24px; }
        label { display:block; font-size:12px; letter-spacing:.08em; text-transform:uppercase;
                color:#8A7480; margin:18px 0 6px; }
        .dica { text-transform:none; letter-spacing:0; font-size:13px; color:#8A7480; margin-top:2px; }
        input { width:100%; padding:12px 14px; border:1px solid #E8DDE3; border-radius:10px;
                font:inherit; color:var(--vinho); }
        input:focus { outline:2px solid rgba(54,13,41,.2); border-color:var(--vinho); }
        button { margin-top:24px; padding:13px 26px; border:0; border-radius:99px;
                 background:var(--vinho); color:#fff; font:inherit; font-weight:500;
                 letter-spacing:.08em; text-transform:uppercase; font-size:13px; cursor:pointer; }
        textarea { width:100%; height:240px; margin-top:16px; font:13px ui-monospace,monospace;
                   border:1px solid #E8DDE3; border-radius:10px; padding:12px; }
      </style>
    </head>
    <body>
    <main>
      <h1>Configurar o banco</h1>
      <p class="sub">Aline Dan · Espaço Lounge</p>

      <?php if ($aviso): ?><div class="alerta"><?= htmlspecialchars($aviso) ?></div><?php endif; ?>

      <p class="sub">Preencha com os dados que a hospedagem mostra no painel do MySQL.
         Vou testar a conexão antes de salvar — nada é gravado se ela não funcionar.</p>

      <form method="post">
        <input type="hidden" name="chave" value="<?= htmlspecialchars((string) ($_GET['chave'] ?? $_POST['chave'] ?? ''), ENT_QUOTES) ?>">
        <label for="host">Servidor
          <span class="dica">Quase sempre <code>localhost</code>.</span></label>
        <input id="host" name="host" value="<?= htmlspecialchars($dados['host']) ?>" required>

        <label for="nome">Nome do banco</label>
        <input id="nome" name="nome" value="<?= htmlspecialchars($dados['nome']) ?>" required autocomplete="off">

        <label for="usuario">Usuário do banco</label>
        <input id="usuario" name="usuario" value="<?= htmlspecialchars($dados['usuario']) ?>" required autocomplete="off">

        <label for="senha">Senha do banco</label>
        <input id="senha" name="senha" type="password" autocomplete="new-password">

        <label for="url">Endereço do site
          <span class="dica">Usado nos links dos e-mails. Com a barra no fim.</span></label>
        <input id="url" name="url" value="<?= htmlspecialchars($dados['url']) ?>" required>

        <button type="submit">Testar e salvar</button>
      </form>

      <?php if ($conteudo !== null): ?>
        <label>Conteúdo para salvar como api/config.php</label>
        <textarea readonly onclick="this.select()"><?= htmlspecialchars($conteudo) ?></textarea>
      <?php endif; ?>
    </main>
    </body>
    </html>
    <?php
    exit;
}

// =====================================================================
// Daqui em diante, o banco responde
// =====================================================================
if (!$temConfig) {
    exit("\nFalta api/config.php. Copie api/config.example.php e preencha os dados do banco.\n\n");
}

if ($web) {
    $configurado = defined('SETUP_TOKEN') && SETUP_TOKEN !== '';
    $enviada = (string) ($_GET['chave'] ?? $_POST['chave'] ?? '');
    if (!$configurado || $enviada === '' || !hash_equals(SETUP_TOKEN, $enviada)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<title>Acesso negado</title></head><body style="font:16px system-ui;padding:40px">'
            . '<h1 style="font-size:20px">Acesso negado</h1>'
            . '<p>O banco está configurado e respondendo. Para abrir esta página, preencha '
            . '<code>SETUP_TOKEN</code> em <code>api/config.php</code> e use '
            . '<code>?chave=</code> com esse valor.</p>'
            . '<p style="color:#666;font-size:14px">Esvazie o token de novo assim que terminar.</p>'
            . '</body></html>');
    }
}

if ($pdo === null) {
    $pdo = db();
}
anota(NIVEL_OK, 'Conectado em ' . DB_NAME . '@' . DB_HOST . ' como ' . DB_USER);

// ---------- Tabelas ----------
$esperadas = ['users', 'services', 'bookings', 'schedule_blocks'];
$existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$faltando = array_diff($esperadas, $existentes);

if ($faltando) {
    anota(NIVEL_ERRO, 'Faltam tabelas: ' . implode(', ', $faltando),
        'Importe setup/banco-completo.sql pelo phpMyAdmin — ele cria as tabelas e traz os serviços.');
    $fatal = 'tabelas';
} else {
    anota(NIVEL_OK, 'As ' . count($esperadas) . ' tabelas existem');
}

if ($fatal === null) {
    $colunas = array_column($pdo->query('DESCRIBE bookings')->fetchAll(), 'Field');
    if (!in_array('status', $colunas, true)) {
        anota(NIVEL_ERRO, 'Falta a coluna "status" em bookings',
            'Importe setup/banco-completo.sql — sem ela o cancelamento quebra.');
    } else {
        anota(NIVEL_OK, 'Migrations aplicadas');
    }

    // ---------- Catálogo ----------
    $qtd = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    if ($qtd === 0) {
        require_once $raiz . '/api/data.php';
        $qtd = seed_services_if_empty($pdo);
        anota($qtd > 0 ? NIVEL_OK : NIVEL_ERRO,
            $qtd > 0 ? 'Catálogo populado agora: ' . $qtd . ' serviços'
                     : 'O catálogo está vazio e não consegui popular',
            $qtd > 0 ? '' : 'Importe setup/banco-completo.sql pelo phpMyAdmin.');
    } else {
        anota(NIVEL_OK, 'Catálogo com ' . $qtd . ' serviços');
    }

    // ---------- Administradora ----------
    $emailAdmin = null;
    $senhaAdmin = null;

    if ($web && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['email'])) {
        $emailAdmin = trim((string) $_POST['email']);
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

// ---------- Avisos de produção ----------
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
