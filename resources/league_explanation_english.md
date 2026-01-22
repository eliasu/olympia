# 🏆 BTV League Explanation

## 📖 Table of Contents
1. [Overview](#overview)
2. [Elo Rating System](#elo-rating-system)
3. [Pairing System (Matchmaking)](#pairing-system)
4. [League Ranking (LPI)](#league-ranking)
5. [Example Scenarios](#example-scenarios)
6. [Frequently Asked Questions (FAQ)](#faq)
7. [Edge Cases](#edge-cases)
8. [Summary](#summary)

---

## 🎯 Overview

### What is the Goal?
Our league system measures **your actual performance**, not just how often you show up. A player who attends 5 days and consistently plays well can beat a player who attended 12 days but performed weaker.

### Two Separate Ratings:

| Rating | Purpose | Calculation |
|---------|-------|------------|
| **Global Elo** | All-time Strength | Permanent, across all leagues |
| **League Performance Index (LPI)** | Season Rating | Per league, reset each season |

**Important:** 
- **Global Elo** is used for pairings (team composition)
- **LPI** determines who wins the league

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
| 11:10 | ±0.6 Elo |
| 11:9 | ±1.6 Elo |
| 11:7 | ±4.0 Elo |
| 11:5 | ±6.1 Elo |

**Conclusion:** With equally strong teams, you gain little, lose little.

#### **Scenario 2: You Are the Favorite (+100 Elo Advantage)**

| Result | Score | Your Gain/Loss |
|----------|-------|---------------------|
| You win narrowly | 11:9 | **-2.9 Elo** ❌ (too close!) |
| You win clearly | 11:5 | **+1.6 Elo** ✅ |
| You lose | 9:11 | **-6.1 Elo** 💥 |

**Conclusion:** As the favorite, you must win clearly, otherwise you lose points!

#### **Scenario 3: You Are the Underdog (-100 Elo Disadvantage)**

| Result | Score | Your Gain/Loss |
|----------|-------|---------------------|
| You lose narrowly | 9:11 | **+2.9 Elo** ✅ (good performance!) |
| You lose badly | 5:11 | **-1.6 Elo** (ok) |
| You win | 11:9 | **+6.1 Elo** 🚀 (Upset!) |

**Conclusion:** As the underdog, you can gain points even in defeat!

### 🎯 Important Principles:

1. **Score Difference Counts:** 11:5 is better than 11:9
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

## 🏆 League Ranking (LPI System)

### What is the League Performance Index (LPI)?

**LPI = Average Elo Points per Game Day**

**Formula:**
```
LPI = Sum of All Elo Deltas / Number of Game Days
```

### Why LPI Instead of Total Points?

**Problem with Total Points:**
```
Player A: 5 days, +90 points → Loses
Player B: 12 days, +144 points → Wins

But: Player A has better performance per day! (+18 vs +12)
```

**Solution with LPI:**
```
Player A: +90 / 5 = +18.0 LPI → Wins! ✅
Player B: +144 / 12 = +12.0 LPI
```

### 📊 LPI Calculation Example

**Player: Max Müller**

| Game Day | Matches | Elo Deltas | Day Sum |
|----------|---------|------------|-------------|
| Day 1 | 3 Matches | +2.5, -1.2, +3.8 | +5.1 |
| Day 2 | 4 Matches | +1.0, +2.2, -0.5, +4.1 | +6.8 |
| Day 3 | 3 Matches | -2.1, +5.5, +1.8 | +5.2 |
| Day 4 | 4 Matches | +3.2, +0.8, +2.9, +1.5 | +8.4 |
| Day 5 | 3 Matches | +4.2, -1.1, +2.6 | +5.7 |

**Calculation:**
```
Total Points: +5.1 +6.8 +5.2 +8.4 +5.7 = +31.2
Game Days: 5
LPI = 31.2 / 5 = +6.24 points per day
```

**Visible in Dashboard:**
- `league_performance`: +31.2 (Total sum)
- `played_gamedays`: 5 (Number of game days)
- `average_delta`: +1.88 (Avg per match)
- **LPI (for ranking)**: +6.24

### 🎯 Qualification & Ranking

**Minimum Requirement:**
- **7 game days** (configurable in league settings)
- Anyone with less → not in ranking

**Ranking Order:**
1. Qualified players (≥7 days), sorted by LPI
2. Non-qualified players, sorted by LPI (without rank)

**Example Table:**

| Rank | Player | Game Days | Total Points | LPI | Status |
|------|---------|-----------|---------------|-----|--------|
| 🥇 1 | Anna Schmidt | 10 | +95.5 | **+9.55** | Qualified |
| 🥈 2 | Max Müller | 7 | +52.8 | **+7.54** | Qualified |
| 🥉 3 | Lisa Weber | 12 | +84.0 | **+7.00** | Qualified |
| 4 | Tom Fischer | 8 | +48.2 | **+6.03** | Qualified |
| - | Ben Klein | 5 | +35.0 | +7.00 | ⚠️ Not qualified |
| - | Sarah Lang | 4 | +28.5 | +7.13 | ⚠️ Not qualified |

**Note:** Ben and Sarah have good LPIs, but not enough game days!

### 💡 Strategy Tips for League Success

1. **Consistency > Attendance**
   - Better 7 days with +8 LPI than 15 days with +4 LPI
   
2. **Quality of Wins**
   - Clear wins against good opponents yield the most
   - Close wins against weak opponents can be negative
   
3. **Meet Minimum Days**
   - Only from 7 days onwards are you in the ranking
   - After that: Performance > Attendance

4. **Long-term Thinking**
   - Your Global Elo rises/falls to your true level long-term
   - League Performance is short-term (reset each season)

---

## 📋 Example Scenarios

### Scenario 1: "The Part-Time Pro"

**Situation:**
- Anna comes only 7x (minimum requirement)
- Plays consistently very well
- Global Elo: 1650 (strong)

**Performance:**
```
7 game days × Avg +9 Elo/day = +63 total points
LPI = +63 / 7 = +9.0
```

**Result:** ✅ Rank 1-3 possible despite minimal attendance!

---

### Scenario 2: "The Diligent Average"

**Situation:**
- Ben comes 15x (very often there)
- Plays average
- Global Elo: 1500 (midfield)

**Performance:**
```
15 game days × Avg +4 Elo/day = +60 total points
LPI = +60 / 15 = +4.0
```

**Result:** ⚠️ Despite more total points → only midfield (Rank 10-15)

---

### Scenario 3: "The Rising Star"

**Situation:**
- Lisa starts at 1450 Elo (weak)
- Improves strongly over the season
- Ends at 1580 Elo (good)

**Performance:**
```
First 5 days: Avg +10 Elo/day (rapid improvement)
Next 7 days: Avg +5 Elo/day (stabilizes)
12 game days, +85 total points
LPI = +85 / 12 = +7.08
```

**Result:** ✅ Top 5 despite weak start!

---

### Scenario 4: "The Bad Luck Streak"

**Situation:**
- Tom is actually strong (1620 Elo)
- Has 3 game days with bad luck/bad partners
- Loses -15 points in 3 days

**Performance:**
```
Bad 3 days: -15 points
Good 7 days: +60 points
10 game days, +45 total points
LPI = +45 / 10 = +4.5
```

**Result:** ⚠️ Bad luck streaks hurt the LPI, but recovers over time

**Important:** System measures average → individual bad days don't weigh as heavily

---

### Scenario 5: "The Sandbagger"

**Situation:**
- Someone tries to intentionally play poorly to get weaker opponents
- Loses first 3 days intentionally (-30 Elo)
- Then "wake up" and dominate

**Why this doesn't work:**
```
3 bad days: -30 points (LPI: -10)
7 good days: +70 points (LPI: +10)
10 days total: +40 points
LPI = +40 / 10 = +4.0 (only midfield!)
```

**Result:** ❌ Sandbagging massively hurts your LPI
**Reason:** Every day counts equally → bad days can't be "made up"

---

## ❓ FAQ (Frequently Asked Questions)

### **Q: Why did I lose points even though I won?**

**A:** You were the favorite and won too narrowly.

**Example:**
- Your team: Avg 1600 Elo
- Opponent: Avg 1500 Elo
- Expected: 11:7 win
- Actual: 11:9 win
- **Result: -2 Elo** (worse performance than expected)

**Solution:** As the favorite, you must win clearly!

---

### **Q: I attend often, but I'm far back in the ranking. Why?**

**A:** The system rates **performance per day**, not total attendance.

**Comparison:**
- You: 15 days, +45 points → LPI: +3.0
- Anna: 8 days, +64 points → LPI: +8.0
- **Anna wins** (better performance)

**This is fair:** Otherwise part-time players would have no chance.

---

### **Q: Why do I never play with the best/worst players?**

**A:** The system maintains a ±150 Elo spread.

**Example:**
- You: 1500 Elo
- Player A: 1720 Elo (Top)
- Player B: 1280 Elo (Beginner)

**Difference to A:** 220 > 150 → no pairing
**Difference to B:** 220 > 150 → no pairing

**You play with:** 1350-1650 range (your league)

**Advantages:** 
- Fair games
- Top players don't frustrate beginners
- You learn against similarly strong players

---

### **Q: I had 3 matches today, others had 4. Unfair?**

**A:** With an odd number of players, it's mathematically impossible to distribute equally.

**Example:** 19 players, 16 matches
- 16 matches × 4 players = 64 slots
- 64 slots / 19 players = 3.37 matches/person

**System Solution:**
- Prioritizes players with fewer games
- Prioritizes players with less league experience
- In the end: 15 players with 3 matches, 4 players with 4 matches

**LPI balances it out:** Your average is calculated over days, not matches

---

### **Q: Can I "farm" my Elo against weak players?**

**A:** No, for two reasons:

1. **Pairing System:** You only play against ±150 Elo (not against much weaker players)
2. **Diminishing Returns:** Wins against weaker players yield hardly any points

**Example:**
- You (1600) vs Weaker (1500)
- Win 11:5 → only +1.6 Elo
- 10 such wins → +16 Elo total
- **1 upset win against 1700 → +8 Elo**

**Conclusion:** Games against stronger players are more worthwhile!

---

### **Q: My partner was bad, I still lost points. Fair?**

**A:** This is a known problem in doubles Elo.

**Why the system is still fair:**
- Over many games it balances out
- Sometimes you have the strong partner, sometimes the weak one
- Diversity system ensures variation
- **Long-term** your Elo shows your true strength

**Tip:** Focus on consistency over many days, not individual matches

---

### **Q: Why don't I see a rank even though I have points?**

**A:** You haven't reached the minimum of 7 game days.

**Reason:** Prevents "Lucky Streaks"
- Someone comes 2x, gets lucky, +20 points
- Never comes again
- Without minimum: "Winner" of the league

**Solution:** Play at least 7 days, then you'll get your rank!

---

### **Q: My Global Elo is 1600, but I'm only Rank 15. Why?**

**A:** **Global Elo ≠ League Ranking**

- **Global Elo:** Your all-time strength (across all games)
- **League Ranking:** Your performance **in this season**

**Possible Reasons:**
- You had bad days in this league
- Others played better in this league
- You didn't improve in this season

**Next Season:** Fresh start! League Performance is reset.

---

## 🔥 Edge Cases

### Edge Case 1: "Only 4 Players on Game Day"

**Situation:** Only 4 players show up.

**System Behavior:**
- 1 match is generated: [Player 1+4] vs [Player 2+3]
- Power Pairing works normally
- No diversity needed (only 1 match)

**Result:** ✅ System works, but little variation

---

### Edge Case 2: "Extreme Elo Distribution"

**Situation:** 1 pro (1800), 18 beginners (1300-1400)

**System Behavior:**
- Pro spread: 1650-1950 → only finds players in 1400 range
- System **automatically expands** the spread
- Pro plays with best available players (1400)

**Result:**
- Pro likely loses Elo (too weak partners/opponents)
- Beginners gain Elo on upsets
- **Over time:** System balances (pro falls, beginners rise)

**Important:** This is temporary! After 5-10 days the Elo field stabilizes.

---

### Edge Case 3: "Everyone Has Already Had Everyone as Partner"

**Situation:** Game day 15, small group, everyone has already partnered with everyone.

**System Behavior:**
- Diversity penalties are added
- System still takes best Elo match
- Partners with lowest penalty score are chosen

**Example:**
- Player A was partner 3x → Penalty: 3000
- Player B was partner 1x → Penalty: 1000
- **System chooses Player B** (lower penalty)

**Result:** ✅ System prefers rare pairings, but doesn't force them

---

### Edge Case 4: "Someone Only Plays on Days with Many Beginners"

**Situation:** Player only comes when many weak players are there → tries for easy wins.

**Why this doesn't work:**
1. **Pairing System:** Even if many weak players are there, you play with similar Elos (±150)
2. **Diminishing Returns:** Wins against weaker players yield hardly any points
3. **LPI System:** Average over days → a few good days aren't enough

**Result:** ❌ "Cherry-picking" game days brings no advantage

---

### Edge Case 5: "Negative LPI but in Ranking"

**Situation:** Player has -5.2 LPI but Rank 25.

**Explanation:**
- Player is qualified (≥7 days)
- Other qualified players have even worse LPI
- Player is "best of the worst"

**Important:** 
- Negative LPI = On average worse than expected
- **But:** Still better than not being qualified!

---

### Edge Case 6: "Two Players Have the Same LPI"

**Situation:** Anna and Ben both have +7.52 LPI.

**System Behavior:**
- Sorting in PHP is stable (maintains original order on ties)
- In practice: Minimal decimal differences (7.524 vs 7.518)

**If truly identical:**
- Both get the same rank
- Next player skips a number

**Example:**
```
Rank 3: Anna (+7.52)
Rank 3: Ben (+7.52)
Rank 5: Lisa (+7.40) ← Rank 4 is skipped
```

---

## 🎓 Summary for All Players

### What You Need to Know:

1. **Elo is Honest**
   - Close wins against weak players = point loss
   - Upsets against strong players = big gain
   - System "learns" your true level in ~20 games

2. **LPI Rewards Performance, Not Attendance**
   - Average per day counts
   - 7 very good days > 15 mediocre days
   - Consistency is king

3. **Pairing is Fair and Diversity-Oriented**
   - ±150 Elo spread → suitable opponents
   - New partners/opponents preferred
   - Power Pairing prevents unfair teams

4. **No Exploits**
   - Sandbagging hurts your LPI
   - Cherry-picking doesn't work
   - Long-term the best players win

5. **Each Season is a Fresh Start**
   - Global Elo remains (all-time rating)
   - League Performance is reset
   - New chance for everyone!

---

## 📞 Questions or Feedback?

For any questions or suggestions about the league system, talk to your league admin!

**Good luck in the season! 🏆**