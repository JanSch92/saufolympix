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

    public function __construct()
    {
        $this->gameResults = new ArrayCollection();
        $this->quizAnswers = new ArrayCollection();
        $this->jokers = new ArrayCollection();
        $this->targetJokers = new ArrayCollection();
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

    public function hasJokerDoubleAvailable(): bool
    {
        return !$this->jokerDoubleUsed;
    }

    public function hasJokerSwapAvailable(): bool
    {
        return !$this->jokerSwapUsed;
    }

public function calculateTotalPoints(): int
{
    $total = 0;
    foreach ($this->gameResults as $result) {
        $total += $result->getFinalPoints(); // Verwendet getFinalPoints() statt getPoints()
    }
    
    $this->totalPoints = $total;
    return $total;
}
}