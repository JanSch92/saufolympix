<?php

namespace App\Controller;

use App\Entity\GamechangerThrow;
use App\Entity\GameResult;
use App\Repository\GameRepository;
use App\Repository\GamechangerThrowRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use App\Service\StandardScoringService;
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
        private JokerRepository $jokerRepository,
        private StandardScoringService $standardScoringService
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

        // Wurf erst speichern — die Auswertung liest die Würfe aus der Datenbank
        $this->entityManager->flush();

        // Prüfe ob Spiel nach diesem Wurf komplett ist
        $playerCount = $game->getOlympix()->getPlayers()->count();
        $realThrowsAfterThis = $this->gamechangerThrowRepository->getThrowsCount($gameId);

        if ($realThrowsAfterThis >= $playerCount) {
            // Spiel beenden: Einheitsschema-Auswertung inkl. Joker
            $this->completeGame($game);
        }

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

        // Abgeschlossene Spiele haben bereits gewertet (inkl. Joker) —
        // dafür gibt es den kompletten Spiel-Reset im Admin
        if ($game->getStatus() === 'completed') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Spiel ist bereits abgeschlossen und gewertet — nutze im Admin "Zurücksetzen", um es neu zu spielen',
            ]);
        }

        $lastThrow = $this->gamechangerThrowRepository->getLastThrow($gameId);
        if (!$lastThrow) {
            return new JsonResponse(['success' => false, 'message' => 'Kein Wurf zum Rückgängigmachen gefunden']);
        }

        // Wurf zurück auf Platzhalter — die Wertung verändert keine
        // Gesamtpunkte mehr, es gibt also nichts zurückzurechnen
        $lastThrow->setThrownPoints(0);
        $lastThrow->setPointsScored(0);
        $lastThrow->setScoringReason('Reihenfolge festgelegt');
        $lastThrow->setIsProcessed(false);
        $this->entityManager->persist($lastThrow);

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

    /**
     * WICHTIG (Einheitsschema): Die Wertung (+8 / -4) verändert die
     * Gesamtpunkte NICHT mehr direkt. Sie ist die spielinterne Wertung,
     * die am Ende nur die Rangfolge bestimmt — Punkte für die
     * Gesamtwertung kommen wie überall aus der Verteilung (n..1).
     * Referenz für Treffer sind die Punktestände VOR dem Gamechanger
     * (sie bleiben während der Runde stabil).
     */
    private function calculateScoring(GamechangerThrow $throw, $game): void
    {
        $thrownPoints = $throw->getThrownPoints();
        $throwingPlayer = $throw->getPlayer();
        $allPlayers = $game->getOlympix()->getPlayers();

        $pointsScored = 0;
        $scoringReason = 'Keine besonderen Treffer';

        // Regel 1: Eigene Punkte treffen = +8 Wertung
        if ($thrownPoints == $throwingPlayer->getTotalPoints()) {
            $pointsScored = 8;
            $scoringReason = 'Eigene Punkte getroffen (+8 Wertung)';
        } else {
            // Regel 2: Andere Spieler treffen = -4 Wertung für die Getroffenen
            $hitPlayers = [];

            foreach ($allPlayers as $otherPlayer) {
                if ($otherPlayer->getId() !== $throwingPlayer->getId() &&
                    $thrownPoints == $otherPlayer->getTotalPoints()) {
                    $hitPlayers[] = $otherPlayer->getName();
                }
            }

            if (!empty($hitPlayers)) {
                $pointsScored = -4;

                if (count($hitPlayers) == 1) {
                    $scoringReason = $hitPlayers[0] . ' getroffen (-4 Wertung für ' . $hitPlayers[0] . ')';
                } else {
                    $scoringReason = count($hitPlayers) . ' Spieler getroffen (-4 Wertung für ' . implode(', ', $hitPlayers) . ')';
                }
            }
        }

        $throw->setPointsScored($pointsScored);
        $throw->setScoringReason($scoringReason);
        $throw->setIsProcessed(true);
    }

    /**
     * Netto-Wertung je Spieler aus allen Würfen. Deterministisch
     * reproduzierbar, weil die Gesamtpunkte während der Runde stabil sind.
     *
     * @return array<int, int> playerId => Wertung
     */
    private function calculateNetMetric($game): array
    {
        $players = $game->getOlympix()->getPlayers();

        $metric = [];
        foreach ($players as $player) {
            $metric[$player->getId()] = 0;
        }

        foreach ($this->gamechangerThrowRepository->findByGameOrderedByPlayerOrder($game->getId()) as $throw) {
            if (!$throw->isProcessed()) {
                continue;
            }

            $thrower = $throw->getPlayer();
            $thrownPoints = $throw->getThrownPoints();

            if ($thrownPoints == $thrower->getTotalPoints()) {
                $metric[$thrower->getId()] += 8;
            } else {
                foreach ($players as $otherPlayer) {
                    if ($otherPlayer->getId() !== $thrower->getId() &&
                        $thrownPoints == $otherPlayer->getTotalPoints()) {
                        $metric[$otherPlayer->getId()] -= 4;
                    }
                }
            }
        }

        return $metric;
    }

    private function completeGame($game): void
    {
        $playersById = [];
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $playersById[$player->getId()] = $player;
        }

        $metric = $this->calculateNetMetric($game);

        // Einheitsschema: Rangfolge nach Wertung, Punkte aus der Verteilung,
        // Joker werden angewendet, Gesamtpunkte konsistent neu berechnet
        $this->standardScoringService->completeGameByMetric($game, $metric, $playersById);
    }
}