<?php
// src/Controller/ApiController.php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
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
}