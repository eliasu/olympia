<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Statamic\Facades\Entry;
use Illuminate\Support\Str;

$names = [
    "Roger Federer", "Rafael Nadal", "Novak Djokovic", "Serena Williams",
    "Andre Agassi", "Steffi Graf", "Pete Sampras", "Bjorn Borg",
    "John McEnroe", "Maria Sharapova"
];

foreach ($names as $name) {
    $slug = Str::slug($name);
    
    // Check for existence
    $existing = Entry::query()
        ->where('collection', 'players')
        ->where('slug', $slug)
        ->first();

    if ($existing) {
        echo "Skipping $name (Already exists)\n";
        continue;
    }

    $entry = Entry::make()
        ->collection('players')
        ->slug($slug)
        ->data([
            'title' => $name,
            'global_elo' => 1500.0,
            'total_games' => 0,
            'played_game_days' => 0,
            'status' => 'active'
        ]);
    
    $entry->save();
    echo "Created: $name\n";
}
