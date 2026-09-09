<?php
/**
 * Agendamentos da cliente logada.
 *   GET  [?scope=all]  → lista (futuro por padrão; all inclui histórico)
 *   POST               → cria {service_id, pro_id, date, time}
 *   PUT ?id=N          → remarca {date, time, pro_id} (serviço e preço não mudam)
 *   DELETE ?id=N       → cancela (admin pode cancelar de qualquer cliente)
 * Cria/remarca/cancela avisam a administração por e-mail.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/data.php';
require __DIR__ . '/mailer.php';

$user   = require_user();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---- Listar ----
if ($method === 'GET') {
    $scope = (string) ($_GET['scope'] ?? 'future');
    // Na lista de próximos, cancelado não aparece. No histórico aparece,
    // marcado — é justamente o registro que antes se perdia.
    $where = $scope === 'all'
        ? ''
        : ' AND b.booking_date >= CURDATE() AND b.status = "confirmado"';
    $stmt  = $pdo->prepare(
        'SELECT b.id, b.service_id, b.pro_id,
                DATE_FORMAT(b.booking_date, "%Y-%m-%d") AS date,
                TIME_FORMAT(b.booking_time, "%H:%i")    AS time,
                b.price, b.duration_min, b.status,
                DATE_FORMAT(b.cancelled_at, "%d/%m/%Y") AS cancelled_at,
                b.cancelled_by,
                COALESCE(s.name, b.service_id) AS service_name
           FROM bookings b LEFT JOIN services s ON s.id = b.service_id
          WHERE b.user_id = ?' . $where . '
          ORDER BY b.booking_date, b.booking_time'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['price'] = (float) $r['price'];
        $r['duration_min'] = (int) $r['duration_min'];
        // a tela não recalcula o prazo: quem decide é o servidor
        $r['can_change'] = $r['status'] === 'confirmado'
            && client_can_change($r['date'], $r['time']);
    }
    json_response(200, ['bookings' => $rows]);
}

// ---- Criar ----
if ($method === 'POST') {
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
    $slotError = validate_slot($date, $time);
    if ($slotError !== null) {
        json_response(422, ['error' => $slotError]);
    }

    try {
        $pdo->beginTransaction();
        $ctx = day_context($pdo, $date, true); // trava o dia contra corrida
        if (slot_conflict($ctx, $time, $svc['duration_min'], $proId)) {
            $pdo->rollBack();
            json_response(409, ['error' => 'Esse horário não está mais disponível para este serviço. Escolha outro, por favor.']);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO bookings (user_id, service_id, pro_id, booking_date, booking_time, price, duration_min)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $serviceId, $proId, $date, $time, $svc['price'], $svc['duration_min']]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(500, ['error' => 'Não foi possível salvar. Tente novamente.']);
    }

    // Aviso para a administração (falha de e-mail não afeta o agendamento)
    $d = (new DateTime($date))->format('d/m/Y');
    notify_admin(
        'Novo agendamento: ' . $svc['name'] . ' em ' . $d . ' às ' . $time,
        '<p><strong>' . htmlspecialchars($user['name']) . '</strong> acabou de agendar pelo site:</p>'
        . '<p style="font-size:17px;"><strong>' . htmlspecialchars($svc['name']) . '</strong> ('
        . $svc['duration_min'] . ' min)<br>' . $d . ' às <strong>' . $time . '</strong> · com '
        . PROFESSIONALS[$proId] . '</p>'
        . '<p>Contato: ' . htmlspecialchars($user['phone']) . ' · ' . htmlspecialchars($user['email']) . '</p>'
    );

    json_response(201, ['booking' => [
        'id' => $id, 'service_id' => $serviceId, 'pro_id' => $proId,
        'date' => $date, 'time' => $time,
    ]]);
}

// ---- Remarcar (mantém o serviço; muda data, horário e opcionalmente a profissional) ----
if ($method === 'PUT') {
    $body  = read_json_body();
    $id    = (int) ($_GET['id'] ?? ($body['id'] ?? 0));
    $date  = (string) ($body['date'] ?? '');
    $time  = (string) ($body['time'] ?? '');
    $proId = (string) ($body['pro_id'] ?? '');

    if ($id <= 0) {
        json_response(422, ['error' => 'Agendamento inválido.']);
    }
    if (!array_key_exists($proId, PROFESSIONALS)) {
        json_response(422, ['error' => 'Profissional inválida.']);
    }

    $isAdmin = ($user['role'] ?? 'client') === 'admin';
    $stmt = $pdo->prepare(
        'SELECT b.id, b.user_id, b.service_id, b.pro_id, b.duration_min,
                DATE_FORMAT(b.booking_date, "%Y-%m-%d") AS date,
                TIME_FORMAT(b.booking_time, "%H:%i")    AS time,
                b.status,
                COALESCE(s.name, b.service_id) AS service_name
           FROM bookings b LEFT JOIN services s ON s.id = b.service_id
          WHERE b.id = ?'
    );
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    if ($booking === false || (!$isAdmin && (int) $booking['user_id'] !== (int) $user['id'])) {
        json_response(404, ['error' => 'Agendamento não encontrado.']);
    }

    if ($booking['status'] !== 'confirmado') {
        json_response(422, ['error' => 'Este agendamento foi cancelado e não pode ser remarcado.']);
    }

    // A cliente remarca até 4h antes; a administração, sempre.
    if (!$isAdmin && !client_can_change($booking['date'], $booking['time'])) {
        $passou = minutes_until($booking['date'], $booking['time']) < 0;
        json_response(422, ['error' => $passou
            ? 'Este horário já passou e não pode ser remarcado.'
            : change_deadline_message('remarcado')]);
    }

    $slotError = validate_slot($date, $time, $isAdmin);
    if ($slotError !== null) {
        json_response(422, ['error' => $slotError]);
    }

    if ($date === $booking['date'] && $time === $booking['time'] && $proId === $booking['pro_id']) {
        json_response(422, ['error' => 'Escolha uma data, horário ou profissional diferente.']);
    }

    try {
        $pdo->beginTransaction();
        // ignora o próprio agendamento para ele não bloquear a si mesmo
        $ctx = day_context($pdo, $date, true, $id);
        if (slot_conflict($ctx, $time, (int) $booking['duration_min'], $proId)) {
            $pdo->rollBack();
            json_response(409, ['error' => 'Esse horário não está disponível para este serviço. Escolha outro, por favor.']);
        }
        $stmt = $pdo->prepare(
            'UPDATE bookings
                SET booking_date = ?, booking_time = ?, pro_id = ?, reminder_sent_at = NULL
              WHERE id = ?'
        );
        $stmt->execute([$date, $time, $proId, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(500, ['error' => 'Não foi possível remarcar. Tente novamente.']);
    }

    if (!$isAdmin) {
        $de   = (new DateTime($booking['date']))->format('d/m/Y') . ' às ' . $booking['time'];
        $para = (new DateTime($date))->format('d/m/Y') . ' às ' . $time;
        notify_admin(
            'Remarcação: ' . $booking['service_name'] . ' agora em ' . $para,
            '<p><strong>' . htmlspecialchars($user['name']) . '</strong> remarcou pelo site:</p>'
            . '<p style="font-size:17px;"><strong>' . htmlspecialchars($booking['service_name']) . '</strong></p>'
            . '<p>De: ' . $de . ' · com ' . PROFESSIONALS[$booking['pro_id']] . '<br>'
            . 'Para: <strong>' . $para . '</strong> · com ' . PROFESSIONALS[$proId] . '</p>'
            . '<p>Contato: ' . htmlspecialchars($user['phone']) . '</p>'
        );
    }

    json_response(200, ['booking' => [
        'id' => $id, 'date' => $date, 'time' => $time, 'pro_id' => $proId,
    ]]);
}

// ---- Cancelar ----
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(422, ['error' => 'Agendamento inválido.']);
    }

    $stmt = $pdo->prepare(
        'SELECT b.id, b.user_id, b.status,
                DATE_FORMAT(b.booking_date, "%d/%m/%Y") AS d,
                DATE_FORMAT(b.booking_date, "%Y-%m-%d") AS date_iso,
                TIME_FORMAT(b.booking_time, "%H:%i") AS t,
                COALESCE(s.name, b.service_id) AS service_name,
                COALESCE(u.name, b.guest_name) AS client_name
           FROM bookings b
           LEFT JOIN users u ON u.id = b.user_id
           LEFT JOIN services s ON s.id = b.service_id
          WHERE b.id = ?'
    );
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    $isAdmin = ($user['role'] ?? 'client') === 'admin';
    if ($booking === false || (!$isAdmin && (int) $booking['user_id'] !== (int) $user['id'])) {
        json_response(404, ['error' => 'Agendamento não encontrado.']);
    }

    if ($booking['status'] === 'cancelado') {
        json_response(422, ['error' => 'Este agendamento já estava cancelado.']);
    }

    // A cliente cancela até 4h antes; a administração, sempre.
    if (!$isAdmin && !client_can_change($booking['date_iso'], $booking['t'])) {
        $passou = minutes_until($booking['date_iso'], $booking['t']) < 0;
        json_response(422, ['error' => $passou
            ? 'Este horário já passou.'
            : change_deadline_message('cancelado')]);
    }

    // Não apaga: marca. Assim fica registrado quem desmarcou e quando, e a
    // Aline consegue enxergar quem desmarca sempre em cima da hora.
    $stmt = $pdo->prepare(
        'UPDATE bookings
            SET status = "cancelado", cancelled_at = NOW(), cancelled_by = ?
          WHERE id = ?'
    );
    $stmt->execute([$isAdmin ? 'salao' : 'cliente', $id]);

    if (!$isAdmin) {
        notify_admin(
            'Cancelamento: ' . $booking['service_name'] . ' em ' . $booking['d'] . ' às ' . $booking['t'],
            '<p><strong>' . htmlspecialchars((string) $booking['client_name']) . '</strong> cancelou pelo site:</p>'
            . '<p style="font-size:17px;"><strong>' . htmlspecialchars($booking['service_name'])
            . '</strong><br>' . $booking['d'] . ' às <strong>' . $booking['t'] . '</strong></p>'
            . '<p>O horário voltou a ficar livre na agenda.</p>'
        );
    }

    json_response(200, ['ok' => true]);
}

json_response(405, ['error' => 'Método não permitido.']);
