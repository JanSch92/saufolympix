<?php

namespace App\Service;

/**
 * Eingebauter Pool deutscher Allgemeinwissen-Fragen mit ganzzahligen Antworten.
 * Dient als Fallback, wenn keine OpenAI-Generierung möglich ist (kein Key, offline, Fehler).
 * Niveau: Erwachsene 25+, kein Fachwissen, nicht trivial.
 */
class QuizQuestionPool
{
    /**
     * @return array<array{question: string, answer: int}>
     */
    public static function all(): array
    {
        return [
            // Geographie
            ['question' => 'Wie viele Bundesstaaten haben die USA?', 'answer' => 50],
            ['question' => 'Wie viele Bundesländer hat Deutschland?', 'answer' => 16],
            ['question' => 'Wie viele Bundesländer hat Österreich?', 'answer' => 9],
            ['question' => 'Wie viele Kantone hat die Schweiz?', 'answer' => 26],
            ['question' => 'Wie viele Nachbarländer hat Deutschland?', 'answer' => 9],
            ['question' => 'Wie viele Mitgliedstaaten hat die Europäische Union?', 'answer' => 27],
            ['question' => 'Wie viele Mitgliedstaaten hat die UNO?', 'answer' => 193],
            ['question' => 'Wie viele Amtssprachen hat die EU?', 'answer' => 24],
            ['question' => 'Wie viele Zeitzonen gibt es in Russland?', 'answer' => 11],
            ['question' => 'Wie viele Länder liegen auf dem afrikanischen Kontinent?', 'answer' => 54],
            ['question' => 'Wie hoch ist die Zugspitze in Metern?', 'answer' => 2962],
            ['question' => 'Wie hoch ist der Mount Everest in Metern?', 'answer' => 8849],
            ['question' => 'Wie hoch ist der Eiffelturm mit Antenne in Metern?', 'answer' => 330],
            ['question' => 'Wie hoch ist der Burj Khalifa in Metern?', 'answer' => 828],
            ['question' => 'Wie viele Etagen hat der Burj Khalifa?', 'answer' => 163],
            ['question' => 'Wie hoch ist der Kölner Dom in Metern?', 'answer' => 157],
            ['question' => 'Wie viele Stockwerke hat das Empire State Building?', 'answer' => 102],
            ['question' => 'Wie lang ist der Rhein ungefähr in Kilometern?', 'answer' => 1233],
            ['question' => 'Wie tief ist der Marianengraben ungefähr in Metern?', 'answer' => 11000],
            ['question' => 'Wie lang ist die Chinesische Mauer laut offizieller Angabe ungefähr in Kilometern?', 'answer' => 21196],
            ['question' => 'Wie viele Provinzen hat Kanada?', 'answer' => 10],
            ['question' => 'Wie viele Bundesstaaten hat Australien?', 'answer' => 6],
            ['question' => 'Wie viele Bundesstaaten hat Brasilien?', 'answer' => 26],
            ['question' => 'Wie viele Präfekturen hat Japan?', 'answer' => 47],
            ['question' => 'Wie viele Regionen hat Italien?', 'answer' => 20],
            ['question' => 'Wie viele Autonome Gemeinschaften hat Spanien?', 'answer' => 17],
            ['question' => 'Wie viele Einwohner hat die Erde ungefähr in Milliarden?', 'answer' => 8],
            ['question' => 'Wie viel Prozent der Erdoberfläche sind ungefähr von Wasser bedeckt?', 'answer' => 71],
            ['question' => 'Wie viele Stufen führen ungefähr auf die Spitze des Eiffelturms?', 'answer' => 1665],
            ['question' => 'Wie viele Inseln gehören ungefähr zu Indonesien?', 'answer' => 17000],
            ['question' => 'Wie viele Kontinente gibt es nach gängiger Zählung?', 'answer' => 7],
            ['question' => 'In wie vielen Ländern ist der Euro offizielles Zahlungsmittel (Eurozone)?', 'answer' => 20],
            ['question' => 'Wie viele Sterne hat die EU-Flagge?', 'answer' => 12],
            ['question' => 'Wie viele Streifen hat die Flagge der USA?', 'answer' => 13],
            ['question' => 'Wie viele Säulen hat das Brandenburger Tor?', 'answer' => 12],
            ['question' => 'Wie viele Länder gibt es ungefähr auf der Welt (von der UNO anerkannt)?', 'answer' => 195],

            // Geschichte
            ['question' => 'In welchem Jahr fiel die Berliner Mauer?', 'answer' => 1989],
            ['question' => 'In welchem Jahr endete der Zweite Weltkrieg in Europa?', 'answer' => 1945],
            ['question' => 'In welchem Jahr begann der Erste Weltkrieg?', 'answer' => 1914],
            ['question' => 'In welchem Jahr erreichte Kolumbus Amerika?', 'answer' => 1492],
            ['question' => 'In welchem Jahr betrat der erste Mensch den Mond?', 'answer' => 1969],
            ['question' => 'In welchem Jahr wurde die Bundesrepublik Deutschland gegründet?', 'answer' => 1949],
            ['question' => 'In welchem Jahr fand die deutsche Wiedervereinigung statt?', 'answer' => 1990],
            ['question' => 'In welchem Jahr sank die Titanic?', 'answer' => 1912],
            ['question' => 'In welchem Jahr begann die Französische Revolution?', 'answer' => 1789],
            ['question' => 'In welchem Jahr wurde John F. Kennedy ermordet?', 'answer' => 1963],
            ['question' => 'In welchem Jahr wurde der Euro als Bargeld eingeführt?', 'answer' => 2002],
            ['question' => 'In welchem Jahr explodierte der Reaktor in Tschernobyl?', 'answer' => 1986],
            ['question' => 'In welchem Jahr verlor Napoleon die Schlacht bei Waterloo?', 'answer' => 1815],
            ['question' => 'Wie viele Jahre war Angela Merkel Bundeskanzlerin?', 'answer' => 16],
            ['question' => 'Wie viele Bundeskanzler hatte Deutschland bis einschließlich Olaf Scholz?', 'answer' => 9],
            ['question' => 'In welchem Jahr wurde das erste iPhone vorgestellt?', 'answer' => 2007],
            ['question' => 'In welchem Jahr wurde Google gegründet?', 'answer' => 1998],
            ['question' => 'In welchem Jahr wurde Facebook gegründet?', 'answer' => 2004],
            ['question' => 'Wie viele US-Präsidenten gab es bis einschließlich Joe Biden?', 'answer' => 46],
            ['question' => 'Wie alt wurde Queen Elizabeth II.?', 'answer' => 96],
            ['question' => 'Wie viele Kinder hatte Queen Elizabeth II.?', 'answer' => 4],
            ['question' => 'In welchem Jahr wurde die Titanic gefunden (Wrack entdeckt)?', 'answer' => 1985],
            ['question' => 'In welchem Jahr trat Großbritannien offiziell aus der EU aus (Brexit)?', 'answer' => 2020],
            ['question' => 'In welchem Jahr fanden die ersten Olympischen Spiele der Neuzeit statt?', 'answer' => 1896],
            ['question' => 'In welchem Jahr wurde die Mauer in Berlin gebaut?', 'answer' => 1961],
            ['question' => 'Wie lange dauerte der Hundertjährige Krieg tatsächlich in Jahren?', 'answer' => 116],

            // Wissenschaft & Natur
            ['question' => 'Wie viele Knochen hat ein erwachsener Mensch?', 'answer' => 206],
            ['question' => 'Wie viele Knochen hat ein Baby bei der Geburt ungefähr?', 'answer' => 300],
            ['question' => 'Wie viele Zähne hat ein Erwachsener inklusive Weisheitszähne?', 'answer' => 32],
            ['question' => 'Wie viele Chromosomen hat ein Mensch?', 'answer' => 46],
            ['question' => 'Wie viele Planeten hat unser Sonnensystem?', 'answer' => 8],
            ['question' => 'Wie viele Monde hat der Mars?', 'answer' => 2],
            ['question' => 'Wie schnell ist das Licht ungefähr in Kilometern pro Sekunde?', 'answer' => 300000],
            ['question' => 'Wie viele Elemente enthält das Periodensystem aktuell?', 'answer' => 118],
            ['question' => 'Wie viele Herzen hat ein Oktopus?', 'answer' => 3],
            ['question' => 'Wie viele Mägen hat eine Kuh?', 'answer' => 4],
            ['question' => 'Wie viele Monate ist ein Elefant ungefähr trächtig?', 'answer' => 22],
            ['question' => 'Wie viele Muskeln hat der Mensch ungefähr?', 'answer' => 650],
            ['question' => 'Wie viele Rippenpaare hat der Mensch?', 'answer' => 12],
            ['question' => 'Wie viele Halswirbel hat der Mensch?', 'answer' => 7],
            ['question' => 'Wie viele Augen hat eine Honigbiene?', 'answer' => 5],
            ['question' => 'Wie viele Tage braucht der Mond ungefähr für eine Erdumrundung?', 'answer' => 27],
            ['question' => 'Wie viele Erdtage dauert ein Jahr auf dem Mars ungefähr?', 'answer' => 687],
            ['question' => 'Wie heiß ist die Oberfläche der Sonne ungefähr in Grad Celsius?', 'answer' => 5500],
            ['question' => 'Wie viele Herzschläge pro Minute hat ein gesunder Erwachsener ungefähr in Ruhe?', 'answer' => 70],
            ['question' => 'Wie viele Haare verliert ein Mensch durchschnittlich pro Tag?', 'answer' => 100],
            ['question' => 'Wie viele Kalorien hat ein Gramm Fett?', 'answer' => 9],
            ['question' => 'Wie viele Geschmacksrichtungen kann der Mensch nach klassischer Zählung wahrnehmen?', 'answer' => 5],
            ['question' => 'Wie viele Liter Blut hat ein Erwachsener ungefähr?', 'answer' => 5],
            ['question' => 'Wie viel Grad Celsius beträgt die normale Körpertemperatur des Menschen (gerundet)?', 'answer' => 37],
            ['question' => 'Wie viele Beine hat ein Hundertfüßer mindestens (Paare x2, häufigste Art)?', 'answer' => 30],
            ['question' => 'Wie viele Jahre kann eine Galapagos-Riesenschildkröte ungefähr alt werden?', 'answer' => 100],
            ['question' => 'Wie viel Prozent seines Körpergewichts kann eine Ameise ungefähr tragen (das Vielfache)?', 'answer' => 50],
            ['question' => 'Bei wie viel Grad Celsius siedet Wasser auf dem Gipfel des Mount Everest ungefähr?', 'answer' => 70],

            // Sport & Spiele
            ['question' => 'Wie lang ist ein Marathon in Kilometern (gerundet)?', 'answer' => 42],
            ['question' => 'Wie viele Löcher hat eine komplette Golfrunde?', 'answer' => 18],
            ['question' => 'Wie oft wurde Deutschland Fußball-Weltmeister (Männer)?', 'answer' => 4],
            ['question' => 'Wie oft wurde Brasilien Fußball-Weltmeister (Männer)?', 'answer' => 5],
            ['question' => 'Wie viele Felder hat ein Schachbrett?', 'answer' => 64],
            ['question' => 'Wie viele Figuren hat ein Schachspieler zu Beginn der Partie?', 'answer' => 16],
            ['question' => 'Wie hoch hängt ein Basketballkorb in Zentimetern?', 'answer' => 305],
            ['question' => 'Wie viele Grand-Slam-Turniere gibt es im Tennis pro Jahr?', 'answer' => 4],
            ['question' => 'Wie viele Pins stehen beim Bowling?', 'answer' => 10],
            ['question' => 'Wie viele Karten hat ein Skatblatt?', 'answer' => 32],
            ['question' => 'Wie viele Karten hat ein Pokerdeck ohne Joker?', 'answer' => 52],
            ['question' => 'Was ist die höchste Punktzahl mit einem einzigen Dartpfeil?', 'answer' => 60],
            ['question' => 'Was ist das höchstmögliche Finish (Checkout) beim Darts mit drei Pfeilen?', 'answer' => 170],
            ['question' => 'Wie viele Spieler stehen beim Volleyball pro Team auf dem Feld?', 'answer' => 6],
            ['question' => 'Wie viele Spieler stehen beim Handball pro Team auf dem Feld (inkl. Torwart)?', 'answer' => 7],
            ['question' => 'Wie viele Spieler stehen beim Basketball pro Team auf dem Feld?', 'answer' => 5],
            ['question' => 'Wie viele Spieler hat eine Rugby-Union-Mannschaft auf dem Feld?', 'answer' => 15],
            ['question' => 'Wie viele Minuten dauert eine Halbzeit beim Handball?', 'answer' => 30],
            ['question' => 'Wie viele Vereine spielen in der 1. Fußball-Bundesliga?', 'answer' => 18],
            ['question' => 'Wie viele Spieltage hat eine Bundesliga-Saison?', 'answer' => 34],
            ['question' => 'Wie viele Teams spielen in der NFL?', 'answer' => 32],
            ['question' => 'Mit wie vielen Toren insgesamt endete das WM-Halbfinale 2014 Deutschland gegen Brasilien?', 'answer' => 8],
            ['question' => 'In welchem Jahr gewann Deutschland zuletzt die Fußball-Weltmeisterschaft?', 'answer' => 2014],
            ['question' => 'Wie viele Punkte gibt ein Touchdown im American Football?', 'answer' => 6],
            ['question' => 'Wie viele Karten gibt es bei UNO?', 'answer' => 108],
            ['question' => 'Wie viele Felder hat ein Monopoly-Spielbrett?', 'answer' => 40],
            ['question' => 'Wie viele Steine hat ein klassisches Domino-Spiel (Doppel-Sechs)?', 'answer' => 28],
            ['question' => 'Wie viele Augen haben alle Seiten eines Würfels zusammen?', 'answer' => 21],
            ['question' => 'Wie viele Buchstabensteine gibt es beim deutschen Scrabble?', 'answer' => 102],
            ['question' => 'Wie viele Weltmeistertitel holte Michael Schumacher in der Formel 1?', 'answer' => 7],
            ['question' => 'Wie viele Ringe hat das olympische Symbol?', 'answer' => 5],
            ['question' => 'Wie viele Bahnen hat ein olympisches Schwimmbecken?', 'answer' => 10],
            ['question' => 'Wie lang ist ein olympisches Schwimmbecken in Metern?', 'answer' => 50],

            // Kultur, Musik & Unterhaltung
            ['question' => 'Wie viele Tasten hat ein klassisches Klavier?', 'answer' => 88],
            ['question' => 'Wie viele Saiten hat eine klassische Gitarre?', 'answer' => 6],
            ['question' => 'Wie viele Symphonien hat Beethoven vollendet?', 'answer' => 9],
            ['question' => 'Wie viele Bücher umfasst die Harry-Potter-Hauptreihe?', 'answer' => 7],
            ['question' => 'Wie viele James-Bond-Filme gibt es mit Daniel Craig?', 'answer' => 5],
            ['question' => 'Wie viele Oscars gewann der Film "Titanic"?', 'answer' => 11],
            ['question' => 'Wie viele Oscars gewann "Der Herr der Ringe: Die Rückkehr des Königs"?', 'answer' => 11],
            ['question' => 'Wie viele Buchstaben hat das griechische Alphabet?', 'answer' => 24],
            ['question' => 'Wie viele Buchstaben hat das deutsche Alphabet ohne Umlaute und ß?', 'answer' => 26],
            ['question' => 'Wie viele Sprachen werden weltweit ungefähr gesprochen?', 'answer' => 7000],
            ['question' => 'Wie viele Zacken hat ein Davidstern?', 'answer' => 6],
            ['question' => 'Wie viele Staffeln hat die Serie "Game of Thrones"?', 'answer' => 8],
            ['question' => 'Wie viele Minuten dauert eine klassische "Tatort"-Folge?', 'answer' => 90],
            ['question' => 'Wie viele Musiker spielen in einem Streichquartett?', 'answer' => 4],
            ['question' => 'Wie viele Kinder haben Kate und Peter McCallister im Film "Kevin – Allein zu Haus"?', 'answer' => 5],
            ['question' => 'Wie viele Farben hat ein klassischer Zauberwürfel?', 'answer' => 6],
            ['question' => 'Wie viele kleine Steine hat ein klassischer Zauberwürfel auf einer Seite?', 'answer' => 9],

            // Deutschland & Alltag
            ['question' => 'Wie viele Sitze hat der Deutsche Bundestag laut Wahlrechtsreform (Sollgröße)?', 'answer' => 630],
            ['question' => 'Ab welchem Alter darf man in Deutschland Bier und Wein kaufen?', 'answer' => 16],
            ['question' => 'Wie viele Flaschen sind in einem klassischen deutschen Bierkasten?', 'answer' => 20],
            ['question' => 'Wie viele Liter Bier passen in einen Kasten mit 20 Halbliterflaschen?', 'answer' => 10],
            ['question' => 'Bei wie vielen Punkten in Flensburg ist der Führerschein weg?', 'answer' => 8],
            ['question' => 'Wie viel km/h darf man in Deutschland innerorts normalerweise fahren?', 'answer' => 50],
            ['question' => 'Wie viele Kalorien hat eine Flasche Bier (0,5 l) ungefähr?', 'answer' => 210],
            ['question' => 'Wie viele PS leistet ein echtes Pferd in der Spitze ungefähr?', 'answer' => 15],
            ['question' => 'Wie viele Nullen hat eine Milliarde?', 'answer' => 9],
            ['question' => 'Wie viele Nullen hat eine Billion?', 'answer' => 12],
            ['question' => 'Wie viele Sekunden hat eine Stunde?', 'answer' => 3600],
            ['question' => 'Wie viele Minuten hat ein ganzer Tag?', 'answer' => 1440],
            ['question' => 'Wie viele Wochen hat ein Jahr?', 'answer' => 52],
            ['question' => 'Wie viele Bit hat ein Byte?', 'answer' => 8],
            ['question' => 'Wie viele Grad beträgt die Innenwinkelsumme eines Dreiecks?', 'answer' => 180],
            ['question' => 'Wie viele Tage hat das Oktoberfest in München traditionell mindestens?', 'answer' => 16],
            ['question' => 'Wie viele Maß Bier trinken die Besucher des Oktoberfests ungefähr insgesamt in Millionen?', 'answer' => 7],
            ['question' => 'Wie viele Bundesautobahnen-Kilometer hat Deutschland ungefähr?', 'answer' => 13200],
            ['question' => 'Wie viele Stufen hat der Weg auf den Kölner Dom (Turmbesteigung) ungefähr?', 'answer' => 533],
            ['question' => 'Wie viele Einwohner hat Deutschland ungefähr in Millionen?', 'answer' => 84],
            ['question' => 'Wie viele Landkreise gibt es in Deutschland ungefähr?', 'answer' => 294],
            ['question' => 'Wie hoch ist der Berliner Fernsehturm in Metern?', 'answer' => 368],
            ['question' => 'Wie viele Türen hat ein klassischer Adventskalender?', 'answer' => 24],
            ['question' => 'Wie viele Tage hat ein Schaltjahr?', 'answer' => 366],
            ['question' => 'Wie viele Zonen hat eine Dartscheibe (Zahlenfelder 1-20)?', 'answer' => 20],
            ['question' => 'Wie viele Streichhölzer sind ungefähr in einer klassischen Schachtel?', 'answer' => 38],
            ['question' => 'Wie viele Gramm hat ein Standardbrief in Deutschland maximal?', 'answer' => 20],
            ['question' => 'Wie viele Euro-Geldscheine gibt es (verschiedene Werte)?', 'answer' => 7],
            ['question' => 'Wie viele Euro-Münzen gibt es (verschiedene Werte)?', 'answer' => 8],
        ];
    }

    /**
     * Zieht $count zufällige Fragen ohne Wiederholung.
     *
     * @return array<array{question: string, answer: int}>
     */
    public static function random(int $count): array
    {
        $pool = self::all();
        shuffle($pool);

        return array_slice($pool, 0, min($count, count($pool)));
    }
}
