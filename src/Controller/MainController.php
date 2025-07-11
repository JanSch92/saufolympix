<?php

namespace App\Controller;

use App\Entity\Olympix;
use App\Entity\GameResult;
use App\Entity\Joker;
use App\Repository\OlympixRepository;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository
    ) {}

    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        $recentOlympix = $this->olympixRepository->findActiveOlympix();

        return $this->render('main/index.html.twig', [
            'recent_olympix' => $recentOlympix,
        ]);
    }

    #[Route('/create', name: 'app_create_olympix')]
    public function createOlympix(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            
            if (empty($name)) {
                $this->addFlash('error', 'Name ist erforderlich');
                return $this->redirectToRoute('app_index');
            }

            $olympix = new Olympix();
            $olympix->setName($name);

            $this->entityManager->persist($olympix);
            $this->entityManager->flush();

            $this->addFlash('success', 'Olympix "' . $name . '" wurde erstellt!');
            
            return $this->redirectToRoute('app_game_admin', ['id' => $olympix->getId()]);
        }

        return $this->redirectToRoute('app_index');
    }

    #[Route('/olympix/{id}', name: 'app_show_olympix')]
    public function showOlympix(int $id): Response
    {
        $olympix = $this->olympixRepository->findWithFullData($id);

        if (!$olympix) {
            throw $this->createNotFoundException('Olympix nicht gefunden');
        }

        // Calculate current rankings
        $players = $olympix->getPlayers()->toArray();
        usort($players, function($a, $b) {
            return $b->getTotalPoints() - $a->getTotalPoints();
        });

        $currentGame = $olympix->getCurrentGame();
        $nextGame = $olympix->getNextGame();

        return $this->render('main/show.html.twig', [
            'olympix' => $olympix,
            'players' => $players,
            'current_game' => $currentGame,
            'next_game' => $nextGame,
        ]);
    }

    #[Route('/gameadmin/{id}', name: 'app_game_admin')]
    public function gameAdmin(int $id): Response
    {
        $olympix = $this->olympixRepository->findWithPlayersAndGames($id);

        if (!$olympix) {
            throw $this->createNotFoundException('Olympix nicht gefunden');
        }

        // Sort games by order position
        $games = $olympix->getGames()->toArray();
        usort($games, function($a, $b) {
            return $a->getOrderPosition() - $b->getOrderPosition();
        });

        // Sort players by total points
        $players = $olympix->getPlayers()->toArray();
        usort($players, function($a, $b) {
            return $b->getTotalPoints() - $a->getTotalPoints();
        });

        return $this->render('main/game_admin.html.twig', [
            'olympix' => $olympix,
            'games' => $games,
            'players' => $players,
        ]);
    }

    #[Route('/api/olympix/{id}/status', name: 'app_api_olympix_status')]
    public function apiOlympixStatus(int $id): Response
    {
        $olympix = $this->olympixRepository->findWithFullData($id);

        if (!$olympix) {
            return $this->json(['error' => 'Olympix nicht gefunden'], 404);
        }

        // Get last completed game for ranking changes
        $lastCompletedGame = null;
        $games = $olympix->getGames()->toArray();
        usort($games, function($a, $b) {
            return $b->getOrderPosition() - $a->getOrderPosition();
        });
        
        foreach ($games as $game) {
            if ($game->getStatus() === 'completed') {
                $lastCompletedGame = $game;
                break;
            }
        }

        // Calculate current rankings with position changes
        $players = [];
        $rankingChanges = [];
        
        if ($lastCompletedGame) {
            // Calculate rankings BEFORE last game
            $playersBeforeLastGame = [];
            foreach ($olympix->getPlayers() as $player) {
                $pointsBeforeLastGame = $this->calculatePlayerPointsExcludingGame($player, $lastCompletedGame);
                $playersBeforeLastGame[] = [
                    'id' => $player->getId(),
                    'name' => $player->getName(),
                    'points' => $pointsBeforeLastGame,
                ];
            }
            
            // Sort by points before last game
            usort($playersBeforeLastGame, function($a, $b) {
                return $b['points'] - $a['points'];
            });
            
            // Create position map BEFORE last game
            $positionsBeforeLastGame = [];
            foreach ($playersBeforeLastGame as $index => $player) {
                $positionsBeforeLastGame[$player['id']] = $index + 1;
            }
        }

        // Calculate current rankings
        foreach ($olympix->getPlayers() as $player) {
            $players[] = [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'total_points' => $player->getTotalPoints(),
                'joker_double_available' => $player->hasJokerDoubleAvailable(),
                'joker_swap_available' => $player->hasJokerSwapAvailable(),
            ];
        }

        // Sort by current points
        usort($players, function($a, $b) {
            return $b['total_points'] - $a['total_points'];
        });

        // Calculate ranking changes
        if ($lastCompletedGame && isset($positionsBeforeLastGame)) {
            foreach ($players as $index => &$player) {
                $currentPosition = $index + 1;
                $previousPosition = $positionsBeforeLastGame[$player['id']] ?? $currentPosition;
                
                $change = $previousPosition - $currentPosition; // Positive = moved up, Negative = moved down
                
                $player['position_change'] = $change;
                $player['previous_position'] = $previousPosition;
            }
        } else {
            // No changes if no completed game
            foreach ($players as &$player) {
                $player['position_change'] = 0;
                $player['previous_position'] = null;
            }
        }

        $currentGame = $olympix->getCurrentGame();
        $nextGame = $olympix->getNextGame();

        // Get last completed game if no current game
        $displayGame = $currentGame ?: $lastCompletedGame;

        $gameData = null;
        $gameResults = null;
        
        if ($displayGame) {
            $gameData = [
                'id' => $displayGame->getId(),
                'name' => $displayGame->getName(),
                'type' => $displayGame->getGameType(),
                'status' => $displayGame->getStatus(),
                'is_gamechanger_game' => $displayGame->isGamechangerGame(),
            ];

            // *** NEU: GAMECHANGER-DATEN HINZUFÜGEN ***
            if ($displayGame->isGamechangerGame()) {
                $gamechangerThrowRepository = $this->entityManager->getRepository(\App\Entity\GamechangerThrow::class);
                
                // Lade Gamechanger-Daten
                $throws = $gamechangerThrowRepository->findByGameOrderedByPlayerOrder($displayGame->getId());
                $stats = $gamechangerThrowRepository->getGamechangerStatistics($displayGame->getId());
                $isGameComplete = $gamechangerThrowRepository->isGameComplete($displayGame->getId());
                
                // Finde nächsten Spieler (basierend auf GamechangerController Logik)
                $nextPlayer = null;
                $allThrows = $gamechangerThrowRepository->findByGameOrderedByPlayerOrder($displayGame->getId());
                foreach ($allThrows as $throw) {
                    if ($throw->getThrownPoints() == 0) {
                        $nextPlayer = [
                            'id' => $throw->getPlayer()->getId(),
                            'name' => $throw->getPlayer()->getName()
                        ];
                        break;
                    }
                }
                
                // Konvertiere Würfe für JSON
                $throwsData = [];
                foreach ($throws as $throw) {
                    if ($throw->getThrownPoints() > 0) { // Nur echte Würfe
                        $throwsData[] = [
                            'player' => [
                                'id' => $throw->getPlayer()->getId(),
                                'name' => $throw->getPlayer()->getName()
                            ],
                            'thrown_points' => $throw->getThrownPoints(),
                            'points_scored' => $throw->getPointsScored(),
                            'scoring_reason' => $throw->getScoringReason(),
                            'thrown_at' => $throw->getThrownAt()->format('H:i:s'),
                            'player_order' => $throw->getPlayerOrder()
                        ];
                    }
                }
                
                $gameData['gamechanger'] = [
                    'throws' => $throwsData,
                    'stats' => $stats,
                    'next_player' => $nextPlayer,
                    'is_game_complete' => $isGameComplete,
                    'throws_count' => count($throwsData)
                ];
            }

            // Add tournament bracket data if it's a tournament game
            if ($displayGame->isTournamentGame() && $displayGame->getTournament()) {
                $gameData['tournament'] = [
                    'bracket_data' => $displayGame->getTournament()->getBracketData(),
                    'current_round' => $displayGame->getTournament()->getCurrentRound(),
                    'is_completed' => $displayGame->getTournament()->isIsCompleted(),
                ];

                // Add tournament results if completed
                if ($displayGame->getTournament()->isIsCompleted()) {
                    $gameData['tournament']['tournament_results'] = $displayGame->getTournament()->getTournamentResults();
                }
            }

            // Get game results if game is completed
            if ($displayGame->getStatus() === 'completed') {
                $gameResults = $this->getGameResultsWithJokers($displayGame);
            }
        }

        $nextGameData = null;
        if ($nextGame) {
            $nextGameData = [
                'id' => $nextGame->getId(),
                'name' => $nextGame->getName(),
                'type' => $nextGame->getGameType(),
            ];
        }

        // Get all games for history
        $allGames = [];
        foreach ($olympix->getGames() as $game) {
            $allGames[] = [
                'id' => $game->getId(),
                'name' => $game->getName(),
                'type' => $game->getGameType(),
                'status' => $game->getStatus(),
                'order_position' => $game->getOrderPosition(),
            ];
        }

        return $this->json([
            'olympix' => [
                'id' => $olympix->getId(),
                'name' => $olympix->getName(),
            ],
            'players' => $players,
            'current_game' => $gameData,
            'next_game' => $nextGameData,
            'games' => $allGames,
            'game_results' => $gameResults,
            'last_completed_game' => $lastCompletedGame ? $lastCompletedGame->getName() : null,
            'timestamp' => time(),
        ]);
    }

    #[Route('/api/olympix/{id}/refresh', name: 'app_api_refresh_scores')]
    public function apiRefreshScores(int $id): Response
    {
        $olympix = $this->olympixRepository->find($id);

        if (!$olympix) {
            return $this->json(['error' => 'Olympix nicht gefunden'], 404);
        }

        // Recalculate all player scores
        foreach ($olympix->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    /**
     * Calculate player points excluding a specific game
     */
    private function calculatePlayerPointsExcludingGame($player, $excludeGame): int
    {
        $totalPoints = 0;
        
        foreach ($player->getGameResults() as $result) {
            if ($result->getGame()->getId() !== $excludeGame->getId()) {
                $totalPoints += $result->getFinalPoints(); // Include joker effects
            }
        }
        
        return $totalPoints;
    }

    /**
     * Get game results with joker information for a completed game
     */
    private function getGameResultsWithJokers($game): array
    {
        // Get repositories
        $gameResultRepository = $this->entityManager->getRepository(GameResult::class);
        $jokerRepository = $this->entityManager->getRepository(Joker::class);
        
        // Get game results and jokers
        $results = $gameResultRepository->findByGameOrderedByPosition($game->getId());
        $usedJokers = $jokerRepository->getUsedJokersByGame($game->getId());
        
        // Group jokers by player
        $jokersByPlayer = [];
        foreach ($usedJokers as $joker) {
            $playerId = $joker->getPlayer()->getId();
            if (!isset($jokersByPlayer[$playerId])) {
                $jokersByPlayer[$playerId] = [];
            }
            
            if ($joker->getJokerType() === 'double') {
                $jokersByPlayer[$playerId][] = 'double';
            } elseif ($joker->getJokerType() === 'swap') {
                $jokersByPlayer[$playerId][] = 'swap';
                // Also add for target player
                if ($joker->getTargetPlayer()) {
                    $targetPlayerId = $joker->getTargetPlayer()->getId();
                    if (!isset($jokersByPlayer[$targetPlayerId])) {
                        $jokersByPlayer[$targetPlayerId] = [];
                    }
                    $jokersByPlayer[$targetPlayerId][] = 'swap';
                }
            }
        }
        
        // Build results array
        $gameResults = [];
        foreach ($results as $result) {
            $playerId = $result->getPlayer()->getId();
            $gameResults[] = [
                'player' => [
                    'id' => $result->getPlayer()->getId(),
                    'name' => $result->getPlayer()->getName(),
                ],
                'position' => $result->getPosition(),
                'points' => $result->getPoints(),
                'final_points' => $result->getFinalPoints(), // includes joker effects
                'jokers' => $jokersByPlayer[$playerId] ?? [],
            ];
        }
        
        return $gameResults;
    }
}