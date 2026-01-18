<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class FillScores extends Command
{
    protected $signature = 'fill:scores {league?} {--all}';
    protected $description = 'Fill random scores for all matches in gamedays';

    public function handle()
    {
        $leagueSlug = $this->argument('league');
        $fillAll = $this->option('all');
        
        // Get gamedays
        if ($fillAll) {
            $gamedays = Entry::query()
                ->where('collection', 'gamedays')
                ->where('generated_plan', true)
                ->where('is_finished', false)
                ->get();
            $this->info("Filling scores for ALL gamedays...");
        } elseif ($leagueSlug) {
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
                ->where('generated_plan', true)
                ->where('is_finished', false)
                ->get()
                ->filter(function($gameday) use ($league) {
                    $gamedayLeague = $gameday->get('league');
                    if (is_array($gamedayLeague)) {
                        return in_array($league->id(), $gamedayLeague);
                    }
                    return $gamedayLeague === $league->id();
                });
                
            $this->info("Filling scores for gamedays in league: {$league->get('title')}");
        } else {
            $this->error("❌ Please specify a league slug or use --all flag");
            $this->info("Examples:");
            $this->info("  php artisan fill:scores btv-liga-2026");
            $this->info("  php artisan fill:scores --all");
            return 1;
        }
        
        if ($gamedays->count() === 0) {
            $this->warn("⚠️  No gamedays found with generated plans that are not finished");
            return 0;
        }
        
        $this->info("Found {$gamedays->count()} gamedays");
        
        $totalMatches = 0;
        $bar = $this->output->createProgressBar($gamedays->count());
        $bar->start();
        
        foreach ($gamedays as $gameday) {
            $matches = Entry::query()
                ->where('collection', 'matches')
                ->get()
                ->filter(function($match) use ($gameday) {
                    $matchGameday = $match->get('gameday');
                    if (is_array($matchGameday)) {
                        return in_array($gameday->id(), $matchGameday);
                    }
                    return $matchGameday === $gameday->id();
                });
            
            foreach ($matches as $match) {
                // Skip if already played
                if ($match->get('is_played')) {
                    continue;
                }
                
                // 1. Calculate Team Elos
                $teamAIds = (array) $match->get('team_a');
                $teamBIds = (array) $match->get('team_b');
                
                $eloA = collect($teamAIds)->avg(fn($id) => (float) (Entry::find($id)?->get('global_elo') ?? 1500));
                $eloB = collect($teamBIds)->avg(fn($id) => (float) (Entry::find($id)?->get('global_elo') ?? 1500));
                
                // 2. Calculate Winning Probability for Team A
                $probabilityA = 1 / (1 + pow(10, ($eloB - $eloA) / 400));
                
                // 3. Determine Winner based on probability
                $aWins = (rand(0, 1000) / 1000) <= $probabilityA;
                
                // 4. Generate realistic scores based on probability
                // The higher the probability, the more dominant the score usually is.
                $dominance = abs($probabilityA - 0.5) * 2; // 0 (equal) to 1 (totally dominant)
                
                if ($aWins) {
                    $scoreA = rand(11, 15);
                    // Higher dominance = lower score for loser
                    $maxLoserScore = (int) max(0, min($scoreA - 2, 9 - ($dominance * 8)));
                    $scoreB = rand(0, $maxLoserScore);
                } else {
                    $scoreB = rand(11, 15);
                    $maxLoserScore = (int) max(0, min($scoreB - 2, 9 - ($dominance * 8)));
                    $scoreA = rand(0, $maxLoserScore);
                }
                
                $match->set('score_a', $scoreA);
                $match->set('score_b', $scoreB);
                $match->set('is_played', true);
                $match->save();
                
                $totalMatches++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("✅ Filled scores for {$totalMatches} matches across {$gamedays->count()} gamedays!");
        $this->newLine();
        $this->warn("⚠️  Remember to finalize gamedays in the CP to calculate Elo!");
        
        return 0;
    }
}
