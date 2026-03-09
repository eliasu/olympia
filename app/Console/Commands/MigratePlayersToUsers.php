<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Illuminate\Support\Str;

class MigratePlayersToUsers extends Command
{
    protected $signature = 'migrate:players-to-users 
        {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Migrate player collection entries to Statamic user YAML files';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $players = Entry::query()
            ->where('collection', 'players')
            ->get();

        if ($players->isEmpty()) {
            $this->info('No players found to migrate.');
            return;
        }

        $this->info("Found {$players->count()} players to migrate.");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made.');
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($players as $player) {
            $name = $player->get('title', 'Unknown');
            $slug = $player->slug();

            // Generate a fake email from the player slug
            $email = Str::slug($name, '.') . '@olympia-player.local';

            // Check if user with this email already exists
            if (User::findByEmail($email)) {
                $this->warn("SKIP: User with email {$email} already exists ({$name})");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("WOULD MIGRATE: {$name} -> {$email} (slug: {$slug})");
                $migrated++;
                continue;
            }

            // Create the user with all player data
            $user = User::make()
                ->email($email)
                ->data([
                    'name' => $name,
                    'slug' => $slug,

                    'global_elo' => $player->get('global_elo', 1500),
                    'player_status' => $player->get('player_status', 'active'),
                    'total_games' => $player->get('total_games', 0),
                    'wins' => $player->get('wins', 0),
                    'losses' => $player->get('losses', 0),
                    'league_stats' => $player->get('league_stats', []),
                    'matches' => $player->get('matches', []),
                    'elo_history' => $player->get('elo_history', []),
                    'roles' => ['player'],
                    'groups' => ['players'],
                ]);

            // Set a default password
            $user->password('Olympia2026!');
            $user->save();

            // Now we need to update all references from the old player ID to the new user ID
            $oldId = $player->id();
            $newId = $user->id();

            $this->updateReferences($oldId, $newId);

            $this->info("✅ Migrated: {$name} -> {$email} (old: {$oldId}, new: {$newId})");
            $migrated++;
        }

        $this->newLine();
        $this->info("Migration complete: {$migrated} migrated, {$skipped} skipped.");

        if (!$dryRun) {
            $this->newLine();
            $this->warn('IMPORTANT: Run the following commands after migration:');
            $this->line('  php please stache:refresh');
            $this->line('  php please relate:fill');
        }
    }

    /**
     * Update all references from old player entry ID to new user ID.
     * Updates: matches (team_a, team_b), gamedays (present_players, gameday_rankings)
     */
    protected function updateReferences($oldId, $newId)
    {
        // Update matches: team_a and team_b
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->get();

        foreach ($matches as $match) {
            $changed = false;

            $teamA = (array)$match->get('team_a', []);
            $teamB = (array)$match->get('team_b', []);

            if (in_array($oldId, $teamA)) {
                $teamA = array_map(fn($id) => $id === $oldId ? $newId : $id, $teamA);
                $match->set('team_a', array_values($teamA));
                $changed = true;
            }

            if (in_array($oldId, $teamB)) {
                $teamB = array_map(fn($id) => $id === $oldId ? $newId : $id, $teamB);
                $match->set('team_b', array_values($teamB));
                $changed = true;
            }

            if ($changed) {
                $match->save();
            }
        }

        // Update gamedays: present_players and gameday_rankings
        $gamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->get();

        foreach ($gamedays as $gameday) {
            $changed = false;

            // Update present_players
            $presentPlayers = (array)$gameday->get('present_players', []);
            if (in_array($oldId, $presentPlayers)) {
                $presentPlayers = array_map(fn($id) => $id === $oldId ? $newId : $id, $presentPlayers);
                $gameday->set('present_players', array_values($presentPlayers));
                $changed = true;
            }

            // Update gameday_rankings
            $rankings = $gameday->get('gameday_rankings', []);
            if (!empty($rankings)) {
                foreach ($rankings as &$ranking) {
                    $playerRef = (array)($ranking['player'] ?? []);
                    if (in_array($oldId, $playerRef)) {
                        $ranking['player'] = array_map(fn($id) => $id === $oldId ? $newId : $id, $playerRef);
                        $changed = true;
                    }
                }
                if ($changed) {
                    $gameday->set('gameday_rankings', $rankings);
                }
            }

            if ($changed) {
                $gameday->save();
            }
        }
    }
}
