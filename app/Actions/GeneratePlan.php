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
        
        $successCount = 0;
        $errors = [];
        
        foreach ($items as $entry) {
            if (!$entry || $entry->collection()->handle() !== 'gamedays') {
                continue;
            }

            // ONLY execute on entries where plan generated is false
            if ($entry->get('generated_plan')) {
                continue;
            }

            try {
                $service->generateGamedayPlan($entry->id());
                $successCount++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('GeneratePlan Action Failed: ' . $e->getMessage());
                $errors[] = "{$entry->get('title')}: " . $e->getMessage();
            }
        }
        
        // Build response message
        $message = "Spielplan generiert für {$successCount} Gameday(s).";
        if (count($errors) > 0) {
            $message .= " Fehler: " . implode(', ', $errors);
        }
        
        return [
            'success' => $successCount > 0,
            'message' => $message
        ];
    }
}
