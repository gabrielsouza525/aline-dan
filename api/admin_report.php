<?php
/**
 * Relatório mensal (admin): GET ?month=YYYY-MM
 * Agendamentos, faturamento, clientes novas, top serviços e profissionais —
 * com comparação com o mês anterior.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';

require_method('GET');
require_admin();

$month = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    json_response(422, ['error' => 'Mês inválido.']);
}

$pdo = db();

function month_totals(PDO $pdo, string $month): array
{
    $start = $month . '-01';
    $end   = date('Y-m-t', strtotime($start));
    $stmt  = $pdo->prepare(
        'SELECT COUNT(*) AS bookings, COALESCE(SUM(price), 0) AS revenue
           FROM bookings WHERE booking_date BETWEEN ? AND ?'
    );
    $stmt->execute([$start, $end]);
    $r = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM users
          WHERE role = "client" AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)'
    );
    $stmt->execute([$start, $end]);
    $new = (int) $stmt->fetch()['c'];

    return [
        'bookings'    => (int) $r['bookings'],
        'revenue'     => (float) $r['revenue'],
        'new_clients' => $new,
        'start'       => $start,
        'end'         => $end,
    ];
}

$current  = month_totals($pdo, $month);
$previous = month_totals($pdo, date('Y-m', strtotime($month . '-01 -1 month')));

// Top serviços do mês
$stmt = $pdo->prepare(
    'SELECT b.service_id, COALESCE(s.name, b.service_id) AS name,
            COUNT(*) AS count, COALESCE(SUM(b.price), 0) AS revenue
       FROM bookings b LEFT JOIN services s ON s.id = b.service_id
      WHERE b.booking_date BETWEEN ? AND ?
      GROUP BY b.service_id, s.name
      ORDER BY count DESC, revenue DESC
      LIMIT 5'
);
$stmt->execute([$current['start'], $current['end']]);
$topServices = array_map(static function ($r) {
    return ['name' => $r['name'], 'count' => (int) $r['count'], 'revenue' => (float) $r['revenue']];
}, $stmt->fetchAll());

// Agendamentos por profissional
$stmt = $pdo->prepare(
    'SELECT pro_id, COUNT(*) AS count FROM bookings
      WHERE booking_date BETWEEN ? AND ?
      GROUP BY pro_id ORDER BY count DESC'
);
$stmt->execute([$current['start'], $current['end']]);
$byPro = array_map(static function ($r) {
    return ['pro_id' => $r['pro_id'], 'count' => (int) $r['count']];
}, $stmt->fetchAll());

json_response(200, [
    'month'        => $month,
    'current'      => $current,
    'previous'     => $previous,
    'top_services' => $topServices,
    'by_pro'       => $byPro,
]);
