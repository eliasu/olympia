<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class DeletePlayers extends Command
{
    protected $signature = 'delete:players';
    protected $description = 'Delete all players from the collection';

    public function handle()
    {
        $count = Entry::query()->where('collection', 'players')->count();
        
        if ($count === 0) {
            $this->info('No players to delete.');
            return 0;
        }
        
        Entry::query()->where('collection', 'players')->get()->each->delete();
        
        $this->info("✅ Deleted {$count} players.");
        return 0;
    }
}
