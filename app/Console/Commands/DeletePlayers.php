<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\User;

class DeletePlayers extends Command
{
    protected $signature = 'delete:players';
    protected $description = 'Delete all players from the collection';

    public function handle()
    {
        $players = User::all()->filter(function ($user) {
            return !$user->isSuper() && !$user->hasRole('league_manager');
        });

        $count = $players->count();
        
        if ($count === 0) {
            $this->info('No players to delete.');
            return 0;
        }
        
        $players->each->delete();
        
        $this->info("✅ Deleted {$count} players.");
        return 0;
    }
}
