# Design: Quiz-Autogenerierung, Stoppuhr-Spiel, UI-Modernisierung, Testabdeckung

**Datum:** 2026-07-18
**Projekt:** Saufolympix (Symfony 7.3, PHP >= 8.2, Doctrine ORM, Twig + Tailwind CDN)

## Ziele

1. **Quiz-Modus erweitern:** 10 Allgemeinwissen-Fragen werden beim Spielstart automatisch generiert (OpenAI/ChatGPT, mit eingebautem Fallback-Pool). Fragen mit ganzzahligen Antworten, Niveau Erwachsene 25+ (kein Fachwissen, nicht trivial — z. B. "Wie viele Bundesstaaten haben die USA?").
2. **Neues Spiel "Stoppuhr":** Zufällige Zielzeit zwischen 5 und 60 Sekunden (2 Nachkommastellen). Jeder Spieler startet/stoppt eine blinde Stoppuhr auf dem Handy. Ranking nach kleinster Abweichung, Punkte absteigend (wie Quiz).
3. **UI-Modernisierung:** Dunkles Party-Theme, mobile-first, konsistente Emojis pro Spieltyp (Entscheidung: Emojis ausbauen = mehr/konsistenter einsetzen).
4. **Testabdeckung:** Unit-Tests (Entities/Services), Funktionale Tests (WebTestCase, alle Controller-Flows), E2E-Walkthrough mit Playwright gegen lokal laufende App.

## Bestandsaufnahme

- Quiz existiert bereits vollständig (QuizController, QuizQuestion mit `calculateScores()`, QuizAnswer, Mobile-Templates, QR-Code) — Fragen werden aber **manuell** vom Admin eingetragen.
- Spieler-Interface über Handy existiert (player_interface/*, QR-Zugang, Joker).
- Joker-Anwendungslogik ist **dupliziert** in GameController und QuizController.
- `tests/` enthält nur `bootstrap.php` — keine Tests.
- UI: Tailwind CDN, helles Theme, Inter-Font, 56 Emojis verstreut.
- DB: Postgres via Docker (Prod-Default). Lokal wird SQLite via `.env.local` genutzt (nicht committet).

## Architektur

### 1. Quiz-Autogenerierung

**Neuer Service `App\Service\QuizQuestionGeneratorService`:**
- `generateQuestions(int $count = 10): array<{question: string, answer: string}>`
- Versucht OpenAI Chat Completions (Symfony HttpClient, `OPENAI_API_KEY` + `OPENAI_MODEL` env, Default `gpt-4o-mini`, JSON-Antwortformat, deutscher Prompt: Allgemeinwissen, ganzzahlige Antworten, Niveau 25+).
- Validierung der API-Antwort (Struktur, numerische Antworten). Bei **jedem** Fehler (kein Key, Timeout, ungültiges JSON) → Fallback.
- **Fallback:** eingebauter Pool mit 150+ deutschen Allgemeinwissen-Fragen (`App\Service\QuizQuestionPool`), zufällige Auswahl ohne Wiederholung.

**Integration:**
- `GameController::start()`: Wenn Quiz-Spiel ohne Fragen gestartet wird → 10 Fragen automatisch generieren.
- Quiz-Admin-Seite: Button "🤖 10 Fragen neu generieren" (Route `app_quiz_generate`, löscht vorhandene Fragen nur wenn noch keine Antworten existieren, generiert neu). Manuelle Fragen bleiben weiterhin möglich.

### 2. Stoppuhr-Spiel

**Datenmodell:**
- Neuer `gameType` `'stopwatch'` (Game: `isStopwatchGame()`, Label "Stoppuhr", MinPlayers 2, kein Setup nötig).
- Neue Spalte `Game.stopwatchTarget` (DECIMAL(6,2), nullable) — wird beim Start zufällig gesetzt (5.00–60.00).
- Neue Entity `StopwatchAttempt`: game (ManyToOne), player (ManyToOne), `elapsedSeconds` (DECIMAL(8,2)), `deviation` (berechnet, DECIMAL(8,2)), createdAt. Ein Versuch pro Spieler pro Spiel.
- Migration platform-unabhängig über Doctrine Schema-API.

**Spielablauf:**
1. Admin startet Spiel → Zielzeit wird zufällig gesetzt, Spiel aktiv.
2. Spieler öffnen `/stopwatch/mobile/{gameId}` (QR/Player-Dashboard): Spieler wählen, Zielzeit prominent sichtbar, großer Start-Button → wird zum Stop-Button. **Laufende Zeit ist verborgen** (blindes Timing — das ist das Spiel).
3. Stop → Zeit wird per `performance.now()` gemessen, automatisch per POST übermittelt, Spieler sieht eigene Zeit + Abweichung.
4. Admin-Seite `/stopwatch/manage/{gameId}`: Live-Fortschritt (Polling-API `/api/stopwatch/{gameId}/status`), wer hat abgegeben.
5. Wenn alle Spieler abgegeben haben → automatische Auswertung (wie Quiz-Auto-Complete): Ranking nach `|elapsed − target|` aufsteigend, Punkte n, n−1, …, 1 → GameResults, Joker anwenden, Spiel abschließen, Gesamtpunkte aktualisieren. Admin kann auch manuell auswerten.
6. Ergebnis-Seite mit Zielzeit, allen Zeiten, Abweichungen und Punkten.

**Auswertung als eigener Service `App\Service\StopwatchEvaluationService`** (unit-testbar ohne DB-Roundtrip-Logik im Controller).

### 3. Gemeinsamer Joker-Service (gezielter Refactor)

`App\Service\JokerApplicationService::applyJokersForGame(Game $game)` — extrahiert die duplizierte Double-/Swap-Joker-Logik aus GameController und QuizController; wird von Quiz, Stoppuhr und Free-for-all-Ergebnissen genutzt. Verhalten bleibt identisch (Double zuerst, dann Swap; verfallene Joker werden als benutzt markiert).

### 4. UI-Modernisierung (dunkles Party-Theme, mobile-first)

- `base.html.twig` + `baselive.html.twig`: dunkles Theme (slate-950/900-Hintergrund, Gradient-Akzente violett→pink→amber), Tailwind-Config inline, Design-Tokens als wiederverwendbare CSS-Klassen (`.btn-primary`, `.card`, `.input` …), Touch-Targets >= 44px, safe-area-insets für Mobile.
- Emoji-System pro Spieltyp konsequent: 🍻 Brand, ⚔️ Free-for-all, 🏆 Turnier, 🧠 Quiz, ⏱️ Stoppuhr, 🤝 Split or Steal, 🎯 Gamechanger, 🃏 Joker, 👑 Rangliste.
- Alle Templates werden auf das neue Theme umgestellt; Handy-Seiten (Quiz mobile, Stoppuhr mobile, Player-Dashboard, Player-Access) bekommen besonderen Mobile-Fokus (große Buttons, kein horizontales Scrollen, sticky Submit).
- TV-/Beamer-Ansicht (`main/show.html.twig`) profitiert vom dunklen Theme (Live-Rangliste).

### 5. Tests

- **Unit** (`tests/Unit/`): Game (Typ-/Status-/Punkte-Logik), QuizQuestion::calculateScores (inkl. Gleichstand), QuizAnswer, Player-Punkteberechnung, QuizQuestionGeneratorService (gemockter HttpClient: Erfolg, kaputtes JSON, kein Key → Fallback), QuizQuestionPool (Größe, Eindeutigkeit, numerische Antworten), StopwatchEvaluationService (Ranking, Ties, Punktevergabe), JokerApplicationService (gemockter EM).
- **Funktional** (`tests/Functional/`, WebTestCase + SQLite-Test-DB, Schema pro Prozess): Olympix anlegen, Spieler-CRUD, Spiel-Lifecycle je Typ, Quiz-Komplettflow (Start → Autogenerierung → Mobile-Antworten aller Spieler → Auto-Complete → Punkte), Stoppuhr-Komplettflow (Start → Zielzeit → Abgaben → Auto-Auswertung → Punkte), Joker-Flows, API-Endpoints.
- **E2E** (Playwright, manuell im Rahmen dieser Session gegen `php -S` + SQLite): komplettes Spiel anlegen und durchspielen — Olympix, Spieler, Quiz und Stoppuhr inkl. Mobile-Ansichten (Viewport-Test), Dashboard/Rangliste.

## Fehlerbehandlung

- OpenAI nicht erreichbar/ungültig → lautloser Fallback auf Pool + Log-Eintrag; Spielstart schlägt **nie** wegen OpenAI fehl.
- Stoppuhr: doppelte Abgabe wird serverseitig abgelehnt (idempotent, freundliche Meldung); unplausible Zeiten (<0 oder >600s) werden abgelehnt.
- Auto-Auswertung ist idempotent (bereits abgeschlossenes Spiel wird nicht doppelt ausgewertet).

## Nicht-Ziele

- Kein Wechsel des CSS-Stacks (Tailwind CDN bleibt), kein JS-Framework.
- Keine Änderung bestehender Spielregeln (Turnier, Split or Steal, Gamechanger) über das Theming hinaus.
- Keine Prod-Deployment-Änderungen (Postgres/Docker bleibt Default; SQLite nur lokal/Test).
