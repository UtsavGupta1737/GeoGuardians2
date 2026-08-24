<?php
/**
 * Volunteer Field Photo Upload API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$uploadDir = __DIR__ . '/../uploads/field_photos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$photoUrl = 'assets/hero.png';

if (!empty($_FILES['photo']['name'])) {
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = 'incident_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
        $photoUrl = 'uploads/field_photos/' . $filename;
    }
}

$notes = trim($_POST['notes'] ?? 'Field photo report');
logActivity($pdo, 'FIELD_PHOTO_UPLOAD', "Photo uploaded: {$photoUrl} ({$notes})");

echo json_encode([
    'success' => true,
    'data' => [
        'photo_url' => $photoUrl,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'notes' => $notes
    ]
]);
