<?php

namespace App\Entity;

use App\Repository\GamechangerThrowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GamechangerThrowRepository::class)]
class GamechangerThrow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'gamechangerThrows')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne(inversedBy: 'gamechangerThrows')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column]
    private ?int $thrownPoints = null;

    #[ORM\Column]
    private ?int $playerOrder = null;

    #[ORM\Column]
    private ?int $pointsScored = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $scoringReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $thrownAt = null;

    #[ORM\Column]
    private bool $isProcessed = false;

    public function __construct()
    {
        $this->thrownAt = new \DateTime();
        $this->pointsScored = 0;
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

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;
        return $this;
    }

    public function getThrownPoints(): ?int
    {
        return $this->thrownPoints;
    }

    public function setThrownPoints(int $thrownPoints): static
    {
        $this->thrownPoints = $thrownPoints;
        return $this;
    }

    public function getPlayerOrder(): ?int
    {
        return $this->playerOrder;
    }

    public function setPlayerOrder(int $playerOrder): static
    {
        $this->playerOrder = $playerOrder;
        return $this;
    }

    public function getPointsScored(): ?int
    {
        return $this->pointsScored;
    }

    public function setPointsScored(int $pointsScored): static
    {
        $this->pointsScored = $pointsScored;
        return $this;
    }

    public function getScoringReason(): ?string
    {
        return $this->scoringReason;
    }

    public function setScoringReason(?string $scoringReason): static
    {
        $this->scoringReason = $scoringReason;
        return $this;
    }

    public function getThrownAt(): ?\DateTimeInterface
    {
        return $this->thrownAt;
    }

    public function setThrownAt(\DateTimeInterface $thrownAt): static
    {
        $this->thrownAt = $thrownAt;
        return $this;
    }

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): static
    {
        $this->isProcessed = $isProcessed;
        return $this;
    }
}