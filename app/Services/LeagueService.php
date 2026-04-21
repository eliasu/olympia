<?php

namespace App\Services;

use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;
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
    const ELO_SPREAD = 100;              // Maximum Elo difference for matchmaking (±100)
    const PARTNER_PENALTY = 2000;        // Penalty for repeating partners (strong avoidance)
    const OPPONENT_PENALTY = 1000;       // Penalty for repeating opponents (moderate avoidance)
    const FOURSOME_REPEAT_PENALTY = 100000;  // Near-hard-block: same 4 players meeting again
    const TEAM_PAIR_REPEAT_PENALTY = 100000; // Near-hard-block: same 2 players partnered again
    
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
            return User::find($id);
        })->filter();

        if ($players->count() < 4) {
            throw new \Exception('Mindestens 4 Spieler benötigt.');
        }

        // Calculate total matches needed
        $courtsCount = (int)$gameday->get('courts_count', 0);
        $gamesPerCourt = (int)$gameday->get('games_per_court', 0);
        if ($courtsCount <= 0 || $gamesPerCourt <= 0) {
            throw new \Exception('Anzahl Courts und Spiele pro Court müssen gesetzt sein.');
        }
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

        // Track exact foursomes + team pairs already used this gameday.
        // Keys are sorted pipe-joined player IDs. Values = frequency.
        // Fed into scoring to block identical matches (e.g. M10 == M15 bug).
        $usedFoursomes = [];
        $usedTeamPairs = [];

        // Generate each match
        for ($i = 0; $i < $totalGames; $i++) {
            // 1. Select 4 players with skill-based diversity
            $selectedPlayers = $this->selectDiversePlayers($playerStats, self::ELO_SPREAD, $usedFoursomes);

            if ($selectedPlayers->count() < 4) {
                Log::warning("Not enough players for match " . ($i + 1));
                continue;
            }

            // 2. Create balanced teams, avoiding repeated team pairings
            $teams = $this->createBalancedTeams($selectedPlayers, $usedTeamPairs);

            // 3. Update player tracking (games played, partners, opponents)
            $this->updatePlayerTracking($playerStats, $teams);

            // Record foursome + team pairs for repeat avoidance in later matches
            $foursomeKey = $this->sortedIdKey($selectedPlayers->pluck('id')->all());
            $usedFoursomes[$foursomeKey] = ($usedFoursomes[$foursomeKey] ?? 0) + 1;

            foreach (['team_a', 'team_b'] as $teamKey) {
                $pairKey = $this->sortedIdKey($teams[$teamKey]->pluck('id')->all());
                $usedTeamPairs[$pairKey] = ($usedTeamPairs[$pairKey] ?? 0) + 1;
            }
            
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
        
        // Post-generation fairness check: warn if any player has fewer games than expected
        $totalSlots = $totalGames * 4;
        $minExpected = (int)floor($totalSlots / $players->count());
        $underPlayed = $playerStats->filter(fn($p) => $p['games_today'] < $minExpected);
        if ($underPlayed->isNotEmpty()) {
            Log::warning('Fairness-Check: Spieler unter Minimum-Spielzahl nach Generierung', [
                'expected_min' => $minExpected,
                'under_played' => $underPlayed->map(fn($p) => [
                    'id' => $p['id'],
                    'games_today' => $p['games_today'],
                ])->values()->all(),
            ]);
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
    protected function selectDiversePlayers($playerStats, $eloSpread, array $usedFoursomes = [])
    {
        $available = $playerStats
            ->shuffle()
            ->sortBy('league_matches')
            ->sortBy('games_today')
            ->values();

        $minGames = $available->min('games_today');
        $minTier  = $available->filter(fn($p) => $p['games_today'] === $minGames)->values();

        if ($minTier->count() >= 4) {
            // Normal path: enough underplayed players to fill a match on their own.
            $eligiblePool = $minTier;

            // If exactly 4 and that foursome already played, pull in one more from
            // the next tier so a different group can be formed (fixes M10==M15 bug).
            if ($eligiblePool->count() === 4) {
                $poolKey = $this->sortedIdKey($eligiblePool->pluck('id')->all());
                $expand = 0;
                while (isset($usedFoursomes[$poolKey]) && $eligiblePool->count() < $available->count()) {
                    $expand++;
                    $eligiblePool = $available->filter(fn($p) =>
                        $p['games_today'] <= $minGames + $expand
                    )->values();
                    $poolKey = $this->sortedIdKey($eligiblePool->pluck('id')->all());
                }
            }

            // Seed: fewest interactions today, random tiebreak
            $seedCandidates = $eligiblePool
                ->filter(fn($p) => $p['games_today'] === $minGames)
                ->shuffle()
                ->values();
            if ($seedCandidates->isEmpty()) {
                $seedCandidates = $eligiblePool;
            }
            $seed = $seedCandidates
                ->sortBy(fn($p) => count($p['partners_today']) + count($p['opponents_today']))
                ->first();

            $candidates = $eligiblePool->reject(fn($p) => $p['id'] === $seed['id'])->values();

            return collect($this->pickBestGroup($seed, $candidates->all(), $usedFoursomes));

        } else {
            // Fairness-critical path: fewer than 4 players at minGames.
            // ALL of them MUST be in this match — otherwise they fall below floor.
            // Fill remaining spots from the next tier via scoring.
            $forced    = $minTier->all();   // 1–3 players that are hard-included
            $forcedIds = array_column($forced, 'id');
            $fillers   = $available->filter(fn($p) => !in_array($p['id'], $forcedIds))->values();

            $needed = 4 - count($forced);
            $group  = $this->pickBestFillers($forced, $fillers->all(), $needed, $usedFoursomes);

            return collect($group);
        }
    }

    /**
     * Pick the best group of (seed + 3 others) by evaluating all combinations.
     * Returns the 4-element array with the lowest score.
     */
    protected function pickBestGroup(array $seed, array $candidates, array $usedFoursomes): array
    {
        $n         = count($candidates);
        $bestGroup = null;
        $bestScore = PHP_INT_MAX;

        for ($i = 0; $i < $n - 2; $i++) {
            for ($j = $i + 1; $j < $n - 1; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    $group = [$seed, $candidates[$i], $candidates[$j], $candidates[$k]];
                    $score = $this->scoreGroup($group, $usedFoursomes);
                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestGroup = $group;
                    }
                }
            }
        }

        return $bestGroup ?? array_merge([$seed], array_slice($candidates, 0, 3));
    }

    /**
     * Given forced players (must all be included) and filler candidates,
     * pick the best $needed fillers to complete a group of 4.
     * Returns the full 4-player group.
     */
    protected function pickBestFillers(array $forced, array $fillers, int $needed, array $usedFoursomes): array
    {
        $n         = count($fillers);
        $bestGroup = null;
        $bestScore = PHP_INT_MAX;

        if ($needed === 1) {
            foreach ($fillers as $f) {
                $group = array_merge($forced, [$f]);
                $score = $this->scoreGroup($group, $usedFoursomes);
                if ($score < $bestScore) { $bestScore = $score; $bestGroup = $group; }
            }
        } elseif ($needed === 2) {
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $group = array_merge($forced, [$fillers[$i], $fillers[$j]]);
                    $score = $this->scoreGroup($group, $usedFoursomes);
                    if ($score < $bestScore) { $bestScore = $score; $bestGroup = $group; }
                }
            }
        } elseif ($needed === 3) {
            for ($i = 0; $i < $n - 2; $i++) {
                for ($j = $i + 1; $j < $n - 1; $j++) {
                    for ($k = $j + 1; $k < $n; $k++) {
                        $group = array_merge($forced, [$fillers[$i], $fillers[$j], $fillers[$k]]);
                        $score = $this->scoreGroup($group, $usedFoursomes);
                        if ($score < $bestScore) { $bestScore = $score; $bestGroup = $group; }
                    }
                }
            }
        }

        return $bestGroup ?? array_merge($forced, array_slice($fillers, 0, $needed));
    }

    /**
     * Build a stable key from a list of player IDs. Sort first so order doesn't matter.
     */
    protected function sortedIdKey(array $ids): string
    {
        sort($ids);
        return implode('|', $ids);
    }

    /**
     * Score a group of 4 players by summing pairwise penalties.
     *
     * Evaluates all 6 pairs within the group. For each pair:
     * - Elo distance contributes to the score
     * - Repeated partners are penalized (frequency × PARTNER_PENALTY)
     * - Repeated opponents are penalized (frequency × OPPONENT_PENALTY)
     *
     * Lower score = better group.
     *
     * @param array $group Array of 4 player stat arrays
     * @return float Combined penalty score
     */
    protected function scoreGroup(array $group, array $usedFoursomes = []): float
    {
        $score = 0.0;

        for ($i = 0; $i < 3; $i++) {
            for ($j = $i + 1; $j < 4; $j++) {
                $a = $group[$i];
                $b = $group[$j];

                // Elo distance
                $score += abs($a['elo'] - $b['elo']);

                // Frequency-based partner penalty (2x partner → 2× penalty)
                $aPartnerCounts = array_count_values($a['partners_today']);
                $bPartnerCounts = array_count_values($b['partners_today']);
                $partnerFreq = max(
                    $aPartnerCounts[$b['id']] ?? 0,
                    $bPartnerCounts[$a['id']] ?? 0
                );
                $score += $partnerFreq * self::PARTNER_PENALTY;

                // Frequency-based opponent penalty
                $aOpponentCounts = array_count_values($a['opponents_today']);
                $bOpponentCounts = array_count_values($b['opponents_today']);
                $opponentFreq = max(
                    $aOpponentCounts[$b['id']] ?? 0,
                    $bOpponentCounts[$a['id']] ?? 0
                );
                $score += $opponentFreq * self::OPPONENT_PENALTY;
            }
        }

        // Exact foursome repeat: huge penalty, scales with frequency.
        // Acts as near-hard-block when any alternative group exists.
        $foursomeKey = $this->sortedIdKey(array_map(fn($p) => $p['id'], $group));
        $foursomeFreq = $usedFoursomes[$foursomeKey] ?? 0;
        $score += $foursomeFreq * self::FOURSOME_REPEAT_PENALTY;

        return $score;
    }

    /**
     * Create balanced teams by evaluating all 3 possible splits.
     *
     * Four players (sorted by Elo desc: [0]=strongest, [3]=weakest) have exactly
     * 3 distinct team splits:
     *  1. Power pairing:  [0,3] vs [1,2]  (strongest+weakest vs middles)
     *  2. Top-vs-bottom:  [0,1] vs [2,3]
     *  3. Mixed:          [0,2] vs [1,3]
     *
     * Each split scored on: Elo imbalance + partner-repeat penalty + team-pair-repeat
     * penalty. Lowest score wins. Fixes deterministic split bug that caused
     * identical match repeats (same 4 players → always same teams).
     *
     * @param \Illuminate\Support\Collection $players Collection of 4 players
     * @param array $usedTeamPairs Frequency map of already-used team pairs
     * @return array ['team_a' => Collection, 'team_b' => Collection]
     */
    protected function createBalancedTeams($players, array $usedTeamPairs = [])
    {
        $sorted = $players->sortByDesc('elo')->values();
        $p = [$sorted[0], $sorted[1], $sorted[2], $sorted[3]];

        $splits = [
            ['a' => [$p[0], $p[3]], 'b' => [$p[1], $p[2]]],  // power pairing
            ['a' => [$p[0], $p[1]], 'b' => [$p[2], $p[3]]],  // top vs bottom
            ['a' => [$p[0], $p[2]], 'b' => [$p[1], $p[3]]],  // mixed
        ];

        $bestSplit = null;
        $bestScore = PHP_INT_MAX;

        foreach ($splits as $split) {
            $score = $this->scoreSplit($split['a'], $split['b'], $usedTeamPairs);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestSplit = $split;
            }
        }

        return [
            'team_a' => collect($bestSplit['a']),
            'team_b' => collect($bestSplit['b']),
        ];
    }

    /**
     * Score a team split. Lower is better.
     * Combines Elo imbalance, partner-repeat penalty, and team-pair-repeat penalty.
     */
    protected function scoreSplit(array $teamA, array $teamB, array $usedTeamPairs): float
    {
        $score = 0.0;

        // Elo balance: absolute diff of team averages
        $avgA = ($teamA[0]['elo'] + $teamA[1]['elo']) / 2;
        $avgB = ($teamB[0]['elo'] + $teamB[1]['elo']) / 2;
        $score += abs($avgA - $avgB);

        // Per-team partner-repeat + prior-pair penalty
        foreach ([$teamA, $teamB] as $team) {
            [$x, $y] = $team;

            $xPartnerCounts = array_count_values($x['partners_today']);
            $yPartnerCounts = array_count_values($y['partners_today']);
            $partnerFreq = max(
                $xPartnerCounts[$y['id']] ?? 0,
                $yPartnerCounts[$x['id']] ?? 0
            );
            $score += $partnerFreq * self::PARTNER_PENALTY;

            $pairKey = $this->sortedIdKey([$x['id'], $y['id']]);
            $pairFreq = $usedTeamPairs[$pairKey] ?? 0;
            $score += $pairFreq * self::TEAM_PAIR_REPEAT_PENALTY;
        }

        return $score;
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
        
        // Setup User Cache for in-memory modification
        $usersCache = [];

        // OPT: Pre-compute matchDate once for all matches in this gameday
        $matchDate = $gameday->date()
            ? $gameday->date()->toIso8601String()
            : now()->toIso8601String();
        
        // Process Elo for all played matches, sorted by slug to guarantee
        // chronological order (slugs contain match number: match-...-1, match-...-2, etc.).
        // Without deterministic order, Elo cascades would be wrong for players in multiple matches.
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->where('gameday', $gamedayId)
            ->where('is_played', true)
            ->get()
            ->sortBy(fn($m) => (int)last(explode('-', $m->slug())));

        foreach ($matches as $match) {
            $this->processMatchElo($match, $kFactor, $usersCache, $leagueId, $matchDate);
        }
        
        // Batch save users after all Elo calculations for this gameday are done
        foreach ($usersCache as $player) {
            $player->save();
        }
        
        // NEW: Calculate gameday rankings BEFORE marking as finished
        $gamedayRankings = $this->calculateGamedayRankings($gamedayId, $matches, $usersCache);
        
        // Store rankings in gameday
        $gameday->set('gameday_rankings', $gamedayRankings);

        // Mark gameday as finished BEFORE stats queries.
        // This is required: updatePlayerLeagueStats queries for is_finished gamedays,
        // so this gameday must be persisted as finished first to be included.
        $gameday->set('is_finished', true);
        $gameday->save();

        // Update league stats and rankings. Wrapped in try/catch so a partial failure
        // doesn't silently leave stats inconsistent without any indication.
        try {
            $presentPlayers = $gameday->get('present_players', []);

            // PRE-FETCH all gamedays and matches to prevent N+1 queries in the loop
            $allGamedays = Entry::query()
                ->where('collection', 'gamedays')
                ->where('is_finished', true)
                ->get();

            $allPlayedMatches = Entry::query()
                ->where('collection', 'matches')
                ->where('is_played', true)
                ->get();

            foreach ($presentPlayers as $playerId) {
                $this->updatePlayerLeagueStats($playerId, $allGamedays, $allPlayedMatches);
            }

            // Recalculate rankings for ALL leagues where present players participate
            $affectedLeagues = $this->getAllLeaguesForPlayers($presentPlayers, $usersCache);
            foreach ($affectedLeagues as $affectedLeagueId) {
                $this->recalculateLeagueRanks($affectedLeagueId);
            }
        } catch (\Throwable $e) {
            Log::error('Fehler bei Stats/Rankings nach Gameday-Finalisierung. Gameday ist als finished markiert, aber Stats sind möglicherweise inkonsistent.', [
                'gameday_id' => $gamedayId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // OPT: Refresh Stache exactly once after all writes are complete.
        // The UserSaved -> Stache::refresh() listener in AppServiceProvider has been
        // removed because it triggered a full cache rebuild on every player save
        // (40-60 times per finalization). Statamic updates its Stache incrementally
        // on each individual save already; this single call ensures the CP reflects
        // all changes immediately after the entire operation completes.
        Stache::refresh();
    }
    
    /**
     * Calculate gameday rankings for all players who participated.
     * 
     * Ranks players based on:
     * 1. Avg Elo gain per match (primary) — normalizes for unequal game counts
     * 2. Total Elo Gain (tiebreaker)
     * 3. Total Wins of the day (tiebreaker)
     * 4. Global Elo (final tiebreaker)
     * 
     * @param string $gamedayId
     * @param \Illuminate\Support\Collection $matches Played matches from this gameday
     * @param array $usersCache Already-loaded user objects keyed by ID
     * @return array Ranking data for each player
     */
    protected function calculateGamedayRankings($gamedayId, $matches, array $usersCache = [])
    {
        $gameday = Entry::find($gamedayId);
        $presentPlayerIds = $gameday->get('present_players', []);
        
        $rankings = [];
        
        foreach ($presentPlayerIds as $playerId) {
            // OPT: Reuse already-loaded user from $usersCache before falling back to User::find()
            $player = $usersCache[$playerId] ?? User::find($playerId);
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
            $avgEloGain = $matchesPlayed > 0 ? round($eloGain / $matchesPlayed, 2) : 0;

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
                'avg_elo_gain' => $avgEloGain,
                'global_elo' => $eloEnd  // For tiebreaker sorting
            ];
        }
        
        // Sort by ranking criteria
        usort($rankings, function($a, $b) {
            // 1. Avg Elo gain per match (descending) — normalizes for unequal game counts
            if (abs($a['avg_elo_gain'] - $b['avg_elo_gain']) > 1e-9) {
                return $b['avg_elo_gain'] <=> $a['avg_elo_gain'];
            }

            // 2. Total Elo Gain (descending)
            if (abs($a['elo_gain'] - $b['elo_gain']) > 1e-9) {
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
     * @param array $usersCache Optional pre-loaded user objects keyed by ID
     * @return array Unique league IDs
     */
    protected function getAllLeaguesForPlayers($playerIds, array $usersCache = [])
    {
        $leagueIds = collect();

        foreach ($playerIds as $playerId) {
            $player = $usersCache[$playerId] ?? User::find($playerId);
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
    public function updatePlayerLeagueStats($playerId, $allGamedays = null, $allPlayedMatches = null)
    {
        $player = User::find($playerId);
        if (!$player) return;

        // Find all finished gamedays where player was present
        if ($allGamedays === null) {
            $allGamedays = Entry::query()
                ->where('collection', 'gamedays')
                ->where('is_finished', true)
                ->get();
        }

        // OPT: Build a lookup map keyed by gameday ID once, so the inner foreach
        //      over $allMatches can resolve gameday→league with a map lookup
        //      instead of Entry::find() per match (was: up to 480 queries per call).
        $gamedayMap = $allGamedays->keyBy(fn($d) => $d->id());

        $gamedays = $allGamedays->filter(function($day) use ($playerId) {
            $presentPlayers = (array)$day->get('present_players', []);
            return in_array($playerId, $presentPlayers);
        });

        $matchesToFilter = $allPlayedMatches === null 
            ? Entry::query()
                ->where('collection', 'matches')
                ->where('is_played', true)
                ->get()
            : $allPlayedMatches;
            
        $allMatches = $matchesToFilter->filter(function($match) use ($playerId) {
            return in_array($playerId, (array)$match->get('team_a', [])) || 
                   in_array($playerId, (array)$match->get('team_b', []));
        });

        // Process performance by league (LEAGUE-SPECIFIC)
        $performanceByLeague = [];
        
        foreach ($allMatches as $match) {
            $gamedayIds = (array)$match->get('gameday', []);
            if (empty($gamedayIds)) continue;
            
            $gamedayId = reset($gamedayIds);
            // OPT: Map lookup instead of Entry::find($gamedayId)
            $gameday = $gamedayMap->get($gamedayId);
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

        // OPT: Build a set of valid league IDs from the already-loaded $allGamedays
        //      to avoid Entry::find($leagueId) once per league in the loop below.
        $validLeagueIds = $allGamedays->map(function($d) {
            $ids = (array)$d->get('league', []);
            return !empty($ids) ? reset($ids) : null;
        })->filter()->unique()->flip()->all(); // flip() for O(1) isset() checks

        $gamedaysByLeague = $gamedays->groupBy(function($day) {
            $leagueIds = (array)$day->get('league', []);
            return !empty($leagueIds) ? reset($leagueIds) : null;
        });

        foreach ($gamedaysByLeague as $leagueId => $days) {
             // OPT: isset() on pre-built map instead of Entry::find($leagueId)
             if (!$leagueId || !isset($validLeagueIds[$leagueId])) continue;
             
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
        $player = User::find($playerId);
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
     * @param array $usersCache In-memory user cache (passed by reference)
     * @param string|null $leagueId Pre-resolved league ID (avoids re-fetching gameday)
     * @param string|null $matchDate Pre-resolved ISO date string (avoids re-fetching gameday)
     */
    protected function processMatchElo($match, $kFactor, &$usersCache = [], $leagueId = null, $matchDate = null)
    {
        $teamAIds = $match->get('team_a');
        $teamBIds = $match->get('team_b');
        
        $teamAPlayers = collect($teamAIds)->map(function($id) use (&$usersCache) {
            if (!isset($usersCache[$id])) {
                $user = User::find($id);
                if ($user) $usersCache[$id] = $user;
            }
            return $usersCache[$id] ?? null;
        })->filter();
        
        $teamBPlayers = collect($teamBIds)->map(function($id) use (&$usersCache) {
            if (!isset($usersCache[$id])) {
                $user = User::find($id);
                if ($user) $usersCache[$id] = $user;
            }
            return $usersCache[$id] ?? null;
        })->filter();
        
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
        
        // Actual performance based on score ratio (NOT binary win/loss).
        // This is an intentional design choice: the margin of victory affects Elo.
        //   - 11-0 win → actualA ≈ 1.0  → large positive delta
        //   - 11-9 win → actualA ≈ 0.55 → small positive delta
        //   - 9-11 loss → actualA ≈ 0.45 → small negative delta
        // This rewards dominant play and softens the penalty for close losses,
        // better reflecting individual skill in a doubles format where partners rotate.
        $pointsTotal = $scoreA + $scoreB;
        if ($pointsTotal == 0) return; // Prevent division by zero

        $actualA = $scoreA / $pointsTotal;

        // Elo delta: K × (actual - expected). No win floor/ceiling applied.
        $delta = $kFactor * ($actualA - $expectedA);
        
        // OPT: $leagueId and $matchDate are now passed in from finalizeGameday(),
        //      eliminating one Entry::find(gamedayId) call per match (was: 24 queries).
        //      Fall back to the original lookup only when called outside finalizeGameday().
        if ($leagueId === null || $matchDate === null) {
            $gamedayId = $match->get('gameday')[0] ?? null;
            $gameday = $gamedayId ? Entry::find($gamedayId) : null;
            if ($leagueId === null) {
                $leagueId = $gameday ? ($gameday->get('league')[0] ?? null) : null;
            }
            if ($matchDate === null) {
                $matchDate = $gameday && $gameday->date()
                    ? $gameday->date()->toIso8601String()
                    : now()->toIso8601String();
            }
        }

        
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
            
            // intentionally omitting $player->save() to allow batch saving
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
            
            // intentionally omitting $player->save() to allow batch saving
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

        // Collect only player IDs that participated in this league's gamedays,
        // instead of loading every user in the system.
        $leaguePlayerIds = Entry::query()
            ->where('collection', 'gamedays')
            ->where('league', $leagueId)
            ->where('is_finished', true)
            ->get()
            ->flatMap(fn($day) => (array)$day->get('present_players', []))
            ->unique()
            ->values();

        $leagueUsers = $leaguePlayerIds->map(fn($id) => User::find($id))
            ->filter()
            ->keyBy(fn($u) => $u->id());

        // Build ranking data for league players only
        $rankingData = $leagueUsers->map(function($player) use ($leagueId, $minGameDays) {
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
            if (abs($a['win_percentage'] - $b['win_percentage']) > 1e-9) {
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
            $player = $leagueUsers->get($item['id']);
            if (!$player) continue;

            $stats = $player->get('league_stats', []);
            $statsChanged = false;
            
            foreach ($stats as &$row) {
                $rowLeagues = (array)($row['league'] ?? []);
                $rowLeagueId = reset($rowLeagues);
                if ($rowLeagueId === $leagueId) {
                    // Assign rank number if qualified
                    $newRank = $item['is_qualified'] ? ($index + 1) : null;
                    if (($row['rank'] ?? null) !== $newRank) {
                        $row['rank'] = $newRank;
                        $statsChanged = true;
                    }
                }
            }
            
            if ($statsChanged) {
                $player->set('league_stats', $stats);
                $player->save();
            }
        }
    }
}