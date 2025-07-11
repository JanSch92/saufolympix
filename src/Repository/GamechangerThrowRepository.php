<?php

namespace App\Repository;

use App\Entity\GamechangerThrow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GamechangerThrow>
 */
class GamechangerThrowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GamechangerThrow::class);
    }

    /**
     * @return GamechangerThrow[]
     */
    public function findByGameOrderedByPlayerOrder(int $gameId): array
    {
        return $this->createQueryBuilder('gt')
            ->andWhere('gt.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('gt.playerOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return GamechangerThrow[]
     */
    public function findByGameOrderedByThrownAt(int $gameId): array
    {
        return $this->createQueryBuilder('gt')
            ->andWhere('gt.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('gt.thrownAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextPlayerOrder(int $gameId): int
    {
        $result = $this->createQueryBuilder('gt')
            ->select('MAX(gt.playerOrder) as maxOrder')
            ->andWhere('gt.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * GEFIXT: Prüft ob Spieler tatsächlich geworfen hat (thrownPoints > 0)
     */
    public function hasPlayerThrown(int $gameId, int $playerId): bool
    {
        $count = $this->createQueryBuilder('gt')
            ->select('COUNT(gt.id)')
            ->andWhere('gt.game = :gameId')
            ->andWhere('gt.player = :playerId')
            ->andWhere('gt.thrownPoints > 0') // NUR echte Würfe!
            ->setParameter('gameId', $gameId)
            ->setParameter('playerId', $playerId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * GEFIXT: Zählt nur echte Würfe (thrownPoints > 0)
     */
    public function getThrowsCount(int $gameId): int
    {
        return $this->createQueryBuilder('gt')
            ->select('COUNT(gt.id)')
            ->andWhere('gt.game = :gameId')
            ->andWhere('gt.thrownPoints > 0') // NUR echte Würfe!
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * GEFIXT: Spiel ist nur komplett wenn alle Spieler echte Würfe haben
     */
    public function isGameComplete(int $gameId): bool
    {
        $game = $this->getEntityManager()
            ->getRepository(\App\Entity\Game::class)
            ->find($gameId);
            
        if (!$game) {
            return false;
        }

        $playerCount = $game->getOlympix()->getPlayers()->count();
        $realThrowsCount = $this->getThrowsCount($gameId); // Nutzt bereits gefixte Methode

        return $realThrowsCount >= $playerCount;
    }

    /**
     * NEU: Findet Platzhalter für einen Spieler
     */
    public function findPlaceholderForPlayer(int $gameId, int $playerId): ?GamechangerThrow
    {
        return $this->createQueryBuilder('gt')
            ->andWhere('gt.game = :gameId')
            ->andWhere('gt.player = :playerId')
            ->andWhere('gt.thrownPoints = 0') // Platzhalter haben 0 Punkte
            ->setParameter('gameId', $gameId)
            ->setParameter('playerId', $playerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getGamechangerStatistics(int $gameId): array
    {
        $throws = $this->createQueryBuilder('gt')
            ->andWhere('gt.game = :gameId')
            ->andWhere('gt.thrownPoints > 0') // NUR echte Würfe!
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getResult();
        
        $stats = [
            'totalThrows' => count($throws),
            'bonusHits' => 0,      // Eigene Punkte getroffen
            'penaltyHits' => 0,    // Andere getroffen
            'normalThrows' => 0,   // Keine besonderen Treffer
            'totalBonusPoints' => 0,
            'totalPenaltyPoints' => 0
        ];

        foreach ($throws as $throw) {
            if (str_contains($throw->getScoringReason() ?? '', 'Eigene Punkte')) {
                $stats['bonusHits']++;
                $stats['totalBonusPoints'] += $throw->getPointsScored();
            } elseif (str_contains($throw->getScoringReason() ?? '', 'getroffen')) {
                $stats['penaltyHits']++;
                $stats['totalPenaltyPoints'] += abs($throw->getPointsScored());
            } else {
                $stats['normalThrows']++;
            }
        }

        return $stats;
    }

    public function getLastThrow(int $gameId): ?GamechangerThrow
    {
        return $this->createQueryBuilder('gt')
            ->andWhere('gt.game = :gameId')
            ->andWhere('gt.thrownPoints > 0') // NUR echte Würfe!
            ->setParameter('gameId', $gameId)
            ->orderBy('gt.thrownAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}