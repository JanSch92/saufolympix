<?php

namespace App\Tests\Functional;

use App\Entity\Joker;
use App\Entity\Player;

/**
 * Schutz vor Joker-Doppelnutzung: Jeder Spieler hat GENAU EINEN
 * Doppelt-Joker und GENAU EINEN Tausch-Joker pro Olympix, pro Spiel
 * ist nur EIN Tausch erlaubt, und Joker gehen nur auf wartende Spiele.
 */
class JokerProtectionTest extends FunctionalTestCase
{
    public function testDoubleJokerCannotBeUsedTwice(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game1 = $this->createGame($olympix, 'free_for_all', 'Spiel 1');
        $game2 = $this->createGame($olympix, 'free_for_all', 'Spiel 2');

        $url = '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId();

        // Erste Nutzung: wird vorgemerkt
        $this->client->request('POST', $url, ['selected_game_id' => $game1->getId()]);

        // Zweite Nutzung (anderes Spiel): MUSS abgelehnt werden
        $this->client->request('POST', $url, ['selected_game_id' => $game2->getId()]);

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findBy([
            'player' => $players[0]->getId(),
            'jokerType' => 'double',
        ]);
        $this->assertCount(1, $jokers, 'Doppelt-Joker darf nur EINMAL vorgemerkt werden');

        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertFalse($player->hasJokerDoubleAvailable());
    }

    public function testSwapJokerCannotBeUsedTwice(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game1 = $this->createGame($olympix, 'free_for_all', 'Spiel 1');
        $game2 = $this->createGame($olympix, 'free_for_all', 'Spiel 2');

        $url = '/player-joker-swap/' . $olympix->getId() . '/' . $players[0]->getId();

        $this->client->request('POST', $url, [
            'target_player_id' => $players[1]->getId(),
            'selected_game_id' => $game1->getId(),
        ]);

        // Zweiter Tausch desselben Spielers (anderes Spiel, anderes Ziel): MUSS abgelehnt werden
        $this->client->request('POST', $url, [
            'target_player_id' => $players[2]->getId(),
            'selected_game_id' => $game2->getId(),
        ]);

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findBy([
            'player' => $players[0]->getId(),
            'jokerType' => 'swap',
        ]);
        $this->assertCount(1, $jokers, 'Tausch-Joker darf nur EINMAL vorgemerkt werden');
    }

    public function testOnlyOneSwapPerGame(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all');

        // Spieler 1 legt den Tausch auf das Spiel
        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'target_player_id' => $players[1]->getId(),
            'selected_game_id' => $game->getId(),
        ]);

        // Spieler 2 versucht einen ZWEITEN Tausch auf dasselbe Spiel: MUSS abgelehnt werden
        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[1]->getId(), [
            'target_player_id' => $players[2]->getId(),
            'selected_game_id' => $game->getId(),
        ]);

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findBy([
            'game' => $game->getId(),
            'jokerType' => 'swap',
        ]);
        $this->assertCount(1, $jokers, 'Pro Spiel ist nur EIN Tausch-Joker erlaubt');

        // WICHTIG: Spieler 2 hat seinen Tausch dabei NICHT verloren
        $player2 = $this->entityManager->getRepository(Player::class)->find($players[1]->getId());
        $this->assertTrue(
            $player2->hasJokerSwapAvailable(),
            'Abgelehnter Tausch darf den Joker des Spielers nicht verbrauchen'
        );
    }

    public function testJokersOnlyOnPendingGames(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $active = $this->createGame($olympix, 'free_for_all', 'Aktiv', 'active');
        $this->createGame($olympix, 'free_for_all', 'Wartend'); // damit pendingGames nicht leer ist

        // Doppelt-Joker auf ein AKTIVES Spiel: MUSS abgelehnt werden, Joker bleibt erhalten
        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $active->getId(),
        ]);

        // Tausch-Joker auf ein AKTIVES Spiel: MUSS abgelehnt werden, Joker bleibt erhalten
        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'target_player_id' => $players[1]->getId(),
            'selected_game_id' => $active->getId(),
        ]);

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findAll();
        $this->assertCount(0, $jokers, 'Auf aktive Spiele dürfen keine Joker gelegt werden');

        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertTrue($player->hasJokerDoubleAvailable(), 'Abgelehnter Joker darf nicht verbraucht sein');
        $this->assertTrue($player->hasJokerSwapAvailable(), 'Abgelehnter Joker darf nicht verbraucht sein');
    }

    public function testAdminRouteAlsoEnforcesOneSwapPerGame(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 4);
        $game = $this->createGame($olympix, 'free_for_all');

        // Spieler 1 legt den Tausch über die SPIELER-Route
        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'target_player_id' => $players[1]->getId(),
            'selected_game_id' => $game->getId(),
        ]);

        // Spieler 3 versucht über die ADMIN-Route einen ZWEITEN Tausch (anderes Ziel)
        $this->client->request('POST', '/joker/swap/' . $players[2]->getId() . '/' . $game->getId(), [
            'target_player_id' => $players[3]->getId(),
        ]);

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findBy([
            'game' => $game->getId(),
            'jokerType' => 'swap',
        ]);
        $this->assertCount(1, $jokers, 'Auch über die Admin-Verwaltung: nur EIN Tausch pro Spiel');

        $player3 = $this->entityManager->getRepository(Player::class)->find($players[2]->getId());
        $this->assertTrue($player3->hasJokerSwapAvailable(), 'Abgelehnter Admin-Tausch darf den Joker nicht verbrauchen');
    }

    public function testAdminRouteRejectsJokersOnCompletedGames(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all', 'Fertig', 'completed');

        $this->client->request('GET', '/joker/double/' . $players[0]->getId() . '/' . $game->getId());
        $this->client->request('POST', '/joker/swap/' . $players[0]->getId() . '/' . $game->getId(), [
            'target_player_id' => $players[1]->getId(),
        ]);

        $this->entityManager->clear();
        $this->assertCount(
            0,
            $this->entityManager->getRepository(Joker::class)->findAll(),
            'Auf abgeschlossene Spiele darf kein Joker mehr gelegt werden'
        );

        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertTrue($player->hasJokerDoubleAvailable());
        $this->assertTrue($player->hasJokerSwapAvailable());
    }

    public function testMultipleDoubleJokersOnSameGameAreAllowed(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all');

        // ALLE Spieler dürfen auf DASSELBE Spiel verdoppeln (kein Pro-Spiel-Limit für Doppelt)
        foreach ($players as $player) {
            $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $player->getId(), [
                'selected_game_id' => $game->getId(),
            ]);
        }

        $this->entityManager->clear();
        $jokers = $this->entityManager->getRepository(Joker::class)->findBy([
            'game' => $game->getId(),
            'jokerType' => 'double',
        ]);
        $this->assertCount(3, $jokers, 'Doppelte Punkte darf JEDER Spieler auf dasselbe Spiel legen');

        // Und beim Abschluss werden ALLE Verdopplungen angewendet
        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
                $players[2]->getId() => 3,
            ],
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(\App\Entity\Game::class)->find($game->getId());
        $finals = [];
        foreach ($game->getGameResults() as $result) {
            $this->assertTrue($result->isJokerDoubleApplied());
            $finals[$result->getPlayer()->getId()] = $result->getFinalPoints();
        }
        $this->assertSame(6, $finals[$players[0]->getId()], '3 x 2');
        $this->assertSame(4, $finals[$players[1]->getId()], '2 x 2');
        $this->assertSame(2, $finals[$players[2]->getId()], '1 x 2');
    }

    public function testSwapWithYourselfIsRejected(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'target_player_id' => $players[0]->getId(),
            'selected_game_id' => $game->getId(),
        ]);

        $this->entityManager->clear();
        $this->assertCount(0, $this->entityManager->getRepository(Joker::class)->findAll());

        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertTrue($player->hasJokerSwapAvailable());
    }

    public function testSwapNeverDuplicatesPointsAndDoesNotCarryTheDouble(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all');

        // Spieler 1 verdoppelt auf dem Spiel, Spieler 2 tauscht mit Spieler 1
        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $game->getId(),
        ]);
        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[1]->getId(), [
            'target_player_id' => $players[0]->getId(),
            'selected_game_id' => $game->getId(),
        ]);

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
                $players[2]->getId() => 3,
            ],
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(\App\Entity\Game::class)->find($game->getId());

        $byPlayer = [];
        $basePointsSum = 0;
        foreach ($game->getGameResults() as $result) {
            $byPlayer[$result->getPlayer()->getId()] = $result;
            $basePointsSum += $result->getPoints();
        }

        // Der Tausch ist ein ECHTER Tausch: Basispunkte bleiben 3+2+1 = 6, nichts wird dupliziert
        $this->assertSame(6, $basePointsSum, 'Tausch darf Punkte niemals vervielfachen');

        // Spieler 2 hat Platz 1 (3 Punkte) ertauscht — OHNE Verdopplung (der Doppelt-Joker gehört Spieler 1!)
        $p2 = $byPlayer[$players[1]->getId()];
        $this->assertSame(1, $p2->getPosition());
        $this->assertSame(3, $p2->getPoints());
        $this->assertFalse($p2->isJokerDoubleApplied(), 'Der Tausch darf die Verdopplung NICHT mitnehmen');
        $this->assertSame(3, $p2->getFinalPoints());

        // Spieler 1 behält seine Verdopplung auf den ertauschten Platz 2 (2 x 2 = 4)
        $p1 = $byPlayer[$players[0]->getId()];
        $this->assertSame(2, $p1->getPosition());
        $this->assertSame(2, $p1->getPoints());
        $this->assertTrue($p1->isJokerDoubleApplied());
        $this->assertSame(4, $p1->getFinalPoints());

        // Unbeteiligter Spieler 3 bleibt unberührt
        $this->assertSame(1, $byPlayer[$players[2]->getId()]->getFinalPoints());

        // Gesamtpunkte korrekt, keine Doppelzählung
        foreach ($game->getOlympix()->getPlayers() as $player) {
            $sum = 0;
            foreach ($player->getGameResults() as $result) {
                $sum += $result->getFinalPoints();
            }
            $this->assertSame($sum, $player->getTotalPoints());
        }
    }

    public function testAppliedJokerIsNeverAppliedAgain(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game1 = $this->createGame($olympix, 'free_for_all', 'Spiel 1');
        $game2 = $this->createGame($olympix, 'free_for_all', 'Spiel 2');

        // Doppelt-Joker auf Spiel 1 vormerken und Spiel 1 spielen
        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $game1->getId(),
        ]);
        $this->client->request('GET', '/game/start/' . $game1->getId());
        $this->client->request('POST', '/game/results/' . $game1->getId(), [
            'positions' => [$players[0]->getId() => 1, $players[1]->getId() => 2],
        ]);

        // Spiel 2 spielen — der bereits angewendete Joker darf NICHT nochmal wirken
        $this->client->request('GET', '/game/start/' . $game2->getId());
        $this->client->request('POST', '/game/results/' . $game2->getId(), [
            'positions' => [$players[0]->getId() => 1, $players[1]->getId() => 2],
        ]);

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());

        // Spiel 1: 2 Punkte verdoppelt = 4; Spiel 2: 2 Punkte OHNE Verdopplung -> 6 gesamt
        $this->assertSame(6, $player->getTotalPoints(), 'Ein angewendeter Joker darf nie doppelt wirken');

        $jokers = $this->entityManager->getRepository(Joker::class)->findAll();
        $this->assertCount(1, $jokers);
        $this->assertTrue($jokers[0]->isIsUsed());
    }
}
