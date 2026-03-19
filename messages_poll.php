<?php
// api/messages_poll.php
// Retourne les nouveaux messages d'une conversation depuis un certain ID
session_start();
require 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non connecté']); exit;
}

$user_id = $_SESSION['user_id'];
$conv_id = isset($_GET['conv'])    ? (int)$_GET['conv']    : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (!$conv_id) {
    echo json_encode(['error' => 'Paramètre manquant']); exit;
}

// Vérifier que l'utilisateur est participant
$part = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$part->execute([$conv_id, $user_id]);
if (!$part->fetch()) {
    echo json_encode(['error' => 'Accès refusé']); exit;
}

// Récupérer les messages après last_id
$stmt = $pdo->prepare("
    SELECT m.id, m.sender_id, m.body, m.created_at,
           u.name AS sender_name, u.profile_image AS sender_photo
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.conversation_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conv_id, $last_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lu
$pdo->prepare("UPDATE conversation_participants SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?")
    ->execute([$conv_id, $user_id]);

// Formater pour le JS
$result = [];
foreach ($messages as $msg) {
    $result[] = [
        'id'           => (int)$msg['id'],
        'sender_id'    => (int)$msg['sender_id'],
        'body'         => $msg['body'],
        'created_at'   => $msg['created_at'],
        'time_fmt'     => date('d/m/Y H:i', strtotime($msg['created_at'])),
        'sender_name'  => $msg['sender_name'],
        'sender_photo' => $msg['sender_photo'],
        'is_mine'      => ($msg['sender_id'] == $user_id),
    ];
}

echo json_encode(['messages' => $result]);