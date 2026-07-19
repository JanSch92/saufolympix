<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\StopwatchAttempt;
use PHPUnit\Framework\TestCase;

class StopwatchAttemptTest extends TestCase
{
    public function testDeviationIsAbsolute(): void
    {
        $game = new Game();
        $game->setGameType('stopwatch');
        $game->setStopwatchTarget('20.86');

        $under = new StopwatchAttempt();
        $under->setGame($game);
        $under->setElapsedSeconds('19.86');
        $this->assertSame(1.0, $under->getDeviation());

        $over = new StopwatchAttempt();
        $over->setGame($game);
        $over->setElapsedSeconds('22.36');
        $this->assertSame(1.5, $over->getDeviation());

        $exact = new StopwatchAttempt();
        $exact->setGame($game);
        $exact->setElapsedSeconds('20.86');
        $this->assertSame(0.0, $exact->getDeviation());
    }

    public function testDeviationRoundsToTwoDecimals(): void
    {
        $game = new Game();
        $game->setGameType('stopwatch');
        $game->setStopwatchTarget('10.00');

        $attempt = new StopwatchAttempt();
        $attempt->setGame($game);
        $attempt->setElapsedSeconds('10.556');

        $this->assertSame(0.56, $attempt->getDeviation());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $attempt = new StopwatchAttempt();

        $this->assertInstanceOf(\DateTimeInterface::class, $attempt->getCreatedAt());
    }

    public function testPlayerAndGameAccessors(): void
    {
        $game = new Game();
        $player = new Player();
        $player->setName('Timo');

        $attempt = new StopwatchAttempt();
        $attempt->setGame($game);
        $attempt->setPlayer($player);

        $this->assertSame($game, $attempt->getGame());
        $this->assertSame($player, $attempt->getPlayer());
    }
}
