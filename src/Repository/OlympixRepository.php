<?php

namespace App\Repository;

use App\Entity\Olympix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Olympix>
 */
class OlympixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Olympix::class);
    }

    public function save(Olympix $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Olympix $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveOlympix(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithPlayersAndGames(int $id): ?Olympix
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.players', 'p')
            ->leftJoin('o.games', 'g')
            ->addSelect('p')
            ->addSelect('g')
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findWithFullData(int $id): ?Olympix
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.players', 'p')
            ->leftJoin('o.games', 'g')
            ->leftJoin('g.gameResults', 'gr')
            ->leftJoin('g.quizQuestions', 'qq')
            ->leftJoin('g.tournament', 't')
            ->addSelect('p')
            ->addSelect('g')
            ->addSelect('gr')
            ->addSelect('qq')
            ->addSelect('t')
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}