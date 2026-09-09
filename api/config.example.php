<?php
/**
 * Aline Dan · Configuração do banco, sessão e helpers de API.
 *
 * MODELO. Copie este arquivo para api/config.php e preencha os valores.
 * O config.php de verdade fica fora do Git (veja .gitignore), para as
 * senhas do banco e do e-mail não irem parar no repositório.
 *
 * No XAMPP local basta manter root sem senha. Em produção, crie um
 * usuário próprio do MySQL e defina DB_PASS.
 */
declare(strict_types=1);

// Fuso do salão. Sem isto o PHP usa o fuso do servidor — numa hospedagem
// quase sempre UTC — e a agenda inteira sai errada: “hoje” vira outro dia,
// e horários ainda válidos passam a ser recusados por “já passou”.
const SALON_TIMEZONE = 'America/Sao_Paulo';
date_default_timezone_set(SALON_TIMEZONE);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'aline_dan';
const DB_USER = 'root';
const DB_PASS = '';

// Endereço público do site (usado nos links de e-mail)
const BASE_URL = 'http://localhost/aline-dan/';

// ---------- E-mails ----------
// Com MAIL_MODE em 'file' nada é enviado: cada mensagem vira um .html em
// storage/outbox, para conferir em desenvolvimento. Preencha o SMTP abaixo e
// troque para 'smtp' quando o site for ao ar — sem isso a recuperação de senha
// não funciona, porque o link de redefinição nunca chega à cliente.
const MAIL_MODE      = 'file';
const ADMIN_EMAIL    = 'aline@alinedan.com';          // quem recebe os avisos de agendamento
const MAIL_FROM      = 'contato@alinedan.com';        // use o MESMO endereço do SMTP_USER
const MAIL_FROM_NAME = 'Aline Dan · Salão de Beleza';

// Servidor de saída.
//   Gmail: smtp.gmail.com, porta 465, segurança 'ssl', e uma SENHA DE APP
//     (myaccount.google.com > Segurança > Verificação em duas etapas > Senhas de
//     app). A senha normal da conta é recusada.
//   Hospedagem própria: costuma ser mail.seudominio.com.br na porta 587 com 'tls'.
// Teste antes de depender disso:  php setup/testar_email.php seu@email.com
const SMTP_HOST     = '';
const SMTP_PORT     = 465;
const SMTP_SECURITY = 'ssl';                          // 'ssl' na 465 · 'tls' na 587
const SMTP_USER     = '';
const SMTP_PASS     = '';
const SMTP_TIMEOUT  = 15;                             // segundos de espera pelo servidor

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // O MySQL tem fuso próprio: alinha com o do PHP para NOW() e CURDATE()
        // baterem com o que o site calcula.
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('alinedan_sess');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

function json_response(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw !== false ? $raw : '', true);
    return is_array($data) ? $data : [];
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_response(405, ['error' => 'Método não permitido.']);
    }
}

/** Usuário logado (ou null). */
function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT id, name, email, phone, role,
                DATE_FORMAT(created_at, "%d/%m/%Y") AS member_since,
                (email_verified_at IS NOT NULL) AS email_verified
           FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user === false) {
        return null;
    }
    $user['email_verified'] = (bool) $user['email_verified'];
    return $user;
}

/** Encerra com 401 se não houver usuário logado. */
function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(401, ['error' => 'Você precisa entrar na sua conta.']);
    }
    return $user;
}

/** Encerra com 403 se o usuário logado não for administradora. */
function require_admin(): array
{
    $user = require_user();
    if (($user['role'] ?? 'client') !== 'admin') {
        json_response(403, ['error' => 'Acesso restrito à administração do salão.']);
    }
    return $user;
}
