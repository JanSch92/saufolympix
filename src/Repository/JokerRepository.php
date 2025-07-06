<?php

namespace App\Repository;

use App\Entity\Joker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Joker>
 */
class JokerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Joker::class);
    }

    public function save(Joker $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Joker $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveJokersByGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->addSelect('p')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('isUsed', true)
            ->getQuery()
            ->getResult();
    }

    public function findDoubleJokersByGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->addSelect('p')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'double')
            ->setParameter('isUsed', true)
            ->getQuery()
            ->getResult();
    }

    public function findSwapJokersByGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('p')
            ->addSelect('tp')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', true)
            ->getQuery()
            ->getResult();
    }

    public function hasSwapJokerOnPlayer(int $playerId, int $gameId): bool
    {
        $result = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.targetPlayer = :playerId')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('playerId', $playerId)
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
}