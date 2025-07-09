<?php

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