<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class CreatePlayers extends Command
{
    protected $signature = 'create:players {count=10} {elo=1500}';
    protected $description = 'Create random players with specified Elo rating';

    public function handle()
    {
        $count = (int) $this->argument('count');
        $elo = (float) $this->argument('elo');
        
        $this->info("Creating {$count} players with Elo {$elo}...");
        
        $names = $this->generateNames($count);
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        foreach ($names as $name) {
            $slug = \Illuminate\Support\Str::slug($name);
            
            $player = Entry::make()
                ->collection('players')
                ->slug($slug)
                ->data([
                    'title' => $name,
                    'global_elo' => $elo,
                    'total_games' => 0,
                    'player_status' => 'active',
                    'avatar_url' => "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($name)
                ]);
            
            $player->save();
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("✅ Created {$count} players successfully!");
        
        return 0;
    }
    
    private function generateNames($count)
    {
        $firstNames = [
            'Max', 'Anna', 'Tom', 'Lisa', 'Jan', 'Sarah', 'Tim', 'Laura', 'Felix', 'Emma',
            'Lukas', 'Sophie', 'Noah', 'Mia', 'Leon', 'Hannah', 'Paul', 'Lena', 'Jonas', 'Lea',
            'David', 'Marie', 'Finn', 'Julia', 'Ben', 'Emily', 'Luis', 'Charlotte', 'Elias', 'Amelie',
            'Henry', 'Sophia', 'Anton', 'Clara', 'Oskar', 'Emilia', 'Theo', 'Ida', 'Jakob', 'Greta',
            'Moritz', 'Frieda', 'Emil', 'Mathilda', 'Karl', 'Paula', 'Friedrich', 'Lotte', 'Otto', 'Alma'
        ];
        
        $lastNames = [
            'Müller', 'Schmidt', 'Weber', 'Meyer', 'Fischer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Koch',
            'Werner', 'Richter', 'Klein', 'Krause', 'Schmitt', 'Zimmermann', 'Braun', 'Hartmann', 'Lange', 'Schmid',
            'Krüger', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Hofmann', 'Schneider', 'Keller', 'Lehmann', 'Huber'
        ];
        
        $names = [];
        $used = [];
        
        for ($i = 0; $i < $count; $i++) {
            do {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $fullName = "{$firstName} {$lastName}";
            } while (in_array($fullName, $used));
            
            $used[] = $fullName;
            $names[] = $fullName;
        }
        
        return $names;
    }
}
