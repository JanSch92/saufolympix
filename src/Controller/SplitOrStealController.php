<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\SplitOrStealMatch;
use App\Entity\GameResult;
use App\Entity\Player;
use App\Repository\GameRepository;
use App\Repository\SplitOrStealMatchRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SplitOrStealController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private SplitOrStealMatchRepository $splitOrStealMatchRepository,
        private PlayerRepository $playerRepository,
        private GameResultRepository $gameResultRepository
    ) {}

    #[Route('/split-or-steal/setup/{gameId}', name: 'app_split_or_steal_setup')]
    public function setup(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isSplitOrStealGame()) {
            throw $this->createNotFoundException('Split or Steal Spiel nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            $pointsAtStake = (int) $request->request->get('points_at_stake', 50);
            
            if ($pointsAtStake <= 0) {
                $this->addFlash('error', 'Punkte müssen größer als 0 sein');
                return $this->redirectToRoute('app_split_or_steal_setup', ['gameId' => $gameId]);
            }

            // Lösche existierende Matches
            $existingMatches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);
            foreach ($existingMatches as $match) {
                $this->entityManager->remove($match);
            }

            // Erstelle neue zufällige Paarungen
            $this->createRandomPairings($game, $pointsAtStake);
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Split or Steal Paarungen wurden erstellt!');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        $existingMatches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);

        return $this->render('split_or_steal/setup.html.twig', [
            'game' => $game,
            'existing_matches' => $existingMatches,
            'players' => $game->getOlympix()->getPlayers(),
        ]);
    }

    #[Route('/split-or-steal/player-choice/{matchId}', name: 'app_split_or_steal_player_choice', methods: ['POST'])]
    public function playerChoice(int $matchId, Request $request): JsonResponse
    {
        $match = $this->splitOrStealMatchRepository->find($matchId);
        $playerId = (int) $request->request->get('player_id');
        $choice = $request->request->get('choice');

        if (!$match || !in_array($choice, ['split', 'steal'])) {
            return new JsonResponse(['success' => false, 'error' => 'Ungültige Daten'], 400);
        }

        $player = $this->playerRepository->find($playerId);
        if (!$player || ($match->getPlayer1()->getId() !== $playerId && $match->getPlayer2()->getId() !== $playerId)) {
            return new JsonResponse(['success' => false, 'error' => 'Spieler nicht berechtigt'], 403);
        }

        if ($match->getIsCompleted()) {
            return new JsonResponse(['success' => false, 'error' => 'Match bereits abgeschlossen'], 400);
        }

        // Prüfe ob Spieler bereits gewählt hat
        if ($match->getPlayerChoice($player) !== null) {
            return new JsonResponse(['success' => false, 'error' => 'Du hast bereits gewählt'], 400);
        }

        // Prüfe ob das Spiel noch aktiv ist
        if ($match->getGame()->getStatus() !== 'active') {
            return new JsonResponse(['success' => false, 'error' => 'Spiel ist nicht mehr aktiv'], 400);
        }

        // Speichere die Wahl
        $match->setPlayerChoice($player, $choice);
        $this->entityManager->persist($match);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Wahl gespeichert',
            'both_chosen' => $match->bothPlayersHaveChosen(),
            'choice' => $choice,
            'player_id' => $playerId,
            'match_id' => $matchId
        ]);
    }

    #[Route('/split-or-steal/evaluate/{gameId}', name: 'app_split_or_steal_evaluate')]
    public function evaluate(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isSplitOrStealGame()) {
            throw $this->createNotFoundException('Split or Steal Spiel nicht gefunden');
        }

        $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);

        if (empty($matches)) {
            $this->addFlash('error', 'Keine Matches vorhanden. Bitte konfiguriere das Spiel erst.');
            return $this->redirectToRoute('app_split_or_steal_setup', ['gameId' => $gameId]);
        }

        // Prüfe ob alle Matches Entscheidungen haben
        $allDecided = true;
        $pendingPlayers = [];
        
        foreach ($matches as $match) {
            if (!$match->bothPlayersHaveChosen()) {
                $allDecided = false;
                if (!$match->getPlayer1Choice()) {
                    $pendingPlayers[] = $match->getPlayer1()->getName();
                }
                if (!$match->getPlayer2Choice()) {
                    $pendingPlayers[] = $match->getPlayer2()->getName();
                }
            }
        }

        if (!$allDecided) {
            $this->addFlash('error', 'Nicht alle Spieler haben ihre Wahl getroffen. Noch ausstehend: ' . implode(', ', $pendingPlayers));
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Berechne Ergebnisse und erstelle GameResults
        foreach ($matches as $match) {
            // Berechne Punkte basierend auf Entscheidungen
            $match->calculatePoints();
            $this->entityManager->persist($match);

            // Erstelle GameResult für Player 1
            $gameResult1 = new GameResult();
            $gameResult1->setGame($game);
            $gameResult1->setPlayer($match->getPlayer1());
            $gameResult1->setPoints($match->getPlayer1Points());
            $gameResult1->setPosition($match->getPlayer1Points() > 0 ? 1 : 2);
            $this->entityManager->persist($gameResult1);

            // Erstelle GameResult für Player 2
            $gameResult2 = new GameResult();
            $gameResult2->setGame($game);
            $gameResult2->setPlayer($match->getPlayer2());
            $gameResult2->setPoints($match->getPlayer2Points());
            $gameResult2->setPosition($match->getPlayer2Points() > 0 ? 1 : 2);
            $this->entityManager->persist($gameResult2);

            // Aktualisiere Spieler-Punkte
            $match->getPlayer1()->setTotalPoints($match->getPlayer1()->getTotalPoints() + $match->getPlayer1Points());
            $match->getPlayer2()->setTotalPoints($match->getPlayer2()->getTotalPoints() + $match->getPlayer2Points());
            $this->entityManager->persist($match->getPlayer1());
            $this->entityManager->persist($match->getPlayer2());
        }

        // Spiel als abgeschlossen markieren
        $game->setStatus('completed');
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Split or Steal wurde ausgewertet! Alle Ergebnisse sind nun im Live-Ranking sichtbar.');
        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
    }

    #[Route('/split-or-steal/results/{gameId}', name: 'app_split_or_steal_results')]
    public function results(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isSplitOrStealGame()) {
            throw $this->createNotFoundException('Split or Steal Spiel nicht gefunden');
        }

        $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);
        $gameResults = $this->gameResultRepository->findByGameOrderedByPosition($gameId);

        return $this->render('split_or_steal/results.html.twig', [
            'game' => $game,
            'matches' => $matches,
            'game_results' => $gameResults,
        ]);
    }

    #[Route('/api/split-or-steal/match-status/{matchId}', name: 'app_api_split_or_steal_match_status')]
    public function getMatchStatus(int $matchId): JsonResponse
    {
        $match = $this->splitOrStealMatchRepository->find($matchId);

        if (!$match) {
            return new JsonResponse(['error' => 'Match nicht gefunden'], 404);
        }

        return new JsonResponse([
            'match_id' => $match->getId(),
            'player1' => [
                'id' => $match->getPlayer1()->getId(),
                'name' => $match->getPlayer1()->getName(),
                'has_chosen' => $match->getPlayer1Choice() !== null,
                'choice' => $match->getPlayer1Choice(),
                'points' => $match->getPlayer1Points(),
            ],
            'player2' => [
                'id' => $match->getPlayer2()->getId(),
                'name' => $match->getPlayer2()->getName(),
                'has_chosen' => $match->getPlayer2Choice() !== null,
                'choice' => $match->getPlayer2Choice(),
                'points' => $match->getPlayer2Points(),
            ],
            'points_at_stake' => $match->getPointsAtStake(),
            'is_completed' => $match->getIsCompleted(),
            'both_chosen' => $match->bothPlayersHaveChosen(),
            'result_description' => $match->getResultDescription(),
            'created_at' => $match->getCreatedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $match->getCompletedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/api/split-or-steal/{gameId}/status', name: 'app_api_split_or_steal_status')]
    public function getGameStatus(int $gameId): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);
        
        if (!$game || !$game->isSplitOrStealGame()) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }

        $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);
        $stats = $this->splitOrStealMatchRepository->getGameStatistics($gameId);
        
        $matchesData = [];
        foreach ($matches as $match) {
            $matchesData[] = [
                'id' => $match->getId(),
                'player1' => [
                    'id' => $match->getPlayer1()->getId(),
                    'name' => $match->getPlayer1()->getName(),
                    'has_chosen' => $match->getPlayer1Choice() !== null,
                    'choice' => $match->getPlayer1Choice(),
                    'points' => $match->getPlayer1Points(),
                ],
                'player2' => [
                    'id' => $match->getPlayer2()->getId(),
                    'name' => $match->getPlayer2()->getName(),
                    'has_chosen' => $match->getPlayer2Choice() !== null,
                    'choice' => $match->getPlayer2Choice(),
                    'points' => $match->getPlayer2Points(),
                ],
                'points_at_stake' => $match->getPointsAtStake(),
                'is_completed' => $match->getIsCompleted(),
                'both_chosen' => $match->bothPlayersHaveChosen(),
                'result_description' => $match->getResultDescription(),
                'created_at' => $match->getCreatedAt()->format('Y-m-d H:i:s'),
                'completed_at' => $match->getCompletedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse([
            'game' => [
                'id' => $game->getId(),
                'name' => $game->getName(),
                'status' => $game->getStatus(),
                'game_type' => $game->getGameType(),
            ],
            'matches' => $matchesData,
            'stats' => $stats,
            'timestamp' => time()
        ]);
    }

    #[Route('/split-or-steal/admin/{gameId}', name: 'app_split_or_steal_admin')]
    public function admin(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isSplitOrStealGame()) {
            throw $this->createNotFoundException('Split or Steal Spiel nicht gefunden');
        }

        $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);
        $stats = $this->splitOrStealMatchRepository->getGameStatistics($gameId);

        return $this->render('split_or_steal/admin.html.twig', [
            'game' => $game,
            'matches' => $matches,
            'stats' => $stats,
        ]);
    }

    #[Route('/split-or-steal/reset/{gameId}', name: 'app_split_or_steal_reset')]
    public function reset(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isSplitOrStealGame()) {
            throw $this->createNotFoundException('Split or Steal Spiel nicht gefunden');
        }

        if ($game->getStatus() === 'completed') {
            $this->addFlash('error', 'Abgeschlossene Spiele können nicht zurückgesetzt werden');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Lösche alle Matches
        $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);
        foreach ($matches as $match) {
            $this->entityManager->remove($match);
        }

        // Setze Spiel zurück auf pending
        $game->setStatus('pending');
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Split or Steal Spiel wurde zurückgesetzt');
        return $this->redirectToRoute('app_split_or_steal_setup', ['gameId' => $gameId]);
    }

    private function createRandomPairings(Game $game, int $pointsAtStake): void
    {
        $players = $game->getOlympix()->getPlayers()->toArray();
        
        if (count($players) < 2) {
            throw new \InvalidArgumentException('Mindestens 2 Spieler erforderlich');
        }

        // Zufällige Reihenfolge
        shuffle($players);

        // Erstelle Paarungen
        for ($i = 0; $i < count($players); $i += 2) {
            if (isset($players[$i + 1])) {
                $match = new SplitOrStealMatch();
                $match->setGame($game);
                $match->setPlayer1($players[$i]);
                $match->setPlayer2($players[$i + 1]);
                $match->setPointsAtStake($pointsAtStake);
                $this->entityManager->persist($match);
            }
        }

        // Wenn ungerade Anzahl, erstelle einen Dummy-Match oder lasse einen Spieler aus
        if (count($players) % 2 !== 0) {
            // Aktuell: Letzter Spieler bleibt ohne Match
            // Alternativ: Erstelle 3-Spieler-Match oder ähnliches
        }
    }
}