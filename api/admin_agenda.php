<?php
/**
 * Agenda para a administração (todas as clientes). Restrito a admin.
 *   GET ?date=YYYY-MM-DD       → um dia (com bloqueios e flag "fechado")
 *   GET ?from=...&to=...       → intervalo (visão da semana)
 * Ao abrir, dispara os lembretes pendentes de amanhã (item automático).
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';
require __DIR__ . '/mailer.php';

require_method('GET');
require_admin();

$pdo = db();

// Lembretes de amanhã (lazy cron): roda no máximo o que estiver pendente
try {
    send_due_reminders($pdo);
} catch (Throwable $e) {
    // lembrete não pode derrubar o painel
}

function fetch_bookings(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, b.service_id, b.pro_id,
                DATE_FORMAT(b.booking_date, "%Y-%m-%d") AS date,
                TIME_FORMAT(b.booking_time, "%H:%i")    AS time,
                b.price, b.duration_min,
                COALESCE(s.name, b.service_id)     AS service_name,
                COALESCE(u.name, b.guest_name)     AS client_name,
                COALESCE(u.phone, b.guest_phone)   AS client_phone,
                u.email                            AS client_email,
                b.status,
                (b.user_id IS NULL)                AS is_guest
           FROM bookings b
           LEFT JOIN users u ON u.id = b.user_id
           LEFT JOIN services s ON s.id = b.service_id
          -- falta continua na grade: foi um horário ocupado de verdade, e a
          -- Aline precisa poder desfazer se marcar errado
          WHERE b.booking_date BETWEEN ? AND ? AND b.status IN ("confirmado", "falta")
          ORDER BY b.booking_date, b.booking_time, b.pro_id'
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['price']        = (float) $r['price'];
        $r['duration_min'] = (int) $r['duration_min'];
        $r['is_guest']     = (bool) $r['is_guest'];
    }
    return $rows;
}

/** Cancelamentos do período — o registro que antes sumia com o DELETE. */
function fetch_cancellations(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, TIME_FORMAT(b.booking_time, "%H:%i") AS time,
                DATE_FORMAT(b.booking_date, "%Y-%m-%d") AS date,
                DATE_FORMAT(b.cancelled_at, "%d/%m às %H:%i") AS cancelled_at,
                b.cancelled_by, b.pro_id, b.price,
                COALESCE(s.name, b.service_id)   AS service_name,
                COALESCE(u.name, b.guest_name)   AS client_name
           FROM bookings b
           LEFT JOIN users u ON u.id = b.user_id
           LEFT JOIN services s ON s.id = b.service_id
          WHERE b.booking_date BETWEEN ? AND ? AND b.status = "cancelado"
          ORDER BY b.booking_date, b.booking_time'
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['price'] = (float) $r['price'];
    }
    return $rows;
}

function fetch_blocks(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT id, pro_id, DATE_FORMAT(block_date, "%Y-%m-%d") AS date,
                TIME_FORMAT(start_time, "%H:%i") AS start,
                TIME_FORMAT(end_time, "%H:%i")   AS end, reason
           FROM schedule_blocks WHERE block_date BETWEEN ? AND ?
          ORDER BY block_date, start_time'
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

// ---- Intervalo (semana) ----
if (isset($_GET['from'], $_GET['to'])) {
    $from = (string) $_GET['from'];
    $to   = (string) $_GET['to'];
    if (!is_valid_date($from) || !is_valid_date($to) || $from > $to) {
        json_response(422, ['error' => 'Intervalo inválido.']);
    }
    json_response(200, [
        'from'     => $from,
        'to'       => $to,
        'bookings'  => fetch_bookings($pdo, $from, $to),
        'blocks'    => fetch_blocks($pdo, $from, $to),
        'cancelled' => fetch_cancellations($pdo, $from, $to),
    ]);
}

// ---- Um dia ----
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!is_valid_date($date)) {
    json_response(422, ['error' => 'Data inválida.']);
}

json_response(200, [
    'date'     => $date,
    'closed'   => in_array((int) (new DateTime($date))->format('w'), CLOSED_WEEKDAYS, true),
    'bookings'  => fetch_bookings($pdo, $date, $date),
    'blocks'    => fetch_blocks($pdo, $date, $date),
    'cancelled' => fetch_cancellations($pdo, $date, $date),
]);
