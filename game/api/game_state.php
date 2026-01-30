<?php
header('Content-Type: application/json');

require_once 'db.php';

$db = getDB();

/*
GET:
?game_id=1
&user_id=2
*/

if (!isset($_GET['game_id'], $_GET['user_id'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$gameId = (int)$_GET['game_id'];
$userId = (int)$_GET['user_id'];

try {

    /* Φόρτωση παιχνιδιού */
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        throw new Exception("Game not found");
    }

    /* Παίκτης */
    $stmt = $db->prepare("
        SELECT hand, score 
        FROM game_players
        WHERE game_id = ? AND user_id = ?
    ");
    $stmt->execute([$gameId, $userId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        throw new Exception("Player not in this game");
    }

    /* Αντίπαλος (score μόνο) */
    $stmt = $db->prepare("
        SELECT user_id, score
        FROM game_players
        WHERE game_id = ? AND user_id != ?
    ");
    $stmt->execute([$gameId, $userId]);
    $opponent = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "status" => $game['status'],
        "current_player" => $game['current_player'],
        "your_turn" => ((int)$game['current_player'] === $userId),

        "table" => json_decode($game['table_cards'], true),
        "hand"  => json_decode($player['hand'], true),

        "your_score" => (int)$player['score'],
        "opponent" => $opponent ? [
            "user_id" => (int)$opponent['user_id'],
            "score"   => (int)$opponent['score']
        ] : null
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

