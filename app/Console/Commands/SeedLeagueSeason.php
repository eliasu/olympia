<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use App\Services\LeagueService;
use Carbon\Carbon;

class SeedLeagueSeason extends Command
{
    protected $signature = 'seed:league-season';
    protected $description = 'Seed a complete league season with players, gamedays, and matches';

    private $starPlayers = [];
    private $leagueService;

    public function handle()
    {
        $this->leagueService = new LeagueService();
        
        $this->info('🎾 Starting League Season Seeder...');
        
        // Step 1: Create 50 Players
        $this->info("\n📝 Step 1: Creating 50 players...");
        $players = $this->createPlayers();
        $this->info("✅ Created {$players->count()} players");
        
        // Step 2: Find League
        $this->info("\n🏆 Step 2: Finding 'BTV Liga 2026'...");
        $league = Entry::query()
            ->where('collection', 'leagues')
            ->where('title', 'BTV Liga 2026')
            ->first();
            
        if (!$league) {
            $this->error('❌ League "BTV Liga 2026" not found!');
            return 1;
        }
        $this->info("✅ Found league: {$league->get('title')}");
        
        // Step 3: Add 40 players to league
        $this->info("\n👥 Step 3: Adding 40 players to league...");
        $leaguePlayers = $players->take(40)->pluck('id')->all();
        $league->set('players', $leaguePlayers);
        $league->save();
        $this->info("✅ Added 40 players to league");
        
        // Step 4: Create 15 Gamedays (Mondays)
        $this->info("\n📅 Step 4: Creating 15 gamedays (Mondays)...");
        $gamedays = $this->createGamedays($league->id());
        $this->info("✅ Created {$gamedays->count()} gamedays");
        
        // Step 5: Simulate each gameday
        $this->info("\n🎮 Step 5: Simulating gamedays...");
        $this->simulateGamedays($gamedays, $players->take(40), $league->id());
        
        $this->info("\n🎉 Season seeding complete!");
        $this->info("📊 Check the dashboard to see the results!");
        
        return 0;
    }
    
    private function createPlayers()
    {
        $names = [
            'Max Müller', 'Anna Schmidt', 'Tom Weber', 'Lisa Meyer', 'Jan Fischer',
            'Sarah Wagner', 'Tim Becker', 'Laura Schulz', 'Felix Hoffmann', 'Emma Koch',
            'Lukas Werner', 'Sophie Richter', 'Noah Klein', 'Mia Krause', 'Leon Schmitt',
            'Hannah Zimmermann', 'Paul Braun', 'Lena Hartmann', 'Jonas Lange', 'Lea Schmid',
            'David Krüger', 'Marie Wolf', 'Finn Schröder', 'Julia Neumann', 'Ben Schwarz',
            'Emily Zimmermann', 'Luis Hofmann', 'Charlotte Krause', 'Elias Schmitt', 'Amelie Koch',
            'Henry Richter', 'Sophia Klein', 'Anton Werner', 'Clara Becker', 'Oskar Wagner',
            'Emilia Fischer', 'Theo Meyer', 'Ida Weber', 'Jakob Schmidt', 'Greta Müller',
            'Moritz Lange', 'Frieda Hartmann', 'Emil Braun', 'Mathilda Zimmermann', 'Karl Schröder',
            'Paula Neumann', 'Friedrich Schwarz', 'Lotte Wolf', 'Otto Krüger', 'Alma Schmid'
        ];
        
        $players = collect();
        
        foreach ($names as $index => $name) {
            $slug = \Illuminate\Support\Str::slug($name);
            
            $player = Entry::make()
                ->collection('players')
                ->slug($slug)
                ->data([
                    'title' => $name,
                    'global_elo' => 1500.00,
                    'total_games' => 0,
                    'player_status' => 'active'
                ]);
            
            $player->save();
            $players->push($player);
            
            // Mark first 5 as star players (internally)
            if ($index < 5) {
                $this->starPlayers[] = $player->id();
            }
        }
        
        $this->info("⭐ Star players: " . implode(', ', array_slice($names, 0, 5)));
        
        return $players;
    }
    
    private function createGamedays($leagueId)
    {
        $gamedays = collect();
        $startDate = Carbon::now()->next(Carbon::MONDAY);
        
        for ($i = 0; $i < 15; $i++) {
            $date = $startDate->copy()->addWeeks($i);
            $title = "Gameday " . ($i + 1) . " - " . $date->format('d.m.Y');
            $slug = \Illuminate\Support\Str::slug($title);
            
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
                    'present_players' => []
                ]);
            
            // Set league separately to ensure it's saved correctly
            $gameday->set('league', [$leagueId]);
            $gameday->save();
            $gamedays->push($gameday);
        }
        
        return $gamedays;
    }
    
    private function simulateGamedays($gamedays, $players, $leagueId)
    {
        $bar = $this->output->createProgressBar($gamedays->count());
        $bar->start();
        
        foreach ($gamedays as $index => $gameday) {
            $this->info("\n\n🎯 Gameday " . ($index + 1) . ": {$gameday->get('title')}");
            
            // 1. Add 15-25 random players
            $playerCount = rand(15, 25);
            $presentPlayers = $players->random($playerCount)->pluck('id')->all();
            
            // Ensure at least 2-3 star players are present
            $starCount = 0;
            foreach ($presentPlayers as $playerId) {
                if (in_array($playerId, $this->starPlayers)) {
                    $starCount++;
                }
            }
            
            // Add more stars if needed
            while ($starCount < 2 && count($presentPlayers) < 25) {
                $randomStar = $this->starPlayers[array_rand($this->starPlayers)];
                if (!in_array($randomStar, $presentPlayers)) {
                    $presentPlayers[] = $randomStar;
                    $starCount++;
                }
            }
            
            $gameday->set('present_players', $presentPlayers);
            $gameday->save();
            $this->info("  👥 Added {$playerCount} players");
            
            // 2. Generate plan
            try {
                $matches = $this->leagueService->generateGamedayPlan($gameday->id());
                $this->info("  ⚡ Generated plan: " . count($matches) . " matches");
            } catch (\Exception $e) {
                $this->error("  ❌ Plan generation failed: " . $e->getMessage());
                continue;
            }
            
            // 3. Enter scores
            $this->enterScores($gameday->id());
            $this->info("  📊 Entered scores");
            
            // 4. Finalize gameday
            try {
                $this->leagueService->finalizeGameday($gameday->id());
                $this->info("  ✅ Finalized gameday");
            } catch (\Exception $e) {
                $this->error("  ❌ Finalization failed: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
    }
    
    private function enterScores($gamedayId)
    {
        // Query matches - gameday field is stored as array
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->get()
            ->filter(function($match) use ($gamedayId) {
                $matchGameday = $match->get('gameday');
                if (is_array($matchGameday)) {
                    return in_array($gamedayId, $matchGameday);
                }
                return $matchGameday === $gamedayId;
            });
        
        foreach ($matches as $match) {
            $teamA = $match->get('team_a', []);
            $teamB = $match->get('team_b', []);
            
            // Check if team has star players
            $teamAStars = count(array_intersect($teamA, $this->starPlayers));
            $teamBStars = count(array_intersect($teamB, $this->starPlayers));
            
            // Determine winner based on star players
            if ($teamAStars > $teamBStars) {
                // Team A wins (80% chance if they have more stars)
                $scoreA = rand(9, 11);
                $scoreB = rand(0, 8);
            } elseif ($teamBStars > $teamAStars) {
                // Team B wins
                $scoreA = rand(0, 8);
                $scoreB = rand(9, 11);
            } else {
                // Random outcome
                if (rand(0, 1) === 0) {
                    $scoreA = rand(9, 11);
                    $scoreB = rand(0, 8);
                } else {
                    $scoreA = rand(0, 8);
                    $scoreB = rand(9, 11);
                }
            }
            
            $match->set('score_a', $scoreA);
            $match->set('score_b', $scoreB);
            $match->set('is_played', true);
            $match->save();
        }
    }
}
