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

    /**
     * Antworten sind IMMER ganze Zahlen in diesem Bereich — größere Werte
     * würden von der DECIMAL(10,2)-Spalte gekappt (der 99999999.99-Bug).
     * Fragen mit größeren Realwerten müssen umformuliert werden ("in Millionen").
     */
    public const MAX_ANSWER = 99999999;

    /**
     * Liefert ChatGPT ungültige Fragen (z.B. zu große Antworten), wird bei
     * ChatGPT NACHGENERIERT — der Fragenpool ist nur der allerletzte Fallback.
     */
    private const MAX_GENERATION_ATTEMPTS = 3;

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
        if (empty($this->openAiApiKey)) {
            return $this->generateFromPool($count);
        }

        $collected = [];
        $seen = [];

        // ChatGPT generiert — ungültige oder zu wenige Fragen werden bei
        // ChatGPT NACHGENERIERT (bis zu 3 Anläufe), nicht aus dem Pool ersetzt
        for ($attempt = 1; $attempt <= self::MAX_GENERATION_ATTEMPTS && count($collected) < $count; $attempt++) {
            $missing = $count - count($collected);

            try {
                // Ab dem zweiten Anlauf etwas mehr anfordern, um Ausschuss zu kompensieren
                $batch = $this->generateViaOpenAi($attempt === 1 ? $missing : $missing + 3);
            } catch (\Throwable $e) {
                $this->logger->warning('OpenAI-Fragengenerierung fehlgeschlagen (Anlauf {attempt}): {error}', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            foreach ($batch as $entry) {
                $key = mb_strtolower(trim($entry['question']));
                if (isset($seen[$key])) {
                    continue; // Duplikate über Anläufe hinweg verwerfen
                }
                $seen[$key] = true;
                $collected[] = $entry;
                if (count($collected) >= $count) {
                    break;
                }
            }

            if (count($collected) < $count) {
                $this->logger->info('Nachgenerierung nötig: {have}/{want} gültige Fragen nach Anlauf {attempt}.', [
                    'have' => count($collected),
                    'want' => $count,
                    'attempt' => $attempt,
                ]);
            }
        }

        if (count($collected) >= $count) {
            return array_slice($collected, 0, $count);
        }

        // ALLERLETZTER Fallback: Rest aus dem eingebauten Pool
        $this->logger->warning('Auch nach Nachgenerierung nur {have}/{want} Fragen — fülle den Rest aus dem Pool auf.', [
            'have' => count($collected),
            'want' => $count,
        ]);

        return array_merge($collected, $this->generateFromPool($count - count($collected)));
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
                            . 'Das Feld "answer" muss IMMER eine positive GANZE Zahl sein (niemals Dezimalzahlen) '
                            . 'und MUSS unter 99 Millionen liegen. Wenn der echte Wert größer ist, formuliere die '
                            . 'Frage mit einer Einheit um (z.B. "in Millionen", "in Tonnen", "in Milliarden"), '
                            . 'sodass die Antwort klein genug wird.',
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

            // NUR ganze Zahlen im gültigen Bereich — alles andere wird verworfen
            // (sonst kappt die DB den Wert und es entstehen Antworten wie 99999999.99)
            $answer = (int) round((float) $entry['answer']);
            if ($answer < 0 || $answer > self::MAX_ANSWER) {
                $this->logger->warning('OpenAI-Frage verworfen (Antwort außerhalb 0..{max}): {question} = {answer}', [
                    'max' => self::MAX_ANSWER,
                    'question' => $entry['question'],
                    'answer' => $entry['answer'],
                ]);
                continue;
            }

            $questions[] = [
                'question' => trim((string) $entry['question']),
                'answer' => (string) $answer,
            ];
        }

        return $questions;
    }
}
