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

    public function testOversizedAnswersTriggerRegenerationAtChatGpt(): void
    {
        // Der 99999999.99-Bug: ChatGPT liefert Milliarden-Werte, die die
        // DECIMAL(10,2)-Spalte kappen würde — solche Fragen fliegen raus
        // und werden bei ChatGPT NACHGENERIERT (kein Pool!)
        $firstBatch = [
            ['question' => 'Wie viele Bäume gibt es im Amazonas?', 'answer' => 390000000000],
            ['question' => 'Wie viele Sterne hat die Milchstraße?', 'answer' => 100000000000],
            ['question' => 'Negativ?', 'answer' => -5],
        ];
        for ($i = 1; $i <= 7; $i++) {
            $firstBatch[] = ['question' => "Gültige Frage $i?", 'answer' => $i * 100];
        }

        $secondBatch = [];
        for ($i = 1; $i <= 5; $i++) {
            $secondBatch[] = ['question' => "Nachschub $i?", 'answer' => $i * 7];
        }

        $client = new MockHttpClient([
            $this->openAiResponse($firstBatch),
            $this->openAiResponse($secondBatch),
        ]);
        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', null);

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions);
        $this->assertSame(2, $client->getRequestsCount(), 'Fehlende Fragen werden bei ChatGPT nachgeneriert');

        $texts = array_column($questions, 'question');
        // Alle 10 Fragen stammen von ChatGPT (7 aus Anlauf 1 + 3 aus Anlauf 2), NICHT aus dem Pool
        foreach ($texts as $text) {
            $this->assertMatchesRegularExpression('/^(Gültige Frage|Nachschub) \d\?$/', $text, 'Keine Pool-Frage erwartet: ' . $text);
        }
        $this->assertNotContains('Wie viele Bäume gibt es im Amazonas?', $texts);

        foreach ($questions as $entry) {
            $this->assertMatchesRegularExpression('/^\d+$/', $entry['answer'], 'Antworten sind IMMER ganze Zahlen');
            $this->assertLessThanOrEqual(QuizQuestionGeneratorService::MAX_ANSWER, (int) $entry['answer']);
        }
    }

    public function testPoolIsOnlyLastFallbackAfterAllRetries(): void
    {
        // 3 Anläufe liefern je nur 2 gültige Fragen -> erst DANN füllt der Pool auf
        $batch = [
            ['question' => 'Zu groß?', 'answer' => 999999999999],
            ['question' => 'Gültig A?', 'answer' => 10],
            ['question' => 'Gültig B?', 'answer' => 20],
        ];

        $client = new MockHttpClient([
            $this->openAiResponse($batch),
            $this->openAiResponse($batch), // Duplikate werden verworfen
            $this->openAiResponse($batch),
        ]);
        $service = new QuizQuestionGeneratorService($client, new NullLogger(), 'sk-test', null);

        $questions = $service->generateQuestions(10);

        $this->assertCount(10, $questions, 'Pool füllt als LETZTER Fallback auf');
        $this->assertSame(3, $client->getRequestsCount(), 'Alle 3 Nachgenerierungs-Anläufe wurden genutzt');

        $texts = array_column($questions, 'question');
        $this->assertContains('Gültig A?', $texts);
        $this->assertContains('Gültig B?', $texts);
        $this->assertNotContains('Zu groß?', $texts);
        $this->assertSame(count($texts), count(array_unique($texts)), 'Keine doppelten Fragen über Anläufe hinweg');
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
