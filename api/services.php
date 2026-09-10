<?php
/**
 * Serviços do salão.
 *   GET  → lista pública (só ativos); com ?all=1 e sessão admin, lista todos
 *   POST → admin: {action: 'create'|'update', service: {...}}
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (isset($_GET['all'])) {
        require_admin();
        json_response(200, ['services' => get_services(false)]);
    }

    $lista = get_services(true);
    if (!$lista) {
        // Instalação nova: popula com o catálogo que veio junto e responde já
        // com ele, para o site não abrir vazio na primeira visita.
        if (seed_services_if_empty(db()) > 0) {
            $lista = get_services(true);
        }
    }
    json_response(200, ['services' => $lista]);
}

if ($method !== 'POST') {
    json_response(405, ['error' => 'Método não permitido.']);
}

require_admin();
$body    = read_json_body();
$action  = (string) ($body['action'] ?? '');
$svc     = is_array($body['service'] ?? null) ? $body['service'] : [];

$name      = trim((string) ($svc['name'] ?? ''));
$category  = trim((string) ($svc['category'] ?? 'Outros'));
$desc      = trim((string) ($svc['description'] ?? ''));
$duration  = (int) ($svc['duration_min'] ?? 0);
$price     = (float) ($svc['price'] ?? 0);
$priceFrom = !empty($svc['price_from']) ? 1 : 0;
$icon      = (string) ($svc['icon'] ?? 'sparkles');
$active    = !empty($svc['active']) ? 1 : 0;

if (mb_strlen($name) < 3 || mb_strlen($name) > 80) {
    json_response(422, ['error' => 'O nome do serviço precisa ter entre 3 e 80 letras.']);
}
if ($category === '' || mb_strlen($category) > 40) {
    json_response(422, ['error' => 'A categoria precisa ter entre 1 e 40 letras.']);
}
if (mb_strlen($desc) > 255) {
    json_response(422, ['error' => 'A descrição pode ter no máximo 255 caracteres.']);
}
if (!in_array($duration, ALLOWED_DURATIONS, true)) {
    json_response(422, ['error' => 'Duração inválida.']);
}
if ($price <= 0 || $price > 99999) {
    json_response(422, ['error' => 'Informe um preço válido.']);
}
if (!in_array($icon, SERVICE_ICONS, true)) {
    $icon = 'sparkles';
}

$pdo = db();

if ($action === 'create') {
    // Gera um id (slug) único a partir do nome
    $ascii = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: 'servico');
    $slug  = trim(preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    $slug = substr($slug !== '' ? $slug : 'servico', 0, 24);
    $base = $slug;
    $n = 1;
    while (get_service($slug, false) !== null) {
        $slug = $base . '-' . (++$n);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO services (id, name, category, description, duration_min, price, price_from, icon, active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(s2.sort_order), 0) + 1 FROM services s2))'
    );
    $stmt->execute([$slug, $name, $category, $desc, $duration, $price, $priceFrom, $icon, $active]);
    json_response(201, ['service' => get_service($slug, false)]);
}

if ($action === 'update') {
    $id = (string) ($svc['id'] ?? '');
    if (get_service($id, false) === null) {
        json_response(404, ['error' => 'Serviço não encontrado.']);
    }
    $stmt = $pdo->prepare(
        'UPDATE services SET name = ?, category = ?, description = ?, duration_min = ?, price = ?, price_from = ?, icon = ?, active = ? WHERE id = ?'
    );
    $stmt->execute([$name, $category, $desc, $duration, $price, $priceFrom, $icon, $active, $id]);
    json_response(200, ['service' => get_service($id, false)]);
}

json_response(422, ['error' => 'Ação inválida.']);
