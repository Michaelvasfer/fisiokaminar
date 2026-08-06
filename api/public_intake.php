<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php';

ensurePublicIntakeSchema($pdo);
ensureProtocolSchema($pdo);
ensureAuditSchema($pdo);

const PUBLIC_APPOINTMENT_SLOT_MINUTES = 30;
const PUBLIC_APPOINTMENT_DURATION_MINUTES = 60;

// Morning shift: 8:00 AM – 1:00 PM (last bookable slot at 12:00, ends 13:00)
const PUBLIC_MORNING_START = 480;   // 08:00
const PUBLIC_MORNING_LAST  = 720;  // 12:00 (last slot start; appointment ends at 13:00)

// Afternoon shift: 2:30 PM – 7:30 PM (last bookable slot at 18:30, ends 19:30)
const PUBLIC_AFTERNOON_START = 870;  // 14:30
const PUBLIC_AFTERNOON_LAST  = 1110; // 18:30 (last slot start; appointment ends at 19:30)

function publicJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function publicFriendlyErrorMessage(Throwable $e, string $fallback = 'No se pudo completar la solicitud. Intenta nuevamente.'): string
{
    $message = trim((string)$e->getMessage());
    if ($message === '') {
        return $fallback;
    }

    if (
        stripos($message, 'foreign key constraint fails') !== false ||
        stripos($message, 'appointments_ibfk_1') !== false ||
        stripos($message, 'SQLSTATE[') !== false
    ) {
        return 'No se pudo enlazar correctamente tu registro con la cita. Intenta nuevamente en unos segundos.';
    }

    return $message;
}

function publicReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ensurePublicPatientColumns(PDO $pdo): void
{
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $columns = [];
    }

    $required = [
        'dni' => "VARCHAR(20) DEFAULT NULL",
        'phone' => "VARCHAR(20) DEFAULT NULL",
        'must_change_password' => "TINYINT(1) NOT NULL DEFAULT 0",
        'patient_code' => "VARCHAR(50) DEFAULT NULL",
        'birth_date' => "DATE DEFAULT NULL",
    ];

    foreach ($required as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            } catch (Exception $e) {
            }
        }
    }
}

function publicRandomCode(int $length = 10): string
{
    try {
        return strtoupper(substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length));
    } catch (Exception $e) {
        return strtoupper(substr(md5(uniqid('', true)), 0, $length));
    }
}

function publicNormalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', trim($phone));
}

function publicNormalizeDni(string $dni): string
{
    return preg_replace('/\D+/', '', trim($dni));
}

function publicMinutesToTime(int $totalMinutes): string
{
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    return str_pad((string)$hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$minutes, 2, '0', STR_PAD_LEFT);
}

function publicCountOverlappingAppointments(PDO $pdo, string $date, string $startTime, string $endTime, int $therapistId, int $patientId = 0): array
{
    $therapistRow = pdoQuery($pdo, "
        SELECT COUNT(*) AS total
        FROM appointments
        WHERE appointment_date = ?
          AND therapist_id = ?
          AND status != 'cancelled'
          AND start_time < ?
          AND end_time > ?
    ", [$date, $therapistId, $endTime, $startTime])->fetch();

    $patientTotal = 0;
    if ($patientId > 0) {
        $patientRow = pdoQuery($pdo, "
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE appointment_date = ?
              AND patient_id = ?
              AND status != 'cancelled'
              AND start_time < ?
              AND end_time > ?
        ", [$date, $patientId, $endTime, $startTime])->fetch();
        $patientTotal = (int)($patientRow['total'] ?? 0);
    }

    return [
        'therapist' => (int)($therapistRow['total'] ?? 0),
        'patient' => $patientTotal,
    ];
}

function publicListTherapists(PDO $pdo): array
{
    try {
        return pdoQuery($pdo, "
            SELECT id, name
            FROM users
            WHERE role = 'therapist'
            ORDER BY name ASC
        ")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function publicBuildExercises(string $painArea): array
{
    $area = strtolower(trim($painArea));

    $maps = [
        'hombro' => [
            ['title' => 'Balanceo suave', 'detail' => 'Inclina ligeramente el tronco y deja que el brazo haga pequenos balanceos 30-45 segundos.'],
            ['title' => 'Respiracion costal', 'detail' => 'Respira profundo 5 veces para bajar tension antes de mover el hombro.'],
        ],
        'lumbar' => [
            ['title' => 'Pelvis neutra', 'detail' => 'Acostado boca arriba, lleva la pelvis suave a una posicion comoda 8 repeticiones.'],
            ['title' => 'Caminata corta', 'detail' => 'Haz una caminata de 3 a 5 minutos sin llegar a dolor alto.'],
        ],
        'cervical' => [
            ['title' => 'Retraccion cervical suave', 'detail' => 'Lleva la barbilla ligeramente hacia atras 8 repeticiones, sin forzar.'],
            ['title' => 'Pausa postural', 'detail' => 'Cada hora relaja hombros y respira profundo 30 segundos.'],
        ],
        'rodilla' => [
            ['title' => 'Bombeo de tobillo', 'detail' => 'Mueve el tobillo arriba y abajo 20 veces para activar circulacion.'],
            ['title' => 'Extension asistida', 'detail' => 'Estira la rodilla hasta donde no aumente el dolor por 10 repeticiones.'],
        ],
        'tobillo' => [
            ['title' => 'Circulos suaves', 'detail' => 'Haz circulos pequenos con el pie 10 veces por lado sin dolor fuerte.'],
            ['title' => 'Elevacion', 'detail' => 'Mantener el pie elevado 10 minutos ayuda a desinflamar.'],
        ],
        'aquiles' => [
            ['title' => 'Movilidad de tobillo', 'detail' => 'Mueve el pie arriba y abajo 15 repeticiones sin rebotes.'],
            ['title' => 'Frio local', 'detail' => 'Aplicar frio 10 minutos si hay dolor o inflamacion.'],
        ],
    ];

    foreach ($maps as $keyword => $items) {
        if ($area !== '' && strpos($area, $keyword) !== false) {
            return $items;
        }
    }

    return [
        ['title' => 'Movimiento suave', 'detail' => 'Realiza movimientos suaves de la zona sin pasar de dolor 4/10.'],
        ['title' => 'Frio o calor segun tolerancia', 'detail' => 'Usa 10 minutos lo que te alivie mejor, sin quemar la piel.'],
    ];
}

function publicBuildHomeRecommendations(string $painArea, bool $traumaEvent, int $painScore): array
{
    $area = strtolower(trim($painArea));

    $base = [
        'Aplicar frio local 10-15 minutos, 2 a 3 veces al dia durante las primeras 48-72 horas (con una tela entre la piel y el hielo).',
        'Reposo relativo: evita actividades que disparen el dolor, sin inmovilizar completamente la zona.',
        'Evita ejercicios repetitivos o cargas altas de la zona dolorosa por ahora.',
        'Haz movilidad suave sin dolor intenso (mantenerte entre 0 y 4/10).',
        'Si aparecen sintomas de alarma (debilidad progresiva, fiebre, perdida de fuerza marcada), busca evaluacion medica prioritaria.',
    ];

    if (strpos($area, 'hombro') !== false || strpos($area, 'codo') !== false) {
        return [
            $base[0],
            $base[1],
            'Evita levantar peso por encima del hombro y movimientos repetitivos de empuje o traccion.',
            'Puedes hacer movimientos pendulares suaves 30-45 segundos, 2 o 3 veces al dia.',
            $base[4],
        ];
    }

    if (strpos($area, 'lumbar') !== false || strpos($area, 'cervical') !== false) {
        return [
            $base[1],
            'Evita permanecer mucho tiempo en una sola postura; realiza pausas de 1-2 minutos cada 30-45 minutos.',
            'No hagas giros bruscos ni cargas pesadas mientras haya dolor activo.',
            'Camina distancias cortas y progresivas segun tolerancia.',
            $base[4],
        ];
    }

    if (strpos($area, 'rodilla') !== false || strpos($area, 'tobillo') !== false || strpos($area, 'aquiles') !== false) {
        return [
            $base[0],
            'Eleva la pierna 10-15 minutos cuando notes inflamacion.',
            'Evita saltos, trote y cambios bruscos de direccion hasta ser evaluado.',
            'Apoya de forma progresiva segun dolor; evita sobreesfuerzo.',
            $base[4],
        ];
    }

    $generic = $base;
    if ($traumaEvent || $painScore >= 8) {
        $generic[] = 'Como hubo trauma o dolor alto, recomendamos evaluacion presencial lo antes posible para guiar la carga de forma segura.';
    }

    return $generic;
}

function publicRecommendProtocol(PDO $pdo, array $answers): array
{
    $painArea = strtolower(trim((string)($answers['pain_area'] ?? '')));
    $painScore = max(0, min(10, (int)($answers['pain_score'] ?? 0)));
    $redFlags = array_values(array_filter(array_map('trim', (array)($answers['red_flags'] ?? []))));
    $traumaEvent = !empty($answers['trauma_event']);
    $wantsTrauma = !empty($answers['wants_trauma_eval']);

    $protocols = [];
    try {
        $protocols = pdoQuery($pdo, "
            SELECT id, name, description, total_sessions
            FROM treatment_protocols
            ORDER BY total_sessions ASC, id ASC
        ")->fetchAll();
    } catch (Exception $e) {
        $protocols = [];
    }

    $keywordMap = [
        'hombro' => ['hombro', 'manguito', 'rotador'],
        'lumbar' => ['lumbar', 'espalda baja', 'columna'],
        'cervical' => ['cervical', 'cuello'],
        'rodilla' => ['rodilla', 'menisco', 'ligamento'],
        'tobillo' => ['tobillo', 'esguince'],
        'aquiles' => ['aquiles', 'tendon de aquiles', 'tendon'],
        'cadera' => ['cadera', 'gluteo', 'piriforme'],
        'codo' => ['codo', 'epicond', 'epitrocle'],
    ];

    $matchedKeywords = [];
    foreach ($keywordMap as $group => $keywords) {
        foreach ($keywords as $keyword) {
            if ($painArea !== '' && strpos($painArea, $keyword) !== false) {
                $matchedKeywords = $keywords;
                break 2;
            }
        }
    }

    $bestProtocol = null;
    $bestScore = -1;
    foreach ($protocols as $protocol) {
        $haystack = strtolower(trim((string)($protocol['name'] ?? '') . ' ' . (string)($protocol['description'] ?? '')));
        $score = 0;

        foreach ($matchedKeywords as $keyword) {
            if ($keyword !== '' && strpos($haystack, $keyword) !== false) {
                $score += 4;
            }
        }

        if ($painScore >= 7 && (int)($protocol['total_sessions'] ?? 0) >= 10) {
            $score += 1;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestProtocol = $protocol;
        }
    }

    if (!$bestProtocol && count($protocols) > 0) {
        $bestProtocol = $protocols[0];
    }

    $needsTrauma = count($redFlags) > 0 || $wantsTrauma;
    if (!$needsTrauma && $traumaEvent && $painScore >= 8) {
        $needsTrauma = true;
    }

    $carePath = $needsTrauma ? 'trauma_first' : 'physio_first';
    $headline = $needsTrauma
        ? 'Conviene una revision medica antes o en paralelo a la fisioterapia.'
        : 'Tu caso parece candidato a una evaluacion fisioterapeutica inicial.';

    $summaryLines = [];
    if ($painArea !== '') {
        $summaryLines[] = 'Zona reportada: ' . $painArea;
    }
    $summaryLines[] = 'Dolor actual: EVA ' . $painScore . '/10';
    if (!empty($answers['pain_since'])) {
        $summaryLines[] = 'Tiempo de evolucion: ' . trim((string)$answers['pain_since']);
    }
    if (!empty($answers['main_limitation'])) {
        $summaryLines[] = 'Limitacion principal: ' . trim((string)$answers['main_limitation']);
    }
    if ($needsTrauma && $redFlags) {
        $summaryLines[] = 'Alertas detectadas: ' . implode(', ', $redFlags);
    }

    $programName = 'Programa de Rehabilitacion KaminarFisio';
    $planName = $bestProtocol['name'] ?? 'Evaluacion inicial personalizada';
    $suggestedSessions = max(1, (int)($bestProtocol['total_sessions'] ?? ($painScore >= 7 ? 10 : 6)));

    $programSummary = 'Objetivo: bajar dolor, recuperar movilidad y volver a tus actividades con seguridad. Incluye evaluacion funcional, terapia manual, ejercicio terapeutico progresivo y seguimiento.';

    return [
        'care_path' => $carePath,
        'headline' => $headline,
        'summary' => implode('. ', $summaryLines) . '.',
        'recommended_protocol_id' => $bestProtocol ? (int)$bestProtocol['id'] : null,
        'recommended_plan_name' => $planName,
        'program_name' => $programName,
        'program_summary' => $programSummary,
        'suggested_sessions' => $suggestedSessions,
        'needs_trauma_eval' => $needsTrauma,
        'confidence_label' => $bestProtocol ? 'Plan sugerido orientativo' : 'Plan inicial orientativo',
        'pre_exercises' => publicBuildExercises($painArea),
        'home_recommendations' => publicBuildHomeRecommendations($painArea, $traumaEvent, $painScore),
    ];
}

function publicFetchIntake(PDO $pdo, string $publicCode): ?array
{
    $publicCode = strtoupper(trim($publicCode));
    if ($publicCode === '') {
        return null;
    }

    $intake = pdoQuery($pdo, "
        SELECT *
        FROM lead_intakes
        WHERE public_code = ?
        LIMIT 1
    ", [$publicCode])->fetch();

    if (!$intake) {
        return null;
    }

    $intake['answers'] = json_decode((string)($intake['answers_json'] ?? ''), true) ?: [];
    $intake['result'] = json_decode((string)($intake['result_json'] ?? ''), true) ?: [];
    $intake['match_fields'] = json_decode((string)($intake['matched_fields_json'] ?? ''), true) ?: [];
    $intake['photos'] = pdoQuery($pdo, "
        SELECT id, file_path, original_name, caption, created_at
        FROM lead_intake_photos
        WHERE intake_id = ?
        ORDER BY created_at DESC, id DESC
    ", [(int)$intake['id']])->fetchAll();

    unset($intake['answers_json'], $intake['result_json'], $intake['matched_fields_json']);
    return $intake;
}

function publicSaveIntake(PDO $pdo, array $payload): array
{
    $publicCode = strtoupper(trim((string)($payload['public_code'] ?? '')));
    $fullName = trim((string)($payload['full_name'] ?? ''));
    $phone = publicNormalizePhone((string)($payload['phone'] ?? ''));
    $dni = publicNormalizeDni((string)($payload['dni'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
    $currentStep = max(1, min(4, (int)($payload['current_step'] ?? 1)));
    $sourceChannel = trim((string)($payload['source_channel'] ?? 'whatsapp_link')) ?: 'whatsapp_link';

    if ($fullName === '' || strlen($phone) !== 9) {
        publicJsonResponse(422, ['success' => false, 'error' => 'Nombre y telefono valido son obligatorios.']);
    }

    if ($dni !== '' && strlen($dni) !== 8) {
        publicJsonResponse(422, ['success' => false, 'error' => 'El DNI debe tener 8 digitos.']);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        publicJsonResponse(422, ['success' => false, 'error' => 'El correo no es valido.']);
    }

    $recommendation = publicRecommendProtocol($pdo, $answers);
    $status = $recommendation['needs_trauma_eval'] ? 'referred_trauma' : 'triaged';
    if ($publicCode === '') {
        $publicCode = publicRandomCode(12);
        pdoQuery($pdo, "
            INSERT INTO lead_intakes (
                public_code, full_name, phone, dni, email, source_channel, status, current_step, wants_trauma_eval, answers_json, result_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $publicCode,
            $fullName,
            $phone,
            $dni !== '' ? $dni : null,
            $email !== '' ? $email : null,
            $sourceChannel,
            $status,
            $currentStep,
            !empty($answers['wants_trauma_eval']) ? 1 : 0,
            json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($recommendation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $intakeId = (int)$pdo->lastInsertId();
    } else {
        $existing = publicFetchIntake($pdo, $publicCode);
        if ($existing) {
            $intakeId = (int)$existing['id'];
            if (($existing['status'] ?? '') === 'booked' && !empty($existing['appointment_id'])) {
                $status = 'booked';
                $currentStep = 4;
            }
            pdoQuery($pdo, "
                UPDATE lead_intakes
                SET full_name = ?, phone = ?, dni = ?, email = ?, source_channel = ?, status = ?, current_step = ?, wants_trauma_eval = ?, answers_json = ?, result_json = ?
                WHERE id = ?
            ", [
                $fullName,
                $phone,
                $dni !== '' ? $dni : null,
                $email !== '' ? $email : null,
                $sourceChannel,
                $status,
                $currentStep,
                !empty($answers['wants_trauma_eval']) ? 1 : 0,
                json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($recommendation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $intakeId,
            ]);
        } else {
            pdoQuery($pdo, "
                INSERT INTO lead_intakes (
                    public_code, full_name, phone, dni, email, source_channel, status, current_step, wants_trauma_eval, answers_json, result_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $publicCode,
                $fullName,
                $phone,
                $dni !== '' ? $dni : null,
                $email !== '' ? $email : null,
                $sourceChannel,
                $status,
                $currentStep,
                !empty($answers['wants_trauma_eval']) ? 1 : 0,
                json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($recommendation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $intakeId = (int)$pdo->lastInsertId();
        }
    }

    appLog($pdo, 'lead_intake.save', 'lead_intake', (string)$intakeId, [
        'source_channel' => $sourceChannel,
        'status' => $status,
        'current_step' => $currentStep,
        'phone' => $phone,
    ], [
        'user_id' => null,
        'user_name' => 'Public Intake',
        'user_role' => 'public',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $saved = publicFetchIntake($pdo, $publicCode);
    return [
        'intake' => $saved,
        'recommendation' => $recommendation,
    ];
}

function publicBuildAppointmentNotes(array $intake, array $recommendation): string
{
    $answers = is_array($intake['answers'] ?? null) ? $intake['answers'] : [];
    $matchFields = is_array($intake['match_fields'] ?? null) ? $intake['match_fields'] : [];
    $redFlags = array_values(array_filter(array_map('trim', (array)($answers['red_flags'] ?? []))));
    $lines = [
        'Origen: Enlace publico / WhatsApp',
        'Nombre reportado: ' . trim((string)($intake['full_name'] ?? 'Paciente Web')),
        'Zona: ' . trim((string)($answers['pain_area'] ?? 'No especificada')),
        'Dolor EVA: ' . max(0, min(10, (int)($answers['pain_score'] ?? 0))) . '/10',
        'Tiempo: ' . trim((string)($answers['pain_since'] ?? 'No especificado')),
        'Limitacion: ' . trim((string)($answers['main_limitation'] ?? 'No especificada')),
        'Objetivo: ' . trim((string)($answers['goal'] ?? 'No especificado')),
        'Plan sugerido: ' . trim((string)($recommendation['recommended_plan_name'] ?? 'Evaluacion inicial')),
    ];

    if (!empty($intake['phone'])) {
        $lines[] = 'Telefono reportado: ' . trim((string)$intake['phone']);
    }

    if (!empty($intake['dni'])) {
        $lines[] = 'DNI reportado: ' . trim((string)$intake['dni']);
    }

    if ($redFlags) {
        $lines[] = 'Alertas: ' . implode(', ', $redFlags);
    }

    if ($matchFields) {
        $matchedLabels = [];
        if (!empty($matchFields['dni'])) {
            $matchedLabels[] = 'DNI';
        }
        if (!empty($matchFields['phone'])) {
            $matchedLabels[] = 'telefono';
        }
        if ($matchedLabels) {
            $lines[] = 'Verificar identidad: hubo coincidencia previa por ' . implode(' y ', $matchedLabels) . '. El sistema no fusiono automaticamente este ingreso con el paciente anterior.';
        }
    }

    if (!empty($recommendation['needs_trauma_eval'])) {
        $lines[] = 'Sugerencia: revisar traumatologia antes o en paralelo.';
    }

    return implode("\n", $lines);
}

function publicFindExistingPatientMatches(PDO $pdo, string $dni, string $phone): array
{
    $fields = [];

    if ($dni !== '') {
        $existingByDni = pdoQuery($pdo, "
            SELECT id, name, patient_code
            FROM users
            WHERE role = 'patient' AND dni = ?
            ORDER BY id DESC
            LIMIT 1
        ", [$dni])->fetch();
        if ($existingByDni) {
            $fields['dni'] = [
                'id' => (int)$existingByDni['id'],
                'name' => (string)($existingByDni['name'] ?? ''),
                'patient_code' => (string)($existingByDni['patient_code'] ?? ''),
            ];
        }
    }

    if ($phone !== '') {
        $existingByPhone = pdoQuery($pdo, "
            SELECT id, name, patient_code
            FROM users
            WHERE role = 'patient' AND phone = ?
            ORDER BY id DESC
            LIMIT 1
        ", [$phone])->fetch();
        if ($existingByPhone) {
            $fields['phone'] = [
                'id' => (int)$existingByPhone['id'],
                'name' => (string)($existingByPhone['name'] ?? ''),
                'patient_code' => (string)($existingByPhone['patient_code'] ?? ''),
            ];
        }
    }

    $primaryMatchId = 0;
    if (!empty($fields['dni']['id'])) {
        $primaryMatchId = (int)$fields['dni']['id'];
    } elseif (!empty($fields['phone']['id'])) {
        $primaryMatchId = (int)$fields['phone']['id'];
    }

    return [
        'primary_patient_id' => $primaryMatchId,
        'fields' => $fields,
    ];
}

function publicUpsertPatient(PDO $pdo, array $intake): array
{
    ensurePublicPatientColumns($pdo);

    $fullName = trim((string)($intake['full_name'] ?? 'Paciente Web'));
    $phone = publicNormalizePhone((string)($intake['phone'] ?? ''));
    $dni = publicNormalizeDni((string)($intake['dni'] ?? ''));
    $email = trim((string)($intake['email'] ?? ''));
    $existingMatches = publicFindExistingPatientMatches($pdo, $dni, $phone);
    $matchFields = $existingMatches['fields'];
    $matchedExistingPatientId = (int)($existingMatches['primary_patient_id'] ?? 0);

    $patientCode = '#PT-' . publicRandomCode(6);
    $finalEmail = $email !== '' ? $email : ('lead.' . time() . '.' . publicRandomCode(4) . '@kaminarfisio.com');
    $passwordHash = password_hash(publicRandomCode(12), PASSWORD_DEFAULT);
    $safeDni = isset($matchFields['dni']) ? null : ($dni !== '' ? $dni : null);
    $safePhone = isset($matchFields['phone']) ? null : ($phone !== '' ? $phone : null);

    pdoQuery($pdo, "
        INSERT INTO users (role, name, dni, email, password, age, birth_date, patient_code, phone, must_change_password)
        VALUES ('patient', ?, ?, ?, ?, NULL, NULL, ?, ?, 1)
    ", [
        $fullName,
        $safeDni,
        $finalEmail,
        $passwordHash,
        $patientCode,
        $safePhone,
    ]);

    $insertedPatient = pdoQuery($pdo, "
        SELECT id
        FROM users
        WHERE role = 'patient' AND patient_code = ?
        LIMIT 1
    ", [$patientCode])->fetch();

    if ($insertedPatient) {
        return [
            'patient_id' => (int)$insertedPatient['id'],
            'matched_existing_patient_id' => $matchedExistingPatientId > 0 ? $matchedExistingPatientId : null,
            'matched_fields' => $matchFields,
        ];
    }

    throw new RuntimeException('No se pudo crear el paciente para completar la reserva.');
}

function publicListAvailability(PDO $pdo, string $date, int $therapistId = 0): array
{
    $today = date('Y-m-d');
    if ($date < $today) {
        return [];
    }

    $weekday = (int)date('N', strtotime($date));
    if ($weekday === 7) {
        return [];
    }

    $therapists = publicListTherapists($pdo);
    if ($therapistId > 0) {
        $therapists = array_values(array_filter($therapists, static function ($therapist) use ($therapistId) {
            return (int)($therapist['id'] ?? 0) === $therapistId;
        }));
    }

    if (!$therapists) {
        return [];
    }

    $rows = pdoQuery($pdo, "
        SELECT therapist_id, start_time, end_time
        FROM appointments
        WHERE appointment_date = ?
          AND status != 'cancelled'
    ", [$date])->fetchAll();

    $appointmentsByTherapist = [];
    foreach ($rows as $row) {
        $tid = (int)($row['therapist_id'] ?? 0);
        if ($tid <= 0) {
            continue;
        }
        if (!isset($appointmentsByTherapist[$tid])) {
            $appointmentsByTherapist[$tid] = [];
        }
        $appointmentsByTherapist[$tid][] = $row;
    }

    $isToday = $date === $today;
    $currentMinutes = ((int)date('H')) * 60 + (int)date('i');
    $slots = [];

    // Build bookable time ranges: morning + afternoon shifts
    $shifts = [
        ['start' => PUBLIC_MORNING_START, 'last' => PUBLIC_MORNING_LAST],
        ['start' => PUBLIC_AFTERNOON_START, 'last' => PUBLIC_AFTERNOON_LAST],
    ];

    foreach ($therapists as $therapist) {
        $tid = (int)($therapist['id'] ?? 0);
        foreach ($shifts as $shift) {
            for ($minutes = $shift['start']; $minutes <= $shift['last']; $minutes += PUBLIC_APPOINTMENT_SLOT_MINUTES) {
                if ($isToday && $minutes <= $currentMinutes) {
                    continue;
                }

                $startTime = publicMinutesToTime($minutes);
                $endTime = publicMinutesToTime($minutes + PUBLIC_APPOINTMENT_DURATION_MINUTES);
                $overlapCount = 0;
                foreach ($appointmentsByTherapist[$tid] ?? [] as $existing) {
                    if (($existing['start_time'] ?? '') < $endTime && ($existing['end_time'] ?? '') > $startTime) {
                        $overlapCount++;
                    }
                }

                $slots[] = [
                    'therapist_id' => $tid,
                    'therapist_name' => $therapist['name'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'capacity_left' => null,
                ];
            }
        }
    }

    usort($slots, static function ($a, $b) {
        if ($a['start_time'] === $b['start_time']) {
            return strcmp((string)$a['therapist_name'], (string)$b['therapist_name']);
        }
        return strcmp((string)$a['start_time'], (string)$b['start_time']);
    });

    return $slots;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = trim((string)($_GET['action'] ?? 'bootstrap'));

    if ($action === 'bootstrap') {
        $publicCode = strtoupper(trim((string)($_GET['code'] ?? '')));
        $intake = $publicCode !== '' ? publicFetchIntake($pdo, $publicCode) : null;

        publicJsonResponse(200, [
            'success' => true,
            'today' => date('Y-m-d'),
            'max_date' => date('Y-m-d', strtotime('+30 days')),
            'therapists' => publicListTherapists($pdo),
            'intake' => $intake,
        ]);
    }

    if ($action === 'availability') {
        $date = trim((string)($_GET['date'] ?? ''));
        $therapistId = (int)($_GET['therapist_id'] ?? 0);
        if ($date === '') {
            publicJsonResponse(422, ['success' => false, 'error' => 'La fecha es obligatoria.']);
        }

        publicJsonResponse(200, [
            'success' => true,
            'date' => $date,
            'slots' => publicListAvailability($pdo, $date, $therapistId),
        ]);
    }

    publicJsonResponse(400, ['success' => false, 'error' => 'Accion no valida.']);
}

if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    $publicCode = strtoupper(trim((string)($_POST['public_code'] ?? '')));
    $intake = publicFetchIntake($pdo, $publicCode);
    if (!$intake) {
        publicJsonResponse(404, ['success' => false, 'error' => 'Ingreso no encontrado.']);
    }

    if (!isset($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? 1) !== 0) {
        publicJsonResponse(422, ['success' => false, 'error' => 'No se pudo cargar la foto.']);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $mime = mime_content_type($_FILES['photo']['tmp_name']) ?: '';
    if (!in_array($mime, $allowed, true)) {
        publicJsonResponse(422, ['success' => false, 'error' => 'Solo se permiten imagenes JPG, PNG o WEBP.']);
    }

    $uploadDir = '../uploads/intake/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        publicJsonResponse(500, ['success' => false, 'error' => 'No se pudo preparar la carpeta de subida.']);
    }

    $extension = strtolower(pathinfo((string)($_FILES['photo']['name'] ?? ''), PATHINFO_EXTENSION));
    $fileName = 'intake_' . (int)$intake['id'] . '_' . time() . '_' . publicRandomCode(4) . ($extension ? '.' . $extension : '');
    $targetPath = $uploadDir . $fileName;
    $dbPath = 'uploads/intake/' . $fileName;

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
        publicJsonResponse(500, ['success' => false, 'error' => 'No se pudo guardar la foto.']);
    }

    pdoQuery($pdo, "
        INSERT INTO lead_intake_photos (intake_id, file_path, original_name, caption)
        VALUES (?, ?, ?, ?)
    ", [
        (int)$intake['id'],
        $dbPath,
        trim((string)($_FILES['photo']['name'] ?? '')),
        trim((string)($_POST['caption'] ?? '')),
    ]);

    $updated = publicFetchIntake($pdo, $publicCode);
    publicJsonResponse(200, ['success' => true, 'intake' => $updated]);
}

if ($method !== 'POST') {
    publicJsonResponse(405, ['success' => false, 'error' => 'Metodo no permitido.']);
}

$body = publicReadJsonBody();
$action = trim((string)($body['action'] ?? ''));

if ($action === 'save_intake') {
    $saved = publicSaveIntake($pdo, $body);
    publicJsonResponse(200, ['success' => true] + $saved);
}

if ($action === 'book') {
    $publicCode = strtoupper(trim((string)($body['public_code'] ?? '')));
    $date = trim((string)($body['appointment_date'] ?? ''));
    $startTime = trim((string)($body['start_time'] ?? ''));
    $therapistId = (int)($body['therapist_id'] ?? 0);

    if ($publicCode === '' || $date === '' || $startTime === '' || $therapistId <= 0) {
        publicJsonResponse(422, ['success' => false, 'error' => 'Faltan datos para reservar.']);
    }

    $intake = publicFetchIntake($pdo, $publicCode);
    if (!$intake) {
        publicJsonResponse(404, ['success' => false, 'error' => 'Ingreso no encontrado.']);
    }

    if (($intake['status'] ?? '') === 'booked' && !empty($intake['appointment_id'])) {
        $existingAppointment = pdoQuery($pdo, "
            SELECT a.id, a.patient_id, a.appointment_date, a.start_time, a.end_time, a.type, u.name AS therapist_name
            FROM appointments a
            LEFT JOIN users u ON u.id = a.therapist_id
            WHERE a.id = ?
            LIMIT 1
        ", [(int)$intake['appointment_id']])->fetch();

        if ($existingAppointment) {
            publicJsonResponse(200, [
                'success' => true,
                'already_booked' => true,
                'appointment' => $existingAppointment,
                'intake' => $intake,
            ]);
        }
    }

    $availableSlots = publicListAvailability($pdo, $date, $therapistId);
    $slot = null;
    foreach ($availableSlots as $candidate) {
        if (($candidate['start_time'] ?? '') === $startTime && (int)($candidate['therapist_id'] ?? 0) === $therapistId) {
            $slot = $candidate;
            break;
        }
    }

    if (!$slot) {
        publicJsonResponse(409, ['success' => false, 'error' => 'Ese horario ya no esta disponible.']);
    }

    $pdo->beginTransaction();
    try {
        $patientLink = publicUpsertPatient($pdo, $intake);
        $patientId = (int)($patientLink['patient_id'] ?? 0);
        $matchedExistingPatientId = (int)($patientLink['matched_existing_patient_id'] ?? 0);
        $matchedFields = is_array($patientLink['matched_fields'] ?? null) ? $patientLink['matched_fields'] : [];
        $patientExists = pdoQuery($pdo, "
            SELECT id
            FROM users
            WHERE id = ? AND role = 'patient'
            LIMIT 1
        ", [$patientId])->fetch();

        if (!$patientExists) {
            throw new RuntimeException('No se pudo preparar el registro del paciente. Intenta nuevamente.');
        }

        $overlap = publicCountOverlappingAppointments($pdo, $date, $startTime, $slot['end_time'], $therapistId, $patientId);

        if ($overlap['therapist'] >= 3) {
            throw new RuntimeException('Ese horario ya no esta disponible.');
        }
        if ($overlap['patient'] >= 1) {
            throw new RuntimeException('El paciente ya tiene una cita en ese horario.');
        }

        $recommendation = is_array($intake['result'] ?? null) ? $intake['result'] : [];
        $intake['match_fields'] = $matchedFields;
        $type = !empty($recommendation['needs_trauma_eval']) ? 'Evaluacion Inicial con Alerta' : 'Evaluacion Inicial Web';

        pdoQuery($pdo, "
            INSERT INTO appointments (
                patient_id, therapist_id, appointment_date, start_time, end_time, type, notes, status, source_channel, public_intake_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', 'public_intake', ?)
        ", [
            $patientId,
            $therapistId,
            $date,
            $startTime,
            $slot['end_time'],
            $type,
            publicBuildAppointmentNotes($intake, $recommendation),
            (int)$intake['id'],
        ]);

        $appointmentId = (int)$pdo->lastInsertId();

        pdoQuery($pdo, "
            UPDATE lead_intakes
            SET patient_id = ?, appointment_id = ?, therapist_id = ?, matched_existing_patient_id = ?, matched_fields_json = ?, booked_slot_date = ?, booked_slot_time = ?, status = 'booked', current_step = 4
            WHERE id = ?
        ", [
            $patientId,
            $appointmentId,
            $therapistId,
            $matchedExistingPatientId > 0 ? $matchedExistingPatientId : null,
            $matchedFields ? json_encode($matchedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $date,
            $startTime,
            (int)$intake['id'],
        ]);

        $pdo->commit();

        appLog($pdo, 'lead_intake.book', 'lead_intake', (string)$intake['id'], [
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'therapist_id' => $therapistId,
            'appointment_date' => $date,
            'start_time' => $startTime,
        ], [
            'user_id' => null,
            'user_name' => 'Public Intake',
            'user_role' => 'public',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        publicJsonResponse(200, [
            'success' => true,
            'appointment' => [
                'id' => $appointmentId,
                'patient_id' => $patientId,
                'appointment_date' => $date,
                'start_time' => $startTime,
                'end_time' => $slot['end_time'],
                'therapist_name' => $slot['therapist_name'],
                'type' => $type,
            ],
            'intake' => publicFetchIntake($pdo, $publicCode),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Public intake booking error: ' . $e->getMessage());
        publicJsonResponse(409, ['success' => false, 'error' => publicFriendlyErrorMessage($e, 'No se pudo completar la reserva. Intenta nuevamente.')]);
    }
}

publicJsonResponse(400, ['success' => false, 'error' => 'Accion no valida.']);
