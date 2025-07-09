<?php

namespace App\Controller;

use App\Entity\Joker;
use App\Repository\OlympixRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use App\Repository\JokerRepository;
use App\Repository\GameResultRepository;
use App\Repository\SplitOrStealMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class PlayerInterfaceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private PlayerRepository $playerRepository,
        private GameRepository $gameRepository,
        private JokerRepository $jokerRepository,
        private GameResultRepository $gameResultRepository,
        private SplitOrStealMatchRepository $splitOrStealMatchRepository
    ) {}

    #[Route('/player-access/{olympixId}', name: 'app_player_access')]
    public function playerAccess(int $olympixId): Response
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            throw $this->createNotFoundException('Olympix nicht gefunden');
        }

        return $this->render('player_interface/access.html.twig', [
            'olympix' => $olympix,
        ]);
    }

    #[Route('/player-dashboard/{olympixId}/{playerId}', name: 'app_player_dashboard')]
    public function playerDashboard(int $olympixId, int $playerId): Response
    {
        $olympix = $this->olympixRepository->find($olympixId);
        $player = $this->playerRepository->find($playerId);

        if (!$olympix || !$player || $player->getOlympix()->getId() !== $olympixId) {
            throw $this->createNotFoundException('Spieler oder Olympix nicht gefunden');
        }

        // Aktuelle Rangliste - sortiert nach Punkten
        $players = $this->playerRepository->findBy(['olympix' => $olympix], ['totalPoints' => 'DESC']);
        
        // Aktuelles Spiel
        $currentGame = $this->gameRepository->findActiveGameForOlympix($olympixId);
        
        // Nächstes Spiel
        $nextGame = $this->gameRepository->findNextGameToPlay($olympixId);

        // Zukünftige Spiele für Joker
        $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);

        // Joker-Status
        $canUseDoubleJoker = $player->hasJokerDoubleAvailable() && count($pendingGames) > 0;
        $canUseSwapJoker = $player->hasJokerSwapAvailable() && count($pendingGames) > 0;

        return $this->render('player_interface/dashboard.html.twig', [
            'olympix' => $olympix,
            'player' => $player,
            'players' => $players,
            'current_game' => $currentGame,
            'next_game' => $nextGame,
            'can_use_double_joker' => $canUseDoubleJoker,
            'can_use_swap_joker' => $canUseSwapJoker,
            'pending_games_count' => count($pendingGames),
        ]);
    }

    #[Route('/player-joker-double/{olympixId}/{playerId}', name: 'app_player_joker_double')]
    public function useDoubleJoker(int $olympixId, int $playerId, Request $request): Response
    {
        $player = $this->playerRepository->find($playerId);
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$player || !$olympix) {
            $this->addFlash('error', 'Spieler oder Olympix nicht gefunden');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Check if player still has joker available (global)
        if (!$player->hasJokerDoubleAvailable()) {
            $this->addFlash('error', 'Doppelte Punkte Joker bereits verwendet');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Get PENDING games (not active!)
        $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);

        if (empty($pendingGames)) {
            $this->addFlash('error', 'Keine zukünftigen Spiele gefunden. Der Doppelte-Punkte-Joker kann nur für noch nicht gespielte Spiele verwendet werden.');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        if ($request->isMethod('POST')) {
            $selectedGameId = $request->request->get('selected_game_id');
            
            if (!$selectedGameId) {
                $this->addFlash('error', 'Bitte wähle ein Spiel aus');
                return $this->redirectToRoute('app_player_joker_double', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            $selectedGame = $this->gameRepository->find($selectedGameId);

            if (!$selectedGame || $selectedGame->getOlympix()->getId() !== $olympixId || $selectedGame->getStatus() !== 'pending') {
                $this->addFlash('error', 'Ungültiges Spiel ausgewählt - nur zukünftige Spiele erlaubt');
                return $this->redirectToRoute('app_player_joker_double', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // Check if player already has a double joker for this game
            $existingDoubleJoker = $this->jokerRepository->findOneBy([
                'player' => $player,
                'game' => $selectedGame,
                'jokerType' => 'double'
            ]);

            if ($existingDoubleJoker) {
                $this->addFlash('error', 'Doppelte Punkte Joker bereits für "' . $selectedGame->getName() . '" vorgemerkt');
                return $this->redirectToRoute('app_player_joker_double', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // VERSION 3: Create joker record for this specific game
            $joker = new Joker();
            $joker->setPlayer($player);
            $joker->setGame($selectedGame);
            $joker->setJokerType('double');
            $joker->use();

            // VERSION 4: VORMERKEN: Create joker record for this specific FUTURE game
            $joker->setIsUsed(false); // NOT used yet - will be applied when game is played!

            // Mark global joker as used
            $player->setJokerDoubleUsed(true);

            $this->entityManager->persist($joker);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Filter games where double joker is still possible
        $availableGamesWithStatus = [];
        foreach ($pendingGames as $game) {
            // Check if this game already has a double joker from this player
            $hasDoubleJoker = $this->jokerRepository->findOneBy([
                'player' => $player,
                'game' => $game,
                'jokerType' => 'double'
            ]) !== null;
            
            $availableGamesWithStatus[] = [
                'game' => $game,
                'has_double_joker' => $hasDoubleJoker
            ];
        }

        return $this->render('player_interface/double_joker.html.twig', [
            'olympix' => $olympix,
            'player' => $player,
            'available_games_with_status' => $availableGamesWithStatus,
        ]);
    }

    #[Route('/player-joker-swap/{olympixId}/{playerId}', name: 'app_player_joker_swap')]
    public function useSwapJoker(int $olympixId, int $playerId, Request $request): Response
    {
        $player = $this->playerRepository->find($playerId);
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$player || !$olympix) {
            $this->addFlash('error', 'Spieler oder Olympix nicht gefunden');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Check if player still has joker available (global)
        if (!$player->hasJokerSwapAvailable()) {
            $this->addFlash('error', 'Punkte-Tausch Joker bereits verwendet');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Get PENDING games (not completed!)
        $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);

        if (empty($pendingGames)) {
            $this->addFlash('error', 'Keine zukünftigen Spiele gefunden. Der Swap-Joker kann nur für noch nicht gespielte Spiele verwendet werden.');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Get last completed game for info display
        $lastCompletedGame = $this->gameRepository->findLastCompletedGameForOlympix($olympixId);

        if ($request->isMethod('POST')) {
            $targetPlayerId = $request->request->get('target_player_id');
            $selectedGameId = $request->request->get('selected_game_id');
            
            if (!$targetPlayerId || !$selectedGameId) {
                $this->addFlash('error', 'Bitte wähle sowohl einen Spieler als auch ein Spiel aus');
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            $targetPlayer = $this->playerRepository->find($targetPlayerId);
            $selectedGame = $this->gameRepository->find($selectedGameId);

            if (!$targetPlayer || $targetPlayer->getOlympix()->getId() !== $olympixId) {
                $this->addFlash('error', 'Ungültiger Zielspieler');
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            if (!$selectedGame || $selectedGame->getOlympix()->getId() !== $olympixId || $selectedGame->getStatus() !== 'pending') {
                $this->addFlash('error', 'Ungültiges Spiel ausgewählt - nur zukünftige Spiele erlaubt');
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            if ($targetPlayer->getId() === $player->getId()) {
                $this->addFlash('error', 'Du kannst nicht mit dir selbst tauschen');
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // Check if ANY swap already exists for this game (only one swap per game allowed)
            if ($this->jokerRepository->hasAnySwapForGame($selectedGame->getId())) {
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // VERSION 3: Create swap joker
            $joker = new Joker();
            $joker->setPlayer($player);
            $joker->setTargetPlayer($targetPlayer);
            $joker->setGame($selectedGame);
            $joker->setJokerType('swap');
            $joker->use();

            // VERSION 4: VORMERKEN: Create joker record for this specific FUTURE game
            $joker->setIsUsed(false); // NOT used yet - will be applied when game is played!

            // Mark global joker as used
            $player->setJokerSwapUsed(true);

            $this->entityManager->persist($joker);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // VERSION 3: Get other players as potential swap targets
        $otherPlayers = $this->playerRepository->findBy(['olympix' => $olympix]);
        $otherPlayers = array_filter($otherPlayers, fn($p) => $p->getId() !== $player->getId());

        // VERSION 4: Get available players (excluding current player)
        $availablePlayers = $this->playerRepository->findBy(['olympix' => $olympix]);
        $availablePlayers = array_filter($availablePlayers, function($p) use ($player) {
            return $p->getId() !== $player->getId();
        });

        // VERSION 4: Filter games where swap is still possible (no swap reserved yet)
        $availableGamesWithPlayerStatus = [];
        foreach ($pendingGames as $game) {
            // Check if this game already has a swap reserved
            $hasSwap = $this->jokerRepository->hasAnySwapForGame($game->getId());
            
            $availableGamesWithPlayerStatus[] = [
                'game' => $game,
                'has_swap' => $hasSwap,
                'blocked_players' => [] // Not needed for pending games
            ];
        }

        return $this->render('player_interface/swap_joker.html.twig', [
            'olympix' => $olympix,
            'player' => $player,
            'other_players' => $otherPlayers,
            'available_players' => $availablePlayers,
            'pending_games' => $pendingGames,
            'available_games_with_status' => $availableGamesWithPlayerStatus,
            'last_completed_game' => $lastCompletedGame,
        ]);
    }

    // =============================================================================
    // API ROUTES - SPEZIFISCHE ROUTEN ZUERST (olympixId/playerId)
    // =============================================================================

    #[Route('/api/player/{olympixId}/{playerId}/status', name: 'app_api_player_status')]
    public function apiPlayerStatus(int $olympixId, int $playerId): JsonResponse
    {
        $player = $this->playerRepository->find($playerId);
        $olympix = $this->olympixRepository->find($olympixId);
        
        if (!$player || !$olympix || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Spieler oder Olympix nicht gefunden'], 404);
        }

        $currentGame = $this->gameRepository->findActiveGameForOlympix($olympixId);
        $nextGame = $this->gameRepository->findNextGameToPlay($olympixId);
        $lastCompletedGame = $this->gameRepository->findLastCompletedGameForOlympix($olympixId);
        $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);

        // VERSION 4: Spieler-Position in Rangliste finden
        $players = $this->playerRepository->findBy(['olympix' => $player->getOlympix()], ['totalPoints' => 'DESC']);
        $position = 1;
        foreach ($players as $p) {
            if ($p->getId() === $player->getId()) {
                break;
            }
            $position++;
        }

        return new JsonResponse([
            'player' => [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'total_points' => $player->getTotalPoints(),
                'position' => $position,
                'joker_double_available' => $player->hasJokerDoubleAvailable(),
                'joker_swap_available' => $player->hasJokerSwapAvailable(),
            ],
            'current_game' => $currentGame ? [
                'id' => $currentGame->getId(),
                'name' => $currentGame->getName(),
                'status' => $currentGame->getStatus(),
                'game_type' => $currentGame->getGameType(),
                'type' => $currentGame->getGameType(),
            ] : null,
            'next_game' => $nextGame ? [
                'id' => $nextGame->getId(),
                'name' => $nextGame->getName(),
                'status' => $nextGame->getStatus(),
                'game_type' => $nextGame->getGameType(),
                'type' => $nextGame->getGameType(),
            ] : null,
            'last_completed_game' => $lastCompletedGame ? [
                'id' => $lastCompletedGame->getId(),
                'name' => $lastCompletedGame->getName(),
                'status' => $lastCompletedGame->getStatus(),
                'game_type' => $lastCompletedGame->getGameType(),
            ] : null,
            'pending_games_count' => count($pendingGames),
            'ranking' => array_map(fn($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'total_points' => $p->getTotalPoints(),
            ], $players),
        ]);
    }

#[Route('/api/player/{olympixId}/{playerId}/joker-status', name: 'app_api_player_joker_status_interface')]
public function apiPlayerJokerStatus(int $olympixId, int $playerId): JsonResponse
{
    $player = $this->playerRepository->find($playerId);
    
    if (!$player || $player->getOlympix()->getId() !== $olympixId) {
        return new JsonResponse(['error' => 'Spieler nicht gefunden'], 404);
    }

    $currentGame = $this->gameRepository->findActiveGameForOlympix($olympixId);
    $lastCompletedGame = $this->gameRepository->findLastCompletedGameForOlympix($olympixId);
    $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);

    // NEU: Finde vorgemerkte Joker
    $pendingDoubleJoker = $this->jokerRepository->findOneBy([
        'player' => $player,
        'jokerType' => 'double',
        'isUsed' => false
    ]);
    
    $pendingSwapJoker = $this->jokerRepository->findOneBy([
        'player' => $player,
        'jokerType' => 'swap',
        'isUsed' => false
    ]);

    return new JsonResponse([
        'player_id' => $player->getId(),
        'olympix_id' => $olympixId,
        'joker_double_available_global' => $player->hasJokerDoubleAvailable(),
        'joker_swap_available_global' => $player->hasJokerSwapAvailable(),
        'can_use_double_joker' => $player->hasJokerDoubleAvailable() && count($pendingGames) > 0,
        'can_use_swap_joker' => $player->hasJokerSwapAvailable() && count($pendingGames) > 0,
        
        // NEU: Informationen über verwendete Joker
        'double_joker_used_for' => $pendingDoubleJoker ? $pendingDoubleJoker->getGame()->getName() : null,
        'swap_joker_used_for' => $pendingSwapJoker ? $pendingSwapJoker->getGame()->getName() : null,
        
        'current_game' => $currentGame ? [
            'id' => $currentGame->getId(),
            'name' => $currentGame->getName(),
            'status' => $currentGame->getStatus(),
        ] : null,
        'last_completed_game' => $lastCompletedGame ? [
            'id' => $lastCompletedGame->getId(),
            'name' => $lastCompletedGame->getName(),
            'status' => $lastCompletedGame->getStatus(),
        ] : null,
        'pending_games_count' => count($pendingGames),
    ]);
}

    #[Route('/api/player/{olympixId}/{playerId}/pending-doubles', name: 'app_api_player_pending_doubles')]
    public function apiPlayerPendingDoubles(int $olympixId, int $playerId): JsonResponse
    {
        $player = $this->playerRepository->find($playerId);
        
        if (!$player || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Spieler nicht gefunden'], 404);
        }

        // Find pending double jokers for this player
        $pendingDoubles = $this->jokerRepository->findBy([
            'player' => $player,
            'jokerType' => 'double',
            'isUsed' => false
        ]);

        $doubleData = [];
        foreach ($pendingDoubles as $double) {
            $doubleData[] = [
                'id' => $double->getId(),
                'game_name' => $double->getGame()->getName(),
                'game_id' => $double->getGame()->getId(),
            ];
        }

        return new JsonResponse([
            'pending_doubles' => $doubleData,
            'total_pending' => count($doubleData)
        ]);
    }

    #[Route('/api/player/{olympixId}/{playerId}/pending-swaps', name: 'app_api_player_pending_swaps')]
    public function apiPlayerPendingSwaps(int $olympixId, int $playerId): JsonResponse
    {
        $player = $this->playerRepository->find($playerId);
        
        if (!$player || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Spieler nicht gefunden'], 404);
        }

        // Find pending swaps for this player
        $pendingSwaps = $this->jokerRepository->findBy([
            'player' => $player,
            'jokerType' => 'swap',
            'isUsed' => false
        ]);

        $swapData = [];
        foreach ($pendingSwaps as $swap) {
            $swapData[] = [
                'id' => $swap->getId(),
                'game_name' => $swap->getGame()->getName(),
                'game_id' => $swap->getGame()->getId(),
                'target_player_name' => $swap->getTargetPlayer()->getName(),
                'target_player_id' => $swap->getTargetPlayer()->getId(),
            ];
        }

        return new JsonResponse([
            'pending_swaps' => $swapData,
            'total_pending' => count($swapData)
        ]);
    }

    #[Route('/api/player/{olympixId}/{playerId}/available-swap-targets', name: 'app_api_player_swap_targets')]
    public function apiAvailableSwapTargets(int $olympixId, int $playerId): JsonResponse
    {
        $player = $this->playerRepository->find($playerId);
        $olympix = $this->olympixRepository->find($olympixId);
        
        if (!$player || !$olympix || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Spieler oder Olympix nicht gefunden'], 404);
        }

        $lastCompletedGame = $this->gameRepository->findLastCompletedGameForOlympix($olympixId);

        if (!$lastCompletedGame) {
            return new JsonResponse([
                'available_targets' => [],
                'message' => 'Kein abgeschlossenes Spiel gefunden'
            ]);
        }

        $allPlayers = $this->playerRepository->findBy(['olympix' => $olympix]);
        $availableTargets = [];

        foreach ($allPlayers as $p) {
            if ($p->getId() === $player->getId()) {
                continue; // Skip current player
            }

            $availableTargets[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'total_points' => $p->getTotalPoints(),
                'points_difference' => $p->getTotalPoints() - $player->getTotalPoints(),
            ];
        }

        return new JsonResponse([
            'available_targets' => $availableTargets,
            'last_completed_game' => [
                'id' => $lastCompletedGame->getId(),
                'name' => $lastCompletedGame->getName(),
            ]
        ]);
    }

    #[Route('/api/player/{olympixId}/{playerId}/debug', name: 'app_api_player_debug')]
    public function apiPlayerDebug(int $olympixId, int $playerId): JsonResponse
    {
        $player = $this->playerRepository->find($playerId);
        $olympix = $this->olympixRepository->find($olympixId);
        
        if (!$player || !$olympix || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Spieler oder Olympix nicht gefunden'], 404);
        }

        $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);
        $allGames = $this->gameRepository->findByOlympixOrdered($olympixId);

        $gameInfo = [];
        foreach ($allGames as $game) {
            $gameInfo[] = [
                'id' => $game->getId(),
                'name' => $game->getName(),
                'status' => $game->getStatus(),
                'order' => $game->getOrderPosition()
            ];
        }

        return new JsonResponse([
            'player' => [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'joker_double_available' => $player->hasJokerDoubleAvailable(),
                'joker_swap_available' => $player->hasJokerSwapAvailable(),
            ],
            'olympix' => [
                'id' => $olympix->getId(),
                'name' => $olympix->getName(),
            ],
            'pending_games_count' => count($pendingGames),
            'total_games_count' => count($allGames),
            'all_games' => $gameInfo,
            'can_use_double_joker_calculated' => $player->hasJokerDoubleAvailable() && count($pendingGames) > 0,
            'can_use_swap_joker_calculated' => $player->hasJokerSwapAvailable() && count($pendingGames) > 0,
        ]);
    }

    // =============================================================================
    // API ROUTES - GENERISCHE ROUTEN ZULETZT (nur olympixId mit query params)
    // =============================================================================

    #[Route('/api/player/{olympixId}/active-split-or-steal', name: 'app_api_player_active_split_or_steal')]
    public function getActiveSplitOrSteal(int $olympixId, Request $request): JsonResponse
    {
        $playerId = $request->query->get('player_id');
        
        if (!$playerId) {
            return new JsonResponse(['error' => 'Player ID required'], 400);
        }

        $player = $this->playerRepository->find($playerId);
        if (!$player || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Player not found'], 404);
        }

        $activeMatch = $this->splitOrStealMatchRepository->findActiveMatchForPlayer($player);

        if (!$activeMatch) {
            return new JsonResponse(['active_match' => null]);
        }

        $otherPlayer = $activeMatch->getOtherPlayer($player);
        $playerChoice = $activeMatch->getPlayerChoice($player);

        return new JsonResponse([
            'active_match' => [
                'id' => $activeMatch->getId(),
                'points_at_stake' => $activeMatch->getPointsAtStake(),
                'opponent' => [
                    'id' => $otherPlayer->getId(),
                    'name' => $otherPlayer->getName(),
                ],
                'player_choice' => $playerChoice,
                'has_chosen' => $playerChoice !== null,
                'both_chosen' => $activeMatch->bothPlayersHaveChosen(),
                'is_completed' => $activeMatch->getIsCompleted(),
                'result_description' => $activeMatch->getResultDescription(),
                'game_status' => $activeMatch->getGame()->getStatus(),
            ]
        ]);
    }

    #[Route('/api/player/{olympixId}/status', name: 'app_api_player_status_by_param')]
    public function apiPlayerStatusByParam(int $olympixId, Request $request): JsonResponse
    {
        $playerId = $request->query->get('player_id');
        
        if (!$playerId) {
            return new JsonResponse(['error' => 'Player ID required'], 400);
        }

        $player = $this->playerRepository->find($playerId);
        if (!$player || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Player not found'], 404);
        }

        // Get all players for rankings
        $players = $this->playerRepository->findBy(['olympix' => $player->getOlympix()], ['totalPoints' => 'DESC']);
        
        $rankings = [];
        foreach ($players as $p) {
            $rankings[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'total_points' => $p->getTotalPoints(),
            ];
        }

        return new JsonResponse([
            'player' => [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'total_points' => $player->getTotalPoints(),
            ],
            'rankings' => $rankings,
            'timestamp' => time()
        ]);
    }
}