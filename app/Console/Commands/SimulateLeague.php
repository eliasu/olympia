<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use App\Services\LeagueService;
use Illuminate\Support\Facades\Artisan;

class SimulateLeague extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulate:league {league} {--players=50} {--gamedays=16} {--reset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a full league season from scratch (Create players, gamedays, and process all matches)';

    /**
     * Execute the console command.
     */
    public function handle(LeagueService $leagueService)
    {
        $leagueSlug = $this->argument('league');
        $playerCount = $this->option('players');
        $gamedayCount = $this->option('gamedays');

        $this->info("🚀 Starting League Simulation for: {$leagueSlug}");

        // 0. Cleanup
        $this->warn("🧹 Step 0: Deleting existing Players, Gamedays and Matches...");
        
        $matchCount = Entry::query()->where('collection', 'matches')->count();
        $gamedayCountToDelete = Entry::query()->where('collection', 'gamedays')->count();
        $playerCountToDelete = Entry::query()->where('collection', 'players')->count();

        Entry::query()->where('collection', 'matches')->get()->each->delete();
        Entry::query()->where('collection', 'gamedays')->get()->each->delete();
        Entry::query()->where('collection', 'players')->get()->each->delete();

        $this->comment("Deleted {$matchCount} matches, {$gamedayCountToDelete} gamedays, and {$playerCountToDelete} players.");

        // 1. Create Players
        $this->info("👥 Step 1: Creating {$playerCount} players...");
        Artisan::call("create:players {$playerCount} 1500");
        $this->line(Artisan::output());

        // 2. Create Gamedays
        $this->info("📅 Step 2: Creating {$gamedayCount} gamedays...");
        Artisan::call("create:gamedays {$leagueSlug} {$gamedayCount}");
        $this->line(Artisan::output());

        // Find the league and its gamedays
        $league = Entry::query()
            ->where('collection', 'leagues')
            ->where('slug', $leagueSlug)
            ->first();

        if (!$league) {
            $this->error("❌ League '{$leagueSlug}' not found!");
            return 1;
        }

        $gamedays = Entry::query()
            ->where('collection', 'gamedays')
            ->get()
            ->filter(function($g) use ($league) {
                $l = $g->get('league');
                 if (is_array($l)) return in_array($league->id(), $l);
                 return $l === $league->id();
            })
            ->sortBy('date')
            ->values();

        if ($gamedays->isEmpty()) {
            $this->error("❌ No gamedays found to simulate.");
            return 1;
        }

        $this->info("🔄 Step 3: Processing {$gamedays->count()} gamedays (Plan -> Score -> Finish)...");
        $bar = $this->output->createProgressBar($gamedays->count());
        $bar->start();

        foreach ($gamedays as $index => $gameday) {
            try {
                // a. Generate Plan
                $leagueService->generateGamedayPlan($gameday->id());

                // b. Fill Scores for this specific league
                // (fill:scores looks for unplayed matches in the league)
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
