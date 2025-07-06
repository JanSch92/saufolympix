<?php

namespace App\Controller;

use App\Entity\Player;
use App\Repository\OlympixRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlayerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private PlayerRepository $playerRepository
    ) {}

    #[Route('/player/manage/{olympixId}', name: 'app_player_manage')]
    public function manage(int $olympixId): Response
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            throw $this->createNotFoundException('Olympix nicht gefunden');
        }

        $players = $this->playerRepository->findByOlympixOrderedByPoints($olympixId);

        return $this->render('player/manage.html.twig', [
            'olympix' => $olympix,
            'players' => $players,
        ]);
    }

    #[Route('/player/create/{olympixId}', name: 'app_player_create')]
    public function create(int $olympixId, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            
            if (empty($name)) {
                $this->addFlash('error', 'Name ist erforderlich');
                return $this->redirectToRoute('app_player_manage', ['olympixId' => $olympixId]);
            }

            $olympix = $this->olympixRepository->find($olympixId);

            if (!$olympix) {
                throw $this->createNotFoundException('Olympix nicht gefunden');
            }

            // Check if player name already exists
            $existingPlayer = $this->playerRepository->findOneBy([
                'name' => $name,
                'olympix' => $olympix
            ]);

            if ($existingPlayer) {
                $this->addFlash('error', 'Spieler "' . $name . '" existiert bereits');
                return $this->redirectToRoute('app_player_manage', ['olympixId' => $olympixId]);
            }

            $player = new Player();
            $player->setName($name);
            $player->setOlympix($olympix);

            $this->entityManager->persist($player);
            $this->entityManager->flush();

            $this->addFlash('success', 'Spieler "' . $name . '" wurde hinzugefügt');
        }

        return $this->redirectToRoute('app_player_manage', ['olympixId' => $olympixId]);
    }

    #[Route('/player/edit/{id}', name: 'app_player_edit')]
    public function edit(int $id, Request $request): Response
    {
        $player = $this->playerRepository->find($id);

        if (!$player) {
            throw $this->createNotFoundException('Spieler nicht gefunden');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            
            if (empty($name)) {
                $this->addFlash('error', 'Name ist erforderlich');
                return $this->redirectToRoute('app_player_manage', ['olympixId' => $player->getOlympix()->getId()]);
            }

            // Check if player name already exists (excluding current player)
            $existingPlayer = $this->playerRepository->findOneBy([
                'name' => $name,
                'olympix' => $player->getOlympix()
            ]);

            if ($existingPlayer && $existingPlayer->getId() !== $player->getId()) {
                $this->addFlash('error', 'Spieler "' . $name . '" existiert bereits');
                return $this->redirectToRoute('app_player_manage', ['olympixId' => $player->getOlympix()->getId()]);
            }

            $player->setName($name);
            $this->entityManager->flush();

            $this->addFlash('success', 'Spieler wurde bearbeitet');
        }

        return $this->redirectToRoute('app_player_manage', ['olympixId' => $player->getOlympix()->getId()]);
    }

    #[Route('/player/delete/{id}', name: 'app_player_delete')]
    public function delete(int $id): Response
    {
        $player = $this->playerRepository->find($id);

        if (!$player) {
            throw $this->createNotFoundException('Spieler nicht gefunden');
        }

        $olympixId = $player->getOlympix()->getId();

        // Check if player has any game results
        if ($player->getGameResults()->count() > 0) {
            $this->addFlash('error', 'Spieler kann nicht gelöscht werden, da bereits Spielergebnisse vorhanden sind');
            return $this->redirectToRoute('app_player_manage', ['olympixId' => $olympixId]);
        }

        $playerName = $player->getName();
        $this->entityManager->remove($player);
        $this->entityManager->flush();

        $this->addFlash('success', 'Spieler "' . $playerName . '" wurde gelöscht');

        return $this->redirectToRoute('app_player_manage', ['olympixId' => $olympixId]);
    }

    #[Route('/player/reset-jokers/{id}', name: 'app_player_reset_jokers')]
    public function resetJokers(int $id): Response
    {
        $player = $this->playerRepository->find($id);

        if (!$player) {
            throw $this->createNotFoundException('Spieler nicht gefunden');
        }

        $player->setJokerDoubleUsed(false);
        $player->setJokerSwapUsed(false);

        $this->entityManager->flush();

        $this->addFlash('success', 'Joker für "' . $player->getName() . '" wurden zurückgesetzt');

        return $this->redirectToRoute('app_player_manage', ['olympixId' => $player->getOlympix()->getId()]);
    }

    #[Route('/player/reset-points/{id}', name: 'app_player_reset_points')]
    public function resetPoints(int $id): Response
    {
        $player = $this->playerRepository->find($id);

        if (!$player) {
            throw $this->createNotFoundException('Spieler nicht gefunden');
        }

        $player->setTotalPoints(0);
        $this->entityManager->flush();

        $this->addFlash('success', 'Punkte für "' . $player->getName() . '" wurden zurückgesetzt');

        return $this->redirectToRoute('app_player_manage', ['olympixId' => $player->getOlympix()->getId()]);
    }

    #[Route('/api/players/{olympixId}', name: 'app_api_players')]
    public function apiPlayers(int $olympixId): Response
    {
        $players = $this->playerRepository->findByOlympixOrderedByPoints($olympixId);

        $playerData = [];
        foreach ($players as $player) {
            $playerData[] = [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'total_points' => $player->getTotalPoints(),
                'joker_double_available' => $player->hasJokerDoubleAvailable(),
                'joker_swap_available' => $player->hasJokerSwapAvailable(),
            ];
        }

        return $this->json($playerData);
    }

    #[Route('/api/player/{id}/joker-status', name: 'app_api_player_joker_status')]
    public function apiPlayerJokerStatus(int $id): Response
    {
        $player = $this->playerRepository->find($id);

        if (!$player) {
            return $this->json(['error' => 'Spieler nicht gefunden'], 404);
        }

        return $this->json([
            'player_id' => $player->getId(),
            'name' => $player->getName(),
            'joker_double_available' => $player->hasJokerDoubleAvailable(),
            'joker_swap_available' => $player->hasJokerSwapAvailable(),
        ]);
    }
}