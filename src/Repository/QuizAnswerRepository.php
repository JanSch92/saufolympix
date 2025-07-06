<?php

namespace App\Repository;

use App\Entity\QuizAnswer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAnswer>
 */
class QuizAnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAnswer::class);
    }

    public function save(QuizAnswer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QuizAnswer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByQuestion(int $questionId): array
    {
        return $this->createQueryBuilder('qa')
            ->leftJoin('qa.player', 'p')
            ->addSelect('p')
            ->andWhere('qa.quizQuestion = :questionId')
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getResult();
    }

    public function findByPlayerAndQuestion(int $playerId, int $questionId): ?QuizAnswer
    {
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.player = :playerId')
            ->andWhere('qa.quizQuestion = :questionId')
            ->setParameter('playerId', $playerId)
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}