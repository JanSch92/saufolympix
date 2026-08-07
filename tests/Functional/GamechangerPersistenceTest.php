<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Olympix;

/**
 * Reproduziert den gemeldeten Endstand-Fehler: Gamechanger-Punkte gingen
 * verloren, sobald ein späteres Spiel abgeschlossen wurde. Mit dem
 * Einheitsschema (jedes Spiel vergibt n..1 über GameResults) gilt jetzt
 * überall die Invariante: Gesamtpunkte == Summe aller Spielergebnisse.
 */
class GamechangerPersistenceTest extends FunctionalTestCase
{
    public function testGamechangerPointsSurviveLaterGames(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);

        // Ausgangslage über ein echtes Spiel (FFA): 3/2/1 Punkte
        $ffa = $this->createGame($olympix, 'free_for_all', 'Spiel 1');
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
        $olympix = $this->entityManager->getRepository(Olympix::class)->find($olympix->getId());

        // Gamechanger: Spieler 1 trifft die eigenen Punkte (3) -> Wertung +8 -> Platz 1 -> +3
        $gc = $this->createGame($olympix, 'gamechanger', 'Spiel 2');
        $this->client->request('POST', '/gamechanger/setup/' . $gc->getId());
        $this->client->request('POST', '/gamechanger/throw/' . $gc->getId(), [
            'player_id' => $players[0]->getId(),
            'thrown_points' => 3,
        ]);
        $this->client->request('POST', '/gamechanger/throw/' . $gc->getId(), [
            'player_id' => $players[1]->getId(),
            'thrown_points' => 99,
        ]);
        $this->client->request('POST', '/gamechanger/throw/' . $gc->getId(), [
            'player_id' => $players[2]->getId(),
            'thrown_points' => 98,
        ]);

        $this->entityManager->clear();
        $olympix = $this->entityManager->getRepository(Olympix::class)->find($olympix->getId());
        $totals = [];
        foreach ($olympix->getPlayers() as $player) {
            $totals[$player->getId()] = $player->getTotalPoints();
        }
        // Wertung 8/0/0 -> Plätze 1, 2, 2 (geteilt) -> Punkte 3, 2, 2
        $this->assertSame(6, $totals[$players[0]->getId()], '3 (FFA) + 3 (Gamechanger Platz 1)');
        $this->assertSame(4, $totals[$players[1]->getId()], '2 + 2 (geteilter Platz 2)');
        $this->assertSame(3, $totals[$players[2]->getId()], '1 + 2 (geteilter Platz 2)');

        // Jetzt ein weiteres Spiel komplett durchspielen (Stoppuhr)
        $stopwatch = $this->createGame($olympix, 'stopwatch', 'Spiel 3');
        $this->client->request('GET', '/game/start/' . $stopwatch->getId());

        $this->entityManager->clear();
        $stopwatch = $this->entityManager->getRepository(Game::class)->find($stopwatch->getId());
        $target = (float) $stopwatch->getStopwatchTarget();

        foreach ([0.05, 1.0, 2.0] as $i => $offset) {
            $this->client->request(
                'POST',
                '/stopwatch/submit/' . $stopwatch->getId(),
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['player_id' => $players[$i]->getId(), 'elapsed_seconds' => round($target + $offset, 2)])
            );
        }

        $this->entityManager->clear();
        $olympix = $this->entityManager->getRepository(Olympix::class)->find($olympix->getId());

        $totals = [];
        $sumResults = [];
        foreach ($olympix->getPlayers() as $player) {
            $totals[$player->getId()] = $player->getTotalPoints();
            $sum = 0;
            foreach ($player->getGameResults() as $result) {
                $sum += $result->getFinalPoints();
            }
            $sumResults[$player->getId()] = $sum;
        }

        // DER BUG von früher: Gamechanger-Punkte dürfen durch den Abschluss
        // des nächsten Spiels NICHT verloren gehen.
        // Spieler 1: 3 (FFA) + 3 (GC) + 3 (Stoppuhr Platz 1) = 9
        $this->assertSame(9, $totals[$players[0]->getId()], 'Gamechanger-Punkte müssen spätere Spiele überleben');

        // Invariante für JEDEN Spieler: Gesamt == Summe aller Ergebnisse
        foreach ($totals as $playerId => $total) {
            $this->assertSame($sumResults[$playerId], $total, "Invariante verletzt für Spieler $playerId");
        }
    }

    public function testDoubleJokerOnGamechangerIsApplied(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);

        // Basis über echtes FFA: 2 / 1 Punkte
        $ffa = $this->createGame($olympix, 'free_for_all', 'Basis');
        $this->client->request('GET', '/game/start/' . $ffa->getId());
        $this->client->request('POST', '/game/results/' . $ffa->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
            ],
        ]);

        // Nach Client-Requests ist der EntityManager resettet — Olympix neu laden
        $this->entityManager->clear();
        $olympix = $this->entityManager->getRepository(Olympix::class)->find($olympix->getId());

        $gc = $this->createGame($olympix, 'gamechanger');

        // Spieler 1 merkt Doppelt-Joker für das PENDING Gamechanger-Spiel vor
        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $gc->getId(),
        ]);

        $this->client->request('POST', '/gamechanger/setup/' . $gc->getId());

        // Spieler 1 trifft eigene Punkte (2) -> Wertung +8 -> Platz 1, Spieler 2 verfehlt
        $this->client->request('POST', '/gamechanger/throw/' . $gc->getId(), [
            'player_id' => $players[0]->getId(),
            'thrown_points' => 2,
        ]);
        $this->client->request('POST', '/gamechanger/throw/' . $gc->getId(), [
            'player_id' => $players[1]->getId(),
            'thrown_points' => 99,
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($gc->getId());
        $this->assertTrue($game->isCompleted());

        foreach ($game->getGameResults() as $result) {
            if ($result->getPlayer()->getId() === $players[0]->getId()) {
                $this->assertTrue($result->isJokerDoubleApplied(), 'Doppelt-Joker muss auch beim Gamechanger angewendet werden');
                $this->assertSame(2, $result->getPoints(), 'Platz 1 bei 2 Spielern = 2 Punkte');
                $this->assertSame(4, $result->getFinalPoints(), '2 verdoppelt = 4');
            }
        }

        // Gesamtpunkte: 2 (FFA) + 4 (GC verdoppelt) = 6
        $player = $this->entityManager->getRepository(\App\Entity\Player::class)->find($players[0]->getId());
        $this->assertSame(6, $player->getTotalPoints());
    }
}
