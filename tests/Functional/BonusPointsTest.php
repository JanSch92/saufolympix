<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Olympix;
use App\Entity\Player;

/**
 * Admin-Extrapunkte: dokumentierte Sonderpunkte pro Spiel und Spieler.
 * Sie zählen in die Endpunkte, die Invariante bleibt erhalten, und alles
 * ist auf der Detailseite nachvollziehbar.
 */
class BonusPointsTest extends FunctionalTestCase
{
    private function playCompletedFfa(Olympix $olympix, array $players): Game
    {
        $game = $this->createGame($olympix, 'free_for_all');
        $this->client->request('GET', '/game/start/' . $game->getId());
        $positions = [];
        foreach ($players as $i => $player) {
            $positions[$player->getId()] = $i + 1;
        }
        $this->client->request('POST', '/game/results/' . $game->getId(), ['positions' => $positions]);

        return $game;
    }

    public function testAdminCanSetBonusPointsWithReason(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->playCompletedFfa($olympix, $players);

        // +2 Extrapunkte für Spieler 3 (Platz 3, 1 Basispunkt)
        $this->client->request('POST', '/game/bonus/' . $game->getId(), [
            'player_id' => $players[2]->getId(),
            'bonus_points' => '2',
            'bonus_reason' => 'Bester Trinkspruch des Abends',
        ]);

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(Player::class)->find($players[2]->getId());
        $this->assertSame(3, $player->getTotalPoints(), '1 Basispunkt + 2 Extrapunkte');

        // Invariante: Gesamt == Summe finalPoints — Extrapunkte inklusive
        $sum = 0;
        foreach ($player->getGameResults() as $result) {
            $sum += $result->getFinalPoints();
        }
        $this->assertSame($sum, $player->getTotalPoints());

        // Auf der Detailseite komplett dokumentiert
        $this->client->request('GET', '/game/details/' . $game->getId());
        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Extra: +2', $html);
        $this->assertStringContainsString('Bester Trinkspruch des Abends', $html);
    }

    public function testBonusZeroRemovesBonus(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->playCompletedFfa($olympix, $players);

        $this->client->request('POST', '/game/bonus/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'bonus_points' => '5',
            'bonus_reason' => 'Test',
        ]);
        $this->client->request('POST', '/game/bonus/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'bonus_points' => '0',
            'bonus_reason' => '',
        ]);

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertSame(2, $player->getTotalPoints(), 'Extrapunkte mit 0 wieder entfernt');
    }

    public function testBonusRequiresReasonAndIntegerInRange(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->playCompletedFfa($olympix, $players);

        // Ohne Begründung, keine ganze Zahl, außerhalb des Bereichs: alles abgelehnt
        foreach ([
            ['bonus_points' => '3', 'bonus_reason' => ''],
            ['bonus_points' => '2.5', 'bonus_reason' => 'Grund'],
            ['bonus_points' => '101', 'bonus_reason' => 'Grund'],
            ['bonus_points' => '-101', 'bonus_reason' => 'Grund'],
            ['bonus_points' => 'abc', 'bonus_reason' => 'Grund'],
        ] as $invalid) {
            $this->client->request('POST', '/game/bonus/' . $game->getId(), array_merge(
                ['player_id' => $players[0]->getId()],
                $invalid
            ));
        }

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertSame(2, $player->getTotalPoints(), 'Ungültige Extrapunkte dürfen nichts ändern');
    }

    public function testBonusOnlyForCompletedGames(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all'); // pending

        $this->client->request('POST', '/game/bonus/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'bonus_points' => '3',
            'bonus_reason' => 'Zu früh',
        ]);

        $this->entityManager->clear();
        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertSame(0, $player->getTotalPoints(), 'Extrapunkte nur für abgeschlossene Spiele');
    }

    public function testBonusStaysWithPlayerAndCombinesWithDouble(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);

        // Spieler 1 verdoppelt auf dem Spiel
        $game = $this->createGame($olympix, 'free_for_all');
        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $game->getId(),
        ]);
        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [$players[0]->getId() => 1, $players[1]->getId() => 2],
        ]);

        // Extrapunkte oben drauf: (2 x 2) + 1 = 5
        $this->client->request('POST', '/game/bonus/' . $game->getId(), [
            'player_id' => $players[0]->getId(),
            'bonus_points' => '1',
            'bonus_reason' => 'Fairplay',
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());
        foreach ($game->getGameResults() as $result) {
            if ($result->getPlayer()->getId() === $players[0]->getId()) {
                $this->assertSame(5, $result->getFinalPoints(), 'Verdopplung x2 wirkt auf Basispunkte, Extrapunkte kommen oben drauf');
            }
        }

        $player = $this->entityManager->getRepository(Player::class)->find($players[0]->getId());
        $this->assertSame(5, $player->getTotalPoints());
    }
}
