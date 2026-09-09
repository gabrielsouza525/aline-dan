<?php
/** Busca de clientes para o agendamento de balcão (admin): GET ?q=texto. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('GET');
require_admin();

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    json_response(200, ['clients' => []]);
}

$like = '%' . $q . '%';
$stmt = db()->prepare(
    'SELECT id, name, email, phone FROM users
      WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
      ORDER BY name LIMIT 8'
);
$stmt->execute([$like, $like, $like]);

json_response(200, ['clients' => $stmt->fetchAll()]);
