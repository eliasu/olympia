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
                // OPT: Removed Log::info() — logging on every user save added unnecessary
                //      disk I/O, firing 40–60 times per gameday finalization.
                $user = $event->user;
                if (empty($user->get('slug')) || $user->isDirty('name')) {
                    $newSlug = \Illuminate\Support\Str::slug($user->name);
                    $user->set('slug', $newSlug);
                }
            });

            // OPT: Removed the UserSaved → Stache::refresh() listener entirely.
            //
            // Stache::refresh() rebuilds Statamic's full file-based cache by scanning
            // all entries, users, and taxonomies on disk. With the old listener in place,
            // every $player->save() inside finalizeGameday() triggered a full Stache
            // rebuild — potentially 40–60 times per finalization on a small server.
            //
            // Statamic's Stache updates itself incrementally on individual saves without
            // needing a full refresh. A manual refresh is only needed after bulk out-of-
            // band file changes (e.g. a git deploy or direct file edits). If you ever need
            // it again, call Stache::refresh() once explicitly at the end of the operation
            // rather than hooking it to every save event.

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
