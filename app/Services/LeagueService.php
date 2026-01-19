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
                'total_games' => (int)$p->get('total_games', 0),
                'games_today' => 0
            ];
        });

        for ($i = 0; $i < $totalGames; $i++) {
            // 1. SELECT A SEED PLAYER
            // Priority: Least games played today, then least total league games (attendance)
            $seedIndex = $playerStats
                ->sortBy('total_games')
                ->sortBy('games_today')
                ->keys()
                ->first();
            
            if ($seedIndex === null) break; // Should not happen
            
            $seed = $playerStats[$seedIndex];
            
            // 2. SELECT 3 COMPATIBLE PARTNERS
            // We want players with similar Elo who also need to play.
            $minGamesToday = $playerStats->min('games_today');
            
            // First attempt: Players with similar Elo and low games today
            $candidatePool = $playerStats->reject(fn($p, $key) => $key === $seedIndex);
            
            $matchCandidates = $candidatePool
                ->filter(fn($p) => $p['games_today'] <= $minGamesToday + 1)
                ->map(function($p) use ($seed) {
                    $p['elo_diff'] = abs($p['elo'] - $seed['elo']);
                    return $p;
                })
                ->sortBy('elo_diff')
                ->take(3);
                
            // Fallback: If not enough candidates found with low games, take anyone remaining
            if ($matchCandidates->count() < 3) {
                $stillNeeded = 3 - $matchCandidates->count();
                $alreadySelectedIds = $matchCandidates->pluck('id')->push($seed['id']);
                
                $extraCandidates = $candidatePool
                    ->reject(fn($p) => $alreadySelectedIds->contains($p['id']))
                    ->sortBy('games_today')
                    ->take($stillNeeded);
                    
                $matchCandidates = $matchCandidates->concat($extraCandidates);
            }

            // Ensure we have 4 players. If still not enough (e.g. extreme low player count), skip match.
            if ($matchCandidates->count() < 3) continue;

            $matchPlayersStats = collect([$seed])->concat($matchCandidates);
            $selectedIds = $matchPlayersStats->pluck('id');

            // 3. INCREMENT GAMES COUNT IN THE POOL
            foreach ($playerStats as $key => $stats) {
                if ($selectedIds->contains($stats['id'])) {
                    $stats['games_today']++;
                    $playerStats[$key] = $stats;
                }
            }

            // 4. PAIR THEM INTO TEAMS
            $sortedElo = $matchPlayersStats->sortByDesc('elo')->values();
            
            if (rand(1, 100) <= 20) {
                $shuffled = $matchPlayersStats->shuffle()->values();
                $teamA = $shuffled->take(2);
                $teamB = $shuffled->slice(2, 2);
            } else {
                // Power Pairing: (Strongest + Weakest) vs (Middle Two)
                $teamA = collect([$sortedElo[0], $sortedElo[3]]);
                $teamB = collect([$sortedElo[1], $sortedElo[2]]);
            }

            // 5. CREATE MATCH ENTRY
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
    
    public function updatePlayerLeagueStats($playerId)
    {
        $player = Entry::find($playerId);
        if (!$player) return;

        // 1. Find all finished gamedays where player was present
        $gamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->where('is_finished', true)
            ->whereJsonContains('present_players', $playerId)
            ->get();
            
        // 2. Find all played matches for this player
        $allMatches = Entry::query()
            ->where('collection', 'matches')
            ->where('is_played', true)
            ->get()
            ->filter(function($match) use ($playerId) {
                return in_array($playerId, (array)$match->get('team_a', [])) || 
                       in_array($playerId, (array)$match->get('team_b', []));
            });

        // 3. Process performance by league
        $performanceByLeague = [];
        
        foreach ($allMatches as $match) {
            $gamedayIds = (array)$match->get('gameday', []);
            if (empty($gamedayIds)) continue;
            
            $gamedayId = reset($gamedayIds);
            $gameday = Entry::find($gamedayId);
            if (!$gameday || !$gameday->get('is_finished')) continue;
            
            $leagueIds = (array)$gameday->get('league', []);
            if (empty($leagueIds)) continue;
            
            $leagueId = reset($leagueIds);
            
            if (!isset($performanceByLeague[$leagueId])) {
                $performanceByLeague[$leagueId] = [
                    'delta_sum' => 0,
                    'match_count' => 0,
                    'wins' => 0,
                    'losses' => 0
                ];
            }
            
            $delta = (float)$match->get('elo_delta', 0);
            $isTeamA = in_array($playerId, (array)$match->get('team_a', []));
            $scoreA = (int)$match->get('score_a');
            $scoreB = (int)$match->get('score_b');
            
            // If player was in Team A, the delta stored is their gain.
            $actualDelta = $isTeamA ? $delta : -$delta;
            $won = $isTeamA ? ($scoreA > $scoreB) : ($scoreB > $scoreA);
            
            if ($won) {
                $performanceByLeague[$leagueId]['wins']++;
            } else {
                $performanceByLeague[$leagueId]['losses']++;
            }
            
            $performanceByLeague[$leagueId]['delta_sum'] += $actualDelta;
            $performanceByLeague[$leagueId]['match_count']++;
        }

        // 4. Build Grid data
        $gridData = [];
        $gamedaysByLeague = $gamedays->groupBy(function($day) {
            $leagueIds = (array)$day->get('league', []);
            return !empty($leagueIds) ? reset($leagueIds) : null;
        });

        foreach ($gamedaysByLeague as $leagueId => $days) {
             if (!$leagueId || !Entry::find($leagueId)) continue;
             
             $perf = $performanceByLeague[$leagueId] ?? ['delta_sum' => 0, 'match_count' => 0, 'wins' => 0, 'losses' => 0];
             $avgDelta = $perf['match_count'] > 0 ? $perf['delta_sum'] / $perf['match_count'] : 0;
             $gridData[] = [
                 'league' => [$leagueId],
                 'played_games' => $days->count(),
                 'match_count' => $perf['match_count'],
                 'league_performance' => round($perf['delta_sum'], 2),
                 'average_delta' => round($avgDelta, 2),
                 'league_wins' => $perf['wins'],
                 'league_losses' => $perf['losses']
             ];
        }
        
        $player->set('league_stats', $gridData);
        $player->save();
    }
    
    /**
     * Get stats for a player in a specific league.
     * 
     * @param string $playerId
     * @param string $leagueId
     * @return array
     */
    public function getPlayerLeagueStats($playerId, $leagueId)
    {
        $player = Entry::find($playerId);
        if (!$player) return ['played_game_days' => 0, 'match_count' => 0, 'league_performance' => 0];

        $stats = collect($player->get('league_stats', []))->first(function($row) use ($leagueId) {
            $rowLeagueId = reset((array)($row['league'] ?? []));
            return $rowLeagueId === $leagueId;
        });
            
        return [
            'played_game_days' => (int)($stats['played_games'] ?? 0),
            'match_count' => (int)($stats['match_count'] ?? 0),
            'league_performance' => (float)($stats['league_performance'] ?? 0),
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
            
            if ($scoreA > $scoreB) {
                $player->set('wins', (int)$player->get('wins', 0) + 1);
            } else {
                $player->set('losses', (int)$player->get('losses', 0) + 1);
            }
            
            $player->save();
        }
        
        foreach ($teamBPlayers as $player) {
            $newElo = $player->get('global_elo', 1500) - $delta;
            $player->set('global_elo', round($newElo, 2));
            $player->set('total_games', $player->get('total_games', 0) + 1);
            
            if ($scoreB > $scoreA) {
                $player->set('wins', (int)$player->get('wins', 0) + 1);
            } else {
                $player->set('losses', (int)$player->get('losses', 0) + 1);
            }
            
            $player->save();
        }
    }
}
