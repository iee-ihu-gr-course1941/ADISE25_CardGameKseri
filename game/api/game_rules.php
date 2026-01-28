<?php

function calculatePoints(array $cards): int {
    $points = 0;
    foreach ($cards as $card) {
        if (in_array($card['value'], ['A','K','Q','J'])) {
            $points += 1;
        }
        if ($card['value'] === '10' && $card['suit'] === 'D') {
            $points += 2;
        }
    }
    return $points;
}

function isCapture($playedCard, $tableCards) {
    if (empty($tableCards)) return false;

    $top = end($tableCards);

    if ($playedCard['value'] === 'J') return true;

    return $playedCard['value'] === $top['value'];
}


function isXeri($tableCards, $playedCard) {
    return count($tableCards) === 1
        && $playedCard['value'] !== 'J'
        && $playedCard['value'] === $tableCards[0]['value'];
}
