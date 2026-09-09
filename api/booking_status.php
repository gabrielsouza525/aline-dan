<?php
/**
 * Marca (ou desfaz) falta de comparecimento. Restrito a admin.
 *   POST {id, status}  →  status: 'falta' | 'confirmado'
 *
 * Só depois que o horário começou: antes disso ninguém faltou ainda.
 * Cancelamento é outra coisa e tem rota própria (DELETE em bookings.php) —
 * quem desmarcou avisando não fica marcada como falta.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';

require_method('POST');
require_admin();

$pdo    = db();
$body   = read_json_body();
$id     = (int) ($body['id'] ?? 0);
$status = (string) ($body['status'] ?? '');

if ($id <= 0) {
    json_response(422, ['error' => 'Agendamento inválido.']);
}
if (!in_array($status, ['falta', 'confirmado'], true)) {
    json_response(422, ['error' => 'Situação inválida.']);
}

$stmt = $pdo->prepare(
    'SELECT b.status,
            DATE_FORMAT(b.booking_date, "%Y-%m-%d") AS date,
            TIME_FORMAT(b.booking_time, "%H:%i")    AS time,
            COALESCE(u.name, b.guest_name) AS client_name
       FROM bookings b LEFT JOIN users u ON u.id = b.user_id
      WHERE b.id = ?'
);
$stmt->execute([$id]);
$booking = $stmt->fetch();

if ($booking === false) {
    json_response(404, ['error' => 'Agendamento não encontrado.']);
}
if ($booking['status'] === 'cancelado') {
    json_response(422, ['error' => 'Este horário foi cancelado — não cabe marcar falta.']);
}
if (minutes_until($booking['date'], $booking['time']) > 0) {
    json_response(422, ['error' => 'Este horário ainda não chegou.']);
}

$stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
$stmt->execute([$status, $id]);

json_response(200, [
    'id'     => $id,
    'status' => $status,
    'client' => $booking['client_name'],
]);
