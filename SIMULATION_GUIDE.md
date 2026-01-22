# Realistic League Simulation with Skill-Based Progression

## Overview

This system allows you to simulate a realistic league where all players start at the same Elo (1500) but progress based on their hidden skill level. This validates whether Pro players naturally rise to the top over time.

## Key Concepts

### 1. **Skill Rating** (Hidden, Simulation Only)
- Each player has a `skill_rating` field that represents their true skill level
- **Beginner**: 1200
- **Intermediate**: 1500  
- **Advanced**: 1600
- **Pro**: 1750
- This field is **only used during simulation** to determine match outcomes
- In real-world scenarios, this field is ignored

### 2. **Global Elo** (Visible, Calculated from Results)
- This is the player's visible rating that changes based on match results
- By default, all players start at **1500 Elo** (equal starting point)
- Elo changes are calculated from actual match results using standard Elo formulas

### 3. **Player Names**
- Players keep skill-based last names (e.g., "John Pro", "Sarah Beginner")
- This allows you to visually track how different skill levels progress over time

## Usage

### Realistic Simulation (Equal Start, Skill-Based Progression)

This is the **default** and **recommended** approach for validating skill progression:

```bash
# 1. Clean slate
php artisan delete:matches
php artisan delete:gamedays
php artisan delete:players

# 2. Create players (all start at 1500 Elo)
php artisan create:players --beginners=3 --intermediates=6 --advanced=8 --pros=5

# 3. Create gamedays
php artisan create:gamedays btv-new --count=16

# 4. Simulate league
php artisan simulate:league btv-new
```

**What happens:**
- All players start at 1500 Elo
- During simulation, match outcomes are determined by `skill_rating`
- Pro players (skill 1750) will win more often than Beginners (skill 1200)
- Over time, Pro players' Elo will rise and Beginners' will fall
- You can validate that skill-based progression works correctly

### Legacy Mode (Skill-Based Starting Elo)

If you want players to start with different Elos based on their skill level (old behavior):

```bash
# Create players with skill-based starting Elo
php artisan create:players --beginners=3 --pros=5 --use-skill-elo

# Then simulate as normal
php artisan create:gamedays btv-new --count=16
php artisan simulate:league btv-new
```

**What happens:**
- Beginners start at ~1200 Elo
- Intermediates start at ~1500 Elo
- Advanced start at ~1600 Elo
- Pros start at ~1750 Elo
- Simulation still uses `skill_rating` for match outcomes

## How Simulation Works

### Match Outcome Calculation

1. **Get Team Skills**: Average the `skill_rating` of each team's players
2. **Calculate Win Probability**: Use Elo formula with skill ratings
   ```
   P(A wins) = 1 / (1 + 10^((SkillB - SkillA) / 400))
   ```
3. **Determine Winner**: Random roll weighted by probability
4. **Generate Score**: More dominant teams (higher probability) tend to win by larger margins

### Elo Calculation (After Match)

After a match is played, Elo is calculated using:
- **Current global_elo** of each player
- **Actual match result** (who won)
- Standard Elo K-factor and formulas

The `skill_rating` is **never** used for Elo calculation - only for determining match outcomes during simulation.

## Real-World Usage

When you have real players and real match results:

1. Create players without skill ratings (or ignore the field)
2. Enter actual match scores manually
3. Elo is calculated purely from results and current Elo
4. The `skill_rating` field is ignored

## Validation Example

To validate that Pro players develop better:

```bash
# Create equal-start league
php artisan delete:matches && php artisan delete:gamedays && php artisan delete:players
php artisan create:players --beginners=5 --intermediates=5 --advanced=5 --pros=5
php artisan create:gamedays btv-new --count=20
php artisan simulate:league btv-new

# Check the leaderboard
# Expected: Pro players should be at the top, Beginners at the bottom
```

## Command Reference

### `create:players`

```bash
php artisan create:players [options]

Options:
  --beginners=N       Number of beginner players (skill 1200)
  --intermediates=N   Number of intermediate players (skill 1500)
  --advanced=N        Number of advanced players (skill 1600)
  --pros=N            Number of pro players (skill 1750)
  --use-skill-elo     Start players at skill-based Elo instead of 1500
```

**Default behavior**: All players start at 1500 Elo with hidden skill ratings

### `simulate:league`

```bash
php artisan simulate:league {league-slug}
```

Simulates all unfinished gamedays for a league:
1. Generates match plans
2. Fills scores based on skill ratings
3. Calculates Elo from results

## Benefits of This Approach

✅ **Realistic Progression**: See how players naturally separate by skill over time  
✅ **Equal Start**: Fair starting point for all players  
✅ **Validation**: Prove that your Elo system correctly identifies skill  
✅ **Backward Compatible**: Old simulations still work with `--use-skill-elo`  
✅ **Real-World Ready**: Skill ratings are ignored for real match data  

## Technical Details

### Player Data Structure

```yaml
title: "John Pro"
global_elo: 1500.0          # Visible Elo (changes with results)
skill_rating: 1750.0        # Hidden skill (used only in simulation)
total_games: 0
wins: 0
losses: 0
```

### Files Modified

- `app/Console/Commands/CreatePlayers.php` - Added skill_rating field
- `app/Console/Commands/FillScores.php` - Uses skill_rating for simulation
