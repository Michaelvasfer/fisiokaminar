<?php
// api/bot-cron.php — Recordatorios y reseñas de la fisioapp (cada 15 min por cron del sistema).
//   GET ?secret=CRON_SECRET
// - Recordatorio 2 h antes de la cita.
// - Solicitud de reseña de Google Maps 1 h después de concluir la cita.
header('Content-Type: application/json');
require_once '../db.php';

// Enlace de reseñas del centro de fisioterapia (Google Maps).
define('GOOGLE_MAPS_URL', 'https://maps.app.goo.gl/n6VBumW1HMeVobWR7');

function cargarEnvCron(): array {
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

$env = cargarEnvCron();
$esperado = $env['CRON_SECRET'] ?? '';
if (!$esperado || !hash_equals($esperado, $_GET['secret'] ?? '')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

function enviarWhatsapp(string $telefono, string $mensaje): void {
    $env = cargarEnvCron();
    $token = $env['WHATSAPP_TOKEN'] ?? '';
    $phoneId = $env['WHATSAPP_PHONE_NUMBER_ID'] ?? '';
    if (!$token || !$phoneId) throw new Exception('WhatsApp no configurado');
    $ch = curl_init("https://graph.facebook.com/v21.0/$phoneId/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'text',
            'text' => ['body' => $mensaje, 'preview_url' => false],
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400) throw new Exception('Meta respondió ' . $code . ': ' . substr((string)$resp, 0, 200));
}

function normalizarTel(string $tel): string {
    $t = preg_replace('/\D/', '', $tel);
    if (strlen($t) === 9) $t = '51' . $t;
    return $t;
}

$ahora = new DateTime('now', new DateTimeZone('America/Lima'));
$ahoraMin = (int)$ahora->format('H') * 60 + (int)$ahora->format('i');
$hoy = $ahora->format('Y-m-d');

// Pausa nocturna: no enviar recordatorios ni reseñas fuera de 8 a. m.–9 p. m.
if ($ahoraMin < 8 * 60 || $ahoraMin >= 21 * 60) {
    echo json_encode(['ok' => true, 'pausaNocturna' => true]);
    exit;
}

$resultado = ['recordatorios' => ['enviados' => 0, 'fallidos' => 0], 'resenas' => ['enviadas' => 0, 'fallidas' => 0]];

// --- Recordatorio 2 horas antes (ventana 105–135 min) ---
$citas = pdoQuery($pdo,
    "SELECT a.id, a.start_time, u.name AS paciente, u.phone
     FROM appointments a JOIN users u ON u.id = a.patient_id
     WHERE a.appointment_date = ? AND a.status = 'scheduled' AND a.whatsapp_reminder_2h_sent = 0 AND u.phone IS NOT NULL AND u.phone != ''",
    [$hoy]
)->fetchAll();

foreach ($citas as $c) {
    try {
        $minutos = aMinutosCron(substr($c['start_time'], 0, 5)) - $ahoraMin;
        if ($minutos < 105 || $minutos > 135) continue;
        $msg = "⏰ *Su sesión es en 2 horas*\n\nHola *{$c['paciente']}*,\n\nLe recordamos que HOY tiene su sesión de fisioterapia a las *" . substr($c['start_time'], 0, 5) . "*.\n\nLe recomendamos llegar unos minutos antes.\n\n¡Le esperamos!";
        enviarWhatsapp(normalizarTel($c['phone']), $msg);
        pdoQuery($pdo, "UPDATE appointments SET whatsapp_reminder_2h_sent = 1 WHERE id = ?", [$c['id']]);
        $resultado['recordatorios']['enviados']++;
    } catch (Throwable $e) {
        $resultado['recordatorios']['fallidos']++;
        error_log('bot-cron recordatorio falló cita ' . $c['id'] . ': ' . $e->getMessage());
    }
}

// --- Reseña 1 hora después de concluir (máx. 48 h atrás) ---
$desde = (clone $ahora)->modify('-2 days')->format('Y-m-d');
$completadas = pdoQuery($pdo,
    "SELECT a.id, a.appointment_date, a.end_time, u.name AS paciente, u.phone
     FROM appointments a JOIN users u ON u.id = a.patient_id
     WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed' AND a.whatsapp_review_sent = 0 AND u.phone IS NOT NULL AND u.phone != ''",
    [$desde, $hoy]
)->fetchAll();

foreach ($completadas as $c) {
    try {
        $fin = new DateTime($c['appointment_date'] . ' ' . substr($c['end_time'], 0, 5), new DateTimeZone('America/Lima'));
        $minDesdeFin = ($ahora->getTimestamp() - $fin->getTimestamp()) / 60;
        if ($minDesdeFin < 60 || $minDesdeFin > 48 * 60) continue;
        $msg = "⭐ *Su opinión es importante*\n\nHola *{$c['paciente']}*,\n\nEsperamos que su sesión de fisioterapia haya sido de su agrado.\n\nSi tiene un momento, nos encantaría conocer su experiencia en Google Maps:\n\n" . GOOGLE_MAPS_URL . "\n\n¡Gracias por confiar en nosotros!";
        enviarWhatsapp(normalizarTel($c['phone']), $msg);
        pdoQuery($pdo, "UPDATE appointments SET whatsapp_review_sent = 1 WHERE id = ?", [$c['id']]);
        $resultado['resenas']['enviadas']++;
    } catch (Throwable $e) {
        $resultado['resenas']['fallidas']++;
        error_log('bot-cron reseña falló cita ' . $c['id'] . ': ' . $e->getMessage());
    }
}

echo json_encode(['ok' => true] + $resultado);

function aMinutosCron(string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $h * 60 + $m;
}
