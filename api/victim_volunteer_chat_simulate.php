<?php
/**
 * Victim-Volunteer Chat Simulate API
 * Simulates victim distress responses for live field demo
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$sosId = (int)($_GET['sos_id'] ?? $_POST['sos_id'] ?? 1);

$sampleReplies = [
    "Thank you! We can hear the sirens in the distance. We are flashing an LED light from the roof.",
    "Water has stopped rising here for now. We have an elderly patient who needs help walking down.",
    "Understood! We have life jackets ready. Standing by at the balcony.",
    "Received! Our family is safe and waiting for the volunteer team to arrive."
];

$chosenReply = $sampleReplies[array_rand($sampleReplies)];

try {
    $stmt = $pdo->prepare("
        INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message, message_type)
        VALUES (?, 10, 'Aarav Patel', 'victim', ?, 'text')
    ");
    $stmt->execute([$sosId, $chosenReply]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $newId,
            'sos_id' => $sosId,
            'message' => $chosenReply,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
