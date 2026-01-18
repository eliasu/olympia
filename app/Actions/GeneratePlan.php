<?php

namespace App\Actions;

use Statamic\Actions\Action;

class GeneratePlan extends Action
{
    /**
     * Determine if the action is visible for the given item.
     *
     * @param  mixed  $item
     * @return bool
     */
    public function visibleTo($item)
    {
        return $item instanceof \Statamic\Entries\Entry && $item->collection()->handle() === 'gamedays';
    }

    /**
     * The run method
     *
     * @return mixed
     */
    public function run($items, $values)
    {
        $service = new \App\Services\LeagueService();
        
        // We expect this action to be run on a single Gameday entry
        $entry = $items->first();
        
        if (!$entry || $entry->collection()->handle() !== 'gamedays') {
            return; // Should only run on gamedays
        }

        try {
            $entry = $items->first();
            if ($entry->get('generated_plan')) {
                 return ['error' => 'Plan existiert bereits!'];
            }

            $matches = $service->generateGamedayPlan($entry->id());
            
            return [
                'success' => true,
                'message' => 'Spielplan erfolgreich generiert. ' . count($matches) . ' Matches erstellt.',
                'redirect' => $entry->editUrl()
            ];
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('GeneratePlan Action Failed: ' . $e->getMessage());
            return ['error' => 'Fehler beim Generieren: ' . $e->getMessage()];
        }
    }
}
