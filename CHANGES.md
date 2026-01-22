# Average Point Differential System (Option B) - Changes Documentation

## 📊 System Overview

**System:** Win Percentage + Average Point Differential

### Primary Metric: Win %
- Standard win percentage: `Wins / (Wins + Losses) × 100`

### Tiebreaker: Average Point Differential
- **Formula:** `Total Point Differential / Total Matches`
- **How it works:**
  - Every match contributes to point differential (wins AND losses)
  - 21-15 Win → +6 differential
  - 18-21 Loss → -3 differential
  - Total differential divided by matches played
  - **Result:** Average performance per match

### Why This Is Fair
1. ✅ **High wins rewarded**: 21-10 win → +11 differential
2. ✅ **Close losses less punished**: 20-21 loss → -1 differential
3. ✅ **Blowout losses heavily punished**: 10-21 loss → -11 differential
4. ✅ **Sample size neutralized**: Uses average, not total (10 matches vs 50 matches is fair)
5. ✅ **Every match matters**: Both wins and losses contribute

---

## 🔧 Changes Made

### 1. **LeagueService.php**

#### **Modified: `updatePlayerLeagueStats()` method** (Line ~400-530)

**Added tracking for:**
- `total_point_differential` - Sum of all match differentials (LEAGUE-SPECIFIC)
- `avg_point_differential` - Average differential per match

**New calculation logic:**
```php
$performanceByLeague[$leagueId] = [
    'match_count' => 0,
    'wins' => 0,
    'losses' => 0,
    'total_point_differential' => 0      // NEW
];

// During match processing:
$playerScore = $isTeamA ? $scoreA : $scoreB;
$opponentScore = $isTeamA ? $scoreB : $scoreA;

// Point differential (can be positive OR negative)
$pointDifferential = $playerScore - $opponentScore;

// Add to total (includes wins AND losses)
$performanceByLeague[$leagueId]['total_point_differential'] += $pointDifferential;
```

**Average calculation:**
```php
$avgPointDifferential = $perf['match_count'] > 0 
    ? $perf['total_point_differential'] / $perf['match_count'] 
    : 0;
```

**Updated Grid Data:**
```php
$gridData[] = [
    'league' => [$leagueId],
    'played_gamedays' => $days->count(),
    'match_count' => $perf['match_count'],              // LEAGUE-SPECIFIC
    'league_wins' => $perf['wins'],                     // LEAGUE-SPECIFIC
    'league_losses' => $perf['losses'],                 // LEAGUE-SPECIFIC
    'total_point_differential' => $perf['total_point_differential'],  // NEW
    'avg_point_differential' => round($avgPointDifferential, 2),      // NEW
    'win_percentage' => round($winPercentage, 2)
];
```

#### **Modified: `recalculateLeagueRanks()` method** (Line ~680-760)

**New Tiebreaker Hierarchy:**
1. Qualified players first (unchanged)
2. **Win Percentage** (primary - unchanged)
3. **Average Point Differential** (NEW tiebreaker #1)
4. Total Wins (tiebreaker #2)
5. Global Elo (final tiebreaker)

**New Sorting Logic:**
```php
->sort(function($a, $b) {
    // 1. Qualified players first
    if ($a['is_qualified'] && !$b['is_qualified']) return -1;
    if (!$a['is_qualified'] && $b['is_qualified']) return 1;
    
    // 2. Sort by win percentage
    if ($a['win_percentage'] != $b['win_percentage']) {
        return $b['win_percentage'] <=> $a['win_percentage'];
    }
    
    // 3. NEW: Tiebreaker 1 - Average point differential
    if ($a['avg_point_differential'] != $b['avg_point_differential']) {
        return $b['avg_point_differential'] <=> $a['avg_point_differential'];
    }
    
    // 4. Tiebreaker 2: League wins
    if ($a['league_wins'] != $b['league_wins']) {
        return $b['league_wins'] <=> $a['league_wins'];
    }
    
    // 5. Final tiebreaker: Global Elo
    return $b['global_elo'] <=> $a['global_elo'];
})
```

---

### 2. **players.yaml Blueprint**

**Added two new fields to `league_stats` grid:**

```yaml
-
  handle: total_point_differential
  field:
    type: integer
    display: 'Total Point Diff'
    default: 0
    instructions: 'Sum of all point differentials (can be negative)'
-
  handle: avg_point_differential
  field:
    type: float
    display: 'Avg Point Diff'
    default: 0
    instructions: 'Average point differential per match'
```

These fields are **read_only** and automatically populated by `LeagueService`.

---

### 3. **show.antlers.html Template**

**No changes** - Frontend remains clean, showing only:
- Rank
- Player Name + Elo
- Win %
- Record (W/L)
- Qualification Status

Point differential is used **internally** for ranking but not displayed to users.

---

## 📈 Data Structure Changes

### Player Blueprint - `league_stats` Field

**OLD Structure:**
```yaml
league_stats:
  - league: [league-id]
    played_gamedays: 12
    match_count: 47
    league_wins: 23
    league_losses: 24
    win_percentage: 48.94
    rank: 14
```

**NEW Structure:**
```yaml
league_stats:
  - league: [league-id]
    played_gamedays: 12
    match_count: 47              # LEAGUE-SPECIFIC (not total_games)
    league_wins: 23              # LEAGUE-SPECIFIC
    league_losses: 24            # LEAGUE-SPECIFIC
    total_point_differential: 35  # NEW (sum of all differentials)
    avg_point_differential: 0.74  # NEW (total / matches)
    win_percentage: 48.94
    rank: 14
```

---

## 🎯 Example Calculations

### Scenario 1: Dominant Player

**Match Results:**
- Match 1: 21-10 Win → Differential: +11
- Match 2: 21-8 Win → Differential: +13
- Match 3: 21-15 Win → Differential: +6
- Match 4: 21-12 Win → Differential: +9
- Match 5: 21-7 Win → Differential: +14

**Totals:**
- Wins: 5
- Losses: 0
- Total Differential: +53
- **Win%: 100%**
- **Avg Differential: +10.6** (very high!)

---

### Scenario 2: Close Player

**Match Results:**
- Match 1: 21-19 Win → Differential: +2
- Match 2: 19-21 Loss → Differential: -2
- Match 3: 21-20 Win → Differential: +1
- Match 4: 20-21 Loss → Differential: -1
- Match 5: 21-18 Win → Differential: +3

**Totals:**
- Wins: 3
- Losses: 2
- Total Differential: +3
- **Win%: 60%**
- **Avg Differential: +0.6** (low, but positive)

---

### Scenario 3: Struggling Player

**Match Results:**
- Match 1: 15-21 Loss → Differential: -6
- Match 2: 10-21 Loss → Differential: -11
- Match 3: 18-21 Loss → Differential: -3
- Match 4: 21-19 Win → Differential: +2
- Match 5: 12-21 Loss → Differential: -9

**Totals:**
- Wins: 1
- Losses: 4
- Total Differential: -27
- **Win%: 20%**
- **Avg Differential: -5.4** (negative!)

---

## 🔀 Ranking Examples

### Example: Tiebreaker in Action

Two players with same Win%:

**Player A:**
- Win%: 60%
- Avg Differential: +5.2
- Wins: 12
- Elo: 1520

**Player B:**
- Win%: 60%
- Avg Differential: +1.8
- Wins: 15
- Elo: 1510

**Result:** Player A ranks higher (better avg differential shows dominance)

---

## ⚙️ Key Technical Details

### League-Specific Tracking

**IMPORTANT:** All calculations use **league-specific** data, not global:

```php
// ✅ CORRECT (league-specific)
$perf['match_count']  // Only matches in THIS league
$perf['wins']         // Only wins in THIS league
$perf['losses']       // Only losses in THIS league

// ❌ WRONG (global - never used for league ranking)
$player->get('total_games')  // Global across all leagues
$player->get('wins')         // Global wins
$player->get('losses')       // Global losses
```

### Why This Matters

- Player may be in multiple leagues
- Each league tracks its own performance
- Global Elo is shared, but league stats are isolated
- Rankings are per-league, not global

---

## 🚀 Migration Notes

### Existing Data

When you first deploy this change:

1. **Existing players with `league_stats` will be missing point differential fields**
   - Solution: Run `finalizeGameday()` on any active gameday to recalculate, OR
   - Re-finalize old finished gamedays to backfill data

2. **Rankings may shift slightly**
   - Players with dominant wins will rank higher
   - Players with close games will see more accurate positioning
   - This is expected and correct

---

## ✅ Testing Checklist

- [ ] Create new gameday and generate plan
- [ ] Record match scores (test blowouts, close games, losses)
- [ ] Finalize gameday
- [ ] Verify `total_point_differential` and `avg_point_differential` are populated
- [ ] Check that values can be negative (losses)
- [ ] Verify league stats are separate from global stats
- [ ] Check league ranking order
- [ ] Verify tiebreakers work correctly (same Win%, check avg diff)
- [ ] Test with players in multiple leagues (ensure isolation)

---

## 📝 Formula Summary

```
Win% = (Wins / Total Matches) × 100

Point Differential (per match) = Player Score - Opponent Score

Total Point Differential = Sum of all match differentials

Average Point Differential = Total Point Differential / Total Matches

Ranking Order:
1. Qualified Status
2. Win% (descending)
3. Avg Point Differential (descending) ← NEW
4. Total Wins (descending)
5. Global Elo (descending)
```

---

## 🎲 Why This System Is Better

### vs. Game-Based Win%
- **More granular**: 21-10 win ≠ 21-20 win
- **Rewards dominance**: High wins matter
- **Fair for losses**: Close loss less punished

### vs. Pure Point Differential
- **Win% still primary**: Winning games is most important
- **Differential as tiebreaker**: Used only when Win% is equal
- **Balanced approach**: Not overly complex

### vs. Other Systems
- **Sample size neutral**: Average removes player count bias
- **League-specific**: Fair comparison within league
- **Transparent**: Easy to understand and explain

---

## 📦 Files Changed

1. **LeagueService.php** - Core logic with point differential tracking
2. **players.yaml** - Blueprint with new fields
3. **show.antlers.html** - Template (unchanged, clean UI)
4. **CHANGES.md** - This documentation
