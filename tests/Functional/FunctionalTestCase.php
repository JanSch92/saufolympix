<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Olympix;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Basis für funktionale Tests: SQLite-Test-DB mit frischem Schema pro Test.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->entityManager, $this->client);
    }

    protected function createOlympix(string $name = 'Test-Olympix'): Olympix
    {
        $olympix = new Olympix();
        $olympix->setName($name);
        $this->entityManager->persist($olympix);
        $this->entityManager->flush();

        return $olympix;
    }

    /**
     * @return Player[]
     */
    protected function createPlayers(Olympix $olympix, int $count): array
    {
        $players = [];
        for ($i = 1; $i <= $count; $i++) {
            $player = new Player();
            $player->setName('Spieler' . $i);
            $player->setOlympix($olympix);
            $olympix->addPlayer($player);
            $this->entityManager->persist($player);
            $players[] = $player;
        }
        $this->entityManager->flush();

        return $players;
    }

    protected function createGame(Olympix $olympix, string $gameType, string $name = 'Testspiel', string $status = 'pending'): Game
    {
        $game = new Game();
        $game->setName($name);
        $game->setGameType($gameType);
        $game->setOlympix($olympix);
        $olympix->addGame($game);
        $game->setStatus($status);
        $game->setOrderPosition(1);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }
}
