<?php

namespace App\Services;

use Statamic\Facades\Entry;
use Statamic\Facades\Collection;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\Facades\Log;

/**
 * LeagueService
 * 
 * Core service for managing league operations including:
 * - Gameday matchmaking (balanced team generation)
 * - Elo rating calculations
 * - League statistics and rankings (simple win percentage)
 */
class LeagueService
{
    // Matchmaking configuration constants
    const ELO_SPREAD = 150;              // Maximum Elo difference for matchmaking (±150)
    const PARTNER_PENALTY = 1000;        // Penalty for repeating partners (strong avoidance)
    const OPPONENT_PENALTY = 500;        // Penalty for repeating opponents (moderate avoidance)
    
    /**
     * Generate a gameday plan with balanced matchmaking.
     * 
     * Creates balanced matches for a gameday using:
     * - Elo-based skill matching
     * - Partner/opponent diversity tracking
     * - Power pairing for team balance
     *
     * @param string $gamedayId
     * @return array Array of created match IDs
     * @throws \Exception If gameday not found, already generated, or insufficient players
     */
    public function generateGamedayPlan($gamedayId)
    {
        $gameday = Entry::find($gamedayId);
        if (!$gameday) {
            throw new \Exception("Gameday not found");
        }
        
        // Prevent duplicate plan generation
        if ($gameday->get('generated_plan')) {
            throw new \Exception('Plan wurde bereits generiert. Bitte Gameday zurücksetzen, falls nötig.');
        }

        // Get league
        $leagueId = $gameday->get('league');
        if (is_array($leagueId)) {
            $leagueId = reset($leagueId);
        }
        
        $league = Entry::find($leagueId);
        if (!$league) {
            throw new \Exception("League not found");
        }

        // Get present players
        $presentPlayerIds = $gameday->get('present_players', []);
        $players = collect($presentPlayerIds)->map(function ($id) {
            return Entry::find($id);
        })->filter();

        if ($players->count() < 4) {
            throw new \Exception('Mindestens 4 Spieler benötigt.');
        }

        // Calculate total matches needed
        $courtsCount = $gameday->get('courts_count');
        $gamesPerCourt = $gameday->get('games_per_court');
        $totalGames = $courtsCount * $gamesPerCourt;
        
        // Initialize player tracking for matchmaking
        // Uses global Elo for skill-based matching, but league-specific match counts for fairness
        $playerStats = $players->map(function ($p) use ($leagueId) {
            // Get league-specific match count
            $leagueStats = collect($p->get('league_stats', []))->first(function($row) use ($leagueId) {
                $rowLeagues = (array)($row['league'] ?? []);
                $rowLeagueId = reset($rowLeagues);
                return $rowLeagueId === $leagueId;
            });
            
            $leagueMatchCount = (int)($leagueStats['match_count'] ?? 0);
            
            return [
                'id' => $p->id(), 
                'elo' => (float)$p->get('global_elo', 1500),
                'league_matches' => $leagueMatchCount,  // Use league-specific count
                'games_today' => 0,
                'partners_today' => [],      // Track partners for diversity
                'opponents_today' => [],     // Track opponents for diversity
            ];
        });

        $matchesToCreate = [];
        
        // Generate each match
        for ($i = 0; $i < $totalGames; $i++) {
            // 1. Select 4 players with skill-based diversity
            $selectedPlayers = $this->selectDiversePlayers($playerStats, self::ELO_SPREAD);
            
            if ($selectedPlayers->count() < 4) {
                Log::warning("Not enough players for match " . ($i + 1));
                continue;
            }

            // 2. Create balanced teams using power pairing
            $teams = $this->createBalancedTeams($selectedPlayers);
            
            // 3. Update player tracking (games played, partners, opponents)
            $this->updatePlayerTracking($playerStats, $teams);
            
            // 4. Create match entry
            $matchTitle = $gameday->get('title') . ' - Match ' . ($i + 1);
            
            $match = Entry::make()
                ->collection('matches')
                ->slug('match-' . $gamedayId . '-' . ($i + 1))
                ->data([
                    'title' => $matchTitle,
                    'team_a' => $teams['team_a']->pluck('id')->all(),
                    'team_b' => $teams['team_b']->pluck('id')->all(),
                    'is_played' => false,
                ]);
            
            $match->set('gameday', [$gamedayId]);
            $match->save();
            $matchesToCreate[] = $match->id();
        }
        
        // Mark gameday as having a generated plan
        $gameday->set('generated_plan', true);
        $gameday->set('matches', $matchesToCreate);
        $gameday->save();

        return $matchesToCreate;
    }

    /**
     * Select 4 players with skill-based diversity and history tracking.
     * 
     * Algorithm:
     * 1. Prioritize players with fewest games today
     * 2. Select seed player (fewest games)
     * 3. Find 3 partners within Elo band (±ELO_SPREAD)
     * 4. Apply diversity penalties for repeated pairings
     *
     * @param \Illuminate\Support\Collection $playerStats Player tracking data
     * @param int $eloSpread Maximum Elo difference allowed
     * @return \Illuminate\Support\Collection Collection of 4 selected players
     */
    protected function selectDiversePlayers($playerStats, $eloSpread)
    {
        // Sort by priority: fewest games today, then fewest league matches
        $available = $playerStats
            ->sortBy('league_matches')
            ->sortBy('games_today')
            ->values();
        
        $minGames = $available->min('games_today');
        
        // Eligible pool: players with minimal games today
        $eligiblePool = $available->filter(fn($p) => 
            $p['games_today'] <= $minGames + 1
        )->values();
        
        if ($eligiblePool->count() < 4) {
            // Fallback: take the 4 with fewest games
            return $available->take(4);
        }
        
        // Select seed player (player with fewest games)
        $seed = $eligiblePool->first();
        $seedElo = $seed['elo'];
        
        // Find 3 partners within Elo band
        $candidates = $eligiblePool
            ->reject(fn($p) => $p['id'] === $seed['id'])
            ->filter(fn($p) => abs($p['elo'] - $seedElo) <= $eloSpread);
        
        // Expand search if not enough candidates
        if ($candidates->count() < 3) {
            $candidates = $eligiblePool->reject(fn($p) => $p['id'] === $seed['id']);
        }
        
        // Prioritize diversity (avoid repeating partners/opponents)
        $selected = $candidates
            ->map(function($p) use ($seed) {
                // Calculate selection score (lower = better)
                $eloDiff = abs($p['elo'] - $seed['elo']);
                
                // Check if this player was already partner/opponent with seed today
                $wasPartner = in_array($p['id'], $seed['partners_today']);
                $wasOpponent = in_array($p['id'], $seed['opponents_today']);
                
                $diversityPenalty = 0;
                if ($wasPartner) $diversityPenalty += self::PARTNER_PENALTY;
                if ($wasOpponent) $diversityPenalty += self::OPPONENT_PENALTY;
                
                $p['selection_score'] = $eloDiff + $diversityPenalty;
                return $p;
            })
            ->sortBy('selection_score')
            ->take(3);
        
        // Fallback if still not enough
        if ($selected->count() < 3) {
            $alreadySelected = $selected->pluck('id')->push($seed['id']);
            $extras = $candidates
                ->reject(fn($p) => $alreadySelected->contains($p['id']))
                ->sortBy('games_today')
                ->take(3 - $selected->count());
            $selected = $selected->concat($extras);
        }
        
        return collect([$seed])->concat($selected);
    }

    /**
     * Create balanced teams using power pairing.
     * 
     * Power Pairing Strategy:
     * - Strongest + Weakest vs. Middle Two
     * - Example: [1650, 1580, 1520, 1480]
     *   Team A: 1650 + 1480 = Avg 1565
     *   Team B: 1580 + 1520 = Avg 1550
     *
     * @param \Illuminate\Support\Collection $players Collection of 4 players
     * @return array ['team_a' => Collection, 'team_b' => Collection]
     */
    protected function createBalancedTeams($players)
    {
        // Sort by Elo (descending)
        $sorted = $players->sortByDesc('elo')->values();
        
        // Power pairing: strongest + weakest vs. middle two
        $teamA = collect([$sorted[0], $sorted[3]]);
        $teamB = collect([$sorted[1], $sorted[2]]);
        
        return [
            'team_a' => $teamA,
            'team_b' => $teamB
        ];
    }

    /**
     * Update player tracking after match assignment.
     * 
     * Tracks for each player:
     * - Games played today
     * - Partners played with today
     * - Opponents played against today
     *
     * @param \Illuminate\Support\Collection $playerStats Player tracking data (by reference)
     * @param array $teams ['team_a' => Collection, 'team_b' => Collection]
     */
    protected function updatePlayerTracking(&$playerStats, $teams)
    {
        $teamAIds = $teams['team_a']->pluck('id')->all();
        $teamBIds = $teams['team_b']->pluck('id')->all();
        
        foreach ($playerStats as $key => $stats) {
            $playerId = $stats['id'];
            
            if (in_array($playerId, $teamAIds)) {
                // Player is in Team A
                $stats['games_today']++;
                
                // Partner = other player in Team A
                $partner = array_values(array_diff($teamAIds, [$playerId]));
                $stats['partners_today'] = array_merge(
                    $stats['partners_today'], 
                    $partner
                );
                
                // Opponents = Team B
                $stats['opponents_today'] = array_merge(
                    $stats['opponents_today'], 
                    $teamBIds
                );
                
                $playerStats[$key] = $stats;
            } 
            elseif (in_array($playerId, $teamBIds)) {
                // Player is in Team B
                $stats['games_today']++;
                
                $partner = array_values(array_diff($teamBIds, [$playerId]));
                $stats['partners_today'] = array_merge(
                    $stats['partners_today'], 
                    $partner
                );
                
                $stats['opponents_today'] = array_merge(
                    $stats['opponents_today'], 
                    $teamAIds
                );
                
                $playerStats[$key] = $stats;
            }
        }
    }

    /**
     * Finalize a gameday by processing all match results.
     * 
     * Steps:
     * 1. Calculate Elo changes for all played matches
     * 2. Update player league statistics
     * 3. Recalculate league rankings
     * 
     * @param string $gamedayId
     * @throws \Exception If plan not generated or already finished
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

        // Get league and K-factor
        $leagueId = $gameday->get('league');
        if (is_array($leagueId)) {
            $leagueId = reset($leagueId);
        }

        $league = Entry::find($leagueId);
        $kFactor = $league->get('k_factor', 32);
        
        // Process Elo for all played matches
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->where('gameday', $gamedayId)
            ->where('is_played', true)
            ->get();
            
        foreach ($matches as $match) {
            $this->processMatchElo($match, $kFactor);
        }
        
        // NEW: Calculate gameday rankings BEFORE marking as finished
        $gamedayRankings = $this->calculateGamedayRankings($gamedayId, $matches);
        
        // Store rankings in gameday
        $gameday->set('gameday_rankings', $gamedayRankings);
        
        // Mark gameday as finished
        $gameday->set('is_finished', true);
        $gameday->save();
        
        // Update league stats for all present players
        $presentPlayers = $gameday->get('present_players', []);
        foreach ($presentPlayers as $playerId) {
            $this->updatePlayerLeagueStats($playerId);
        }

        // Recalculate rankings for ALL leagues where present players participate
        $affectedLeagues = $this->getAllLeaguesForPlayers($presentPlayers);
        foreach ($affectedLeagues as $affectedLeagueId) {
            $this->recalculateLeagueRanks($affectedLeagueId);
        }
    }
    
    /**
     * Calculate gameday rankings for all players who participated.
     * 
     * Ranks players based on:
     * 1. Win% of the day (primary)
     * 2. Elo Gain of the day (tiebreaker)
     * 3. Total Wins of the day (tiebreaker)
     * 4. Global Elo (final tiebreaker)
     * 
     * @param string $gamedayId
     * @param \Illuminate\Support\Collection $matches Played matches from this gameday
     * @return array Ranking data for each player
     */
    protected function calculateGamedayRankings($gamedayId, $matches)
    {
        $gameday = Entry::find($gamedayId);
        $presentPlayerIds = $gameday->get('present_players', []);
        
        $rankings = [];
        
        foreach ($presentPlayerIds as $playerId) {
            $player = Entry::find($playerId);
            if (!$player) continue;
            
            // Find all matches this player participated in
            $playerMatches = $matches->filter(function($match) use ($playerId) {
                $teamA = (array)$match->get('team_a', []);
                $teamB = (array)$match->get('team_b', []);
                return in_array($playerId, $teamA) || in_array($playerId, $teamB);
            });
            
            // Skip players who didn't play any matches
            if ($playerMatches->count() === 0) {
                continue;
            }
            
            // Get starting Elo from first match
            $firstMatch = $playerMatches->first();
            $isTeamA = in_array($playerId, (array)$firstMatch->get('team_a', []));
            $elosBefore = $isTeamA 
                ? $firstMatch->get('team_a_elo_before') 
                : $firstMatch->get('team_b_elo_before');
            
            $teamIds = $isTeamA 
                ? (array)$firstMatch->get('team_a') 
                : (array)$firstMatch->get('team_b');
            $playerIndex = array_search($playerId, $teamIds);
            $eloStart = $elosBefore[$playerIndex] ?? 1500;
            
            // Get ending Elo (current player Elo after all updates)
            $eloEnd = (float)$player->get('global_elo', 1500);
            $eloGain = round($eloEnd - $eloStart, 2);
            
            // Count wins/losses today
            $winsToday = 0;
            $lossesToday = 0;
            
            foreach ($playerMatches as $match) {
                $isTeamA = in_array($playerId, (array)$match->get('team_a', []));
                $scoreA = (int)$match->get('score_a');
                $scoreB = (int)$match->get('score_b');
                
                $playerWon = $isTeamA ? ($scoreA > $scoreB) : ($scoreB > $scoreA);
                
                if ($playerWon) {
                    $winsToday++;
                } else {
                    $lossesToday++;
                }
            }
            
            $matchesPlayed = $winsToday + $lossesToday;
            $winPercentage = $matchesPlayed > 0 ? ($winsToday / $matchesPlayed) * 100 : 0;
            
            $rankings[] = [
                'player_id' => $playerId,
                'player' => [$playerId],  // Array format for Statamic entries field
                'matches_played' => $matchesPlayed,
                'wins' => $winsToday,
                'losses' => $lossesToday,
                'win_percentage' => round($winPercentage, 2),
                'elo_start' => round($eloStart, 2),
                'elo_end' => round($eloEnd, 2),
                'elo_gain' => $eloGain,
                'global_elo' => $eloEnd  // For tiebreaker sorting
            ];
        }
        
        // Sort by ranking criteria
        usort($rankings, function($a, $b) {
            // 1. Win% (descending)
            if ($a['win_percentage'] != $b['win_percentage']) {
                return $b['win_percentage'] <=> $a['win_percentage'];
            }
            
            // 2. Elo Gain (descending)
            if ($a['elo_gain'] != $b['elo_gain']) {
                return $b['elo_gain'] <=> $a['elo_gain'];
            }
            
            // 3. Total Wins (descending)
            if ($a['wins'] != $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }
            
            // 4. Global Elo (descending)
            return $b['global_elo'] <=> $a['global_elo'];
        });
        
        // Assign ranks and remove helper fields
        foreach ($rankings as $index => &$ranking) {
            $ranking['rank'] = $index + 1;
            unset($ranking['player_id']);  // Remove helper field
            unset($ranking['global_elo']);  // Remove helper field
        }
        
        return $rankings;
    }
    
    /**
     * Get all unique league IDs where the given players have stats.
     * 
     * Used to determine which leagues need rank recalculation when
     * a gameday is finalized.
     * 
     * @param array $playerIds Array of player IDs
     * @return array Unique league IDs
     */
    protected function getAllLeaguesForPlayers($playerIds)
    {
        $leagueIds = collect();
        
        foreach ($playerIds as $playerId) {
            $player = Entry::find($playerId);
            if (!$player) continue;
            
            $leagueStats = $player->get('league_stats', []);
            foreach ($leagueStats as $stat) {
                $statLeague = $stat['league'] ?? [];
                if (is_array($statLeague) && !empty($statLeague)) {
                    $leagueIds->push(reset($statLeague));
                }
            }
        }
        
        return $leagueIds->unique()->filter()->values()->all();
    }
    
    /**
     * Update a player's league statistics (simple win percentage).
     * 
     * Calculates and stores (LEAGUE-SPECIFIC):
     * - Gamedays played per league
     * - Total matches played per league
     * - Wins and losses per league
     * - Win percentage per league
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
            
        // Find all played matches for this player
        $allMatches = Entry::query()
            ->where('collection', 'matches')
            ->where('is_played', true)
            ->get()
            ->filter(function($match) use ($playerId) {
                return in_array($playerId, (array)$match->get('team_a', [])) || 
                       in_array($playerId, (array)$match->get('team_b', []));
            });

        // Process performance by league (LEAGUE-SPECIFIC)
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
            
            // Initialize league stats if not exists
            if (!isset($performanceByLeague[$leagueId])) {
                $performanceByLeague[$leagueId] = [
                    'match_count' => 0,
                    'wins' => 0,
                    'losses' => 0
                ];
            }
            
            // Determine if player won
            $isTeamA = in_array($playerId, (array)$match->get('team_a', []));
            $scoreA = (int)$match->get('score_a');
            $scoreB = (int)$match->get('score_b');
            
            $playerScore = $isTeamA ? $scoreA : $scoreB;
            $opponentScore = $isTeamA ? $scoreB : $scoreA;
            
            $won = $playerScore > $opponentScore;
            
            // Update stats
            if ($won) {
                $performanceByLeague[$leagueId]['wins']++;
            } else {
                $performanceByLeague[$leagueId]['losses']++;
            }
            
            $performanceByLeague[$leagueId]['match_count']++;
        }

        // Build grid data for each league
        $gridData = [];
        $gamedaysByLeague = $gamedays->groupBy(function($day) {
            $leagueIds = (array)$day->get('league', []);
            return !empty($leagueIds) ? reset($leagueIds) : null;
        });

        foreach ($gamedaysByLeague as $leagueId => $days) {
             if (!$leagueId || !Entry::find($leagueId)) continue;
             
             $perf = $performanceByLeague[$leagueId] ?? [
                 'match_count' => 0, 
                 'wins' => 0, 
                 'losses' => 0
             ];
             
             // Calculate simple win percentage
             $totalMatches = $perf['wins'] + $perf['losses'];
             $winPercentage = $totalMatches > 0 ? ($perf['wins'] / $totalMatches) * 100 : 0;
             
             $gridData[] = [
                 'league' => [$leagueId],
                 'played_gamedays' => $days->count(),
                 'match_count' => $perf['match_count'],
                 'league_wins' => $perf['wins'],
                 'league_losses' => $perf['losses'],
                 'win_percentage' => round($winPercentage, 2)
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
     * @return array League statistics
     */
    public function getPlayerLeagueStats($playerId, $leagueId)
    {
        $player = Entry::find($playerId);
        if (!$player) {
            return [
                'played_game_days' => 0, 
                'match_count' => 0, 
                'win_percentage' => 0
            ];
        }

        $stats = collect($player->get('league_stats', []))->first(function($row) use ($leagueId) {
            $rowLeagues = (array)($row['league'] ?? []);
            $rowLeagueId = reset($rowLeagues);
            return $rowLeagueId === $leagueId;
        });
            
        return [
            'played_game_days' => (int)($stats['played_gamedays'] ?? 0),
            'match_count' => (int)($stats['match_count'] ?? 0),
            'win_percentage' => (float)($stats['win_percentage'] ?? 0)
        ];
    }

    /**
     * Process Elo calculation for a match.
     * 
     * Uses TRUE ELO system (no win protection):
     * - Expected outcome based on team average Elo
     * - Actual outcome based on score ratio
     * - Delta = K-factor × (Actual - Expected)
     * 
     * Also tracks Elo history for each player.
     *
     * @param \Statamic\Entries\Entry $match
     * @param int $kFactor Elo K-factor (typically 32)
     */
    protected function processMatchElo($match, $kFactor)
    {
        $teamAIds = $match->get('team_a');
        $teamBIds = $match->get('team_b');
        
        $teamAPlayers = collect($teamAIds)->map(fn($id) => Entry::find($id))->filter();
        $teamBPlayers = collect($teamBIds)->map(fn($id) => Entry::find($id))->filter();
        
        if ($teamAPlayers->count() < 2 || $teamBPlayers->count() < 2) {
            Log::warning("Match {$match->id()} has incomplete teams");
            return;
        }
        
        $scoreA = (int)$match->get('score_a');
        $scoreB = (int)$match->get('score_b');
        
        // Capture Elo BEFORE changes
        $teamAEloBefore = $teamAPlayers->map(fn($p) => round((float)$p->get('global_elo', 1500), 2))->values()->all();
        $teamBEloBefore = $teamBPlayers->map(fn($p) => round((float)$p->get('global_elo', 1500), 2))->values()->all();
        
        // Calculate team average Elo
        $eloA = $teamAPlayers->avg(fn($p) => (float)$p->get('global_elo', 1500));
        $eloB = $teamBPlayers->avg(fn($p) => (float)$p->get('global_elo', 1500));
        
        // Expected win probability for Team A (Elo formula)
        $expectedA = 1 / (1 + pow(10, ($eloB - $eloA) / 400));
        
        // Actual performance based on score ratio
        $pointsTotal = $scoreA + $scoreB;
        if ($pointsTotal == 0) return; // Prevent division by zero
        
        $actualA = $scoreA / $pointsTotal;
        
        // Calculate Elo delta (TRUE ELO - no win protection)
        $delta = $kFactor * ($actualA - $expectedA);
        
        // Get gameday and league info for history tracking
        $gamedayId = $match->get('gameday')[0] ?? null;
        $gameday = $gamedayId ? Entry::find($gamedayId) : null;
        $leagueId = $gameday ? ($gameday->get('league')[0] ?? null) : null;
        
        // Get date from gameday (Statamic extracts from filename for date-ordered collections)
        $matchDate = $gameday && $gameday->date() ? $gameday->date()->toIso8601String() : now()->toIso8601String();

        
        // Update Team A players
        foreach ($teamAPlayers as $player) {
            $oldElo = $player->get('global_elo', 1500);
            $newElo = $oldElo + $delta;
            $player->set('global_elo', round($newElo, 2));
            $player->set('total_games', $player->get('total_games', 0) + 1);
            
            // Update win/loss count
            if ($scoreA > $scoreB) {
                $player->set('wins', (int)$player->get('wins', 0) + 1);
            } else {
                $player->set('losses', (int)$player->get('losses', 0) + 1);
            }
            
            // Add to Elo history
            $history = $player->get('elo_history', []);
            $history[] = [
                'date' => $matchDate,
                'elo' => round($newElo, 2),
                'match' => $match->id(),
                'league' => $leagueId,
            ];
            $player->set('elo_history', $history);
            
            $player->save();
        }
        
        // Update Team B players
        foreach ($teamBPlayers as $player) {
            $oldElo = $player->get('global_elo', 1500);
            $newElo = $oldElo - $delta;
            $player->set('global_elo', round($newElo, 2));
            $player->set('total_games', $player->get('total_games', 0) + 1);
            
            // Update win/loss count
            if ($scoreB > $scoreA) {
                $player->set('wins', (int)$player->get('wins', 0) + 1);
            } else {
                $player->set('losses', (int)$player->get('losses', 0) + 1);
            }
            
            // Add to Elo history
            $history = $player->get('elo_history', []);
            $history[] = [
                'date' => $matchDate,
                'elo' => round($newElo, 2),
                'match' => $match->id(),
                'league' => $leagueId,
            ];
            $player->set('elo_history', $history);
            
            $player->save();
        }
        
        // Capture Elo AFTER changes
        $teamAEloAfter = $teamAPlayers->map(fn($p) => round((float)$p->get('global_elo', 1500), 2))->values()->all();
        $teamBEloAfter = $teamBPlayers->map(fn($p) => round((float)$p->get('global_elo', 1500), 2))->values()->all();
        
        // Save Elo data to match for history/display
        $match->set('elo_delta', $delta);
        $match->set('team_a_elo_before', $teamAEloBefore);
        $match->set('team_a_elo_after', $teamAEloAfter);
        $match->set('team_b_elo_before', $teamBEloBefore);
        $match->set('team_b_elo_after', $teamBEloAfter);
        $match->save();
    }

    /**
     * Recalculate league rankings based on simple win percentage.
     * 
     * Ranking Algorithm:
     * 1. Qualified players first (met minimum gameday requirement)
     * 2. Sort by win percentage (descending)
     * 3. Tiebreaker 1: Total wins (descending)
     * 4. Tiebreaker 2: Global Elo (descending)
     * 
     * Only qualified players receive a rank number.
     * 
     * @param string $leagueId
     */
    public function recalculateLeagueRanks($leagueId)
    {
        $league = Entry::find($leagueId);
        if (!$league) return;
        
        $minGameDays = (int)$league->get('min_game_days', 0);
        $players = Entry::query()->where('collection', 'players')->get();
        
        // Build ranking data for all players
        $rankingData = $players->map(function($player) use ($leagueId, $minGameDays) {
            $stats = collect($player->get('league_stats', []))->first(function($row) use ($leagueId) {
                $rowLeagues = (array)($row['league'] ?? []);
                $rowLeagueId = reset($rowLeagues);
                return $rowLeagueId === $leagueId;
            });
            
            $playedGamedays = (int)($stats['played_gamedays'] ?? 0);
            $leagueWins = (int)($stats['league_wins'] ?? 0);
            $leagueLosses = (int)($stats['league_losses'] ?? 0);
            $isQualified = $playedGamedays >= $minGameDays;
            
            // Use pre-calculated win percentage from league_stats
            $winPercentage = (float)($stats['win_percentage'] ?? 0);

            return [
                'id' => $player->id(),
                'win_percentage' => $winPercentage,
                'league_wins' => $leagueWins,
                'league_losses' => $leagueLosses,
                'global_elo' => (float)$player->get('global_elo', 1500),
                'has_stats' => !empty($stats),
                'is_qualified' => $isQualified,
                'played_gamedays' => $playedGamedays
            ];
        })
        ->filter(fn($p) => $p['has_stats'])
        ->sort(function($a, $b) {
            // 1. Qualified players first
            if ($a['is_qualified'] && !$b['is_qualified']) return -1;
            if (!$a['is_qualified'] && $b['is_qualified']) return 1;
            
            // 2. Sort by win percentage (descending)
            if ($a['win_percentage'] != $b['win_percentage']) {
                return $b['win_percentage'] <=> $a['win_percentage'];
            }
            
            // 3. Tiebreaker 1: League wins (descending)
            if ($a['league_wins'] != $b['league_wins']) {
                return $b['league_wins'] <=> $a['league_wins'];
            }
            
            // 4. Final tiebreaker: Global Elo (descending)
            return $b['global_elo'] <=> $a['global_elo'];
        })
        ->values();

        // Assign ranks to players
        foreach ($rankingData as $index => $item) {
            $player = Entry::find($item['id']);
            $stats = $player->get('league_stats', []);
            
            foreach ($stats as &$row) {
                $rowLeagues = (array)($row['league'] ?? []);
                $rowLeagueId = reset($rowLeagues);
                if ($rowLeagueId === $leagueId) {
                    // Assign rank number if qualified
                    $row['rank'] = $item['is_qualified'] ? ($index + 1) : null;
                }
            }
            
            $player->set('league_stats', $stats);
            $player->save();
        }
    }
}