<?php

namespace App\Entity;

use App\Repository\TournamentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
class Tournament
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    private array $bracketData = [];

    #[ORM\Column]
    private ?int $currentRound = null;

    #[ORM\Column]
    private ?bool $isCompleted = null;

    #[ORM\OneToOne(inversedBy: 'tournament', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    public function __construct()
    {
        $this->currentRound = 1;
        $this->isCompleted = false;
        $this->bracketData = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBracketData(): array
    {
        return $this->bracketData;
    }

    public function setBracketData(array $bracketData): static
    {
        $this->bracketData = $bracketData;
        return $this;
    }

    public function getCurrentRound(): ?int
    {
        return $this->currentRound;
    }

    public function setCurrentRound(int $currentRound): static
    {
        $this->currentRound = $currentRound;
        return $this;
    }

    public function isIsCompleted(): ?bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;
        return $this;
    }

    public function initializeBracket(array $participants): void
    {
        $this->bracketData = $this->generateBracket($participants);
    }

    private function generateBracket(array $participants): array
    {
        $participantCount = count($participants);
        
        // Shuffle participants for random seeding
        shuffle($participants);
        
        $bracket = [
            'rounds' => [],
            'participants' => $participants,
            'bye' => null
        ];
        
        // Handle odd number of participants - give bye to lowest scoring participant
        if ($participantCount % 2 === 1) {
            $lowestScoringParticipant = $this->findLowestScoringParticipant($participants);
            $participants = array_filter($participants, function($p) use ($lowestScoringParticipant) {
                return $p['id'] !== $lowestScoringParticipant['id'];
            });
            $participants = array_values($participants);
            $bracket['bye'] = $lowestScoringParticipant;
        }
        
        // Create first round matches
        $firstRoundMatches = [];
        for ($i = 0; $i < count($participants); $i += 2) {
            $firstRoundMatches[] = [
                'id' => uniqid(),
                'participant1' => $participants[$i],
                'participant2' => $participants[$i + 1] ?? null,
                'winner' => null,
                'completed' => false,
                'round' => 1
            ];
        }
        
        $bracket['rounds'][] = $firstRoundMatches;
        
        // Generate subsequent rounds
        $this->generateSubsequentRounds($bracket);
        
        return $bracket;
    }

    private function generateSubsequentRounds(array &$bracket): void
    {
        $currentRoundMatches = end($bracket['rounds']);
        $roundNumber = count($bracket['rounds']) + 1;
        
        // Generate rounds until we have final
        while (count($currentRoundMatches) > 1) {
            $nextRoundMatches = [];
            
            for ($i = 0; $i < count($currentRoundMatches); $i += 2) {
                $nextRoundMatches[] = [
                    'id' => uniqid(),
                    'participant1' => null,
                    'participant2' => null,
                    'winner' => null,
                    'completed' => false,
                    'round' => $roundNumber,
                    'sourceMatch1' => $currentRoundMatches[$i]['id'],
                    'sourceMatch2' => $currentRoundMatches[$i + 1]['id'] ?? null
                ];
            }
            
            $bracket['rounds'][] = $nextRoundMatches;
            $currentRoundMatches = $nextRoundMatches;
            $roundNumber++;
        }
        
        // Add third place match if we have semifinals
        if (count($bracket['rounds']) >= 2) {
            $semifinalRound = $bracket['rounds'][count($bracket['rounds']) - 2];
            if (count($semifinalRound) === 2) {
                $bracket['thirdPlaceMatch'] = [
                    'id' => uniqid(),
                    'participant1' => null,
                    'participant2' => null,
                    'winner' => null,
                    'completed' => false,
                    'round' => $roundNumber,
                    'sourceMatch1' => $semifinalRound[0]['id'],
                    'sourceMatch2' => $semifinalRound[1]['id']
                ];
            }
        }
    }

    private function findLowestScoringParticipant(array $participants): array
    {
        $lowestScore = PHP_INT_MAX;
        $lowestParticipant = $participants[0];
        
        foreach ($participants as $participant) {
            if ($participant['total_points'] < $lowestScore) {
                $lowestScore = $participant['total_points'];
                $lowestParticipant = $participant;
            }
        }
        
        return $lowestParticipant;
    }

    public function updateMatchResult(string $matchId, array $winner): void
    {
        $this->updateMatchRecursive($this->bracketData, $matchId, $winner);
    }

    private function updateMatchRecursive(array &$bracket, string $matchId, array $winner): void
    {
        // Update match in rounds
        foreach ($bracket['rounds'] as &$round) {
            foreach ($round as &$match) {
                if ($match['id'] === $matchId) {
                    $match['winner'] = $winner;
                    $match['completed'] = true;
                    
                    // Propagate winner to next round
                    $this->propagateWinner($bracket, $matchId, $winner);
                    return;
                }
            }
        }
        
        // Update third place match if exists
        if (isset($bracket['thirdPlaceMatch']) && $bracket['thirdPlaceMatch']['id'] === $matchId) {
            $bracket['thirdPlaceMatch']['winner'] = $winner;
            $bracket['thirdPlaceMatch']['completed'] = true;
        }
    }

    private function propagateWinner(array &$bracket, string $sourceMatchId, array $winner): void
    {
        foreach ($bracket['rounds'] as &$round) {
            foreach ($round as &$match) {
                if (isset($match['sourceMatch1']) && $match['sourceMatch1'] === $sourceMatchId) {
                    $match['participant1'] = $winner;
                } elseif (isset($match['sourceMatch2']) && $match['sourceMatch2'] === $sourceMatchId) {
                    $match['participant2'] = $winner;
                }
            }
        }
        
        // Handle third place match - add LOSERS from semifinals
        if (isset($bracket['thirdPlaceMatch'])) {
            if ($bracket['thirdPlaceMatch']['sourceMatch1'] === $sourceMatchId) {
                $bracket['thirdPlaceMatch']['participant1'] = $this->getLoserFromMatch($bracket, $sourceMatchId);
            } elseif ($bracket['thirdPlaceMatch']['sourceMatch2'] === $sourceMatchId) {
                $bracket['thirdPlaceMatch']['participant2'] = $this->getLoserFromMatch($bracket, $sourceMatchId);
            }
        }
    }

    private function getLoserFromMatch(array $bracket, string $matchId): ?array
    {
        foreach ($bracket['rounds'] as $round) {
            foreach ($round as $match) {
                if ($match['id'] === $matchId && $match['completed']) {
                    // Return the participant who didn't win
                    return $match['participant1']['id'] === $match['winner']['id'] 
                        ? $match['participant2'] 
                        : $match['participant1'];
                }
            }
        }
        
        return null;
    }

    public function isMatchReady(string $matchId): bool
    {
        foreach ($this->bracketData['rounds'] as $round) {
            foreach ($round as $match) {
                if ($match['id'] === $matchId) {
                    return $match['participant1'] !== null && $match['participant2'] !== null;
                }
            }
        }
        
        // Check third place match
        if (isset($this->bracketData['thirdPlaceMatch']) && $this->bracketData['thirdPlaceMatch']['id'] === $matchId) {
            $match = $this->bracketData['thirdPlaceMatch'];
            return $match['participant1'] !== null && $match['participant2'] !== null;
        }
        
        return false;
    }

    public function getNextMatch(): ?array
    {
        foreach ($this->bracketData['rounds'] as $round) {
            foreach ($round as $match) {
                if (!$match['completed'] && $this->isMatchReady($match['id'])) {
                    return $match;
                }
            }
        }
        
        // Check third place match
        if (isset($this->bracketData['thirdPlaceMatch'])) {
            $thirdPlaceMatch = $this->bracketData['thirdPlaceMatch'];
            if (!$thirdPlaceMatch['completed'] && $this->isMatchReady($thirdPlaceMatch['id'])) {
                return $thirdPlaceMatch;
            }
        }
        
        return null;
    }

    public function getTournamentResults(): array
    {
        $results = [];
        
        // Get final match winner (1st place)
        $finalRound = end($this->bracketData['rounds']);
        if ($finalRound && count($finalRound) === 1) {
            $finalMatch = $finalRound[0];
            if ($finalMatch['completed']) {
                $results[1] = $finalMatch['winner'];
                // Get runner-up (2nd place)
                $results[2] = $finalMatch['participant1']['id'] === $finalMatch['winner']['id'] 
                    ? $finalMatch['participant2'] 
                    : $finalMatch['participant1'];
            }
        }
        
        // Get third place match winner (3rd place) and loser (4th place)
        if (isset($this->bracketData['thirdPlaceMatch']) && $this->bracketData['thirdPlaceMatch']['completed']) {
            $thirdPlaceMatch = $this->bracketData['thirdPlaceMatch'];
            $results[3] = $thirdPlaceMatch['winner'];
            $results[4] = $thirdPlaceMatch['participant1']['id'] === $thirdPlaceMatch['winner']['id'] 
                ? $thirdPlaceMatch['participant2'] 
                : $thirdPlaceMatch['participant1'];
        }
        
        return $results;
    }
}