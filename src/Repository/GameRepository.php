<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function save(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByOlympixOrdered(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveGameByOlympix(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextPendingGame(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'pending')
            ->orderBy('g.orderPosition', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextOrderPosition(int $olympixId): int
    {
        $result = $this->createQueryBuilder('g')
            ->select('MAX(g.orderPosition)')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}