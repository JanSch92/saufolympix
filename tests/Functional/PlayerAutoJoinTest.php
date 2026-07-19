<?php

namespace App\Tests\Functional;

use App\Entity\Game;

class PlayerAutoJoinTest extends FunctionalTestCase
{
    public function testQuizAnswerZeroIsValid(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');

        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $questions = $game->getQuizQuestions();

        // Beide Spieler antworten überall mit "0" — muss als gültige Antwort zählen
        foreach ($players as $player) {
            $formData = ['player_id' => (string) $player->getId()];
            foreach ($questions as $question) {
                $formData['answer_' . $question->getId()] = '0';
            }
            $this->client->request('POST', '/quiz/mobile/' . $game->getId(), $formData);
            $this->assertResponseIsSuccessful();
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertTrue($game->isCompleted(), 'Quiz muss auch mit "0"-Antworten abgeschlossen werden');
        $this->assertCount(2, $game->getGameResults());
    }

    public function testDashboardRedirectsToActiveStopwatch(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('GET', '/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId());

        $this->assertResponseRedirects(
            '/stopwatch/mobile/' . $game->getId() . '?player=' . $players[0]->getId(),
            null,
            'Dashboard muss automatisch zum aktiven Stoppuhr-Spiel weiterleiten'
        );
    }

    public function testDashboardRedirectsToActiveQuiz(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('GET', '/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId());

        $this->assertResponseRedirects(
            '/quiz/mobile/' . $game->getId() . '?player=' . $players[0]->getId()
        );
    }

    public function testDashboardShowsNormallyAfterPlayerSubmitted(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        // Spieler 1 gibt ab
        $this->client->request(
            'POST',
            '/stopwatch/submit/' . $game->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['player_id' => $players[0]->getId(), 'elapsed_seconds' => 12.34])
        );

        // Danach: kein Redirect mehr für Spieler 1, Dashboard normal
        $this->client->request('GET', '/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId());
        $this->assertResponseIsSuccessful();

        // Spieler 2 wird weiterhin umgeleitet
        $this->client->request('GET', '/player-dashboard/' . $olympix->getId() . '/' . $players[1]->getId());
        $this->assertResponseRedirects();
    }

    public function testStatusApiContainsJoinUrl(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('GET', '/api/player/' . $olympix->getId() . '/' . $players[0]->getId() . '/status');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(
            '/stopwatch/mobile/' . $game->getId() . '?player=' . $players[0]->getId(),
            $data['current_game']['join_url']
        );
    }

    public function testQuizMobilePreselectsPlayer(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('GET', '/quiz/mobile/' . $game->getId() . '?player=' . $players[0]->getId());
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Du spielst als', $content);
        $this->assertStringContainsString($players[0]->getName(), $content);
        $this->assertStringContainsString('type="hidden" id="player_id" name="player_id" value="' . $players[0]->getId() . '"', $content);
    }

    public function testStopwatchResultsPlayerViewHasNoAdminLink(): void
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

        // Spieleransicht: Dashboard-Link, NIEMALS Admin-Link
        $this->client->request('GET', '/stopwatch/results/' . $game->getId() . '?player=' . $players[0]->getId());
        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId(), $content);
        $this->assertStringNotContainsString('Zurück zum Admin', $content);
        $this->assertStringNotContainsString('/gameadmin/', $content);

        // Adminansicht (ohne player-Parameter): Admin-Link vorhanden
        $this->client->request('GET', '/stopwatch/results/' . $game->getId());
        $this->assertStringContainsString('Zurück zum Admin', $this->client->getResponse()->getContent());
    }

    public function testStopwatchMobilePreselectSkipsPlayerStep(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('GET', '/stopwatch/mobile/' . $game->getId() . '?player=' . $players[0]->getId());
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('id="step-player" class="card p-6 hidden"', $content);
        $this->assertStringContainsString('id="active-player-name">' . $players[0]->getName(), $content);
    }
}
