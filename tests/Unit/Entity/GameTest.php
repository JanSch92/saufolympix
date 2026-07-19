<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\Olympix;
use App\Entity\Player;
use App\Entity\StopwatchAttempt;
use App\Tests\Unit\EntityIdTrait;
use PHPUnit\Framework\TestCase;

class GameTest extends TestCase
{
    use EntityIdTrait;

    private function createGameWithPlayers(string $gameType, int $playerCount): Game
    {
        $olympix = new Olympix();
        $olympix->setName('Test-Olympix');

        for ($i = 1; $i <= $playerCount; $i++) {
            $player = new Player();
            $player->setName('Spieler ' . $i);
            $olympix->addPlayer($player);
        }

        $game = new Game();
        $game->setName('Testspiel');
        $game->setGameType($gameType);
        $game->setOlympix($olympix);

        return $game;
    }

    public function testNewGameStartsPending(): void
    {
        $game = new Game();

        $this->assertTrue($game->isPending());
        $this->assertTrue($game->canBeStarted());
        $this->assertFalse($game->isActive());
        $this->assertFalse($game->isCompleted());
    }

    public function testGameTypeChecks(): void
    {
        $checks = [
            'quiz' => 'isQuizGame',
            'free_for_all' => 'isFreeForAllGame',
            'split_or_steal' => 'isSplitOrStealGame',
            'gamechanger' => 'isGamechangerGame',
            'stopwatch' => 'isStopwatchGame',
        ];

        foreach ($checks as $type => $method) {
            $game = new Game();
            $game->setGameType($type);
            $this->assertTrue($game->$method(), "$method sollte true sein für $type");
        }

        foreach (['tournament_team', 'tournament_single'] as $type) {
            $game = new Game();
            $game->setGameType($type);
            $this->assertTrue($game->isTournamentGame());
        }
    }

    public function testStopwatchGameTypeLabelAndEmoji(): void
    {
        $game = new Game();
        $game->setGameType('stopwatch');

        $this->assertSame('Stoppuhr', $game->getGameTypeLabel());
        $this->assertSame('⏱️', $game->getGameTypeEmoji());
    }

    public function testEveryGameTypeHasLabelAndEmoji(): void
    {
        $types = ['free_for_all', 'tournament_team', 'tournament_single', 'quiz', 'split_or_steal', 'gamechanger', 'stopwatch'];

        foreach ($types as $type) {
            $game = new Game();
            $game->setGameType($type);
            $this->assertNotSame('Unbekannt', $game->getGameTypeLabel(), "Label fehlt für $type");
            $this->assertNotSame('🎲', $game->getGameTypeEmoji(), "Emoji fehlt für $type");
        }
    }

    public function testStatusTransitions(): void
    {
        $game = new Game();

        $game->setStatus('active');
        $this->assertTrue($game->isActive());
        $this->assertTrue($game->canBeCompleted());
        $this->assertFalse($game->canBeStarted());

        $game->setStatus('completed');
        $this->assertTrue($game->isCompleted());
        $this->assertFalse($game->canBeCompleted());
    }

    public function testStopwatchTargetAccessors(): void
    {
        $game = new Game();
        $this->assertNull($game->getStopwatchTarget());

        $game->setStopwatchTarget('42.13');
        $this->assertSame('42.13', $game->getStopwatchTarget());
    }

    public function testDefaultPointsDistributionFreeForAll(): void
    {
        $game = $this->createGameWithPlayers('free_for_all', 4);

        $this->assertSame([4, 3, 2, 1], $game->getDefaultPointsDistribution());
    }

    public function testDefaultPointsDistributionStopwatchDescending(): void
    {
        $game = $this->createGameWithPlayers('stopwatch', 5);

        $this->assertSame([5, 4, 3, 2, 1], $game->getDefaultPointsDistribution());
    }

    public function testDefaultPointsDistributionTournament(): void
    {
        $game = $this->createGameWithPlayers('tournament_single', 6);

        $this->assertSame([8, 6, 4, 2, 0, 0], $game->getDefaultPointsDistribution());
    }

    public function testCustomPointsDistributionWins(): void
    {
        $game = $this->createGameWithPlayers('free_for_all', 3);
        $game->setPointsDistribution([10, 5, 1]);

        $this->assertSame([10, 5, 1], $game->getDefaultPointsDistribution());
    }

    public function testStopwatchNeedsNoSetup(): void
    {
        $game = $this->createGameWithPlayers('stopwatch', 3);

        $this->assertFalse($game->needsSetup());
        $this->assertNull($game->getSetupUrl());
    }

    public function testStopwatchPlayUrlWhenActive(): void
    {
        $game = $this->createGameWithPlayers('stopwatch', 2);
        $this->setEntityId($game, 7);

        $this->assertNull($game->getPlayUrl());

        $game->setStatus('active');
        $this->assertSame('/stopwatch/manage/7', $game->getPlayUrl());
    }

    public function testQuizPlayUrlPointsToQrPage(): void
    {
        $game = $this->createGameWithPlayers('quiz', 2);
        $this->setEntityId($game, 9);
        $game->setStatus('active');

        $this->assertSame('/quiz/9', $game->getPlayUrl());
    }

    public function testStopwatchProgressPercentage(): void
    {
        $game = $this->createGameWithPlayers('stopwatch', 4);
        $game->setStatus('active');

        $this->assertSame(0.0, (float) $game->getProgressPercentage());

        foreach ([1, 2] as $i) {
            $attempt = new StopwatchAttempt();
            $attempt->setPlayer($game->getOlympix()->getPlayers()->get($i - 1));
            $attempt->setElapsedSeconds('10.00');
            $game->addStopwatchAttempt($attempt);
        }

        $this->assertSame(50.0, (float) $game->getProgressPercentage());

        $game->setStatus('completed');
        $this->assertSame(100, $game->getProgressPercentage());
    }

    public function testCanStartRequiresMinPlayers(): void
    {
        $game = $this->createGameWithPlayers('stopwatch', 1);
        $this->assertFalse($game->canStart());

        $game2 = $this->createGameWithPlayers('stopwatch', 2);
        $this->assertTrue($game2->canStart());
    }
}
