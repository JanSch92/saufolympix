<?php

namespace App\Entity;

use App\Repository\QuizQuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizQuestionRepository::class)]
class QuizQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $question = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $correctAnswer = null;

    #[ORM\Column]
    private ?int $orderPosition = null;

    #[ORM\ManyToOne(inversedBy: 'quizQuestions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\OneToMany(mappedBy: 'quizQuestion', targetEntity: QuizAnswer::class, orphanRemoval: true)]
    private Collection $quizAnswers;

    public function __construct()
    {
        $this->quizAnswers = new ArrayCollection();
        $this->orderPosition = 1;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getCorrectAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function setCorrectAnswer(string $correctAnswer): static
    {
        $this->correctAnswer = $correctAnswer;

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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

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
            $quizAnswer->setQuizQuestion($this);
        }

        return $this;
    }

    public function removeQuizAnswer(QuizAnswer $quizAnswer): static
    {
        if ($this->quizAnswers->removeElement($quizAnswer)) {
            // set the owning side to null (unless already changed)
            if ($quizAnswer->getQuizQuestion() === $this) {
                $quizAnswer->setQuizQuestion(null);
            }
        }

        return $this;
    }

    public function calculateScores(): void
    {
        $answers = $this->quizAnswers->toArray();
        
        // Sort answers by distance from correct answer
        usort($answers, function($a, $b) {
            $distanceA = abs(floatval($a->getAnswer()) - floatval($this->correctAnswer));
            $distanceB = abs(floatval($b->getAnswer()) - floatval($this->correctAnswer));
            
            if ($distanceA === $distanceB) {
                return 0;
            }
            
            return $distanceA < $distanceB ? -1 : 1;
        });

        // Assign points based on ranking
        $totalPlayers = count($answers);
        for ($i = 0; $i < $totalPlayers; $i++) {
            $points = $totalPlayers - $i;
            $answers[$i]->setPointsEarned($points);
        }
    }

    public function hasAllAnswers(): bool
    {
        $playerCount = $this->game->getOlympix()->getPlayers()->count();
        return $this->quizAnswers->count() === $playerCount;
    }
}