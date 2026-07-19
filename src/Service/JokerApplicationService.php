<?php

namespace App\Service;

use App\Entity\Game;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wendet ausstehende Joker (Double, dann Swap) auf die GameResults eines Spiels an.
 * Ersetzt die zuvor in GameController und QuizController duplizierte Logik.
 */
class JokerApplicationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JokerRepository $jokerRepository,
        private GameResultRepository $gameResultRepository,
    ) {}

    /**
     * @return array<array{type: string, message: string}> Meldungen für Flash-Anzeige
     */
    public function applyJokersForGame(Game $game): array
    {
        $messages = array_merge(
            $this->applyDoubleJokers($game),
            $this->applySwapJokers($game)
        );

        $this->entityManager->flush();

        return $messages;
    }

    /**
     * @return array<array{type: string, message: string}>
     */
    private function applyDoubleJokers(Game $game): array
    {
        $messages = [];

        $doubleJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'double',
            'isUsed' => false,
        ]);

        foreach ($doubleJokers as $doubleJoker) {
            $player = $doubleJoker->getPlayer();

            if (!$player) {
                continue;
            }

            $playerResult = $this->gameResultRepository->findByPlayerAndGame($player->getId(), $game->getId());

            $doubleJoker->setIsUsed(true);
            $doubleJoker->setUsedAt(new \DateTime());
            $this->entityManager->persist($doubleJoker);

            if ($playerResult) {
                $playerResult->setJokerDoubleApplied(true);
                $this->entityManager->persist($playerResult);

                $messages[] = [
                    'type' => 'info',
                    'message' => 'Doppelte-Punkte-Joker angewendet: ' . $player->getName() .
                        ' für Spiel "' . $game->getName() . '" (Punkte: ' . $playerResult->getPoints() . ' → ' . $playerResult->getFinalPoints() . ')',
                ];
            } else {
                $messages[] = [
                    'type' => 'warning',
                    'message' => 'Doppelte-Punkte-Joker verfallen: ' . $player->getName() .
                        ' hat nicht an "' . $game->getName() . '" teilgenommen',
                ];
            }
        }

        return $messages;
    }

    /**
     * @return array<array{type: string, message: string}>
     */
    private function applySwapJokers(Game $game): array
    {
        $messages = [];

        $swapJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'swap',
            'isUsed' => false,
        ]);

        foreach ($swapJokers as $swapJoker) {
            $sourcePlayer = $swapJoker->getPlayer();
            $targetPlayer = $swapJoker->getTargetPlayer();

            if (!$sourcePlayer || !$targetPlayer) {
                continue;
            }

            $sourceResult = $this->gameResultRepository->findByPlayerAndGame($sourcePlayer->getId(), $game->getId());
            $targetResult = $this->gameResultRepository->findByPlayerAndGame($targetPlayer->getId(), $game->getId());

            $swapJoker->setIsUsed(true);
            $swapJoker->setUsedAt(new \DateTime());
            $this->entityManager->persist($swapJoker);

            if ($sourceResult && $targetResult) {
                $tempPosition = $sourceResult->getPosition();
                $tempPoints = $sourceResult->getPoints();

                $sourceResult->setPosition($targetResult->getPosition());
                $sourceResult->setPoints($targetResult->getPoints());

                $targetResult->setPosition($tempPosition);
                $targetResult->setPoints($tempPoints);

                $this->entityManager->persist($sourceResult);
                $this->entityManager->persist($targetResult);

                $messages[] = [
                    'type' => 'info',
                    'message' => 'Swap-Joker angewendet: ' . $sourcePlayer->getName() . ' ↔ ' . $targetPlayer->getName() .
                        ' für Spiel "' . $game->getName() . '" (Positionen getauscht)',
                ];
            } else {
                $messages[] = [
                    'type' => 'warning',
                    'message' => 'Swap-Joker verfallen: ' . $sourcePlayer->getName() . ' oder ' . $targetPlayer->getName() .
                        ' haben nicht an "' . $game->getName() . '" teilgenommen',
                ];
            }
        }

        return $messages;
    }
}
