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
use App\Repository\SplitOrStealMatchRepository;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class GameController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameResultRepository $gameResultRepository,
        private JokerRepository $jokerRepository,
        private TournamentService $tournamentService,
        private SplitOrStealMatchRepository $splitOrStealMatchRepository
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

            // Validate game type
            $validGameTypes = ['free_for_all', 'tournament_team', 'tournament_single', 'quiz', 'split_or_steal'];
            if (!in_array($gameType, $validGameTypes)) {
                $this->addFlash('error', 'Ungültiger Spieltyp');
                return $this->redirectToRoute('app_game_create', ['olympixId' => $olympixId]);
            }

            // Validate player count for game type
            $playerCount = $olympix->getPlayers()->count();
            $minPlayers = $this->getMinPlayersForGameType($gameType);
            
            if ($playerCount < $minPlayers) {
                $this->addFlash('error', "Für $gameType sind mindestens $minPlayers Spieler erforderlich. Aktuell sind nur $playerCount Spieler vorhanden.");
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

            $this->addFlash('success', 'Spiel "' . $name . '" wurde erstellt!');

            // Redirect to appropriate setup page
            if ($gameType === 'split_or_steal') {
                return $this->redirectToRoute('app_split_or_steal_setup', ['gameId' => $game->getId()]);
            } elseif ($gameType === 'quiz') {
                return $this->redirectToRoute('app_quiz_questions', ['gameId' => $game->getId()]);
            } else {
                return $this->redirectToRoute('app_game_admin', ['id' => $olympixId]);
            }
        }

        return $this->render('game/create.html.twig', [
            'olympix' => $olympix,
        ]);
    }

    #[Route('/game/start/{id}', name: 'app_game_start')]
    public function start(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($game->getStatus() !== 'pending') {
            $this->addFlash('error', 'Spiel kann nicht gestartet werden (Status: ' . $game->getStatus() . ')');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Check if game needs setup
        if ($game->needsSetup()) {
            $this->addFlash('error', 'Spiel muss erst konfiguriert werden');
            $setupUrl = $game->getSetupUrl();
            if ($setupUrl) {
                return $this->redirect($setupUrl);
            }
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Special handling for Split or Steal games
        if ($game->isSplitOrStealGame()) {
            $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($game->getId());
            
            if (empty($matches)) {
                $this->addFlash('error', 'Keine Paarungen für Split or Steal vorhanden. Bitte konfiguriere das Spiel erst.');
                return $this->redirectToRoute('app_split_or_steal_setup', ['gameId' => $game->getId()]);
            }
        }

        // Check if there are already active games
        $activeGame = $this->gameRepository->findActiveGameForOlympix($game->getOlympix()->getId());
        if ($activeGame && $activeGame->getId() !== $game->getId()) {
            $this->addFlash('error', 'Es kann nur ein Spiel gleichzeitig aktiv sein. Beende erst "' . $activeGame->getName() . '".');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Set all other games to pending/completed (not active) - VERSION 8
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

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde gestartet!');

        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
    }

    #[Route('/game/complete/{id}', name: 'app_game_complete')]
    public function complete(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($game->getStatus() !== 'active') {
            $this->addFlash('error', 'Spiel kann nicht abgeschlossen werden (Status: ' . $game->getStatus() . ')');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Special handling for Split or Steal games
        if ($game->isSplitOrStealGame()) {
            if (!$game->canBeEvaluated()) {
                $this->addFlash('error', 'Nicht alle Spieler haben ihre Wahl getroffen');
                return $this->redirectToRoute('app_split_or_steal_setup', ['gameId' => $game->getId()]);
            }
            return $this->redirectToRoute('app_split_or_steal_evaluate', ['gameId' => $game->getId()]);
        }

        // VERSION 8: NOTE: Jokers are now applied immediately when results are processed, not on manual completion
        // This method only handles manual game completion without results
        
        $game->setStatus('completed');
        
        // Update player total points
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde abgeschlossen');

        // For other game types, redirect to results page
        return $this->redirectToRoute('app_game_results', ['id' => $game->getId()]);
    }

    #[Route('/game/results/{id}', name: 'app_game_results')]
    public function results(int $id, Request $request): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($game->getStatus() !== 'active') {
            $this->addFlash('error', 'Spiel ist nicht aktiv');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Special handling for Split or Steal games
        if ($game->isSplitOrStealGame()) {
            return $this->redirectToRoute('app_split_or_steal_evaluate', ['gameId' => $game->getId()]);
        }

        if ($request->isMethod('POST')) {
            // VERSION 7: PROCESS GAME RESULTS AND APPLY JOKERS
            $this->processGameResults($game, $request);
            
            $this->addFlash('success', 'Ergebnisse für "' . $game->getName() . '" wurden gespeichert!');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        $players = $game->getOlympix()->getPlayers();
        $existingResults = $this->gameResultRepository->findByGameOrderedByPosition($game->getId());

        // Get ALL double jokers for this game (both pending and used for display) - VERSION 8
        $doubleJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'double'
        ]);
        
        // Get swap joker for this game (if any) - VERSION 8
        $swapJoker = $this->jokerRepository->findOneBy([
            'game' => $game,
            'jokerType' => 'swap'
        ]);

        return $this->render('game/results.html.twig', [
            'game' => $game,
            'players' => $players,
            'existing_results' => $existingResults,
            'double_jokers' => $doubleJokers,
            'swap_joker' => $swapJoker,
            'pending_double_jokers' => $this->jokerRepository->findPendingDoubleJokersByGame($game->getId()),
            'pending_swap_jokers' => $this->jokerRepository->findPendingSwapJokersByGame($game->getId()),
        ]);
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

        if ($game->getStatus() === 'completed') {
            // Remove game results and update player points
            $gameResults = $this->gameResultRepository->findByGameOrderedByPosition($id);
            foreach ($gameResults as $result) {
                $player = $result->getPlayer();
                $player->setTotalPoints($player->getTotalPoints() - $result->getFinalPoints());
                $this->entityManager->persist($player);
                $this->entityManager->remove($result);
            }
        }

        // Remove split or steal matches if any
        if ($game->isSplitOrStealGame()) {
            $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($id);
            foreach ($matches as $match) {
                $this->entityManager->remove($match);
            }
        }

        // VERSION 8: Check if game has results
        if ($game->getGameResults()->count() > 0) {
            $this->addFlash('error', 'Spiel kann nicht gelöscht werden, da bereits Ergebnisse vorhanden sind');
            return $this->redirectToRoute('app_game_admin', ['id' => $olympixId]);
        }

        $this->entityManager->remove($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $gameName . '" wurde gelöscht!');

        return $this->redirectToRoute('app_game_admin', ['id' => $olympixId]);
    }

    #[Route('/game/edit/{id}', name: 'app_game_edit')]
    public function edit(int $id, Request $request): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($game->getStatus() === 'completed') {
            $this->addFlash('error', 'Abgeschlossene Spiele können nicht bearbeitet werden');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
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

            // Validate game type
            $validGameTypes = ['free_for_all', 'tournament_team', 'tournament_single', 'quiz', 'split_or_steal'];
            if (!in_array($gameType, $validGameTypes)) {
                $this->addFlash('error', 'Ungültiger Spieltyp');
                return $this->redirectToRoute('app_game_edit', ['id' => $id]);
            }

            // Check if game type changed and if it has results
            if ($gameType !== $game->getGameType() && ($game->hasResults() || $game->getStatus() === 'active')) {
                $this->addFlash('error', 'Spieltyp kann nicht geändert werden, wenn das Spiel bereits gestartet wurde oder Ergebnisse hat');
                return $this->redirectToRoute('app_game_edit', ['id' => $id]);
            }

            $game->setName($name);
            $game->setGameType($gameType);

            if ($gameType === 'tournament_team' && $teamSize) {
                $game->setTeamSize((int)$teamSize);
            } else {
                $game->setTeamSize(null);
            }

            if ($pointsDistribution) {
                $points = array_map('intval', explode(',', $pointsDistribution));
                $game->setPointsDistribution($points);
            } else {
                $game->setPointsDistribution(null);
            }

            $this->entityManager->persist($game);
            $this->entityManager->flush();

            $this->addFlash('success', 'Spiel "' . $name . '" wurde aktualisiert!');

            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        return $this->render('game/edit.html.twig', [
            'game' => $game,
        ]);
    }

    #[Route('/game/reset/{id}', name: 'app_game_reset')]
    public function reset(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if ($game->getStatus() === 'pending') {
            $this->addFlash('error', 'Spiel ist bereits im Ausgangszustand');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Remove game results and update player points
        if ($game->getStatus() === 'completed') {
            $gameResults = $this->gameResultRepository->findByGameOrderedByPosition($id);
            foreach ($gameResults as $result) {
                $player = $result->getPlayer();
                $player->setTotalPoints($player->getTotalPoints() - $result->getFinalPoints());
                $this->entityManager->persist($player);
                $this->entityManager->remove($result);
            }
        }

        // Remove split or steal matches if any
        if ($game->isSplitOrStealGame()) {
            $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($id);
            foreach ($matches as $match) {
                $this->entityManager->remove($match);
            }
        }

        // Reset game status
        $game->setStatus('pending');
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde zurückgesetzt!');

        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
    }

    #[Route('/game/duplicate/{id}', name: 'app_game_duplicate')]
    public function duplicate(int $id): Response
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        $newGame = new Game();
        $newGame->setName($game->getName() . ' (Kopie)');
        $newGame->setGameType($game->getGameType());
        $newGame->setTeamSize($game->getTeamSize());
        $newGame->setPointsDistribution($game->getPointsDistribution());
        $newGame->setOlympix($game->getOlympix());
        $newGame->setOrderPosition($this->gameRepository->getNextOrderPosition($game->getOlympix()->getId()));

        $this->entityManager->persist($newGame);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde dupliziert!');

        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
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
            $winnerType = $request->request->get('winner_type', 'player');
            
            if ($winnerId) {
                // Handle both player and team winners
                if ($winnerType === 'team') {
                    // Find team data from bracket
                    $winnerData = $this->findTeamDataInBracket($tournament, $winnerId);
                } else {
                    // Single player
                    $winner = $this->playerRepository->find($winnerId);
                    if ($winner) {
                        $winnerData = [
                            'id' => $winner->getId(),
                            'name' => $winner->getName(),
                            'total_points' => $winner->getTotalPoints(),
                            'type' => 'player'
                        ];
                    }
                }
                
                if (isset($winnerData)) {
                    $tournament->updateMatchResult($matchId, $winnerData);
                    
                    // Check if tournament is complete
                    if ($this->tournamentService->isTournamentComplete($tournament)) {
                        $tournament->setIsCompleted(true);
                        
                        // Create tournament results with jokers
                        $this->createTournamentResultsWithJokers($game);
                        
                        // Complete game
                        $game->setStatus('completed');
                        
                        // Update player total points
                        foreach ($game->getOlympix()->getPlayers() as $player) {
                            $player->calculateTotalPoints();
                        }
                        
                        $this->addFlash('success', 'Turnier abgeschlossen! Ergebnisse wurden automatisch erstellt.');
                    } else {
                        $this->addFlash('success', 'Match-Ergebnis wurde gespeichert');
                    }

                    $this->entityManager->flush();
                }
            }
        }

        return $this->redirectToRoute('app_game_bracket', ['id' => $gameId]);
    }

    #[Route('/api/games/update-order', name: 'api_games_update_order', methods: ['POST'])]
    public function updateGamesOrder(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['olympix_id'], $data['games'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Ungültige Daten'
                ], 400);
            }
            
            $olympixId = $data['olympix_id'];
            $games = $data['games'];
            
            foreach ($games as $gameData) {
                if (!isset($gameData['id'], $gameData['order'])) {
                    continue;
                }
                
                $game = $this->gameRepository->find($gameData['id']);
                if ($game && $game->getOlympix()->getId() == $olympixId) {
                    $game->setOrderPosition($gameData['order']);
                    $this->entityManager->persist($game);
                }
            }
            
            $this->entityManager->flush();
            
            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/game/{id}/status', name: 'app_api_game_status')]
    public function apiGameStatus(int $id): JsonResponse
    {
        $game = $this->gameRepository->find($id);

        if (!$game) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }

        $response = [
            'game' => [
                'id' => $game->getId(),
                'name' => $game->getName(),
                'game_type' => $game->getGameType(),
                'status' => $game->getStatus(),
                'can_start' => $game->canStart(),
                'can_complete' => $game->canBeCompleted(),
                'needs_setup' => $game->needsSetup(),
                'setup_url' => $game->getSetupUrl(),
            ],
            'timestamp' => time()
        ];

        // Add specific data for Split or Steal games
        if ($game->isSplitOrStealGame()) {
            $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($id);
            $stats = $this->splitOrStealMatchRepository->getGameStatistics($id);
            
            $response['split_or_steal'] = [
                'matches_count' => count($matches),
                'stats' => $stats,
                'can_evaluate' => $game->canBeEvaluated(),
            ];
        }

        return new JsonResponse($response);
    }

    private function processGameResults(Game $game, Request $request): void
    {
        if ($game->isFreeForAllGame()) {
            $this->processFreeForAllResults($game, $request);
            
            // Flush first, then apply jokers
            $this->entityManager->flush();
            
            // Apply both joker types
            $this->applyJokersForGame($game);
        } elseif ($game->isTournamentGame()) {
            $this->processTournamentResults($game);
            // For tournament games: Jokers are already applied in createTournamentResultsWithJokers()
        }
        
        // Complete game after processing results
        $game->setStatus('completed');
        
        // Update player total points
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }
        
        $this->entityManager->flush();
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
    }

    private function processTournamentResults(Game $game): void
    {
        $this->createTournamentResultsWithJokers($game);
    }

    private function createTournamentResultsWithJokers(Game $game): void
    {
        $tournament = $game->getTournament();
        if (!$tournament || !$tournament->isIsCompleted()) {
            return;
        }

        // Get pending jokers BEFORE creating results
        $pendingDoubleJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'double',
            'isUsed' => false
        ]);

        $pendingSwapJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'swap',
            'isUsed' => false
        ]);

        $results = $tournament->getTournamentResults();
        $pointsDistribution = [8, 6, 4, 2]; // Fixed values for tournaments

        // Clear existing results
        foreach ($game->getGameResults() as $result) {
            $this->entityManager->remove($result);
        }

        // Create GameResults array to track who gets what
        $gameResults = [];

        foreach ($results as $position => $participantData) {
            if ($participantData['type'] === 'team') {
                // Handle team results - distribute points to all team members
                foreach ($participantData['players'] as $playerData) {
                    $player = $this->playerRepository->find($playerData['id']);
                    if ($player) {
                        $result = new GameResult();
                        $result->setGame($game);
                        $result->setPlayer($player);
                        $result->setPosition($position);
                        
                        $points = $pointsDistribution[$position - 1] ?? 0;
                        $result->setPoints($points);

                        $gameResults[$player->getId()] = $result;
                        $this->entityManager->persist($result);
                    }
                }
            } else {
                // Handle single player results
                $player = $this->playerRepository->find($participantData['id']);
                if ($player) {
                    $result = new GameResult();
                    $result->setGame($game);
                    $result->setPlayer($player);
                    $result->setPosition($position);
                    
                    $points = $pointsDistribution[$position - 1] ?? 0;
                    $result->setPoints($points);

                    $gameResults[$player->getId()] = $result;
                    $this->entityManager->persist($result);
                }
            }
        }

        // Flush - save GameResults in DB
        $this->entityManager->flush();

        // Apply Double Jokers
        foreach ($pendingDoubleJokers as $doubleJoker) {
            $player = $doubleJoker->getPlayer();
            
            if ($player && isset($gameResults[$player->getId()])) {
                $result = $gameResults[$player->getId()];
                
                // Apply double joker - mark in GameResult
                $result->setJokerDoubleApplied(true);
                $this->entityManager->persist($result);
                
                // Mark the double joker as used
                $doubleJoker->setIsUsed(true);
                $doubleJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($doubleJoker);
                
                $this->addFlash('info', 
                    'Doppelte-Punkte-Joker angewendet: ' . $player->getName() . 
                    ' für Spiel "' . $game->getName() . '" (Punkte: ' . $result->getPoints() . ' → ' . $result->getFinalPoints() . ')'
                );
            } else {
                // Mark as used but wasted
                $doubleJoker->setIsUsed(true);
                $doubleJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($doubleJoker);
                
                $this->addFlash('warning', 
                    'Doppelte-Punkte-Joker verfallen: ' . $player->getName() . 
                    ' hat nicht an "' . $game->getName() . '" teilgenommen'
                );
            }
        }

        // Apply Swap Jokers
        foreach ($pendingSwapJokers as $swapJoker) {
            $sourcePlayer = $swapJoker->getPlayer();
            $targetPlayer = $swapJoker->getTargetPlayer();
            
            if ($sourcePlayer && $targetPlayer && 
                isset($gameResults[$sourcePlayer->getId()]) && 
                isset($gameResults[$targetPlayer->getId()])) {
                
                $sourceResult = $gameResults[$sourcePlayer->getId()];
                $targetResult = $gameResults[$targetPlayer->getId()];
                
                // Swap the positions and points
                $tempPosition = $sourceResult->getPosition();
                $tempPoints = $sourceResult->getPoints();

                $sourceResult->setPosition($targetResult->getPosition());
                $sourceResult->setPoints($targetResult->getPoints());

                $targetResult->setPosition($tempPosition);
                $targetResult->setPoints($tempPoints);

                $this->entityManager->persist($sourceResult);
                $this->entityManager->persist($targetResult);

                // Mark the swap joker as used
                $swapJoker->setIsUsed(true);
                $swapJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($swapJoker);

                $this->addFlash('info', 
                    'Swap-Joker angewendet: ' . $sourcePlayer->getName() . ' ↔ ' . $targetPlayer->getName() . 
                    ' für Spiel "' . $game->getName() . '" (Positionen getauscht)'
                );
            } else {
                // Mark as used but wasted
                $swapJoker->setIsUsed(true);
                $swapJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($swapJoker);
                
                $this->addFlash('warning', 
                    'Swap-Joker verfallen: ' . $sourcePlayer->getName() . ' oder ' . $targetPlayer->getName() . 
                    ' haben nicht an "' . $game->getName() . '" teilgenommen'
                );
            }
        }

        // Final flush
        $this->entityManager->flush();
    }

    private function applyJokersForGame(Game $game): void
    {
        // Apply double jokers first
        $this->applyDoubleJokersForGame($game);
        
        // Then apply swap jokers
        $this->applySwapJokersForGame($game);
    }

    private function applyDoubleJokersForGame(Game $game): void
    {
        // Get all pending double jokers for this game
        $doubleJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'double',
            'isUsed' => false
        ]);

        if (empty($doubleJokers)) {
            return; // No double jokers for this game
        }

        foreach ($doubleJokers as $doubleJoker) {
            $player = $doubleJoker->getPlayer();
            
            if (!$player) {
                continue;
            }

            // Find player result directly in database
            $playerResult = $this->gameResultRepository->findByPlayerAndGame($player->getId(), $game->getId());

            if ($playerResult) {
                // Apply double joker - mark in GameResult
                $playerResult->setJokerDoubleApplied(true);
                $this->entityManager->persist($playerResult);
                
                // Mark the double joker as used
                $doubleJoker->setIsUsed(true);
                $doubleJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($doubleJoker);
                
                // Log the double joker application
                $this->addFlash('info', 
                    'Doppelte-Punkte-Joker angewendet: ' . $player->getName() . 
                    ' für Spiel "' . $game->getName() . '" (Punkte: ' . $playerResult->getPoints() . ' → ' . $playerResult->getFinalPoints() . ')'
                );
            } else {
                // If player didn't participate, the joker is wasted but mark as used
                $doubleJoker->setIsUsed(true);
                $doubleJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($doubleJoker);
                
                $this->addFlash('warning', 
                    'Doppelte-Punkte-Joker verfallen: ' . $player->getName() . 
                    ' hat nicht an "' . $game->getName() . '" teilgenommen'
                );
            }
        }

        $this->entityManager->flush();
    }

    private function applySwapJokersForGame(Game $game): void
    {
        // Get all pending swap jokers for this game
        $swapJokers = $this->jokerRepository->findBy([
            'game' => $game,
            'jokerType' => 'swap',
            'isUsed' => false
        ]);

        if (empty($swapJokers)) {
            return; // No swap jokers for this game
        }

        foreach ($swapJokers as $swapJoker) {
            $sourcePlayer = $swapJoker->getPlayer();
            $targetPlayer = $swapJoker->getTargetPlayer();
            
            if (!$sourcePlayer || !$targetPlayer) {
                continue;
            }

            // Find results directly in database
            $sourceResult = $this->gameResultRepository->findByPlayerAndGame($sourcePlayer->getId(), $game->getId());
            $targetResult = $this->gameResultRepository->findByPlayerAndGame($targetPlayer->getId(), $game->getId());

            if ($sourceResult && $targetResult) {
                // Swap the positions and points
                $tempPosition = $sourceResult->getPosition();
                $tempPoints = $sourceResult->getPoints();

                $sourceResult->setPosition($targetResult->getPosition());
                $sourceResult->setPoints($targetResult->getPoints());

                $targetResult->setPosition($tempPosition);
                $targetResult->setPoints($tempPoints);

                $this->entityManager->persist($sourceResult);
                $this->entityManager->persist($targetResult);

                // Mark the swap joker as used
                $swapJoker->setIsUsed(true);
                $swapJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($swapJoker);

                // Log the swap for admin view
                $this->addFlash('info', 
                    'Swap-Joker angewendet: ' . $sourcePlayer->getName() . ' ↔ ' . $targetPlayer->getName() . 
                    ' für Spiel "' . $game->getName() . '" (Positionen getauscht)'
                );
            } else {
                // If one or both players didn't participate, the joker is wasted but mark as used
                $swapJoker->setIsUsed(true);
                $swapJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($swapJoker);
                
                $this->addFlash('warning', 
                    'Swap-Joker verfallen: ' . $sourcePlayer->getName() . ' oder ' . $targetPlayer->getName() . 
                    ' haben nicht an "' . $game->getName() . '" teilgenommen'
                );
            }
        }

        $this->entityManager->flush();
    }

    private function findTeamDataInBracket(Tournament $tournament, int $teamId): ?array
    {
        $bracketData = $tournament->getBracketData();
        
        foreach ($bracketData['participants'] as $participant) {
            if ($participant['type'] === 'team' && $participant['id'] === $teamId) {
                return $participant;
            }
        }
        
        return null;
    }

    private function getMinPlayersForGameType(string $gameType): int
    {
        return match($gameType) {
            'free_for_all' => 2,
            'tournament_team' => 4,
            'tournament_single' => 2,
            'quiz' => 1,
            'split_or_steal' => 2,
            default => 2
        };
    }
}