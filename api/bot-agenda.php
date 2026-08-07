<?php
// api/bot-agenda.php — Puente entre el fisiobot (WhatsApp) y la agenda de la fisioapp.
// Acceso: header x-bot-token (el mismo KAMINAR_API_TOKEN del .env del fisiobot).
//   GET  ?accion=disponibilidad&dias=14&antelacion=120
//   POST {accion: registrar|estado|confirmar-por-telefono|cancelar-por-telefono|citas-del-dia, ...}
header('Content-Type: application/json');
require_once '../db.php';

// --- Config compartida con el bot ---
function cargarEnv(): array {
    static $env = null;
    if ($env !== null) return $env;
    $env = [];
    $ruta = '/var/www/fisiobot/bot/.env';
    if (file_exists($ruta)) {
        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            if (preg_match('/^([A-Z_]+)=(.*)$/', trim($linea), $m)) $env[$m[1]] = trim($m[2]);
        }
    }
    return $env;
}

function autorizado(): bool {
    $env = cargarEnv();
    $esperado = $env['KAMINAR_API_TOKEN'] ?? '';
    $recibido = $_SERVER['HTTP_X_BOT_TOKEN'] ?? '';
    return $esperado !== '' && hash_equals($esperado, $recibido);
}

function clinicaAgenda(): array {
    $ruta = '/var/www/fisiobot/bot/config/clinica.json';
    $c = json_decode(file_get_contents($ruta), true);
    return $c['agenda'] ?? [];
}

$MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$DIAS = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];

function ahoraLima(): DateTime { return new DateTime('now', new DateTimeZone('America/Lima')); }
function aMinutos(string $hhmm): int { [$h, $m] = array_map('intval', explode(':', $hhmm)); return $h * 60 + $m; }
function aHora12(int $mins): string {
    $h = intdiv($mins, 60); $m = str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT);
    $suf = $h >= 12 ? 'p. m.' : 'a. m.';
    $h = $h % 12; if ($h === 0) $h = 12;
    return "$h:$m $suf";
}
function a24(int $mins): string { return str_pad((string)intdiv($mins, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT); }
function textoFecha(DateTime $f): string {
    global $MESES, $DIAS;
    return $DIAS[(int)$f->format('w')] . ', ' . $f->format('j') . ' de ' . $MESES[(int)$f->format('n') - 1];
}

// "viernes, 7 de agosto" → 'Y-m-d' (año en curso; si quedó 60+ días atrás, el siguiente)
function parseFecha(string $texto): ?string {
    global $MESES;
    $t = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $texto));
    if (!preg_match('/(\d{1,2})\s*de\s*([a-z]+)/', $t, $m)) return null;
    $mes = array_search($m[2], $MESES);
    if ($mes === false) return null;
    $ahora = ahoraLima();
    $anio = (int)$ahora->format('Y');
    $fecha = new DateTime("$anio-" . ($mes + 1) . "-{$m[1]}", new DateTimeZone('America/Lima'));
    $hoy = new DateTime($ahora->format('Y-m-d'), new DateTimeZone('America/Lima'));
    if (($hoy->getTimestamp() - $fecha->getTimestamp()) > 60 * 86400) $fecha->modify('+1 year');
    return $fecha->format('Y-m-d');
}

function parseHora(string $texto): ?int {
    $t = mb_strtolower(str_replace(['.', ' '], '', $texto));
    if (!preg_match('/(\d{1,2}):(\d{2})(am|pm)/', $t, $m)) return null;
    $h = ((int)$m[1]) % 12;
    if ($m[3] === 'pm') $h += 12;
    return $h * 60 + (int)$m[2];
}

function terapeutaDefault(PDO $pdo): int {
    $row = pdoQuery($pdo, "SELECT id FROM users WHERE role = 'therapist' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
    if ($row) return (int)$row['id'];
    $row = pdoQuery($pdo, "SELECT id FROM users WHERE role IN ('admin','receptionist') ORDER BY id LIMIT 1")->fetch();
    return (int)($row['id'] ?? 1);
}

// Sesiones que traslaparían [inicioMin, inicioMin+duracion) ese día.
function solapadas(PDO $pdo, string $fecha, int $inicioMin, int $dur): int {
    $inicio = a24($inicioMin);
    $fin = a24($inicioMin + $dur);
    $row = pdoQuery($pdo,
        "SELECT COUNT(*) AS total FROM appointments
         WHERE appointment_date = ? AND status != 'cancelled' AND start_time < ? AND end_time > ?",
        [$fecha, $fin, $inicio]
    )->fetch();
    return (int)($row['total'] ?? 0);
}

// Próxima cita del paciente por teléfono (últimos 9 dígitos).
function citaProximaPorTelefono(PDO $pdo, string $telefono, array $estados): ?array {
    $ult9 = substr(preg_replace('/\D/', '', $telefono), -9);
    if (strlen($ult9) < 9) return null;
    $in = implode(',', array_fill(0, count($estados), '?'));
    $rows = pdoQuery($pdo,
        "SELECT a.id, a.appointment_date, a.start_time, u.name AS paciente
         FROM appointments a JOIN users u ON u.id = a.patient_id
         WHERE (u.phone LIKE ? OR u.phone LIKE ?) AND a.status IN ($in) AND a.appointment_date >= CURDATE()
         ORDER BY a.appointment_date, a.start_time LIMIT 5",
        array_merge(["%$ult9", "%+$ult9"], $estados)
    )->fetchAll();
    $ahora = ahoraLima();
    foreach ($rows as $c) {
        if ($c['appointment_date'] > $ahora->format('Y-m-d')) return $c;
        if ($c['appointment_date'] === $ahora->format('Y-m-d') && aMinutos(substr($c['start_time'], 0, 5)) > (int)$ahora->format('H') * 60 + (int)$ahora->format('i')) return $c;
    }
    return null;
}

if (!autorizado()) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }

$accion = $_GET['accion'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if ($body) $accion = $body['accion'] ?? $accion;

// ---------- DISPONIBILIDAD ----------
if ($accion === 'disponibilidad') {
    $ag = clinicaAgenda();
    $dias = min((int)($_GET['dias'] ?? 14), 30);
    $antelacion = (int)($_GET['antelacion'] ?? 120);
    $dur = $ag['duracionSesionMin'] ?? 60;
    $paso = $ag['intervaloTurnoMin'] ?? 30;
    $cap = $ag['capacidadParalela'] ?? 4;
    $diasSemana = $ag['diasSemana'] ?? [1,2,3,4,5,6];
    $cerrados = $ag['diasCerrados'] ?? [];

    $ahora = ahoraLima();
    $cupos = [];
    for ($d = 0; $d < $dias; $d++) {
        $fecha = (clone $ahora)->modify("+$d days");
        if (!in_array((int)$fecha->format('w'), $diasSemana)) continue;
        $fechaTexto = textoFecha($fecha);
        $fechaIso = $fecha->format('Y-m-d');
        $cerrado = false;
        foreach ($cerrados as $c) {
            $ci = parseFecha($c) ?? $c;
            if ($ci === $fechaIso) { $cerrado = true; break; }
        }
        if ($cerrado) continue;

        $horas = [];
        foreach ($ag['bloques'] ?? [] as $b) {
            for ($t = aMinutos($b['inicio']); $t + $dur <= aMinutos($b['fin']); $t += $paso) {
                if ($d === 0 && $t <= (int)$ahora->format('H') * 60 + (int)$ahora->format('i') + $antelacion) continue;
                if (solapadas($pdo, $fechaIso, $t, $dur) < $cap) $horas[] = aHora12($t);
            }
        }
        if ($horas) $cupos[] = ['fecha' => $fechaTexto, 'horas' => $horas];
    }
    echo json_encode(['cupos' => $cupos, 'sinCupos' => count($cupos) === 0]);
    exit;
}

// ---------- REGISTRAR ----------
if ($accion === 'registrar') {
    $nombre = trim($body['nombre'] ?? '');
    $dni = trim($body['dni'] ?? '');
    $motivo = trim($body['motivo'] ?? 'Consulta general');
    $telefono = trim($body['telefono'] ?? '');
    $fechaIso = parseFecha($body['fecha'] ?? '');
    $mins = parseHora($body['hora'] ?? '');
    if (!$nombre || !$dni || !$fechaIso || $mins === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos obligatorios (nombre, dni, fecha, hora)']);
        exit;
    }
    $ag = clinicaAgenda();
    $dur = $ag['duracionSesionMin'] ?? 60;
    $cap = $ag['capacidadParalela'] ?? 4;
    if (solapadas($pdo, $fechaIso, $mins, $dur) >= $cap) {
        http_response_code(409);
        echo json_encode(['error' => 'Ese horario ya está lleno']);
        exit;
    }

    // Paciente: por DNI; si no existe se crea con su código de portal.
    $paciente = pdoQuery($pdo, "SELECT * FROM users WHERE dni = ? AND role = 'patient' LIMIT 1", [$dni])->fetch();
    $codigoNuevo = null;
    if ($paciente) {
        pdoQuery($pdo, "UPDATE users SET name = ?, phone = COALESCE(NULLIF(?, ''), phone) WHERE id = ?", [$nombre, $telefono, $paciente['id']]);
        $paciente['name'] = $nombre;
        if (empty($paciente['patient_code'])) {
            $codigoNuevo = 'FISIO-' . strtoupper(substr(md5(uniqid()), 0, 6));
            pdoQuery($pdo, "UPDATE users SET patient_code = ? WHERE id = ?", [$codigoNuevo, $paciente['id']]);
        }
    } else {
        $codigoNuevo = 'FISIO-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $email = $dni . '@paciente.fisio.kaminar.pe';
        $pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        pdoQuery($pdo,
            "INSERT INTO users (role, name, dni, email, password, phone, patient_code, is_active) VALUES ('patient', ?, ?, ?, ?, ?, ?, 1)",
            [$nombre, $dni, $email, $pass, $telefono, $codigoNuevo]
        );
        // La auditoría de pdoQuery corrompe lastInsertId: se lee el id real por DNI.
        $paciente = pdoQuery($pdo, "SELECT * FROM users WHERE dni = ? AND role = 'patient' LIMIT 1", [$dni])->fetch();
        if (!$paciente) {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo crear el paciente']);
            exit;
        }
    }

    pdoQuery($pdo,
        "INSERT INTO appointments (patient_id, therapist_id, appointment_date, start_time, end_time, type, notes, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)",
        [
            $paciente['id'], terapeutaDefault($pdo), $fechaIso, a24($mins), a24($mins + $dur),
            $motivo, 'Registrada por el bot de WhatsApp' . ($telefono ? " ($telefono)" : ''), terapeutaDefault($pdo),
        ]
    );
    // La auditoría de pdoQuery interfiere con lastInsertId: se lee el id real.
    $fila = pdoQuery($pdo,
        "SELECT id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND start_time = ? ORDER BY id DESC LIMIT 1",
        [$paciente['id'], $fechaIso, a24($mins)]
    )->fetch();
    $citaId = (int)($fila['id'] ?? 0);

    echo json_encode([
        'ok' => true,
        'citaId' => $citaId,
        'codigoPaciente' => $codigoNuevo ?: ($paciente['patient_code'] ?? null),
        'pacienteNuevo' => $codigoNuevo !== null,
    ]);
    exit;
}

// ---------- ESTADO ----------
if ($accion === 'estado') {
    $mapa = ['confirmada' => 'scheduled', 'cancelada' => 'cancelled', 'atendida' => 'completed', 'pendiente' => 'scheduled'];
    $estado = $mapa[$body['estado'] ?? ''] ?? null;
    if (!$estado || empty($body['citaId'])) { http_response_code(400); echo json_encode(['error' => 'Faltan citaId/estado válido']); exit; }
    pdoQuery($pdo, "UPDATE appointments SET status = ? WHERE id = ?", [$estado, (int)$body['citaId']]);
    echo json_encode(['ok' => true]);
    exit;
}

// ---------- CONFIRMAR / CANCELAR POR TELÉFONO ----------
if ($accion === 'confirmar-por-telefono' || $accion === 'cancelar-por-telefono') {
    $cita = citaProximaPorTelefono($pdo, $body['telefono'] ?? '', ['scheduled']);
    if (!$cita) { echo json_encode(['ok' => false, 'error' => 'sin cita próxima']); exit; }
    if ($accion === 'cancelar-por-telefono') {
        pdoQuery($pdo, "UPDATE appointments SET status = 'cancelled' WHERE id = ?", [$cita['id']]);
    }
    // confirmar: el estado ya es 'scheduled'; se deja constancia en notas.
    if ($accion === 'confirmar-por-telefono') {
        pdoQuery($pdo, "UPDATE appointments SET notes = CONCAT(COALESCE(notes,''), ' [confirmada por WhatsApp]') WHERE id = ?", [$cita['id']]);
    }
    echo json_encode([
        'ok' => true,
        'nombre' => $cita['paciente'],
        'fecha' => textoFecha(new DateTime($cita['appointment_date'], new DateTimeZone('America/Lima'))),
        'hora' => substr($cita['start_time'], 0, 5),
    ]);
    exit;
}

// ---------- CITAS DEL DÍA ----------
if ($accion === 'citas-del-dia') {
    $rows = pdoQuery($pdo,
        "SELECT a.start_time, a.status, u.name AS paciente
         FROM appointments a JOIN users u ON u.id = a.patient_id
         WHERE a.appointment_date = CURDATE() AND a.status != 'cancelled'
         ORDER BY a.start_time"
    )->fetchAll();
    echo json_encode(['ok' => true, 'citas' => array_map(fn($c) => [
        'hora' => substr($c['start_time'], 0, 5),
        'paciente' => $c['paciente'],
        'estado' => $c['status'],
    ], $rows)]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'accion no soportada']);
