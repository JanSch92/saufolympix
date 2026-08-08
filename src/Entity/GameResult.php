<?php

namespace App\Entity;

use App\Repository\GameResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameResultRepository::class)]
class GameResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $points = null;

    #[ORM\Column]
    private ?int $position = null;

    #[ORM\Column(nullable: true)]
    private ?int $teamId = null;

    #[ORM\ManyToOne(inversedBy: 'gameResults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne(inversedBy: 'gameResults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column]
    private ?bool $jokerDoubleApplied = null;

    // Vom Admin vergebene Extrapunkte (mit Begründung) — zählen in die
    // Endpunkte und bleiben beim Spieler (werden NICHT mitgetauscht)
    #[ORM\Column(options: ['default' => 0])]
    private int $bonusPoints = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bonusReason = null;

    public function __construct()
    {
        $this->jokerDoubleApplied = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getTeamId(): ?int
    {
        return $this->teamId;
    }

    public function setTeamId(?int $teamId): static
    {
        $this->teamId = $teamId;

        return $this;
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

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function isJokerDoubleApplied(): ?bool
    {
        return $this->jokerDoubleApplied;
    }

    public function setJokerDoubleApplied(bool $jokerDoubleApplied): static
    {
        $this->jokerDoubleApplied = $jokerDoubleApplied;

        return $this;
    }

    public function getBonusPoints(): int
    {
        return $this->bonusPoints;
    }

    public function setBonusPoints(int $bonusPoints): static
    {
        $this->bonusPoints = $bonusPoints;

        return $this;
    }

    public function getBonusReason(): ?string
    {
        return $this->bonusReason;
    }

    public function setBonusReason(?string $bonusReason): static
    {
        $this->bonusReason = $bonusReason;

        return $this;
    }

    public function getFinalPoints(): int
    {
        $basePoints = $this->points;

        if ($this->jokerDoubleApplied) {
            $basePoints *= 2;
        }

        return $basePoints + $this->bonusPoints;
    }

    public function applyDoubleJoker(): static
    {
        $this->jokerDoubleApplied = true;
        return $this;
    }
}