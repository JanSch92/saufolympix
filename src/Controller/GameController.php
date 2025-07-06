<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\Tournament;
use App\Repository\GameRepository;
use App\Repository\OlympixRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameResultRepository $gameResultRepository,
        private JokerRepository $jokerRepository,
        private TournamentService $tournamentService
    ) {}

    #[Route('/game/create/{olympixId}', name: 'app_game_create')]
    public function create(int $olympixId, Request $request): Response
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            throw $this->createNotFoundException('Olympix nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $gameType = $request->request->get('game_type');
            $teamSize = $request->request->get('team_size');
            $pointsDistribution = $request->request->get('points_distribution');

            if (empty($name) || empty($gameType)) {
                $this->addFlash('error', 'Name und Spieltyp sind erforderlich');
                return $this->redirectToRoute('app_game_create', ['olympixId' => $olympixId]);
            }

            $game = new Game();
            $game->setName($name);
            $game->setGameType($gameType);
            $game->setOlympix($olympix);
            $game->setOrderPosition($this->gameRepository->getNextOrderPosition($olympixId));

          if ($gameType === 'tournament_team' && $teamSize) {
    $game->setTeamSize((int)$teamSize);
}

            if ($pointsDistribution) {
                $points = array_map('intval', explode(',', $pointsDistribution));
                $game->setPointsDistribution($points);
            }

            $this->entityManager->persist($game);
            $this->entityManager->flush();

            $this->addFlash('success', 'Spiel "' . $name . '" wurde erstellt');
            return $this->redirectToRoute('app_game_admin', ['id' => $olympixId]);
        }

        return $this->render('game/create.html.twig', [
            'olympix' => $olympix,
        ]);
    }

    #[Route('/game/edit/{id}', name: 'app_game_edit')]
    public function edit(int $id, Request $request): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $gameType = $request->request->get('game_type');
            $teamSize = $request->request->get('team_size');
            $pointsDistribution = $request->request->get('points_distribution');

            if (empty($name) || empty($gameType)) {
                $this->addFlash('error', 'Name und Spieltyp sind erforderlich');
                return $this->redirectToRoute('app_game_edit', ['id' => $id]);
            }

            $game->setName($name);
            $game->setGameType($gameType);

            if ($teamSize) {
                $game->setTeamSize((int)$teamSize);
            }

            if ($pointsDistribution) {
                $points = array_map('intval', explode(',', $pointsDistribution));
                $game->setPointsDistribution($points);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Spiel wurde bearbeitet');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        return $this->render('game/edit.html.twig', [
            'game' => $game,
        ]);
    }

    #[Route('/game/start/{id}', name: 'app_game_start')]
    public function start(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        // Set all other games to pending
        $allGames = $this->gameRepository->findByOlympixOrdered($game->getOlympix()->getId());
        foreach ($allGames as $g) {
            if ($g->getStatus() === 'active') {
                $g->setStatus('pending');
            }
        }

        $game->setStatus('active');

        // Initialize tournament if needed
        if ($game->isTournamentGame()) {
            $this->tournamentService->initializeTournament($game);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde gestartet');
        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
    }

    #[Route('/game/results/{id}', name: 'app_game_results')]
    public function results(int $id, Request $request): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            $this->processGameResults($game, $request);
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        $players = $game->getOlympix()->getPlayers();
        $existingResults = $this->gameResultRepository->findByGameOrderedByPosition($game->getId());

        // Get double jokers for this game
        $doubleJokers = $this->jokerRepository->findDoubleJokersByGame($game->getId());

        return $this->render('game/results.html.twig', [
            'game' => $game,
            'players' => $players,
            'existing_results' => $existingResults,
            'double_jokers' => $doubleJokers,
        ]);
    }

    #[Route('/game/bracket/{id}', name: 'app_game_bracket')]
    public function bracket(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isTournamentGame()) {
            $this->addFlash('error', 'Bracket ist nur für Turnierspiele verfügbar');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        $tournament = $game->getTournament();
        if (!$tournament) {
            $this->addFlash('error', 'Turnier wurde noch nicht initialisiert');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        return $this->render('game/bracket.html.twig', [
            'game' => $game,
            'tournament' => $tournament,
        ]);
    }

    #[Route('/game/match-result/{gameId}/{matchId}', name: 'app_game_match_result')]
    public function matchResult(int $gameId, string $matchId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        $tournament = $game->getTournament();
        if (!$tournament) {
            throw $this->createNotFoundException('Turnier nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            $winnerId = $request->request->get('winner_id');
            
            if ($winnerId) {
                $winner = $this->playerRepository->find($winnerId);
                if ($winner) {
                    $winnerData = [
                        'id' => $winner->getId(),
                        'name' => $winner->getName(),
                        'total_points' => $winner->getTotalPoints()
                    ];
                    
                    $tournament->updateMatchResult($matchId, $winnerData);
                    if ($this->tournamentService->isTournamentComplete($tournament)) {
                        $tournament->setIsCompleted(true);
                    }

                    $this->entityManager->flush();
                    
                    $this->addFlash('success', 'Match-Ergebnis wurde gespeichert');
                }
            }
        }

        return $this->redirectToRoute('app_game_bracket', ['id' => $gameId]);
    }
    #[Route('/api/games/update-order', name: 'app_api_games_update_order', methods: ['POST'])]
public function updateGamesOrder(Request $request): Response
{
    $data = json_decode($request->getContent(), true);
    
    foreach ($data['games'] as $gameData) {
        $game = $this->gameRepository->find($gameData['id']);
        if ($game) {
            $game->setOrderPosition($gameData['order']);
        }
    }
    
    $this->entityManager->flush();
    
    return $this->json(['success' => true]);
}
    #[Route('/game/complete/{id}', name: 'app_game_complete')]
    public function complete(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        // Process final results and apply jokers
        $this->processSwapJokers($game);
        
        $game->setStatus('completed');
        
        // Update player total points
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }

        $this->entityManager->flush();

        
        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde abgeschlossen');
        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
    }

    #[Route('/game/delete/{id}', name: 'app_game_delete')]
    public function delete(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        $olympixId = $game->getOlympix()->getId();
        $gameName = $game->getName();

        // Check if game has results
        if ($game->getGameResults()->count() > 0) {
            $this->addFlash('error', 'Spiel kann nicht gelöscht werden, da bereits Ergebnisse vorhanden sind');
            return $this->redirectToRoute('app_game_admin', ['id' => $olympixId]);
        }

        $this->entityManager->remove($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $gameName . '" wurde gelöscht');
        return $this->redirectToRoute('app_game_admin', ['id' => $olympixId]);
    }

    private function processGameResults(Game $game, Request $request): void
    {
        if ($game->isFreeForAllGame()) {
            $this->processFreeForAllResults($game, $request);
        } elseif ($game->isTournamentGame()) {
            $this->processTournamentResults($game, $request);
        }
    }

    private function processFreeForAllResults(Game $game, Request $request): void
    {
        $positions = $request->request->all()['positions'] ?? [];
        $pointsDistribution = $game->getDefaultPointsDistribution();

        // Clear existing results
        foreach ($game->getGameResults() as $result) {
            $this->entityManager->remove($result);
        }

        foreach ($positions as $playerId => $position) {
            $player = $this->playerRepository->find($playerId);
            if ($player && $position > 0) {
                $result = new GameResult();
                $result->setGame($game);
                $result->setPlayer($player);
                $result->setPosition((int)$position);
                
                // Calculate points based on position
                $points = $pointsDistribution[(int)$position - 1] ?? 0;
                $result->setPoints($points);

                $this->entityManager->persist($result);
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'Ergebnisse wurden gespeichert');
        // Total points für alle Spieler aktualisieren
foreach ($game->getOlympix()->getPlayers() as $player) {
    $player->calculateTotalPoints();
}

$this->entityManager->flush();
    }

    private function processTournamentResults(Game $game, Request $request): void
    {
        $tournament = $game->getTournament();
        if (!$tournament) {
            return;
        }

        $results = $tournament->getTournamentResults();
        $pointsDistribution = $game->getDefaultPointsDistribution();

        // Clear existing results
        foreach ($game->getGameResults() as $result) {
            $this->entityManager->remove($result);
        }

        foreach ($results as $position => $playerData) {
            $player = $this->playerRepository->find($playerData['id']);
            if ($player) {
                $result = new GameResult();
                $result->setGame($game);
                $result->setPlayer($player);
                $result->setPosition($position);
                
                $points = $pointsDistribution[$position - 1] ?? 0;
                $result->setPoints($points);

                $this->entityManager->persist($result);
            }
        }

        $tournament->setIsCompleted(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'Turnier-Ergebnisse wurden gespeichert');
    }

    private function processSwapJokers(Game $game): void
    {
        $swapJokers = $this->jokerRepository->findSwapJokersByGame($game->getId());
        
        foreach ($swapJokers as $joker) {
            $sourcePlayer = $joker->getPlayer();
            $targetPlayer = $joker->getTargetPlayer();
            
            if (!$sourcePlayer || !$targetPlayer) {
                continue;
            }

            // Check if target player is already blocked by another swap
            if ($this->jokerRepository->hasSwapJokerOnPlayer($targetPlayer->getId(), $game->getId())) {
                $swapJokersOnTarget = $this->jokerRepository->findBy([
                    'targetPlayer' => $targetPlayer,
                    'game' => $game,
                    'jokerType' => 'swap',
                    'isUsed' => true
                ]);

                if (count($swapJokersOnTarget) > 1) {
                    // Multiple swaps on same target - block all
                    continue;
                }
            }

            // Perform the swap
            $sourceResult = $this->gameResultRepository->findByPlayerAndGame($sourcePlayer->getId(), $game->getId());
            $targetResult = $this->gameResultRepository->findByPlayerAndGame($targetPlayer->getId(), $game->getId());

            if ($sourceResult && $targetResult) {
                $tempPoints = $sourceResult->getPoints();
                $sourceResult->setPoints($targetResult->getPoints());
                $targetResult->setPoints($tempPoints);
            }
        }

        $this->entityManager->flush();
    }
}