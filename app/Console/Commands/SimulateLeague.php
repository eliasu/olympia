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
    protected $signature = 'simulate:league {league} 
        {--beginners=0 : Number of beginner players (Elo ~1200)} 
        {--intermediates=0 : Number of intermediate players (Elo ~1500)} 
        {--advanced=0 : Number of advanced players (Elo ~1600)} 
        {--pros=0 : Number of pro players (Elo ~1750)} 
        {--gamedays=16 : Number of gamedays to simulate}
        {--no-cleanup : Skip deletion of existing players, gamedays, and matches}
        {--no-active-gameday : Skip creation of an extra active gameday at the end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a full league season with skill-based players (Beginner/Intermediate/Advanced/Pro)';

    /**
     * Skill group definitions with base Elo and variance
     */
    const SKILL_GROUPS = [
        'Beginner' => ['elo' => 1200, 'variance' => 50],
        'Intermediate' => ['elo' => 1500, 'variance' => 50],
        'Advanced' => ['elo' => 1600, 'variance' => 50],
        'Pro' => ['elo' => 1750, 'variance' => 50],
    ];

    /**
     * Execute the console command.
     */
    public function handle(LeagueService $leagueService)
    {
        $leagueSlug = $this->argument('league');
        $gamedayCount = $this->option('gamedays');

        $this->info("🚀 Starting League Simulation for: {$leagueSlug}");

        // 0. Cleanup (optional)
        if (!$this->option('no-cleanup')) {
            $this->warn("🧹 Step 0: Deleting existing Players, Gamedays and Matches...");
            
            $matchCount = Entry::query()->where('collection', 'matches')->count();
            $gamedayCountToDelete = Entry::query()->where('collection', 'gamedays')->count();
            $playerCountToDelete = Entry::query()->where('collection', 'players')->count();

            Entry::query()->where('collection', 'matches')->get()->each->delete();
            Entry::query()->where('collection', 'gamedays')->get()->each->delete();
            Entry::query()->where('collection', 'players')->get()->each->delete();

            $this->comment("Deleted {$matchCount} matches, {$gamedayCountToDelete} gamedays, and {$playerCountToDelete} players.");
        } else {
            $this->info("⏭️  Skipping cleanup (--no-cleanup flag set)");
        }

        // 1. Create Skill-Based Players
        $playerCounts = [
            'Beginner' => $this->option('beginners'),
            'Intermediate' => $this->option('intermediates'),
            'Advanced' => $this->option('advanced'),
            'Pro' => $this->option('pros'),
        ];
        
        $totalPlayers = array_sum($playerCounts);
        
        if ($totalPlayers === 0) {
            $this->error("❌ No players specified! Use --beginners, --intermediates, --advanced, or --pros");
            $this->info("Example: php artisan simulate:league btv-new --beginners=10 --intermediates=20 --advanced=15 --pros=5");
            return 1;
        }
        
        $this->info("👥 Step 1: Creating {$totalPlayers} skill-based players...");
        $this->createSkillBasedPlayers($playerCounts);
        $this->comment("Created players: " . implode(', ', array_map(fn($k, $v) => "{$v} {$k}", array_keys($playerCounts), $playerCounts)));

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

        // 3. Link Players to League
        $allPlayers = Entry::query()->where('collection', 'players')->get();
        $league->set('players', $allPlayers->pluck('id')->all());
        $league->save();
        $this->info("🔗 Linked " . $allPlayers->count() . " players to the league.");

        // Find the gamedays
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
        
        // 4. Create Active Gameday (optional)
        if (!$this->option('no-active-gameday')) {
            $this->info("📋 Step 4: Creating active gameday (plan generated, not finished)...");
            
            // Create one more gameday
            $lastGameday = $gamedays->last();
            $nextDate = \Carbon\Carbon::parse($lastGameday->get('date'))->addWeek();
            
            $activeGameday = Entry::make()
                ->collection('gamedays')
                ->slug('gameday-' . ($gamedayCount + 1) . '-' . $nextDate->format('Y-m-d'))
                ->data([
                    'title' => 'Gameday ' . ($gamedayCount + 1),
                    'date' => $nextDate->format('Y-m-d'),
                    'league' => [$league->id()],
                    'generated_plan' => false,
                    'is_finished' => false,
                ]);
            $activeGameday->save();
            
            // Generate plan for active gameday
            $leagueService->generateGamedayPlan($activeGameday->id());
            
            $this->comment("Created active gameday: {$activeGameday->get('title')} (plan generated, ready to play)");
        }
        
        $this->info("✅ Simulation complete! Check the Leaderboard at: /leagues/{$leagueSlug}");
        
        return 0;
    }

    /**
     * Create skill-based players with appropriate Elo ratings and surnames
     *
     * @param array $playerCounts Array with skill levels as keys and counts as values
     */
    protected function createSkillBasedPlayers(array $playerCounts)
    {
        $faker = \Faker\Factory::create();
        $createdCount = 0;

        foreach ($playerCounts as $skillLevel => $count) {
            if ($count <= 0) continue;

            $skillConfig = self::SKILL_GROUPS[$skillLevel];
            $baseElo = $skillConfig['elo'];
            $variance = $skillConfig['variance'];

            for ($i = 0; $i < $count; $i++) {
                // Generate Elo with variance
                $elo = $baseElo + rand(-$variance, $variance);
                
                // Generate player name
                $playerName = $faker->firstName() . ' ' . $skillLevel;

                // Create player entry
                $player = Entry::make()
                    ->collection('players')
                    ->slug(\Illuminate\Support\Str::slug($faker->firstName() . '-' . $skillLevel . '-' . $i))
                    ->data([
                        'title' => $playerName,
                        'global_elo' => $elo,
                        'total_games' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'player_status' => 'active',
                        'avatar_url' => "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($playerName)
                    ]);

                $player->save();
                $createdCount++;
            }
        }

        return $createdCount;
    }
}
