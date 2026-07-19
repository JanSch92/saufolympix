<?php

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\Olympix;
use App\Entity\Player;
use App\Entity\StopwatchAttempt;
use App\Service\JokerApplicationService;
use App\Service\StopwatchEvaluationService;
use App\Tests\Unit\EntityIdTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StopwatchEvaluationServiceTest extends TestCase
{
    use EntityIdTrait;

    private function createService(?EntityManagerInterface $em = null): StopwatchEvaluationService
    {
        $em ??= $this->createMock(EntityManagerInterface::class);

        $jokerService = $this->createMock(JokerApplicationService::class);
        $jokerService->method('applyJokersForGame')->willReturn([]);

        return new StopwatchEvaluationService($em, $jokerService);
    }

    private function createAttempt(int $playerId, string $elapsed, ?\DateTime $createdAt = null): StopwatchAttempt
    {
        $player = new Player();
        $player->setName('Spieler ' . $playerId);
        $this->setEntityId($player, $playerId);

        $attempt = new StopwatchAttempt();
        $attempt->setPlayer($player);
        $attempt->setElapsedSeconds($elapsed);
        if ($createdAt) {
            $attempt->setCreatedAt($createdAt);
        }

        return $attempt;
    }

    public function testRankAttemptsOrdersByDeviation(): void
    {
        $service = $this->createService();

        $a = $this->createAttempt(1, '22.00'); // Abweichung 1.14
        $b = $this->createAttempt(2, '20.90'); // Abweichung 0.04
        $c = $this->createAttempt(3, '15.00'); // Abweichung 5.86

        $ranked = $service->rankAttempts([$a, $b, $c], 20.86);

        $this->assertSame([$b, $a, $c], $ranked);
    }

    public function testRankAttemptsTieBrokenByEarlierSubmission(): void
    {
        $service = $this->createService();

        $later = $this->createAttempt(1, '21.00', new \DateTime('2026-07-18 20:00:10'));
        $earlier = $this->createAttempt(2, '19.00', new \DateTime('2026-07-18 20:00:05'));

        // Beide Abweichung 1.00 von 20.00
        $ranked = $service->rankAttempts([$later, $earlier], 20.00);

        $this->assertSame([$earlier, $later], $ranked);
    }

    public function testCalculatePointsDescending(): void
    {
        $service = $this->createService();

        $first = $this->createAttempt(11, '20.00');
        $second = $this->createAttempt(22, '21.00');
        $third = $this->createAttempt(33, '25.00');

        $points = $service->calculatePoints([$first, $second, $third]);

        $this->assertSame(3, $points[11]);
        $this->assertSame(2, $points[22]);
        $this->assertSame(1, $points[33]);
    }

    public function testRandomTargetStaysInRange(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $target = StopwatchEvaluationService::randomTarget();

            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $target);
            $this->assertGreaterThanOrEqual(5.0, (float) $target);
            $this->assertLessThanOrEqual(60.0, (float) $target);
        }
    }

    public function testEvaluateCreatesResultsAndCompletesGame(): void
    {
        $olympix = new Olympix();
        $olympix->setName('Test');

        $game = new Game();
        $game->setName('Stoppuhr');
        $game->setGameType('stopwatch');
        $game->setStatus('active');
        $game->setStopwatchTarget('20.00');
        $game->setOlympix($olympix);

        $players = [];
        foreach ([1 => '20.10', 2 => '25.00', 3 => '19.00'] as $id => $elapsed) {
            $player = new Player();
            $player->setName('P' . $id);
            $this->setEntityId($player, $id);
            $olympix->addPlayer($player);
            $players[$id] = $player;

            $attempt = new StopwatchAttempt();
            $attempt->setPlayer($player);
            $attempt->setElapsedSeconds($elapsed);
            $game->addStopwatchAttempt($attempt);
        }

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $service = $this->createService($em);
        $service->evaluate($game);

        $this->assertTrue($game->isCompleted());

        $results = array_values(array_filter($persisted, fn ($e) => $e instanceof GameResult));
        $this->assertCount(3, $results);

        // Platz 1: Spieler 1 (Abweichung 0.10), 3 Punkte
        $this->assertSame(1, $results[0]->getPlayer()->getId());
        $this->assertSame(1, $results[0]->getPosition());
        $this->assertSame(3, $results[0]->getPoints());

        // Platz 2: Spieler 3 (Abweichung 1.00), 2 Punkte
        $this->assertSame(3, $results[1]->getPlayer()->getId());
        $this->assertSame(2, $results[1]->getPoints());

        // Platz 3: Spieler 2 (Abweichung 5.00), 1 Punkt
        $this->assertSame(2, $results[2]->getPlayer()->getId());
        $this->assertSame(1, $results[2]->getPoints());
    }

    public function testEvaluateIsIdempotentForCompletedGame(): void
    {
        $game = new Game();
        $game->setGameType('stopwatch');
        $game->setStatus('completed');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $service = $this->createService($em);
        $messages = $service->evaluate($game);

        $this->assertSame([], $messages);
    }
}
