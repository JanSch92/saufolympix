<?php

namespace App\Entity;

use App\Repository\QuizAnswerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAnswerRepository::class)]
class QuizAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $answer = null;

    #[ORM\Column]
    private ?int $pointsEarned = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $answeredAt = null;

    #[ORM\ManyToOne(inversedBy: 'quizAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?QuizQuestion $quizQuestion = null;

    #[ORM\ManyToOne(inversedBy: 'quizAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    public function __construct()
    {
        $this->answeredAt = new \DateTime();
        $this->pointsEarned = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }

    public function getPointsEarned(): ?int
    {
        return $this->pointsEarned;
    }

    public function setPointsEarned(int $pointsEarned): static
    {
        $this->pointsEarned = $pointsEarned;

        return $this;
    }

    public function getAnsweredAt(): ?\DateTimeInterface
    {
        return $this->answeredAt;
    }

    public function setAnsweredAt(\DateTimeInterface $answeredAt): static
    {
        $this->answeredAt = $answeredAt;

        return $this;
    }

    public function getQuizQuestion(): ?QuizQuestion
    {
        return $this->quizQuestion;
    }

    public function setQuizQuestion(?QuizQuestion $quizQuestion): static
    {
        $this->quizQuestion = $quizQuestion;

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

    public function getDistanceFromCorrectAnswer(): float
    {
        $correctAnswer = floatval($this->quizQuestion->getCorrectAnswer());
        $givenAnswer = floatval($this->answer);
        
        return abs($correctAnswer - $givenAnswer);
    }

    public function getFormattedAnswer(): string
    {
        return number_format(floatval($this->answer), 2);
    }
}