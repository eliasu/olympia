<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use App\Services\LeagueService;

class RecalculateLeagueRanks extends Command
{
    protected $signature = 'recalculate:league-ranks {league?}';
    protected $description = 'Recalculate league rankings for one or all leagues';

    public function handle(LeagueService $leagueService)
    {
        $leagueSlug = $this->argument('league');
        
        if ($leagueSlug) {
            // Recalculate for specific league
            $league = Entry::query()
                ->where('collection', 'leagues')
                ->where('slug', $leagueSlug)
                ->first();
                
            if (!$league) {
                $this->error("❌ League '{$leagueSlug}' not found!");
                return 1;
            }
            
            $this->info("🔄 Recalculating ranks for: {$league->get('title')}");
            $leagueService->recalculateLeagueRanks($league->id());
            $this->info("✅ Done!");
            
        } else {
            // Recalculate for ALL leagues
            $leagues = Entry::query()
                ->where('collection', 'leagues')
                ->get();
                
            if ($leagues->isEmpty()) {
                $this->warn("⚠️  No leagues found!");
                return 0;
            }
            
            $this->info("🔄 Recalculating ranks for {$leagues->count()} leagues...");
            $bar = $this->output->createProgressBar($leagues->count());
            $bar->start();
            
            foreach ($leagues as $league) {
                $leagueService->recalculateLeagueRanks($league->id());
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            $this->info("✅ All league ranks recalculated!");
        }
        
        return 0;
    }
}
