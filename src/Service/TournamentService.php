<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\Tournament;
use App\Entity\Player;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;

class TournamentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerRepository $playerRepository
    ) {}

    public function initializeTournament(Game $game): Tournament
    {
        // Check if tournament already exists
        if ($game->getTournament()) {
            return $game->getTournament();
        }

        $tournament = new Tournament();
        $tournament->setGame($game);

        // Get participants based on game type
        if ($game->getGameType() === 'tournament_team') {
            $participants = $this->createTeamParticipants($game);
        } else {
            $participants = $this->createSingleParticipants($game);
        }

        $tournament->initializeBracket($participants);

        $this->entityManager->persist($tournament);
        $this->entityManager->flush();

        return $tournament;
    }

    private function createTeamParticipants(Game $game): array
    {
        $players = $game->getOlympix()->getPlayers()->toArray();
        $teamSize = $game->getTeamSize() ?? 2;
        
        // Shuffle players for random team assignment
        shuffle($players);
        
        $teams = [];
        $teamId = 1;
        
        for ($i = 0; $i < count($players); $i += $teamSize) {
            $teamPlayers = array_slice($players, $i, $teamSize);
            
            if (count($teamPlayers) === $teamSize) {
                $teamTotalPoints = array_sum(array_map(fn($p) => $p->getTotalPoints(), $teamPlayers));
                
                $teams[] = [
                    'id' => $teamId,
                    'name' => 'Team ' . $teamId,
                    'players' => array_map(fn($p) => [
                        'id' => $p->getId(),
                        'name' => $p->getName(),
                        'total_points' => $p->getTotalPoints()
                    ], $teamPlayers),
                    'total_points' => $teamTotalPoints,
                    'type' => 'team'
                ];
                
                $teamId++;
            }
        }
        
        return $teams;
    }

    private function createSingleParticipants(Game $game): array
    {
        $players = $game->getOlympix()->getPlayers()->toArray();
        
        $participants = [];
        foreach ($players as $player) {
            $participants[] = [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'total_points' => $player->getTotalPoints(),
                'type' => 'player'
            ];
        }
        
        return $participants;
    }

    public function getNextMatch(Tournament $tournament): ?array
    {
        return $tournament->getNextMatch();
    }

    public function updateMatchResult(Tournament $tournament, string $matchId, array $winner): void
    {
        $tournament->updateMatchResult($matchId, $winner);
        $this->entityManager->flush();
    }

    public function isTournamentComplete(Tournament $tournament): bool
    {
        $bracketData = $tournament->getBracketData();
        
        // Check if final match is completed
        if (empty($bracketData['rounds'])) {
            return false;
        }
        
        $finalRound = end($bracketData['rounds']);
        $finalMatch = $finalRound[0] ?? null;
        
        $finalCompleted = $finalMatch && $finalMatch['completed'];
        
        // Check if third place match is completed (if it exists)
        $thirdPlaceCompleted = true;
        if (isset($bracketData['thirdPlaceMatch'])) {
            $thirdPlaceCompleted = $bracketData['thirdPlaceMatch']['completed'];
        }
        
        return $finalCompleted && $thirdPlaceCompleted;
    }

    public function getTournamentResults(Tournament $tournament): array
    {
        if (!$this->isTournamentComplete($tournament)) {
            return [];
        }

        return $tournament->getTournamentResults();
    }

    public function generateBracketHtml(Tournament $tournament): string
    {
        $bracketData = $tournament->getBracketData();
        $html = '<div class="tournament-bracket">';
        
        if (empty($bracketData['rounds'])) {
            return '<div class="text-center text-gray-500">Bracket noch nicht initialisiert</div>';
        }
        
        $html .= '<div class="bracket-rounds grid gap-8" style="grid-template-columns: repeat(' . count($bracketData['rounds']) . ', 1fr);">';
        
        foreach ($bracketData['rounds'] as $roundIndex => $round) {
            $html .= '<div class="round">';
            $html .= '<h3 class="text-lg font-bold mb-4 text-center">';
            
            if ($roundIndex === count($bracketData['rounds']) - 1) {
                $html .= 'Finale';
            } elseif ($roundIndex === count($bracketData['rounds']) - 2) {
                $html .= 'Halbfinale';
            } else {
                $html .= 'Runde ' . ($roundIndex + 1);
            }
            
            $html .= '</h3>';
            
            foreach ($round as $match) {
                $html .= $this->generateMatchHtml($match);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Add third place match if exists
        if (isset($bracketData['thirdPlaceMatch'])) {
            $html .= '<div class="third-place-match mt-8">';
            $html .= '<h3 class="text-lg font-bold mb-4 text-center">Spiel um Platz 3</h3>';
            $html .= $this->generateMatchHtml($bracketData['thirdPlaceMatch']);
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    private function generateMatchHtml(array $match): string
    {
        $html = '<div class="match border-2 border-gray-300 rounded-lg p-4 mb-4 bg-white">';
        
        $participant1 = $match['participant1'];
        $participant2 = $match['participant2'];
        $winner = $match['winner'];
        $completed = $match['completed'];
        
        // Participant 1
        $html .= '<div class="participant mb-2 p-2 rounded ' . 
                ($winner && $winner['id'] === $participant1['id'] ? 'bg-green-100 border-green-500' : 'bg-gray-50') . '">';
        $html .= '<span class="font-medium">' . ($participant1['name'] ?? 'TBD') . '</span>';
        if ($participant1 && isset($participant1['total_points'])) {
            $html .= '<span class="text-sm text-gray-600 ml-2">(' . $participant1['total_points'] . ' Punkte)</span>';
        }
        $html .= '</div>';
        
        $html .= '<div class="text-center text-gray-400 text-sm">vs</div>';
        
        // Participant 2
        $html .= '<div class="participant mt-2 p-2 rounded ' . 
                ($winner && $winner['id'] === $participant2['id'] ? 'bg-green-100 border-green-500' : 'bg-gray-50') . '">';
        $html .= '<span class="font-medium">' . ($participant2['name'] ?? 'TBD') . '</span>';
        if ($participant2 && isset($participant2['total_points'])) {
            $html .= '<span class="text-sm text-gray-600 ml-2">(' . $participant2['total_points'] . ' Punkte)</span>';
        }
        $html .= '</div>';
        
        // Match status
        if ($completed) {
            $html .= '<div class="mt-3 text-center">';
            $html .= '<span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">';
            $html .= 'Gewinner: ' . $winner['name'];
            $html .= '</span>';
            $html .= '</div>';
        } elseif ($participant1 && $participant2) {
            $html .= '<div class="mt-3 text-center">';
            $html .= '<button class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 match-result-btn" data-match-id="' . $match['id'] . '">';
            $html .= 'Ergebnis eingeben';
            $html .= '</button>';
            $html .= '</div>';
        } else {
            $html .= '<div class="mt-3 text-center text-gray-500 text-sm">Warten auf Teilnehmer</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    public function canStartTournament(Game $game): bool
    {
        $playerCount = $game->getOlympix()->getPlayers()->count();
        
        if ($game->getGameType() === 'tournament_team') {
            $teamSize = $game->getTeamSize() ?? 2;
            return $playerCount >= ($teamSize * 2); // At least 2 teams
        }
        
        return $playerCount >= 2; // At least 2 players
    }

    public function getTournamentStats(Tournament $tournament): array
    {
        $bracketData = $tournament->getBracketData();
        
        $totalMatches = 0;
        $completedMatches = 0;
        
        foreach ($bracketData['rounds'] as $round) {
            foreach ($round as $match) {
                $totalMatches++;
                if ($match['completed']) {
                    $completedMatches++;
                }
            }
        }
        
        if (isset($bracketData['thirdPlaceMatch'])) {
            $totalMatches++;
            if ($bracketData['thirdPlaceMatch']['completed']) {
                $completedMatches++;
            }
        }
        
        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'progress_percentage' => $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100) : 0,
            'current_round' => $tournament->getCurrentRound(),
            'is_completed' => $tournament->isIsCompleted()
        ];
    }
}