<?php

namespace App\Http\Controllers\CP;

use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Entry;

class ConsoleUtilityController extends CpController
{
    public function index()
    {
        $leagues = Entry::query()
            ->where('collection', 'leagues')
            ->get()
            ->map(function ($league) {
                return [
                    'id' => $league->id(),
                    'title' => $league->get('title'),
                    'slug' => $league->slug(),
                ];
            });

        return view('cp.utilities.console-commands', [
            'leagues' => $leagues,
            'commands' => $this->getAvailableCommands(),
        ]);
    }

    public function run(Request $request)
    {
        $command = $request->input('command');
        $groups = $this->getAvailableCommands();
        
        // Find the command inside the groups
        $cmdConfig = null;
        foreach ($groups as $group) {
            if (isset($group['commands'][$command])) {
                $cmdConfig = $group['commands'][$command];
                break;
            }
        }

        if (! $cmdConfig) {
            return back()->with('error', 'Invalid or unapproved command requested.');
        }

        $params = [];

        // Process arguments
        foreach ($cmdConfig['args'] ?? [] as $arg => $config) {
            if ($request->has($arg)) {
                $params[$arg] = $request->input($arg);
            }
        }

        // Process options
        foreach ($cmdConfig['options'] ?? [] as $opt => $config) {
            if ($config['type'] === 'bool') {
                if ($request->has($opt)) {
                    $params['--' . $opt] = true;
                }
            } elseif ($request->has($opt) && $request->input($opt) !== null && $request->input($opt) !== '') {
                $params['--' . $opt] = $request->input($opt);
            }
        }

        try {
            Artisan::call($command, $params);
            $output = Artisan::output();
            return back()->with('success', 'Command executed successfully: ' . nl2br($output));
        } catch (\Exception $e) {
            return back()->with('error', 'Error executing command: ' . $e->getMessage());
        }
    }

    private function getAvailableCommands()
    {
        return [
            'generation' => [
                'title' => 'Content Generation',
                'commands' => [
                    'create:gamedays' => [
                        'title' => 'Generate League Gamedays',
                        'description' => 'Schedule match days with smart player attendance based on Elo.',
                        'args' => ['league' => ['type' => 'league_slug', 'label' => 'Target League']],
                        'options' => [
                            'count' => ['type' => 'number', 'label' => 'Gameday Count', 'default' => 16],
                            'start-date' => ['type' => 'text', 'label' => 'First Gameday Date', 'placeholder' => 'YYYY-MM-DD (Defaults to next Monday)'],
                            'courts' => ['type' => 'number', 'label' => 'Available Courts', 'default' => 4],
                            'games' => ['type' => 'number', 'label' => 'Games per Court', 'default' => 4],
                            'players' => ['type' => 'number', 'label' => 'Fixed Attendance', 'placeholder' => 'System defaults to 15 minimum'],
                        ]
                    ],
                    'create:players' => [
                        'title' => 'Seed Dummy Players',
                        'description' => 'Create a batch of players with varied hidden skill ratings for testing.',
                        'options' => [
                            'beginners' => ['type' => 'number', 'label' => 'New Starters', 'default' => 0],
                            'intermediates' => ['type' => 'number', 'label' => 'Intermediate', 'default' => 0],
                            'advanced' => ['type' => 'number', 'label' => 'Advanced', 'default' => 0],
                            'pros' => ['type' => 'number', 'label' => 'Professionals', 'default' => 0],
                            'use-skill-elo' => ['type' => 'bool', 'label' => 'Use Skill for starting Elo'],
                        ]
                    ],
                ]
            ],
            'management' => [
                'title' => 'League Management',
                'commands' => [
                    'fill:scores' => [
                        'title' => 'Auto-Fill Match Scores',
                        'description' => 'Generate realistic scores based on player skill probabilities.',
                        'args' => ['league' => ['type' => 'league_slug', 'label' => 'Specific League']],
                        'options' => [
                            'all' => ['type' => 'bool', 'label' => 'Apply to all unfinished gamedays'],
                        ]
                    ],
                    'recalculate:league-ranks' => [
                        'title' => 'Refresh Leaderboards',
                        'description' => 'Force a full recalculation of player rankings and statistics.',
                        'args' => ['league' => ['type' => 'league_slug', 'label' => 'Target League']],
                    ],
                    'simulate:league' => [
                        'title' => 'Full League Simulation',
                        'description' => 'Auto-generate plans, scores, and finalize all gamedays sequentially.',
                        'args' => ['league' => ['type' => 'league_slug', 'label' => 'Target League']],
                    ],
                ]
            ],
            'cleanup' => [
                'title' => 'Data Cleanup',
                'commands' => [
                    'app:reset-players' => [
                        'title' => 'Reset All Player Stats',
                        'description' => 'Wipe all history and return everyone to 1500 Elo. Irreversible.',
                        'destructive' => true,
                    ],
                    'delete:gamedays' => [
                        'title' => 'Purge All Gamedays',
                        'description' => 'Permanently delete every entry in the Gamedays collection.',
                        'destructive' => true,
                    ],
                    'delete:leagues' => [
                        'title' => 'Purge All Leagues',
                        'description' => 'Permanently delete every entry in the Leagues collection.',
                        'destructive' => true,
                    ],
                    'delete:matches' => [
                        'title' => 'Purge All Matches',
                        'description' => 'Permanently delete every entry in the Matches collection.',
                        'destructive' => true,
                    ],
                    'delete:players' => [
                        'title' => 'Purge Non-Admin Users',
                        'description' => 'Permanently remove all players from the system.',
                        'destructive' => true,
                    ],
                ]
            ]
        ];
    }
}
