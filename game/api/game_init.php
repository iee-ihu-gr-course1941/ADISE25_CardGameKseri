<?php
header('Content-Type: application/json');

require_once 'db.php';
require_once 'deck.php';

$db = getDB();

/*
Expected POST:
{
  "user_id": 3
}
*/

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$userId = (int)$data['user_id'];

try {
    $db->beginTransaction();

    /* 1️⃣ Δημιουργία τράπουλας */
    $deck = createDeck();
    shuffleDeck($deck);

    /* 2️⃣ 4 φύλλα στο τραπέζι */
    $tableCards = array_splice($deck, 0, 4);

    /* 3️⃣ Δημιουργία παιχνιδιού */
    $stmt = $db->prepare("
        INSERT INTO games (status, current_player, deck, table_cards)
        VALUES ('waiting', NULL, ?, ?)
    ");
    $stmt->execute([
        json_encode($deck),
        json_encode($tableCards)
    ]);

    $gameId = $db->lastInsertId();

    /* 4️⃣ Εισαγωγή πρώτου παίκτη */
    $stmt = $db->prepare("
        INSERT INTO game_players (game_id, user_id, hand, score)
        VALUES (?, ?, '[]', 0)
    ");
    $stmt->execute([$gameId, $userId]);

    $db->commit();

    echo json_encode([
        "success" => true,
        "game_id" => $gameId,
        "status" => "waiting"
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(["error" => $e->getMessage()]);
}
