<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class DeleteLeagues extends Command
{
    protected $signature = 'delete:leagues';
    protected $description = 'Delete all leagues from the collection';

    public function handle()
    {
        $count = Entry::query()->where('collection', 'leagues')->count();
        
        if ($count === 0) {
            $this->info('No leagues to delete.');
            return 0;
        }
        
        Entry::query()->where('collection', 'leagues')->get()->each->delete();
        
        $this->info("✅ Deleted {$count} leagues.");
        return 0;
    }
}
