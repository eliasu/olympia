<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class CreatePlayers extends Command
{
    protected $signature = 'create:players 
        {--beginners=0 : Number of beginner players (Skill ~1200)} 
        {--intermediates=0 : Number of intermediate players (Skill ~1500)} 
        {--advanced=0 : Number of advanced players (Skill ~1600)} 
        {--pros=0 : Number of pro players (Skill ~1750)}
        {--equal-start : All players start at 1500 Elo (default: true)}
        {--use-skill-elo : Use skill-based starting Elo instead of 1500}';

    protected $description = 'Create players with skill ratings (all start at 1500 Elo by default)';

    const SKILL_GROUPS = [
        'Beginner' => ['skill_rating' => 1200, 'variance' => 0],
        'Intermediate' => ['skill_rating' => 1500, 'variance' => 0],
        'Advanced' => ['skill_rating' => 1600, 'variance' => 0],
        'Pro' => ['skill_rating' => 1750, 'variance' => 0],
    ];

    public function handle()
    {
        $playerCounts = [
            'Beginner' => $this->option('beginners'),
            'Intermediate' => $this->option('intermediates'),
            'Advanced' => $this->option('advanced'),
            'Pro' => $this->option('pros'),
        ];
        
        $totalPlayers = array_sum($playerCounts);
        
        if ($totalPlayers === 0) {
            $this->error('❌ No players specified! Use --beginners, --intermediates, --advanced, or --pros');
            $this->info('Example: php artisan create:players --beginners=10 --intermediates=20');
            return 1;
        }
        
        $this->info("👥 Creating {$totalPlayers} players...");
        $created = $this->createSkillBasedPlayers($playerCounts);
        
        $this->info("✅ Created {$created} players: " . implode(', ', array_map(
            fn($k, $v) => "{$v} {$k}", 
            array_keys(array_filter($playerCounts)), 
            array_filter($playerCounts)
        )));
        
        return 0;
    }

    protected function createSkillBasedPlayers(array $playerCounts)
    {
        $faker = \Faker\Factory::create();
        $createdCount = 0;
        $useSkillElo = $this->option('use-skill-elo');

        foreach ($playerCounts as $skillLevel => $count) {
            if ($count <= 0) continue;

            $skillConfig = self::SKILL_GROUPS[$skillLevel];
            $skillRating = $skillConfig['skill_rating'];
            $variance = $skillConfig['variance'];

            for ($i = 0; $i < $count; $i++) {
                $firstName = $faker->firstName();
                $playerName = $firstName . ' ' . $skillLevel;
                
                // Determine starting Elo
                // By default (equal-start), everyone starts at 1500
                // With --use-skill-elo, use their skill rating as starting Elo
                $startingElo = $useSkillElo 
                    ? $skillRating + rand(-$variance, $variance)
                    : 1500;

                $player = Entry::make()
                    ->collection('players')
                    ->slug(\Illuminate\Support\Str::slug($firstName . '-' . $skillLevel . '-' . $i))
                    ->data([
                        'title' => $playerName,
                        'global_elo' => (float)$startingElo,
                        'skill_rating' => (float)$skillRating, // Hidden skill level for simulation
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
