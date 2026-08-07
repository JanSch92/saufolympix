<?php

namespace App\Service;

/**
 * Eingebauter Pool deutscher SCHÄTZFRAGEN mit ganzzahligen Antworten.
 * Dient als Fallback, wenn keine OpenAI-Generierung möglich ist.
 *
 * Anspruch: Die exakte Antwort weiß praktisch niemand auswendig — man MUSS
 * schätzen (kein simples Allgemeinwissen wie "Wie viele Bundesländer...").
 * Da beim Quiz die Nähe zur richtigen Antwort zählt, sind gerundete
 * Näherungswerte als Referenz völlig ausreichend.
 */
class QuizQuestionPool
{
    /**
     * @return array<array{question: string, answer: int}>
     */
    public static function all(): array
    {
        return [
            // Geographie & Welt
            ['question' => 'Wie lang ist die Donau in Kilometern?', 'answer' => 2850],
            ['question' => 'Wie lang ist der Amazonas ungefähr in Kilometern?', 'answer' => 6400],
            ['question' => 'Wie lang ist der Nil ungefähr in Kilometern?', 'answer' => 6650],
            ['question' => 'Wie groß ist Deutschland in Quadratkilometern?', 'answer' => 357588],
            ['question' => 'Wie hoch ist der Kilimandscharo in Metern?', 'answer' => 5895],
            ['question' => 'Wie viele Inseln gehören ungefähr zu Schweden?', 'answer' => 267000],
            ['question' => 'Wie tief ist der Bodensee an seiner tiefsten Stelle in Metern?', 'answer' => 251],
            ['question' => 'Wie groß ist der Bodensee in Quadratkilometern?', 'answer' => 536],
            ['question' => 'Wie hoch sind die Niagarafälle in Metern?', 'answer' => 57],
            ['question' => 'Wie lang ist das Great Barrier Reef in Kilometern?', 'answer' => 2300],
            ['question' => 'Wie viele Einwohner hat die Metropolregion Tokio in Millionen?', 'answer' => 37],
            ['question' => 'Wie hoch ist der höchste Wasserfall der Welt (Salto Ángel) in Metern?', 'answer' => 979],
            ['question' => 'Wie groß ist der Vatikan in Hektar?', 'answer' => 44],
            ['question' => 'Wie viele Einwohner hat Island ungefähr?', 'answer' => 390000],
            ['question' => 'Wie lang ist die Grenze zwischen Deutschland und Österreich in Kilometern?', 'answer' => 817],
            ['question' => 'Wie weit sind Berlin und München Luftlinie voneinander entfernt in Kilometern?', 'answer' => 504],
            ['question' => 'Wie weit ist der Mond von der Erde entfernt in Kilometern?', 'answer' => 384400],
            ['question' => 'Wie viele Millionen Kilometer ist die Sonne von der Erde entfernt?', 'answer' => 150],
            ['question' => 'Wie groß ist der Umfang der Erde am Äquator in Kilometern?', 'answer' => 40075],
            ['question' => 'Wie tief ist der Baikalsee, der tiefste See der Welt, in Metern?', 'answer' => 1642],
            ['question' => 'Wie groß ist die Sahara in Millionen Quadratkilometern?', 'answer' => 9],
            ['question' => 'Wie tief ist der Marianengraben ungefähr in Metern?', 'answer' => 11000],
            ['question' => 'Wie lang ist die Chinesische Mauer laut offizieller Vermessung in Kilometern?', 'answer' => 21196],
            ['question' => 'Wie lang ist die Golden Gate Bridge in Metern?', 'answer' => 2737],
            ['question' => 'Wie lang war die legendäre Route 66 in Kilometern?', 'answer' => 3940],
            ['question' => 'Wie hoch ist die Freiheitsstatue mit Sockel in Metern?', 'answer' => 93],
            ['question' => 'Wie hoch ist die Cheops-Pyramide heute in Metern?', 'answer' => 139],
            ['question' => 'Wie viele Figuren umfasst die Terrakotta-Armee ungefähr?', 'answer' => 8000],
            ['question' => 'Wie hoch ist der Elizabeth Tower ("Big Ben") in Metern?', 'answer' => 96],
            ['question' => 'Wie lang war die Titanic in Metern?', 'answer' => 269],
            ['question' => 'Wie viele Stufen führen auf den Eiffelturm bis zur Spitze?', 'answer' => 1665],
            ['question' => 'Wie viel wiegt der Eiffelturm ungefähr in Tonnen?', 'answer' => 10000],
            ['question' => 'Wie viele Brücken gibt es ungefähr in Venedig?', 'answer' => 435],
            ['question' => 'Wie viele Stufen hat die Turmbesteigung des Kölner Doms?', 'answer' => 533],
            ['question' => 'Wie viele Jahre wurde am Kölner Dom insgesamt gebaut?', 'answer' => 632],
            ['question' => 'Wie hoch ist der Ulmer Münster, der höchste Kirchturm der Welt, in Metern?', 'answer' => 161],
            ['question' => 'Wie lang ist die deutsche Küste (Nord- und Ostsee) ungefähr in Kilometern?', 'answer' => 2400],
            ['question' => 'Wie lang war die Berliner Mauer rund um West-Berlin in Kilometern?', 'answer' => 155],
            ['question' => 'Wie viele Landkreise gibt es in Deutschland?', 'answer' => 294],
            ['question' => 'Wie viele Kilometer Autobahn gibt es in Deutschland?', 'answer' => 13200],

            // Deutschland: Alltag & Kurioses
            ['question' => 'Wie viele McDonald\'s-Filialen gibt es ungefähr in Deutschland?', 'answer' => 1400],
            ['question' => 'Wie viele Bahnhöfe und Haltepunkte betreibt die Deutsche Bahn ungefähr?', 'answer' => 5400],
            ['question' => 'Wie viele Einwohner hat Bielefeld ungefähr?', 'answer' => 334000],
            ['question' => 'Wie viele Millionen Currywürste werden in Deutschland pro Jahr gegessen?', 'answer' => 800],
            ['question' => 'Wie viele Brotsorten sind im deutschen Brotregister eingetragen?', 'answer' => 3200],
            ['question' => 'Wie viele Brauereien gibt es ungefähr in Deutschland?', 'answer' => 1500],
            ['question' => 'Wie viele verschiedene Biere werden in Deutschland ungefähr gebraut?', 'answer' => 7500],
            ['question' => 'Wie viele Kirchengebäude gibt es ungefähr in Deutschland?', 'answer' => 44000],
            ['question' => 'Wie viele Burgen und Schlösser gibt es ungefähr in Deutschland?', 'answer' => 25000],
            ['question' => 'Wie viele Fußballvereine gibt es im DFB ungefähr?', 'answer' => 24000],
            ['question' => 'Wie viele Millionen Mitglieder hat der ADAC?', 'answer' => 21],
            ['question' => 'Wie viele Dönerläden gibt es ungefähr in Deutschland?', 'answer' => 17000],
            ['question' => 'Wie viele Stufen hat der Aufstieg zum Ulmer Münster?', 'answer' => 768],
            ['question' => 'Wie hoch ist der Berliner Fernsehturm in Metern?', 'answer' => 368],
            ['question' => 'Wie hoch ist die Zugspitze in Metern?', 'answer' => 2962],
            ['question' => 'Wie viele Liter Bier trinkt ein Deutscher durchschnittlich pro Jahr?', 'answer' => 88],
            ['question' => 'Wie viele Liter Kaffee trinkt ein Deutscher durchschnittlich pro Jahr?', 'answer' => 169],
            ['question' => 'Wie viele Kilogramm Kartoffeln isst ein Deutscher durchschnittlich pro Jahr?', 'answer' => 60],
            ['question' => 'Wie viele Maß Bier werden auf dem Oktoberfest insgesamt ungefähr ausgeschenkt (in Millionen)?', 'answer' => 7],
            ['question' => 'Wie viele Ampelanlagen gibt es ungefähr in Deutschland?', 'answer' => 1500000],
            ['question' => 'Wie viele Streichhölzer sind in einer klassischen Haushaltsschachtel?', 'answer' => 38],
            ['question' => 'Wie viele Stichwörter enthält der Duden ungefähr?', 'answer' => 148000],
            ['question' => 'Wie viele Buchstaben hat das berühmte "Rindfleischetikettierungsüberwachungsaufgabenübertragungsgesetz"?', 'answer' => 63],

            // Körper & Biologie
            ['question' => 'Wie viele Haare hat ein Mensch ungefähr auf dem Kopf?', 'answer' => 100000],
            ['question' => 'Wie oft schlägt ein Herz ungefähr pro Tag?', 'answer' => 100000],
            ['question' => 'Wie oft blinzelt ein Mensch ungefähr pro Tag?', 'answer' => 15000],
            ['question' => 'Wie viele Atemzüge macht ein Mensch ungefähr pro Tag?', 'answer' => 20000],
            ['question' => 'Wie viele Kilometer Blutgefäße hat ein menschlicher Körper ungefähr?', 'answer' => 100000],
            ['question' => 'Wie viele Milliarden Nervenzellen hat das menschliche Gehirn ungefähr?', 'answer' => 86],
            ['question' => 'Wie viele Knochen hat ein einzelner menschlicher Fuß?', 'answer' => 26],
            ['question' => 'Wie viele Milliliter Speichel produziert ein Mensch ungefähr pro Tag?', 'answer' => 1000],
            ['question' => 'Wie viele Billionen Zellen hat ein menschlicher Körper ungefähr?', 'answer' => 37],
            ['question' => 'Wie viel wiegt ein menschliches Herz ungefähr in Gramm?', 'answer' => 300],
            ['question' => 'Wie viel wiegt ein menschliches Gehirn ungefähr in Gramm?', 'answer' => 1400],
            ['question' => 'Wie viele Tage lebt ein rotes Blutkörperchen ungefähr?', 'answer' => 120],
            ['question' => 'Wie viele Millionen Lungenbläschen hat ein Mensch ungefähr?', 'answer' => 300],
            ['question' => 'Wie viele Liter Blut pumpt das Herz ungefähr pro Tag?', 'answer' => 7000],
            ['question' => 'Wie viele Wörter spricht ein Mensch durchschnittlich pro Tag?', 'answer' => 16000],
            ['question' => 'Wie viele Geschmacksknospen hat ein Mensch ungefähr auf der Zunge?', 'answer' => 10000],
            ['question' => 'Wie viele Knochen hat ein Baby bei der Geburt ungefähr?', 'answer' => 300],
            ['question' => 'Wie viele Muskeln hat der Mensch ungefähr?', 'answer' => 650],
            ['question' => 'Wie viele Haare verliert ein Mensch durchschnittlich pro Tag?', 'answer' => 100],

            // Tiere
            ['question' => 'Wie oft schlägt das Herz eines Kolibris pro Minute (im Flug)?', 'answer' => 1200],
            ['question' => 'Wie schnell wird ein Wanderfalke im Sturzflug in km/h?', 'answer' => 320],
            ['question' => 'Wie viel wiegt ein ausgewachsener Blauwal ungefähr in Tonnen?', 'answer' => 150],
            ['question' => 'Wie viel wiegt allein die Zunge eines Blauwals ungefähr in Tonnen?', 'answer' => 3],
            ['question' => 'Wie viel wiegt ein ausgewachsener afrikanischer Elefant ungefähr in Kilogramm?', 'answer' => 5000],
            ['question' => 'Wie viele Bienen leben ungefähr in einem Bienenvolk im Sommer?', 'answer' => 50000],
            ['question' => 'Wie viele Millionen Blüten müssen Bienen für ein Glas Honig anfliegen?', 'answer' => 2],
            ['question' => 'Wie viele Beine hat der beinreichste bekannte Tausendfüßer?', 'answer' => 750],
            ['question' => 'Wie viele Zähne verbraucht ein Hai ungefähr in seinem Leben?', 'answer' => 30000],
            ['question' => 'Wie viele Augen hat eine Jakobsmuschel ungefähr?', 'answer' => 200],
            ['question' => 'Wie viele Stunden schläft ein Koala ungefähr pro Tag?', 'answer' => 20],
            ['question' => 'Wie viel wiegt ein ausgewachsener Strauß ungefähr in Kilogramm?', 'answer' => 130],
            ['question' => 'Wie viel wiegt ein Straußenei ungefähr in Gramm?', 'answer' => 1600],
            ['question' => 'Wie viele Monate ist ein Elefant trächtig?', 'answer' => 22],
            ['question' => 'Wie schnell läuft ein Gepard maximal in km/h?', 'answer' => 110],
            ['question' => 'Wie alt kann eine Galapagos-Riesenschildkröte ungefähr werden?', 'answer' => 150],
            ['question' => 'Wie viele Herzen hat ein Regenwurm?', 'answer' => 5],
            ['question' => 'Wie hoch kann ein Floh im Verhältnis springen — das Wievielfache seiner Körperlänge?', 'answer' => 100],

            // Essen & Trinken
            ['question' => 'Wie viele Milligramm Koffein stecken ungefähr in einer Tasse Filterkaffee?', 'answer' => 100],
            ['question' => 'Wie viele Zuckerwürfel stecken ungefähr in einem Liter Cola?', 'answer' => 35],
            ['question' => 'Wie viele Kalorien hat ein Big Mac?', 'answer' => 500],
            ['question' => 'Wie viele Kerne sitzen ungefähr auf einer Erdbeere?', 'answer' => 200],
            ['question' => 'Wie viele Kakaobohnen braucht man ungefähr für eine Tafel Schokolade?', 'answer' => 50],
            ['question' => 'Wie viele Gummibärchen sind ungefähr in einer 200-Gramm-Tüte?', 'answer' => 85],
            ['question' => 'Wie viele Spaghetti stecken ungefähr in einer 500-Gramm-Packung?', 'answer' => 400],
            ['question' => 'Wie viele Kalorien hat eine Flasche Bier (0,5 l) ungefähr?', 'answer' => 210],
            ['question' => 'Wie viele Weintrauben braucht man ungefähr für eine Flasche Wein?', 'answer' => 600],
            ['question' => 'Wie viele Tassen Espresso ergibt ein Kilogramm Kaffeebohnen ungefähr?', 'answer' => 140],
            ['question' => 'Wie viele Erdnüsse stecken ungefähr in einem 350-Gramm-Glas Erdnussbutter?', 'answer' => 540],
            ['question' => 'Wie viele Pommes-Portionen liefert ein Hektar Kartoffelacker ungefähr?', 'answer' => 100000],

            // Technik, Weltall & Rekorde
            ['question' => 'In welcher Höhe kreist die Internationale Raumstation ISS ungefähr (in Kilometern)?', 'answer' => 400],
            ['question' => 'Wie schnell fliegt die ISS ungefähr in km/h?', 'answer' => 28000],
            ['question' => 'Wie viele Flugzeuge sind weltweit ungefähr gleichzeitig in der Luft?', 'answer' => 10000],
            ['question' => 'Aus wie vielen Millionen Einzelteilen besteht ein Jumbo-Jet ungefähr?', 'answer' => 6],
            ['question' => 'Wie viele Tasten hat eine klassische PC-Tastatur mit Ziffernblock?', 'answer' => 105],
            ['question' => 'Wie viele Teile hat das große LEGO-Eiffelturm-Set ungefähr?', 'answer' => 10000],
            ['question' => 'Wie viele Gewitter toben weltweit ungefähr gleichzeitig?', 'answer' => 2000],
            ['question' => 'Wie oft schlägt der Blitz weltweit ungefähr pro Sekunde ein?', 'answer' => 100],
            ['question' => 'Wie viele Google-Suchanfragen gibt es ungefähr pro Sekunde weltweit?', 'answer' => 100000],
            ['question' => 'Wie viele Milliarden WhatsApp-Nachrichten werden pro Tag ungefähr verschickt?', 'answer' => 100],
            ['question' => 'Wie viele Milliarden Jahre ist das Universum alt?', 'answer' => 14],
            ['question' => 'Wie heiß ist es im Erdkern ungefähr in Grad Celsius?', 'answer' => 6000],
            ['question' => 'Wie viele Milliarden Sterne hat die Milchstraße ungefähr?', 'answer' => 200],
            ['question' => 'Wie heiß ist die Oberfläche der Sonne ungefähr in Grad Celsius?', 'answer' => 5500],
            ['question' => 'Wie viele Erdtage dauert ein Jahr auf dem Mars?', 'answer' => 687],
            ['question' => 'Wie viele aktive Satelliten kreisen ungefähr um die Erde?', 'answer' => 10000],
            ['question' => 'Wie viele Kilometer legt ein Auto in Deutschland durchschnittlich pro Jahr zurück?', 'answer' => 12500],
            ['question' => 'Wie viele Einzelteile hat ein durchschnittliches Auto ungefähr?', 'answer' => 10000],

            // Geschichte & Kultur
            ['question' => 'Wie viele Tage dauerte der Zweite Weltkrieg in Europa?', 'answer' => 2077],
            ['question' => 'Wie alt wurde der älteste Mensch der Welt (Jeanne Calment)?', 'answer' => 122],
            ['question' => 'Wie viele Jahre saß Queen Elizabeth II. auf dem Thron?', 'answer' => 70],
            ['question' => 'Wie viele Päpste gab es bis heute ungefähr?', 'answer' => 267],
            ['question' => 'Wie viele Lieder haben die Beatles offiziell veröffentlicht?', 'answer' => 213],
            ['question' => 'Wie viele James-Bond-Filme gibt es insgesamt (offizielle Reihe)?', 'answer' => 25],
            ['question' => 'Wie viele Filme hat Alfred Hitchcock gedreht?', 'answer' => 53],
            ['question' => 'In wie viele Sprachen wurde die Bibel komplett übersetzt (ungefähr)?', 'answer' => 700],
            ['question' => 'Wie viele Menschen starben ungefähr beim Untergang der Titanic?', 'answer' => 1500],
            ['question' => 'Wie viele Jahre dauerte der Bau der Sagrada Família bisher (Stand heute, seit 1882)?', 'answer' => 144],
            ['question' => 'Wie viele Kilogramm wiegt die Goldene Maske des Tutanchamun?', 'answer' => 10],
            ['question' => 'Wie viele Zeichen hat das chinesische Standard-Wörterbuch ungefähr?', 'answer' => 50000],
            ['question' => 'Wie viele Gemälde hat Vincent van Gogh ungefähr gemalt?', 'answer' => 900],
            ['question' => 'Wie viele Wörter umfasst der erste Harry-Potter-Band ungefähr?', 'answer' => 77000],

            // Sport & Spiel
            ['question' => 'Wie viele Zuschauer sahen das WM-Finale 1950 im Maracanã — Weltrekord (ungefähr)?', 'answer' => 200000],
            ['question' => 'Wie viele Tennisbälle werden in Wimbledon pro Turnier ungefähr verbraucht?', 'answer' => 55000],
            ['question' => 'Wie viele Kilometer ist die Tour de France insgesamt ungefähr lang?', 'answer' => 3500],
            ['question' => 'Wie viele Kurven hat die Nürburgring-Nordschleife?', 'answer' => 73],
            ['question' => 'Wie viele Dellen (Dimples) hat ein Golfball ungefähr?', 'answer' => 336],
            ['question' => 'Wie viele Zentimeter Durchmesser hat eine Turnier-Dartscheibe?', 'answer' => 45],
            ['question' => 'Wie viele Läufer starten beim Berlin-Marathon ungefähr?', 'answer' => 45000],
            ['question' => 'Wie viele Profikämpfe bestritt Muhammad Ali?', 'answer' => 61],
            ['question' => 'Wie viele Steine hat ein Mühle-Spiel insgesamt?', 'answer' => 18],
            ['question' => 'Wie viele Karten gibt es bei UNO?', 'answer' => 108],
            ['question' => 'Wie viele Buchstabensteine hat das deutsche Scrabble?', 'answer' => 102],
            ['question' => 'Wie hoch ist das Preisgeld für den Wimbledon-Sieger ungefähr (in Millionen Euro)?', 'answer' => 3],
            ['question' => 'Wie viele Kilometer läuft ein Fußball-Profi durchschnittlich pro Spiel?', 'answer' => 11],
            ['question' => 'Wie viel wiegt die Meisterschale der Bundesliga in Kilogramm?', 'answer' => 11],
            ['question' => 'Wie viele Mitglieder hat der FC Bayern München ungefähr?', 'answer' => 400000],
            ['question' => 'Wie viele Golfbälle liegen schätzungsweise auf dem Mond?', 'answer' => 2],
            ['question' => 'Wie viele Stufen hat der Treppenlauf im Empire State Building (Run-Up)?', 'answer' => 1576],
            ['question' => 'Wie viele Muskeln benutzt ein Mensch ungefähr beim Gehen?', 'answer' => 200],
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
