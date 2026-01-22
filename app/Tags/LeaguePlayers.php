<?php

namespace App\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;

class LeaguePlayers extends Tags
{
    /**
     * Get all players who participated in at least one gameday of this league.
     * 
     * Usage: {{ league_players :league="id" sort="rank" }}
     * 
     * @return array
     */
    public function index()
    {
        $leagueId = $this->params->get('league');
        $sort = $this->params->get('sort');
        
        if (!$leagueId) {
            return [];
        }
        
        // Find all gamedays for this league
        $gamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->get()
            ->filter(function($gameday) use ($leagueId) {
                $gamedayLeagues = (array)$gameday->get('league', []);
                return in_array($leagueId, $gamedayLeagues);
            });
        
        // Collect all unique player IDs from present_players
        $playerIds = collect();
        foreach ($gamedays as $gameday) {
            $presentPlayers = (array)$gameday->get('present_players', []);
            $playerIds = $playerIds->merge($presentPlayers);
        }
        
        // Get unique player IDs
        $uniquePlayerIds = $playerIds->unique()->values()->all();
        
        // Fetch player entries
        $players = collect($uniquePlayerIds)
            ->map(fn($id) => Entry::find($id))
            ->filter()
            ->values();
        
        // Apply sorting if requested
        if ($sort === 'rank') {
            $players = $players->sort(function($a, $b) use ($leagueId) {
                $rankA = $this->getLeagueRank($a, $leagueId);
                $rankB = $this->getLeagueRank($b, $leagueId);
                
                // Players without rank go to the end
                if ($rankA === null && $rankB === null) return 0;
                if ($rankA === null) return 1;
                if ($rankB === null) return -1;
                
                return $rankA <=> $rankB;
            })->values();
        }
        
        return $players->all();
    }
    
    /**
     * Get the rank for a player in a specific league
     * 
     * @param \Statamic\Entries\Entry $player
     * @param string $leagueId
     * @return int|null
     */
    protected function getLeagueRank($player, $leagueId)
    {
        $leagueStats = $player->get('league_stats', []);
        
        foreach ($leagueStats as $stat) {
            $statLeague = $stat['league'] ?? [];
            if (is_array($statLeague) && in_array($leagueId, $statLeague)) {
                return $stat['rank'] ?? null;
            }
        }
        
        return null;
    }
}
