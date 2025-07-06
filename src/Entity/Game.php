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

    public function __construct()
    {
        $this->gameResults = new ArrayCollection();
        $this->quizQuestions = new ArrayCollection();
        $this->jokers = new ArrayCollection();
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

    public function getDefaultPointsDistribution(): array
    {
        if ($this->pointsDistribution) {
            return $this->pointsDistribution;
        }

        // Default points based on game type
        if ($this->isTournamentGame()) {
            return [6, 4, 2, 0]; // 1st, 2nd, 3rd, 4th place
        }

        // Free for all - points based on number of players
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
}