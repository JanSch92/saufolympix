<?php

namespace App\Controller;

use App\Entity\Olympix;
use App\Repository\OlympixRepository;
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

        // Calculate current rankings
        $players = [];
        foreach ($olympix->getPlayers() as $player) {
            $players[] = [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'total_points' => $player->getTotalPoints(),
                'joker_double_available' => $player->hasJokerDoubleAvailable(),
                'joker_swap_available' => $player->hasJokerSwapAvailable(),
            ];
        }

        // Sort by points
        usort($players, function($a, $b) {
            return $b['total_points'] - $a['total_points'];
        });

        $currentGame = $olympix->getCurrentGame();
        $nextGame = $olympix->getNextGame();

        $gameData = null;
        if ($currentGame) {
            $gameData = [
                'id' => $currentGame->getId(),
                'name' => $currentGame->getName(),
                'type' => $currentGame->getGameType(),
                'status' => $currentGame->getStatus(),
            ];

            // Add tournament bracket data if it's a tournament game
            if ($currentGame->isTournamentGame() && $currentGame->getTournament()) {
                $gameData['tournament'] = [
                    'bracket_data' => $currentGame->getTournament()->getBracketData(),
                    'current_round' => $currentGame->getTournament()->getCurrentRound(),
                    'is_completed' => $currentGame->getTournament()->isIsCompleted(),
                ];
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

        return $this->json([
            'olympix' => [
                'id' => $olympix->getId(),
                'name' => $olympix->getName(),
            ],
            'players' => $players,
            'current_game' => $gameData,
            'next_game' => $nextGameData,
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
}