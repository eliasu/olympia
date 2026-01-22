<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Carbon\Carbon;

class CreateGamedays extends Command
{
    protected $signature = 'create:gamedays {league} 
        {--count=16 : Number of gamedays} 
        {--c= : Shortcut for count} 
        {--start-date= : Start date for gamedays}
        {--courts=4 : Number of courts per gameday}
        {--games=4 : Number of games per court}';
    protected $description = 'Create gamedays with smart player attendance (better players attend more often)';

    public function handle()
    {
        $leagueSlug = $this->argument('league');
        $count = (int) $this->option('count');
        $courtsCount = (int) $this->option('courts');
        $gamesPerCourt = (int) $this->option('games');
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
        $this->info("Courts: {$courtsCount}, Games per court: {$gamesPerCourt}");
        $this->info("Smart attendance: Better players (higher Elo) attend more often");
        
        // Check for existing gamedays to continue numbering
        $existingGamedaysCount = Entry::query()
            ->where('collection', 'gamedays')
            ->where('league', $league->id())
            ->count();
            
        $this->info("Found {$existingGamedaysCount} existing gamedays. Continuing from Gameday " . ($existingGamedaysCount + 1));
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        for ($i = 0; $i < $count; $i++) {
            // Offset the index by existing gamedays count
            $currentIndex = $existingGamedaysCount + $i;
            $date = $startDate->copy()->addWeeks($i);
            $title = "Gameday " . ($currentIndex + 1);
            
            // Generate slug from title (behaves like automatic generation)
            $slug = \Illuminate\Support\Str::slug($title);
            
            // Smart attendance: Select players based on Elo
            $selectedPlayers = $this->selectPlayersWithSmartAttendance($allPlayers);
            
            $gameday = Entry::make()
                ->collection('gamedays')
                ->slug($slug)
                ->date($date)  // Set date separately for dated entries
                ->data([
                    'title' => $title,
                    'courts_count' => $courtsCount,
                    'games_per_court' => $gamesPerCourt,
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
    
    /**
     * Select players with smart attendance probability based on Elo.
     * Higher Elo = higher attendance probability.
     */
    protected function selectPlayersWithSmartAttendance($allPlayers)
    {
        $selected = [];
        
        foreach ($allPlayers as $player) {
            $elo = (float)$player->get('global_elo', 1500);
            
            // Calculate attendance probability based on Elo
            // Elo 1200 (Beginner): ~60% chance
            // Elo 1500 (Intermediate): ~75% chance
            // Elo 1600 (Advanced): ~85% chance
            // Elo 1750 (Pro): ~95% chance
            $attendanceProbability = min(0.95, max(0.60, 0.5 + ($elo - 1200) / 1000));
            
            // Random attendance based on probability
            if (rand(1, 100) / 100 <= $attendanceProbability) {
                $selected[] = $player->id();
            }
        }
        
        // Ensure minimum 15 players
        if (count($selected) < 15) {
            $missing = 15 - count($selected);
            $notSelected = $allPlayers->reject(fn($p) => in_array($p->id(), $selected));
            $additional = $notSelected->random(min($missing, $notSelected->count()))->pluck('id')->all();
            $selected = array_merge($selected, $additional);
        }
        
        return $selected;
    }
}
