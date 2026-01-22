<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class DeleteMatches extends Command
{
    protected $signature = 'delete:matches';
    protected $description = 'Delete all matches from the collection';

    public function handle()
    {
        $count = Entry::query()->where('collection', 'matches')->count();
        
        if ($count === 0) {
            $this->info('No matches to delete.');
            return 0;
        }
        
        Entry::query()->where('collection', 'matches')->get()->each->delete();
        
        $this->info("✅ Deleted {$count} matches.");
        return 0;
    }
}
