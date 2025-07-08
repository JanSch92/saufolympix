<?php

// ===== 1. PLAYER INTERFACE CONTROLLER =====
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

    #[Route('/player-dashboard/{olympixId}', name: 'app_player_dashboard_new')]
    public function playerDashboardNew(int $olympixId, Request $request): Response
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            throw $this->createNotFoundException('Olympix nicht gefunden');
        }

        // Alle Spieler für die Auswahl
        $players = $this->playerRepository->findBy(['olympix' => $olympix], ['totalPoints' => 'DESC']);
        
        // Aktuelles Spiel
        $currentGame = $this->gameRepository->findActiveGameForOlympix($olympixId);
        
        // Nächstes Spiel
        $nextGame = $this->gameRepository->findNextGameToPlay($olympixId);

        return $this->render('player_interface/dashboard_new.html.twig', [
            'olympix' => $olympix,
            'players' => $players,
            'current_game' => $currentGame,
            'next_game' => $nextGame,
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

            // Create joker record for this specific game
            $joker = new Joker();
            $joker->setPlayer($player);
            $joker->setGame($selectedGame);
            $joker->setJokerType('double');
            $joker->use();

            // VORMERKEN: Create joker record for this specific FUTURE game
            $joker->setIsUsed(false); // NOT used yet - will be applied when game is played!

            // Mark global joker as used
            $player->setJokerDoubleUsed(true);

            $this->entityManager->persist($joker);
            $this->entityManager->flush();

            $this->addFlash('success', 'Doppelte Punkte Joker vorgemerkt! 🔥 Wenn "' . $selectedGame->getName() . '" gespielt wird, erhältst du automatisch doppelte Punkte.');
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
                $this->addFlash('error', 'Für dieses Spiel wurde bereits ein Punkte-Tausch vorgemerkt. Pro Spiel ist nur ein Tausch möglich.');
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // Create swap joker
            $joker = new Joker();
            $joker->setPlayer($player);
            $joker->setTargetPlayer($targetPlayer);
            $joker->setGame($selectedGame);
            $joker->setJokerType('swap');
            $joker->use();

            // VORMERKEN: Create joker record for this specific FUTURE game
            $joker->setIsUsed(false); // NOT used yet - will be applied when game is played!

            // Mark global joker as used
            $player->setJokerSwapUsed(true);

            $this->entityManager->persist($joker);
            $this->entityManager->flush();

            $this->addFlash('success', 'Punkte-Tausch vorgemerkt! 🔄 Wenn "' . $selectedGame->getName() . '" gespielt wird, tauschst du automatisch deine Position mit ' . $targetPlayer->getName() . '.');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Get other players as potential swap targets
        $otherPlayers = $this->playerRepository->findBy(['olympix' => $olympix]);
        $otherPlayers = array_filter($otherPlayers, fn($p) => $p->getId() !== $player->getId());

        // Get available players (excluding current player)
        $availablePlayers = $this->playerRepository->findBy(['olympix' => $olympix]);
        $availablePlayers = array_filter($availablePlayers, function($p) use ($player) {
            return $p->getId() !== $player->getId();
        });

        // Filter games where swap is still possible (no swap reserved yet)
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

        // Spieler-Position in Rangliste finden
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

    #[Route('/api/player/{olympixId}/{playerId}/joker-status', name: 'app_api_player_joker_status')]
    public function apiPlayerJokerStatus(int $olympixId, int $playerId): JsonResponse
    {
        $player = $this->playerRepository->find($playerId);
        
        if (!$player || $player->getOlympix()->getId() !== $olympixId) {
            return new JsonResponse(['error' => 'Spieler nicht gefunden'], 404);
        }

        $currentGame = $this->gameRepository->findActiveGameForOlympix($olympixId);
        $lastCompletedGame = $this->gameRepository->findLastCompletedGameForOlympix($olympixId);
        $pendingGames = $this->gameRepository->findPendingGamesForOlympix($olympixId);

        return new JsonResponse([
            'player_id' => $player->getId(),
            'olympix_id' => $olympixId,
            'joker_double_available_global' => $player->hasJokerDoubleAvailable(),
            'joker_swap_available_global' => $player->hasJokerSwapAvailable(),
            'can_use_double_joker' => $player->hasJokerDoubleAvailable() && count($pendingGames) > 0,
            'can_use_swap_joker' => $player->hasJokerSwapAvailable() && count($pendingGames) > 0,
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
}

// ===== 2. API CONTROLLER =====
namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\SplitOrStealMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private SplitOrStealMatchRepository $splitOrStealMatchRepository
    ) {}

    #[Route('/api/games/update-order', name: 'api_games_update_order', methods: ['POST'])]
    public function updateGamesOrder(
        Request $request,
        GameRepository $gameRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
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
                
                $game = $gameRepository->find($gameData['id']);
                if ($game && $game->getOlympix()->getId() == $olympixId) {
                    $game->setOrderPosition($gameData['order']);
                    $entityManager->persist($game);
                }
            }
            
            $entityManager->flush();
            
            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/player/{olympixId}/status', name: 'api_player_status')]
    public function getPlayerStatus(int $olympixId, Request $request): JsonResponse
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

    #[Route('/api/split-or-steal/{gameId}/status', name: 'api_split_or_steal_status')]
    public function getSplitOrStealStatus(int $gameId): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);
        
        if (!$game || !$game->isSplitOrStealGame()) {
            return new JsonResponse(['error' => 'Game not found'], 404);
        }

        $matches = $this->splitOrStealMatchRepository->findByGameOrderedByCreated($gameId);
        
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

        // Calculate stats
        $totalMatches = count($matches);
        $completedMatches = count(array_filter($matches, fn($m) => $m->getIsCompleted()));
        $pendingChoices = 0;
        
        foreach ($matches as $match) {
            if (!$match->getIsCompleted()) {
                if (!$match->getPlayer1Choice()) $pendingChoices++;
                if (!$match->getPlayer2Choice()) $pendingChoices++;
            }
        }

        return new JsonResponse([
            'game' => [
                'id' => $game->getId(),
                'name' => $game->getName(),
                'status' => $game->getStatus(),
            ],
            'matches' => $matchesData,
            'stats' => [
                'total_matches' => $totalMatches,
                'completed_matches' => $completedMatches,
                'pending_choices' => $pendingChoices,
                'can_evaluate' => $pendingChoices === 0 && $totalMatches > 0,
            ],
            'timestamp' => time()
        ]);
    }
}

// ===== 3. GAME CONTROLLER =====
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
            $this->addFlash('error', 'Spiel kann nicht gestartet werden');
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

        $game->setStatus('active');
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
            $this->addFlash('error', 'Spiel kann nicht abgeschlossen werden');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Special handling for Split or Steal games
        if ($game->isSplitOrStealGame()) {
            return $this->redirectToRoute('app_split_or_steal_evaluate', ['gameId' => $game->getId()]);
        }

        $game->setStatus('completed');
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spiel "' . $game->getName() . '" wurde abgeschlossen!');

        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
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
            $results = $request->request->get('results', []);
            
            if (empty($results)) {
                $this->addFlash('error', 'Keine Ergebnisse eingegeben');
                return $this->redirectToRoute('app_game_results', ['id' => $id]);
            }

            $this->processGameResults($game, $results);

            $this->addFlash('success', 'Ergebnisse für "' . $game->getName() . '" wurden gespeichert!');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        return $this->render('game/results.html.twig', [
            'game' => $game,
            'players' => $game->getOlympix()->getPlayers(),
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

            if ($gameType === 'tournament_team' && $teamSize) {
                $game->setTeamSize((int)$teamSize);
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

    private function processGameResults(Game $game, array $results): void
    {
        $pointsDistribution = $game->getDefaultPointsDistribution();
        $pendingDoubleJokers = $this->jokerRepository->findPendingDoubleJokersByGame($game->getId());
        $pendingSwapJokers = $this->jokerRepository->findPendingSwapJokersByGame($game->getId());

        // Create game results
        $gameResults = [];
        foreach ($results as $playerId => $position) {
            $player = $this->playerRepository->find($playerId);
            if (!$player) continue;

            $points = $pointsDistribution[$position - 1] ?? 0;

            $gameResult = new GameResult();
            $gameResult->setGame($game);
            $gameResult->setPlayer($player);
            $gameResult->setPosition($position);
            $gameResult->setPoints($points);

            $this->entityManager->persist($gameResult);
            $gameResults[$playerId] = $gameResult;
        }

        // Apply double jokers
        foreach ($pendingDoubleJokers as $doubleJoker) {
            $player = $doubleJoker->getPlayer();
            
            if (isset($gameResults[$player->getId()])) {
                $result = $gameResults[$player->getId()];
                $result->applyDoubleJoker();
                $this->entityManager->persist($result);

                $doubleJoker->setIsUsed(true);
                $doubleJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($doubleJoker);
            }
        }

        // Apply swap jokers
        foreach ($pendingSwapJokers as $swapJoker) {
            $sourcePlayer = $swapJoker->getPlayer();
            $targetPlayer = $swapJoker->getTargetPlayer();
            
            if (isset($gameResults[$sourcePlayer->getId()]) && isset($gameResults[$targetPlayer->getId()])) {
                $sourceResult = $gameResults[$sourcePlayer->getId()];
                $targetResult = $gameResults[$targetPlayer->getId()];
                
                $tempPosition = $sourceResult->getPosition();
                $tempPoints = $sourceResult->getPoints();

                $sourceResult->setPosition($targetResult->getPosition());
                $sourceResult->setPoints($targetResult->getPoints());
                $targetResult->setPosition($tempPosition);
                $targetResult->setPoints($tempPoints);

                $this->entityManager->persist($sourceResult);
                $this->entityManager->persist($targetResult);

                $swapJoker->setIsUsed(true);
                $swapJoker->setUsedAt(new \DateTime());
                $this->entityManager->persist($swapJoker);
            }
        }

        // Update player total points
        foreach ($gameResults as $result) {
            $player = $result->getPlayer();
            $player->setTotalPoints($player->getTotalPoints() + $result->getFinalPoints());
            $this->entityManager->persist($player);
        }

        $game->setStatus('completed');
        $this->entityManager->persist($game);
        $this->entityManager->flush();
    }
}

// ===== 4. GAME REPOSITORY =====
namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function save(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByOlympixOrdered(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextOrderPosition(int $olympixId): int
    {
        $maxPosition = $this->createQueryBuilder('g')
            ->select('MAX(g.orderPosition)')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxPosition ?? 0) + 1;
    }

    public function findActiveGameForOlympix(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextGameToPlay(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'pending')
            ->orderBy('g.orderPosition', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPendingGamesForOlympix(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'pending')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCompletedGamesForOlympix(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLastCompletedGameForOlympix(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->orderBy('g.orderPosition', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findSplitOrStealGames(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.splitOrStealMatches', 'som')
            ->addSelect('som')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.gameType = :gameType')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('gameType', 'split_or_steal')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getGameTypeDistribution(int $olympixId): array
    {
        $result = $this->createQueryBuilder('g')
            ->select('g.gameType, COUNT(g.id) as count')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->groupBy('g.gameType')
            ->getQuery()
            ->getResult();

        $distribution = [];
        foreach ($result as $row) {
            $distribution[$row['gameType']] = $row['count'];
        }

        return $distribution;
    }

    public function getGameStatistics(int $olympixId): array
    {
        $total = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_games' => $total,
            'completed_games' => $completed,
            'active_games' => $active,
            'pending_games' => $total - $completed - $active,
        ];
    }

    public function getOlympixStats(int $olympixId): array
    {
        $stats = $this->getGameStatistics($olympixId);
        $typeDistribution = $this->getGameTypeDistribution($olympixId);
        
        return array_merge($stats, [
            'tournament_team_games' => $typeDistribution['tournament_team'] ?? 0,
            'tournament_single_games' => $typeDistribution['tournament_single'] ?? 0,
            'free_for_all_games' => $typeDistribution['free_for_all'] ?? 0,
            'quiz_games' => $typeDistribution['quiz'] ?? 0,
            'split_or_steal_games' => $typeDistribution['split_or_steal'] ?? 0,
            'progress_percentage' => $stats['total_games'] > 0 ? round(($stats['completed_games'] / $stats['total_games']) * 100, 1) : 0
        ]);
    }

    public function reorderGames(int $olympixId, array $gameIds): void
    {
        $position = 1;
        foreach ($gameIds as $gameId) {
            $this->createQueryBuilder('g')
                ->update()
                ->set('g.orderPosition', ':position')
                ->where('g.id = :gameId')
                ->andWhere('g.olympix = :olympixId')
                ->setParameter('position', $position)
                ->setParameter('gameId', $gameId)
                ->setParameter('olympixId', $olympixId)
                ->getQuery()
                ->execute();
            $position++;
        }
    }

    public function getAverageGameDuration(int $olympixId): ?float
    {
        $games = $this->findCompletedGamesForOlympix($olympixId);
        if (empty($games)) {
            return null;
        }

        $totalDuration = 0;
        foreach ($games as $game) {
            $totalDuration += $game->getExpectedDuration();
        }

        return $totalDuration / count($games);
    }

    public function findGamesNeedingAttention(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.quizQuestions', 'qq')
            ->leftJoin('g.splitOrStealMatches', 'som')
            ->addSelect('qq', 'som')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->andWhere(
                '(g.gameType = :quizType AND SIZE(g.quizQuestions) < 3) OR ' .
                '(g.gameType IN (:tournamentTypes) AND g.tournament IS NULL) OR ' .
                '(g.gameType = :splitOrStealType AND SIZE(g.splitOrStealMatches) = 0)'
            )
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->setParameter('quizType', 'quiz')
            ->setParameter('tournamentTypes', ['tournament_team', 'tournament_single'])
            ->setParameter('splitOrStealType', 'split_or_steal')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }
}