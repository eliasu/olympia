<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\User;
use Statamic\Facades\Entry;
use Illuminate\Support\Facades\Log;

class ResetPlayers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-players';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all existing players to 1500 Elo, 0 games and no stats. Clears all match history.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting all player statistics and clearing match history...');

        // 1. Reset all users
        $users = User::all();
        $userCount = 0;
        foreach ($users as $user) {
            $this->line("Resetting user: {$user->name()} ({$user->email()})");
            
            $user->set('global_elo', 1500);
            $user->set('total_games', 0);
            $user->set('wins', 0);
            $user->set('losses', 0);
            $user->set('elo_history', []);
            $user->set('league_stats', []);
            
            $user->save();
            $userCount++;
        }
        $this->info("Successfully reset {$userCount} users.");

        // 2. Clear all matches
        $matches = Entry::query()->where('collection', 'matches')->get();
        $matchCount = 0;
        foreach ($matches as $match) {
            $match->delete();
            $matchCount++;
        }
        $this->info("Successfully deleted {$matchCount} matches.");

        // 3. Clear all gamedays
        $gamedays = Entry::query()->where('collection', 'gamedays')->get();
        $gamedayCount = 0;
        foreach ($gamedays as $gameday) {
            $gameday->delete();
            $gamedayCount++;
        }
        $this->info("Successfully deleted {$gamedayCount} gamedays.");

        $this->info('All players reset and match history cleared.');
    }
}
