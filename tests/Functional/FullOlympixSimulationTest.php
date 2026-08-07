<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Joker;
use App\Entity\Olympix;
use App\Entity\Player;

/**
 * Komplette Olympix-Simulation mit 8 Spielern über alle Spielmodi.
 * JEDER Spieler nutzt BEIDE Joker (8x Doppelt + 8x Tausch = 16 Joker).
 *
 * Da pro Spiel nur EIN Tausch-Joker erlaubt ist, braucht die Simulation
 * 8 Spiele: FFA, Stoppuhr, Quiz, Split or Steal, Gamechanger,
 * Turnier (Einzel) und zwei weitere FFA-Spiele.
 *
 * Spieler i legt beide Joker auf Spiel i; der Tausch zielt auf Spieler i+1.
 * Nach JEDEM Spiel wird die Invariante geprüft:
 * Gesamtpunkte == Summe aller finalPoints.
 */
class FullOlympixSimulationTest extends FunctionalTestCase
{
    /** @var int[] */
    private array $playerIds = [];

    public function testFullOlympixWith8PlayersAndAllJokers(): void
    {
        $olympix = $this->createOlympix('Simulations-Olympix');
        $players = $this->createPlayers($olympix, 8);
        $olympixId = $olympix->getId();
        $this->playerIds = array_map(fn (Player $p) => $p->getId(), $players);

        // Alle 8 Spiele VOR dem ersten Request anlegen (alle pending)
        $types = [
            'free_for_all', 'stopwatch', 'quiz', 'split_or_steal',
            'gamechanger', 'tournament_single', 'free_for_all', 'free_for_all',
        ];
        $gameIds = [];
        foreach ($types as $i => $type) {
            $gameIds[$i] = $this->createGame($olympix, $type, 'Spiel ' . ($i + 1))->getId();
        }

        // ===== ALLE 16 JOKER VORMERKEN (jeder Spieler beide) =====
        foreach ($this->playerIds as $i => $playerId) {
            $this->client->request('POST', "/player-joker-double/$olympixId/$playerId", [
                'selected_game_id' => $gameIds[$i],
            ]);
            $this->client->request('POST', "/player-joker-swap/$olympixId/$playerId", [
                'target_player_id' => $this->playerIds[($i + 1) % 8],
                'selected_game_id' => $gameIds[$i],
            ]);
        }

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findAll();
        $this->assertCount(16, $jokers, 'Alle 16 Joker müssen vorgemerkt sein');
        foreach ($jokers as $joker) {
            $this->assertFalse($joker->isIsUsed(), 'Vorgemerkte Joker sind noch nicht angewendet');
        }
        foreach ($this->loadPlayers() as $player) {
            $this->assertFalse($player->hasJokerDoubleAvailable(), 'Doppelt-Joker global verbraucht (vorgemerkt)');
            $this->assertFalse($player->hasJokerSwapAvailable(), 'Tausch-Joker global verbraucht (vorgemerkt)');
        }

        $p = $this->playerIds;

        // ===== SPIEL 1: FFA, Plätze P1..P8 = 1..8 =====
        // Basis 8..1; Joker: P1 doppelt + Tausch P1<->P2
        // Double zuerst (Flag), dann Tausch: P1 bekommt Platz 2 (7 Punkte) verdoppelt = 14, P2 Platz 1 = 8
        $this->client->request('GET', '/game/start/' . $gameIds[0]);
        $positions = [];
        foreach ($p as $i => $pid) {
            $positions[$pid] = $i + 1;
        }
        $this->client->request('POST', '/game/results/' . $gameIds[0], ['positions' => $positions]);

        $this->assertGameFinalPoints($gameIds[0], [
            $p[0] => 14, $p[1] => 8, $p[2] => 6, $p[3] => 5,
            $p[4] => 4, $p[5] => 3, $p[6] => 2, $p[7] => 1,
        ], 'Spiel 1 (FFA mit Doppelt+Tausch P1/P2)');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 2: Stoppuhr, P1 am nächsten dran =====
        // Basis 8..1; Joker: P2 doppelt + Tausch P2<->P3 -> P2: 6*2=12, P3: 7
        $this->client->request('GET', '/game/start/' . $gameIds[1]);
        $this->entityManager->clear();
        $stopwatch = $this->entityManager->getRepository(Game::class)->find($gameIds[1]);
        $target = (float) $stopwatch->getStopwatchTarget();
        foreach ($p as $i => $pid) {
            $this->client->request(
                'POST',
                '/stopwatch/submit/' . $gameIds[1],
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'player_id' => $pid,
                    'elapsed_seconds' => round($target + 0.11 * ($i + 1), 2),
                ])
            );
        }

        $this->assertGameFinalPoints($gameIds[1], [
            $p[0] => 8, $p[1] => 12, $p[2] => 7, $p[3] => 5,
            $p[4] => 4, $p[5] => 3, $p[6] => 2, $p[7] => 1,
        ], 'Spiel 2 (Stoppuhr mit Doppelt+Tausch P2/P3)');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 3: Quiz, P1 exakt, jeder weitere 10 daneben =====
        // Basis 8..1; Joker: P3 doppelt + Tausch P3<->P4 -> P3: 5*2=10, P4: 6
        $this->client->request('GET', '/game/start/' . $gameIds[2]);
        $this->entityManager->clear();
        $quiz = $this->entityManager->getRepository(Game::class)->find($gameIds[2]);
        $this->assertCount(10, $quiz->getQuizQuestions(), 'Quiz braucht 10 automatisch generierte Fragen');

        foreach ($quiz->getQuizQuestions() as $question) {
            $correct = (int) (float) $question->getCorrectAnswer();
            foreach ($p as $i => $pid) {
                $this->client->request(
                    'POST',
                    '/quiz/answer/' . $gameIds[2],
                    server: ['CONTENT_TYPE' => 'application/json'],
                    content: json_encode([
                        'player_id' => $pid,
                        'question_id' => $question->getId(),
                        'answer' => (string) ($correct + 10 * $i),
                    ])
                );
                $response = json_decode($this->client->getResponse()->getContent(), true);
                $this->assertTrue($response['success'], 'Quiz-Antwort (ganze Zahl) muss akzeptiert werden');
            }
        }

        $this->assertGameFinalPoints($gameIds[2], [
            $p[0] => 8, $p[1] => 7, $p[2] => 10, $p[3] => 6,
            $p[4] => 4, $p[5] => 3, $p[6] => 2, $p[7] => 1,
        ], 'Spiel 3 (Quiz mit Doppelt+Tausch P3/P4)');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 4: Split or Steal, ALLE teilen =====
        // Alle 25 Beute -> geteilter Platz 1 -> alle 8 Punkte
        // Joker: P4 doppelt (16) + Tausch P4<->P5 (identische Werte, kein Effekt)
        $this->client->request('POST', '/split-or-steal/setup/' . $gameIds[3], ['points_at_stake' => 50]);
        $this->client->request('GET', '/game/start/' . $gameIds[3]);
        $this->entityManager->clear();
        $sos = $this->entityManager->getRepository(Game::class)->find($gameIds[3]);
        $this->assertCount(4, $sos->getSplitOrStealMatches(), '8 Spieler = 4 Paarungen');

        foreach ($sos->getSplitOrStealMatches() as $match) {
            foreach ([$match->getPlayer1(), $match->getPlayer2()] as $matchPlayer) {
                $this->client->request('POST', '/split-or-steal/player-choice/' . $match->getId(), [
                    'player_id' => $matchPlayer->getId(),
                    'choice' => 'split',
                ]);
            }
        }
        $this->client->request('GET', '/split-or-steal/evaluate/' . $gameIds[3]);

        $this->assertGameFinalPoints($gameIds[3], [
            $p[0] => 8, $p[1] => 8, $p[2] => 8, $p[3] => 16,
            $p[4] => 8, $p[5] => 8, $p[6] => 8, $p[7] => 8,
        ], 'Spiel 4 (Split or Steal, alle teilen, P4 verdoppelt)');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 5: Gamechanger, alle verfehlen =====
        // Alle Wertung 0 -> geteilter Platz 1 -> alle 8 Punkte
        // Joker: P5 doppelt (16) + Tausch P5<->P6 (identische Werte, kein Effekt)
        $this->client->request('POST', '/gamechanger/setup/' . $gameIds[4]);
        foreach ($p as $pid) {
            $this->client->request('POST', '/gamechanger/throw/' . $gameIds[4], [
                'player_id' => $pid,
                'thrown_points' => 99999,
            ]);
            $response = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertTrue($response['success'], 'Gamechanger-Wurf muss angenommen werden');
        }

        $this->assertGameFinalPoints($gameIds[4], [
            $p[0] => 8, $p[1] => 8, $p[2] => 8, $p[3] => 8,
            $p[4] => 16, $p[5] => 8, $p[6] => 8, $p[7] => 8,
        ], 'Spiel 5 (Gamechanger, alle verfehlen, P5 verdoppelt)');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 6: Turnier (Einzel), kleinere ID gewinnt immer =====
        // Plätze 1-4 ausgespielt, Viertelfinal-Verlierer teilen sich Platz 5
        // Joker: P6 doppelt + Tausch P6<->P7 (Plätze bracket-abhängig -> strukturell prüfen)
        $this->client->request('GET', '/game/start/' . $gameIds[5]);
        for ($i = 0; $i < 12; $i++) {
            $this->entityManager->clear();
            $tournament = $this->entityManager->getRepository(Game::class)->find($gameIds[5]);
            if ($tournament->isCompleted()) {
                break;
            }
            $match = $this->findOpenMatch($tournament->getTournament()->getBracketData());
            $this->assertNotNull($match);
            $winner = $match['participant1']['id'] < $match['participant2']['id']
                ? $match['participant1']
                : $match['participant2'];
            $this->client->request('POST', '/game/bracket/' . $gameIds[5] . '/update-match', [
                'match_id' => $match['id'],
                'winner_id' => $winner['id'],
                'winner_type' => 'player',
            ]);
        }

        $this->entityManager->clear();
        $tournament = $this->entityManager->getRepository(Game::class)->find($gameIds[5]);
        $this->assertTrue($tournament->isCompleted(), 'Turnier muss abgeschlossen sein');
        $this->assertCount(8, $tournament->getGameResults());

        $tournamentFinals = [];
        $tournamentPositions = [];
        foreach ($tournament->getGameResults() as $result) {
            $tournamentFinals[$result->getPlayer()->getId()] = $result->getFinalPoints();
            $tournamentPositions[] = $result->getPosition();
            $this->assertSame(
                [1 => 8, 2 => 7, 3 => 6, 4 => 5, 5 => 4][$result->getPosition()],
                $result->getPoints(),
                'Turnierpunkte folgen dem Einheitsschema 8..1 mit geteiltem Platz 5'
            );
            if ($result->getPlayer()->getId() === $p[0]) {
                $this->assertSame(1, $result->getPosition(), 'P1 (kleinste ID) gewinnt das Turnier');
            }
            if ($result->getPlayer()->getId() === $p[5]) {
                $this->assertTrue($result->isJokerDoubleApplied(), 'P6 hat den Doppelt-Joker auf dem Turnier');
                $this->assertSame($result->getPoints() * 2, $result->getFinalPoints());
            }
        }
        sort($tournamentPositions);
        $this->assertSame([1, 2, 3, 4, 5, 5, 5, 5], $tournamentPositions, 'Viertelfinal-Verlierer teilen sich Platz 5');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 7: FFA umgekehrt, P8 gewinnt =====
        // Basis: Platz von Pi = 8-i; Joker: P7 doppelt + Tausch P7<->P8 -> P7: 8*2=16, P8: 7
        $this->client->request('GET', '/game/start/' . $gameIds[6]);
        $positions = [];
        foreach ($p as $i => $pid) {
            $positions[$pid] = 8 - $i;
        }
        $this->client->request('POST', '/game/results/' . $gameIds[6], ['positions' => $positions]);

        $this->assertGameFinalPoints($gameIds[6], [
            $p[0] => 1, $p[1] => 2, $p[2] => 3, $p[3] => 4,
            $p[4] => 5, $p[5] => 6, $p[6] => 16, $p[7] => 7,
        ], 'Spiel 7 (FFA umgekehrt mit Doppelt+Tausch P7/P8)');
        $this->assertInvariant($olympixId);

        // ===== SPIEL 8: FFA umgekehrt, P8 gewinnt =====
        // Joker: P8 doppelt + Tausch P8<->P1 -> P8: Platz 8 (1 Punkt) verdoppelt = 2, P1: Platz 1 = 8
        $this->client->request('GET', '/game/start/' . $gameIds[7]);
        $this->client->request('POST', '/game/results/' . $gameIds[7], ['positions' => $positions]);

        $this->assertGameFinalPoints($gameIds[7], [
            $p[0] => 8, $p[1] => 2, $p[2] => 3, $p[3] => 4,
            $p[4] => 5, $p[5] => 6, $p[6] => 7, $p[7] => 2,
        ], 'Spiel 8 (FFA umgekehrt mit Doppelt+Tausch P8/P1)');
        $this->assertInvariant($olympixId);

        // ===== ENDSTAND: fixe Beiträge aus Spiel 1-5, 7, 8 + Turnier =====
        $fixedTotals = [55, 47, 45, 48, 46, 37, 45, 28];

        $this->entityManager->clear();
        $finalOlympix = $this->entityManager->getRepository(Olympix::class)->find($olympixId);
        foreach ($finalOlympix->getPlayers() as $player) {
            $i = array_search($player->getId(), $p, true);
            $expected = $fixedTotals[$i] + $tournamentFinals[$player->getId()];
            $this->assertSame(
                $expected,
                $player->getTotalPoints(),
                sprintf('Endstand von Spieler %d (Index %d) muss %d sein', $player->getId(), $i, $expected)
            );
        }

        // ===== ALLE 16 JOKER SIND ANGEWENDET =====
        $jokers = $this->entityManager->getRepository(Joker::class)->findAll();
        $this->assertCount(16, $jokers);
        foreach ($jokers as $joker) {
            $this->assertTrue($joker->isIsUsed(), 'Nach der Olympix muss JEDER Joker angewendet sein');
        }
        foreach ($finalOlympix->getPlayers() as $player) {
            $this->assertTrue($player->isJokerDoubleUsed());
            $this->assertTrue($player->isJokerSwapUsed());
        }

        // Alle 8 Spiele abgeschlossen
        foreach ($finalOlympix->getGames() as $game) {
            $this->assertTrue($game->isCompleted(), 'Spiel "' . $game->getName() . '" muss abgeschlossen sein');
        }
    }

    /**
     * Prüft die finalPoints aller Spieler für ein Spiel exakt.
     *
     * @param array<int, int> $expected playerId => finalPoints
     */
    private function assertGameFinalPoints(int $gameId, array $expected, string $label): void
    {
        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($gameId);

        $this->assertTrue($game->isCompleted(), $label . ': Spiel muss abgeschlossen sein');

        $actual = [];
        foreach ($game->getGameResults() as $result) {
            $actual[$result->getPlayer()->getId()] = $result->getFinalPoints();
        }

        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual, $label . ': finalPoints stimmen nicht');
    }

    /**
     * Invariante: Gesamtpunkte == Summe aller finalPoints — für JEDEN Spieler.
     */
    private function assertInvariant(int $olympixId): void
    {
        $this->entityManager->clear();
        $olympix = $this->entityManager->getRepository(Olympix::class)->find($olympixId);

        foreach ($olympix->getPlayers() as $player) {
            $sum = 0;
            foreach ($player->getGameResults() as $result) {
                $sum += $result->getFinalPoints();
            }
            $this->assertSame(
                $sum,
                $player->getTotalPoints(),
                sprintf('Invariante verletzt für Spieler %d: Gesamt %d != Summe %d', $player->getId(), $player->getTotalPoints(), $sum)
            );
        }
    }

    private function loadPlayers(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Player::class)->findBy(['id' => $this->playerIds]);
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
