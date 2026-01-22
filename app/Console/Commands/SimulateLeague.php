<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use App\Services\LeagueService;
use Illuminate\Support\Facades\Artisan;

class SimulateLeague extends Command
{
    protected $signature = 'simulate:league {league}';
    protected $description = 'Simulate all gamedays for a league (generate plan, fill scores, finalize)';

    public function handle(LeagueService $leagueService)
    {
        $leagueSlug = $this->argument('league');

        $this->info("🚀 Starting League Simulation for: {$leagueSlug}");

        // Find the league
        $league = Entry::query()
            ->where('collection', 'leagues')
            ->where('slug', $leagueSlug)
            ->first();

        if (!$league) {
            $this->error("❌ League '{$leagueSlug}' not found!");
            return 1;
        }

        // Find all gamedays for this league that are NOT finished
        $gamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->get()
            ->filter(function($g) use ($league) {
                $l = $g->get('league');
                if (is_array($l)) {
                    return in_array($league->id(), $l) && !$g->get('is_finished');
                }
                return $l === $league->id() && !$g->get('is_finished');
            })
            ->sortBy('date')
            ->values();

        if ($gamedays->isEmpty()) {
            $this->error("❌ No unfinished gamedays found for this league!");
            $this->info("💡 Create gamedays first: php artisan create:gamedays {$leagueSlug} --c=16");
            return 1;
        }

        $this->info("🔄 Processing {$gamedays->count()} gamedays (Plan → Score → Finish)...");
        $bar = $this->output->createProgressBar($gamedays->count());
        $bar->start();

        foreach ($gamedays as $index => $gameday) {
            try {
                // a. Generate Plan (if not already generated)
                if (!$gameday->get('generated_plan')) {
                    $leagueService->generateGamedayPlan($gameday->id());
                }

                // b. Fill Scores for this specific league
                Artisan::call("fill:scores {$leagueSlug}");

                // c. Finish Gameday (Calculates Elo)
                $leagueService->finalizeGameday($gameday->id());

                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\n❌ Error in gameday " . ($index + 1) . ": " . $e->getMessage());
                break;
            }
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Simulation complete! Check the Leaderboard at: /leagues/{$leagueSlug}");
        
        return 0;
    }
}
