<?php

namespace App\Tests\Unit\Service;

use App\Service\QuizQuestionPool;
use PHPUnit\Framework\TestCase;

class QuizQuestionPoolTest extends TestCase
{
    public function testPoolHasEnoughQuestions(): void
    {
        $this->assertGreaterThanOrEqual(150, count(QuizQuestionPool::all()));
    }

    public function testAllQuestionsHaveIntegerAnswersAndText(): void
    {
        foreach (QuizQuestionPool::all() as $entry) {
            $this->assertArrayHasKey('question', $entry);
            $this->assertArrayHasKey('answer', $entry);
            $this->assertIsInt($entry['answer'], 'Antwort muss ganzzahlig sein: ' . $entry['question']);
            $this->assertNotSame('', trim($entry['question']));
            $this->assertStringEndsWith('?', trim($entry['question']));
        }
    }

    public function testQuestionsAreUnique(): void
    {
        $questions = array_column(QuizQuestionPool::all(), 'question');

        $this->assertSame(count($questions), count(array_unique($questions)), 'Fragenpool enthält Duplikate');
    }

    public function testRandomReturnsRequestedCountWithoutDuplicates(): void
    {
        $random = QuizQuestionPool::random(10);

        $this->assertCount(10, $random);

        $texts = array_column($random, 'question');
        $this->assertSame(count($texts), count(array_unique($texts)));
    }

    public function testRandomIsCappedAtPoolSize(): void
    {
        $poolSize = count(QuizQuestionPool::all());

        $this->assertCount($poolSize, QuizQuestionPool::random($poolSize + 500));
    }
}
