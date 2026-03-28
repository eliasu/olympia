<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use App\Services\LeagueService;

class RecalculateLeagueRanks extends Command
{
    protected $signature = 'recalculate:league-ranks {league?}';
    protected $description = 'Recalculate league rankings for one or all leagues';

    public function handle(LeagueService $leagueService)
    {
        $leagueSlug = $this->argument('league');
        
        if ($leagueSlug) {
            // Recalculate for specific league
            $league = Entry::query()
                ->where('collection', 'leagues')
                ->where('slug', $leagueSlug)
                ->first();
                
            if (!$league) {
                $this->error("❌ League '{$leagueSlug}' not found!");
                return 1;
            }
            
            $this->info("🔄 Recalculating ranks for: {$league->get('title')}");
            
            $oldRanks = $this->getLeagueRanks($league->id());
            $leagueService->recalculateLeagueRanks($league->id());
            $newRanks = $this->getLeagueRanks($league->id());
            
            $this->reportDifferences($oldRanks, $newRanks);
            
            $this->info("✅ Done!");
            
        } else {
            // Recalculate for ALL leagues
            $leagues = Entry::query()
                ->where('collection', 'leagues')
                ->get();
                
            if ($leagues->isEmpty()) {
                $this->warn("⚠️  No leagues found!");
                return 0;
            }
            
            $this->info("🔄 Recalculating ranks for {$leagues->count()} leagues...");
            $bar = $this->output->createProgressBar($leagues->count());
            $bar->start();
            
            $allChanges = [];
            
            foreach ($leagues as $league) {
                $oldRanks = $this->getLeagueRanks($league->id());
                $leagueService->recalculateLeagueRanks($league->id());
                $newRanks = $this->getLeagueRanks($league->id());
                
                $changes = $this->getDifferences($oldRanks, $newRanks);
                if (!empty($changes)) {
                    $allChanges[$league->get('title')] = $changes;
                }
                
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            if (empty($allChanges)) {
                $this->info("✨ All league ranks were already correct. No changes made.");
            } else {
                $this->warn("⚠️  Found incorrect ranks in " . count($allChanges) . " league(s):");
                foreach ($allChanges as $leagueTitle => $changes) {
                    $this->line("  League: {$leagueTitle}");
                    foreach ($changes as $change) {
                        $old = $change['old'] ?? 'None';
                        $new = $change['new'] ?? 'None';
                        $this->line("    - {$change['name']}: {$old} -> {$new}");
                    }
                }
            }
            
            $this->info("✅ All league ranks recalculated!");
        }
        
        return 0;
    }

    protected function getLeagueRanks($leagueId)
    {
        $ranks = [];
        $users = User::all();
        foreach ($users as $user) {
            $stats = $user->get('league_stats', []);
            if (!is_array($stats)) continue;
            
            foreach ($stats as $stat) {
                $rowLeagues = (array)($stat['league'] ?? []);
                $rowLeagueId = reset($rowLeagues);
                if ($rowLeagueId === $leagueId) {
                    $ranks[$user->id()] = [
                        'name' => $user->name() ?? $user->email() ?? $user->id(),
                        'rank' => $stat['rank'] ?? null,
                    ];
                }
            }
        }
        return $ranks;
    }

    protected function getDifferences($oldRanks, $newRanks)
    {
        $changes = [];
        foreach ($newRanks as $userId => $new) {
            $oldRank = $oldRanks[$userId]['rank'] ?? null;
            if ($oldRank !== $new['rank']) {
                $changes[] = [
                    'name' => $new['name'],
                    'old' => $oldRank,
                    'new' => $new['rank']
                ];
            }
        }
        return $changes;
    }

    protected function reportDifferences($oldRanks, $newRanks)
    {
        $changes = $this->getDifferences($oldRanks, $newRanks);
        
        if (empty($changes)) {
            $this->info("  ✨ Ranks were already correct. No changes made.");
        } else {
            $this->warn("  ⚠️  Found " . count($changes) . " incorrect rank(s):");
            foreach ($changes as $change) {
                $old = $change['old'] ?? 'None';
                $new = $change['new'] ?? 'None';
                $this->line("    - {$change['name']}: {$old} -> {$new}");
            }
        }
    }
}
