<?php
header('Content-Type: application/json');

require_once 'db.php';
require_once 'deck.php';

$db = getDB();

/*
Expected POST:
{
  "game_id": 1,
  "user_id": 7
}
*/

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['game_id'], $data['user_id'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$gameId = (int)$data['game_id'];
$userId = (int)$data['user_id'];

try {
    $db->beginTransaction();

    /* 1️⃣ Φόρτωση παιχνιδιού */
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        throw new Exception("Game not found");
    }

    if ($game['status'] !== 'waiting') {
        throw new Exception("Game already started");
    }

    /* 2️⃣ Έλεγχος αν υπάρχει ήδη παίκτης */
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM game_players WHERE game_id = ?
    ");
    $stmt->execute([$gameId]);
    $count = (int)$stmt->fetchColumn();

    if ($count !== 1) {
        throw new Exception("Invalid game state");
    }

    /* 3️⃣ Προσθήκη δεύτερου παίκτη */
    $stmt = $db->prepare("
        INSERT INTO game_players (game_id, user_id, hand, score)
        VALUES (?, ?, '[]', 0)
    ");
    $stmt->execute([$gameId, $userId]);

    /* 4️⃣ Μοίρασμα φύλλων */
    $deck = json_decode($game['deck'], true);

    $stmt = $db->prepare("
        SELECT id FROM game_players WHERE game_id = ?
    ");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($players as $pid) {
        $hand = array_splice($deck, 0, 4);
        $stmt = $db->prepare("
            UPDATE game_players SET hand = ? WHERE id = ?
        ");
        $stmt->execute([json_encode($hand), $pid]);
    }

    /* 5️⃣ Ορισμός πρώτου παίκτη (απλά ο πρώτος που μπήκε) */
    $stmt = $db->prepare("
        SELECT user_id FROM game_players
        WHERE game_id = ?
        ORDER BY joined_at ASC
        LIMIT 1
    ");
    $stmt->execute([$gameId]);
    $firstPlayer = $stmt->fetchColumn();

    /* 6️⃣ Update game */
    $stmt = $db->prepare("
        UPDATE games
        SET status = 'active',
            current_player = ?,
            deck = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $firstPlayer,
        json_encode($deck),
        $gameId
    ]);

    $db->commit();

    echo json_encode([
        "success" => true,
        "game_id" => $gameId,
        "first_player" => $firstPlayer
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(["error" => $e->getMessage()]);
}
