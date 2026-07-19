<?php

namespace App\Repository;

use App\Entity\StopwatchAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StopwatchAttempt>
 */
class StopwatchAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StopwatchAttempt::class);
    }

    public function findByPlayerAndGame(int $playerId, int $gameId): ?StopwatchAttempt
    {
        return $this->findOneBy(['player' => $playerId, 'game' => $gameId]);
    }

    /**
     * @return StopwatchAttempt[]
     */
    public function findByGame(int $gameId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByGame(int $gameId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
