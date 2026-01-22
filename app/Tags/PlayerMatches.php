<?php

namespace App\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;
use Statamic\Tags\Concerns\OutputsItems;
use Illuminate\Pagination\LengthAwarePaginator;

class PlayerMatches extends Tags
{
    use OutputsItems;

    /**
     * The {{ player_matches }} tag.
     *
     * @return string|array
     */
    public function index()
    {
        $playerId = $this->params->get('player');
        $sortDir = $this->params->get('sort', 'desc');
        $limit = $this->params->int('limit');
        $page = $this->params->int('page', request('page', 1));
        $perPage = $this->params->int('paginate');

        if (!$playerId) {
            return [];
        }

        // 1. Fetch matches where player is in team_a or team_b
        // We use query builder for efficiency if possible, but 'contains' on array field in Stache 
        // can be tricky if we want exact ID match in array. 
        // Safer to filter collection for correctness with complex array headers.
        // However, for performance on large sets, Entry::query() is better.
        
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->get()
            ->filter(function ($match) use ($playerId) {
                $teamA = (array) $match->get('team_a', []);
                $teamB = (array) $match->get('team_b', []);
                return in_array($playerId, $teamA) || in_array($playerId, $teamB);
            });

        // 2. Sort by Gameday Date
        $matches = $matches->sort(function ($a, $b) use ($sortDir) {
            $dateA = $this->getGamedayDate($a);
            $dateB = $this->getGamedayDate($b);

            if ($dateA == $dateB) {
                return 0;
            }

            if ($sortDir === 'asc') {
                return $dateA < $dateB ? -1 : 1;
            } else {
                return $dateA > $dateB ? -1 : 1;
            }
        });

        // 3. Limit
        $actualLimit = $limit ?: 20;
        $matches = $matches->take($actualLimit);

        return $matches->values();
    }

    /**
     * Helper to get the date of the gameday related to a match.
     */
    protected function getGamedayDate($match)
    {
        $gamedayId = $match->get('gameday');
        
        // Handle if gameday is array or string
        if (is_array($gamedayId)) {
            $gamedayId = $gamedayId[0] ?? null;
        }

        if (!$gamedayId) return 0;

        $gameday = Entry::find($gamedayId);
        
        if (!$gameday) return 0;

        // Gameday date is system date (e.g. from filename) map to timestamp
        return $gameday->date() ? $gameday->date()->getTimestamp() : 0;
    }
}
