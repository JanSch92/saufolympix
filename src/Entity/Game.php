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

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: GamechangerThrow::class, orphanRemoval: true)]
    private Collection $gamechangerThrows;

    public function __construct()
    {
        $this->gameResults = new ArrayCollection();
        $this->quizQuestions = new ArrayCollection();
        $this->jokers = new ArrayCollection();
        $this->splitOrStealMatches = new ArrayCollection();
        $this->gamechangerThrows = new ArrayCollection();
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
            if ($splitOrStealMatch->getGame() === $this) {
                $splitOrStealMatch->setGame(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, GamechangerThrow>
     */
    public function getGamechangerThrows(): Collection
    {
        return $this->gamechangerThrows;
    }

    public function addGamechangerThrow(GamechangerThrow $gamechangerThrow): static
    {
        if (!$this->gamechangerThrows->contains($gamechangerThrow)) {
            $this->gamechangerThrows->add($gamechangerThrow);
            $gamechangerThrow->setGame($this);
        }
        return $this;
    }

    public function removeGamechangerThrow(GamechangerThrow $gamechangerThrow): static
    {
        if ($this->gamechangerThrows->removeElement($gamechangerThrow)) {
            if ($gamechangerThrow->getGame() === $this) {
                $gamechangerThrow->setGame(null);
            }
        }
        return $this;
    }

    // *** GAME TYPE CHECK METHODS ***

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

    public function isGamechangerGame(): bool
    {
        return $this->gameType === 'gamechanger';
    }

    // *** STATUS CHECK METHODS ***

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
            'gamechanger' => 'Gamechanger',
            default => 'Unbekannt'
        };
    }

    public function getExpectedDuration(): int
    {
        return match($this->gameType) {
            'free_for_all' => 30,
            'tournament_team' => 60,
            'tournament_single' => 45,
            'quiz' => 15,
            'split_or_steal' => 10,
            'gamechanger' => 15,
            default => 30
        };
    }

    public function getDefaultPointsDistribution(): array
    {
        if ($this->pointsDistribution) {
            return $this->pointsDistribution;
        }

        if ($this->isGamechangerGame()) {
            return []; // Leer, da Punkte während des Spiels dynamisch vergeben werden
        }

        if ($this->isTournamentGame()) {
            $playerCount = $this->olympix->getPlayers()->count();
            
            $points = [];
            for ($i = 1; $i <= $playerCount; $i++) {
                if ($i == 1) {
                    $points[] = 8;
                } elseif ($i == 2) {
                    $points[] = 6;
                } elseif ($i == 3) {
                    $points[] = 4;
                } elseif ($i == 4) {
                    $points[] = 2;
                } else {
                    $points[] = 0;
                }
            }
            
            return $points;
        }

        if ($this->isSplitOrStealGame()) {
            return [50];
        }

        $playerCount = $this->olympix->getPlayers()->count();
        $points = [];
        for ($i = $playerCount; $i >= 1; $i--) {
            $points[] = $i;
        }
        return $points;
    }

    // *** UTILITY METHODS ***

    public function getMaxPlayers(): ?int
    {
        if ($this->isTournamentGame() && $this->teamSize) {
            return 16;
        }
        return null;
    }

    public function getMinPlayers(): int
    {
        if ($this->gameType === 'tournament_team' && $this->teamSize) {
            return $this->teamSize * 2;
        }
        if ($this->gameType === 'split_or_steal') {
            return 2;
        }
        if ($this->gameType === 'gamechanger') {
            return 2;
        }
        return 2;
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

    public function hasMatches(): bool
    {
        return $this->splitOrStealMatches->count() > 0;
    }

    public function canBeEvaluated(): bool
    {
        if (!$this->isSplitOrStealGame()) {
            return false;
        }

        foreach ($this->splitOrStealMatches as $match) {
            if (!$match->isIsCompleted()) {
                return false;
            }
        }

        return true;
    }

    public function needsSetup(): bool
    {
        if ($this->isTournamentGame()) {
            return !$this->tournament || !$this->tournament->isInitialized();
        }

        if ($this->isSplitOrStealGame()) {
            return $this->splitOrStealMatches->count() === 0;
        }

        if ($this->isGamechangerGame()) {
            return $this->gamechangerThrows->count() === 0;
        }

        return false;
    }

    public function getSetupUrl(): ?string
    {
        if ($this->isTournamentGame()) {
            return '/tournament/setup/' . $this->id;
        }

        if ($this->isSplitOrStealGame()) {
            return '/split-or-steal/setup/' . $this->id;
        }

        if ($this->isGamechangerGame()) {
            return '/gamechanger/setup/' . $this->id;
        }

        return null;
    }

    public function getPlayUrl(): ?string
    {
        if ($this->isActive()) {
            if ($this->isTournamentGame()) {
                return '/game/bracket/' . $this->id;
            }

            if ($this->isSplitOrStealGame()) {
                return '/split-or-steal/manage/' . $this->id;
            }

            if ($this->isQuizGame()) {
                return '/quiz/manage/' . $this->id;
            }

            if ($this->isGamechangerGame()) {
                return '/gamechanger/play/' . $this->id;
            }

            return '/game/results/' . $this->id;
        }

        return null;
    }

    public function getParticipantCount(): int
    {
        return $this->olympix->getPlayers()->count();
    }

    public function getFormattedPointsDistribution(): string
    {
        $distribution = $this->getDefaultPointsDistribution();
        return empty($distribution) ? 'Dynamisch' : implode(',', $distribution);
    }

    public function isStartable(): bool
    {
        return $this->canStart() && !$this->needsSetup();
    }

    /**
     * GEFIXT: Gamechanger Progress Percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        if ($this->isActive()) {
            if ($this->isTournamentGame() && $this->tournament) {
                return $this->tournament->getProgressPercentage();
            }

            if ($this->isSplitOrStealGame()) {
                $total = $this->splitOrStealMatches->count();
                if ($total === 0) return 0;
                
                $completed = 0;
                foreach ($this->splitOrStealMatches as $match) {
                    if ($match->isIsCompleted()) {
                        $completed++;
                    }
                }
                
                return round(($completed / $total) * 100);
            }

            if ($this->isGamechangerGame()) {
                $totalPlayers = $this->olympix->getPlayers()->count();
                if ($totalPlayers === 0) return 0;
                
                // GEFIXT: Zähle nur echte Würfe (thrownPoints > 0)
                $realThrows = 0;
                foreach ($this->gamechangerThrows as $throw) {
                    if ($throw->getThrownPoints() > 0) {
                        $realThrows++;
                    }
                }
                
                return round(($realThrows / $totalPlayers) * 100);
            }

            return 50;
        }

        return 0;
    }
}