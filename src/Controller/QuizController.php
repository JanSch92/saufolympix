<?php

namespace App\Controller;

use App\Entity\QuizQuestion;
use App\Entity\QuizAnswer;
use App\Entity\GameResult;
use App\Repository\GameRepository;
use App\Repository\QuizQuestionRepository;
use App\Repository\QuizAnswerRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private QuizQuestionRepository $quizQuestionRepository,
        private QuizAnswerRepository $quizAnswerRepository,
        private PlayerRepository $playerRepository,
        private GameResultRepository $gameResultRepository
    ) {}

    #[Route('/quiz/questions/{gameId}', name: 'app_quiz_questions')]
    public function questions(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isQuizGame()) {
            $this->addFlash('error', 'Nur Quiz-Spiele können Fragen haben');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        if ($request->isMethod('POST')) {
            $question = $request->request->get('question');
            $correctAnswer = $request->request->get('correct_answer');

            if (empty($question) || empty($correctAnswer)) {
                $this->addFlash('error', 'Frage und korrekte Antwort sind erforderlich');
                return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
            }

            $quizQuestion = new QuizQuestion();
            $quizQuestion->setQuestion($question);
            $quizQuestion->setCorrectAnswer($correctAnswer);
            $quizQuestion->setGame($game);
            $quizQuestion->setOrderPosition($this->quizQuestionRepository->getNextOrderPosition($gameId));

            $this->entityManager->persist($quizQuestion);
            $this->entityManager->flush();

            $this->addFlash('success', 'Frage wurde hinzugefügt');
            return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
        }

        $questions = $this->quizQuestionRepository->findByGameOrdered($gameId);

        return $this->render('quiz/questions.html.twig', [
            'game' => $game,
            'questions' => $questions,
        ]);
    }

    #[Route('/quiz/question/delete/{id}', name: 'app_quiz_question_delete')]
    public function deleteQuestion(int $id): Response
    {
        $question = $this->quizQuestionRepository->find($id);

        if (!$question) {
            throw $this->createNotFoundException('Frage nicht gefunden');
        }

        $gameId = $question->getGame()->getId();

        // Check if question has answers
        if ($question->getQuizAnswers()->count() > 0) {
            $this->addFlash('error', 'Frage kann nicht gelöscht werden, da bereits Antworten vorhanden sind');
            return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
        }

        $this->entityManager->remove($question);
        $this->entityManager->flush();

        $this->addFlash('success', 'Frage wurde gelöscht');
        return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
    }

    #[Route('/quiz/{gameId}', name: 'app_quiz_qr')]
    public function showQR(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isQuizGame()) {
            $this->addFlash('error', 'Nur Quiz-Spiele können QR-Codes anzeigen');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Generate QR code URL
        $quizUrl = $this->generateUrl('app_quiz_mobile', ['gameId' => $gameId], true);

        return $this->render('quiz/qr_code.html.twig', [
            'game' => $game,
            'quiz_url' => $quizUrl,
        ]);
    }

    #[Route('/quiz/mobile/{gameId}', name: 'app_quiz_mobile')]
    public function mobile(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isQuizGame()) {
            throw $this->createNotFoundException('Nur Quiz-Spiele sind verfügbar');
        }

        $players = $game->getOlympix()->getPlayers();
        $questions = $this->quizQuestionRepository->findByGameOrdered($gameId);

        if ($request->isMethod('POST')) {
            $playerId = $request->request->get('player_id');
            
            if (!$playerId) {
                $this->addFlash('error', 'Bitte wähle deinen Namen aus');
                return $this->redirectToRoute('app_quiz_mobile', ['gameId' => $gameId]);
            }

            $player = $this->playerRepository->find($playerId);
            if (!$player) {
                $this->addFlash('error', 'Spieler nicht gefunden');
                return $this->redirectToRoute('app_quiz_mobile', ['gameId' => $gameId]);
            }

            // Save all answers
            $allAnswered = true;
            foreach ($questions as $question) {
                $answerValue = $request->request->get('answer_' . $question->getId());
                
                if (empty($answerValue)) {
                    $allAnswered = false;
                    continue;
                }

                // Check if answer already exists
                $existingAnswer = $this->quizAnswerRepository->findByPlayerAndQuestion($player->getId(), $question->getId());
                
                if ($existingAnswer) {
                    $existingAnswer->setAnswer($answerValue);
                } else {
                    $answer = new QuizAnswer();
                    $answer->setPlayer($player);
                    $answer->setQuizQuestion($question);
                    $answer->setAnswer($answerValue);
                    $this->entityManager->persist($answer);
                }
            }

            if ($allAnswered) {
                $this->entityManager->flush();
                
                // Check if all players have answered
                if ($this->allPlayersAnswered($game)) {
                    $this->calculateQuizResults($game);
                }

                return $this->render('quiz/success.html.twig', [
                    'game' => $game,
                    'player' => $player,
                ]);
            } else {
                $this->addFlash('error', 'Bitte beantworte alle Fragen');
            }
        }

        return $this->render('quiz/mobile.html.twig', [
            'game' => $game,
            'players' => $players,
            'questions' => $questions,
        ]);
    }

    #[Route('/quiz/results/{gameId}', name: 'app_quiz_results')]
    public function results(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isQuizGame()) {
            throw $this->createNotFoundException('Nur Quiz-Spiele haben Ergebnisse');
        }

        $questions = $this->quizQuestionRepository->findByGameOrdered($gameId);
        $results = $this->gameResultRepository->findByGameOrderedByPosition($gameId);

        return $this->render('quiz/results.html.twig', [
            'game' => $game,
            'questions' => $questions,
            'results' => $results,
        ]);
    }

    #[Route('/quiz/calculate/{gameId}', name: 'app_quiz_calculate')]
    public function calculate(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isQuizGame()) {
            $this->addFlash('error', 'Nur Quiz-Spiele können berechnet werden');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        $this->calculateQuizResults($game);

        $this->addFlash('success', 'Quiz-Ergebnisse wurden berechnet');
        return $this->redirectToRoute('app_quiz_results', ['gameId' => $gameId]);
    }

    #[Route('/api/quiz/{gameId}/status', name: 'app_api_quiz_status')]
    public function apiQuizStatus(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            return $this->json(['error' => 'Spiel nicht gefunden'], 404);
        }

        $questions = $this->quizQuestionRepository->findByGameOrdered($gameId);
        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $answeredPlayers = [];

        foreach ($questions as $question) {
            $answers = $this->quizAnswerRepository->findByQuestion($question->getId());
            $answeredPlayers[$question->getId()] = count($answers);
        }

        return $this->json([
            'game_id' => $game->getId(),
            'total_players' => $totalPlayers,
            'questions' => count($questions),
            'answered_players' => $answeredPlayers,
            'all_answered' => $this->allPlayersAnswered($game),
        ]);
    }

    private function allPlayersAnswered(Game $game): bool
    {
        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $questions = $this->quizQuestionRepository->findByGameOrdered($game->getId());

        foreach ($questions as $question) {
            $answers = $this->quizAnswerRepository->findByQuestion($question->getId());
            if (count($answers) < $totalPlayers) {
                return false;
            }
        }

        return true;
    }

    private function calculateQuizResults(Game $game): void
    {
        $questions = $this->quizQuestionRepository->findByGameOrdered($game->getId());
        $playerTotalPoints = [];

        // Calculate scores for each question
        foreach ($questions as $question) {
            $question->calculateScores();
            
            // Add points to player totals
            foreach ($question->getQuizAnswers() as $answer) {
                $playerId = $answer->getPlayer()->getId();
                if (!isset($playerTotalPoints[$playerId])) {
                    $playerTotalPoints[$playerId] = 0;
                }
                $playerTotalPoints[$playerId] += $answer->getPointsEarned();
            }
        }

        // Sort players by total points (descending)
        arsort($playerTotalPoints);

        // Clear existing game results
        foreach ($game->getGameResults() as $result) {
            $this->entityManager->remove($result);
        }

        // Create new game results
        $position = 1;
        foreach ($playerTotalPoints as $playerId => $totalPoints) {
            $player = $this->playerRepository->find($playerId);
            
            if ($player) {
                $result = new GameResult();
                $result->setGame($game);
                $result->setPlayer($player);
                $result->setPosition($position);
                $result->setPoints($totalPoints);
                
                $this->entityManager->persist($result);
                $position++;
            }
        }

        $this->entityManager->flush();
    }
}