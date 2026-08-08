<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\QuizQuestion;
use App\Entity\QuizAnswer;
use App\Entity\GameResult;
use App\Repository\GameRepository;
use App\Repository\QuizQuestionRepository;
use App\Repository\QuizAnswerRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameResultRepository;
use App\Service\JokerApplicationService;
use App\Service\QuizQuestionGeneratorService;
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
        private GameResultRepository $gameResultRepository,
        private JokerApplicationService $jokerApplicationService,
        private QuizQuestionGeneratorService $quizQuestionGeneratorService
    ) {}

    #[Route('/quiz/generate/{gameId}', name: 'app_quiz_generate', methods: ['POST'])]
    public function generate(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            throw $this->createNotFoundException('Spiel nicht gefunden');
        }

        if (!$game->isQuizGame()) {
            $this->addFlash('error', 'Nur Quiz-Spiele können Fragen generieren');
            return $this->redirectToRoute('app_game_admin', ['id' => $game->getOlympix()->getId()]);
        }

        // Vorhandene Fragen nur löschen, wenn noch keine Antworten existieren
        foreach ($game->getQuizQuestions() as $question) {
            if ($question->getQuizAnswers()->count() > 0) {
                $this->addFlash('error', 'Fragen können nicht neu generiert werden, da bereits Antworten vorhanden sind');
                return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
            }
        }

        foreach ($game->getQuizQuestions() as $question) {
            $this->entityManager->remove($question);
        }
        $this->entityManager->flush();

        $generated = $this->quizQuestionGeneratorService->generateQuestions(10);

        $position = 1;
        foreach ($generated as $entry) {
            $question = new QuizQuestion();
            $question->setQuestion($entry['question']);
            $question->setCorrectAnswer($entry['answer']);
            $question->setGame($game);
            $question->setOrderPosition($position++);
            $this->entityManager->persist($question);
        }

        $this->entityManager->flush();

        $source = $this->quizQuestionGeneratorService->isOpenAiConfigured() ? 'ChatGPT' : 'Fragenpool';
        $this->addFlash('success', count($generated) . ' Fragen wurden automatisch generiert (' . $source . ')');

        return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
    }

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

            if (empty($question) || $correctAnswer === null || $correctAnswer === '') {
                $this->addFlash('error', 'Frage und korrekte Antwort sind erforderlich');
                return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
            }

            if (!$this->isWholeNumber($correctAnswer)) {
                $this->addFlash('error', 'Die korrekte Antwort muss eine ganze Zahl sein (keine Dezimalzahlen)');
                return $this->redirectToRoute('app_quiz_questions', ['gameId' => $gameId]);
            }

            if ((int) $correctAnswer < 0 || (int) $correctAnswer > \App\Service\QuizQuestionGeneratorService::MAX_ANSWER) {
                $this->addFlash('error', 'Die korrekte Antwort muss zwischen 0 und 99.999.999 liegen — formuliere die Frage ggf. mit Einheit um (z.B. "in Millionen")');
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

                // WICHTIG: "0" ist eine gültige Antwort — nur null/leer zählt als fehlend (empty('0') wäre true!)
                // Und: NUR ganze Zahlen im gültigen Bereich — niemals Dezimalzahlen.
                if ($answerValue === null || !$this->isValidAnswerNumber($answerValue)) {
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
                    
                    // AUTOMATICALLY COMPLETE THE GAME
                    $game->setStatus('completed');
                    
                    // Update player total points
                    foreach ($game->getOlympix()->getPlayers() as $p) {
                        $p->calculateTotalPoints();
                    }
                    
                    $this->entityManager->flush();
                }

                return $this->render('quiz/success.html.twig', [
                    'game' => $game,
                    'player' => $player,
                ]);
            } else {
                $this->addFlash('error', 'Bitte beantworte alle Fragen');
            }
        }

        // Vorausgewählter Spieler (kommt vom Dashboard-Auto-Join, ?player=ID):
        // Name muss dann nicht mehr ausgewählt werden
        $preselectedPlayer = null;
        $preselectedId = $request->query->getInt('player');
        if ($preselectedId > 0) {
            foreach ($players as $p) {
                if ($p->getId() === $preselectedId) {
                    $preselectedPlayer = $p;
                    break;
                }
            }
        }

        return $this->render('quiz/mobile.html.twig', [
            'game' => $game,
            'players' => $players,
            'questions' => $questions,
            'preselected_player' => $preselectedPlayer,
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

        // AUTOMATICALLY COMPLETE THE GAME AFTER CALCULATION
        $game->setStatus('completed');
        
        // Update player total points
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }
        
        $this->entityManager->flush();

        $this->addFlash('success', 'Quiz-Ergebnisse wurden berechnet und Spiel abgeschlossen');
        return $this->redirectToRoute('app_quiz_results', ['gameId' => $gameId]);
    }

    /**
     * Frage-für-Frage-Modus: liefert die aktuell offene Frage (erste Frage,
     * die noch nicht von allen Spielern beantwortet wurde).
     */
    #[Route('/api/quiz/{gameId}/current', name: 'app_api_quiz_current')]
    public function apiCurrentQuestion(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isQuizGame()) {
            return $this->json(['error' => 'Quiz nicht gefunden'], 404);
        }

        $questions = $this->quizQuestionRepository->findByGameOrdered($gameId);
        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $playerId = $request->query->getInt('player');

        // Eine Frage ist erst DURCH, wenn alle geantwortet UND alle die
        // Auswertung mit "Weiter" bestätigt haben (Barriere pro Frage).
        $current = null;
        $index = 0;
        $phase = 'question';
        foreach ($questions as $i => $question) {
            $answers = $this->quizAnswerRepository->findByQuestion($question->getId());

            if (count($answers) < $totalPlayers) {
                $current = $question;
                $index = $i;
                $phase = 'question';
                break;
            }

            $seen = count(array_filter($answers, fn ($a) => $a->isResultSeen()));
            if ($seen < $totalPlayers) {
                $current = $question;
                $index = $i;
                $phase = 'result';
                break;
            }
        }

        // Abschluss der WERTUNG bereits, sobald alle Antworten da sind — die
        // Weiter-Barriere der letzten Frage hält nur die Anzeige, nicht die Punkte
        if ($game->isActive() && count($questions) > 0 && $this->allPlayersAnswered($game)) {
            $this->calculateQuizResults($game);
            $game->setStatus('completed');
            foreach ($game->getOlympix()->getPlayers() as $p) {
                $p->calculateTotalPoints();
            }
            $this->entityManager->flush();
        }

        if (!$current) {
            $lastQuestion = count($questions) > 0 ? end($questions) : null;

            return $this->json([
                'quiz_completed' => true,
                'total' => count($questions),
                'last_question_id' => $lastQuestion?->getId(),
                'dashboard_url' => $playerId > 0
                    ? $this->generateUrl('app_player_dashboard', ['olympixId' => $game->getOlympix()->getId(), 'playerId' => $playerId])
                    : null,
            ]);
        }

        $answers = $this->quizAnswerRepository->findByQuestion($current->getId());
        $playerAnswered = false;
        $playerContinued = false;
        $continued = 0;
        foreach ($answers as $answer) {
            if ($answer->isResultSeen()) {
                $continued++;
            }
            if ($answer->getPlayer()->getId() === $playerId) {
                $playerAnswered = true;
                $playerContinued = $answer->isResultSeen();
            }
        }

        return $this->json([
            'quiz_completed' => false,
            'phase' => $phase,
            'question' => [
                'id' => $current->getId(),
                'text' => $phase === 'question' ? $current->getQuestion() : null,
                'index' => $index + 1,
                'total' => count($questions),
            ],
            'answered' => count($answers),
            'continued' => $continued,
            'total_players' => $totalPlayers,
            'player_answered' => $playerAnswered,
            'player_continued' => $playerContinued,
        ]);
    }

    /**
     * Frage-für-Frage-Modus: Spieler bestätigt die Auswertung ("Weiter").
     * Erst wenn ALLE Spieler bestätigt haben, wird die nächste Frage aktuell.
     */
    #[Route('/quiz/continue/{gameId}', name: 'app_quiz_continue', methods: ['POST'])]
    public function continueAfterResult(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isQuizGame()) {
            return $this->json(['success' => false, 'error' => 'Quiz nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $playerId = (int) ($data['player_id'] ?? 0);
        $questionId = (int) ($data['question_id'] ?? 0);

        if (!$playerId || !$questionId) {
            return $this->json(['success' => false, 'error' => 'Ungültige Daten'], 400);
        }

        $question = $this->quizQuestionRepository->find($questionId);
        if (!$question || $question->getGame()->getId() !== $gameId) {
            return $this->json(['success' => false, 'error' => 'Frage nicht gefunden'], 404);
        }

        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $answers = $this->quizAnswerRepository->findByQuestion($questionId);

        // Weiter geht erst, wenn die Frage komplett beantwortet ist
        if (count($answers) < $totalPlayers) {
            return $this->json(['success' => false, 'error' => 'Die Frage ist noch nicht komplett beantwortet'], 400);
        }

        $continued = 0;
        $found = false;
        foreach ($answers as $answer) {
            if ($answer->getPlayer()->getId() === $playerId) {
                $answer->setResultSeen(true); // idempotent
                $found = true;
            }
            if ($answer->isResultSeen()) {
                $continued++;
            }
        }

        if (!$found) {
            return $this->json(['success' => false, 'error' => 'Keine Antwort dieses Spielers vorhanden'], 404);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'continued' => $continued,
            'total_players' => $totalPlayers,
            'all_continued' => $continued >= $totalPlayers,
        ]);
    }

    /**
     * Frage-für-Frage-Modus: Auswertung einer fertig beantworteten Frage
     * (korrekte Antwort + Rangliste nach Nähe, Punkte wie beim Endergebnis).
     */
    #[Route('/api/quiz/question/{questionId}/result', name: 'app_api_quiz_question_result')]
    public function apiQuestionResult(int $questionId): Response
    {
        $question = $this->quizQuestionRepository->find($questionId);

        if (!$question) {
            return $this->json(['error' => 'Frage nicht gefunden'], 404);
        }

        $answers = $question->getQuizAnswers()->toArray();
        $correct = (float) $question->getCorrectAnswer();

        usort($answers, function ($a, $b) use ($correct) {
            return abs((float) $a->getAnswer() - $correct) <=> abs((float) $b->getAnswer() - $correct);
        });

        // Gleicher Abstand = gleicher Platz = gleiche Punkte (1-1-3),
        // identisch zur Wertung in QuizQuestion::calculateScores()
        $total = count($answers);
        $entries = [];
        $groupStartIndex = 0;
        $previousDistance = null;

        foreach ($answers as $i => $answer) {
            $distance = abs((float) $answer->getAnswer() - $correct);

            if ($previousDistance === null || abs($distance - $previousDistance) > 0.000001) {
                $groupStartIndex = $i;
                $previousDistance = $distance;
            }

            $entries[] = [
                'name' => $answer->getPlayer()->getName(),
                'player_id' => $answer->getPlayer()->getId(),
                'answer' => (float) $answer->getAnswer(),
                'points' => $total - $groupStartIndex,
                'position' => $groupStartIndex + 1,
            ];
        }

        return $this->json([
            'question' => $question->getQuestion(),
            'correct_answer' => (float) $question->getCorrectAnswer(),
            'entries' => $entries,
        ]);
    }

    /**
     * Frage-für-Frage-Modus: einzelne Antwort eines Spielers speichern.
     */
    #[Route('/quiz/answer/{gameId}', name: 'app_quiz_answer', methods: ['POST'])]
    public function submitAnswer(int $gameId, Request $request): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game || !$game->isQuizGame()) {
            return $this->json(['success' => false, 'error' => 'Quiz nicht gefunden'], 404);
        }

        if (!$game->isActive()) {
            return $this->json(['success' => false, 'error' => 'Das Quiz ist nicht aktiv'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $playerId = $data['player_id'] ?? null;
        $questionId = $data['question_id'] ?? null;
        $answerValue = $data['answer'] ?? null;

        if (!$playerId || !$questionId || $answerValue === null || $answerValue === '') {
            return $this->json(['success' => false, 'error' => 'Ungültige Daten'], 400);
        }

        if (!$this->isWholeNumber($answerValue)) {
            return $this->json(['success' => false, 'error' => 'Nur ganze Zahlen sind erlaubt'], 400);
        }

        if (!$this->isValidAnswerNumber($answerValue)) {
            return $this->json(['success' => false, 'error' => 'Zahl zu groß — maximal 99.999.999'], 400);
        }

        $player = $this->playerRepository->find($playerId);
        $question = $this->quizQuestionRepository->find($questionId);

        if (!$player || !$question
            || $question->getGame()->getId() !== $game->getId()
            || $player->getOlympix()->getId() !== $game->getOlympix()->getId()) {
            return $this->json(['success' => false, 'error' => 'Spieler oder Frage nicht gefunden'], 404);
        }

        $existing = $this->quizAnswerRepository->findByPlayerAndQuestion($player->getId(), $question->getId());
        if ($existing) {
            return $this->json(['success' => false, 'error' => 'Du hast diese Frage bereits beantwortet'], 409);
        }

        $answer = new QuizAnswer();
        $answer->setPlayer($player);
        $answer->setQuizQuestion($question);
        $answer->setAnswer((string) $answerValue);
        $this->entityManager->persist($answer);
        $this->entityManager->flush();

        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $answeredCount = count($this->quizAnswerRepository->findByQuestion($question->getId()));

        // Letzte Frage von allen beantwortet? Dann Quiz automatisch abschließen
        if ($this->allPlayersAnswered($game)) {
            $this->calculateQuizResults($game);
            $game->setStatus('completed');
            foreach ($game->getOlympix()->getPlayers() as $p) {
                $p->calculateTotalPoints();
            }
            $this->entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'answered' => $answeredCount,
            'total_players' => $totalPlayers,
            'question_complete' => $answeredCount >= $totalPlayers,
            'quiz_completed' => $game->isCompleted(),
        ]);
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

        $allAnswered = $this->allPlayersAnswered($game);

        return $this->json([
            'game_id' => $game->getId(),
            'game_name' => $game->getName(),
            'game_status' => $game->getStatus(),
            'total_players' => $totalPlayers,
            'questions' => count($questions),
            'answered_players' => $answeredPlayers,
            'all_answered' => $allAnswered,
            'is_completed' => $game->isCompleted(),
            'has_results' => $game->hasResults(),
        ]);
    }

    #[Route('/api/quiz/{gameId}/auto-complete', name: 'app_api_quiz_auto_complete')]
    public function apiAutoComplete(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            return $this->json(['error' => 'Spiel nicht gefunden'], 404);
        }

        if (!$game->isQuizGame()) {
            return $this->json(['error' => 'Nur Quiz-Spiele können auto-completed werden'], 400);
        }

        if ($game->isCompleted()) {
            return $this->json(['message' => 'Spiel bereits abgeschlossen'], 200);
        }

        if (!$this->allPlayersAnswered($game)) {
            return $this->json(['error' => 'Noch nicht alle Spieler haben geantwortet'], 400);
        }

        // Calculate results and complete game
        $this->calculateQuizResults($game);
        
        $game->setStatus('completed');
        
        // Update player total points
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $player->calculateTotalPoints();
        }
        
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Quiz wurde automatisch abgeschlossen',
            'game_status' => $game->getStatus(),
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

        // WICHTIG: Die Quiz-Punkte bestimmen nur die RANGLISTE dieses Spiels.
        // In die Gesamtwertung fließen die Punkte aus der Standard-Verteilung
        // (getDefaultPointsDistribution), genau wie bei jedem anderen Spiel.
        // Punktgleiche Spieler teilen sich den Platz und bekommen dieselben
        // Punkte (Competition Ranking 1-1-3).
        $pointsDistribution = $game->getDefaultPointsDistribution();

        $index = 0;
        $groupStartIndex = 0;
        $previousTotal = null;

        foreach ($playerTotalPoints as $playerId => $totalPoints) {
            $player = $this->playerRepository->find($playerId);

            if ($player) {
                if ($previousTotal === null || $totalPoints !== $previousTotal) {
                    $groupStartIndex = $index;
                    $previousTotal = $totalPoints;
                }

                $result = new GameResult();
                $result->setGame($game);
                $result->setPlayer($player);
                $result->setPosition($groupStartIndex + 1);
                $result->setPoints($pointsDistribution[$groupStartIndex] ?? 0);

                $this->entityManager->persist($result);
                $index++;
            }
        }

        $this->entityManager->flush();

        // WICHTIG: Nach dem Erstellen der Quiz-Ergebnisse, JOKER anwenden (gemeinsamer Service)!
        foreach ($this->jokerApplicationService->applyJokersForGame($game) as $message) {
            $this->addFlash($message['type'], $message['message']);
        }
    }

    private function getQuizStats(Game $game): array
    {
        $questions = $this->quizQuestionRepository->findByGameOrdered($game->getId());
        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $stats = [];

        foreach ($questions as $question) {
            $answers = $this->quizAnswerRepository->findByQuestion($question->getId());
            $stats[] = [
                'question_id' => $question->getId(),
                'question_text' => $question->getQuestion(),
                'correct_answer' => $question->getCorrectAnswer(),
                'answers_count' => count($answers),
                'completion_rate' => round((count($answers) / $totalPlayers) * 100, 2),
            ];
        }

        return $stats;
    }

    private function canCompleteQuiz(Game $game): bool
    {
        return $this->allPlayersAnswered($game) && $game->isActive();
    }

    private function getQuizProgress(Game $game): array
    {
        $totalPlayers = $game->getOlympix()->getPlayers()->count();
        $questions = $this->quizQuestionRepository->findByGameOrdered($game->getId());
        $totalAnswersNeeded = count($questions) * $totalPlayers;
        $currentAnswers = 0;

        foreach ($questions as $question) {
            $answers = $this->quizAnswerRepository->findByQuestion($question->getId());
            $currentAnswers += count($answers);
        }

        return [
            'total_answers_needed' => $totalAnswersNeeded,
            'current_answers' => $currentAnswers,
            'progress_percentage' => $totalAnswersNeeded > 0 ? round(($currentAnswers / $totalAnswersNeeded) * 100, 2) : 0,
            'is_complete' => $currentAnswers >= $totalAnswersNeeded,
        ];
    }

    /**
     * Quiz-Antworten sind IMMER ganze Zahlen — "0" und negative Werte sind
     * gültig, Dezimal-/Kommazahlen und Text werden überall abgelehnt.
     */
    private function isWholeNumber(mixed $value): bool
    {
        return preg_match('/^-?\d+$/', trim((string) $value)) === 1;
    }

    /**
     * Ganze Zahl UND im speicherbaren Bereich (±99.999.999) — größere Werte
     * würden von der DECIMAL(10,2)-Spalte gekappt (99999999.99-Bug).
     */
    private function isValidAnswerNumber(mixed $value): bool
    {
        return $this->isWholeNumber($value)
            && abs((int) trim((string) $value)) <= \App\Service\QuizQuestionGeneratorService::MAX_ANSWER;
    }
}