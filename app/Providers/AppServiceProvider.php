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
        Relate::manyToOne('leagues.gamedays', 'gamedays.league')->withEvents(true);

        // Gameday <-> Matches (One-to-Many)
        Relate::manyToOne('gamedays.matches', 'matches.gameday')->withEvents(true);

        // Players <-> Matches (Many-to-Many)
        // Since Match has team_a and team_b, we relate both to players.matches
        Relate::manyToMany('matches.team_a', 'players.matches')->withEvents(true);
        Relate::manyToMany('matches.team_b', 'players.matches')->withEvents(true);
    }
}
