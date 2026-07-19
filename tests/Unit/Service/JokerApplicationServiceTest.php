<?php

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\Joker;
use App\Entity\Player;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use App\Service\JokerApplicationService;
use App\Tests\Unit\EntityIdTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class JokerApplicationServiceTest extends TestCase
{
    use EntityIdTrait;

    private function createPlayer(int $id, string $name): Player
    {
        $player = new Player();
        $player->setName($name);
        $this->setEntityId($player, $id);

        return $player;
    }

    private function createResult(Game $game, Player $player, int $position, int $points): GameResult
    {
        $result = new GameResult();
        $result->setGame($game);
        $result->setPlayer($player);
        $result->setPosition($position);
        $result->setPoints($points);

        return $result;
    }

    public function testDoubleJokerIsAppliedToParticipant(): void
    {
        $game = new Game();
        $game->setName('Testspiel');
        $this->setEntityId($game, 1);

        $player = $this->createPlayer(10, 'Anna');
        $result = $this->createResult($game, $player, 1, 5);

        $joker = new Joker();
        $joker->setPlayer($player);
        $joker->setJokerType('double');
        $joker->setIsUsed(false);

        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findBy')->willReturnCallback(
            fn (array $criteria) => $criteria['jokerType'] === 'double' ? [$joker] : []
        );

        $resultRepo = $this->createMock(GameResultRepository::class);
        $resultRepo->method('findByPlayerAndGame')->willReturn($result);

        $service = new JokerApplicationService(
            $this->createMock(EntityManagerInterface::class),
            $jokerRepo,
            $resultRepo
        );

        $messages = $service->applyJokersForGame($game);

        $this->assertTrue($result->isJokerDoubleApplied());
        $this->assertSame(10, $result->getFinalPoints());
        $this->assertTrue($joker->isIsUsed());
        $this->assertCount(1, $messages);
        $this->assertSame('info', $messages[0]['type']);
    }

    public function testDoubleJokerIsWastedWithoutResult(): void
    {
        $game = new Game();
        $game->setName('Testspiel');
        $this->setEntityId($game, 1);

        $player = $this->createPlayer(10, 'Anna');

        $joker = new Joker();
        $joker->setPlayer($player);
        $joker->setJokerType('double');
        $joker->setIsUsed(false);

        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findBy')->willReturnCallback(
            fn (array $criteria) => $criteria['jokerType'] === 'double' ? [$joker] : []
        );

        $resultRepo = $this->createMock(GameResultRepository::class);
        $resultRepo->method('findByPlayerAndGame')->willReturn(null);

        $service = new JokerApplicationService(
            $this->createMock(EntityManagerInterface::class),
            $jokerRepo,
            $resultRepo
        );

        $messages = $service->applyJokersForGame($game);

        $this->assertTrue($joker->isIsUsed(), 'Verfallener Joker muss trotzdem als benutzt markiert werden');
        $this->assertSame('warning', $messages[0]['type']);
    }

    public function testSwapJokerSwapsPositionsAndPoints(): void
    {
        $game = new Game();
        $game->setName('Testspiel');
        $this->setEntityId($game, 1);

        $source = $this->createPlayer(10, 'Anna');
        $target = $this->createPlayer(20, 'Ben');

        $sourceResult = $this->createResult($game, $source, 3, 2);
        $targetResult = $this->createResult($game, $target, 1, 8);

        $joker = new Joker();
        $joker->setPlayer($source);
        $joker->setTargetPlayer($target);
        $joker->setJokerType('swap');
        $joker->setIsUsed(false);

        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findBy')->willReturnCallback(
            fn (array $criteria) => $criteria['jokerType'] === 'swap' ? [$joker] : []
        );

        $resultRepo = $this->createMock(GameResultRepository::class);
        $resultRepo->method('findByPlayerAndGame')->willReturnCallback(
            fn (int $playerId) => $playerId === 10 ? $sourceResult : $targetResult
        );

        $service = new JokerApplicationService(
            $this->createMock(EntityManagerInterface::class),
            $jokerRepo,
            $resultRepo
        );

        $messages = $service->applyJokersForGame($game);

        $this->assertSame(1, $sourceResult->getPosition());
        $this->assertSame(8, $sourceResult->getPoints());
        $this->assertSame(3, $targetResult->getPosition());
        $this->assertSame(2, $targetResult->getPoints());
        $this->assertTrue($joker->isIsUsed());
        $this->assertSame('info', $messages[0]['type']);
    }

    public function testNoJokersProducesNoMessages(): void
    {
        $game = new Game();
        $this->setEntityId($game, 1);

        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findBy')->willReturn([]);

        $service = new JokerApplicationService(
            $this->createMock(EntityManagerInterface::class),
            $jokerRepo,
            $this->createMock(GameResultRepository::class)
        );

        $this->assertSame([], $service->applyJokersForGame($game));
    }
}
