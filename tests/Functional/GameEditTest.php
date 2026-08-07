<?php

namespace App\Tests\Functional;

use App\Entity\Game;

class GameEditTest extends FunctionalTestCase
{
    public function testEditChangesTypeTeamSizeAndDistributionWhilePending(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 4);
        $game = $this->createGame($olympix, 'free_for_all', 'Wandelbar');

        $this->client->request('POST', '/game/edit/' . $game->getId(), [
            'name' => 'Jetzt Turnier',
            'game_type' => 'tournament_single',
            'points_distribution' => '10,7,4,2',
        ]);
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('Jetzt Turnier', $game->getName());
        $this->assertSame('tournament_single', $game->getGameType(), 'Spieltyp muss beim Bearbeiten änderbar sein');
        $this->assertSame([10, 7, 4, 2], $game->getPointsDistribution());
    }

    public function testEditToTeamTournamentStoresTeamSize(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 4);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('POST', '/game/edit/' . $game->getId(), [
            'name' => 'Team-Turnier',
            'game_type' => 'tournament_team',
            'team_size' => '2',
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('tournament_team', $game->getGameType());
        $this->assertSame(2, $game->getTeamSize());
    }

    public function testEditRejectsInvalidType(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('POST', '/game/edit/' . $game->getId(), [
            'name' => 'Kaputt',
            'game_type' => 'gibt_es_nicht',
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('free_for_all', $game->getGameType(), 'Ungültiger Typ darf nicht übernommen werden');
    }

    public function testActiveGameKeepsTypeButAllowsRename(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'stopwatch');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->client->request('POST', '/game/edit/' . $game->getId(), [
            'name' => 'Umbenannt',
            'game_type' => 'quiz',
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertSame('Umbenannt', $game->getName());
        $this->assertSame('stopwatch', $game->getGameType(), 'Bei laufenden Spielen darf der Typ nicht mehr wechseln');
    }

    public function testEditPageShowsAllGameTypes(): void
    {
        $olympix = $this->createOlympix();
        $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('GET', '/game/edit/' . $game->getId());
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        foreach (['split_or_steal', 'gamechanger', 'stopwatch'] as $type) {
            $this->assertStringContainsString('value="' . $type . '"', $content, "Spieltyp $type muss im Bearbeiten-Dropdown stehen");
        }
    }
}
