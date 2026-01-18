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
        
        $successCount = 0;
        $errors = [];
        
        foreach ($items as $entry) {
            if (!$entry || $entry->collection()->handle() !== 'gamedays') {
                continue;
            }

            // ONLY execute on entries where is_finished is false AND plan generated is true
            if ($entry->get('is_finished') || !$entry->get('generated_plan')) {
                continue;
            }

            try {
                $service->finalizeGameday($entry->id());
                $successCount++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('FinishGameday Action Failed: ' . $e->getMessage());
                $errors[] = "{$entry->get('title')}: " . $e->getMessage();
            }
        }
        
        // Build response message
        $message = "Spieltag beendet für {$successCount} Gameday(s).";
        if (count($errors) > 0) {
            $message .= " Fehler: " . implode(', ', $errors);
        }
        
        return [
            'success' => $successCount > 0,
            'message' => $message
        ];
    }
}
