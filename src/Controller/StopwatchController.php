<?php

namespace App\Controller;

use App\Entity\StopwatchAttempt;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\StopwatchAttemptRepository;
use App\Service\StopwatchEvaluationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StopwatchController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private StopwatchAttemptRepository $stopwatchAttemptRepository,
        private StopwatchEvaluationService $stopwatchEvaluationService,
    ) {}

    #[Route('/stopwatch/manage/{gameId}', name: 'app_stopwatch_manage')]
    public function manage(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isStopwatchGame()) {
            throw $this->createNotFoundException('Stoppuhr-Spiel nicht gefunden');
        }

        $attempts = $this->stopwatchAttemptRepository->findByGame($gameId);
        $players = $game->getOlympix()->getPlayers();

        $submittedPlayerIds = array_map(
            fn (StopwatchAttempt $attempt) => $attempt->getPlayer()->getId(),
            $attempts
        );

        return $this->render('stopwatch/manage.html.twig', [
            'game' => $game,
            'attempts' => $attempts,
            'players' => $players,
            'submitted_player_ids' => $submittedPlayerIds,
            'all_submitted' => count($attempts) >= $players->count(),
        ]);
    }

    #[Route('/stopwatch/mobile/{gameId}', name: 'app_stopwatch_mobile')]
    public function mobile(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isStopwatchGame()) {
            throw $this->createNotFoundException('Stoppuhr-Spiel nicht gefunden');
        }

        $players = $game->getOlympix()->getPlayers();

        $attempts = $this->stopwatchAttemptRepository->findByGame($gameId);
        $submittedPlayerIds = array_map(
            fn (StopwatchAttempt $attempt) => $attempt->getPlayer()->getId(),
            $attempts
        );

        // Vorausgewählter Spieler (Dashboard-Auto-Join, ?player=ID):
        // Namensauswahl wird übersprungen, sofern noch nicht abgegeben
        $preselectedPlayer = null;
        $preselectedId = $request->query->getInt('player');
        if ($preselectedId > 0 && !in_array($preselectedId, $submittedPlayerIds, true)) {
            foreach ($players as $p) {
                if ($p->getId() === $preselectedId) {
                    $preselectedPlayer = $p;
                    break;
                }
            }
        }

        return $this->render('stopwatch/mobile.html.twig', [
            'game' => $game,
            'players' => $players,
            'submitted_player_ids' => $submittedPlayerIds,
            'preselected_player' => $preselectedPlayer,
        ]);
    }

    #[Route('/stopwatch/submit/{gameId}', name: 'app_stopwatch_submit', methods: ['POST'])]
    public function submit(int $gameId, Request $request): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isStopwatchGame()) {
            return $this->json(['success' => false, 'error' => 'Stoppuhr-Spiel nicht gefunden'], 404);
        }

        if (!$game->isActive()) {
            return $this->json(['success' => false, 'error' => 'Das Spiel ist nicht aktiv'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $playerId = $data['player_id'] ?? null;
        $elapsedSeconds = $data['elapsed_seconds'] ?? null;

        if (!$playerId || $elapsedSeconds === null || !is_numeric($elapsedSeconds)) {
            return $this->json(['success' => false, 'error' => 'Ungültige Daten'], 400);
        }

        $elapsed = (float) $elapsedSeconds;
        if ($elapsed <= 0 || $elapsed > 600) {
            return $this->json(['success' => false, 'error' => 'Unplausible Zeit'], 400);
        }

        $player = $this->playerRepository->find($playerId);
        if (!$player || $player->getOlympix()->getId() !== $game->getOlympix()->getId()) {
            return $this->json(['success' => false, 'error' => 'Spieler nicht gefunden'], 404);
        }

        $existing = $this->stopwatchAttemptRepository->findByPlayerAndGame($player->getId(), $gameId);
        if ($existing) {
            return $this->json(['success' => false, 'error' => 'Du hast bereits eine Zeit abgegeben'], 409);
        }

        $attempt = new StopwatchAttempt();
        $attempt->setGame($game);
        $attempt->setPlayer($player);
        $attempt->setElapsedSeconds(number_format($elapsed, 2, '.', ''));

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $submittedCount = $this->stopwatchAttemptRepository->countByGame($gameId);

        $allSubmitted = $submittedCount >= $totalPlayers;

        // Automatische Auswertung, sobald alle Spieler abgegeben haben
        if ($allSubmitted && !$game->isCompleted()) {
            $this->stopwatchEvaluationService->evaluate($game);
        }

        return $this->json([
            'success' => true,
            'deviation' => $attempt->getDeviation(),
            'target' => $game->getStopwatchTarget(),
            'elapsed' => $attempt->getElapsedSeconds(),
            'submitted_count' => $submittedCount,
            'total_players' => $totalPlayers,
            'all_submitted' => $allSubmitted,
            'game_completed' => $game->isCompleted(),
        ]);
    }

    #[Route('/stopwatch/evaluate/{gameId}', name: 'app_stopwatch_evaluate')]
    public function evaluate(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isStopwatchGame()) {
            throw $this->createNotFoundException('Stoppuhr-Spiel nicht gefunden');
        }

        if ($game->isCompleted()) {
            return $this->redirectToRoute('app_stopwatch_results', ['gameId' => $gameId]);
        }

        if ($game->getStopwatchAttempts()->count() === 0) {
            $this->addFlash('error', 'Noch keine Zeiten abgegeben — Auswertung nicht möglich');
            return $this->redirectToRoute('app_stopwatch_manage', ['gameId' => $gameId]);
        }

        $messages = $this->stopwatchEvaluationService->evaluate($game);

        foreach ($messages as $message) {
            $this->addFlash($message['type'], $message['message']);
        }

        $this->addFlash('success', 'Stoppuhr-Spiel wurde ausgewertet und abgeschlossen');

        return $this->redirectToRoute('app_stopwatch_results', ['gameId' => $gameId]);
    }

    #[Route('/stopwatch/results/{gameId}', name: 'app_stopwatch_results')]
    public function results(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isStopwatchGame()) {
            throw $this->createNotFoundException('Stoppuhr-Spiel nicht gefunden');
        }

        $attempts = $this->stopwatchAttemptRepository->findByGame($gameId);
        $target = (float) $game->getStopwatchTarget();
        $ranked = $this->stopwatchEvaluationService->rankAttempts($attempts, $target);

        return $this->render('stopwatch/results.html.twig', [
            'game' => $game,
            'ranked_attempts' => $ranked,
        ]);
    }

    #[Route('/api/stopwatch/{gameId}/status', name: 'app_api_stopwatch_status')]
    public function apiStatus(int $gameId): JsonResponse
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isStopwatchGame()) {
            return $this->json(['error' => 'Stoppuhr-Spiel nicht gefunden'], 404);
        }

        $attempts = $this->stopwatchAttemptRepository->findByGame($gameId);
        $totalPlayers = $game->getOlympix()->getPlayers()->count();

        $submitted = array_map(fn (StopwatchAttempt $attempt) => [
            'player_id' => $attempt->getPlayer()->getId(),
            'player_name' => $attempt->getPlayer()->getName(),
        ], $attempts);

        return $this->json([
            'game_id' => $game->getId(),
            'game_status' => $game->getStatus(),
            'target' => $game->getStopwatchTarget(),
            'total_players' => $totalPlayers,
            'submitted_count' => count($attempts),
            'submitted' => $submitted,
            'all_submitted' => count($attempts) >= $totalPlayers,
            'is_completed' => $game->isCompleted(),
        ]);
    }
}
