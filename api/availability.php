<?php
/**
 * Disponibilidade da agenda (público).
 *   GET ?date=YYYY-MM-DD&pro=aline&service=corte → {taken: ["15:00", ...]}
 *     (considera a duração do serviço, bloqueios e o fechamento às 18h)
 *   GET ?next=1 → {date, time} do primeiro horário livre (serviço de 60 min)
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';

require_method('GET');

$pdo = db();

// ---- Próximo horário livre (dica do hero) ----
if (isset($_GET['next'])) {
    $today = new DateTime('today');
    for ($i = 0; $i <= 21; $i++) {
        $day = (clone $today)->modify("+$i days");
        if (in_array((int) $day->format('w'), CLOSED_WEEKDAYS, true)) {
            continue;
        }
        $date = $day->format('Y-m-d');
        $ctx  = day_context($pdo, $date);
        foreach (TIME_SLOTS as $time) {
            if (!slot_conflict($ctx, $time, 60, 'any') && validate_slot($date, $time) === null) {
                json_response(200, ['date' => $date, 'time' => $time]);
            }
        }
    }
    json_response(200, ['date' => null, 'time' => null]);
}

// ---- Horários bloqueados de uma data para um serviço/profissional ----
$date      = (string) ($_GET['date'] ?? '');
$pro       = (string) ($_GET['pro'] ?? 'any');
$serviceId = (string) ($_GET['service'] ?? '');

if (!is_valid_date($date)) {
    json_response(422, ['error' => 'Data inválida.']);
}
if (!array_key_exists($pro, PROFESSIONALS)) {
    json_response(422, ['error' => 'Profissional inválida.']);
}

$duration = 60;
if ($serviceId !== '') {
    $svc = get_service($serviceId);
    if ($svc === null) {
        json_response(422, ['error' => 'Serviço inválido.']);
    }
    $duration = $svc['duration_min'];
}

// Ao remarcar, ignora o próprio agendamento (só o dono ou a administração pode)
$exclude = (int) ($_GET['exclude'] ?? 0);
if ($exclude > 0) {
    $user = current_user();
    if ($user === null) {
        $exclude = 0;
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM bookings WHERE id = ?');
        $stmt->execute([$exclude]);
        $row = $stmt->fetch();
        $isOwner = $row !== false && (int) $row['user_id'] === (int) $user['id'];
        if (!$isOwner && ($user['role'] ?? 'client') !== 'admin') {
            $exclude = 0;
        }
    }
}

$ctx = day_context($pdo, $date, false, $exclude);
json_response(200, ['taken' => taken_start_times($ctx, $duration, $pro)]);
