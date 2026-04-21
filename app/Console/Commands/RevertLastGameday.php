<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use App\Services\LeagueService;

class RevertLastGameday extends Command
{
    protected $signature = 'gameday:revert {league? : League slug (optional, prompts if omitted)}';
    protected $description = 'Revert the last finished gameday so scores can be corrected and re-submitted';

    public function handle(LeagueService $leagueService)
    {
        // Resolve league
        $leagueSlug = $this->argument('league');

        if ($leagueSlug) {
            $league = Entry::query()
                ->where('collection', 'leagues')
                ->where('slug', $leagueSlug)
                ->first();

            if (!$league) {
                $this->error("League '{$leagueSlug}' not found.");
                return 1;
            }
        } else {
            $leagues = Entry::query()->where('collection', 'leagues')->get();

            if ($leagues->isEmpty()) {
                $this->error('No leagues found.');
                return 1;
            }

            $choice = $this->choice(
                'Which league?',
                $leagues->map(fn($l) => $l->get('title') . ' [' . $l->slug() . ']')->all()
            );

            $slug = trim(explode('[', $choice)[1] ?? '');
            $slug = rtrim($slug, ']');
            $league = $leagues->first(fn($l) => $l->slug() === $slug);

            if (!$league) {
                $this->error('Could not resolve league selection.');
                return 1;
            }
        }

        $leagueId = $league->id();

        // Find last finished gameday for this league
        $gameday = Entry::query()
            ->where('collection', 'gamedays')
            ->where('league', $leagueId)
            ->where('is_finished', true)
            ->get()
            ->sortByDesc(fn($d) => $d->date())
            ->first();

        if (!$gameday) {
            $this->warn('No finished gamedays found for ' . $league->get('title') . '.');
            return 0;
        }

        $this->info('Found gameday: ' . $gameday->get('title') . ' (' . ($gameday->date()?->toDateString() ?? 'no date') . ')');

        if (!$this->confirm('Revert this gameday? Player Elo, rankings, and stats will be rolled back.')) {
            $this->info('Aborted.');
            return 0;
        }

        $gamedayId = $gameday->id();

        // Load all played matches for this gameday, sorted by match number (same order as finalize)
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->where('gameday', $gamedayId)
            ->where('is_played', true)
            ->get()
            ->sortBy(fn($m) => (int) last(explode('-', $m->slug())));

        $this->info('Processing ' . $matches->count() . ' played matches...');

        // Collect all affected player IDs
        $affectedPlayerIds = collect();
        foreach ($matches as $match) {
            $affectedPlayerIds = $affectedPlayerIds
                ->merge((array) $match->get('team_a', []))
                ->merge((array) $match->get('team_b', []));
        }
        $affectedPlayerIds = $affectedPlayerIds->unique()->filter()->values();

        // Load players into cache
        $usersCache = [];
        foreach ($affectedPlayerIds as $playerId) {
            $user = User::find($playerId);
            if ($user) {
                $usersCache[$playerId] = $user;
            }
        }

        // Revert Elo for each match in REVERSE order so cascading Elo is undone correctly
        foreach ($matches->reverse() as $match) {
            $this->revertMatchElo($match, $usersCache);
        }

        // Batch-save all affected players
        foreach ($usersCache as $player) {
            $player->save();
        }

        // Clear Elo data from match entries
        foreach ($matches as $match) {
            $match->set('elo_delta', null);
            $match->set('team_a_elo_before', null);
            $match->set('team_a_elo_after', null);
            $match->set('team_b_elo_before', null);
            $match->set('team_b_elo_after', null);
            $match->save();
        }

        // Revert gameday to "plan generated, not finished"
        $gameday->set('is_finished', false);
        $gameday->set('gameday_rankings', []);
        $gameday->save();

        $this->info('Gameday marked as not finished. Recalculating league stats...');

        // Recalculate league stats for all affected players (excluding this gameday now)
        $allGamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->where('is_finished', true)
            ->get();

        $allPlayedMatches = Entry::query()
            ->where('collection', 'matches')
            ->where('is_played', true)
            ->get();

        foreach ($affectedPlayerIds as $playerId) {
            $leagueService->updatePlayerLeagueStats($playerId, $allGamedays, $allPlayedMatches);
        }

        // Recalculate league rankings
        $leagueService->recalculateLeagueRanks($leagueId);

        Stache::refresh();

        $this->info('');
        $this->info('Done! Gameday reverted successfully.');
        $this->info('You can now correct the scores and finish the gameday again.');

        return 0;
    }

    /**
     * Revert Elo changes for a single match using the saved elo_before snapshots.
     * Removes the corresponding elo_history entries from each player.
     */
    protected function revertMatchElo($match, array &$usersCache)
    {
        $matchId = $match->id();
        $teamAIds = (array) $match->get('team_a', []);
        $teamBIds = (array) $match->get('team_b', []);

        $teamAEloBefore = $match->get('team_a_elo_before');
        $teamBEloBefore = $match->get('team_b_elo_before');

        if (!$teamAEloBefore || !$teamBEloBefore) {
            $this->warn("  Match {$matchId}: no elo_before snapshot found, skipping Elo revert for this match.");
            return;
        }

        $scoreA = (int) $match->get('score_a');
        $scoreB = (int) $match->get('score_b');

        foreach ($teamAIds as $index => $playerId) {
            $player = $usersCache[$playerId] ?? null;
            if (!$player) continue;

            $eloBefore = (float) ($teamAEloBefore[$index] ?? $player->get('global_elo', 1500));

            $player->set('global_elo', $eloBefore);
            $player->set('total_games', max(0, (int) $player->get('total_games', 0) - 1));

            if ($scoreA > $scoreB) {
                $player->set('wins', max(0, (int) $player->get('wins', 0) - 1));
            } else {
                $player->set('losses', max(0, (int) $player->get('losses', 0) - 1));
            }

            $this->removeEloHistoryEntry($player, $matchId);
        }

        foreach ($teamBIds as $index => $playerId) {
            $player = $usersCache[$playerId] ?? null;
            if (!$player) continue;

            $eloBefore = (float) ($teamBEloBefore[$index] ?? $player->get('global_elo', 1500));

            $player->set('global_elo', $eloBefore);
            $player->set('total_games', max(0, (int) $player->get('total_games', 0) - 1));

            if ($scoreB > $scoreA) {
                $player->set('wins', max(0, (int) $player->get('wins', 0) - 1));
            } else {
                $player->set('losses', max(0, (int) $player->get('losses', 0) - 1));
            }

            $this->removeEloHistoryEntry($player, $matchId);
        }
    }

    /**
     * Remove all elo_history entries that belong to the given match.
     */
    protected function removeEloHistoryEntry($player, $matchId)
    {
        $history = $player->get('elo_history', []);
        $filtered = array_values(array_filter($history, fn($h) => ($h['match'] ?? null) !== $matchId));
        $player->set('elo_history', $filtered);
    }
}
