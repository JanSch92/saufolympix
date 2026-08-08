<?php

namespace App\Tests\Functional;

/**
 * Spiel-Detailseite: Ergebnisse, Punkte und volle Joker-Transparenz
 * (wer hat verdoppelt, wer hat mit wem getauscht — inkl. erspielter Platz).
 */
class GameDetailsTest extends FunctionalTestCase
{
    public function testDetailsShowResultsAndJokerTransparency(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 3);
        $game = $this->createGame($olympix, 'free_for_all', 'Bierpong');

        // Spieler 1 verdoppelt, Spieler 2 tauscht mit Spieler 1
        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $game->getId(),
        ]);
        $this->client->request('POST', '/player-joker-swap/' . $olympix->getId() . '/' . $players[1]->getId(), [
            'target_player_id' => $players[0]->getId(),
            'selected_game_id' => $game->getId(),
        ]);

        $this->client->request('GET', '/game/start/' . $game->getId());
        $this->client->request('POST', '/game/results/' . $game->getId(), [
            'positions' => [
                $players[0]->getId() => 1,
                $players[1]->getId() => 2,
                $players[2]->getId() => 3,
            ],
        ]);

        $this->client->request('GET', '/game/details/' . $game->getId());
        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();

        // Ergebnis-Tabelle mit allen Spielern
        $this->assertStringContainsString('Bierpong', $html);
        foreach ($players as $player) {
            $this->assertStringContainsString($player->getName(), $html);
        }

        // Verdopplung nachvollziehbar: Spieler 1 hat Platz 2 ertauscht bekommen (2 -> 4)
        $this->assertStringContainsString('Doppelte Punkte: Spieler1', $html);
        $this->assertStringContainsString('2 → 4', $html);

        // Tausch nachvollziehbar: Spieler 2 hat Platz 2 erspielt und Platz 1 übernommen
        $this->assertStringContainsString('Punktetausch: Spieler2 ↔ Spieler1', $html);
        $this->assertStringContainsString('hat Platz 2 erspielt', $html);
        $this->assertStringContainsString('Platz 1 (3 Punkte)', $html);
    }

    public function testDetailsRenderForPendingGameWithPremarkedJokers(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'free_for_all');

        $this->client->request('POST', '/player-joker-double/' . $olympix->getId() . '/' . $players[0]->getId(), [
            'selected_game_id' => $game->getId(),
        ]);

        $this->client->request('GET', '/game/details/' . $game->getId());
        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Vorgemerkt', $html);
    }

    public function testDetailsShowQuizQuestionsAndAnswers(): void
    {
        $olympix = $this->createOlympix();
        $players = $this->createPlayers($olympix, 2);
        $game = $this->createGame($olympix, 'quiz');
        $this->client->request('GET', '/game/start/' . $game->getId());

        $this->entityManager->clear();
        $game = $this->entityManager->getRepository(\App\Entity\Game::class)->find($game->getId());

        foreach ($game->getQuizQuestions() as $question) {
            foreach ($players as $i => $player) {
                $this->client->request(
                    'POST',
                    '/quiz/answer/' . $game->getId(),
                    server: ['CONTENT_TYPE' => 'application/json'],
                    content: json_encode(['player_id' => $player->getId(), 'question_id' => $question->getId(), 'answer' => (string) (10 + $i)])
                );
            }
        }

        $this->client->request('GET', '/game/details/' . $game->getId());
        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Fragen &amp; Antworten', $html);
        $this->assertStringContainsString('Frage 1:', $html);
        $this->assertStringContainsString('Richtige Antwort:', $html);
        $this->assertStringContainsString('Spieler1', $html);
    }
}
