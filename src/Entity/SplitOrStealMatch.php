<?php

namespace App\Entity;

use App\Repository\SplitOrStealMatchRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SplitOrStealMatchRepository::class)]
class SplitOrStealMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'splitOrStealMatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player1 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $pointsAtStake = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $player1Choice = null; // 'split' or 'steal'

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $player2Choice = null; // 'split' or 'steal'

    #[ORM\Column(nullable: true)]
    private ?int $player1Points = null;

    #[ORM\Column(nullable: true)]
    private ?int $player2Points = null;

    #[ORM\Column]
    private ?bool $isCompleted = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isCompleted = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;
        return $this;
    }

    public function getPlayer1(): ?Player
    {
        return $this->player1;
    }

    public function setPlayer1(?Player $player1): static
    {
        $this->player1 = $player1;
        return $this;
    }

    public function getPlayer2(): ?Player
    {
        return $this->player2;
    }

    public function setPlayer2(?Player $player2): static
    {
        $this->player2 = $player2;
        return $this;
    }

    public function getPointsAtStake(): ?int
    {
        return $this->pointsAtStake;
    }

    public function setPointsAtStake(?int $pointsAtStake): static
    {
        $this->pointsAtStake = $pointsAtStake;
        return $this;
    }

    public function getPlayer1Choice(): ?string
    {
        return $this->player1Choice;
    }

    public function setPlayer1Choice(?string $player1Choice): static
    {
        $this->player1Choice = $player1Choice;
        return $this;
    }

    public function getPlayer2Choice(): ?string
    {
        return $this->player2Choice;
    }

    public function setPlayer2Choice(?string $player2Choice): static
    {
        $this->player2Choice = $player2Choice;
        return $this;
    }

    public function getPlayer1Points(): ?int
    {
        return $this->player1Points;
    }

    public function setPlayer1Points(?int $player1Points): static
    {
        $this->player1Points = $player1Points;
        return $this;
    }

    public function getPlayer2Points(): ?int
    {
        return $this->player2Points;
    }

    public function setPlayer2Points(?int $player2Points): static
    {
        $this->player2Points = $player2Points;
        return $this;
    }

    public function getIsCompleted(): ?bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function bothPlayersHaveChosen(): bool
    {
        return $this->player1Choice !== null && $this->player2Choice !== null;
    }

    public function calculatePoints(): void
    {
        if (!$this->bothPlayersHaveChosen()) {
            return;
        }

        $player1Split = $this->player1Choice === 'split';
        $player2Split = $this->player2Choice === 'split';

        if ($player1Split && $player2Split) {
            // Beide Split - jeder bekommt die Hälfte
            $this->player1Points = intval($this->pointsAtStake / 2);
            $this->player2Points = intval($this->pointsAtStake / 2);
        } elseif ($player1Split && !$player2Split) {
            // Player1 Split, Player2 Steal - Player2 bekommt alles
            $this->player1Points = 0;
            $this->player2Points = $this->pointsAtStake;
        } elseif (!$player1Split && $player2Split) {
            // Player1 Steal, Player2 Split - Player1 bekommt alles
            $this->player1Points = $this->pointsAtStake;
            $this->player2Points = 0;
        } else {
            // Beide Steal - keiner bekommt etwas
            $this->player1Points = 0;
            $this->player2Points = 0;
        }

        $this->isCompleted = true;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function getOtherPlayer(Player $player): ?Player
    {
        if ($this->player1 && $this->player1->getId() === $player->getId()) {
            return $this->player2;
        } elseif ($this->player2 && $this->player2->getId() === $player->getId()) {
            return $this->player1;
        }
        return null;
    }

    public function getPlayerChoice(Player $player): ?string
    {
        if ($this->player1 && $this->player1->getId() === $player->getId()) {
            return $this->player1Choice;
        } elseif ($this->player2 && $this->player2->getId() === $player->getId()) {
            return $this->player2Choice;
        }
        return null;
    }

    public function setPlayerChoice(Player $player, string $choice): void
    {
        if ($this->player1 && $this->player1->getId() === $player->getId()) {
            $this->player1Choice = $choice;
        } elseif ($this->player2 && $this->player2->getId() === $player->getId()) {
            $this->player2Choice = $choice;
        }
    }

    public function getPlayerPoints(Player $player): ?int
    {
        if ($this->player1 && $this->player1->getId() === $player->getId()) {
            return $this->player1Points;
        } elseif ($this->player2 && $this->player2->getId() === $player->getId()) {
            return $this->player2Points;
        }
        return null;
    }

    public function getResultDescription(): string
    {
        if (!$this->isCompleted) {
            return 'Noch nicht abgeschlossen';
        }

        $player1Split = $this->player1Choice === 'split';
        $player2Split = $this->player2Choice === 'split';

        if ($player1Split && $player2Split) {
            return 'Beide haben Split gewählt - Punkte geteilt';
        } elseif ($player1Split && !$player2Split) {
            return $this->player2->getName() . ' hat gestohlen!';
        } elseif (!$player1Split && $player2Split) {
            return $this->player1->getName() . ' hat gestohlen!';
        } else {
            return 'Beide haben Steal gewählt - keine Punkte';
        }
    }
}