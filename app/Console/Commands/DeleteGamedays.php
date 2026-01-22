<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class DeleteGamedays extends Command
{
    protected $signature = 'delete:gamedays';
    protected $description = 'Delete all gamedays from the collection';

    public function handle()
    {
        $count = Entry::query()->where('collection', 'gamedays')->count();
        
        if ($count === 0) {
            $this->info('No gamedays to delete.');
            return 0;
        }
        
        Entry::query()->where('collection', 'gamedays')->get()->each->delete();
        
        $this->info("✅ Deleted {$count} gamedays.");
        return 0;
    }
}
