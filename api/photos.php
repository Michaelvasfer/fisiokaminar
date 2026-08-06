<?php
// api/photos.php - Manejo de fotos de evolución
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $therapist_id = $_SESSION['user_id'];
    $title = $_POST['title'] ?? '';

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'Error al subir archivo']);
        exit;
    }

    $uploadDir = '../uploads/photos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = time() . '_' . basename($_FILES['photo']['name']);
    $targetPath = $uploadDir . $fileName;
    $dbPath = 'uploads/photos/' . $fileName;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
        pdoQuery($pdo, "
            INSERT INTO patient_photos (patient_id, therapist_id, title, photo_path)
            VALUES (?, ?, ?, ?)
        ", [$patient_id, $therapist_id, $title, $dbPath]);
        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'photo.upload', 'patient_photo', (string)$newId, [
            'patient_id' => $patient_id,
            'title' => $title,
            'photo_path' => $dbPath
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo']);
    }
}
