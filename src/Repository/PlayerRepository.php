<?php

namespace App\Repository;

use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    public function save(Player $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Player $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByOlympixOrderedByPoints(int $olympixId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('p.totalPoints', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLowestScoringPlayer(int $olympixId): ?Player
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('p.totalPoints', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}