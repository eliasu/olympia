<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;
use Stillat\Relationships\Support\Facades\Relate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // League <-> gamedays (One-to-Many)
        Relate::oneToMany('leagues.gamedays', 'gamedays.league');

        // Gameday <-> Matches (One-to-Many)
        Relate::oneToMany('gamedays.matches', 'matches.gameday');

        // Players <-> Matches (Many-to-Many)
        // Since Match has team_a and team_b, we relate both to players.matches
        Relate::manyToMany('matches.team_a', 'players.matches');
        Relate::manyToMany('matches.team_b', 'players.matches');
    }
}
