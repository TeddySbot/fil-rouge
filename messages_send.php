<?php
// api/messages_send.php
// Reçoit un message en POST et l'insère en BDD
session_start();
require 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode invalide']); exit;
}

$user_id = $_SESSION['user_id'];
$conv_id = (int)($_POST['conv_id'] ?? 0);
$body    = trim($_POST['body'] ?? '');

if (!$conv_id || !$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Données manquantes']); exit;
}

// Vérifier que l'utilisateur est participant
$part = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$part->execute([$conv_id, $user_id]);
if (!$part->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']); exit;
}

try {
    $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)")
        ->execute([$conv_id, $user_id, $body]);
    $new_id = $pdo->lastInsertId();

    $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")
        ->execute([$conv_id]);

    echo json_encode([
        'success' => true,
        'id'      => (int)$new_id,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}