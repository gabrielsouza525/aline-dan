<?php
/**
 * Bloqueios de agenda (férias, almoço, imprevistos). Restrito a admin.
 *   GET    ?date=YYYY-MM-DD  (ou ?from=&to=)
 *   POST   {pro_id, date, start, end, reason}
 *   DELETE ?id=N
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';

require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $from = (string) ($_GET['from'] ?? ($_GET['date'] ?? ''));
    $to   = (string) ($_GET['to'] ?? $from);
    if (!is_valid_date($from) || !is_valid_date($to)) {
        json_response(422, ['error' => 'Data inválida.']);
    }
    $stmt = $pdo->prepare(
        'SELECT id, pro_id, DATE_FORMAT(block_date, "%Y-%m-%d") AS date,
                TIME_FORMAT(start_time, "%H:%i") AS start,
                TIME_FORMAT(end_time, "%H:%i") AS end, reason
           FROM schedule_blocks
          WHERE block_date BETWEEN ? AND ?
          ORDER BY block_date, start_time'
    );
    $stmt->execute([$from, $to]);
    json_response(200, ['blocks' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body   = read_json_body();
    $proId  = (string) ($body['pro_id'] ?? '');
    $date   = (string) ($body['date'] ?? '');
    $start  = (string) ($body['start'] ?? '');
    $end    = (string) ($body['end'] ?? '');
    $reason = trim((string) ($body['reason'] ?? ''));

    if ($proId !== 'all' && !in_array($proId, PRO_IDS, true)) {
        json_response(422, ['error' => 'Profissional inválida.']);
    }
    if (!is_valid_date($date)) {
        json_response(422, ['error' => 'Data inválida.']);
    }
    $hhmm = '/^([01]\d|2[0-3]):[0-5]\d$/';
    if (!preg_match($hhmm, $start) || !preg_match($hhmm, $end)) {
        json_response(422, ['error' => 'Horário inválido.']);
    }
    $s = hm_to_min($start);
    $e = hm_to_min($end);
    if ($s < OPENING_MIN || $e > CLOSING_MIN || $s >= $e) {
        json_response(422, ['error' => 'O período precisa estar entre 08:00 e 18:00, com início antes do fim.']);
    }
    if (mb_strlen($reason) > 120) {
        json_response(422, ['error' => 'O motivo pode ter no máximo 120 caracteres.']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO schedule_blocks (pro_id, block_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$proId, $date, $start, $end, $reason]);
    json_response(201, ['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM schedule_blocks WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        json_response(404, ['error' => 'Bloqueio não encontrado.']);
    }
    json_response(200, ['ok' => true]);
}

json_response(405, ['error' => 'Método não permitido.']);
