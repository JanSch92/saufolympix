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

    public function testCalculateScoresSameAnswerGetsSamePoints(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Wie viele Bundesstaaten haben die USA?');
        $question->setCorrectAnswer('50');

        $tieA = $this->createAnswer($question, '48', 'Jannik');  // Abweichung 2
        $tieB = $this->createAnswer($question, '48', 'Hans');    // Abweichung 2 (gleiche Antwort!)
        $far = $this->createAnswer($question, '30', 'Clara');    // Abweichung 20

        $question->calculateScores();

        $this->assertSame(3, $tieA->getPointsEarned(), 'Gleiche Antwort muss gleiche Punkte geben');
        $this->assertSame(3, $tieB->getPointsEarned(), 'Gleiche Antwort muss gleiche Punkte geben');
        $this->assertSame(1, $far->getPointsEarned(), 'Nach einem Gleichstand wird der Platz übersprungen (1-1-3)');
    }

    public function testCalculateScoresSameDistanceOverAndUnderGetsSamePoints(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Testfrage');
        $question->setCorrectAnswer('100');

        $over = $this->createAnswer($question, '105', 'Über');   // Abweichung 5
        $under = $this->createAnswer($question, '95', 'Unter');  // Abweichung 5

        $question->calculateScores();

        $this->assertSame(2, $over->getPointsEarned());
        $this->assertSame(2, $under->getPointsEarned());
    }

    public function testCalculateScoresAllTiedAllGetFullPoints(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Alle gleich');
        $question->setCorrectAnswer('10');

        $a = $this->createAnswer($question, '12', 'A');
        $b = $this->createAnswer($question, '12', 'B');
        $c = $this->createAnswer($question, '12', 'C');

        $question->calculateScores();

        $this->assertSame(3, $a->getPointsEarned());
        $this->assertSame(3, $b->getPointsEarned());
        $this->assertSame(3, $c->getPointsEarned());
    }

    public function testCalculateScoresTieGetsEqualPoints(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Gleichstand');
        $question->setCorrectAnswer('12');

        $tieA = $this->createAnswer($question, '12', 'Jannik');  // Abweichung 0
        $tieB = $this->createAnswer($question, '12', 'Hans');    // Abweichung 0 -> gleiche Punkte!
        $far = $this->createAnswer($question, '99', 'Clara');    // Abweichung 87

        $question->calculateScores();

        $this->assertSame(3, $tieA->getPointsEarned());
        $this->assertSame(3, $tieB->getPointsEarned(), 'Gleiche Antwort muss gleiche Punkte geben');
        $this->assertSame(1, $far->getPointsEarned(), 'Nach einem 2er-Gleichstand wird Platz 2 übersprungen');
    }

    public function testCalculateScoresTieOnSameDistanceDifferentSide(): void
    {
        $question = new QuizQuestion();
        $question->setQuestion('Abstand gleich');
        $question->setCorrectAnswer('100');

        $under = $this->createAnswer($question, '90', 'Unter');  // Abweichung 10
        $over = $this->createAnswer($question, '110', 'Über');   // Abweichung 10 -> gleich

        $question->calculateScores();

        $this->assertSame(2, $under->getPointsEarned());
        $this->assertSame(2, $over->getPointsEarned(), 'Gleicher Abstand (drüber/drunter) muss gleiche Punkte geben');
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
