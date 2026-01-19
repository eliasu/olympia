<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Carbon\Carbon;

class CreateGamedays extends Command
{
    protected $signature = 'create:gamedays {league} {count=5} {--start-date=}';
    protected $description = 'Create gamedays with random players for a league';

    public function handle()
    {
        $leagueSlug = $this->argument('league');
        $count = (int) $this->argument('count');
        $startDate = $this->option('start-date') 
            ? Carbon::parse($this->option('start-date'))
            : Carbon::now()->next(Carbon::MONDAY);
        
        // Find league
        $league = Entry::query()
            ->where('collection', 'leagues')
            ->where('slug', $leagueSlug)
            ->first();
            
        if (!$league) {
            $this->error("❌ League '{$leagueSlug}' not found!");
            return 1;
        }
        
        // Get all active players
        $allPlayers = Entry::query()
            ->where('collection', 'players')
            ->where('player_status', 'active')
            ->get();
            
        if ($allPlayers->count() < 15) {
            $this->error("❌ Not enough players! Need at least 15, found {$allPlayers->count()}");
            return 1;
        }
        
        $this->info("Creating {$count} gamedays for league: {$league->get('title')}");
        $this->info("Starting from: {$startDate->format('d.m.Y')}");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        for ($i = 0; $i < $count; $i++) {
            $date = $startDate->copy()->addWeeks($i);
            $title = "Gameday " . ($i + 1) . " - " . $date->format('d.m.Y');
            $slug = \Illuminate\Support\Str::slug($title);
            
            // Select 15-25 random players
            $playerCount = rand(15, 25);
            $selectedPlayers = $allPlayers->random(min($playerCount, $allPlayers->count()))
                ->pluck('id')
                ->all();
            
            $gameday = Entry::make()
                ->collection('gamedays')
                ->slug($slug)
                ->data([
                    'title' => $title,
                    'date' => $date->toDateString(),
                    'courts_count' => 4,
                    'games_per_court' => 4,
                    'generated_plan' => false,
                    'is_finished' => false,
                    'present_players' => $selectedPlayers
                ]);
            
            $gameday->set('league', [$league->id()]);
            $gameday->save();
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("✅ Created {$count} gamedays successfully!");
        
        return 0;
    }
}
