<?php

namespace App\Command;

use App\Repository\OlympixRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use App\Repository\QuizQuestionRepository;
use App\Repository\QuizAnswerRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'app:olympix:manage',
    description: 'Manage Olympix - delete, reset, or list',
)]
class OlympixManagementCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private PlayerRepository $playerRepository,
        private GameRepository $gameRepository,
        private GameResultRepository $gameResultRepository,
        private JokerRepository $jokerRepository,
        private QuizQuestionRepository $quizQuestionRepository,
        private QuizAnswerRepository $quizAnswerRepository,
        private TournamentRepository $tournamentRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action: list, delete, reset, or stats')
            ->addArgument('olympixId', InputArgument::OPTIONAL, 'Olympix ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force action without confirmation')
            ->addOption('keep-players', null, InputOption::VALUE_NONE, 'Keep players when resetting (reset only)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $olympixId = $input->getArgument('olympixId');
        $force = $input->getOption('force');
        $keepPlayers = $input->getOption('keep-players');

        switch ($action) {
            case 'list':
                return $this->listOlympix($io);
            
            case 'delete':
                if (!$olympixId) {
                    $io->error('Olympix ID required for delete action');
                    return Command::FAILURE;
                }
                return $this->deleteOlympix((int) $olympixId, $io, $force);
            
            case 'reset':
                if (!$olympixId) {
                    $io->error('Olympix ID required for reset action');
                    return Command::FAILURE;
                }
                return $this->resetOlympix((int) $olympixId, $io, $force, $keepPlayers);
            
            case 'stats':
                if ($olympixId) {
                    return $this->showOlympixStats((int) $olympixId, $io);
                } else {
                    return $this->showAllStats($io);
                }
            
            default:
                $io->error('Invalid action. Use: list, delete, reset, or stats');
                return Command::FAILURE;
        }
    }

    private function listOlympix(SymfonyStyle $io): int
    {
        $olympixList = $this->olympixRepository->findAll();

        if (empty($olympixList)) {
            $io->info('No Olympix found.');
            return Command::SUCCESS;
        }

        $io->title('All Olympix');

        $tableRows = [];
        foreach ($olympixList as $olympix) {
            $playerCount = $olympix->getPlayers()->count();
            $gameCount = $olympix->getGames()->count();
            $completedGames = 0;
            
            foreach ($olympix->getGames() as $game) {
                if ($game->getStatus() === 'completed') {
                    $completedGames++;
                }
            }

            $tableRows[] = [
                $olympix->getId(),
                $olympix->getName(),
                $olympix->getCreatedAt()->format('Y-m-d H:i'),
                $olympix->isIsActive() ? '✓' : '✗',
                $playerCount,
                $gameCount,
                $completedGames,
                $gameCount > 0 ? round(($completedGames / $gameCount) * 100) . '%' : '0%'
            ];
        }

        $io->table([
            'ID',
            'Name',
            'Created',
            'Active',
            'Players',
            'Games',
            'Completed',
            'Progress'
        ], $tableRows);

        $io->text([
            '',
            'Usage examples:',
            '  php bin/console app:olympix:manage delete 123     # Delete olympix',
            '  php bin/console app:olympix:manage reset 123      # Reset olympix (delete games, keep players)',
            '  php bin/console app:olympix:manage reset 123 --keep-players  # Reset games only',
            '  php bin/console app:olympix:manage stats 123      # Show detailed stats',
        ]);

        return Command::SUCCESS;
    }

    private function deleteOlympix(int $olympixId, SymfonyStyle $io, bool $force): int
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            $io->error("Olympix with ID $olympixId not found.");
            return Command::FAILURE;
        }

        $io->title("Delete Olympix - {$olympix->getName()}");
        
        $stats = $this->getOlympixStats($olympix);
        $io->text([
            "Name: {$olympix->getName()}",
            "Created: {$olympix->getCreatedAt()->format('Y-m-d H:i:s')}",
            "Players: {$stats['players']}",
            "Games: {$stats['games']}",
            "Results: {$stats['results']}",
            "Jokers: {$stats['jokers']}",
        ]);

        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Are you sure you want to DELETE this entire Olympix? This will remove EVERYTHING and cannot be undone! (y/N) ",
                false
            );

            if (!$helper->ask($input ?? $this->getInput(), $output ?? $this->getOutput(), $question)) {
                $io->info('Deletion cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->performCompleteOlympixDeletion($olympix, $io);

        $io->success("Olympix '{$olympix->getName()}' has been completely deleted.");
        return Command::SUCCESS;
    }

    private function resetOlympix(int $olympixId, SymfonyStyle $io, bool $force, bool $keepPlayers): int
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            $io->error("Olympix with ID $olympixId not found.");
            return Command::FAILURE;
        }

        $io->title("Reset Olympix - {$olympix->getName()}");
        
        $stats = $this->getOlympixStats($olympix);
        $io->text([
            "Name: {$olympix->getName()}",
            "Players: {$stats['players']} " . ($keepPlayers ? '(will be kept)' : '(will be deleted)'),
            "Games: {$stats['games']} (will be deleted)",
            "Results: {$stats['results']} (will be deleted)",
            "Jokers: {$stats['jokers']} (will be deleted)",
        ]);

        if (!$force) {
            $helper = $this->getHelper('question');
            $resetType = $keepPlayers ? 'RESET (keep players)' : 'RESET (delete everything except olympix)';
            $question = new ConfirmationQuestion(
                "Are you sure you want to $resetType this Olympix? This cannot be undone! (y/N) ",
                false
            );

            if (!$helper->ask($input ?? $this->getInput(), $output ?? $this->getOutput(), $question)) {
                $io->info('Reset cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->performOlympixReset($olympix, $io, $keepPlayers);

        $resetType = $keepPlayers ? 'reset (players kept)' : 'completely reset';
        $io->success("Olympix '{$olympix->getName()}' has been $resetType.");
        return Command::SUCCESS;
    }

    private function showOlympixStats(int $olympixId, SymfonyStyle $io): int
    {
        $olympix = $this->olympixRepository->find($olympixId);

        if (!$olympix) {
            $io->error("Olympix with ID $olympixId not found.");
            return Command::FAILURE;
        }

        $io->title("Olympix Statistics - {$olympix->getName()}");

        $stats = $this->getDetailedOlympixStats($olympix);

        // Basic info
        $io->section('Basic Information');
        $io->definitionList(
            ['ID' => $olympix->getId()],
            ['Name' => $olympix->getName()],
            ['Created' => $olympix->getCreatedAt()->format('Y-m-d H:i:s')],
            ['Active' => $olympix->isIsActive() ? 'Yes' : 'No'],
            ['Progress' => $stats['progress_percentage'] . '%']
        );

        // Counts
        $io->section('Counts');
        $io->definitionList(
            ['Players' => $stats['players']],
            ['Games' => $stats['games']],
            ['Active Games' => $stats['active_games']],
            ['Completed Games' => $stats['completed_games']],
            ['Pending Games' => $stats['pending_games']],
            ['Game Results' => $stats['results']],
            ['Jokers Used' => $stats['jokers']],
            ['Tournaments' => $stats['tournaments']],
            ['Quiz Questions' => $stats['quiz_questions']],
            ['Quiz Answers' => $stats['quiz_answers']]
        );

        // Game types
        if ($stats['game_types']) {
            $io->section('Game Types');
            foreach ($stats['game_types'] as $type => $count) {
                $io->text("• $type: $count");
            }
        }

        // Top players
        if ($stats['top_players']) {
            $io->section('Top 5 Players');
            foreach ($stats['top_players'] as $i => $player) {
                $position = $i + 1;
                $io->text("$position. {$player['name']} - {$player['points']} points");
            }
        }

        return Command::SUCCESS;
    }

    private function showAllStats(SymfonyStyle $io): int
    {
        $allOlympix = $this->olympixRepository->findAll();

        if (empty($allOlympix)) {
            $io->info('No Olympix found.');
            return Command::SUCCESS;
        }

        $io->title('Global Statistics');

        $totalPlayers = 0;
        $totalGames = 0;
        $totalResults = 0;
        $totalJokers = 0;

        foreach ($allOlympix as $olympix) {
            $stats = $this->getOlympixStats($olympix);
            $totalPlayers += $stats['players'];
            $totalGames += $stats['games'];
            $totalResults += $stats['results'];
            $totalJokers += $stats['jokers'];
        }

        $io->definitionList(
            ['Total Olympix' => count($allOlympix)],
            ['Total Players' => $totalPlayers],
            ['Total Games' => $totalGames],
            ['Total Results' => $totalResults],
            ['Total Jokers Used' => $totalJokers],
            ['Average Players per Olympix' => count($allOlympix) > 0 ? round($totalPlayers / count($allOlympix), 1) : 0],
            ['Average Games per Olympix' => count($allOlympix) > 0 ? round($totalGames / count($allOlympix), 1) : 0]
        );

        return Command::SUCCESS;
    }

    private function performCompleteOlympixDeletion($olympix, SymfonyStyle $io): void
    {
        $io->section("Deleting Olympix: {$olympix->getName()}");

        // Delete in correct order due to foreign key constraints
        
        // 1. Quiz Answers
        $quizAnswers = $this->quizAnswerRepository->createQueryBuilder('qa')
            ->leftJoin('qa.quizQuestion', 'qq')
            ->leftJoin('qq.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($quizAnswers as $answer) {
            $this->entityManager->remove($answer);
        }
        $io->text("✓ Quiz Answers deleted: " . count($quizAnswers));

        // 2. Quiz Questions
        $quizQuestions = $this->quizQuestionRepository->createQueryBuilder('qq')
            ->leftJoin('qq.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($quizQuestions as $question) {
            $this->entityManager->remove($question);
        }
        $io->text("✓ Quiz Questions deleted: " . count($quizQuestions));

        // 3. Jokers
        $jokers = $this->jokerRepository->createQueryBuilder('j')
            ->leftJoin('j.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($jokers as $joker) {
            $this->entityManager->remove($joker);
        }
        $io->text("✓ Jokers deleted: " . count($jokers));

        // 4. Game Results
        $gameResults = $this->gameResultRepository->createQueryBuilder('gr')
            ->leftJoin('gr.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($gameResults as $result) {
            $this->entityManager->remove($result);
        }
        $io->text("✓ Game Results deleted: " . count($gameResults));

        // 5. Tournaments
        $tournaments = $this->tournamentRepository->createQueryBuilder('t')
            ->leftJoin('t.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($tournaments as $tournament) {
            $this->entityManager->remove($tournament);
        }
        $io->text("✓ Tournaments deleted: " . count($tournaments));

        // 6. Games
        $games = $olympix->getGames();
        foreach ($games as $game) {
            $this->entityManager->remove($game);
        }
        $io->text("✓ Games deleted: " . count($games));

        // 7. Players
        $players = $olympix->getPlayers();
        foreach ($players as $player) {
            $this->entityManager->remove($player);
        }
        $io->text("✓ Players deleted: " . count($players));

        // 8. Finally, the Olympix itself
        $this->entityManager->remove($olympix);
        $io->text("✓ Olympix deleted");

        $this->entityManager->flush();
    }

    private function performOlympixReset($olympix, SymfonyStyle $io, bool $keepPlayers): void
    {
        $io->section("Resetting Olympix: {$olympix->getName()}");

        // Delete game-related data (same as deletion but keep players if requested)
        
        // 1. Quiz Answers
        $quizAnswers = $this->quizAnswerRepository->createQueryBuilder('qa')
            ->leftJoin('qa.quizQuestion', 'qq')
            ->leftJoin('qq.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($quizAnswers as $answer) {
            $this->entityManager->remove($answer);
        }
        $io->text("✓ Quiz Answers deleted: " . count($quizAnswers));

        // 2. Quiz Questions  
        $quizQuestions = $this->quizQuestionRepository->createQueryBuilder('qq')
            ->leftJoin('qq.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($quizQuestions as $question) {
            $this->entityManager->remove($question);
        }
        $io->text("✓ Quiz Questions deleted: " . count($quizQuestions));

        // 3. Jokers (and reset player joker status)
        $jokers = $this->jokerRepository->createQueryBuilder('j')
            ->leftJoin('j.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($jokers as $joker) {
            // Reset player joker availability
            if ($joker->isDoubleJoker()) {
                $joker->getPlayer()->setJokerDoubleUsed(false);
            } elseif ($joker->isSwapJoker()) {
                $joker->getPlayer()->setJokerSwapUsed(false);
            }
            $this->entityManager->remove($joker);
        }
        $io->text("✓ Jokers deleted and player joker status reset: " . count($jokers));

        // 4. Game Results
        $gameResults = $this->gameResultRepository->createQueryBuilder('gr')
            ->leftJoin('gr.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($gameResults as $result) {
            $this->entityManager->remove($result);
        }
        $io->text("✓ Game Results deleted: " . count($gameResults));

        // 5. Tournaments
        $tournaments = $this->tournamentRepository->createQueryBuilder('t')
            ->leftJoin('t.game', 'g')
            ->where('g.olympix = :olympix')
            ->setParameter('olympix', $olympix)
            ->getQuery()
            ->getResult();
        
        foreach ($tournaments as $tournament) {
            $this->entityManager->remove($tournament);
        }
        $io->text("✓ Tournaments deleted: " . count($tournaments));

        // 6. Games
        $games = $olympix->getGames();
        foreach ($games as $game) {
            $this->entityManager->remove($game);
        }
        $io->text("✓ Games deleted: " . count($games));

        // 7. Players (only if not keeping them)
        if (!$keepPlayers) {
            $players = $olympix->getPlayers();
            foreach ($players as $player) {
                $this->entityManager->remove($player);
            }
            $io->text("✓ Players deleted: " . count($players));
        } else {
            // Reset player points
            foreach ($olympix->getPlayers() as $player) {
                $player->setTotalPoints(0);
                $player->setJokerDoubleUsed(false);
                $player->setJokerSwapUsed(false);
            }
            $io->text("✓ Player points and jokers reset: " . count($olympix->getPlayers()));
        }

        $this->entityManager->flush();
    }

    private function getOlympixStats($olympix): array
    {
        return [
            'players' => $olympix->getPlayers()->count(),
            'games' => $olympix->getGames()->count(),
            'results' => $this->gameResultRepository->createQueryBuilder('gr')
                ->leftJoin('gr.game', 'g')
                ->where('g.olympix = :olympix')
                ->setParameter('olympix', $olympix)
                ->select('COUNT(gr.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'jokers' => $this->jokerRepository->createQueryBuilder('j')
                ->leftJoin('j.game', 'g')
                ->where('g.olympix = :olympix')
                ->setParameter('olympix', $olympix)
                ->select('COUNT(j.id)')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    private function getDetailedOlympixStats($olympix): array
    {
        $stats = $this->getOlympixStats($olympix);
        
        // Game status breakdown
        $completedGames = 0;
        $activeGames = 0;
        $pendingGames = 0;
        $gameTypes = [];
        
        foreach ($olympix->getGames() as $game) {
            switch ($game->getStatus()) {
                case 'completed': $completedGames++; break;
                case 'active': $activeGames++; break;
                case 'pending': $pendingGames++; break;
            }
            
            $type = $game->getGameTypeLabel();
            $gameTypes[$type] = ($gameTypes[$type] ?? 0) + 1;
        }
        
        // Top players
        $topPlayers = [];
        $players = $olympix->getPlayers()->toArray();
        usort($players, function($a, $b) {
            return $b->getTotalPoints() - $a->getTotalPoints();
        });
        
        foreach (array_slice($players, 0, 5) as $player) {
            $topPlayers[] = [
                'name' => $player->getName(),
                'points' => $player->getTotalPoints()
            ];
        }
        
        return array_merge($stats, [
            'completed_games' => $completedGames,
            'active_games' => $activeGames,
            'pending_games' => $pendingGames,
            'progress_percentage' => $stats['games'] > 0 ? round(($completedGames / $stats['games']) * 100) : 0,
            'game_types' => $gameTypes,
            'top_players' => $topPlayers,
            'tournaments' => $this->tournamentRepository->createQueryBuilder('t')
                ->leftJoin('t.game', 'g')
                ->where('g.olympix = :olympix')
                ->setParameter('olympix', $olympix)
                ->select('COUNT(t.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'quiz_questions' => $this->quizQuestionRepository->createQueryBuilder('qq')
                ->leftJoin('qq.game', 'g')
                ->where('g.olympix = :olympix')
                ->setParameter('olympix', $olympix)
                ->select('COUNT(qq.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'quiz_answers' => $this->quizAnswerRepository->createQueryBuilder('qa')
                ->leftJoin('qa.quizQuestion', 'qq')
                ->leftJoin('qq.game', 'g')
                ->where('g.olympix = :olympix')
                ->setParameter('olympix', $olympix)
                ->select('COUNT(qa.id)')
                ->getQuery()
                ->getSingleScalarResult(),
        ]);
    }
}