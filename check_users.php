<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Statamic\Facades\User;

$users = User::all();
echo "Total Users: " . $users->count() . "\n";
foreach ($users as $user) {
    echo "- " . $user->name() . " (" . $user->email() . "): " . $user->get('total_games') . " games\n";
}
