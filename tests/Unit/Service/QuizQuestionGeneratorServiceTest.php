<?php

namespace App\Tests\Unit\Service;

use App\Service\QuizQuestionGeneratorService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class QuizQuestionGeneratorServiceTest extends TestCase
{
    private function openAiResponse(array $questions): MockResponse
    {
        return new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => json_encode(['questions' => $questions])]],
            ],
        ]));
    }

    public function testWithoutApiKeyUsesFallbackPoolWithoutHttpCall(): void
    {
        $client = new MockHttpClient(function () {
            $this->fail('Ohne API-Key darf kein HTTP-Request abgesetzt werden');
        });

        $service = new QuizQuestionGeneratorService($client, new NullLogger(), null, null);

        $this->assertFalse($service->isOpenAiConfigured());

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions);
        foreach ($questions as $entry) {
            $this->assertNotSame('', $entry['question']);
            $this->assertMatchesRegularExpression('/^-?\d+$/', $entry['answer']);
        }
    }

    public function testSuccessfulOpenAiResponseIsUsed(): void
    {
        $generated = [];
        for ($i = 1; $i <= 10; $i++) {
            $generated[] = ['question' => "Frage $i?", 'answer' => $i * 3];
        }

        $client = new MockHttpClient($this->openAiResponse($generated));
        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', 'gpt-4o-mini');

        $this->assertTrue($service->isOpenAiConfigured());

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions);
        $this->assertSame('Frage 1?', $questions[0]['question']);
        $this->assertSame('3', $questions[0]['answer']);
    }

    public function testInvalidJsonFallsBackToPool(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'choices' => [['message' => ['content' => 'kein json hier']]],
        ])));

        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', null);

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions);
    }

    public function testHttpErrorFallsBackToPool(): void
    {
        $client = new MockHttpClient(new MockResponse('Server Error', ['http_code' => 500]));

        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', null);

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions);
    }

    public function testTooFewValidQuestionsFallsBackToPool(): void
    {
        // Nur 3 gültige Fragen, Rest ungültig (nicht numerisch / leer)
        $client = new MockHttpClient($this->openAiResponse([
            ['question' => 'A?', 'answer' => 1],
            ['question' => 'B?', 'answer' => 2],
            ['question' => 'C?', 'answer' => 3],
            ['question' => 'D?', 'answer' => 'keine Zahl'],
            ['question' => '', 'answer' => 5],
        ]));

        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', null);

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions);
    }

    public function testNonIntegerAnswersAreRounded(): void
    {
        $generated = [];
        for ($i = 1; $i <= 10; $i++) {
            $generated[] = ['question' => "Frage $i?", 'answer' => $i + 0.4];
        }

        $client = new MockHttpClient($this->openAiResponse($generated));
        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', null);

        $questions = $service->generateQuestions(10);

        $this->assertSame('1', $questions[0]['answer']);
        $this->assertSame('10', $questions[9]['answer']);
    }
}
