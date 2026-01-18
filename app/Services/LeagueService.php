<?php

namespace App\Services;

use Statamic\Facades\Entry;
use Statamic\Facades\Collection;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\Facades\Log;

class LeagueService
{
    protected $league;
    protected $gameday;
    
    /**
     * Generate a gameday plan (matchmaking).
     *
     * @param string $gamedayId
     * @return array Matches created
     */
    public function generateGamedayPlan($gamedayId)
    {
        $gameday = Entry::find($gamedayId);
        if (!$gameday) {
            throw new \Exception("Gameday not found");
        }
        
        // Check if plan already generated
        if ($gameday->get('generated_plan')) {
            throw new \Exception('Plan wurde bereits generiert. Bitte Gameday zurücksetzen, falls nötig.');
        }

        $leagueId = $gameday->get('league');
        if (is_array($leagueId)) {
            $leagueId = reset($leagueId);
        }
        
        $league = Entry::find($leagueId);
        if (!$league) {
            throw new \Exception("League not found");
        }

        $presentPlayerIds = $gameday->get('present_players', []);
        $players = collect($presentPlayerIds)->map(function ($id) {
            return Entry::find($id);
        })->filter();

        $courtsCount = $gameday->get('courts_count');
        $gamesPerCourt = $gameday->get('games_per_court');
        
        // Total slots available = Courts * Games * 4 (since doubles)
        $totalGames = $courtsCount * $gamesPerCourt;
        // In a perfect world, we have 4 players per game.
        
        // Matchmaking: Pure "Power Pairing"
        // Always try to balance games by Elo. If Elos are equal, random is fine (shimmed by sort stability or explicit shuffle).

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
                // Fix: Indirect modification of overloaded element error
                // We must explicitly get, modify, and set the value for Collections of arrays
                $stats = $playerStats[$key];
                $stats['games_today']++;
                $playerStats[$key] = $stats;
            });
            
            $selectedIds = $candidates->pluck('id');
            $selectedPlayersStats = $candidates->values();

            // Pair them
            // Pair them using Power Pairing
            // Goal: Minimize difference between (Elo A + Elo B) and (Elo C + Elo D)
            // Strategy: Sort 4 players by Elo.
            // Best balance is usually (Strongest + Weakest) vs (2nd Strongest + 2nd Weakest)
            // i.e., (1st + 4th) vs (2nd + 3rd)
            
            // Randomize first to ensure if Elos are identical, we get random pairs
            $candidates = $candidates->shuffle(); 
            
            // Sort by Elo descending
            $sortedElo = $candidates->sortByDesc('elo')->values();
            
            // Check if we have variance. If all Elos are effectively same, just take random shuffle from above.
            if ($sortedElo->first()['elo'] - $sortedElo->last()['elo'] < 1.0) {
                 // Random pairing
                $teamA = $candidates->take(2);
                $teamB = $candidates->slice(2, 2);
            } else {
                 // Power Pairing: (1, 4) vs (2, 3)
                $teamA = collect([$sortedElo[0], $sortedElo[3]]);
                $teamB = collect([$sortedElo[1], $sortedElo[2]]);
            }

            // Create Match Entry
            $matchTitle = $gameday->get('title') . ' - Match ' . ($i + 1);
            
            $match = Entry::make()
                ->collection('matches')
                ->slug('match-' . $gamedayId . '-' . ($i + 1))
                ->data([
                    'title' => $matchTitle,
                    'team_a' => $teamA->pluck('id')->all(),
                    'team_b' => $teamB->pluck('id')->all(),
                    'is_played' => false,
                ]);
            
            // Explicitly set relationship field
            $match->set('gameday', [$gamedayId]);
            
            $match->save();
            $matchesToCreate[] = $match->id();
        }
        
        // Lock the gameday and save matches
        $gameday->set('generated_plan', true);
        $gameday->set('matches', $matchesToCreate);
        $gameday->save();

        return $matchesToCreate;
    }

    /**
     * Finalize the gameday: Update Elo.
     * 
     * @param string $gamedayId
     */
    public function finalizeGameday($gamedayId)
    {
        $gameday = Entry::find($gamedayId);
        
        if (!$gameday->get('generated_plan')) {
             throw new \Exception('Es wurde noch kein Plan generiert.');
        }
        
        if ($gameday->get('is_finished')) {
             throw new \Exception('Gameday ist bereits abgeschlossen.');
        }

        $leagueId = $gameday->get('league');
        if (is_array($leagueId)) {
            $leagueId = reset($leagueId);
        }

        $league = Entry::find($leagueId);
        $kFactor = $league->get('k_factor', 32);
        
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->where('gameday', $gamedayId)
            ->where('is_played', true)
            ->get();
            
        foreach ($matches as $match) {
            $this->processMatchElo($match, $kFactor);
        }
        
        $gameday->set('is_finished', true);
        $gameday->save();
        
        // Update cached stats for all present players
        $presentPlayers = $gameday->get('present_players', []);
        foreach ($presentPlayers as $playerId) {
            $this->updatePlayerLeagueStats($playerId);
        }
    }
    
    /**
     * Update cached league stats on the player entry.
     * 
     * @param string $playerId
     */
    public function updatePlayerLeagueStats($playerId)
    {
        $player = Entry::find($playerId);
        if (!$player) return;

        // Find all finished gamedays where player was present
        $gamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->where('is_finished', true)
            ->whereJsonContains('present_players', $playerId)
            ->get();
            
        // Group by league and count
        $statsByLeague = $gamedays->groupBy(fn($day) => $day->get('league'))->map->count();
        
        // Build Grid data
        $gridData = [];
        foreach ($statsByLeague as $leagueId => $count) {
             // Verify league exists
             if (Entry::find($leagueId)) {
                $gridData[] = [
                    'league' => [$leagueId],
                    'played_games' => $count
                ];
             }
        }
        
        $player->set('league_stats', $gridData);
        $player->save();
    }
    
    /**
     * Get stats for a player in a specific league.
     * 
     * @param string $playerId
     * @param string $leagueId
     * @return array {played_games: int}
     */
    public function getPlayerLeagueStats($playerId, $leagueId)
    {
        // Count finished gamedays in this league where player was present
        $playedDays = Entry::query()
            ->where('collection', 'gamedays')
            ->where('league', $leagueId)
            ->where('is_finished', true)
            ->whereJsonContains('present_players', $playerId)
            ->count();
            
        return [
            'played_game_days' => $playedDays,
        ];
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
            $player->set('global_elo', round($newElo, 2));
            $player->set('total_games', $player->get('total_games', 0) + 1);
            $player->save();
        }
        
        foreach ($teamBPlayers as $player) {
            $newElo = $player->get('global_elo', 1500) - $delta;
            $player->set('global_elo', round($newElo, 2));
            $player->set('total_games', $player->get('total_games', 0) + 1);
            $player->save();
        }
    }
}
