# Simple Win Percentage System - Changes Documentation

## 📊 System Overview

**System:** Simple Win Percentage Ranking

### Primary Metric: Win %
- Standard win percentage: `Wins / (Wins + Losses) × 100`
- That's it. Simple and transparent.

### Why Simple Win%?
1. ✅ **Easy to understand**: "I have 60% Win Rate"
2. ✅ **Large sample size**: With 400+ games, Win% is highly reliable
3. ✅ **Tiebreakers rarely needed**: Different match counts make equal Win% extremely unlikely
4. ✅ **KISS Principle**: Keep It Simple, Stupid

---

## 🔧 Changes Made

### 1. **LeagueService.php**

#### **Simplified: `updatePlayerLeagueStats()` method**

**Tracks only:**
- `match_count` - Total matches in league
- `wins` - Total wins in league
- `losses` - Total losses in league
- `win_percentage` - Simple calculation

**Calculation:**
```php
$totalMatches = $perf['wins'] + $perf['losses'];
$winPercentage = $totalMatches > 0 ? ($perf['wins'] / $totalMatches) * 100 : 0;
```

**Grid Data:**
```php
$gridData[] = [
    'league' => [$leagueId],
    'played_gamedays' => $days->count(),
    'match_count' => $perf['match_count'],      // LEAGUE-SPECIFIC
    'league_wins' => $perf['wins'],             // LEAGUE-SPECIFIC
    'league_losses' => $perf['losses'],         // LEAGUE-SPECIFIC
    'win_percentage' => round($winPercentage, 2)
];
```

#### **Simplified: `recalculateLeagueRanks()` method**

**Ranking Hierarchy:**
1. Qualified players first (unchanged)
2. **Win Percentage** (descending)
3. **Total Wins** (tiebreaker #1)
4. **Global Elo** (final tiebreaker)

**Sorting Logic:**
```php
->sort(function($a, $b) {
    // 1. Qualified players first
    if ($a['is_qualified'] && !$b['is_qualified']) return -1;
    if (!$a['is_qualified'] && $b['is_qualified']) return 1;
    
    // 2. Sort by win percentage
    if ($a['win_percentage'] != $b['win_percentage']) {
        return $b['win_percentage'] <=> $a['win_percentage'];
    }
    
    // 3. Tiebreaker: League wins
    if ($a['league_wins'] != $b['league_wins']) {
        return $b['league_wins'] <=> $a['league_wins'];
    }
    
    // 4. Final tiebreaker: Global Elo
    return $b['global_elo'] <=> $a['global_elo'];
})
```

---

### 2. **players.yaml Blueprint**

**Simple fields in `league_stats` grid:**

```yaml
- league (reference)
- played_gamedays
- match_count
- league_wins
- league_losses
- win_percentage
- rank
```

**No complex calculations, no additional metrics.**

---

### 3. **show.antlers.html Template**

**No changes** - Frontend shows:
- Rank
- Player Name + Elo
- Win %
- Record (W/L)
- Qualification Status

Clean and simple.

---

## 📈 Data Structure

### Player Blueprint - `league_stats` Field

```yaml
league_stats:
  - league: [league-id]
    played_gamedays: 12
    match_count: 47              # LEAGUE-SPECIFIC
    league_wins: 23              # LEAGUE-SPECIFIC
    league_losses: 24            # LEAGUE-SPECIFIC
    win_percentage: 48.94        # Simple: wins / (wins + losses) × 100
    rank: 14
```

---

## 🎯 Example Calculations

### Player Stats

**Match Results:**
- Total Matches: 47
- Wins: 23
- Losses: 24

**Calculation:**
```
Win% = (23 / 47) × 100 = 48.94%
```

That's it!

---

## 🔀 Ranking Examples

### Example: Standard Ranking

| Rank | Player | Days | Matches | W-L | Win% |
|------|---------|------|---------|-----|------|
| 🥇 1 | Anna | 10 | 40 | 32-8 | **80.00%** |
| 🥈 2 | Max | 8 | 32 | 24-8 | **75.00%** |
| 🥉 3 | Lisa | 12 | 50 | 35-15 | **70.00%** |
| 4 | Tom | 9 | 36 | 24-12 | **66.67%** |

**Clear, simple, transparent.**

---

### Example: Tiebreaker

Two players with same Win% (rare with 400+ games):

**Player A:**
- Win%: 60.00%
- Total Wins: 30
- Elo: 1520

**Player B:**
- Win%: 60.00%
- Total Wins: 24
- Elo: 1510

**Result:** Player A ranks higher (more total wins)

---

## ⚙️ Key Technical Details

### League-Specific Tracking

**IMPORTANT:** All calculations use **league-specific** data:

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

---

## 🚀 Migration Notes

### From Previous System

If migrating from point differential system:

1. **Old fields are ignored** (total_point_differential, avg_point_differential)
2. **Run finalizeGameday()** on any active gameday to recalculate
3. **Rankings update automatically** based on Win%

---

## ✅ Testing Checklist

- [ ] Create new gameday and generate plan
- [ ] Record match scores
- [ ] Finalize gameday
- [ ] Verify `win_percentage` is calculated correctly
- [ ] Verify league stats are separate from global stats
- [ ] Check league ranking order
- [ ] Test with players in multiple leagues (ensure isolation)

---

## 📝 Formula Summary

```
Win% = (Wins / Total Matches) × 100

Ranking Order:
1. Qualified Status (≥ minimum game days)
2. Win% (descending)
3. Total Wins (descending)
4. Global Elo (descending)
```

**Simple. Transparent. Fair.**

---

## 🎲 Why This System Works

### At Scale (400+ games):
- **Win% is highly reliable** - law of large numbers
- **Tiebreakers rarely needed** - different match counts
- **Easy to understand** - no complex formulas
- **Transparent** - players know exactly where they stand

### For Users:
- ✅ "I have 60% Win Rate" - instantly understandable
- ✅ "I need to win more" - clear action item
- ✅ No confusion about differentials or averages
- ✅ Focus on what matters: winning games

---

## 📦 Files Changed

1. **LeagueService.php** - Simplified ranking logic
2. **players.yaml** - Removed point differential fields
3. **show.antlers.html** - Unchanged (already simple)
4. **CHANGES.md** - This documentation

---

## 💡 Philosophy

**Occam's Razor:** The simplest solution is usually the best.

With 20 gamedays and 400+ total games, Win% alone is sufficient to determine the best player. Additional complexity adds minimal value while making the system harder to understand.

**Result:** A ranking system that's fair, transparent, and easy for everyone to understand.
