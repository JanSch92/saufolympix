<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\Olympix;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Setzt Spiele bzw. ein ganzes Olympix vollständig zurück:
 * Ergebnisse, Fragen/Antworten, Stoppuhr-Versuche, Matches, Würfe und
 * Turnierdaten werden entfernt, eingesetzte Joker an die Spieler zurückgegeben.
 */
class GameResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Setzt ein einzelnes Spiel auf "wartend" zurück (ohne Flush).
     */
    public function resetGame(Game $game): void
    {
        foreach ($game->getGameResults() as $result) {
            $this->entityManager->remove($result);
        }

        // Quiz: Antworten UND Fragen entfernen — beim nächsten Start werden frische Fragen generiert
        foreach ($game->getQuizQuestions() as $question) {
            foreach ($question->getQuizAnswers() as $answer) {
                $this->entityManager->remove($answer);
            }
            $this->entityManager->remove($question);
        }

        foreach ($game->getStopwatchAttempts() as $attempt) {
            $this->entityManager->remove($attempt);
        }
        $game->setStopwatchTarget(null);

        foreach ($game->getSplitOrStealMatches() as $match) {
            $this->entityManager->remove($match);
        }

        foreach ($game->getGamechangerThrows() as $throw) {
            $this->entityManager->remove($throw);
        }

        if ($game->getTournament()) {
            $this->entityManager->remove($game->getTournament());
        }

        // Eingesetzte Joker gehen an die Spieler zurück
        foreach ($game->getJokers() as $joker) {
            $player = $joker->getPlayer();
            if ($player) {
                if ($joker->getJokerType() === 'double') {
                    $player->setJokerDoubleUsed(false);
                } elseif ($joker->getJokerType() === 'swap') {
                    $player->setJokerSwapUsed(false);
                }
            }
            $this->entityManager->remove($joker);
        }

        $game->setStatus('pending');
    }

    /**
     * Setzt ein einzelnes Spiel zurück und aktualisiert die Gesamtpunkte.
     */
    public function resetGameAndRecalculate(Game $game): void
    {
        $this->resetGame($game);
        $this->entityManager->flush();

        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }

        $this->entityManager->flush();
    }

    /**
     * Setzt ein komplettes Olympix zurück: alle Spiele wartend,
     * alle Spieler auf 0 Punkte mit frischen Jokern.
     */
    public function resetOlympix(Olympix $olympix): void
    {
        foreach ($olympix->getGames() as $game) {
            $this->resetGame($game);
        }

        foreach ($olympix->getPlayers() as $player) {
            $player->setTotalPoints(0);
            $player->setJokerDoubleUsed(false);
            $player->setJokerSwapUsed(false);
        }

        $this->entityManager->flush();
    }
}
