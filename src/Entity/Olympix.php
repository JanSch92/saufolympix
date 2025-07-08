<?php

namespace App\Entity;

use App\Repository\OlympixRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OlympixRepository::class)]
class Olympix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\OneToMany(mappedBy: 'olympix', targetEntity: Player::class, orphanRemoval: true)]
    private Collection $players;

    #[ORM\OneToMany(mappedBy: 'olympix', targetEntity: Game::class, orphanRemoval: true)]
    private Collection $games;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->games = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->isActive = true;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return Collection<int, Player>
     */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
            $player->setOlympix($this);
        }
        return $this;
    }

    public function removePlayer(Player $player): static
    {
        if ($this->players->removeElement($player)) {
            // set the owning side to null (unless already changed)
            if ($player->getOlympix() === $this) {
                $player->setOlympix(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Game>
     */
    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game): static
    {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->setOlympix($this);
        }
        return $this;
    }

    public function removeGame(Game $game): static
    {
        if ($this->games->removeElement($game)) {
            // set the owning side to null (unless already changed)
            if ($game->getOlympix() === $this) {
                $game->setOlympix(null);
            }
        }
        return $this;
    }

    public function getCurrentGame(): ?Game
    {
        // Get games sorted by orderPosition
        $games = $this->games->toArray();
        usort($games, function($a, $b) {
            return $a->getOrderPosition() - $b->getOrderPosition();
        });

        // Find first active game
        foreach ($games as $game) {
            if ($game->getStatus() === 'active') {
                return $game;
            }
        }

        return null;
    }

    public function getNextGame(): ?Game
    {
        // Get games sorted by orderPosition
        $games = $this->games->toArray();
        usort($games, function($a, $b) {
            return $a->getOrderPosition() - $b->getOrderPosition();
        });

        // Find first pending game
        foreach ($games as $game) {
            if ($game->getStatus() === 'pending') {
                return $game;
            }
        }

        return null;
    }

    public function getGamesByOrder(): array
    {
        $games = $this->games->toArray();
        usort($games, function($a, $b) {
            return $a->getOrderPosition() - $b->getOrderPosition();
        });
        return $games;
    }

    public function getCompletedGamesCount(): int
    {
        $count = 0;
        foreach ($this->games as $game) {
            if ($game->getStatus() === 'completed') {
                $count++;
            }
        }
        return $count;
    }

    public function getTotalGamesCount(): int
    {
        return $this->games->count();
    }

    public function getProgress(): int
    {
        $total = $this->getTotalGamesCount();
        if ($total === 0) {
            return 0;
        }
        return round(($this->getCompletedGamesCount() / $total) * 100);
    }

    public function isCompleted(): bool
    {
        $total = $this->getTotalGamesCount();
        if ($total === 0) {
            return false;
        }
        return $this->getCompletedGamesCount() === $total;
    }

    public function getLeadingPlayer(): ?Player
    {
        $players = $this->players->toArray();
        if (empty($players)) {
            return null;
        }

        usort($players, function($a, $b) {
            return $b->getTotalPoints() - $a->getTotalPoints();
        });

        return $players[0];
    }
}