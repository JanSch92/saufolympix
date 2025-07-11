<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $totalPoints = null;

    #[ORM\Column]
    private ?bool $jokerDoubleUsed = null;

    #[ORM\Column]
    private ?bool $jokerSwapUsed = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Olympix $olympix = null;

    #[ORM\OneToMany(mappedBy: 'player', targetEntity: GameResult::class, orphanRemoval: true)]
    private Collection $gameResults;

    #[ORM\OneToMany(mappedBy: 'player', targetEntity: QuizAnswer::class, orphanRemoval: true)]
    private Collection $quizAnswers;

    #[ORM\OneToMany(mappedBy: 'player', targetEntity: Joker::class, orphanRemoval: true)]
    private Collection $jokers;

    #[ORM\OneToMany(mappedBy: 'targetPlayer', targetEntity: Joker::class)]
    private Collection $targetJokers;

    // *** NEU: GAMECHANGER RELATIONSHIP ***
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: GamechangerThrow::class, orphanRemoval: true)]
    private Collection $gamechangerThrows;

    public function __construct()
    {
        $this->gameResults = new ArrayCollection();
        $this->quizAnswers = new ArrayCollection();
        $this->jokers = new ArrayCollection();
        $this->targetJokers = new ArrayCollection();
        $this->gamechangerThrows = new ArrayCollection(); // *** NEU ***
        $this->totalPoints = 0;
        $this->jokerDoubleUsed = false;
        $this->jokerSwapUsed = false;
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

    public function getTotalPoints(): ?int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;

        return $this;
    }

    public function addPoints(int $points): static
    {
        $this->totalPoints += $points;

        return $this;
    }

    public function isJokerDoubleUsed(): ?bool
    {
        return $this->jokerDoubleUsed;
    }

    public function setJokerDoubleUsed(bool $jokerDoubleUsed): static
    {
        $this->jokerDoubleUsed = $jokerDoubleUsed;

        return $this;
    }

    public function isJokerSwapUsed(): ?bool
    {
        return $this->jokerSwapUsed;
    }

    public function setJokerSwapUsed(bool $jokerSwapUsed): static
    {
        $this->jokerSwapUsed = $jokerSwapUsed;

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
            $gameResult->setPlayer($this);
        }

        return $this;
    }

    public function removeGameResult(GameResult $gameResult): static
    {
        if ($this->gameResults->removeElement($gameResult)) {
            // set the owning side to null (unless already changed)
            if ($gameResult->getPlayer() === $this) {
                $gameResult->setPlayer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QuizAnswer>
     */
    public function getQuizAnswers(): Collection
    {
        return $this->quizAnswers;
    }

    public function addQuizAnswer(QuizAnswer $quizAnswer): static
    {
        if (!$this->quizAnswers->contains($quizAnswer)) {
            $this->quizAnswers->add($quizAnswer);
            $quizAnswer->setPlayer($this);
        }

        return $this;
    }

    public function removeQuizAnswer(QuizAnswer $quizAnswer): static
    {
        if ($this->quizAnswers->removeElement($quizAnswer)) {
            // set the owning side to null (unless already changed)
            if ($quizAnswer->getPlayer() === $this) {
                $quizAnswer->setPlayer(null);
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
            $joker->setPlayer($this);
        }

        return $this;
    }

    public function removeJoker(Joker $joker): static
    {
        if ($this->jokers->removeElement($joker)) {
            // set the owning side to null (unless already changed)
            if ($joker->getPlayer() === $this) {
                $joker->setPlayer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Joker>
     */
    public function getTargetJokers(): Collection
    {
        return $this->targetJokers;
    }

    public function addTargetJoker(Joker $targetJoker): static
    {
        if (!$this->targetJokers->contains($targetJoker)) {
            $this->targetJokers->add($targetJoker);
            $targetJoker->setTargetPlayer($this);
        }

        return $this;
    }

    public function removeTargetJoker(Joker $targetJoker): static
    {
        if ($this->targetJokers->removeElement($targetJoker)) {
            // set the owning side to null (unless already changed)
            if ($targetJoker->getTargetPlayer() === $this) {
                $targetJoker->setTargetPlayer(null);
            }
        }

        return $this;
    }

    // *** NEUE GAMECHANGER METHODEN ***

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
            $gamechangerThrow->setPlayer($this);
        }
        return $this;
    }

    public function removeGamechangerThrow(GamechangerThrow $gamechangerThrow): static
    {
        if ($this->gamechangerThrows->removeElement($gamechangerThrow)) {
            if ($gamechangerThrow->getPlayer() === $this) {
                $gamechangerThrow->setPlayer(null);
            }
        }
        return $this;
    }

    public function getGamechangerThrowsForGame(int $gameId): Collection
    {
        return $this->gamechangerThrows->filter(function(GamechangerThrow $throw) use ($gameId) {
            return $throw->getGame()->getId() === $gameId;
        });
    }

    public function hasGamechangerThrowForGame(int $gameId): bool
    {
        return !$this->getGamechangerThrowsForGame($gameId)->isEmpty();
    }

    public function getGamechangerStats(): array
    {
        $totalThrows = $this->gamechangerThrows->count();
        $bonusHits = 0;
        $penaltyHits = 0;
        $totalBonusPoints = 0;
        $totalPenaltyPoints = 0;

        foreach ($this->gamechangerThrows as $throw) {
            $reason = $throw->getScoringReason() ?? '';
            if (str_contains($reason, 'Eigene Punkte')) {
                $bonusHits++;
                $totalBonusPoints += $throw->getPointsScored();
            } elseif (str_contains($reason, 'getroffen')) {
                $penaltyHits++;
                $totalPenaltyPoints += abs($throw->getPointsScored());
            }
        }

        return [
            'totalThrows' => $totalThrows,
            'bonusHits' => $bonusHits,
            'penaltyHits' => $penaltyHits,
            'totalBonusPoints' => $totalBonusPoints,
            'totalPenaltyPoints' => $totalPenaltyPoints,
            'accuracy' => $totalThrows > 0 ? round(($bonusHits / $totalThrows) * 100, 1) : 0
        ];
    }

    // *** BESTEHENDE JOKER AVAILABILITY METHODS ***

    public function hasJokerDoubleAvailable(): bool
    {
        return !$this->jokerDoubleUsed;
    }

    public function hasJokerSwapAvailable(): bool
    {
        return !$this->jokerSwapUsed;
    }

    // *** BESTEHENDE POINT CALCULATION METHODS ***

    public function calculateTotalPoints(): void
    {
        $totalPoints = 0;
        
        foreach ($this->gameResults as $gameResult) {
            $totalPoints += $gameResult->getFinalPoints();
        }
        
        $this->totalPoints = $totalPoints;
    }

    public function addPointsFromGame(int $points): void
    {
        $this->totalPoints += $points;
    }

    public function subtractPointsFromGame(int $points): void
    {
        $this->totalPoints = max(0, $this->totalPoints - $points);
    }

    // *** BESTEHENDE UTILITY METHODS ***

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function getRanking(): int
    {
        // This would typically be calculated in a service or repository
        // based on comparison with other players in the same olympix
        return 1;
    }

    public function getCompletedGamesCount(): int
    {
        return $this->gameResults->filter(function(GameResult $result) {
            return $result->getGame()->isCompleted();
        })->count();
    }

    public function getPendingGamesCount(): int
    {
        $olympixGames = $this->olympix->getGames();
        $completedGameIds = $this->gameResults->map(function(GameResult $result) {
            return $result->getGame()->getId();
        })->toArray();

        return $olympixGames->filter(function(Game $game) use ($completedGameIds) {
            return !in_array($game->getId(), $completedGameIds) && $game->isPending();
        })->count();
    }

    public function getActiveGamesCount(): int
    {
        $olympixGames = $this->olympix->getGames();
        return $olympixGames->filter(function(Game $game) {
            return $game->isActive();
        })->count();
    }
}