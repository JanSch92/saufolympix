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

        // EINHEITSSCHEMA: Beute (50/25/25/0) bestimmt nur die Rangfolge,
        // Punkte kommen aus der Verteilung 4..1 mit geteilten Plätzen
        $m1 = $matchList[0];
        $m2 = $matchList[1];
        $this->assertSame(4, $pointsByPlayer[$m2->getPlayer1()->getId()], 'Stealer (50 Beute) = Platz 1 = 4 Punkte');
        $this->assertSame(3, $pointsByPlayer[$m1->getPlayer1()->getId()], 'Splitter (25 Beute) teilen sich Platz 2 = je 3 Punkte');
        $this->assertSame(3, $pointsByPlayer[$m1->getPlayer2()->getId()]);
        $this->assertSame(1, $pointsByPlayer[$m2->getPlayer2()->getId()], 'Bestohlener (0 Beute) = Platz 4 = 1 Punkt');
    }

    public function testGamechangerFullFlow(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);

        // Ausgangspunkte über ein ECHTES Spiel (FFA): 3 / 2 / 1
        $ffa = $this->createGame($olympix, 'free_for_all', 'Basis');
        $this->client->request('GET', '/game/start/' . $ffa->getId());
        $this->client->request('POST', '/game/results/' . $ffa->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
                $players[2]->getId() => 3,
            ],
        ]);

        // Nach Client-Requests ist der EntityManager resettet — Olympix neu laden
        $this->entityManager->clear();
        $olympix = $this->entityManager->getRepository(\App\Entity\Olympix::class)->find($olympix->getId());

        $game = $this->createGame($olympix, 'gamechanger');

        // Setup aktiviert das Spiel und legt die Wurfreihenfolge fest
        $this->client->request('POST', '/gamechanger/setup/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertSame('active', $game->getStatus());

        // Spieler 1 trifft die EIGENEN Punkte (3) -> Wertung +8
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'thrown_points' => 3,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(8, $data['throw']['points_scored']);

        // Spieler 2 trifft Spieler 3 (1 Punkt) -> Wertung -4 für Spieler 3
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[1]->getId(),
            'thrown_points' => 1,
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

        // EINHEITSSCHEMA: Wertung (+8 / 0 / -4) bestimmt nur die Rangfolge,
        // Punkte kommen aus der Verteilung 3..1
        $totals = [];
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $totals[$player->getId()] = $player->getTotalPoints();
        }
        $this->assertSame(6, $totals[$players[0]->getId()], '3 (FFA) + 3 (Wertung +8 = Platz 1)');
        $this->assertSame(4, $totals[$players[1]->getId()], '2 (FFA) + 2 (Wertung 0 = Platz 2)');
        $this->assertSame(2, $totals[$players[2]->getId()], '1 (FFA) + 1 (Wertung -4 = Platz 3)');

        // Invariante: Gesamtpunkte == Summe der Spielergebnisse
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $sum = 0;
            foreach ($player->getGameResults() as $result) {
                $sum += $result->getFinalPoints();
            }
            $this->assertSame($sum, $player->getTotalPoints(), 'Invariante: Gesamt == Summe der Ergebnisse');
        }
    }

    public function testGamechangerZeroThrowCountsAsMiss(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $players[0]->setTotalPoints(10);
        $players[1]->setTotalPoints(20);
        $this->entityManager->flush();

        $game = $this->createGame($olympix, 'gamechanger');
        $this->client->request('POST', '/gamechanger/setup/' . $game->getId());

        // Spieler 1 verfehlt alles: 0 Punkte geworfen — muss trotzdem als Wurf zählen
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'thrown_points' => 0,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);

        // Derselbe Spieler darf NICHT nochmal werfen
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'thrown_points' => 5,
        ]);
        $again = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($again['success'], 'Ein 0-Punkte-Wurf zählt als Wurf — kein zweiter Versuch');

        // Spieler 2 wirft -> Spiel muss abgeschlossen sein
        $this->client->request('POST', '/gamechanger/throw/' . $game->getId(), [
            'player_id' => $players[1]->getId(),
            'thrown_points' => 3,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['is_game_complete'], 'Spiel muss auch mit einem 0-Punkte-Wurf abgeschlossen werden');

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted());
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

        // Bracket-Seite rendert (Formulare zeigen auf die update-match-Route)
        $this->client->request('GET', '/game/bracket/' . $game->getId());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            '/game/bracket/' . $game->getId() . '/update-match',
            $this->client->getResponse()->getContent()
        );

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

        // EINHEITSSCHEMA: auch Turnier vergibt n..1 (bei 4 Spielern: 4, 3, 2, 1)
        $pointsByPosition = [];
        foreach ($game->getGameResults() as $result) {
            $pointsByPosition[$result->getPosition()] = $result->getPoints();
        }
        $this->assertSame(4, $pointsByPosition[1], 'Turniersieger bekommt 4 Punkte (bei 4 Spielern)');
        $this->assertSame(3, $pointsByPosition[2]);
    }

    public function testTournamentWith8PlayersHasSharedPlace5(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 8);
        $game = $this->createGame($olympix, 'tournament_single');

        $this->client->request('GET', '/game/start/' . $game->getId());

        // Bracket komplett durchspielen: immer der Spieler mit der kleineren ID gewinnt
        // (8 Spieler: 4 Viertelfinals, 2 Halbfinals, Spiel um Platz 3, Finale)
        for ($i = 0; $i < 12; $i++) {
            $this->entityManager->clear();
            $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

            if ($game->isCompleted()) {
                break;
            }

            $match = $this->findOpenMatch($game->getTournament()->getBracketData());
            $this->assertNotNull($match, 'Es muss ein offenes Match geben, solange das Turnier läuft');

            $winner = $match['participant1']['id'] < $match['participant2']['id']
                ? $match['participant1']
                : $match['participant2'];

            $this->client->request('POST', '/game/bracket/' . $game->getId() . '/update-match', [
                'match_id' => $match['id'],
                'winner_id' => $winner['id'],
                'winner_type' => 'player',
            ]);
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted(), 'Turnier mit 8 Spielern muss abgeschlossen sein');
        $this->assertCount(8, $game->getGameResults(), 'Jeder der 8 Spieler braucht ein Ergebnis');

        $positions = [];
        $pointsByPosition = [];
        foreach ($game->getGameResults() as $result) {
            $positions[] = $result->getPosition();
            $pointsByPosition[$result->getPosition()] = $result->getPoints();
        }
        sort($positions);

        // Plätze 1-4 werden ausgespielt, die 4 Viertelfinal-Verlierer teilen sich Platz 5
        $this->assertSame([1, 2, 3, 4, 5, 5, 5, 5], $positions, 'Viertelfinal-Verlierer teilen sich Platz 5');

        // EINHEITSSCHEMA bei 8 Spielern: 8, 7, 6, 5 und 4 für den geteilten Platz 5
        $this->assertSame(8, $pointsByPosition[1]);
        $this->assertSame(7, $pointsByPosition[2]);
        $this->assertSame(6, $pointsByPosition[3]);
        $this->assertSame(5, $pointsByPosition[4]);
        $this->assertSame(4, $pointsByPosition[5], 'Geteilter Platz 5 = 4 Punkte für jeden Viertelfinal-Verlierer');

        // Spieler mit der kleinsten ID hat alles gewonnen -> Platz 1
        foreach ($game->getGameResults() as $result) {
            if ($result->getPlayer()->getId() === $players[0]->getId()) {
                $this->assertSame(1, $result->getPosition());
            }
        }

        // Invariante: Gesamtpunkte == Summe der Spielergebnisse
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $sum = 0;
            foreach ($player->getGameResults() as $result) {
                $sum += $result->getFinalPoints();
            }
            $this->assertSame($sum, $player->getTotalPoints());
        }
    }

    public function testTournamentTeamFullFlowWith8Players(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 8);
        $game = $this->createGame($olympix, 'tournament_team');
        $game->setTeamSize(2);
        $this->entityManager->flush();

        // Start bildet zufällige 2er-Teams (4 Teams) und das Bracket
        $this->client->request('GET', '/game/start/' . $game->getId());

        // Bracket durchspielen: 2 Halbfinals, Spiel um Platz 3, Finale
        for ($i = 0; $i < 8; $i++) {
            $this->entityManager->clear();
            $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

            if ($game->isCompleted()) {
                break;
            }

            $match = $this->findOpenMatch($game->getTournament()->getBracketData());
            $this->assertNotNull($match, 'Es muss ein offenes Match geben, solange das Turnier läuft');

            $this->client->request('POST', '/game/bracket/' . $game->getId() . '/update-match', [
                'match_id' => $match['id'],
                'winner_id' => $match['participant1']['id'],
                'winner_type' => 'team',
            ]);
        }

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        $this->assertTrue($game->isCompleted(), 'Team-Turnier muss nach dem Finale abgeschlossen sein');
        $this->assertCount(8, $game->getGameResults(), 'Jeder Spieler braucht ein Ergebnis (Teams teilen die Platzierung)');

        // EINHEITSSCHEMA für Teams: Team-Platz -> Punkte der Verteilung für JEDES Mitglied
        // 4 Teams: Platz 1 = 8/8, Platz 2 = 7/7, Platz 3 = 6/6, Platz 4 = 5/5
        $positions = [];
        $points = [];
        foreach ($game->getGameResults() as $result) {
            $positions[] = $result->getPosition();
            $points[] = $result->getPoints();
        }
        sort($positions);
        sort($points);
        $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $positions, 'Beide Teammitglieder teilen sich die Team-Platzierung');
        $this->assertSame([5, 5, 6, 6, 7, 7, 8, 8], $points, 'Teampunkte folgen der Verteilung 8..1 nach Team-Platz');

        // Invariante: Gesamtpunkte == Summe der Spielergebnisse
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $sum = 0;
            foreach ($player->getGameResults() as $result) {
                $sum += $result->getFinalPoints();
            }
            $this->assertSame($sum, $player->getTotalPoints());
        }
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
