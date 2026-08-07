<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Olympix;

class ResetTest extends FunctionalTestCase
{
    private function playQuiz(Game $game, array $players): void
    {
        foreach ($game->getQuizQuestions() as $question) {
            foreach ($players as $player) {
                $this->client->request(
                    'POST',
                    '/quiz/answer/' . $game->getId(),
                    server: ['CONTENT_TYPE' => 'application/json'],
                    content: json_encode(['player_id' => $player->getId(), 'question_id' => $question->getId(), 'answer' => '5'])
                );
            }
        }
    }

    public function testQuizResetClearsEverythingAndAllowsFreshRestart(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->playQuiz($game, $players);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted());

        // Reset
        $this->client->request('GET', '/game/reset/' . $game->getId());
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('pending', $game->getStatus());
        $this->assertCount(0, $game->getQuizQuestions(), 'Fragen und Antworten müssen weg sein');
        $this->assertCount(0, $game->getGameResults());

        foreach ($game->getOlympix()->getPlayers() as $player) {
            $this->assertSame(0, $player->getTotalPoints(), 'Punkte aus dem Spiel müssen abgezogen sein');
        }

        // Neustart funktioniert: frische Fragen, komplettes Durchspielen
        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertCount(10, $game->getQuizQuestions(), 'Neustart generiert frische Fragen');

        $this->playQuiz($game, $players);
        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted(), 'Spiel muss nach Reset erneut komplett durchspielbar sein');
    }

    public function testStopwatchResetClearsAttemptsAndTarget(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        foreach ($players as $i => $player) {
            $this->client->request(
                'POST',
                '/stopwatch/submit/' . $game->getId(),
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['player_id' => $player->getId(), 'elapsed_seconds' => 10.0 + $i])
            );
        }

        $this->client->request('GET', '/game/reset/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('pending', $game->getStatus());
        $this->assertCount(0, $game->getStopwatchAttempts());
        $this->assertNull($game->getStopwatchTarget(), 'Neustart bekommt eine neue Zielzeit');
    }

    public function testGameResetReturnsUsedJokerToPlayer(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all');
        $this->client->request('GET', '/game/start/' . $game->getId());

        // Joker über die Admin-Route einsetzen
        $this->client->request('GET', '/joker/double/' . $players[0]->getId() . '/' . $game->getId());

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(\App\Entity\Player::class)->find($players[0]->getId());
        $this->assertFalse($player->hasJokerDoubleAvailable(), 'Joker ist eingesetzt');

        $this->client->request('GET', '/game/reset/' . $game->getId());

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(\App\Entity\Player::class)->find($players[0]->getId());
        $this->assertTrue($player->hasJokerDoubleAvailable(), 'Nach Spiel-Reset muss der Joker zurückgegeben werden');
    }

    public function testOlympixResetSetsEverythingBackToStart(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $quiz = $this->createGame($olympix, 'quiz', 'Quiz');
        $stopwatch = $this->createGame($olympix, 'stopwatch', 'Uhr');

        // Quiz komplett durchspielen
        $this->client->request('GET', '/game/start/' . $quiz->getId());
        $this->entityManager->clear();
        $quiz = $this->entityManager->getRepository(Game::class)->find($quiz->getId());
        $this->playQuiz($quiz, $players);

        // Stoppuhr starten + eine Abgabe
        $this->client->request('GET', '/game/start/' . $stopwatch->getId());
        $this->client->request(
            'POST',
            '/stopwatch/submit/' . $stopwatch->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['player_id' => $players[0]->getId(), 'elapsed_seconds' => 9.99])
        );

        // Olympix-Reset
        $this->client->request('POST', '/olympix/reset/' . $olympix->getId());
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $this->entityManager->clear();
        $olympix = $this->entityManager->getRepository(Olympix::class)->find($olympix->getId());

        $this->assertCount(2, $olympix->getGames(), 'Spielplan bleibt erhalten');
        foreach ($olympix->getGames() as $game) {
            $this->assertSame('pending', $game->getStatus());
            $this->assertCount(0, $game->getGameResults());
            $this->assertCount(0, $game->getQuizQuestions());
            $this->assertCount(0, $game->getStopwatchAttempts());
        }

        $this->assertCount(2, $olympix->getPlayers(), 'Spieler bleiben erhalten');
        foreach ($olympix->getPlayers() as $player) {
            $this->assertSame(0, $player->getTotalPoints());
            $this->assertTrue($player->hasJokerDoubleAvailable());
            $this->assertTrue($player->hasJokerSwapAvailable());
        }
    }
}
