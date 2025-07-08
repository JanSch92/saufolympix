<?php

namespace App\Repository;

use App\Entity\SplitOrStealMatch;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SplitOrStealMatch>
 */
class SplitOrStealMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SplitOrStealMatch::class);
    }

    public function save(SplitOrStealMatch $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SplitOrStealMatch $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByGameOrderedByCreated(int $gameId): array
    {
        return $this->createQueryBuilder('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->addSelect('p1', 'p2')
            ->andWhere('som.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('som.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPlayer(Player $player): array
    {
        return $this->createQueryBuilder('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->leftJoin('som.game', 'g')
            ->addSelect('p1', 'p2', 'g')
            ->andWhere('som.player1 = :player OR som.player2 = :player')
            ->setParameter('player', $player)
            ->orderBy('som.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveMatchForPlayer(Player $player): ?SplitOrStealMatch
    {
        return $this->createQueryBuilder('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->leftJoin('som.game', 'g')
            ->addSelect('p1', 'p2', 'g')
            ->andWhere('som.player1 = :player OR som.player2 = :player')
            ->andWhere('som.isCompleted = false')
            ->andWhere('g.status = :status')
            ->setParameter('player', $player)
            ->setParameter('status', 'active')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findIncompleteMatchesByGame(int $gameId): array
    {
        return $this->createQueryBuilder('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->addSelect('p1', 'p2')
            ->andWhere('som.game = :gameId')
            ->andWhere('som.isCompleted = false')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getResult();
    }

    public function findCompletedMatchesByGame(int $gameId): array
    {
        return $this->createQueryBuilder('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->addSelect('p1', 'p2')
            ->andWhere('som.game = :gameId')
            ->andWhere('som.isCompleted = true')
            ->setParameter('gameId', $gameId)
            ->orderBy('som.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingChoicesByGame(int $gameId): int
    {
        $matches = $this->createQueryBuilder('som')
            ->andWhere('som.game = :gameId')
            ->andWhere('som.isCompleted = false')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getResult();

        $pendingChoices = 0;
        foreach ($matches as $match) {
            if (!$match->getPlayer1Choice()) $pendingChoices++;
            if (!$match->getPlayer2Choice()) $pendingChoices++;
        }

        return $pendingChoices;
    }

    public function findMatchesReadyForEvaluation(int $gameId): array
    {
        return $this->createQueryBuilder('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->addSelect('p1', 'p2')
            ->andWhere('som.game = :gameId')
            ->andWhere('som.isCompleted = false')
            ->andWhere('som.player1Choice IS NOT NULL')
            ->andWhere('som.player2Choice IS NOT NULL')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getResult();
    }

    public function hasPlayerChosenInMatch(int $matchId, int $playerId): bool
    {
        $match = $this->createQueryBuilder('som')
            ->andWhere('som.id = :matchId')
            ->andWhere('som.player1 = :playerId OR som.player2 = :playerId')
            ->setParameter('matchId', $matchId)
            ->setParameter('playerId', $playerId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$match) {
            return false;
        }

        if ($match->getPlayer1() && $match->getPlayer1()->getId() === $playerId) {
            return $match->getPlayer1Choice() !== null;
        } elseif ($match->getPlayer2() && $match->getPlayer2()->getId() === $playerId) {
            return $match->getPlayer2Choice() !== null;
        }

        return false;
    }

    public function getGameStatistics(int $gameId): array
    {
        $totalMatches = $this->createQueryBuilder('som')
            ->select('COUNT(som.id)')
            ->andWhere('som.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();

        $completedMatches = $this->createQueryBuilder('som')
            ->select('COUNT(som.id)')
            ->andWhere('som.game = :gameId')
            ->andWhere('som.isCompleted = true')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingChoices = $this->countPendingChoicesByGame($gameId);

        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'pending_matches' => $totalMatches - $completedMatches,
            'pending_choices' => $pendingChoices,
            'can_evaluate' => $pendingChoices === 0 && $totalMatches > 0,
        ];
    }
}