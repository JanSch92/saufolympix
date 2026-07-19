<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Joker;

class AdminJokerFlowTest extends FunctionalTestCase
{
    public function testAdminDoubleJokerRouteCreatesPendingJokerThatGetsApplied(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all');
        $this->client->request('GET', '/game/start/' . $game->getId());

        // Admin aktiviert Doppelt-Joker für Spieler 2 über die Admin-Route
        $this->client->request('GET', '/joker/double/' . $players[1]->getId() . '/' . $game->getId());
        $this->assertResponseRedirects('/gameadmin/' . $olympix->getId());

        // Joker muss als VORGEMERKT (isUsed=false) angelegt sein, sonst wird er nie angewendet
        $joker = $this->entityManager->getRepository(Joker::class)->findOneBy([
            'player' => $players[1]->getId(),
            'jokerType' => 'double',
        ]);
        $this->assertNotNull($joker);
        $this->assertFalse($joker->isIsUsed(), 'Admin-aktivierter Joker muss vorgemerkt sein (isUsed=false), damit er bei Spielende angewendet wird');

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

        foreach ($game->getGameResults() as $result) {
            if ($result->getPlayer()->getId() === $players[1]->getId()) {
                $this->assertTrue($result->isJokerDoubleApplied(), 'Admin-aktivierter Doppelt-Joker muss bei Spielende angewendet werden');
                $this->assertSame(6, $result->getFinalPoints(), '3 Punkte x2 = 6');
            }
        }
    }

    public function testAdminSwapJokerRouteCreatesPendingJokerThatSwapsResults(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all');
        $this->client->request('GET', '/game/start/' . $game->getId());

        // Admin aktiviert Swap-Joker: Spieler 3 tauscht mit Spieler 1
        $this->client->request('POST', '/joker/swap/' . $players[2]->getId() . '/' . $game->getId(), [
            'target_player_id' => $players[0]->getId(),
        ]);

        $joker = $this->entityManager->getRepository(Joker::class)->findOneBy([
            'player' => $players[2]->getId(),
            'jokerType' => 'swap',
        ]);
        $this->assertNotNull($joker);
        $this->assertFalse($joker->isIsUsed(), 'Admin-aktivierter Swap-Joker muss vorgemerkt sein');

        // Ergebnisse: Spieler 1 Erster (3P), Spieler 3 Letzter (1P) -> Tausch dreht das um
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
                $players[2]->getId() => 3,
            ],
        ]);

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(Game::class)->find($game->getId());

        $pointsByPlayer = [];
        foreach ($game->getGameResults() as $result) {
            $pointsByPlayer[$result->getPlayer()->getId()] = $result->getPoints();
        }

        $this->assertSame(1, $pointsByPlayer[$players[0]->getId()], 'Spieler 1 hat nach dem Tausch die Letzter-Punkte');
        $this->assertSame(3, $pointsByPlayer[$players[2]->getId()], 'Spieler 3 hat nach dem Tausch die Sieger-Punkte');
    }
}
