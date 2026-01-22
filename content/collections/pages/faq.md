---
id: 40b37d94-b73c-45b0-b3cb-5a1d14b71bb4
blueprint: page
title: Faq
updated_by: 1861c4a9-8873-459c-97ce-38d399b7ed46
updated_at: 1769118488
template: faq
headline: 'How to BTV League'
subheadline: 'Complete explanation of Elo ratings and league rankings.'
---
## 🎯 Overview

### What is the Goal?
Our league system measures **your actual performance**, not just how often you show up. A player who attends 5 days and wins 80% of their games can beat a player who attended 12 days but only won 55%.

### Two Separate Ratings:

| Rating | Purpose | Calculation |
|---------|-------|------------|
| **Global Elo** | All-time Strength | Permanent, across all leagues |
| **League Ranking** | Season Rating | Per league, reset each season |
| **Gameday Winner** | Daily Champion | Best performance on a single day |

**Important:** 
- **Global Elo** is used for pairings (team composition)
- **Win Percentage** determines who wins the league
- **Gameday Winner** is for daily bragging rights (doesn't affect season ranking)

---

## ⚖️ Elo Rating System

### What is Elo?

Elo is a mathematical system originally developed for chess. It measures your relative playing strength compared to other players.

**Starting Value:** 1500 Elo (every new player)

### How Does My Elo Change?

After each match, your Elo is adjusted based on:
1. **Expected Performance** (based on team Elos)
2. **Actual Performance** (score of the game)
3. **K-Factor** (sensitivity of change, standard: 32)

### Formula (for those interested):

```
Team Elo = Average of 2 players' Elos
Expected = 1 / (1 + 10^((Opponent Elo - Your Team Elo) / 400))
Actual = Your Points / (Your Points + Opponent Points)
Delta = K-Factor × (Actual - Expected)
```

### 💰 How Much Elo Do I Gain/Lose?

#### **Scenario 1: Evenly Matched Game (both teams ~1500)**

| Score | Your Gain/Loss |
|-------|---------------------|
| 21:20 | ±0.8 Elo |
| 21:18 | ±2.1 Elo |
| 21:14 | ±5.3 Elo |
| 21:10 | ±8.0 Elo |

**Conclusion:** With equally strong teams, you gain little, lose little.

#### **Scenario 2: You Are the Favorite (+100 Elo Advantage)**

| Result | Score | Your Gain/Loss |
|----------|-------|---------------------|
| You win narrowly | 21:18 | **-3.8 Elo** ❌ (too close!) |
| You win clearly | 21:10 | **+2.1 Elo** ✅ |
| You lose | 18:21 | **-8.0 Elo** 💥 |

**Conclusion:** As the favorite, you must win clearly, otherwise you lose points!

#### **Scenario 3: You Are the Underdog (-100 Elo Disadvantage)**

| Result | Score | Your Gain/Loss |
|----------|-------|---------------------|
| You lose narrowly | 18:21 | **+3.8 Elo** ✅ (good performance!) |
| You lose badly | 10:21 | **-2.1 Elo** (ok) |
| You win | 21:18 | **+8.0 Elo** 🚀 (Upset!) |

**Conclusion:** As the underdog, you can gain points even in defeat!

### 🎯 Important Principles:

1. **Score Difference Counts:** 21:10 is better than 21:18
2. **No Win Protection:** Winning doesn't guarantee points
3. **Honest System:** Favorite wins against weak teams yield little
4. **Upsets Are Rewarded:** Surprise wins yield many points

### 📈 Elo Development Over Time

**Typical Elo Ranges After 20 Game Days:**

| Skill Level | Elo Range | Description |
|-------------|-----------|--------------|
| **Elite** | 1650-1800+ | Top 5%, dominates strongly |
| **Advanced** | 1550-1650 | Top 20%, very good |
| **Upper Mid** | 1500-1550 | Upper midfield |
| **Average** | 1450-1500 | Average |
| **Lower Mid** | 1400-1450 | Lower midfield |
| **Beginner+** | 1350-1400 | Developing |
| **Beginner** | 1200-1350 | Still learning |

**Example Career:**
```
Start:         1500 Elo
After 5 days:  1540 Elo (+8 Elo/day, good player)
After 10 days: 1580 Elo
After 20 days: 1660 Elo (stabilizes at true level)
```

---

## 🎲 Pairing System (Matchmaking)

### Goals of Pairing:
1. **Skill-based:** You play with/against similarly strong players
2. **Diversity:** You play with different partners and against different opponents
3. **Fairness:** Everyone gets a similar number of matches per game day

### How Does It Work?

#### **Step 1: Elo Band (±150 Elo)**

The system searches for 3 partners within **your Elo ±150**.

**Example:**
- Your Elo: 1600
- Possible Partners: 1450-1750

**Why ±150?**
- At ±150 Elo = ~30% skill difference in 1v1
- In doubles this balances out through Power Pairing
- **Prevents:** 1700 vs 1300 games (frustrating for both)

#### **Step 2: Diversity Prioritization**

The system prefers **new partners and opponents**.

**Example Game Day:**
```
Match 1: You play with Player A, against Players B & C
Match 2: System prefers Players D, E, F (all new for you)
Match 3: Only if necessary: System takes Players A-C again
```

**Weighting:**
- Same partner again? → System strongly avoids (Penalty: 1000)
- Same opponent again? → System moderately avoids (Penalty: 500)

#### **Step 3: Power Pairing (Team Balance)**

When 4 players are selected, they are divided as follows:

```
4 players sorted by Elo: [1650, 1580, 1520, 1480]

Team A: Strongest + Weakest = 1650 + 1480 = Avg 1565
Team B: Middle Two = 1580 + 1520 = Avg 1550

→ Difference: only 15 Elo! (very balanced)
```

**Why Power Pairing?**
- Prevents "Dream Teams" (both strong players together)
- Gives weaker players a chance to learn
- Makes games more exciting

### 🎮 Example Game Day (19 Players, 16 Matches)

**Player Elos:**
```
1700, 1680, 1650, 1620, 1600, 1580, 1560, 1540, 1520, 1500,
1480, 1460, 1440, 1420, 1400, 1380, 1360, 1340, 1320
```

**Match 1:** (System selects the 4 with fewest games today = all 0)
- Seed: Player #1 (1700)
- Search: 1550-1850 Elo
- Find: #2 (1680), #4 (1620), #6 (1580)
- Teams: [1700+1580] vs [1680+1620] → Avg: 1640 vs 1650 ✅

**Match 2:** (Players 1,2,4,6 now have 1 game, rest have 0)
- Seed: Player #3 (1650, 0 games)
- Search: 1500-1800, prefers **not** #1,2,4,6
- Find: #5 (1600), #7 (1560), #8 (1540)
- Teams: [1650+1540] vs [1600+1560] → Avg: 1595 vs 1580 ✅

**Match 5:** (Player #1 plays again)
- Seed: Player #1 (1700, 1 game)
- Search: 1550-1850, **prefers new players**
- System avoids #2,4,6 (were in Match 1)
- Find: #3 (1650), #5 (1600), #9 (1520)
- Teams: [1700+1520] vs [1650+1600] → Avg: 1610 vs 1625 ✅

**Result After 16 Matches:**
- Top player (1700): 3-4 matches, always with 1550-1700 range
- Mid player (1500): 3-4 matches, with 1350-1650 range
- Weak player (1320): 3 matches, with 1170-1470 range

✅ **Nobody plays against completely mismatched opponents!**

---

## 🏆 League Ranking System

### How Are Players Ranked?

**Simple:** Win Percentage

```
Win% = (Wins / Total Matches) × 100
```

That's it. The player with the highest Win% wins the league.

### Why Win Percentage?

#### **Problem with Total Points:**
```
Player A: 5 days, 20 wins, 5 losses → 80% Win
Player B: 15 days, 45 wins, 30 losses → 60% Win

Player B has more total wins, but Player A performs better!
```

#### **Solution: Win Percentage**
```
Player A: 80% Win% → Rank 1 ✅
Player B: 60% Win% → Rank 2

Fair: Performance matters, not just attendance
```

### 📊 Win Percentage Explained

**Simple Calculation:**
- Count your wins
- Count your losses
- Win% = Wins / (Wins + Losses) × 100

**Example:**

| Matches Played | Wins | Losses | Win% |
|----------------|------|--------|------|
| 50 | 35 | 15 | **70.00%** |
| 40 | 32 | 8 | **80.00%** |
| 30 | 15 | 15 | **50.00%** |

### 🎯 Complete Ranking Order

**Season Ranking Hierarchy:**

1. **Qualified players first** (≥ minimum game days)
2. **Win Percentage** (descending)
3. **Total Wins** (tiebreaker - descending)
4. **Global Elo** (final tiebreaker - descending)

**Example Ranking Table:**

| Rank | Player | Days | Matches | W-L | Win% | Status |
|------|---------|------|---------|-----|------|--------|
| 🥇 1 | Anna Schmidt | 10 | 40 | 32-8 | **80.00%** | Qualified |
| 🥈 2 | Max Weber | 8 | 32 | 24-8 | **75.00%** | Qualified |
| 🥉 3 | Lisa Müller | 12 | 50 | 35-15 | **70.00%** | Qualified |
| 4 | Tom Fischer | 9 | 36 | 24-12 | **66.67%** | Qualified |
| 5 | Ben Klein | 7 | 28 | 18-10 | **64.29%** | Qualified |
| - | Sarah Lang | 5 | 20 | 16-4 | **80.00%** | ⚠️ Not qualified |

**Note:** Sarah has excellent Win% but hasn't played enough game days to qualify!

### 💡 What This Means for You

#### **1. Winning Is Everything**
```
Focus: Win your matches
Result: High Win% = High Rank
```

Simple as that!

#### **2. Sample Size Doesn't Matter**
```
Player A: 5 days, 20 matches, 80% Win%
Player B: 15 days, 60 matches, 60% Win%

Player A ranks higher (better performance)
```

Win% removes the "attendance advantage"

#### **3. Every Match Counts Equally**
```
Match 1 of the season = Match 50 of the season
First match of the day = Last match of the day

All wins count the same toward your Win%
```

---

## 🏅 Gameday Winner System

### What is the Gameday Winner?

Every gameday has its own **daily champion** based on that day's performance only!

**Purpose:**
- Adds excitement to every single gameday
- Recognition for great daily performance
- Bragging rights for the day
- **Does NOT affect season rankings** (separate system)

### How is the Gameday Winner Determined?

**Ranking Criteria (calculated when gameday is finalized):**

1. **Win% of the Day** (primary)
   ```
   Gameday Win% = Wins Today / Matches Today × 100
   ```

2. **Elo Gain of the Day** (tiebreaker)
   ```
   Elo Gain = Ending Elo - Starting Elo
   ```

3. **Total Wins of the Day** (tiebreaker)

4. **Global Elo** (final tiebreaker)

### 📊 Example Gameday Ranking

**Gameday: Sunday League - February 16, 2025**

| Rank | Player | Matches | W-L | Win% | Elo Gain |
|------|---------|---------|-----|------|----------|
| 🥇 1 | Max Weber | 4 | 3-1 | **75.00%** | +8.5 |
| 🥈 2 | Anna Schmidt | 4 | 3-1 | **75.00%** | +6.2 |
| 🥉 3 | Lisa Müller | 3 | 2-1 | **66.67%** | +4.1 |
| 4 | Tom Fischer | 4 | 2-2 | **50.00%** | +2.3 |
| 5 | Ben Klein | 3 | 1-2 | **33.33%** | -1.5 |

**Winner: Max Weber** 🏆
- Same Win% as Anna (75%)
- But higher Elo Gain (+8.5 vs +6.2)
- Dominated his wins more

### 🎯 Key Points About Gameday Winner

**Important to understand:**

1. **Separate from Season Ranking**
   - Gameday Winner = Best performance TODAY
   - Season Ranking = Best performance OVERALL
   - Winning a gameday doesn't affect your season rank

2. **Fair with Different Match Counts**
   - Uses Win% (not total wins)
   - 3 games, 100% Win = better than 4 games, 75% Win
   - Everyone has equal chance

3. **Calculated Automatically**
   - System calculates when gameday is finalized
   - Based on actual Elo changes from that day
   - No manual input needed

4. **Motivation & Recognition**
   - Adds excitement to each gameday
   - Even if you're not #1 in season, you can win a day
   - Fresh competition every gameday

### 💡 Strategy for Winning a Gameday

**How to become Gameday Winner:**

1. **Win Most of Your Matches**
   - Primary metric is Win% of the day
   - 3-1 record better than 2-2

2. **Win Decisively**
   - High wins = more Elo gain
   - 21-10 better than 21-19
   - Acts as tiebreaker

3. **Play Consistently**
   - All matches count toward daily Win%
   - One bad loss won't ruin your day

4. **Don't Worry About Others**
   - Focus on your own performance
   - Can't control how many games others play

### 📈 Gameday Winner vs Season Champion

**Example: Two Different Winners**

**Max's Season:**
- 10 game days attended
- 40 total matches: 32-8
- **Season Win%: 80%** → Rank 1 🏆

**Anna's Season:**
- 12 game days attended
- 50 total matches: 35-15
- **Season Win%: 70%** → Rank 3

**But on February 16:**
- Max: 4 games, 2-2, 50% Win → Rank 8 (bad day)
- Anna: 4 games, 4-0, 100% Win → **Gameday Winner!** 🥇

**Both can celebrate:**
- Max = Season Champion (overall best)
- Anna = Gameday Winner (best that day)

### 🎲 Real-World Scenarios

#### **Scenario 1: "The Comeback"**

**Player:** Ben
- Season Ranking: #12 (struggling)
- Gameday Performance: 4-0, +12 Elo
- **Result:** Gameday Winner! 🏆

**What it means:**
- Still ranked #12 in season
- But won the day
- Proof he's improving
- Motivation boost!

---

#### **Scenario 2: "Bad Day at the Top"**

**Player:** Lisa (Season #2)
- Usually dominant
- Today: 1-3 record, -8 Elo
- **Result:** Rank 15 for the day

**What it means:**
- Season rank unchanged (#2)
- Just had an off day
- Tomorrow is a fresh start
- Still in great position overall

---

#### **Scenario 3: "The Consistent Grinder"**

**Player:** Tom
- Gameday: 3-1, +6 Elo, 75% Win
- Another player: 3-1, +8 Elo, 75% Win
- **Result:** Rank 2 for the day (Elo tiebreaker)

**What it means:**
- Close competition!
- Slightly less dominant wins
- Still excellent performance
- One of the top performers

---

### 🎯 Qualification & Minimum Requirements

**For Season Ranking:**
- **Minimum:** 7 game days (configurable per league)
- Anyone with less → not ranked (but stats still tracked)

**For Gameday Winner:**
- **No minimum!** Everyone who plays is eligible
- Only need to play at least 1 match
- Fair chance every single day

**Why No Minimum for Gameday?**
```
Season ranking needs consistency (7+ days)
Gameday winner is about TODAY only
New players can win their first day!
```

---

### 💡 Strategy Tips

#### **For Season Success:**

1. **Focus on Win%**
   - Most important: win your matches
   - Attendance matters but performance matters more
   - 7 great days > 15 mediocre days

2. **Consistency Over Time**
   - Steady 70% Win% beats streaky performance
   - Every match counts equally
   - No "throwaway" games

3. **Quality Over Quantity**
   ```
   Better: 8 days, 75% Win%
   Worse: 15 days, 55% Win%
   ```

#### **For Gameday Winner:**

1. **Play Your Best Today**
   - Focus on THIS gameday only
   - Yesterday doesn't matter
   - Tomorrow doesn't matter

2. **Win Decisively When Possible**
   - High wins boost Elo gain
   - Acts as tiebreaker
   - Show dominance

3. **Stay Consistent**
   - Win most of your matches today
   - Even 3-1 can win the day
   - Don't need perfection

---

## 🎮 Real-World Examples

### Example 1: "The Elite Performer"

**Season Performance:**
- Attends 10 game days
- 40 matches: 32-8
- Win%: **80.00%**
- **Season Rank:** 1 🥇

**Typical Gameday:**
- 4 matches: 3-1
- Win%: 75%
- Elo Gain: +8.2
- **Gameday Rank:** 1-3 (usually wins)

**Result:** Dominates both season and most gamedays

---

### Example 2: "The Improving Player"

**Season Performance:**
- Attends 8 game days
- 32 matches: 18-14
- Win%: **56.25%**
- **Season Rank:** 9

**One Special Gameday:**
- 4 matches: 4-0 (perfect!)
- Win%: 100%
- Elo Gain: +15.3
- **Gameday Rank:** 1 🥇 (Winner!)

**Result:** Not season champion, but won a day!

---

### Example 3: "The Streaky Player"

**Season Performance:**
- Attends 12 game days
- Some excellent, some poor
- Win%: **58.33%**
- **Season Rank:** 7

**Gameday Variation:**
- Good days: Gameday Winner (2x)
- Bad days: Rank 12-15 (5x)
- Average days: Rank 4-8 (5x)

**Result:** Inconsistent but exciting to watch

---

## ❓ FAQ (Frequently Asked Questions)

### **Q: Why did I lose Elo points even though I won?**

**A:** Elo and League Ranking are separate!

- **Elo:** Measures expected vs actual performance
- **League Ranking:** Based on Win% (did you win or not?)
- **Gameday Winner:** Best performance today

**For league ranking:** Win = +1 to wins (helps Win%)

---

### **Q: I won the Gameday but I'm still ranked low in the season. Why?**

**A:** Gameday Winner and Season Ranking are **completely separate**!

**Gameday Winner:**
- Best performance TODAY
- Resets every gameday
- Instant gratification

**Season Ranking:**
- Best performance over ENTIRE season
- Cumulative over all gamedays
- Requires consistency

**Think of it like:**
- Gameday = Winning a stage in Tour de France
- Season = Winning the overall Tour de France

You can win stages without winning the tour!

---

### **Q: Can I win a Gameday even if I play fewer matches than others?**

**A:** Yes! That's why we use Win%.

**Example:**
- You: 3 matches, 3-0 = **100% Win** → Rank 1 🏆
- Other player: 4 matches, 3-1 = **75% Win** → Rank 2

Win% makes it fair regardless of match count.

---

### **Q: Two players have same Win% on gameday. Who wins?**

**A:** **Elo Gain** is the tiebreaker.

**Example:**
- Player A: 75% Win, +8.5 Elo → Rank 1
- Player B: 75% Win, +6.2 Elo → Rank 2

Higher Elo gain = more dominant wins = better rank

---

### **Q: I attend often, but I'm far back in season ranking. Why?**

**A:** The system rates **Win%**, not attendance.

**Comparison:**
- You: 15 days, 60 matches, 55% Win% → Rank 10
- Anna: 8 days, 32 matches, 75% Win% → Rank 2
- **Anna wins** (better Win%)

**This is fair:** Otherwise part-time players would have no chance.

---

### **Q: Does winning Gameday help my season ranking?**

**A:** Indirectly, yes!

**Direct Impact:**
- Gameday Winner doesn't add bonus points
- Doesn't directly change season rank

**Indirect Impact:**
- Winning gameday = you won most matches that day
- Winning matches = improves your season Win%
- Better Win% = better season rank

**So:** Play well → win gameday → improve season rank naturally!

---

### **Q: Why do I never play with the best/worst players?**

**A:** The system maintains a ±150 Elo spread.

**Example:**
- You: 1500 Elo
- Player A: 1720 Elo (Top)
- Player B: 1280 Elo (Beginner)

**Difference to A:** 220 > 150 → no pairing  
**Difference to B:** 220 > 150 → no pairing

**You play with:** 1350-1650 range (your level)

**Advantages:**
- Fair games
- Top players don't frustrate beginners
- You learn against similarly strong players

---

### **Q: I had 3 matches today, others had 4. Unfair?**

**A:** No! That's why we use Win%.

**For Season Ranking:**
- Your season Win% = all your matches
- 3 vs 4 games doesn't matter over time

**For Gameday Winner:**
- We use Win% of the day
- 3 games, 100% = better than 4 games, 75%
- Completely fair!

---

### **Q: Should I skip game days when I'm not feeling 100%?**

**A:** Depends on your goals!

**For Season Ranking:**
```
Bad day: Lowers your overall Win%
Strategy: Skip if really off your game
But: Need minimum 7 days to qualify
```

**For Gameday Winner:**
```
Bad day: Just won't win that day
Strategy: Play anyway, fresh start next time
Benefit: More practice = improvement
```

**Our advice:** Play! It's about fun and improvement.

---

### **Q: Can I see my Gameday Winner history?**

**A:** Yes! Check each finished gameday.

**Where to find:**
- Go to past gamedays
- Click on a finished gameday
- See "Gameday Winner" section
- Your rank is shown there

**Track your progress:**
- How many gamedays won?
- Average gameday rank?
- Improvement over time?

---

### **Q: What's more important: Gameday Winner or Season Rank?**

**A:** **Season Rank** is the main championship.

**Priority:**
1. **Season Ranking** = Overall Champion
2. **Gameday Winner** = Daily Achievement

**Think of it like:**
- Season = Winning the League
- Gameday = Winning Match of the Week

Both are great, but season title is the ultimate goal!

---

## 🎓 Summary for All Players

### What You Need to Know:

1. **Win Percentage Is King (Season)**
   - Most important metric for season ranking
   - Higher Win% = Higher Season Rank
   - Simple and transparent

2. **Gameday Winner Is Daily Fun**
   - Best performance each gameday
   - Separate from season ranking
   - Fresh competition every time
   - Everyone has a chance

3. **Performance > Attendance**
   - Season: 7 great days > 15 mediocre days
   - Gameday: Win% today matters
   - Quality of play determines success

4. **Every Match Counts**
   - All matches contribute to season Win%
   - All matches contribute to gameday Win%
   - No "throwaway" games

5. **Pairing is Fair**
   - ±150 Elo spread → suitable opponents
   - New partners/opponents preferred
   - Power Pairing prevents unfair teams

6. **Two Ways to Win**
   - Season Champion = Best overall (Win%)
   - Gameday Winner = Best single day
   - Both are achievements to celebrate!

7. **Each Season is Fresh**
   - Global Elo remains (for skill matching)
   - Season Win% resets
   - Gameday Winners start fresh each day
   - New chance for everyone!

---

## 📊 Quick Reference

### Season Ranking:
```
PRIMARY METRIC:
Win% = (Wins / Total Matches) × 100

RANKING ORDER:
1. Qualified Status (≥7 game days)
2. Win Percentage (descending)
3. Total Wins (descending)
4. Global Elo (descending)
```

### Gameday Winner:
```
PRIMARY METRIC:
Gameday Win% = Wins Today / Matches Today × 100

RANKING ORDER:
1. Win% of the Day (descending)
2. Elo Gain of the Day (descending)
3. Total Wins of the Day (descending)
4. Global Elo (descending)
```

**Simple. Clear. Fair. Fun!**

---

## 📞 Questions or Feedback?

For any questions or suggestions about the league system, talk to your league admin!

**Good luck in the season! 🏆**

**And remember: Every gameday is a new chance to be the daily champion! 🏅**