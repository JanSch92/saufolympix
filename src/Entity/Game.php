<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $gameType = null;

    #[ORM\Column(nullable: true)]
    private ?int $teamSize = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pointsDistribution = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column]
    private ?int $orderPosition = null;

    #[ORM\ManyToOne(inversedBy: 'games')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Olympix $olympix = null;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: GameResult::class, orphanRemoval: true)]
    private Collection $gameResults;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: QuizQuestion::class, orphanRemoval: true)]
    private Collection $quizQuestions;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Joker::class, orphanRemoval: true)]
    private Collection $jokers;

    #[ORM\OneToOne(mappedBy: 'game', cascade: ['persist', 'remove'])]
    private ?Tournament $tournament = null;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: SplitOrStealMatch::class, orphanRemoval: true)]
    private Collection $splitOrStealMatches;

    public function __construct()
    {
        $this->gameResults = new ArrayCollection();
        $this->quizQuestions = new ArrayCollection();
        $this->jokers = new ArrayCollection();
        $this->splitOrStealMatches = new ArrayCollection();
        $this->status = 'pending';
        $this->orderPosition = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getGameType(): ?string
    {
        return $this->gameType;
    }

    public function setGameType(string $gameType): static
    {
        $this->gameType = $gameType;
        return $this;
    }

    public function getTeamSize(): ?int
    {
        return $this->teamSize;
    }

    public function setTeamSize(?int $teamSize): static
    {
        $this->teamSize = $teamSize;
        return $this;
    }

    public function getPointsDistribution(): ?array
    {
        return $this->pointsDistribution;
    }

    public function setPointsDistribution(?array $pointsDistribution): static
    {
        $this->pointsDistribution = $pointsDistribution;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getOrderPosition(): ?int
    {
        return $this->orderPosition;
    }

    public function setOrderPosition(int $orderPosition): static
    {
        $this->orderPosition = $orderPosition;
        return $this;
    }

    public function getOlympix(): ?Olympix
    {
        return $this->olympix;
    }

    public function setOlympix(?Olympix $olympix): static
    {
        $this->olympix = $olympix;
        return $this;
    }

    /**
     * @return Collection<int, GameResult>
     */
    public function getGameResults(): Collection
    {
        return $this->gameResults;
    }

    public function addGameResult(GameResult $gameResult): static
    {
        if (!$this->gameResults->contains($gameResult)) {
            $this->gameResults->add($gameResult);
            $gameResult->setGame($this);
        }
        return $this;
    }

    public function removeGameResult(GameResult $gameResult): static
    {
        if ($this->gameResults->removeElement($gameResult)) {
            // set the owning side to null (unless already changed)
            if ($gameResult->getGame() === $this) {
                $gameResult->setGame(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    public function getQuizQuestions(): Collection
    {
        return $this->quizQuestions;
    }

    public function addQuizQuestion(QuizQuestion $quizQuestion): static
    {
        if (!$this->quizQuestions->contains($quizQuestion)) {
            $this->quizQuestions->add($quizQuestion);
            $quizQuestion->setGame($this);
        }
        return $this;
    }

    public function removeQuizQuestion(QuizQuestion $quizQuestion): static
    {
        if ($this->quizQuestions->removeElement($quizQuestion)) {
            // set the owning side to null (unless already changed)
            if ($quizQuestion->getGame() === $this) {
                $quizQuestion->setGame(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Joker>
     */
    public function getJokers(): Collection
    {
        return $this->jokers;
    }

    public function addJoker(Joker $joker): static
    {
        if (!$this->jokers->contains($joker)) {
            $this->jokers->add($joker);
            $joker->setGame($this);
        }
        return $this;
    }

    public function removeJoker(Joker $joker): static
    {
        if ($this->jokers->removeElement($joker)) {
            // set the owning side to null (unless already changed)
            if ($joker->getGame() === $this) {
                $joker->setGame(null);
            }
        }
        return $this;
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(Tournament $tournament): static
    {
        // set the owning side of the relation if necessary
        if ($tournament->getGame() !== $this) {
            $tournament->setGame($this);
        }
        $this->tournament = $tournament;
        return $this;
    }

    /**
     * @return Collection<int, SplitOrStealMatch>
     */
    public function getSplitOrStealMatches(): Collection
    {
        return $this->splitOrStealMatches;
    }

    public function addSplitOrStealMatch(SplitOrStealMatch $splitOrStealMatch): static
    {
        if (!$this->splitOrStealMatches->contains($splitOrStealMatch)) {
            $this->splitOrStealMatches->add($splitOrStealMatch);
            $splitOrStealMatch->setGame($this);
        }
        return $this;
    }

    public function removeSplitOrStealMatch(SplitOrStealMatch $splitOrStealMatch): static
    {
        if ($this->splitOrStealMatches->removeElement($splitOrStealMatch)) {
            // set the owning side to null (unless already changed)
            if ($splitOrStealMatch->getGame() === $this) {
                $splitOrStealMatch->setGame(null);
            }
        }
        return $this;
    }

    public function isQuizGame(): bool
    {
        return $this->gameType === 'quiz';
    }

    public function isTournamentGame(): bool
    {
        return in_array($this->gameType, ['tournament_team', 'tournament_single']);
    }

    public function isFreeForAllGame(): bool
    {
        return $this->gameType === 'free_for_all';
    }

    public function isSplitOrStealGame(): bool
    {
        return $this->gameType === 'split_or_steal';
    }

public function getDefaultPointsDistribution(): array
{
    // Use custom points distribution if set
    if ($this->pointsDistribution) {
        return $this->pointsDistribution;
    }

    // FIXED: Use dynamic values for tournaments based on player count
    if ($this->isTournamentGame()) {
        $playerCount = $this->olympix->getPlayers()->count();
        
        // Dynamische Punkteverteilung basierend auf Spieleranzahl
        $points = [];
        for ($i = 1; $i <= $playerCount; $i++) {
            if ($i == 1) {
                $points[] = 8; // 1. Platz
            } elseif ($i == 2) {
                $points[] = 6; // 2. Platz
            } elseif ($i == 3) {
                $points[] = 4; // 3. Platz
            } elseif ($i == 4) {
                $points[] = 2; // 4. Platz
            } else {
                $points[] = 0; // Alle anderen: 0 Punkte
            }
        }
        
        return $points;
    }

    // Split or Steal - default points per match
    if ($this->isSplitOrStealGame()) {
        return [50]; // Default 50 points per match
    }

    // Free for all - points based on number of players (descending)
    $playerCount = $this->olympix->getPlayers()->count();
    $points = [];
    for ($i = $playerCount; $i >= 1; $i--) {
        $points[] = $i;
    }
    return $points;
}

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeStarted(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'active';
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Wartend',
            'active' => 'Aktiv',
            'completed' => 'Abgeschlossen',
            default => 'Unbekannt'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'gray',
            'active' => 'blue',
            'completed' => 'green',
            default => 'gray'
        };
    }

    public function getGameTypeLabel(): string
    {
        return match($this->gameType) {
            'free_for_all' => 'Free For All',
            'tournament_team' => 'Turnier (Team)',
            'tournament_single' => 'Turnier (Einzel)',
            'quiz' => 'Quiz',
            'split_or_steal' => 'Split or Steal',
            default => 'Unbekannt'
        };
    }

    public function getExpectedDuration(): int
    {
        // Return expected duration in minutes
        return match($this->gameType) {
            'free_for_all' => 30,
            'tournament_team' => 60,
            'tournament_single' => 45,
            'quiz' => 15,
            'split_or_steal' => 10,
            default => 30
        };
    }

    public function getMaxPlayers(): ?int
    {
        if ($this->isTournamentGame() && $this->teamSize) {
            return 16; // Max 16 players for tournaments
        }
        return null; // No limit for other game types
    }

    public function getMinPlayers(): int
    {
        if ($this->gameType === 'tournament_team' && $this->teamSize) {
            return $this->teamSize * 2; // At least 2 teams
        }
        if ($this->gameType === 'split_or_steal') {
            return 2; // At least 2 players for split or steal
        }
        return 2; // At least 2 players for all other games
    }

    public function canStart(): bool
    {
        $playerCount = $this->olympix->getPlayers()->count();
        return $playerCount >= $this->getMinPlayers() && $this->canBeStarted();
    }

    public function hasResults(): bool
    {
        return $this->gameResults->count() > 0;
    }

    public function getResultsCount(): int
    {
        return $this->gameResults->count();
    }

    public function getJokersCount(): int
    {
        return $this->jokers->count();
    }

    public function getActiveJokersCount(): int
    {
        $count = 0;
        foreach ($this->jokers as $joker) {
            if ($joker->isIsUsed()) {
                $count++;
            }
        }
        return $count;
    }

    public function getSplitOrStealMatchesCount(): int
    {
        return $this->splitOrStealMatches->count();
    }

    public function getCompletedSplitOrStealMatchesCount(): int
    {
        return $this->splitOrStealMatches->filter(fn($match) => $match->getIsCompleted())->count();
    }

    public function canBeEvaluated(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->isSplitOrStealGame()) {
            // Check if all matches have both players chosen
            foreach ($this->splitOrStealMatches as $match) {
                if (!$match->bothPlayersHaveChosen()) {
                    return false;
                }
            }
            return $this->splitOrStealMatches->count() > 0;
        }

        return true;
    }

    public function needsSetup(): bool
    {
        if ($this->isSplitOrStealGame()) {
            return $this->splitOrStealMatches->count() === 0;
        }
        
        if ($this->isTournamentGame()) {
            return $this->tournament === null;
        }
        
        if ($this->isQuizGame()) {
            return $this->quizQuestions->count() === 0;
        }
        
        return false;
    }

    public function getSetupUrl(): ?string
    {
        if ($this->isSplitOrStealGame()) {
            return '/split-or-steal/setup/' . $this->id;
        }
        
        if ($this->isQuizGame()) {
            return '/quiz/questions/' . $this->id;
        }
        
        return null;
    }

    public function getWinner(): ?string
    {
        if (!$this->isCompleted() || $this->gameResults->isEmpty()) {
            return null;
        }

        $results = $this->gameResults->toArray();
        usort($results, function($a, $b) {
            return $a->getPosition() - $b->getPosition();
        });

        return $results[0]->getPlayer()->getName();
    }

    public function getTopPlayers(int $limit = 3): array
    {
        if (!$this->isCompleted() || $this->gameResults->isEmpty()) {
            return [];
        }

        $results = $this->gameResults->toArray();
        usort($results, function($a, $b) {
            return $a->getPosition() - $b->getPosition();
        });

        return array_slice($results, 0, $limit);
    }

    public function getProgress(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        if ($this->isActive()) {
            if ($this->isTournamentGame() && $this->tournament) {
                $bracketData = $this->tournament->getBracketData();
                $totalMatches = 0;
                $completedMatches = 0;

                foreach ($bracketData['rounds'] as $round) {
                    foreach ($round as $match) {
                        $totalMatches++;
                        if ($match['completed']) {
                            $completedMatches++;
                        }
                    }
                }

                if (isset($bracketData['thirdPlaceMatch'])) {
                    $totalMatches++;
                    if ($bracketData['thirdPlaceMatch']['completed']) {
                        $completedMatches++;
                    }
                }

                return $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100) : 0;
            }

            if ($this->isSplitOrStealGame()) {
                $totalMatches = $this->splitOrStealMatches->count();
                $completedMatches = $this->getCompletedSplitOrStealMatchesCount();
                return $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100) : 0;
            }

            return 50; // 50% for active non-tournament games
        }

        return 0; // Pending games
    }

    public function requiresQuestions(): bool
    {
        return $this->isQuizGame();
    }

    public function hasSufficientQuestions(): bool
    {
        if (!$this->requiresQuestions()) {
            return true;
        }

        return $this->quizQuestions->count() >= 3; // At least 3 questions for quiz
    }

    public function getValidationErrors(): array
    {
        $errors = [];

        if (empty($this->name)) {
            $errors[] = 'Spielname ist erforderlich';
        }

        if ($this->requiresQuestions() && !$this->hasSufficientQuestions()) {
            $errors[] = 'Quiz benötigt mindestens 3 Fragen';
        }

        if (!$this->canStart()) {
            $errors[] = 'Zu wenig Spieler für diesen Spieltyp';
        }

        if ($this->isSplitOrStealGame() && $this->splitOrStealMatches->count() === 0) {
            $errors[] = 'Split or Steal benötigt mindestens ein Match';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->getValidationErrors());
    }
}