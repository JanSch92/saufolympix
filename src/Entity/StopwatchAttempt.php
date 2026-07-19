<?php

namespace App\Entity;

use App\Repository\StopwatchAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StopwatchAttemptRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_stopwatch_attempt_game_player', columns: ['game_id', 'player_id'])]
class StopwatchAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stopwatchAttempts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $elapsedSeconds = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getElapsedSeconds(): ?string
    {
        return $this->elapsedSeconds;
    }

    public function setElapsedSeconds(string $elapsedSeconds): static
    {
        $this->elapsedSeconds = $elapsedSeconds;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Abweichung von der Zielzeit des Spiels in Sekunden (immer positiv).
     */
    public function getDeviation(): float
    {
        $target = (float) ($this->game?->getStopwatchTarget() ?? 0);

        return round(abs((float) $this->elapsedSeconds - $target), 2);
    }
}
