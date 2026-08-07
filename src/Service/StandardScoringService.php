<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Einheitliches Punkteschema für ALLE Spielmodi:
 * Der spielinterne Wert (Fragenpunkte, Abweichung, Beute, Netto-Wertung, ...)
 * bestimmt nur die RANGFOLGE. Punkte für die Gesamtwertung kommen immer aus
 * der Punkteverteilung des Spiels (Standard: n..1 bei n Spielern).
 * Gleichstand teilt den Platz (Competition Ranking 1-1-3).
 */
class StandardScoringService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JokerApplicationService $jokerApplicationService,
    ) {}

    /**
     * @param array<int, int|float> $metricByPlayerId spielinterner Wert je Spieler (höher = besser)
     * @param array<int, Player> $playersById
     * @return array<array{type: string, message: string}> Joker-Meldungen
     */
    public function completeGameByMetric(Game $game, array $metricByPlayerId, array $playersById): array
    {
        arsort($metricByPlayerId);

        $distribution = $game->getDefaultPointsDistribution();

        foreach ($game->getGameResults() as $existing) {
            $this->entityManager->remove($existing);
        }

        $index = 0;
        $groupStartIndex = 0;
        $previousMetric = null;

        foreach ($metricByPlayerId as $playerId => $metric) {
            $player = $playersById[$playerId] ?? null;
            if (!$player) {
                continue;
            }

            if ($previousMetric === null || $metric < $previousMetric) {
                $groupStartIndex = $index;
                $previousMetric = $metric;
            }

            $result = new GameResult();
            $result->setGame($game);
            $result->setPlayer($player);
            $result->setPosition($groupStartIndex + 1);
            $result->setPoints($distribution[$groupStartIndex] ?? 0);
            $this->entityManager->persist($result);

            $index++;
        }

        $this->entityManager->flush();

        $messages = $this->jokerApplicationService->applyJokersForGame($game);

        $game->setStatus('completed');

        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }

        $this->entityManager->flush();

        return $messages;
    }
}
