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
        \Statamic\Statamic::booted(function () {
            // Generate User Slug on Save
            \Illuminate\Support\Facades\Event::listen(\Statamic\Events\UserSaving::class, function ($event) {
                \Illuminate\Support\Facades\Log::info('UserSaving event listener triggered for: ' . $event->user->email);
                $user = $event->user;
                if (empty($user->get('slug')) || $user->isDirty('name')) {
                    $newSlug = \Illuminate\Support\Str::slug($user->name);
                    \Illuminate\Support\Facades\Log::info('Generating new slug: ' . $newSlug);
                    $user->set('slug', $newSlug);
                }
            });

            // Force Stache refresh on User Saved
            \Illuminate\Support\Facades\Event::listen(\Statamic\Events\UserSaved::class, function ($event) {
                \Illuminate\Support\Facades\Log::info('UserSaved event listener triggered - refreshing stache');
                \Statamic\Facades\Stache::refresh();
            });

            // Generate Gameday Slug on Creation
            \Illuminate\Support\Facades\Event::listen(\Statamic\Events\EntryCreating::class, function ($event) {
                $entry = $event->entry;
                
                if ($entry->collectionHandle() === 'gamedays') {
                    $title = $entry->get('title');
                    
                    // We only generate this upon creation to ensure diverse slugs while avoiding 
                    // changing slugs on future updates.
                    $date = $entry->date();
                    $dateString = '';
                    
                    if ($date) {
                        try {
                            $dateString = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            $dateString = '';
                        }
                    }

                    if ($title && $dateString) {
                        $newSlug = \Illuminate\Support\Str::slug($title . '-' . $dateString);
                        $entry->slug($newSlug);
                    }
                }
            });
        });

        // Register Console Commands Utility
        \Statamic\Facades\Utility::extend(function () {
            \Statamic\Facades\Utility::register('console-commands')
                ->title('Console Commands')
                ->description('Run application maintenance and simulation commands.')
                ->icon('terminal')
                ->routes(function ($router) {
                    $router->get('/', [\App\Http\Controllers\CP\ConsoleUtilityController::class, 'index'])->name('index');
                    $router->post('/run', [\App\Http\Controllers\CP\ConsoleUtilityController::class, 'run'])->name('run');
                });
        });

        // League <-> gamedays (One-to-Many)
        Relate::manyToOne('leagues.gamedays', 'gamedays.league')->withEvents(false);

        // Gameday <-> Matches (One-to-Many)
        Relate::manyToOne('gamedays.matches', 'matches.gameday')->withEvents(false);

        // Users (Players) <-> Matches (Many-to-Many)
        // Since Match has team_a and team_b, we relate both to user:matches
        Relate::manyToMany('matches.team_a', 'user:matches')->withEvents(false);
        Relate::manyToMany('matches.team_b', 'user:matches')->withEvents(false);
    }
}
