<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Player;
use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use PHPUnit\Framework\TestCase;

class QuizQuestionTest extends TestCase
{
    private function createAnswer(QuizQuestion $question, string $value, string $playerName): QuizAnswer
    {
        $player = new Player();
        $player->setName($playerName);

        $answer = new QuizAnswer();
        $answer->setPlayer($player);
        $answer->setAnswer($value);
        $question->addQuizAnswer($answer);

        return $answer;
    }

    public function testCalculateScoresClosestGetsMostPoints(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Wie viele Bundesstaaten haben die USA?');
        $question->setCorrectAnswer('50');

        $exact = $this->createAnswer($question, '50', 'Anna');      // Abweichung 0
        $close = $this->createAnswer($question, '48', 'Ben');       // Abweichung 2
        $far = $this->createAnswer($question, '30', 'Clara');       // Abweichung 20

        $question->calculateScores();

        $this->assertSame(3, $exact->getPointsEarned());
        $this->assertSame(2, $close->getPointsEarned());
        $this->assertSame(1, $far->getPointsEarned());
    }

    public function testCalculateScoresOverAndUnderEstimateSameDistance(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Testfrage');
        $question->setCorrectAnswer('100');

        $over = $this->createAnswer($question, '110', 'Über');   // Abweichung 10
        $under = $this->createAnswer($question, '95', 'Unter');  // Abweichung 5

        $question->calculateScores();

        $this->assertSame(2, $under->getPointsEarned());
        $this->assertSame(1, $over->getPointsEarned());
    }

    public function testCalculateScoresWithDecimalAnswers(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Zielzeit?');
        $question->setCorrectAnswer('20.86');

        $a = $this->createAnswer($question, '20.90', 'A'); // 0.04
        $b = $this->createAnswer($question, '20.50', 'B'); // 0.36

        $question->calculateScores();

        $this->assertSame(2, $a->getPointsEarned());
        $this->assertSame(1, $b->getPointsEarned());
    }

    public function testCalculateScoresSinglePlayer(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Solo');
        $question->setCorrectAnswer('10');

        $only = $this->createAnswer($question, '99', 'Solo');

        $question->calculateScores();

        $this->assertSame(1, $only->getPointsEarned());
    }

    public function testCalculateScoresNoAnswersDoesNotCrash(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Leer');
        $question->setCorrectAnswer('1');

        $question->calculateScores();

        $this->assertCount(0, $question->getQuizAnswers());
    }
}
