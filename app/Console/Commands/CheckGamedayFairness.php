<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class CheckGamedayFairness extends Command
{
    protected $signature = 'gameday:check {gameday? : Gameday ID or slug} {--all : Check all gamedays with generated plans}';
    protected $description = 'Check a gameday for fairness: game counts and team/opponent pairing analysis';

    public function handle()
    {
        if ($this->option('all')) {
            $gamedays = Entry::query()
                ->where('collection', 'gamedays')
                ->where('generated_plan', true)
                ->get()
                ->sortBy('date')
                ->values();

            if ($gamedays->isEmpty()) {
                $this->warn('No gamedays with generated plans found.');
                return 1;
            }

            $this->info("Checking {$gamedays->count()} gamedays...");
            $this->line('');

            foreach ($gamedays as $gd) {
                $this->checkGameday($gd);
                $this->line(str_repeat('─', 60));
                $this->line('');
            }

            return 0;
        }

        $identifier = $this->argument('gameday');
        if (!$identifier) {
            $this->error('Provide a gameday ID/slug or use --all.');
            return 1;
        }

        $gameday = Entry::find($identifier)
            ?? Entry::query()->where('collection', 'gamedays')->where('slug', $identifier)->first();

        if (!$gameday) {
            $this->error("Gameday not found: {$identifier}");
            return 1;
        }

        return $this->checkGameday($gameday);
    }

    protected function checkGameday($gameday)
    {
        $gamedayId = $gameday->id();
        $this->info("Gameday: " . $gameday->get('title') . " [{$gamedayId}]");
        $this->info("Status:  " . ($gameday->get('is_finished') ? 'Finished' : 'In progress'));
        $this->line('');

        // Load matches
        $matches = Entry::query()
            ->where('collection', 'matches')
            ->where('gameday', $gamedayId)
            ->get()
            ->sortBy(fn($m) => (int) last(explode('-', $m->slug())));

        if ($matches->isEmpty()) {
            $this->warn('No matches found for this gameday.');
            return 1;
        }

        // Build player name map
        $presentIds = (array) $gameday->get('present_players', []);
        $playerNames = [];
        foreach ($presentIds as $id) {
            $user = User::find($id);
            $playerNames[$id] = $user ? (explode(' ', $user->get('name', $id))[0]) : substr($id, 0, 8);
        }
        $n = fn($id) => $playerNames[$id] ?? substr($id, 0, 8);

        // Parse match data
        $matchData = $matches->map(function ($match) use ($n) {
            $teamA = (array) $match->get('team_a', []);
            $teamB = (array) $match->get('team_b', []);
            return [
                'num'     => (int) last(explode('-', $match->slug())),
                'team_a'  => $teamA,
                'team_b'  => $teamB,
                'played'  => (bool) $match->get('is_played'),
                'label_a' => implode('+', array_map($n, $teamA)),
                'label_b' => implode('+', array_map($n, $teamB)),
            ];
        })->values();

        $totalMatches = $matchData->count();
        $totalSlots   = $totalMatches * 4;
        $playerCount  = count($presentIds);
        $floor        = (int) floor($totalSlots / $playerCount);
        $ceil         = (int) ceil($totalSlots / $playerCount);
        $extraCount   = $totalSlots % $playerCount;

        // ── Game counts ──────────────────────────────────────────────
        $this->line('<options=bold>═══ Game Counts ═══</>');
        $gameCounts = array_fill_keys($presentIds, 0);
        foreach ($matchData as $m) {
            foreach (array_merge($m['team_a'], $m['team_b']) as $pid) {
                if (isset($gameCounts[$pid])) $gameCounts[$pid]++;
            }
        }

        arsort($gameCounts);
        $ideal = $extraCount > 0
            ? "{$extraCount}×{$ceil} + " . ($playerCount - $extraCount) . "×{$floor}"
            : "{$playerCount}×{$floor}";

        $this->line("  {$playerCount} players · {$totalMatches} matches · {$totalSlots} slots · ideal: {$ideal}");
        $this->line('');

        $under = $over = 0;
        $rows = [];
        foreach ($gameCounts as $pid => $count) {
            $flag = '';
            if ($count < $floor) { $flag = ' <fg=red>▼ UNDER</>'; $under++; }
            elseif ($count > $ceil) { $flag = ' <fg=yellow>▲ OVER</>'; $over++; }
            $rows[] = [$n($pid), $count, str_repeat('█', $count) . $flag];
        }
        $this->table(['Player', 'Games', ''], $rows);

        if ($under === 0 && $over === 0) {
            $this->info('  ✅ All players within fair range.');
        } else {
            if ($under) $this->error("  ▼ {$under} player(s) under floor ({$floor})");
            if ($over)  $this->warn("  ▲ {$over} player(s) over ceil ({$ceil})");
        }
        $this->line('');

        // ── Duplicate analysis ───────────────────────────────────────
        $this->line('<options=bold>═══ Duplicate Analysis ═══</>');

        $exactMatches  = [];
        $teamPairs     = [];
        $foursomes     = [];

        foreach ($matchData as $m) {
            $tA = $this->sortedKey($m['team_a']);
            $tB = $this->sortedKey($m['team_b']);
            $pair = [$tA, $tB]; sort($pair);
            $matchKey    = implode('||', $pair);
            $foursomeKey = $this->sortedKey(array_merge($m['team_a'], $m['team_b']));

            $exactMatches[$matchKey][]  = $m['num'];
            $teamPairs[$tA][]           = ['match' => $m['num'], 'label' => $m['label_a']];
            $teamPairs[$tB][]           = ['match' => $m['num'], 'label' => $m['label_b']];
            $foursomes[$foursomeKey][]  = $m['num'];
        }

        $dupExact    = array_filter($exactMatches, fn($v) => count($v) > 1);
        $dupTeams    = array_filter($teamPairs,    fn($v) => count($v) > 1);
        $dupFoursome = array_filter($foursomes,    fn($v) => count($v) > 1);

        if (empty($dupExact)) {
            $this->info('  ✅ No exact match repeats');
        } else {
            foreach ($dupExact as $key => $nums) {
                $this->error("  ❌ Exact match repeated in: M" . implode(', M', $nums));
            }
        }

        if (empty($dupTeams)) {
            $this->info('  ✅ No team-pair repeats');
        } else {
            foreach ($dupTeams as $entries) {
                $label  = $entries[0]['label'];
                $mnums  = array_column($entries, 'match');
                $this->warn("  ⚠  Team pair '{$label}' in M" . implode(', M', $mnums));
            }
        }

        if (empty($dupFoursome)) {
            $this->info('  ✅ No foursome repeats');
        } else {
            foreach ($dupFoursome as $nums) {
                $this->warn("  ⚠  Same foursome in M" . implode(', M', $nums));
            }
        }
        $this->line('');

        // ── Per-player partner & opponent summary ────────────────────
        $this->line('<options=bold>═══ Partner & Opponent Issues ═══</>');

        $issues = [];
        foreach ($presentIds as $pid) {
            $partners   = [];
            $oppPairs   = [];
            $oppIndivs  = [];

            foreach ($matchData as $m) {
                if (in_array($pid, $m['team_a'])) {
                    $partner = array_values(array_diff($m['team_a'], [$pid]))[0] ?? null;
                    if ($partner) $partners[] = $partner;
                    $oppPair = $this->sortedKey($m['team_b']);
                    $oppPairs[] = $oppPair;
                    $oppIndivs = array_merge($oppIndivs, $m['team_b']);
                } elseif (in_array($pid, $m['team_b'])) {
                    $partner = array_values(array_diff($m['team_b'], [$pid]))[0] ?? null;
                    if ($partner) $partners[] = $partner;
                    $oppPair = $this->sortedKey($m['team_a']);
                    $oppPairs[] = $oppPair;
                    $oppIndivs = array_merge($oppIndivs, $m['team_a']);
                }
            }

            $playerIssues = [];

            $partnerCounts = array_count_values($partners);
            foreach ($partnerCounts as $partnerId => $cnt) {
                if ($cnt > 1) $playerIssues[] = "partner '{$n($partnerId)}' ×{$cnt}";
            }

            $oppPairCounts = array_count_values($oppPairs);
            foreach ($oppPairCounts as $pairKey => $cnt) {
                if ($cnt > 1) {
                    $ids   = explode('|', $pairKey);
                    $label = implode('+', array_map($n, $ids));
                    $playerIssues[] = "opp-pair '{$label}' ×{$cnt}";
                }
            }

            $oppIndivCounts = array_count_values($oppIndivs);
            foreach ($oppIndivCounts as $oppId => $cnt) {
                if ($cnt > 1) $playerIssues[] = "opp '{$n($oppId)}' ×{$cnt}";
            }

            if (!empty($playerIssues)) {
                $games = $gameCounts[$pid] ?? 0;
                $issues[] = [$n($pid), $games, implode(', ', $playerIssues)];
            }
        }

        if (empty($issues)) {
            $this->info('  ✅ No partner or opponent repeat issues');
        } else {
            $this->table(['Player', 'Games', 'Issues'], $issues);
        }

        return 0;
    }

    private function sortedKey(array $ids): string
    {
        sort($ids);
        return implode('|', $ids);
    }
}
