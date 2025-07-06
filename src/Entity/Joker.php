<?php

namespace App\Entity;

use App\Repository\JokerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokerRepository::class)]
class Joker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $jokerType = null;

    #[ORM\Column]
    private ?bool $isUsed = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\ManyToOne(inversedBy: 'jokers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\ManyToOne(inversedBy: 'jokers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne(inversedBy: 'targetJokers')]
    private ?Player $targetPlayer = null;

    public function __construct()
    {
        $this->isUsed = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJokerType(): ?string
    {
        return $this->jokerType;
    }

    public function setJokerType(string $jokerType): static
    {
        $this->jokerType = $jokerType;

        return $this;
    }

    public function isIsUsed(): ?bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): static
    {
        $this->isUsed = $isUsed;

        return $this;
    }

    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }

    public function setUsedAt(\DateTimeInterface $usedAt): static
    {
        $this->usedAt = $usedAt;

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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getTargetPlayer(): ?Player
    {
        return $this->targetPlayer;
    }

    public function setTargetPlayer(?Player $targetPlayer): static
    {
        $this->targetPlayer = $targetPlayer;

        return $this;
    }

    public function isDoubleJoker(): bool
    {
        return $this->jokerType === 'double';
    }

    public function isSwapJoker(): bool
    {
        return $this->jokerType === 'swap';
    }

    public function use(): static
    {
        $this->isUsed = true;
        $this->usedAt = new \DateTime();

        return $this;
    }

    public function canBeUsed(): bool
    {
        return !$this->isUsed;
    }

    public function getJokerTypeLabel(): string
    {
        return match($this->jokerType) {
            'double' => 'Doppelte Punkte',
            'swap' => 'Punkte tauschen',
            default => 'Unbekannt'
        };
    }
}