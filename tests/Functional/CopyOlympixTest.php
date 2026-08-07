<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Olympix;

class CopyOlympixTest extends FunctionalTestCase
{
    public function testCopyDuplicatesPlayersAndGamesWithFreshState(): void
    {
        $olympix = $this->createOlympix('Original');
        $players = $this->createPlayers($olympix, 3);
        $quiz = $this->createGame($olympix, 'quiz', 'Wissensquiz');
        $stopwatch = $this->createGame($olympix, 'stopwatch', 'Blind Stoppen');

        // Original durchspielen: Quiz starten (generiert Fragen), Punkte + Joker verbrauchen
        $this->client->request('GET', '/game/start/' . $quiz->getId());
        $players[0]->setTotalPoints(42);
        $players[0]->setJokerDoubleUsed(true);
        $this->entityManager->flush();

        // Kopieren
        $this->client->request('POST', '/olympix/copy/' . $olympix->getId());

        $copy = $this->entityManager->getRepository(Olympix::class)->findOneBy(['name' => 'Original (Kopie)']);
        $this->assertNotNull($copy, 'Kopie muss angelegt werden');
        $this->assertResponseRedirects('/gameadmin/' . $copy->getId());

        $this->entityManager->clear();
        $copy = $this->entityManager->getRepository(Olympix::class)->find($copy->getId());

        // Spieler: gleiche Namen, aber frische Punkte und Joker
        $this->assertCount(3, $copy->getPlayers());
        $names = [];
        foreach ($copy->getPlayers() as $player) {
            $names[] = $player->getName();
            $this->assertSame(0, $player->getTotalPoints(), 'Kopierte Spieler starten bei 0 Punkten');
            $this->assertTrue($player->hasJokerDoubleAvailable(), 'Kopierte Spieler haben frische Joker');
            $this->assertTrue($player->hasJokerSwapAvailable());
        }
        sort($names);
        $this->assertSame(['Spieler1', 'Spieler2', 'Spieler3'], $names);

        // Spiele: gleiche Typen/Namen, aber wartend und ohne Fragen/Ergebnisse
        $this->assertCount(2, $copy->getGames());
        foreach ($copy->getGames() as $game) {
            $this->assertSame('pending', $game->getStatus(), 'Kopierte Spiele sind wieder wartend');
            $this->assertCount(0, $game->getQuizQuestions(), 'Keine Fragen mitkopiert (werden beim Start neu generiert)');
            $this->assertCount(0, $game->getGameResults());
            $this->assertNull($game->getStopwatchTarget());
        }

        // Original unangetastet
        $original = $this->entityManager->getRepository(Olympix::class)->find($olympix->getId());
        $this->assertSame('active', $original->getGames()->filter(
            fn (Game $g) => $g->getGameType() === 'quiz'
        )->first()->getStatus(), 'Original-Quiz bleibt aktiv');

        foreach ($original->getPlayers() as $player) {
            if ($player->getName() === 'Spieler1') {
                $this->assertSame(42, $player->getTotalPoints(), 'Original-Punkte bleiben erhalten');
            }
        }
    }
}
