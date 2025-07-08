<?php

namespace App\Controller;

use App\Entity\Joker;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\JokerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JokerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private JokerRepository $jokerRepository
    ) {}

    #[Route('/joker/double/{playerId}/{gameId}', name: 'app_joker_double')]
    public function useDoubleJoker(int $playerId, int $gameId): Response
    {
        $player = $this->playerRepository->find($playerId);
        $game = $this->gameRepository->find($gameId);

        if (!$player || !$game) {
            throw $this->createNotFoundException('Spieler oder Spiel nicht gefunden');
        }

        // Check if player still has double joker available (global for olympix)
        if (!$player->hasJokerDoubleAvailable()) {
            $this->addFlash('error', 'Doppelte Punkte Joker bereits verwendet');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Check if game is active
        if ($game->getStatus() !== 'active') {
            $this->addFlash('error', 'Joker kann nur bei aktiven Spielen verwendet werden');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Check if player already has double joker for THIS game
        $existingDoubleJoker = $this->jokerRepository->findOneBy([
            'player' => $player,
            'game' => $game,
            'jokerType' => 'double',
            'isUsed' => true
        ]);

        if ($existingDoubleJoker) {
            $this->addFlash('error', 'Doppelte Punkte Joker bereits für dieses Spiel verwendet');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Create joker record for this specific game
        $joker = new Joker();
        $joker->setPlayer($player);
        $joker->setGame($game);
        $joker->setJokerType('double');
        $joker->use();

        // Mark global joker as used
        $player->setJokerDoubleUsed(true);

        $this->entityManager->persist($joker);
        $this->entityManager->flush();

        $this->addFlash('success', 'Doppelte Punkte Joker für "' . $player->getName() . '" wurde für dieses Spiel aktiviert');
        return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
    }

    #[Route('/joker/swap/{playerId}/{gameId}', name: 'app_joker_swap')]
    public function useSwapJoker(int $playerId, int $gameId, Request $request): Response
    {
        $player = $this->playerRepository->find($playerId);
        $game = $this->gameRepository->find($gameId);

        if (!$player || !$game) {
            throw $this->createNotFoundException('Spieler oder Spiel nicht gefunden');
        }

        $olympix = $game->getOlympix();

        // Check if player still has swap joker available (global for olympix)
        if (!$player->hasJokerSwapAvailable()) {
            $this->addFlash('error', 'Punkte tauschen Joker bereits verwendet');
            return $this->redirectToRoute('app_game_admin', ['id' => $olympix->getId()]);
        }

        // Check if player already has swap joker for THIS game
        $existingSwapJoker = $this->jokerRepository->findOneBy([
            'player' => $player,
            'game' => $game,
            'jokerType' => 'swap',
            'isUsed' => true
        ]);

        if ($existingSwapJoker) {
            $this->addFlash('error', 'Punkte tauschen Joker bereits für dieses Spiel verwendet');
            return $this->redirectToRoute('app_game_admin', ['id' => $olympix->getId()]);
        }

        if ($request->isMethod('POST')) {
            $targetPlayerId = $request->request->get('target_player_id');
            
            if (!$targetPlayerId) {
                $this->addFlash('error', 'Bitte wähle einen Zielspieler aus');
                return $this->redirectToRoute('app_joker_swap', ['playerId' => $playerId, 'gameId' => $gameId]);
            }

            $targetPlayer = $this->playerRepository->find($targetPlayerId);
            if (!$targetPlayer) {
                $this->addFlash('error', 'Zielspieler nicht gefunden');
                return $this->redirectToRoute('app_joker_swap', ['playerId' => $playerId, 'gameId' => $gameId]);
            }

            if ($targetPlayer->getId() === $player->getId()) {
                $this->addFlash('error', 'Du kannst nicht mit dir selbst tauschen');
                return $this->redirectToRoute('app_joker_swap', ['playerId' => $playerId, 'gameId' => $gameId]);
            }

            // Check if target player is already blocked for THIS game
            if ($this->jokerRepository->hasSwapJokerOnPlayer($targetPlayer->getId(), $gameId)) {
                $this->addFlash('error', 'Tausch mit "' . $targetPlayer->getName() . '" ist bereits blockiert für dieses Spiel');
                return $this->redirectToRoute('app_game_admin', ['id' => $olympix->getId()]);
            }

            // Create joker record for this specific game
            $joker = new Joker();
            $joker->setPlayer($player);
            $joker->setGame($game);
            $joker->setTargetPlayer($targetPlayer);
            $joker->setJokerType('swap');
            $joker->use();

            // Mark global joker as used
            $player->setJokerSwapUsed(true);

            $this->entityManager->persist($joker);
            $this->entityManager->flush();

            $this->addFlash('success', 'Punkte tauschen Joker wurde für dieses Spiel aktiviert: "' . $player->getName() . '" <-> "' . $targetPlayer->getName() . '"');
            return $this->redirectToRoute('app_game_admin', ['id' => $olympix->getId()]);
        }

        // Get available players (excluding the current player)
        $availablePlayers = [];
        foreach ($olympix->getPlayers() as $p) {
            if ($p->getId() !== $player->getId()) {
                $availablePlayers[] = $p;
            }
        }

        // Get the last completed game for this olympix
        $lastCompletedGame = $this->gameRepository->findLastCompletedGameForOlympix($olympix->getId());

        return $this->render('joker/swap.html.twig', [
            'player' => $player,
            'game' => $game,
            'olympix' => $olympix,
            'available_players' => $availablePlayers,
            'last_completed_game' => $lastCompletedGame,
        ]);
    }

    #[Route('/joker/manage/{gameId}', name: 'app_joker_manage')]
    public function manage(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        $activeJokers = $this->jokerRepository->findActiveJokersByGame($gameId);
        $doubleJokers = $this->jokerRepository->findDoubleJokersByGame($gameId);
        $swapJokers = $this->jokerRepository->findSwapJokersByGame($gameId);

        return $this->render('joker/manage.html.twig', [
            'game' => $game,
            'active_jokers' => $activeJokers,
            'double_jokers' => $doubleJokers,
            'swap_jokers' => $swapJokers,
        ]);
    }

    #[Route('/joker/cancel/{id}', name: 'app_joker_cancel')]
    public function cancel(int $id): Response
    {
        $joker = $this->jokerRepository->find($id);

        if (!$joker) {
            throw $this->createNotFoundException('Joker nicht gefunden');
        }

        $player = $joker->getPlayer();
        $gameId = $joker->getGame()->getId();

        // Reset player joker status (global)
        if ($joker->isDoubleJoker()) {
            $player->setJokerDoubleUsed(false);
        } elseif ($joker->isSwapJoker()) {
            $player->setJokerSwapUsed(false);
        }

        $this->entityManager->remove($joker);
        $this->entityManager->flush();

        $this->addFlash('success', 'Joker wurde storniert');
        return $this->redirectToRoute('app_joker_manage', ['gameId' => $gameId]);
    }

    #[Route('/api/joker/available/{playerId}/{gameId}', name: 'app_api_joker_available')]
    public function apiJokerAvailable(int $playerId, int $gameId): Response
    {
        $player = $this->playerRepository->find($playerId);
        $game = $this->gameRepository->find($gameId);

        if (!$player || !$game) {
            return $this->json(['error' => 'Spieler oder Spiel nicht gefunden'], 404);
        }

        // Check if jokers are already used for this specific game
        $hasDoubleJokerForGame = $this->jokerRepository->findOneBy([
            'player' => $player,
            'game' => $game,
            'jokerType' => 'double',
            'isUsed' => true
        ]) !== null;

        $hasSwapJokerForGame = $this->jokerRepository->findOneBy([
            'player' => $player,
            'game' => $game,
            'jokerType' => 'swap',
            'isUsed' => true
        ]) !== null;

        return $this->json([
            'player_id' => $player->getId(),
            'game_id' => $game->getId(),
            'joker_double_available' => $player->hasJokerDoubleAvailable() && !$hasDoubleJokerForGame,
            'joker_swap_available' => $player->hasJokerSwapAvailable() && !$hasSwapJokerForGame,
            'can_use_jokers' => $game->getStatus() === 'active',
            'joker_double_used_global' => !$player->hasJokerDoubleAvailable(),
            'joker_swap_used_global' => !$player->hasJokerSwapAvailable(),
            'joker_double_used_for_game' => $hasDoubleJokerForGame,
            'joker_swap_used_for_game' => $hasSwapJokerForGame
        ]);
    }

    #[Route('/api/joker/swap/blocked/{playerId}/{gameId}', name: 'app_api_joker_swap_blocked')]
    public function apiSwapBlocked(int $playerId, int $gameId): Response
    {
        $player = $this->playerRepository->find($playerId);
        $game = $this->gameRepository->find($gameId);

        if (!$player || !$game) {
            return $this->json(['error' => 'Spieler oder Spiel nicht gefunden'], 404);
        }

        $isBlocked = $this->jokerRepository->hasSwapJokerOnPlayer($playerId, $gameId);
        $swapJokers = $this->jokerRepository->findBy([
            'targetPlayer' => $player,
            'game' => $game,
            'jokerType' => 'swap',
            'isUsed' => true
        ]);

        $blockingPlayers = [];
        foreach ($swapJokers as $joker) {
            $blockingPlayers[] = [
                'id' => $joker->getPlayer()->getId(),
                'name' => $joker->getPlayer()->getName()
            ];
        }

        return $this->json([
            'player_id' => $player->getId(),
            'game_id' => $game->getId(),
            'is_blocked' => $isBlocked,
            'blocking_players' => $blockingPlayers,
            'message' => $isBlocked ? 'Tausch mit diesem Spieler ist blockiert für dieses Spiel' : 'Tausch möglich'
        ]);
    }

    #[Route('/api/joker/stats/{gameId}', name: 'app_api_joker_stats')]
    public function apiJokerStats(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            return $this->json(['error' => 'Spiel nicht gefunden'], 404);
        }

        $doubleJokers = $this->jokerRepository->findDoubleJokersByGame($gameId);
        $swapJokers = $this->jokerRepository->findSwapJokersByGame($gameId);

        $doubleJokerPlayers = [];
        foreach ($doubleJokers as $joker) {
            $doubleJokerPlayers[] = [
                'id' => $joker->getPlayer()->getId(),
                'name' => $joker->getPlayer()->getName()
            ];
        }

        $swapJokerPairs = [];
        foreach ($swapJokers as $joker) {
            $swapJokerPairs[] = [
                'source' => [
                    'id' => $joker->getPlayer()->getId(),
                    'name' => $joker->getPlayer()->getName()
                ],
                'target' => [
                    'id' => $joker->getTargetPlayer()->getId(),
                    'name' => $joker->getTargetPlayer()->getName()
                ]
            ];
        }

        return $this->json([
            'game_id' => $game->getId(),
            'game_name' => $game->getName(),
            'double_jokers' => $doubleJokerPlayers,
            'swap_jokers' => $swapJokerPairs,
            'total_double_jokers' => count($doubleJokerPlayers),
            'total_swap_jokers' => count($swapJokerPairs)
        ]);
    }
}