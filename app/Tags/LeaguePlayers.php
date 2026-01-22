<?php

namespace App\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;

class LeaguePlayers extends Tags
{
    /**
     * Get all players who participated in at least one gameday of this league.
     * 
     * Usage: {{ league_players :league="id" }}
     * 
     * @return array
     */
    public function index()
    {
        $leagueId = $this->params->get('league');
        
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
        
        return $players;
    }
}
