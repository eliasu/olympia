<?php

namespace App\Actions;

use Statamic\Actions\Action;

class FinishGameday extends Action
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
        
        $entry = $items->first();
        
        if (!$entry || $entry->collection()->handle() !== 'gamedays') {
            return;
        }

        try {
            $entry = $items->first();
            
            if (!$entry->get('generated_plan')) {
                return ['error' => 'Zuerst muss ein Plan generiert werden.'];
            }
            
            if ($entry->get('is_finished')) {
                return ['error' => 'Spieltag ist bereits abgeschlossen.'];
            }

            $service->finalizeGameday($entry->id());
            
            return [
                'success' => true,
                'message' => 'Spieltag beendet und Elos berechnet.',
                'redirect' => $entry->editUrl()
            ];
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FinishGameday Action Failed: ' . $e->getMessage());
            return ['error' => 'Fehler beim Beenden: ' . $e->getMessage()];
        }
    }
}
