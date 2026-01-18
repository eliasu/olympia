<?php

namespace App\Services;

use Statamic\Facades\Entry;
use Statamic\Facades\Collection;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\Facades\Log;

class LeagueService
{
    protected $league;
    protected $session;

    /**
     * Generate a session plan (matchmaking).
     *
     * @param string $sessionId
     * @return array Matches created
     */
    public function generateSessionPlan($sessionId)
    {
        $session = Entry::find($sessionId);
        if (!$session) {
            throw new \Exception("Session not found");
        }

        $league = Entry::find($session->get('league'));
        if (!$league) {
            throw new \Exception("League not found");
        }

        $presentPlayerIds = $session->get('present_players', []);
        $players = collect($presentPlayerIds)->map(function ($id) {
            return Entry::find($id);
        })->filter();

        $courtsCount = $session->get('courts_count');
        $gamesPerCourt = $session->get('games_per_court');
        
        // Total slots available = Courts * Games * 4 (since doubles)
        $totalGames = $courtsCount * $gamesPerCourt;
        // In a perfect world, we have 4 players per game.
        
        // Determine current status of league for "Fair" vs "Mixed"
        // Check how many sessions have happened in this league before today.
        // For simplicity, we can just check the league 'mixed_days_count' against
        // the number of finished sessions or just passed days.
        // Let's assume we rely on the session date or counting finished sessions.
        
        $finishedSessions = Entry::query()
            ->where('collection', 'sessions')
            ->where('league', $league->id())
            ->where('is_finished', true)
            ->count();
            
        $isMixedMode = $finishedSessions < $league->get('mixed_days_count', 0);

        // --- Matchmaking Logic (Simplified for Pre-Generation) --- 
        // 1. Sort players to prioritize those with fewer total_games if needed, 
        //    but for a single session, we want to give everyone roughly equal games TODAY.
        
        // We need to generate $totalGames matches.
        // Each match needs 4 players.
        
        // Let's create a pool of slots.
        // If we have P players and G games (requiring 4*G slots).
        // Each player plays (4*G) / P games on average.
        
        $matchesToCreate = [];
        
        // Basic Round Robin / Randomized distribution for now.
        // Detailed constraint solving is complex, so we will use a heuristic approach.
        
        $playerStats = $players->map(function ($p) {
            return [
                'id' => $p->id(), 
                'elo' => (float)$p->get('global_elo', 1500),
                'games_today' => 0
            ];
        });

        for ($i = 0; $i < $totalGames; $i++) {
            // Select 4 players who have played the least TODAY.
            $sortedByGames = $playerStats->sortBy('games_today');
            $candidates = $sortedByGames->take(4);
            
            // Increment their games count
            $candidates->each(function($c, $key) use ($playerStats) {
                $playerStats[$key]['games_today']++;
            });
            
            $selectedIds = $candidates->pluck('id');
            $selectedPlayersStats = $candidates->values();

            // Pair them
            if ($isMixedMode) {
                // Random pairs
                $shuffled = $selectedPlayersStats->shuffle();
                $teamA = $shuffled->take(2);
                $teamB = $shuffled->slice(2, 2);
            } else {
                // Fair pairing: Try to balance Elo
                // Sort 4 players by Elo
                $sortedElo = $selectedPlayersStats->sortBy('elo')->values();
                // Strongest + Weakest vs Middle two is usually a good balance strategy
                // Or (1 & 4) vs (2 & 3)
                $teamA = collect([$sortedElo[0], $sortedElo[3]]);
                $teamB = collect([$sortedElo[1], $sortedElo[2]]);
            }

            // Create Match Entry
            $match = Entry::make()
                ->collection('matches')
                ->slug('match-' . $sessionId . '-' . ($i + 1))
                ->data([
                    'title' => 'Match ' . ($i + 1),
                    'session' => $sessionId,
                    'team_a' => $teamA->pluck('id')->all(),
                    'team_b' => $teamB->pluck('id')->all(),
                    'is_played' => false,
                ]);
            
            $match->save();
            $matchesToCreate[] = $match->id();
        }

        // Link matches to session? Not strictly necessary if matches have session_id, 
        // but session blueprint has generated_matches field? No, I put generated_matches as "Link to Matches" in prompt but created "Matches" with session_id.
        // Let's stick to querying Matches by session_id.
        
        return $matchesToCreate;
    }

    /**
     * Finalize the session: Update Elo.
     * 
     * @param string $sessionId
     */
    public function finalizeSession($sessionId)
    {
        $session = Entry::find($sessionId);
        $league = Entry::find($session->get('league'));
        $kFactor = $league->get('k_factor', 32);
        
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->where('session', $sessionId)
            ->where('is_played', true)
            ->get();
            
        foreach ($matches as $match) {
            $this->processMatchElo($match, $kFactor);
        }
        
        $session->set('is_finished', true);
        $session->save();
    }
    
    protected function processMatchElo($match, $kFactor)
    {
        // Don't re-process if already has delta? Or maybe we assume safe to recalculate.
        // Prompt says "update global_elo values", implies we commit the change.
        
        $teamAIds = $match->get('team_a');
        $teamBIds = $match->get('team_b');
        
        $teamAPlayers = collect($teamAIds)->map(fn($id) => Entry::find($id));
        $teamBPlayers = collect($teamBIds)->map(fn($id) => Entry::find($id));
        
        $scoreA = (int)$match->get('score_a');
        $scoreB = (int)$match->get('score_b');
        
        // Elo Calculation
        $eloA = $teamAPlayers->avg(fn($p) => (float)$p->get('global_elo', 1500));
        $eloB = $teamBPlayers->avg(fn($p) => (float)$p->get('global_elo', 1500));
        
        $expectedA = 1 / (1 + pow(10, ($eloB - $eloA) / 400));
        // $expectedB = 1 - $expectedA;
        
        $pointsTotal = $scoreA + $scoreB;
        if ($pointsTotal == 0) return; // Prevent division by zero
        
        $actualA = $scoreA / $pointsTotal;
        
        $delta = $kFactor * ($actualA - $expectedA);
        
        // Win bonus? Prompt: "Implementiere einen kleinen Sieg-Bonus, damit der Gewinner keine Punkte verliert."
        // If Winner is A but Delta is negative (because they should have won bigger), maybe clamp it?
        // Or literally add a flat bonus. Let's clamp delta to be >= 0 if won.
        
        if ($scoreA > $scoreB && $delta < 0) {
            $delta = 1; // Small positive value
        } elseif ($scoreB > $scoreA && $delta > 0) {
            $delta = -1;
        }

        // Store Delta on Match
        $match->set('elo_delta', $delta);
        $match->save();
        
        // Update Players
        foreach ($teamAPlayers as $player) {
            $newElo = $player->get('global_elo', 1500) + $delta;
            $player->set('global_elo', $newElo);
            $player->set('total_games', $player->get('total_games', 0) + 1);
            $player->save();
        }
        
        foreach ($teamBPlayers as $player) {
            $newElo = $player->get('global_elo', 1500) - $delta;
            $player->set('global_elo', $newElo);
            $player->set('total_games', $player->get('total_games', 0) + 1);
            $player->save();
        }
    }
}
