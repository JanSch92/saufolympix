<?php

namespace App\Command;

use App\Repository\OlympixRepository;
use App\Repository\GameRepository;
use App\Repository\GameResultRepository;
use App\Repository\JokerRepository;
use App\Repository\PlayerRepository;
use App\Repository\TournamentRepository;
use App\Repository\QuizAnswerRepository;
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
    name: 'app:tournament:reset',
    description: 'Reset complete tournament/olympix - removes all results, jokers, and resets games',
)]
class TournamentResetCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OlympixRepository $olympixRepository,
        private GameRepository $gameRepository,
        private GameResultRepository $gameResultRepository,
        private JokerRepository $jokerRepository,
        private PlayerRepository $playerRepository,
        private TournamentRepository $tournamentRepository,
        private QuizAnswerRepository $quizAnswerRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('olympix-id', InputArgument::REQUIRED, 'The ID of the Olympix to reset')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force reset without confirmation')
            ->addOption('keep-games', null, InputOption::VALUE_NONE, 'Keep games but reset their status and results')
            ->addOption('reset-jokers-only', null, InputOption::VALUE_NONE, 'Only reset jokers (keep results)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $olympixId = (int) $input->getArgument('olympix-id');
        $force = $input->getOption('force');
        $keepGames = $input->getOption('keep-games');
        $resetJokersOnly = $input->getOption('reset-jokers-only');

        // Find olympix
        $olympix = $this->olympixRepository->find($olympixId);
        if (!$olympix) {
            $io->error("Olympix with ID {$olympixId} not found!");
            return Command::FAILURE;
        }

        $io->title("Tournament Reset for: {$olympix->getName()}");

        // Show current statistics
        $this->showCurrentStats($io, $olympixId);

        // Confirmation
        if (!$force) {
            $resetType = $resetJokersOnly ? 'jokers only' : ($keepGames ? 'results and jokers (keeping games)' : 'EVERYTHING');
            $question = new ConfirmationQuestion(
                "Are you sure you want to reset {$resetType} for olympix '{$olympix->getName()}'? This cannot be undone! (y/N) ",
                false
            );
            
            if (!$this->getHelper('question')->ask($input, $output, $question)) {
                $io->info('Reset cancelled.');
                return Command::SUCCESS;
            }
        }

        $io->section('Starting reset process...');

        try {
            $this->entityManager->beginTransaction();

            if ($resetJokersOnly) {
                $this->resetJokersOnly($io, $olympixId);
            } elseif ($keepGames) {
                $this->resetResultsAndJokers($io, $olympixId);
            } else {
                $this->resetEverything($io, $olympixId);
            }

            $this->entityManager->commit();
            $io->success('Tournament reset completed successfully!');

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $io->error('Reset failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Show final statistics
        $io->section('Final Statistics:');
        $this->showCurrentStats($io, $olympixId);

        return Command::SUCCESS;
    }

    private function showCurrentStats(SymfonyStyle $io, int $olympixId): void
    {
        $gameStats = $this->gameRepository->getGameStatistics($olympixId);
        $jokerStats = $this->jokerRepository->getJokerStatistics($olympixId);
        
        $totalResults = $this->entityManager->createQuery(
            'SELECT COUNT(gr.id) FROM App\Entity\GameResult gr JOIN gr.game g WHERE g.olympix = :olympixId'
        )->setParameter('olympixId', $olympixId)->getSingleScalarResult();

        $totalQuizAnswers = $this->entityManager->createQuery(
            'SELECT COUNT(qa.id) FROM App\Entity\QuizAnswer qa JOIN qa.question qq JOIN qq.game g WHERE g.olympix = :olympixId'
        )->setParameter('olympixId', $olympixId)->getSingleScalarResult();

        $players = $this->playerRepository->findBy(['olympix' => $olympixId]);

        $io->table([
            'Category', 'Count'
        ], [
            ['Games Total', $gameStats['total']],
            ['Games Pending', $gameStats['pending']],
            ['Games Active', $gameStats['active']],
            ['Games Completed', $gameStats['completed']],
            ['Game Results', $totalResults],
            ['Players', count($players)],
            ['Double Jokers Used', $jokerStats['double']],
            ['Swap Jokers Used', $jokerStats['swap']],
            ['Total Jokers Used', $jokerStats['total']],
            ['Quiz Answers', $totalQuizAnswers],
        ]);
    }

    private function resetJokersOnly(SymfonyStyle $io, int $olympixId): void
    {
        $io->text('Resetting jokers only...');

        // 1. Remove all jokers
        $jokersRemoved = $this->jokerRepository->removeAllJokersForOlympix($olympixId);
        $io->text("✓ Removed {$jokersRemoved} jokers");

        // 2. Reset player joker status
        $players = $this->playerRepository->findBy(['olympix' => $olympixId]);
        foreach ($players as $player) {
            $player->setJokerDoubleUsed(false);
            $player->setJokerSwapUsed(false);
            $this->entityManager->persist($player);
        }
        $io->text("✓ Reset joker status for " . count($players) . " players");

        $this->entityManager->flush();
    }

    private function resetResultsAndJokers(SymfonyStyle $io, int $olympixId): void
    {
        $io->text('Resetting results and jokers (keeping games)...');

        // 1. Remove all game results
        $resultsRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\GameResult gr WHERE gr.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId)'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$resultsRemoved} game results");

        // 2. Remove all quiz answers
        $quizAnswersRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\QuizAnswer qa WHERE qa.question IN (SELECT qq.id FROM App\Entity\QuizQuestion qq WHERE qq.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId))'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$quizAnswersRemoved} quiz answers");

        // 3. Remove all jokers
        $jokersRemoved = $this->jokerRepository->removeAllJokersForOlympix($olympixId);
        $io->text("✓ Removed {$jokersRemoved} jokers");

        // 4. Reset all games to pending status
        $games = $this->gameRepository->findByOlympixOrdered($olympixId);
        foreach ($games as $game) {
            $game->setStatus('pending');
            $this->entityManager->persist($game);
        }
        $io->text("✓ Reset " . count($games) . " games to pending status");

        // 5. Remove all tournaments
        $tournamentsRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\Tournament t WHERE t.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId)'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$tournamentsRemoved} tournaments");

        // 6. Reset player points and joker status
        $players = $this->playerRepository->findBy(['olympix' => $olympixId]);
        foreach ($players as $player) {
            $player->setTotalPoints(0);
            $player->setJokerDoubleUsed(false);
            $player->setJokerSwapUsed(false);
            $this->entityManager->persist($player);
        }
        $io->text("✓ Reset points and joker status for " . count($players) . " players");

        $this->entityManager->flush();
    }

    private function resetEverything(SymfonyStyle $io, int $olympixId): void
    {
        $io->text('Resetting EVERYTHING...');

        // 1. Remove all game results
        $resultsRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\GameResult gr WHERE gr.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId)'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$resultsRemoved} game results");

        // 2. Remove all quiz answers
        $quizAnswersRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\QuizAnswer qa WHERE qa.question IN (SELECT qq.id FROM App\Entity\QuizQuestion qq WHERE qq.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId))'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$quizAnswersRemoved} quiz answers");

        // 3. Remove all quiz questions
        $questionsRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\QuizQuestion qq WHERE qq.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId)'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$questionsRemoved} quiz questions");

        // 4. Remove all jokers
        $jokersRemoved = $this->jokerRepository->removeAllJokersForOlympix($olympixId);
        $io->text("✓ Removed {$jokersRemoved} jokers");

        // 5. Remove all tournaments
        $tournamentsRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\Tournament t WHERE t.game IN (SELECT g.id FROM App\Entity\Game g WHERE g.olympix = :olympixId)'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$tournamentsRemoved} tournaments");

        // 6. Remove all games
        $gamesRemoved = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\Game g WHERE g.olympix = :olympixId'
        )->setParameter('olympixId', $olympixId)->execute();
        $io->text("✓ Removed {$gamesRemoved} games");

        // 7. Reset player points and joker status
        $players = $this->playerRepository->findBy(['olympix' => $olympixId]);
        foreach ($players as $player) {
            $player->setTotalPoints(0);
            $player->setJokerDoubleUsed(false);
            $player->setJokerSwapUsed(false);
            $this->entityManager->persist($player);
        }
        $io->text("✓ Reset points and joker status for " . count($players) . " players");

        $this->entityManager->flush();
    }
}