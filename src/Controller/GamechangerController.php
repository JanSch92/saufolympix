<?php

namespace App\Controller;

use App\Entity\GamechangerThrow;
use App\Entity\GameResult;
use App\Repository\GameRepository;
use App\Repository\GamechangerThrowRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class GamechangerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private GamechangerThrowRepository $gamechangerThrowRepository,
        private PlayerRepository $playerRepository,
        private GameResultRepository $gameResultRepository,
        private JokerRepository $jokerRepository
    ) {}

    #[Route('/gamechanger/setup/{gameId}', name: 'app_gamechanger_setup')]
    public function setup(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isGamechangerGame()) {
            throw $this->createNotFoundException('Gamechanger Spiel nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            // Lösche existierende Würfe (Reset)
            $existingThrows = $this->gamechangerThrowRepository->findByGameOrderedByPlayerOrder($gameId);
            foreach ($existingThrows as $throw) {
                $this->entityManager->remove($throw);
            }

            // Erstelle zufällige Spielerreihenfolge
            $this->createRandomPlayerOrder($game);
            
            // Setze Spiel auf aktiv
            $game->setStatus('active');
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Gamechanger Spiel wurde gestartet! Zufällige Reihenfolge festgelegt.');
            return $this->redirectToRoute('app_gamechanger_play', ['gameId' => $gameId]);
        }

        $players = $game->getOlympix()->getPlayers();
        $existingThrows = $this->gamechangerThrowRepository->findByGameOrderedByPlayerOrder($gameId);

        return $this->render('gamechanger/setup.html.twig', [
            'game' => $game,
            'players' => $players,
            'existing_throws' => $existingThrows,
        ]);
    }

    #[Route('/gamechanger/play/{gameId}', name: 'app_gamechanger_play')]
    public function play(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isGamechangerGame()) {
            throw $this->createNotFoundException('Gamechanger Spiel nicht gefunden');
        }

        $throws = $this->gamechangerThrowRepository->findByGameOrderedByPlayerOrder($gameId);
        $players = $game->getOlympix()->getPlayers()->toArray();
        
        // Aktuelle Spieler-Punkte für Scoring-Berechnung
        $currentPlayerPoints = [];
        foreach ($players as $player) {
            $currentPlayerPoints[$player->getId()] = $player->getTotalPoints();
        }

        // Nächster Spieler (falls nicht alle geworfen haben)
        $nextPlayer = $this->getNextPlayer($game);
        $isGameComplete = $this->gamechangerThrowRepository->isGameComplete($gameId);

        // Statistiken
        $stats = $this->gamechangerThrowRepository->getGamechangerStatistics($gameId);

        return $this->render('gamechanger/play.html.twig', [
            'game' => $game,
            'throws' => $throws,
            'players' => $players,
            'current_player_points' => $currentPlayerPoints,
            'next_player' => $nextPlayer,
            'is_game_complete' => $isGameComplete,
            'stats' => $stats,
        ]);
    }

    #[Route('/gamechanger/throw/{gameId}', name: 'app_gamechanger_throw', methods: ['POST'])]
    public function addThrow(int $gameId, Request $request): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isGamechangerGame()) {
            return new JsonResponse(['success' => false, 'message' => 'Spiel nicht gefunden']);
        }

        if ($game->getStatus() !== 'active') {
            return new JsonResponse(['success' => false, 'message' => 'Spiel ist nicht aktiv']);
        }

        $playerId = $request->request->get('player_id');
        $thrownPoints = (int) $request->request->get('thrown_points');

        if (empty($playerId) || $thrownPoints < 0) {
            return new JsonResponse(['success' => false, 'message' => 'Ungültige Eingaben']);
        }

        $player = $this->playerRepository->find($playerId);
        if (!$player || $player->getOlympix()->getId() !== $game->getOlympix()->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Spieler nicht gefunden']);
        }

        // GEFIXT: Prüfe ob Spieler bereits ECHTEN Wurf gemacht hat
        if ($this->gamechangerThrowRepository->hasPlayerThrown($gameId, $playerId)) {
            return new JsonResponse(['success' => false, 'message' => 'Spieler hat bereits geworfen']);
        }

        // Prüfe ob alle Spieler bereits geworfen haben
        if ($this->gamechangerThrowRepository->isGameComplete($gameId)) {
            return new JsonResponse(['success' => false, 'message' => 'Spiel ist bereits beendet']);
        }

        // GEFIXT: Finde den bestehenden Platzhalter und aktualisiere ihn
        $throw = $this->gamechangerThrowRepository->findPlaceholderForPlayer($gameId, $playerId);
        
        if (!$throw) {
            // Falls kein Platzhalter gefunden wird, erstelle einen neuen Wurf
            $throw = new GamechangerThrow();
            $throw->setGame($game);
            $throw->setPlayer($player);
            $throw->setPlayerOrder($this->gamechangerThrowRepository->getNextPlayerOrder($gameId));
        }

        // Aktualisiere den Wurf mit echten Daten
        $throw->setThrownPoints($thrownPoints);
        $throw->setThrownAt(new \DateTime()); // Aktualisiere die Zeit

        // Berechne Scoring
        $this->calculateScoring($throw, $game);

        $this->entityManager->persist($throw);

        // Prüfe ob Spiel nach diesem Wurf komplett ist
        $playerCount = $game->getOlympix()->getPlayers()->count();
        $realThrowsAfterThis = $this->gamechangerThrowRepository->getThrowsCount($gameId) + 1;

        if ($realThrowsAfterThis >= $playerCount) {
            // Spiel beenden und finale Ergebnisse erstellen
            $this->completeGame($game);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Wurf erfolgreich hinzugefügt',
            'throw' => [
                'player_name' => $player->getName(),
                'thrown_points' => $thrownPoints,
                'points_scored' => $throw->getPointsScored(),
                'scoring_reason' => $throw->getScoringReason(),
            ],
            'is_game_complete' => $realThrowsAfterThis >= $playerCount
        ]);
    }

    #[Route('/gamechanger/undo-last/{gameId}', name: 'app_gamechanger_undo_last', methods: ['POST'])]
    public function undoLastThrow(int $gameId): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isGamechangerGame()) {
            return new JsonResponse(['success' => false, 'message' => 'Spiel nicht gefunden']);
        }

        $lastThrow = $this->gamechangerThrowRepository->getLastThrow($gameId);
        if (!$lastThrow) {
            return new JsonResponse(['success' => false, 'message' => 'Kein Wurf zum Rückgängigmachen gefunden']);
        }

        // Rückgängigmachen der Punkteänderungen
        $this->undoScoringChanges($lastThrow);

        // GEFIXT: Setze den Wurf zurück auf Platzhalter-Status statt ihn zu löschen
        $lastThrow->setThrownPoints(0);
        $lastThrow->setPointsScored(0);
        $lastThrow->setScoringReason('Reihenfolge festgelegt');
        $lastThrow->setIsProcessed(false);
        $this->entityManager->persist($lastThrow);

        // Wenn das Spiel completed war, setze es zurück auf active
        if ($game->getStatus() === 'completed') {
            $game->setStatus('active');
            
            // Lösche finale GameResults
            $gameResults = $this->gameResultRepository->findBy(['game' => $game]);
            foreach ($gameResults as $result) {
                $this->entityManager->remove($result);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Letzter Wurf wurde rückgängig gemacht',
        ]);
    }

    #[Route('/gamechanger/status/{gameId}', name: 'app_gamechanger_status')]
    public function getStatus(int $gameId): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isGamechangerGame()) {
            return new JsonResponse(['success' => false, 'message' => 'Spiel nicht gefunden']);
        }

        $throws = $this->gamechangerThrowRepository->findByGameOrderedByPlayerOrder($gameId);
        $nextPlayer = $this->getNextPlayer($game);
        $isGameComplete = $this->gamechangerThrowRepository->isGameComplete($gameId);
        $stats = $this->gamechangerThrowRepository->getGamechangerStatistics($gameId);

        return new JsonResponse([
            'success' => true,
            'throws_count' => $this->gamechangerThrowRepository->getThrowsCount($gameId), // Nur echte Würfe
            'next_player' => $nextPlayer ? [
                'id' => $nextPlayer->getId(),
                'name' => $nextPlayer->getName(),
            ] : null,
            'is_game_complete' => $isGameComplete,
            'stats' => $stats,
        ]);
    }

    private function createRandomPlayerOrder($game): void
    {
        $players = $game->getOlympix()->getPlayers()->toArray();
        shuffle($players); // Zufällige Reihenfolge

        // Speichere die Reihenfolge als leere Würfe (Platzhalter)
        foreach ($players as $index => $player) {
            $placeholder = new GamechangerThrow();
            $placeholder->setGame($game);
            $placeholder->setPlayer($player);
            $placeholder->setPlayerOrder($index + 1);
            $placeholder->setThrownPoints(0);
            $placeholder->setPointsScored(0);
            $placeholder->setScoringReason('Reihenfolge festgelegt');
            $placeholder->setIsProcessed(false);

            $this->entityManager->persist($placeholder);
        }
    }

    private function getNextPlayer($game): ?object
    {
        // GEFIXT: Gehe durch die Platzhalter in der richtigen Reihenfolge
        $allThrows = $this->gamechangerThrowRepository->findByGameOrderedByPlayerOrder($game->getId());
        
        foreach ($allThrows as $throw) {
            // Wenn dieser Wurf noch ein Platzhalter ist (thrownPoints = 0), ist das der nächste Spieler
            if ($throw->getThrownPoints() == 0) {
                return $throw->getPlayer();
            }
        }

        return null; // Alle haben geworfen
    }

    private function calculateScoring(GamechangerThrow $throw, $game): void
    {
        $thrownPoints = $throw->getThrownPoints();
        $throwingPlayer = $throw->getPlayer();
        $allPlayers = $game->getOlympix()->getPlayers();

        $pointsScored = 0;
        $scoringReason = 'Keine besonderen Treffer';

        // Regel 1: Eigene Punkte treffen = +8 Punkte
        if ($thrownPoints == $throwingPlayer->getTotalPoints()) {
            $pointsScored = 8;
            $scoringReason = 'Eigene Punkte getroffen (+8)';
            
            // Punkte direkt dem Spieler hinzufügen
            $throwingPlayer->setTotalPoints($throwingPlayer->getTotalPoints() + 8);
        } else {
            // Regel 2: Andere Spieler treffen = sie bekommen -4 Punkte
            $hitPlayers = [];
            
            foreach ($allPlayers as $otherPlayer) {
                if ($otherPlayer->getId() !== $throwingPlayer->getId() && 
                    $thrownPoints == $otherPlayer->getTotalPoints()) {
                    
                    // Punkte vom anderen Spieler abziehen
                    $newPoints = max(0, $otherPlayer->getTotalPoints() - 4); // Nicht unter 0
                    $otherPlayer->setTotalPoints($newPoints);
                    
                    $hitPlayers[] = $otherPlayer->getName();
                }
            }
            
            // Scoring-Informationen setzen basierend auf getroffenen Spielern
            if (!empty($hitPlayers)) {
                $pointsScored = -4; // Für die Anzeige (zeigt den Effekt pro Spieler)
                
                if (count($hitPlayers) == 1) {
                    $scoringReason = $hitPlayers[0] . ' getroffen (-4 für ' . $hitPlayers[0] . ')';
                } else {
                    $scoringReason = count($hitPlayers) . ' Spieler getroffen (-4 für ' . implode(', ', $hitPlayers) . ')';
                }
            }
        }

        $throw->setPointsScored($pointsScored);
        $throw->setScoringReason($scoringReason);
        $throw->setIsProcessed(true);
    }

    private function undoScoringChanges(GamechangerThrow $throw): void
    {
        $scoringReason = $throw->getScoringReason() ?? '';
        $pointsScored = $throw->getPointsScored();
        $throwingPlayer = $throw->getPlayer();
        $thrownPoints = $throw->getThrownPoints();

        if (str_contains($scoringReason, 'Eigene Punkte getroffen')) {
            // Entferne die +8 Punkte vom werfenden Spieler
            $throwingPlayer->setTotalPoints($throwingPlayer->getTotalPoints() - 8);
        } elseif (str_contains($scoringReason, 'getroffen')) {
            // Finde ALLE Spieler die getroffen wurden und gib ihnen die 4 Punkte zurück
            $game = $throw->getGame();
            $allPlayers = $game->getOlympix()->getPlayers();
            
            foreach ($allPlayers as $player) {
                if ($player->getId() !== $throwingPlayer->getId()) {
                    $currentPoints = $player->getTotalPoints();
                    
                    // Prüfe ob dieser Spieler getroffen wurde:
                    // 1. Seine aktuellen Punkte + 4 müssen den geworfenen Punkten entsprechen
                    // 2. ODER seine aktuellen Punkte sind 0 und geworfene Punkte <= 4 (Schutz vor negativen Punkten)
                    if (($currentPoints + 4 == $thrownPoints) || 
                        ($currentPoints == 0 && $thrownPoints <= 4)) {
                        
                        // Gib die 4 Punkte zurück
                        $player->setTotalPoints($currentPoints + 4);
                    }
                }
            }
        }
    }

    private function completeGame($game): void
    {
        $game->setStatus('completed');

        // Erstelle finale GameResults basierend auf den aktuellen Punkten
        $players = $game->getOlympix()->getPlayers()->toArray();
        
        // Sortiere nach Punkten (absteigende Reihenfolge)
        usort($players, function($a, $b) {
            return $b->getTotalPoints() <=> $a->getTotalPoints();
        });

        // Vergabe der finalen Plätze
        foreach ($players as $position => $player) {
            $gameResult = new GameResult();
            $gameResult->setGame($game);
            $gameResult->setPlayer($player);
            $gameResult->setPosition($position + 1);
            $gameResult->setPoints(0); // Punkte wurden bereits während des Spiels vergeben

            $this->entityManager->persist($gameResult);
        }
    }
}