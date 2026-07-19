<?php

namespace App\Tests\Functional;

use App\Entity\Game;

class QuizStepFlowTest extends FunctionalTestCase
{
    private function currentQuestion(Game $game, int $playerId): array
    {
        $this->client->request('GET', '/api/quiz/' . $game->getId() . '/current?player=' . $playerId);
        $this->assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function answer(Game $game, int $playerId, int $questionId, string $value): array
    {
        $this->client->request(
            'POST',
            '/quiz/answer/' . $game->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['player_id' => $playerId, 'question_id' => $questionId, 'answer' => $value])
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testStepByStepFlowCompletesQuiz(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        // Frage 1 ist die aktuelle Frage
        $data = $this->currentQuestion($game, $players[0]->getId());
        $this->assertFalse($data['quiz_completed']);
        $this->assertSame(1, $data['question']['index']);
        $this->assertSame(10, $data['question']['total']);
        $this->assertFalse($data['player_answered']);

        $q1 = $data['question']['id'];

        // Spieler 1 antwortet: Frage bleibt aktuell, player_answered = true
        $result = $this->answer($game, $players[0]->getId(), $q1, '5');
        $this->assertTrue($result['success']);
        $this->assertFalse($result['question_complete']);

        $data = $this->currentQuestion($game, $players[0]->getId());
        $this->assertSame($q1, $data['question']['id']);
        $this->assertTrue($data['player_answered']);
        $this->assertSame(1, $data['answered']);

        // Doppelte Antwort wird abgelehnt
        $dup = $this->answer($game, $players[0]->getId(), $q1, '7');
        $this->assertFalse($dup['success']);
        $this->assertResponseStatusCodeSame(409);

        // Spieler 2 antwortet: Frage 1 komplett -> Frage 2 wird aktuell
        $result = $this->answer($game, $players[1]->getId(), $q1, '99');
        $this->assertTrue($result['question_complete']);

        $data = $this->currentQuestion($game, $players[1]->getId());
        $this->assertSame(2, $data['question']['index']);

        // Auswertung von Frage 1 abrufbar
        $this->client->request('GET', '/api/quiz/question/' . $q1 . '/result');
        $this->assertResponseIsSuccessful();
        $resultData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $resultData['entries']);
        $this->assertSame(2, $resultData['entries'][0]['points'], 'Näherer Tipp bekommt 2 Punkte');
        $this->assertSame(1, $resultData['entries'][1]['points']);

        // Restliche Fragen durchspielen
        while (true) {
            $data = $this->currentQuestion($game, $players[0]->getId());
            if ($data['quiz_completed']) {
                break;
            }
            $qid = $data['question']['id'];
            foreach ($players as $player) {
                $this->answer($game, $player->getId(), $qid, '10');
            }
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertTrue($game->isCompleted(), 'Quiz muss nach der letzten Frage automatisch abgeschlossen sein');
        $this->assertCount(2, $game->getGameResults());

        foreach ($game->getOlympix()->getPlayers() as $player) {
            $this->assertGreaterThan(0, $player->getTotalPoints());
        }
    }

    public function testQuestionResultWithSameAnswerGivesSamePointsAndPosition(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $data = $this->currentQuestion($game, $players[0]->getId());
        $q1 = $data['question']['id'];

        // Beide Spieler geben exakt dieselbe Antwort ab
        $this->answer($game, $players[0]->getId(), $q1, '12');
        $this->answer($game, $players[1]->getId(), $q1, '12');

        $this->client->request('GET', '/api/quiz/question/' . $q1 . '/result');
        $this->assertResponseIsSuccessful();
        $resultData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(2, $resultData['entries']);
        $this->assertSame(2, $resultData['entries'][0]['points'], 'Gleiche Antwort muss gleiche Punkte geben');
        $this->assertSame(2, $resultData['entries'][1]['points'], 'Gleiche Antwort muss gleiche Punkte geben');
        $this->assertSame(1, $resultData['entries'][0]['position']);
        $this->assertSame(1, $resultData['entries'][1]['position']);
    }

    public function testCurrentEndpointReturnsDashboardUrlWhenCompleted(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        foreach ($game->getQuizQuestions() as $question) {
            foreach ($players as $player) {
                $this->answer($game, $player->getId(), $question->getId(), '1');
            }
        }

        $data = $this->currentQuestion($game, $players[0]->getId());
        $this->assertTrue($data['quiz_completed']);
        $this->assertSame(
            '/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId(),
            $data['dashboard_url']
        );
    }

    public function testQuestionResultTieGetsEqualPoints(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $data = $this->currentQuestion($game, $players[0]->getId());
        $qid = $data['question']['id'];

        // Zwei Spieler tippen identisch, einer weit daneben
        $this->answer($game, $players[0]->getId(), $qid, '12');
        $this->answer($game, $players[1]->getId(), $qid, '12');
        $this->answer($game, $players[2]->getId(), $qid, '999999');

        $this->client->request('GET', '/api/quiz/question/' . $qid . '/result');
        $result = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(3, $result['entries'][0]['points']);
        $this->assertSame(3, $result['entries'][1]['points'], 'Gleiche Antworten müssen gleiche Punkte bekommen');
        $this->assertSame(1, $result['entries'][2]['points']);
    }

    public function testFinalScoringUsesPointsDistributionLikeOtherGames(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        // Spieler 0 immer exakt richtig, Spieler 1 leicht daneben, Spieler 2 weit daneben
        foreach ($game->getQuizQuestions() as $question) {
            $correct = (float) $question->getCorrectAnswer();
            $this->answer($game, $players[0]->getId(), $question->getId(), (string) $correct);
            $this->answer($game, $players[1]->getId(), $question->getId(), (string) ($correct + 5));
            $this->answer($game, $players[2]->getId(), $question->getId(), (string) ($correct + 100000));
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted());

        $byPosition = [];
        foreach ($game->getGameResults() as $result) {
            $byPosition[$result->getPosition()] = $result;
        }

        // Endpunkte wie bei jedem anderen Spiel: Verteilung 3,2,1 — NICHT die Fragensummen (30/20/10)
        $this->assertSame($players[0]->getId(), $byPosition[1]->getPlayer()->getId());
        $this->assertSame(3, $byPosition[1]->getPoints(), 'Platz 1 bekommt Punkte aus der Verteilung, nicht die Fragensumme');
        $this->assertSame(2, $byPosition[2]->getPoints());
        $this->assertSame(1, $byPosition[3]->getPoints());
    }

    public function testFinalTieGetsSamePositionAndPoints(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        // Beide Spieler antworten auf ALLES identisch -> kompletter Gleichstand
        foreach ($game->getQuizQuestions() as $question) {
            $this->answer($game, $players[0]->getId(), $question->getId(), '7');
            $this->answer($game, $players[1]->getId(), $question->getId(), '7');
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted());

        foreach ($game->getGameResults() as $result) {
            $this->assertSame(1, $result->getPosition(), 'Gleichstand: beide Platz 1');
            $this->assertSame(2, $result->getPoints(), 'Gleichstand: beide bekommen die Platz-1-Punkte');
        }
    }

    public function testAnswerZeroIsAcceptedInStepMode(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $data = $this->currentQuestion($game, $players[0]->getId());
        $result = $this->answer($game, $players[0]->getId(), $data['question']['id'], '0');

        $this->assertTrue($result['success'], 'Antwort "0" muss im Frage-für-Frage-Modus gültig sein');
    }

    public function testAnswerRejectedWhenQuizNotActive(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz'); // bleibt pending

        $result = $this->answer($game, $players[0]->getId(), 99999, '5');

        $this->assertFalse($result['success']);
        $this->assertResponseStatusCodeSame(400);
    }
}
