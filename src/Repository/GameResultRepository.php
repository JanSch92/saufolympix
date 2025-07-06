<?php

namespace App\Repository;

use App\Entity\GameResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameResult>
 */
class GameResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameResult::class);
    }

    public function save(GameResult $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameResult $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByGameOrderedByPosition(int $gameId): array
    {
        return $this->createQueryBuilder('gr')
            ->leftJoin('gr.player', 'p')
            ->addSelect('p')
            ->andWhere('gr.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('gr.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPlayerAndGame(int $playerId, int $gameId): ?GameResult
    {
        return $this->createQueryBuilder('gr')
            ->andWhere('gr.player = :playerId')
            ->andWhere('gr.game = :gameId')
            ->setParameter('playerId', $playerId)
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}