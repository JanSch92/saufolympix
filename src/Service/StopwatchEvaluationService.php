<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\StopwatchAttempt;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wertet ein Stoppuhr-Spiel aus: Ranking nach kleinster Abweichung von der
 * Zielzeit, Punkte absteigend (n, n-1, ..., 1) wie beim Quiz.
 */
class StopwatchEvaluationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JokerApplicationService $jokerApplicationService,
    ) {}

    /**
     * Sortiert Versuche nach Abweichung von der Zielzeit (aufsteigend).
     * Bei exakt gleicher Abweichung entscheidet die frühere Abgabe.
     *
     * @param StopwatchAttempt[] $attempts
     * @return StopwatchAttempt[]
     */
    public function rankAttempts(array $attempts, float $target): array
    {
        usort($attempts, function (StopwatchAttempt $a, StopwatchAttempt $b) use ($target) {
            $deviationA = abs((float) $a->getElapsedSeconds() - $target);
            $deviationB = abs((float) $b->getElapsedSeconds() - $target);

            if (abs($deviationA - $deviationB) < 0.000001) {
                return ($a->getCreatedAt() <=> $b->getCreatedAt());
            }

            return $deviationA <=> $deviationB;
        });

        return $attempts;
    }

    /**
     * Punkteverteilung: bester Versuch bekommt so viele Punkte wie es
     * Teilnehmer gibt, danach absteigend bis 1.
     *
     * @param StopwatchAttempt[] $rankedAttempts
     * @return array<int, int> playerId => Punkte
     */
    public function calculatePoints(array $rankedAttempts): array
    {
        $points = [];
        $total = count($rankedAttempts);

        foreach ($rankedAttempts as $index => $attempt) {
            $points[$attempt->getPlayer()->getId()] = $total - $index;
        }

        return $points;
    }

    /**
     * Komplette Auswertung: GameResults erstellen, Joker anwenden,
     * Spiel abschließen, Gesamtpunkte aktualisieren. Idempotent —
     * ein bereits abgeschlossenes Spiel wird nicht erneut ausgewertet.
     *
     * @return array<array{type: string, message: string}> Joker-Meldungen
     */
    public function evaluate(Game $game): array
    {
        if ($game->isCompleted()) {
            return [];
        }

        $attempts = [];
        foreach ($game->getStopwatchAttempts() as $attempt) {
            $attempts[] = $attempt;
        }

        $target = (float) $game->getStopwatchTarget();
        $ranked = $this->rankAttempts($attempts, $target);

        // Bestehende Ergebnisse entfernen (Neuauswertung)
        foreach ($game->getGameResults() as $result) {
            $this->entityManager->remove($result);
        }

        $position = 1;
        $total = count($ranked);
        foreach ($ranked as $index => $attempt) {
            $result = new GameResult();
            $result->setGame($game);
            $result->setPlayer($attempt->getPlayer());
            $result->setPosition($position);
            $result->setPoints($total - $index);

            $this->entityManager->persist($result);
            $position++;
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

    /**
     * Zufällige Zielzeit zwischen 5,00 und 60,00 Sekunden.
     */
    public static function randomTarget(): string
    {
        return number_format(random_int(500, 6000) / 100, 2, '.', '');
    }
}
