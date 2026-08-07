<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generiert Allgemeinwissen-Quizfragen mit ganzzahligen Antworten.
 *
 * Primär über die OpenAI-API (ChatGPT), bei fehlendem API-Key oder
 * beliebigem Fehler wird lautlos auf den eingebauten Fragenpool
 * zurückgegriffen — der Spielstart schlägt dadurch nie fehl.
 */
class QuizQuestionGeneratorService
{
    private const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'default::OPENAI_API_KEY')]
        private ?string $openAiApiKey = null,
        #[Autowire(env: 'default::OPENAI_MODEL')]
        private ?string $openAiModel = null,
    ) {}

    /**
     * @return array<array{question: string, answer: string}>
     */
    public function generateQuestions(int $count = 10): array
    {
        if (!empty($this->openAiApiKey)) {
            try {
                $questions = $this->generateViaOpenAi($count);
                if (count($questions) >= $count) {
                    return array_slice($questions, 0, $count);
                }
                $this->logger->warning('OpenAI lieferte zu wenige gültige Fragen ({count}), nutze Fallback-Pool.', [
                    'count' => count($questions),
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('OpenAI-Fragengenerierung fehlgeschlagen, nutze Fallback-Pool: {error}', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generateFromPool($count);
    }

    public function isOpenAiConfigured(): bool
    {
        return !empty($this->openAiApiKey);
    }

    /**
     * @return array<array{question: string, answer: string}>
     */
    private function generateFromPool(int $count): array
    {
        return array_map(
            fn (array $entry) => ['question' => $entry['question'], 'answer' => (string) $entry['answer']],
            QuizQuestionPool::random($count)
        );
    }

    /**
     * @return array<array{question: string, answer: string}>
     */
    private function generateViaOpenAi(int $count): array
    {
        $response = $this->httpClient->request('POST', self::OPENAI_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
            'json' => [
                'model' => $this->openAiModel ?: 'gpt-4o-mini',
                'temperature' => 1.0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist Quizmaster für ein Party-Schätzquiz mit Erwachsenen (25+). '
                            . 'Erstelle deutsche SCHÄTZFRAGEN mit ganzzahliger Antwort, deren exakten Wert '
                            . 'praktisch NIEMAND auswendig weiß — die Spieler müssen wirklich schätzen. '
                            . 'Gewertet wird, wer am nächsten dran ist. '
                            . 'VERBOTEN ist simples Schulwissen mit bekannter Antwort (z.B. "Wie viele Bundesländer hat Deutschland?", '
                            . '"Wie viele Planeten hat das Sonnensystem?", "In welchem Jahr fiel die Mauer?"). '
                            . 'GUT sind Fragen wie: "Wie viele McDonald\'s-Filialen gibt es in Deutschland?", '
                            . '"Wie oft blinzelt ein Mensch pro Tag?", "Wie viele Tennisbälle verbraucht Wimbledon pro Turnier?", '
                            . '"Wie viel wiegt die Zunge eines Blauwals in Tonnen?", "Wie lang ist die Donau in Kilometern?". '
                            . 'Antworte NUR mit einem JSON-Objekt der Form '
                            . '{"questions": [{"question": "...", "answer": 1400}, ...]}. '
                            . 'Das Feld "answer" muss immer eine ganze Zahl (dein bester Näherungswert) sein.',
                    ],
                    [
                        'role' => 'user',
                        'content' => sprintf(
                            'Erstelle %d abwechslungsreiche Schätzfragen mit ganzzahligen Antworten, '
                            . 'deren exakte Werte man nicht auswendig wissen kann. '
                            . 'Mische Kategorien (Geographie, Rekorde, Körper, Tiere, Essen, Technik, Sport, Alltag).',
                            $count
                        ),
                    ],
                ],
            ],
        ]);

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            throw new \RuntimeException('Leere Antwort von OpenAI');
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed) || !isset($parsed['questions']) || !is_array($parsed['questions'])) {
            throw new \RuntimeException('Unerwartetes JSON-Format von OpenAI');
        }

        $questions = [];
        foreach ($parsed['questions'] as $entry) {
            if (!is_array($entry) || empty($entry['question']) || !isset($entry['answer'])) {
                continue;
            }
            if (!is_numeric($entry['answer'])) {
                continue;
            }

            $questions[] = [
                'question' => trim((string) $entry['question']),
                'answer' => (string) (int) round((float) $entry['answer']),
            ];
        }

        return $questions;
    }
}
