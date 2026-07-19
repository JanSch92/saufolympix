<?php

namespace App\Tests\Functional;

use App\Entity\Game;

class QuizFlowTest extends FunctionalTestCase
{
    public function testStartingQuizAutoGeneratesTenQuestions(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'quiz', 'Wissensquiz');

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('active', $game->getStatus());
        $this->assertCount(10, $game->getQuizQuestions(), 'Beim Quiz-Start müssen 10 Fragen generiert werden');

        foreach ($game->getQuizQuestions() as $question) {
            $this->assertNotEmpty($question->getQuestion());
            $this->assertIsNumeric($question->getCorrectAnswer());
        }
    }

    public function testStartingQuizWithExistingQuestionsDoesNotRegenerate(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');

        // Eine manuelle Frage anlegen
        $this->client->request('POST', '/quiz/questions/' . $game->getId(), [
            'question' => 'Wie viele Bundesländer hat Deutschland?',
            'correct_answer' => '16',
        ]);

        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertCount(1, $game->getQuizQuestions(), 'Vorhandene Fragen dürfen nicht überschrieben werden');
    }

    public function testRegenerateEndpointReplacesQuestions(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');

        $this->client->request('POST', '/quiz/questions/' . $game->getId(), [
            'question' => 'Alte Frage?',
            'correct_answer' => '1',
        ]);

        $this->client->request('POST', '/quiz/generate/' . $game->getId());
        $this->assertResponseRedirects('/quiz/questions/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertCount(10, $game->getQuizQuestions());
        foreach ($game->getQuizQuestions() as $question) {
            $this->assertNotSame('Alte Frage?', $question->getQuestion());
        }
    }

    public function testFullQuizFlowWithAllPlayersAnsweringCompletesGameAndAwardsPoints(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'quiz', 'Wissensquiz');

        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $questions = $game->getQuizQuestions();

        // Spieler 1 antwortet exakt richtig, Spieler 2 leicht daneben, Spieler 3 weit daneben
        $offsets = [0, 5, 1000];
        foreach ($players as $index => $player) {
            $formData = ['player_id' => (string) $player->getId()];
            foreach ($questions as $question) {
                $formData['answer_' . $question->getId()] =
                    (string) (((float) $question->getCorrectAnswer()) + $offsets[$index]);
            }

            $this->client->request('POST', '/quiz/mobile/' . $game->getId(), $formData);
            $this->assertResponseIsSuccessful();
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertTrue($game->isCompleted(), 'Quiz muss automatisch abgeschlossen werden, wenn alle geantwortet haben');
        $this->assertCount(3, $game->getGameResults());

        // Ergebnis prüfen: Spieler 1 gewinnt (10 Fragen x 3 Punkte), Spieler 3 ist Letzter
        $resultsByPosition = [];
        foreach ($game->getGameResults() as $result) {
            $resultsByPosition[$result->getPosition()] = $result;
        }

        $this->assertSame($players[0]->getId(), $resultsByPosition[1]->getPlayer()->getId());
        $this->assertSame(30, $resultsByPosition[1]->getPoints());
        $this->assertSame($players[2]->getId(), $resultsByPosition[3]->getPlayer()->getId());
        $this->assertSame(10, $resultsByPosition[3]->getPoints());

        // Gesamtpunkte der Spieler wurden aktualisiert
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $this->assertGreaterThan(0, $player->getTotalPoints());
        }
    }

    public function testQuizStatusApi(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('GET', '/api/quiz/' . $game->getId() . '/status');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(10, $data['questions']);
        $this->assertSame(2, $data['total_players']);
        $this->assertFalse($data['all_answered']);
    }
}
