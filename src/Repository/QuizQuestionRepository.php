<?php

namespace App\Repository;

use App\Entity\QuizQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizQuestion>
 */
class QuizQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizQuestion::class);
    }

    public function save(QuizQuestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QuizQuestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByGameOrdered(int $gameId): array
    {
        return $this->createQueryBuilder('qq')
            ->andWhere('qq.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('qq.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextOrderPosition(int $gameId): int
    {
        $result = $this->createQueryBuilder('qq')
            ->select('MAX(qq.orderPosition)')
            ->andWhere('qq.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}