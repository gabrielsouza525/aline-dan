<?php
/**
 * Agendamento de balcão (admin marca pela cliente).
 *   POST {service_id, pro_id, date, time, user_id? , guest_name?, guest_phone?}
 * Aceita horários de hoje já em cima da hora (walk-in).
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';

require_method('POST');
require_admin();

$body      = read_json_body();
$serviceId = (string) ($body['service_id'] ?? '');
$proId     = (string) ($body['pro_id'] ?? '');
$date      = (string) ($body['date'] ?? '');
$time      = (string) ($body['time'] ?? '');

$svc = get_service($serviceId);
if ($svc === null) {
    json_response(422, ['error' => 'Serviço inválido.']);
}
if (!array_key_exists($proId, PROFESSIONALS)) {
    json_response(422, ['error' => 'Profissional inválida.']);
}
$slotError = validate_slot($date, $time, true); // balcão: sem antecedência mínima
if ($slotError !== null) {
    json_response(422, ['error' => $slotError]);
}

// Cliente: cadastrada (user_id) ou convidada (nome + telefone)
$userId     = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$guestName  = trim((string) ($body['guest_name'] ?? ''));
$guestPhone = trim((string) ($body['guest_phone'] ?? ''));

$pdo = db();

if ($userId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch() === false) {
        json_response(422, ['error' => 'Cliente não encontrada.']);
    }
    $guestName = null;
    $guestPhone = null;
} else {
    $digits = preg_replace('/\D/', '', $guestPhone);
    if (mb_strlen($guestName) < 3) {
        json_response(422, ['error' => 'Informe o nome da cliente (mínimo 3 letras).']);
    }
    if (strlen($digits) < 10 || strlen($digits) > 11) {
        json_response(422, ['error' => 'Informe um telefone válido com DDD.']);
    }
    $userId = null;
}

try {
    $pdo->beginTransaction();
    $ctx = day_context($pdo, $date, true);
    if (slot_conflict($ctx, $time, $svc['duration_min'], $proId)) {
        $pdo->rollBack();
        json_response(409, ['error' => 'Esse horário não comporta este serviço (conflito na agenda).']);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO bookings (user_id, service_id, pro_id, booking_date, booking_time,
                               guest_name, guest_phone, price, duration_min)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $serviceId, $proId, $date, $time,
                    $guestName, $guestPhone, $svc['price'], $svc['duration_min']]);
    $id = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(500, ['error' => 'Não foi possível salvar. Tente novamente.']);
}

json_response(201, ['booking' => ['id' => $id]]);
