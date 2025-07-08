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

    /**
     * Find games by olympix ordered by order position
     */
    public function findByOlympixOrdered(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next order position for a game in an olympix
     */
    public function getNextOrderPosition(int $olympixId): int
    {
        $maxPosition = $this->createQueryBuilder('g')
            ->select('MAX(g.orderPosition)')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Find active game for olympix
     */
    public function findActiveGameForOlympix(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find next game to play
     */
    public function findNextGameToPlay(int $olympixId): ?Game
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

    /**
     * Find pending games for olympix
     */
    public function findPendingGamesForOlympix(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'pending')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed games for olympix
     */
    public function findCompletedGamesForOlympix(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find last completed game for olympix
     */
    public function findLastCompletedGameForOlympix(int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->orderBy('g.orderPosition', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find games with jokers (all) - VERSION 1
     */
    public function findGamesWithJokers(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.jokers', 'j')
            ->addSelect('j')
            ->leftJoin('j.player', 'p')
            ->addSelect('p')
            ->leftJoin('j.targetPlayer', 'tp')
            ->addSelect('tp')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tournament games
     */
    public function findTournamentGames(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.tournament', 't')
            ->addSelect('t')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.gameType IN (:tournamentTypes)')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('tournamentTypes', ['tournament_team', 'tournament_single'])
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find quiz games
     */
    public function findQuizGames(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.quizQuestions', 'qq')
            ->addSelect('qq')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.gameType = :quizType')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('quizType', 'quiz')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find split or steal games
     */
    public function findSplitOrStealGames(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.splitOrStealMatches', 'som')
            ->addSelect('som')
            ->leftJoin('som.player1', 'p1')
            ->leftJoin('som.player2', 'p2')
            ->addSelect('p1', 'p2')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.gameType = :gameType')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('gameType', 'split_or_steal')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games by type
     */
    public function findGamesByType(int $olympixId, string $gameType): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.gameType = :gameType')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('gameType', $gameType)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games by status
     */
    public function findGamesByStatus(int $olympixId, string $status): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', $status)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get game type distribution
     */
    public function getGameTypeDistribution(int $olympixId): array
    {
        $result = $this->createQueryBuilder('g')
            ->select('g.gameType, COUNT(g.id) as count')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->groupBy('g.gameType')
            ->getQuery()
            ->getResult();

        $distribution = [];
        foreach ($result as $row) {
            $distribution[$row['gameType']] = $row['count'];
        }

        return $distribution;
    }

    /**
     * Get game statistics
     */
    public function getGameStatistics(int $olympixId): array
    {
        $total = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_games' => $total,
            'completed_games' => $completed,
            'active_games' => $active,
            'pending_games' => $total - $completed - $active,
        ];
    }

    /**
     * Get olympix stats
     */
    public function getOlympixStats(int $olympixId): array
    {
        $stats = $this->getGameStatistics($olympixId);
        $typeDistribution = $this->getGameTypeDistribution($olympixId);
        
        return array_merge($stats, [
            'tournament_team_games' => $typeDistribution['tournament_team'] ?? 0,
            'tournament_single_games' => $typeDistribution['tournament_single'] ?? 0,
            'free_for_all_games' => $typeDistribution['free_for_all'] ?? 0,
            'quiz_games' => $typeDistribution['quiz'] ?? 0,
            'split_or_steal_games' => $typeDistribution['split_or_steal'] ?? 0,
            'progress_percentage' => $stats['total_games'] > 0 ? round(($stats['completed_games'] / $stats['total_games']) * 100, 1) : 0
        ]);
    }

    /**
     * Reorder games
     */
    public function reorderGames(int $olympixId, array $gameIds): void
    {
        $position = 1;
        foreach ($gameIds as $gameId) {
            $this->createQueryBuilder('g')
                ->update()
                ->set('g.orderPosition', ':position')
                ->where('g.id = :gameId')
                ->andWhere('g.olympix = :olympixId')
                ->setParameter('position', $position)
                ->setParameter('gameId', $gameId)
                ->setParameter('olympixId', $olympixId)
                ->getQuery()
                ->execute();
            $position++;
        }
    }

    /**
     * Get average game duration
     */
    public function getAverageGameDuration(int $olympixId): ?float
    {
        $games = $this->findCompletedGamesForOlympix($olympixId);
        if (empty($games)) {
            return null;
        }

        $totalDuration = 0;
        foreach ($games as $game) {
            $totalDuration += $game->getExpectedDuration();
        }

        return $totalDuration / count($games);
    }

    /**
     * Find games needing attention
     */
    public function findGamesNeedingAttention(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.quizQuestions', 'qq')
            ->leftJoin('g.splitOrStealMatches', 'som')
            ->addSelect('qq', 'som')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->andWhere(
                '(g.gameType = :quizType AND SIZE(g.quizQuestions) < 3) OR ' .
                '(g.gameType IN (:tournamentTypes) AND g.tournament IS NULL) OR ' .
                '(g.gameType = :splitOrStealType AND SIZE(g.splitOrStealMatches) = 0)'
            )
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'active')
            ->setParameter('quizType', 'quiz')
            ->setParameter('tournamentTypes', ['tournament_team', 'tournament_single'])
            ->setParameter('splitOrStealType', 'split_or_steal')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games that can be started - VERSION 1
     */
    public function findGamesCanBeStarted(int $olympixId): array
    {
        $games = $this->findByOlympixOrdered($olympixId);
        $canStart = [];

        foreach ($games as $game) {
            if ($game->canStart()) {
                $canStart[] = $game;
            }
        }

        return $canStart;
    }

    /**
     * Find games that need setup - VERSION 1
     */
    public function findGamesNeedingSetup(int $olympixId): array
    {
        $games = $this->findByOlympixOrdered($olympixId);
        $needSetup = [];

        foreach ($games as $game) {
            if ($game->needsSetup()) {
                $needSetup[] = $game;
            }
        }

        return $needSetup;
    }

    /**
     * Count games by status
     */
    public function countGamesByStatus(int $olympixId, string $status): int
    {
        return $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count games by type
     */
    public function countGamesByType(int $olympixId, string $gameType): int
    {
        return $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.gameType = :gameType')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('gameType', $gameType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find games with results - VERSION 1
     */
    public function findGamesWithResults(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.gameResults', 'gr')
            ->addSelect('gr')
            ->leftJoin('gr.player', 'p')
            ->addSelect('p')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('SIZE(g.gameResults) > 0')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games without results - VERSION 1
     */
    public function findGamesWithoutResults(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('SIZE(g.gameResults) = 0')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total points awarded in olympix - VERSION 1
     */
    public function getTotalPointsAwarded(int $olympixId): int
    {
        return $this->createQueryBuilder('g')
            ->select('SUM(gr.points)')
            ->leftJoin('g.gameResults', 'gr')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * Get completion percentage - VERSION 1
     */
    public function getCompletionPercentage(int $olympixId): float
    {
        $total = $this->countGamesByStatus($olympixId, 'pending') + 
                 $this->countGamesByStatus($olympixId, 'active') + 
                 $this->countGamesByStatus($olympixId, 'completed');

        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->countGamesByStatus($olympixId, 'completed');
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Find recent games (last 5) - VERSION 1
     */
    public function findRecentGames(int $olympixId, int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games by player participation - VERSION 1
     */
    public function findGamesByPlayerParticipation(int $olympixId, int $playerId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.gameResults', 'gr')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('gr.player = :playerId')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('playerId', $playerId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if player participated in game - VERSION 1
     */
    public function hasPlayerParticipated(int $gameId, int $playerId): bool
    {
        $result = $this->createQueryBuilder('g')
            ->select('COUNT(gr.id)')
            ->leftJoin('g.gameResults', 'gr')
            ->andWhere('g.id = :gameId')
            ->andWhere('gr.player = :playerId')
            ->setParameter('gameId', $gameId)
            ->setParameter('playerId', $playerId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Get games scheduled for today/soon - VERSION 1
     */
    public function findUpcomingGames(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('statuses', ['pending', 'active'])
            ->orderBy('g.orderPosition', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    // ==================== VERSION 2 METHODS ====================

    /**
     * Find recently completed games - VERSION 2
     */
    public function findRecentlyCompletedGames(int $olympixId, int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->orderBy('g.orderPosition', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get game completion progress - VERSION 2
     */
    public function getGameCompletionProgress(int $olympixId): array
    {
        $totalGames = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->getQuery()
            ->getSingleScalarResult();

        $completedGames = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $percentage = $totalGames > 0 ? round(($completedGames / $totalGames) * 100, 1) : 0;

        return [
            'total_games' => $totalGames,
            'completed_games' => $completedGames,
            'remaining_games' => $totalGames - $completedGames,
            'completion_percentage' => $percentage
        ];
    }

    /**
     * Find games by order range - VERSION 2
     */
    public function findGamesByOrderRange(int $olympixId, int $startOrder, int $endOrder): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.orderPosition BETWEEN :startOrder AND :endOrder')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('startOrder', $startOrder)
            ->setParameter('endOrder', $endOrder)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find game by name and olympix - VERSION 2
     */
    public function findGameByNameAndOlympix(string $name, int $olympixId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.name = :name')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find duplicate game names - VERSION 2
     */
    public function findDuplicateGameNames(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->select('g.name, COUNT(g.id) as count')
            ->andWhere('g.olympix = :olympixId')
            ->setParameter('olympixId', $olympixId)
            ->groupBy('g.name')
            ->having('COUNT(g.id) > 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get games by player participation - VERSION 2 (different from VERSION 1)
     */
    public function getGamesByPlayerParticipation(int $olympixId, int $playerId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.gameResults', 'gr')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('gr.player = :playerId')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('playerId', $playerId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games without results - VERSION 2 (different from VERSION 1)
     */
    public function findGamesWithoutResultsV2(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->andWhere('SIZE(g.gameResults) = 0')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total points awarded - VERSION 2 (different from VERSION 1)
     */
    public function getTotalPointsAwardedV2(int $olympixId): int
    {
        return $this->createQueryBuilder('g')
            ->select('SUM(gr.finalPoints)')
            ->leftJoin('g.gameResults', 'gr')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status = :status')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * Find upcoming games - VERSION 2 (different from VERSION 1)
     */
    public function findUpcomingGamesV2(int $olympixId, int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('olympixId', $olympixId)
            ->setParameter('statuses', ['pending', 'active'])
            ->orderBy('g.orderPosition', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find games with jokers - VERSION 2 (different from VERSION 1)
     */
    public function findGamesWithJokersV2(int $olympixId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.jokers', 'j')
            ->andWhere('g.olympix = :olympixId')
            ->andWhere('SIZE(g.jokers) > 0')
            ->setParameter('olympixId', $olympixId)
            ->orderBy('g.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }
}