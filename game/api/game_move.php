<?php
header('Content-Type: application/json');

require_once 'db.php';
require_once 'game_rules.php';

$db = getDB();

/*
Expected POST:
{
  "game_id": 1,
  "user_id": 5,
  "card": {"suit":"H","value":"7"}
}
*/

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['game_id'], $data['user_id'], $data['card'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$gameId = (int)$data['game_id'];
$userId = (int)$data['user_id'];
$playedCard = $data['card'];

try {
    $db->beginTransaction();

    /* Φόρτωση παιχνιδιού */
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game || $game['status'] !== 'active') {
        throw new Exception("Game not active");
    }

    if ((int)$game['current_player'] !== $userId) {
        throw new Exception("Not your turn");
    }

    $tableCards = json_decode($game['table_cards'], true);
    $deck = json_decode($game['deck'], true);

    /* Παίκτης */
    $stmt = $db->prepare("
        SELECT * FROM game_players 
        WHERE game_id = ? AND user_id = ?
    ");
    $stmt->execute([$gameId, $userId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        throw new Exception("Player not found");
    }

    $hand = json_decode($player['hand'], true);
    $score = (int)$player['score'];

    /* Έλεγχος ότι το φύλλο υπάρχει στο χέρι */
    $cardIndex = -1;
    foreach ($hand as $i => $c) {
        if ($c['suit'] === $playedCard['suit'] && $c['value'] === $playedCard['value']) {
            $cardIndex = $i;
            break;
        }
    }

    if ($cardIndex === -1) {
        throw new Exception("Card not in hand");
    }

    /* Αφαίρεση φύλλου από χέρι */
    array_splice($hand, $cardIndex, 1);

    /* Κανόνες πιασίματος */
    if (isCapture($playedCard, $tableCards)) {

        $captured = $tableCards;
        $captured[] = $playedCard;

        $points = calculatePoints($captured);
        $score += $points;

        if (isXeri($tableCards, $playedCard)) {
		$score += 10;
	}

        $tableCards = [];

        /* last capture */
        $stmt = $db->prepare("UPDATE games SET last_capture_player = ? WHERE id = ?");
        $stmt->execute([$userId, $gameId]);

    } else {
        /* Δεν πιάνει */
        $tableCards[] = $playedCard;
    }

    /* Ενημέρωση παίκτη */
    $stmt = $db->prepare("
        UPDATE game_players 
        SET hand = ?, score = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($hand),
        $score,
        $player['id']
    ]);

    /* Εύρεση επόμενου παίκτη */
    $stmt = $db->prepare("
        SELECT user_id FROM game_players
        WHERE game_id = ? AND user_id != ?
    ");
    $stmt->execute([$gameId, $userId]);
    $nextPlayer = $stmt->fetchColumn();

    /* Μοίρασμα αν χρειάζεται */
    if (empty($hand)) {

        $stmt = $db->prepare("
            SELECT id, hand FROM game_players WHERE game_id = ?
        ");
        $stmt->execute([$gameId]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $allEmpty = true;
        foreach ($players as $p) {
            if (!empty(json_decode($p['hand'], true))) {
                $allEmpty = false;
            }
        }

        if ($allEmpty && count($deck) >= 8) {
            foreach ($players as $p) {
                $newHand = array_splice($deck, 0, 4);
                $stmt = $db->prepare("UPDATE game_players SET hand = ? WHERE id = ?");
                $stmt->execute([json_encode($newHand), $p['id']]);
            }
        }
    }

    /* Τέλος παιχνιδιού */
    if (count($deck) === 0) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM game_players
            WHERE game_id = ? AND JSON_LENGTH(hand) > 0
        ");
        $stmt->execute([$gameId]);
        $cardsLeft = (int)$stmt->fetchColumn();

        if ($cardsLeft === 0) {
            if (!empty($tableCards) && $game['last_capture_player']) {
                $stmt = $db->prepare("
                    SELECT id, score FROM game_players
                    WHERE game_id = ? AND user_id = ?
                ");
                $stmt->execute([$gameId, $game['last_capture_player']]);
                $last = $stmt->fetch(PDO::FETCH_ASSOC);

                $bonus = calculatePoints($tableCards);
                $stmt = $db->prepare("
                    UPDATE game_players SET score = ?
                    WHERE id = ?
                ");
                $stmt->execute([$last['score'] + $bonus, $last['id']]);
            }

            $stmt = $db->prepare("UPDATE games SET status = 'finished' WHERE id = ?");
            $stmt->execute([$gameId]);

            $db->commit();

            echo json_encode([
                "game_over" => true
            ]);
            exit;
        }
    }

    /* Update game */
    $stmt = $db->prepare("
        UPDATE games 
        SET table_cards = ?, deck = ?, current_player = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($tableCards),
        json_encode($deck),
        $nextPlayer,
        $gameId
    ]);

    $db->commit();

    echo json_encode([
        "success" => true,
        "table" => $tableCards,
        "score" => $score,
        "next_player" => $nextPlayer
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(["error" => $e->getMessage()]);

}
