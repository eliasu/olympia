<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LeagueService;
use Statamic\Facades\Entry;

class LeagueController extends Controller
{
    protected $service;

    public function __construct(LeagueService $service)
    {
        $this->service = $service;
    }

    public function generatePlan(Request $request)
    {
        $gamedayId = $request->input('gameday_id');
        try {
            $matches = $this->service->generateGamedayPlan($gamedayId);
            return response()->json(['success' => true, 'matches' => $matches]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function updateScore(Request $request)
    {
        $matchId = $request->input('match_id');
        $scoreA = $request->input('score_a');
        $scoreB = $request->input('score_b');
        
        $match = Entry::find($matchId);
        if (!$match) {
            return response()->json(['success' => false, 'error' => 'Match not found'], 404);
        }
        
        $match->set('score_a', $scoreA);
        $match->set('score_b', $scoreB);
        $match->set('is_played', true);
        $match->save();
        
        return response()->json(['success' => true]);
    }
    
    public function updatePlayers(Request $request) {
        $gamedayId = $request->input('gameday_id');
        $players = $request->input('players', []);
        
        $gameday = Entry::find($gamedayId);
        if (!$gameday) {
            return response()->json(['success' => false], 404);
        }
        
        $gameday->set('present_players', $players);
        $gameday->save();
        
        return response()->json(['success' => true]);
    }

    public function finishGameday(Request $request) {
        $gamedayId = $request->input('gameday_id');
        try {
            $this->service->finalizeGameday($gamedayId);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
