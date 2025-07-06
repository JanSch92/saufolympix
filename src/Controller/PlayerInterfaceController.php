<?php

namespace App\Controller;

use App\Entity\Joker;
use App\Repository\OlympixRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use App\Repository\JokerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlayerInterfaceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private PlayerRepository $playerRepository,
        private GameRepository $gameRepository,
        private JokerRepository $jokerRepository
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

        // Aktuelle Rangliste
        $players = $this->playerRepository->findByOlympixOrderedByPoints($olympixId);
        
        // Aktuelles Spiel
        $currentGame = $this->gameRepository->findActiveGameByOlympix($olympixId);
        
        // Nächstes Spiel
        $nextGame = $this->gameRepository->findNextPendingGame($olympixId);

        // Joker-Status
        $canUseDoubleJoker = $player->hasJokerDoubleAvailable() && $currentGame && $currentGame->getStatus() === 'active';
$canUseSwapJoker = $player->hasJokerSwapAvailable() && $currentGame && $currentGame->getStatus() === 'active'; // GEÄNDERT: auch VOR dem Spiel

        return $this->render('player_interface/dashboard.html.twig', [
            'olympix' => $olympix,
            'player' => $player,
            'players' => $players,
            'current_game' => $currentGame,
            'next_game' => $nextGame,
            'can_use_double_joker' => $canUseDoubleJoker,
            'can_use_swap_joker' => $canUseSwapJoker,
        ]);
    }

    #[Route('/player-joker-double/{olympixId}/{playerId}', name: 'app_player_joker_double')]
    public function useDoubleJoker(int $olympixId, int $playerId): Response
    {
        $player = $this->playerRepository->find($playerId);
        $currentGame = $this->gameRepository->findActiveGameByOlympix($olympixId);

        if (!$player || !$currentGame || !$player->hasJokerDoubleAvailable()) {
            $this->addFlash('error', 'Joker kann nicht verwendet werden');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        // Joker aktivieren
        $joker = new Joker();
        $joker->setPlayer($player);
        $joker->setGame($currentGame);
        $joker->setJokerType('double');
        $joker->use();

        $player->setJokerDoubleUsed(true);

        $this->entityManager->persist($joker);
        $this->entityManager->flush();

        $this->addFlash('success', 'Doppelte Punkte Joker aktiviert! 🔥');
        return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
    }

    #[Route('/player-joker-swap/{olympixId}/{playerId}', name: 'app_player_joker_swap')]
    public function useSwapJoker(int $olympixId, int $playerId, Request $request): Response
    {
        $player = $this->playerRepository->find($playerId);
        $lastCompletedGame = $this->gameRepository->findBy(['olympix' => $olympixId, 'status' => 'completed'], ['orderPosition' => 'DESC'], 1)[0] ?? null;

        if (!$player || !$lastCompletedGame || !$player->hasJokerSwapAvailable()) {
            $this->addFlash('error', 'Joker kann nicht verwendet werden');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        if ($request->isMethod('POST')) {
            $targetPlayerId = $request->request->get('target_player_id');
            $targetPlayer = $this->playerRepository->find($targetPlayerId);

            if (!$targetPlayer || $targetPlayer->getId() === $player->getId()) {
                $this->addFlash('error', 'Ungültiger Zielspieler');
                return $this->redirectToRoute('app_player_joker_swap', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // Prüfen ob bereits blockiert
            if ($this->jokerRepository->hasSwapJokerOnPlayer($targetPlayer->getId(), $lastCompletedGame->getId())) {
                $this->addFlash('error', 'Tausch mit diesem Spieler ist bereits blockiert');
                return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
            }

            // Joker aktivieren
            $joker = new Joker();
            $joker->setPlayer($player);
            $joker->setGame($lastCompletedGame);
            $joker->setTargetPlayer($targetPlayer);
            $joker->setJokerType('swap');
            $joker->use();

            $player->setJokerSwapUsed(true);

            $this->entityManager->persist($joker);
            $this->entityManager->flush();

            $this->addFlash('success', 'Punkte-Tausch aktiviert! ⇄');
            return $this->redirectToRoute('app_player_dashboard', ['olympixId' => $olympixId, 'playerId' => $playerId]);
        }

        $availablePlayers = $this->playerRepository->findBy(['olympix' => $olympixId]);
        $availablePlayers = array_filter($availablePlayers, fn($p) => $p->getId() !== $player->getId());

        return $this->render('player_interface/swap_joker.html.twig', [
            'olympix' => $player->getOlympix(),
            'player' => $player,
            'available_players' => $availablePlayers,
        ]);
    }

    #[Route('/api/player/{olympixId}/{playerId}/status', name: 'app_api_player_status')]
    public function apiPlayerStatus(int $olympixId, int $playerId): Response
    {
        $player = $this->playerRepository->find($playerId);
        
        if (!$player) {
            return $this->json(['error' => 'Spieler nicht gefunden'], 404);
        }

        $players = $this->playerRepository->findByOlympixOrderedByPoints($olympixId);
        $currentGame = $this->gameRepository->findActiveGameByOlympix($olympixId);
        
        // Spieler-Position in Rangliste finden
        $position = 1;
        foreach ($players as $p) {
            if ($p->getId() === $player->getId()) {
                break;
            }
            $position++;
        }

        return $this->json([
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
                'type' => $currentGame->getGameType(),
                'status' => $currentGame->getStatus(),
            ] : null,
            'ranking' => array_map(fn($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'total_points' => $p->getTotalPoints(),
            ], $players),
        ]);
    }
}