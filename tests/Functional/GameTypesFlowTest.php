<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Tournament;

class GameTypesFlowTest extends FunctionalTestCase
{
    public function testSplitOrStealFullFlow(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 4);
        $game = $this->createGame($olympix, 'split_or_steal');

        // Paarungen erstellen (2 Matches bei 4 Spielern), dann starten
        $this->client->request('POST', '/split-or-steal/setup/' . $game->getId(), ['points_at_stake' => 50]);
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertSame('active', $game->getStatus());

        $matches = $game->getSplitOrStealMatches();
        $this->assertCount(2, $matches);

        // Match 1: beide wählen Split (je 25), Match 2: Steal gegen Split (50/0)
        $matchList = $matches->toArray();

        foreach ([['split', 'split'], ['steal', 'split']] as $i => $choices) {
            $match = $matchList[$i];
            $this->client->request('POST', '/split-or-steal/player-choice/' . $match->getId(), [
                'player_id' => $match->getPlayer1()->getId(),
                'choice' => $choices[0],
            ]);
            $this->assertResponseIsSuccessful();

            $this->client->request('POST', '/split-or-steal/player-choice/' . $match->getId(), [
                'player_id' => $match->getPlayer2()->getId(),
                'choice' => $choices[1],
            ]);
            $this->assertResponseIsSuccessful();
        }

        // Auswerten
        $this->client->request('GET', '/split-or-steal/evaluate/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted(), 'Split or Steal muss nach Auswertung abgeschlossen sein');

        $pointsByPlayer = [];
        foreach ($game->getGameResults() as $result) {
            $pointsByPlayer[$result->getPlayer()->getId()] = $result->getFinalPoints();
        }

        $m1 = $matchList[0];
        $m2 = $matchList[1];
        $this->assertSame(25, $pointsByPlayer[$m1->getPlayer1()->getId()], 'Split/Split: je die Hälfte');
        $this->assertSame(25, $pointsByPlayer[$m1->getPlayer2()->getId()]);
        $this->assertSame(50, $pointsByPlayer[$m2->getPlayer1()->getId()], 'Steal gegen Split: Stealer bekommt alles');
        $this->assertSame(0, $pointsByPlayer[$m2->getPlayer2()->getId()]);
    }

    public function testGamechangerFullFlow(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);

        // Ausgangspunkte setzen, damit die Treffer-Regeln deterministisch sind
        $players[0]->setTotalPoints(10);
        $players[1]->setTotalPoints(20);
        $players[2]->setTotalPoints(30);
        $this->entityManager->flush();

        $game = $this->createGame($olympix, 'gamechanger');

        // Setup aktiviert das Spiel und legt die Wurfreihenfolge fest
        $this->client->request('POST', '/gamechanger/setup/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertSame('active', $game->getStatus());

        // Spieler 1 trifft die EIGENEN Punkte (10) -> +8 => 18
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'thrown_points' => 10,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(8, $data['throw']['points_scored']);

        // Spieler 2 trifft Spieler 3 (30) -> Spieler 3 bekommt -4 => 26
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[1]->getId(),
            'thrown_points' => 30,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(-4, $data['throw']['points_scored']);

        // Spieler 3 trifft nichts
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[2]->getId(),
            'thrown_points' => 7,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['is_game_complete'], 'Nach dem letzten Wurf muss das Spiel beendet sein');

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted());
        $this->assertCount(3, $game->getGameResults());

        $totals = [];
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $totals[$player->getId()] = $player->getTotalPoints();
        }
        $this->assertSame(18, $totals[$players[0]->getId()], '10 + 8 (eigene Punkte getroffen)');
        $this->assertSame(20, $totals[$players[1]->getId()]);
        $this->assertSame(26, $totals[$players[2]->getId()], '30 - 4 (getroffen worden)');
    }

    public function testTournamentSingleFullFlow(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 4);
        $game = $this->createGame($olympix, 'tournament_single');

        // Start initialisiert das Bracket
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertSame('active', $game->getStatus());
        $this->assertNotNull($game->getTournament());

        // Bracket durchspielen: immer participant1 gewinnen lassen
        for ($i = 0; $i < 10; $i++) {
            $this->entityManager->clear();
            $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

            if ($game->isCompleted()) {
                break;
            }

            $bracket = $game->getTournament()->getBracketData();
            $match = $this->findOpenMatch($bracket);
            $this->assertNotNull($match, 'Es muss ein offenes Match geben, solange das Turnier läuft');

            $this->client->request('POST', '/game/bracket/' . $game->getId() . '/update-match', [
                'match_id' => $match['id'],
                'winner_id' => $match['participant1']['id'],
                'winner_type' => 'player',
            ]);
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $this->assertTrue($game->isCompleted(), 'Turnier muss nach dem Finale abgeschlossen sein');
        $this->assertGreaterThanOrEqual(2, $game->getGameResults()->count());

        // Punkte kommen aus der Turnier-Verteilung (8, 6, 4, 2)
        $pointsByPosition = [];
        foreach ($game->getGameResults() as $result) {
            $pointsByPosition[$result->getPosition()] = $result->getPoints();
        }
        $this->assertSame(8, $pointsByPosition[1], 'Turniersieger bekommt 8 Punkte');
        $this->assertSame(6, $pointsByPosition[2]);
    }

    private function findOpenMatch(array $bracket): ?array
    {
        foreach ($bracket['rounds'] as $round) {
            foreach ($round as $match) {
                if (empty($match['completed'])
                    && !empty($match['participant1'])
                    && !empty($match['participant2'])) {
                    return $match;
                }
            }
        }

        if (isset($bracket['thirdPlaceMatch'])
            && empty($bracket['thirdPlaceMatch']['completed'])
            && !empty($bracket['thirdPlaceMatch']['participant1'])
            && !empty($bracket['thirdPlaceMatch']['participant2'])) {
            return $bracket['thirdPlaceMatch'];
        }

        return null;
    }
}
