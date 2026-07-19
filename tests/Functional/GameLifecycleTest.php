<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Joker;

class GameLifecycleTest extends FunctionalTestCase
{
    public function testIndexPageRenders(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testCreateOlympixViaForm(): void
    {
        $this->client->request('POST', '/create', ['name' => 'Sommerspiele 2026']);

        $this->assertResponseRedirects();

        $olympix = $this->entityManager->getRepository(\App\Entity\Olympix::class)
            ->findOneBy(['name' => 'Sommerspiele 2026']);
        $this->assertNotNull($olympix);
    }

    public function testCreateStopwatchGameViaForm(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 3);

        $this->client->request('POST', '/game/create/' . $olympix->getId(), [
            'name' => 'Blind stoppen',
            'game_type' => 'stopwatch',
        ]);

        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $game = $this->entityManager->getRepository(Game::class)->findOneBy(['name' => 'Blind stoppen']);
        $this->assertNotNull($game);
        $this->assertSame('stopwatch', $game->getGameType());
        $this->assertSame('pending', $game->getStatus());
    }

    public function testInvalidGameTypeIsRejected(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 3);

        $this->client->request('POST', '/game/create/' . $olympix->getId(), [
            'name' => 'Kaputt',
            'game_type' => 'nicht_existent',
        ]);

        $game = $this->entityManager->getRepository(Game::class)->findOneBy(['name' => 'Kaputt']);
        $this->assertNull($game);
    }

    public function testOnlyOneGameCanBeActive(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 3);

        $game1 = $this->createGame($olympix, 'free_for_all', 'Spiel 1');
        $game2 = $this->createGame($olympix, 'free_for_all', 'Spiel 2');

        $this->client->request('GET', '/game/start/' . $game1->getId());
        $this->client->request('GET', '/game/start/' . $game2->getId());

        $this->entityManager->clear();
        $game1 = $this->entityManager->getRepository(Game::class)->find($game1->getId());
        $game2 = $this->entityManager->getRepository(Game::class)->find($game2->getId());

        $this->assertSame('active', $game1->getStatus());
        $this->assertSame('pending', $game2->getStatus(), 'Zweites Spiel darf nicht parallel aktiv werden');
    }

    public function testFreeForAllResultsWithDoubleJoker(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('GET', '/game/start/' . $game->getId());

        // Spieler 2 setzt einen Doppelt-Joker auf dieses Spiel
        $joker = new Joker();
        $joker->setPlayer($players[1]);
        $joker->setGame($game);
        $joker->setJokerType('double');
        $joker->setIsUsed(false);
        $joker->setUsedAt(new \DateTime()); // Spalte ist NOT NULL, Wert zählt erst nach Einsatz
        $this->entityManager->persist($joker);
        $this->entityManager->flush();

        // Ergebnisse eintragen: Spieler 2 wird Erster
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [
                $players[0]->getId() => 2,
                $players[1]->getId() => 1,
                $players[2]->getId() => 3,
            ],
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertTrue($game->isCompleted());

        foreach ($game->getGameResults() as $result) {
            if ($result->getPlayer()->getId() === $players[1]->getId()) {
                $this->assertTrue($result->isJokerDoubleApplied(), 'Doppelt-Joker muss angewendet sein');
                $this->assertSame(3, $result->getPoints());
                $this->assertSame(6, $result->getFinalPoints());
            }
        }

        // Gesamtpunkte: Spieler 2 = 6 (3 x 2)
        $player2 = $game->getOlympix()->getPlayers()->filter(
            fn ($p) => $p->getId() === $players[1]->getId()
        )->first();
        $this->assertSame(6, $player2->getTotalPoints());
    }

    public function testCompletingActiveStopwatchRedirectsToEvaluation(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('GET', '/game/complete/' . $game->getId());

        $this->assertResponseRedirects('/stopwatch/evaluate/' . $game->getId());
    }

    public function testGameResetClearsResults(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
            ],
        ]);

        $this->client->request('GET', '/game/reset/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('pending', $game->getStatus());
        $this->assertCount(0, $game->getGameResults());

        foreach ($game->getOlympix()->getPlayers() as $player) {
            $this->assertSame(0, $player->getTotalPoints());
        }
    }

    public function testGameAdminPageRendersWithAllGameTypes(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 4);

        foreach (['free_for_all', 'quiz', 'stopwatch', 'split_or_steal', 'gamechanger'] as $type) {
            $this->createGame($olympix, $type, 'Spiel ' . $type);
        }

        $this->client->request('GET', '/gameadmin/' . $olympix->getId());
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Spiel stopwatch', $content);
    }

    public function testShowOlympixPageRenders(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 3);

        $this->client->request('GET', '/olympix/' . $olympix->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testPlayerDashboardRenders(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);

        $this->client->request('GET', '/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testPlayerDashboardAutoJoinsActiveStopwatch(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        // Dashboard leitet Spieler automatisch zum aktiven Spiel weiter (Auto-Join)
        $this->client->request('GET', '/player-dashboard/' . $olympix->getId() . '/' . $players[0]->getId());
        $this->assertResponseRedirects('/stopwatch/mobile/' . $game->getId() . '?player=' . $players[0]->getId());
    }
}
