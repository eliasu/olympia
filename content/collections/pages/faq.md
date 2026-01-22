---
id: 40b37d94-b73c-45b0-b3cb-5a1d14b71bb4
blueprint: page
title: Faq
updated_by: 1861c4a9-8873-459c-97ce-38d399b7ed46
updated_at: 1769095598
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

**Important:** 
- **Global Elo** is used for pairings (team composition)
- **Win Percentage** determines who wins the league

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

**Ranking Hierarchy:**

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

### 📊 Performance Scenarios

#### **Scenario 1: The Consistent Winner**

**Performance:**
- 10 game days attended
- 40 matches played
- 32 wins, 8 losses

**Stats:**
- Win%: **80.00%**
- Rank: **Top 3**

**Key:** Consistently winning most matches

---

#### **Scenario 2: The Average Player**

**Performance:**
- 12 game days attended
- 50 matches played
- 28 wins, 22 losses

**Stats:**
- Win%: **56.00%**
- Rank: **Middle of pack**

**Key:** Winning slightly more than losing

---

#### **Scenario 3: The Struggling Player**

**Performance:**
- 8 game days attended
- 32 matches played
- 10 wins, 22 losses

**Stats:**
- Win%: **31.25%**
- Rank: **Lower rankings**

**Key:** Need to improve win rate

---

### 🎯 Qualification & Minimum Requirements

**Minimum Requirement:**
- **7 game days** (configurable per league)
- Anyone with less → not ranked (but stats still tracked)

**Why a Minimum?**
```
Without minimum:
- Player comes 2x, gets lucky, 100% Win%
- Never comes again
- Would be "League Champion"

With minimum:
- Must prove consistency over time
- 7+ days = reliable sample size
```

---

### 💡 Strategy Tips for League Success

#### **1. Focus on Winning**
```
Priority #1: Win your matches
- Every win improves your Win%
- Every loss hurts your Win%
- It's that simple!
```

#### **2. Quality Over Quantity**
```
Better: 7 days with 75% Win%
Worse: 15 days with 55% Win%

Reason: System rewards performance, not attendance
```

#### **3. Consistency Matters**
```
Steady performance > Hot streaks
- 70% Win% over 10 days > 90% for 3 days, then 50% for 7 days
- Aim for consistent wins
```

#### **4. Every Match Counts**
```
All matches are equal:
- First or last match of the day
- Against strong or weak opponents (within Elo range)
- Early or late season

Win% treats all equally
```

---

## 🎮 Real-World Examples

### Example 1: "The Elite Player"

**Situation:**
- Attends 10 game days
- Dominates most matches
- Few losses

**Stats:**
- Matches: 40
- Record: 32-8
- Win%: **80.00%**
- **Rank:** 1-2

**Why Top Ranked:**
- Wins 80% of games
- Simple and clear dominance

---

### Example 2: "The Grinder"

**Situation:**
- Attends 15 game days (most in league)
- Many games, but mixed results

**Stats:**
- Matches: 60
- Record: 33-27
- Win%: **55.00%**
- **Rank:** 8-12

**Why Not Higher:**
- Only wins 55% of games
- Attendance doesn't compensate for Win%

---

### Example 3: "The Part-Timer"

**Situation:**
- Only 6 game days attended
- Plays well when present

**Stats:**
- Matches: 24
- Record: 19-5
- Win%: **79.17%**
- **Rank:** None (not qualified)

**Why No Rank:**
- Below minimum 7 game days
- Excellent Win% but need one more day

**Solution:** Come one more day to qualify!

---

### Example 4: "The Comeback Player"

**Situation:**
- First 3 days: struggled (6 wins, 6 losses)
- Next 5 days: improved (18 wins, 2 losses)

**Stats:**
- Total: 8 days, 32 matches
- Record: 24-8
- Win%: **75.00%**
- **Rank:** 2-4

**Key Lesson:**
- Overall Win% is what matters
- Can recover from bad start
- Consistency over time wins

---

## ❓ FAQ (Frequently Asked Questions)

### **Q: Why did I lose Elo points even though I won?**

**A:** Elo and League Ranking are separate!

- **Elo:** Measures expected vs actual performance
- **League Ranking:** Based on Win% (did you win or not?)

**For league ranking:** Win = +1 to wins (helps Win%)

---

### **Q: I attend often, but I'm far back in the ranking. Why?**

**A:** The system rates **Win%**, not attendance.

**Comparison:**
- You: 15 days, 60 matches, 55% Win% → Rank 10
- Anna: 8 days, 32 matches, 75% Win% → Rank 2
- **Anna wins** (better Win%)

**This is fair:** Otherwise part-time players would have no chance.

---

### **Q: Two players have same Win%. Who ranks higher?**

**A:** Very rare with large sample sizes, but tiebreakers exist:

**Tiebreaker Order:**
1. **Total Wins** (more wins = higher rank)
2. **Global Elo** (higher Elo = higher rank)

**Example:**
- Player A: 70% Win%, 35 total wins → Rank 5
- Player B: 70% Win%, 28 total wins → Rank 6

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

**A:** With odd numbers, perfect distribution is impossible.

**Example:** 19 players, 16 matches
- 16 matches × 4 players = 64 slots
- 64 slots / 19 players = 3.37 matches/person

**System Solution:**
- Prioritizes players with fewer games
- In the end: 15 players with 3 matches, 4 with 4 matches

**Fair:** Win% works regardless of match count per day

---

### **Q: Should I skip game days when I'm not feeling 100%?**

**A:** Depends on your goals!

**Analysis:**
```
Good day:  75% Win% contribution
Bad day:   40% Win% contribution

Impact: Bad days lower your overall Win%
```

**Strategy:**
- **Competitive:** Skip if really off your game
- **Casual:** Play anyway, it's about fun!
- **Remember:** Need minimum 7 days to qualify

---

### **Q: Can I "cherry-pick" weak opponents?**

**A:** No, pairing system prevents this.

**Reasons:**
1. **±150 Elo spread** - You only play similarly skilled players
2. **Automatic matching** - You can't choose opponents
3. **Diversity system** - You play different people

**Conclusion:** Just focus on winning your matches!

---

### **Q: Why don't I see a rank even though I have good Win%?**

**A:** You haven't reached the minimum of 7 game days.

**Reason:** Prevents "Lucky Streaks"
- Someone comes 2x, gets lucky, 100% Win%
- Never comes again
- Without minimum: False "champion"

**Solution:** Play at least 7 days, then you'll get your rank!

---

### **Q: My Global Elo is 1600, but I'm only Rank 15. Why?**

**A:** **Global Elo ≠ League Ranking**

- **Global Elo:** Your all-time strength (for pairings)
- **League Ranking:** Your Win% **in this season**

**Possible Reasons:**
- You're winning only 50% in this league
- Others are winning 70-80% in this league
- Elo measures skill, Win% measures results

**Next Season:** Fresh start! League Win% resets.

---

## 🔥 Edge Cases

### Edge Case 1: "Perfect Win Rate"

**Situation:** Player has 100% Win% (very rare long-term).

**System Behavior:**
- Automatically #1 in ranking
- As long as qualified (≥7 days)
- Very difficult to maintain over 20 game days

**Important:** Even 95% Win% is excellent!

---

### Edge Case 2: "Below 50% But Ranked"

**Situation:** Player has 48% Win% but is still ranked.

**Explanation:**
- Player is qualified (≥7 days)
- Other qualified players have similar or worse Win%
- Still competing, just in lower rankings

**Important:** Below 50% = losing more than winning

---

### Edge Case 3: "Identical Win%"

**Situation:** Two players both have exactly 70.00% Win%.

**System Behavior:**
- Next tiebreaker: **Total Wins**
- If still tied: **Global Elo**

**Example:**
```
Anna: 70% Win%, 35 wins, 1550 Elo → Rank 5
Ben:  70% Win%, 35 wins, 1530 Elo → Rank 6

Anna has higher Elo → ranks higher
```

---

### Edge Case 4: "Rapid Improvement"

**Situation:** Player starts poorly but improves dramatically.

**Stats:**
- First 4 days: 10 wins, 10 losses (50% Win%)
- Next 4 days: 16 wins, 4 losses (80% Win%)
- Total: 26 wins, 14 losses

**Result:**
- Overall Win%: 65.00%
- **Fair:** All matches count equally
- Final Win% reflects overall performance

---

## 🎓 Summary for All Players

### What You Need to Know:

1. **Win Percentage Is King**
   - Most important metric
   - Higher Win% = Higher Rank
   - Simple and transparent

2. **Performance > Attendance**
   - 7 great days > 15 mediocre days
   - Win% neutralizes attendance advantage
   - Quality of play determines rank

3. **Every Match Counts Equally**
   - First match = Last match
   - All contribute to Win%
   - No "throwaway" games

4. **Pairing is Fair**
   - ±150 Elo spread → suitable opponents
   - New partners/opponents preferred
   - Power Pairing prevents unfair teams

5. **No Exploits**
   - Can't cherry-pick opponents (system chooses)
   - Can't manipulate Win% (all matches count)
   - Long-term best players win

6. **Each Season is Fresh**
   - Global Elo remains (for skill matching)
   - League Win% resets
   - New chance for everyone!

---

## 📊 Quick Reference

```
PRIMARY METRIC:
Win% = (Wins / Total Matches) × 100

RANKING ORDER:
1. Qualified Status (≥7 game days)
2. Win Percentage (descending)
3. Total Wins (descending)
4. Global Elo (descending)
```

**Simple. Clear. Fair.**

---

## 📞 Questions or Feedback?

For any questions or suggestions about the league system, talk to your league admin!

**Good luck in the season! 🏆**