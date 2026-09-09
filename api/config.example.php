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

const DB_HOST = '127.0.0.1';
const DB_NAME = 'aline_dan';
const DB_USER = 'root';
const DB_PASS = '';

// Endereço público do site (usado nos links de e-mail)
const BASE_URL = 'http://localhost/aline-dan/';

// E-mails
const ADMIN_EMAIL    = 'aline@alinedan.com'; // quem recebe avisos de agendamento
const MAIL_MODE      = 'file';               // 'file' = salva em storage/outbox | 'smtp' = envia de verdade
const MAIL_FROM      = 'contato@alinedan.com';
const MAIL_FROM_NAME = 'Aline Dan · Salão de Beleza';
const SMTP_HOST      = '';                   // ex.: smtp.gmail.com (com senha de app)
const SMTP_PORT      = 465;
const SMTP_USER      = '';
const SMTP_PASS      = '';

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
