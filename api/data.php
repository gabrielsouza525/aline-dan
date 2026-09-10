<?php
/**
 * Aline Dan · Regras de negócio da agenda.
 *
 * Um agendamento ocupa o intervalo [início, início + duração do serviço).
 * Um horário só está livre se, em TODOS os instantes do intervalo:
 *   - a profissional escolhida não tem outro atendimento nem bloqueio; e
 *   - o salão tem capacidade (equipe menos profissionais bloqueadas).
 * "Sem preferência" exige apenas capacidade disponível.
 */
declare(strict_types=1);

// Equipe real (fonte: trinks.com/espaco-lounge-aline-dan)
const PROFESSIONALS = [
    'aline'     => 'Aline',
    'amanda'    => 'Amanda',
    'sebastian' => 'Sebastian',
    'dayana'    => 'Dayana',
    'isabelle'  => 'Isabelle',
    'karen'     => 'Karen',
    'nagila'    => 'Nágila Regina',
    'vitoria'   => 'Vitoria',
    'nicolly'   => 'Nicolly',
    'raissa'    => 'Raissa',
    'any'       => 'Sem preferência',
];
const PRO_IDS = ['aline', 'amanda', 'sebastian', 'dayana', 'isabelle', 'karen', 'nagila', 'vitoria', 'nicolly', 'raissa'];
const TEAM_SIZE = 10;

// Terça a sábado, 08h às 18h
// Horários de início a cada 5 minutos, das 08:00 às 18:00.
// Serviços vão de 5 a 300 min; o motor recusa o que não couber até o fechamento.
const TIME_SLOTS = [
    '08:00', '08:05', '08:10', '08:15', '08:20', '08:25', '08:30', '08:35', '08:40', '08:45', '08:50', '08:55',
    '09:00', '09:05', '09:10', '09:15', '09:20', '09:25', '09:30', '09:35', '09:40', '09:45', '09:50', '09:55',
    '10:00', '10:05', '10:10', '10:15', '10:20', '10:25', '10:30', '10:35', '10:40', '10:45', '10:50', '10:55',
    '11:00', '11:05', '11:10', '11:15', '11:20', '11:25', '11:30', '11:35', '11:40', '11:45', '11:50', '11:55',
    '12:00', '12:05', '12:10', '12:15', '12:20', '12:25', '12:30', '12:35', '12:40', '12:45', '12:50', '12:55',
    '13:00', '13:05', '13:10', '13:15', '13:20', '13:25', '13:30', '13:35', '13:40', '13:45', '13:50', '13:55',
    '14:00', '14:05', '14:10', '14:15', '14:20', '14:25', '14:30', '14:35', '14:40', '14:45', '14:50', '14:55',
    '15:00', '15:05', '15:10', '15:15', '15:20', '15:25', '15:30', '15:35', '15:40', '15:45', '15:50', '15:55',
    '16:00', '16:05', '16:10', '16:15', '16:20', '16:25', '16:30', '16:35', '16:40', '16:45', '16:50', '16:55',
    '17:00', '17:05', '17:10', '17:15', '17:20', '17:25', '17:30', '17:35', '17:40', '17:45', '17:50', '17:55',
];
const CLOSED_WEEKDAYS = [0, 1];   // domingo e segunda
const OPENING_MIN = 8 * 60;       // salão abre às 08h
const CLOSING_MIN = 18 * 60;      // salão fecha às 18h
const MAX_DAYS_AHEAD = 60;
const MIN_ADVANCE_MINUTES = 30;

// A cliente cancela ou remarca sozinha até 4h antes. Depois disso a
// profissional já está com a agenda fechada e o horário dificilmente seria
// reocupado — daí em diante é só falando com o salão. A administração não
// tem esse limite.
const CANCEL_LIMIT_MINUTES = 4 * 60;

const ALLOWED_DURATIONS = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100, 105, 110, 115, 120, 125, 130, 135, 140, 145, 150, 155, 160, 165, 170, 175, 180, 185, 190, 195, 200, 205, 210, 215, 220, 225, 230, 235, 240, 245, 250, 255, 260, 265, 270, 275, 280, 285, 290, 295, 300];
const SERVICE_ICONS = ['scissors', 'wind', 'palette', 'sparkles', 'droplet', 'crown', 'star', 'clock', 'eye', 'pencil'];

// ---------- Serviços (tabela `services`) ----------

function get_services(bool $onlyActive = true): array
{
    $sql = 'SELECT id, name, category, description, duration_min, price, price_from, icon, active, sort_order FROM services'
        . ($onlyActive ? ' WHERE active = 1' : '')
        . ' ORDER BY sort_order, name';
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['duration_min'] = (int) $r['duration_min'];
        $r['price']        = (float) $r['price'];
        $r['price_from']   = (bool) $r['price_from'];
        $r['active']       = (bool) $r['active'];
        $r['sort_order']   = (int) $r['sort_order'];
    }
    return $rows;
}

/**
 * Catálogo vazio numa instalação nova? Carrega o que vem junto com o código.
 *
 * Existe para a hospedagem sem terminal: enviados os arquivos e criado o banco,
 * o site se resolve na primeira visita, em vez de ficar mudo esperando alguém
 * rodar um comando que aquele painel não oferece.
 *
 * Só age quando a tabela está vazia, e o que insere é o arquivo versionado —
 * nada vem do visitante. Se duas visitas caírem juntas aqui, o INSERT IGNORE
 * do arquivo evita duplicata. Falhou? Devolve 0 e o site mostra o aviso.
 */
function seed_services_if_empty(PDO $pdo): int
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() > 0) {
        return 0;
    }
    $arquivo = dirname(__DIR__) . '/setup/servicos.sql';
    if (!is_readable($arquivo)) {
        return 0;
    }
    try {
        $linhas = file($arquivo);
        $sql = implode('', array_filter(
            $linhas,
            static fn($l) => !str_starts_with(ltrim($l), '--')
        ));
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $comando) {
            if ($comando !== '') {
                $pdo->exec($comando);
            }
        }
        return (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    } catch (Throwable $e) {
        error_log('seed do catalogo falhou: ' . $e->getMessage());
        return 0;
    }
}

function get_service(string $id, bool $onlyActive = true): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, category, description, duration_min, price, price_from, icon, active FROM services WHERE id = ?'
        . ($onlyActive ? ' AND active = 1' : '')
    );
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if ($r === false) {
        return null;
    }
    $r['duration_min'] = (int) $r['duration_min'];
    $r['price']        = (float) $r['price'];
    $r['price_from']   = (bool) $r['price_from'];
    $r['active']       = (bool) $r['active'];
    return $r;
}

// ---------- Datas e horários ----------

function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function hm_to_min(string $hhmm): int
{
    $p = explode(':', $hhmm);
    return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0);
}

/**
 * Valida data/horário de um agendamento. Retorna mensagem de erro ou null.
 * $relaxed = true (balcão): permite horários de hoje que já passaram.
 */
function validate_slot(string $date, string $time, bool $relaxed = false): ?string
{
    if (!is_valid_date($date)) {
        return 'Data inválida.';
    }
    if (!in_array($time, TIME_SLOTS, true)) {
        return 'Horário inválido.';
    }

    $today = new DateTime('today');
    $day   = new DateTime($date);
    if ($day < $today) {
        return 'Essa data já passou.';
    }
    if ($day > (clone $today)->modify('+' . MAX_DAYS_AHEAD . ' days')) {
        return 'Só aceitamos agendamentos para os próximos ' . MAX_DAYS_AHEAD . ' dias.';
    }
    if (in_array((int) $day->format('w'), CLOSED_WEEKDAYS, true)) {
        return 'O salão não abre nesse dia (domingo e segunda fechamos).';
    }
    if (!$relaxed && $day->format('Y-m-d') === $today->format('Y-m-d')) {
        $now = new DateTime();
        if (((int) $now->format('H')) * 60 + (int) $now->format('i') > hm_to_min($time) - MIN_ADVANCE_MINUTES) {
            return 'Esse horário está muito em cima da hora. Escolha outro, por favor.';
        }
    }
    return null;
}

/** Minutos que faltam para o agendamento começar. Negativo se já passou. */
function minutes_until(string $date, string $time): int
{
    $inicio = new DateTime($date . ' ' . $time);
    return (int) round(($inicio->getTimestamp() - time()) / 60);
}

/** A cliente ainda pode cancelar ou remarcar sozinha? */
function client_can_change(string $date, string $time): bool
{
    return minutes_until($date, $time) >= CANCEL_LIMIT_MINUTES;
}

/** Aviso mostrado quando o prazo já passou. */
function change_deadline_message(string $verbo): string
{
    return 'Faltam menos de ' . (CANCEL_LIMIT_MINUTES / 60) . ' horas para o seu horário, '
        . 'então ele não pode mais ser ' . $verbo . ' pelo site. '
        . 'Fale com o salão pelo WhatsApp (18) 99665-5263 que a gente dá um jeito.';
}

// ---------- Motor de disponibilidade ----------

/**
 * Contexto de um dia: agendamentos (com duração) e bloqueios.
 * $forUpdate trava as linhas dentro de uma transação (evita corrida).
 */
function day_context(PDO $pdo, string $date, bool $forUpdate = false, int $excludeId = 0): array
{
    // $excludeId: ao remarcar, o próprio agendamento não pode bloquear a si mesmo
    $params = [$date];
    // status: cancelado não ocupa horário nenhum
    $sql = 'SELECT pro_id, TIME_FORMAT(booking_time, "%H:%i") AS time, duration_min
              FROM bookings WHERE booking_date = ? AND status = "confirmado"';
    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT pro_id, TIME_FORMAT(start_time, "%H:%i") AS start,
                TIME_FORMAT(end_time, "%H:%i") AS end
           FROM schedule_blocks WHERE block_date = ?'
    );
    $stmt->execute([$date]);

    return ['bookings' => $bookings, 'blocks' => $stmt->fetchAll()];
}

/**
 * O serviço (duração $duration) pode começar em $time para $proId?
 * Varre o intervalo em passos de 5 min verificando ocupação e bloqueios.
 */
function slot_conflict(array $ctx, string $time, int $duration, string $proId): bool
{
    $start = hm_to_min($time);
    $end   = $start + $duration;
    if ($end > CLOSING_MIN) {
        return true; // não terminaria antes de o salão fechar
    }

    for ($t = $start; $t < $end; $t += 5) {
        $occupied = 0;
        $busy = [];
        $blocked = [];

        foreach ($ctx['bookings'] as $b) {
            $s = hm_to_min($b['time']);
            if ($t >= $s && $t < $s + (int) $b['duration_min']) {
                $occupied++;
                if ($b['pro_id'] !== 'any') {
                    $busy[$b['pro_id']] = true;
                }
            }
        }
        foreach ($ctx['blocks'] as $bl) {
            if ($t >= hm_to_min($bl['start']) && $t < hm_to_min($bl['end'])) {
                if ($bl['pro_id'] === 'all') {
                    foreach (PRO_IDS as $p) { $blocked[$p] = true; }
                } else {
                    $blocked[$bl['pro_id']] = true;
                }
            }
        }

        if ($occupied >= TEAM_SIZE - count($blocked)) {
            return true; // salão sem capacidade nesse instante
        }
        if ($proId !== 'any' && (isset($busy[$proId]) || isset($blocked[$proId]))) {
            return true; // a profissional escolhida está ocupada/bloqueada
        }
    }
    return false;
}

/** Horários de início bloqueados para um serviço/profissional em um dia. */
function taken_start_times(array $ctx, int $duration, string $proId): array
{
    $taken = [];
    foreach (TIME_SLOTS as $t) {
        if (slot_conflict($ctx, $t, $duration, $proId)) {
            $taken[] = $t;
        }
    }
    return $taken;
}
