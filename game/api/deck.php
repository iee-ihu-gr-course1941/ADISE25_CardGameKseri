<?php
function createDeck() {
    $suits = ['♥', '♦', '♣', '♠'];
    $values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];

    $deck = [];
    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = ['suit' => $suit, 'value' => $value];
        }
    }
    return $deck;
}

function shuffleDeck(array &$deck): void {
    shuffle($deck);
}

function dealHands(array &$deck): array {
    $hand1 = array_splice($deck, 0, 4);
    $hand2 = array_splice($deck, 0, 4);

    return [$hand1, $hand2];
}
