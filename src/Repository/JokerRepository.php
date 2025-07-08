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
            ->setParameter('isUsed', true) // Used double jokers for display
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

    /**
     * NEUE METHODE: Get pending double jokers for a specific game
     * Used when processing game results to apply double jokers
     */
    public function getDoubleJokersForGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->addSelect('p')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'double')
            ->setParameter('isUsed', false) // Get pending/unused double jokers
            ->getQuery()
            ->getResult();
    }

    /**
     * Get pending swap joker for a specific game (not yet applied)
     * Used when processing game results to apply swap jokers
     */
    public function getSwapJokerForGame(int $gameId): ?Joker
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
            ->setParameter('isUsed', false) // Get pending/unused swap joker
            ->getQuery()
            ->getOneOrNullResult();
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

    /**
     * NEUE METHODE: Check if ANY swap has already been reserved for a specific game
     * (Only one swap per game allowed - "wer zuerst kommt, mahlt zuerst")
     */
    public function hasAnySwapForGame(int $gameId): bool
    {
        $result = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'swap')
            // Check for both pending (false) and used (true) swap jokers
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Get all pending jokers for a specific game (not yet applied)
     */
    public function getPendingJokersByGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('p')
            ->addSelect('tp')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('isUsed', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all used jokers for a specific game
     */
    public function getUsedJokersByGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('p')
            ->addSelect('tp')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('isUsed', true)
            ->orderBy('j.jokerType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark a joker as used
     */
    public function markAsUsed(Joker $joker, bool $flush = true): void
    {
        $joker->setIsUsed(true);
        $this->getEntityManager()->persist($joker);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Check if there's a swap joker for a specific game
     */
    public function hasSwapJokerForGame(int $gameId): bool
    {
        $result = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', false) // Check for pending swap jokers
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Find joker by player and game
     */
    public function findByPlayerAndGame(int $playerId, int $gameId, string $jokerType = null): array
    {
        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('p')
            ->addSelect('tp')
            ->andWhere('j.player = :playerId')
            ->andWhere('j.game = :gameId')
            ->setParameter('playerId', $playerId)
            ->setParameter('gameId', $gameId);

        if ($jokerType) {
            $qb->andWhere('j.jokerType = :jokerType')
               ->setParameter('jokerType', $jokerType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count jokers by type for a specific game
     */
    public function countByGameAndType(int $gameId, string $jokerType, bool $isUsed = null): int
    {
        $qb = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :jokerType')
            ->setParameter('gameId', $gameId)
            ->setParameter('jokerType', $jokerType);

        if ($isUsed !== null) {
            $qb->andWhere('j.isUsed = :isUsed')
               ->setParameter('isUsed', $isUsed);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Check if a player is blocked for a specific game
     * (e.g., has a swap joker targeting them)
     */
    public function isPlayerBlockedForGame(int $playerId, int $gameId): bool
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
            ->setParameter('isUsed', false) // Check for pending swap jokers
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Check if a player has any active jokers for a specific game
     */
    public function hasPlayerJokersForGame(int $playerId, int $gameId): bool
    {
        $result = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.player = :playerId')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('playerId', $playerId)
            ->setParameter('gameId', $gameId)
            ->setParameter('isUsed', false) // Check for pending jokers
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Find all blocked players for a specific game
     * (players who have swap jokers targeting them)
     */
    public function findBlockedPlayersForGame(int $gameId): array
    {
        return $this->createQueryBuilder('j')
            ->select('DISTINCT p.id, p.name')
            ->leftJoin('j.targetPlayer', 'p')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->andWhere('j.targetPlayer IS NOT NULL')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all jokers for a specific player in an olympix
     */
    public function findJokersByPlayerAndOlympix(int $playerId, int $olympixId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.game', 'g')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('p')
            ->addSelect('g')
            ->addSelect('tp')
            ->andWhere('j.player = :playerId')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('playerId', $playerId)
            ->setParameter('olympixId', $olympixId)
            ->orderBy('j.usedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get joker statistics for an olympix
     */
    public function getJokerStatistics(int $olympixId): array
    {
        $result = $this->createQueryBuilder('j')
            ->select('j.jokerType, COUNT(j.id) as count')
            ->leftJoin('j.game', 'g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('isUsed', true)
            ->groupBy('j.jokerType')
            ->getQuery()
            ->getResult();

        $stats = [
            'double' => 0,
            'swap' => 0,
            'total' => 0
        ];

        foreach ($result as $row) {
            $stats[$row['jokerType']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * NEUE METHODE: Get pending swap details for a specific game
     */
    public function getPendingSwapForGame(int $gameId): ?array
    {
        $swapJoker = $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('p')
            ->addSelect('tp')
            ->andWhere('j.game = :gameId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('gameId', $gameId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', false) // Pending swap
            ->getQuery()
            ->getOneOrNullResult();

        if (!$swapJoker) {
            return null;
        }

        return [
            'joker_id' => $swapJoker->getId(),
            'source_player' => [
                'id' => $swapJoker->getPlayer()->getId(),
                'name' => $swapJoker->getPlayer()->getName()
            ],
            'target_player' => [
                'id' => $swapJoker->getTargetPlayer()->getId(),
                'name' => $swapJoker->getTargetPlayer()->getName()
            ],
            'joker_entity' => $swapJoker
        ];
    }

    /**
     * NEUE METHODE: Get swap details for a specific game
     */
    public function getSwapDetailsForGame(int $gameId): ?array
    {
        $swapJoker = $this->createQueryBuilder('j')
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
            ->getOneOrNullResult();

        if (!$swapJoker) {
            return null;
        }

        return [
            'joker_id' => $swapJoker->getId(),
            'source_player' => [
                'id' => $swapJoker->getPlayer()->getId(),
                'name' => $swapJoker->getPlayer()->getName()
            ],
            'target_player' => [
                'id' => $swapJoker->getTargetPlayer()->getId(),
                'name' => $swapJoker->getTargetPlayer()->getName()
            ],
            'used_at' => $swapJoker->getUsedAt()
        ];
    }

    /**
     * NEUE METHODE: Find all swap jokers for an olympix
     */
    public function findAllSwapJokersForOlympix(int $olympixId): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.player', 'p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->leftJoin('j.game', 'g')
            ->addSelect('p')
            ->addSelect('tp')
            ->addSelect('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', true)
            ->orderBy('j.usedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * NEUE METHODE: Remove all jokers for an olympix (for reset command)
     */
    public function removeAllJokersForOlympix(int $olympixId): int
    {
        return $this->createQueryBuilder('j')
            ->delete()
            ->leftJoin('j.game', 'g')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->execute();
    }

    /**
     * NEUE METHODE: Count swaps per game for an olympix
     */
    public function countSwapsPerGameForOlympix(int $olympixId): array
    {
        $result = $this->createQueryBuilder('j')
            ->select('g.id as game_id, g.name as game_name, COUNT(j.id) as swap_count')
            ->leftJoin('j.game', 'g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('j.jokerType = :type')
            ->andWhere('j.isUsed = :isUsed')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('type', 'swap')
            ->setParameter('isUsed', true)
            ->groupBy('g.id')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();

        $swapCounts = [];
        foreach ($result as $row) {
            $swapCounts[$row['game_id']] = [
                'game_name' => $row['game_name'],
                'swap_count' => (int) $row['swap_count']
            ];
        }

        return $swapCounts;
    }
}