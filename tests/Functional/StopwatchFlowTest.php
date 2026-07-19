<?php

namespace App\Tests\Functional;

use App\Entity\Game;

class StopwatchFlowTest extends FunctionalTestCase
{
    private function submitTime(Game $game, int $playerId, float $elapsed): array
    {
        $this->client->request(
            'POST',
            '/stopwatch/submit/' . $game->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['player_id' => $playerId, 'elapsed_seconds' => $elapsed])
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testStartingStopwatchGameSetsRandomTargetBetween5And60(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch', 'Blindes Stoppen');

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('active', $game->getStatus());
        $this->assertNotNull($game->getStopwatchTarget());
        $this->assertGreaterThanOrEqual(5.0, (float) $game->getStopwatchTarget());
        $this->assertLessThanOrEqual(60.0, (float) $game->getStopwatchTarget());
    }

    public function testMobileAndManagePagesRender(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('GET', '/stopwatch/mobile/' . $game->getId());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Zielzeit', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/stopwatch/manage/' . $game->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testFullStopwatchFlowAutoEvaluatesWhenAllSubmitted(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'stopwatch');

        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $target = (float) $game->getStopwatchTarget();

        // Spieler 1 fast exakt, Spieler 2 daneben, Spieler 3 weit daneben
        $data1 = $this->submitTime($game, $players[0]->getId(), round($target + 0.05, 2));
        $this->assertTrue($data1['success']);
        $this->assertFalse($data1['all_submitted']);

        $data2 = $this->submitTime($game, $players[1]->getId(), round($target + 2.5, 2));
        $this->assertTrue($data2['success']);

        $data3 = $this->submitTime($game, $players[2]->getId(), round($target + 8.0, 2));
        $this->assertTrue($data3['success']);
        $this->assertTrue($data3['all_submitted']);
        $this->assertTrue($data3['game_completed'], 'Spiel muss nach der letzten Abgabe automatisch ausgewertet werden');

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertTrue($game->isCompleted());
        $this->assertCount(3, $game->getGameResults());

        $resultsByPosition = [];
        foreach ($game->getGameResults() as $result) {
            $resultsByPosition[$result->getPosition()] = $result;
        }

        // Bester = Spieler 1 mit 3 Punkten, Letzter = Spieler 3 mit 1 Punkt
        $this->assertSame($players[0]->getId(), $resultsByPosition[1]->getPlayer()->getId());
        $this->assertSame(3, $resultsByPosition[1]->getPoints());
        $this->assertSame($players[2]->getId(), $resultsByPosition[3]->getPlayer()->getId());
        $this->assertSame(1, $resultsByPosition[3]->getPoints());

        // Gesamtpunkte aktualisiert
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $this->assertGreaterThan(0, $player->getTotalPoints());
        }

        // Ergebnisseite rendert
        $this->client->request('GET', '/stopwatch/results/' . $game->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testDuplicateSubmissionIsRejected(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $first = $this->submitTime($game, $players[0]->getId(), 12.34);
        $this->assertTrue($first['success']);

        $second = $this->submitTime($game, $players[0]->getId(), 15.00);
        $this->assertFalse($second['success']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testImplausibleTimesAreRejected(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $negative = $this->submitTime($game, $players[0]->getId(), -1.0);
        $this->assertFalse($negative['success']);
        $this->assertResponseStatusCodeSame(400);

        $tooLong = $this->submitTime($game, $players[0]->getId(), 999.0);
        $this->assertFalse($tooLong['success']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSubmitRejectedWhenGameNotActive(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch'); // bleibt pending

        $data = $this->submitTime($game, $players[0]->getId(), 10.0);

        $this->assertFalse($data['success']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testForeignPlayerCannotSubmit(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $otherOlympix = $this->createOlympix('Anderes');
        $foreignPlayers = $this->createPlayers($otherOlympix, 1);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $data = $this->submitTime($game, $foreignPlayers[0]->getId(), 10.0);

        $this->assertFalse($data['success']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testStatusApiTracksSubmissions(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->submitTime($game, $players[0]->getId(), 10.0);

        $this->client->request('GET', '/api/stopwatch/' . $game->getId() . '/status');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['submitted_count']);
        $this->assertSame(2, $data['total_players']);
        $this->assertFalse($data['all_submitted']);
        $this->assertSame('Spieler1', $data['submitted'][0]['player_name']);
    }
}
