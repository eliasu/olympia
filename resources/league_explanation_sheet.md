# 🏆 BTV Liga-System: Vollständige Erklärung

## 📖 Inhaltsverzeichnis
1. [Überblick](#überblick)
2. [Elo-Rating System](#elo-rating-system)
3. [Pairing-System (Matchmaking)](#pairing-system)
4. [Liga-Ranking (LPI)](#liga-ranking)
5. [Beispiel-Szenarien](#beispiel-szenarien)
6. [Häufige Fragen (FAQ)](#faq)
7. [Edge-Cases](#edge-cases)

---

## 🎯 Überblick

### Was ist das Ziel?
Unser Liga-System misst **deine tatsächliche Performance**, nicht nur wie oft du erscheinst. Ein Spieler, der 5 Tage kommt und konstant stark spielt, kann einen Spieler schlagen, der 12 Tage da war, aber schwächer performt hat.

### Zwei getrennte Wertungen:

| Wertung | Zweck | Berechnung |
|---------|-------|------------|
| **Global Elo** | Allzeit-Stärke | Permanent, über alle Ligen |
| **Liga Performance Index (LPI)** | Saisonwertung | Pro Liga, jede Saison neu |

**Wichtig:** 
- **Global Elo** wird für Pairings (Teamzusammenstellung) verwendet
- **LPI** entscheidet, wer die Liga gewinnt

---

## ⚖️ Elo-Rating System

### Was ist Elo?

Elo ist ein mathematisches System, das ursprünglich für Schach entwickelt wurde. Es misst deine relative Spielstärke im Vergleich zu anderen Spielern.

**Startwert:** 1500 Elo (jeder neue Spieler)

### Wie verändert sich mein Elo?

Nach jedem Match wird dein Elo angepasst basierend auf:
1. **Erwartete Leistung** (basierend auf Team-Elos)
2. **Tatsächliche Leistung** (Score des Spiels)
3. **K-Factor** (Sensitivität der Änderung, Standard: 32)

### Formel (für Interessierte):

```
Team-Elo = Durchschnitt der 2 Spieler-Elos
Erwartung = 1 / (1 + 10^((Gegner-Elo - Dein-Team-Elo) / 400))
Tatsächlich = Deine Punkte / (Deine Punkte + Gegner Punkte)
Delta = K-Factor × (Tatsächlich - Erwartung)
```

### 💰 Wie viel Elo gewinne/verliere ich?

#### **Szenario 1: Ausgeglichenes Spiel (beide Teams ~1500)**

| Score | Dein Gewinn/Verlust |
|-------|---------------------|
| 11:10 | ±0.6 Elo |
| 11:9 | ±1.6 Elo |
| 11:7 | ±4.0 Elo |
| 11:5 | ±6.1 Elo |

**Fazit:** Bei gleich starken Teams gewinnt man wenig, verliert wenig.

#### **Szenario 2: Du bist Favorit (+100 Elo Vorteil)**

| Ergebnis | Score | Dein Gewinn/Verlust |
|----------|-------|---------------------|
| Du gewinnst knapp | 11:9 | **-2.9 Elo** ❌ (zu knapp!) |
| Du gewinnst deutlich | 11:5 | **+1.6 Elo** ✅ |
| Du verlierst | 9:11 | **-6.1 Elo** 💥 |

**Fazit:** Als Favorit musst du klar gewinnen, sonst verlierst du Punkte!

#### **Szenario 3: Du bist Underdog (-100 Elo Nachteil)**

| Ergebnis | Score | Dein Gewinn/Verlust |
|----------|-------|---------------------|
| Du verlierst knapp | 9:11 | **+2.9 Elo** ✅ (gute Leistung!) |
| Du verlierst deutlich | 5:11 | **-1.6 Elo** (ok) |
| Du gewinnst | 11:9 | **+6.1 Elo** 🚀 (Upset!) |

**Fazit:** Als Underdog kannst du auch bei Niederlagen Punkte gewinnen!

### 🎯 Wichtige Prinzipien:

1. **Score-Differenz zählt:** 11:5 ist besser als 11:9
2. **Keine Win-Protection:** Gewinnen garantiert keine Punkte
3. **Ehrliches System:** Favoritensiege gegen schwache Teams bringen wenig
4. **Upsets werden belohnt:** Überraschungssiege bringen viele Punkte

### 📈 Elo-Entwicklung über Zeit

**Typische Elo-Bereiche nach 20 Spieltagen:**

| Skill-Level | Elo-Range | Beschreibung |
|-------------|-----------|--------------|
| **Elite** | 1650-1800+ | Top 5%, dominiert stark |
| **Fortgeschritten** | 1550-1650 | Top 20%, sehr gut |
| **Mittel+** | 1500-1550 | Oberes Mittelfeld |
| **Mittel** | 1450-1500 | Durchschnitt |
| **Mittel-** | 1400-1450 | Unteres Mittelfeld |
| **Anfänger+** | 1350-1400 | Entwickelt sich |
| **Anfänger** | 1200-1350 | Lernt noch |

**Beispiel-Karriere:**
```
Start:        1500 Elo
Nach 5 Tagen:  1540 Elo (+8 Elo/Tag, guter Spieler)
Nach 10 Tagen: 1580 Elo
Nach 20 Tagen: 1660 Elo (stabilisiert sich bei wahrem Level)
```

---

## 🎲 Pairing-System (Matchmaking)

### Ziel des Pairings:
1. **Skill-basiert:** Du spielst mit/gegen ähnlich starke Spieler
2. **Diversität:** Du spielst mit verschiedenen Partnern und gegen verschiedene Gegner
3. **Fairness:** Jeder bekommt ähnlich viele Matches am Spieltag

### Wie funktioniert es?

#### **Schritt 1: Elo-Band (±150 Elo)**

Das System sucht dir 3 Partner im Bereich **dein Elo ±150**.

**Beispiel:**
- Dein Elo: 1600
- Mögliche Partner: 1450-1750

**Warum ±150?**
- Bei ±150 Elo = ~30% Skill-Unterschied im 1v1
- Im Doppel gleicht sich das aus durch Power Pairing
- **Verhindert:** 1700 vs 1300 Spiele (frustrierend für beide)

#### **Schritt 2: Diversität-Priorisierung**

Das System bevorzugt **neue Partner und Gegner**.

**Beispiel-Spieltag:**
```
Match 1: Du spielst mit Spieler A, gegen Spieler B & C
Match 2: System bevorzugt Spieler D, E, F (alle neu für dich)
Match 3: Nur wenn nötig: System nimmt wieder Spieler A-C
```

**Gewichtung:**
- Gleicher Partner nochmal? → System vermeidet stark (Penalty: 1000)
- Gleicher Gegner nochmal? → System vermeidet moderat (Penalty: 500)

#### **Schritt 3: Power Pairing (Team-Balance)**

Wenn 4 Spieler ausgewählt sind, werden sie so geteilt:

```
4 Spieler sortiert nach Elo: [1650, 1580, 1520, 1480]

Team A: Stärkster + Schwächster = 1650 + 1480 = Ø 1565
Team B: Mittlere Zwei = 1580 + 1520 = Ø 1550

→ Differenz: nur 15 Elo! (sehr ausgeglichen)
```

**Warum Power Pairing?**
- Verhindert "Dream Teams" (beide Starke zusammen)
- Gibt schwächeren Spielern Chance zu lernen
- Macht Spiele spannender

### 🎮 Beispiel-Spieltag (19 Spieler, 16 Matches)

**Spieler-Elos:**
```
1700, 1680, 1650, 1620, 1600, 1580, 1560, 1540, 1520, 1500,
1480, 1460, 1440, 1420, 1400, 1380, 1360, 1340, 1320
```

**Match 1:** (System wählt die 4 mit wenigsten Spielen heute = alle 0)
- Seed: Spieler #1 (1700)
- Suche: 1550-1850 Elo
- Finde: #2 (1680), #4 (1620), #6 (1580)
- Teams: [1700+1580] vs [1680+1620] → Avg: 1640 vs 1650 ✅

**Match 2:** (Spieler 1,2,4,6 haben jetzt 1 Spiel, Rest hat 0)
- Seed: Spieler #3 (1650, 0 Spiele)
- Suche: 1500-1800, bevorzugt **nicht** #1,2,4,6
- Finde: #5 (1600), #7 (1560), #8 (1540)
- Teams: [1650+1540] vs [1600+1560] → Avg: 1595 vs 1580 ✅

**Match 5:** (Spieler #1 spielt wieder)
- Seed: Spieler #1 (1700, 1 Spiel)
- Suche: 1550-1850, **bevorzugt neue Spieler**
- System vermeidet #2,4,6 (waren in Match 1)
- Finde: #3 (1650), #5 (1600), #9 (1520)
- Teams: [1700+1520] vs [1650+1600] → Avg: 1610 vs 1625 ✅

**Ergebnis nach 16 Matches:**
- Top-Spieler (1700): 3-4 Matches, immer mit 1550-1700 Bereich
- Mittel-Spieler (1500): 3-4 Matches, mit 1350-1650 Bereich
- Schwache Spieler (1320): 3 Matches, mit 1170-1470 Bereich

✅ **Niemand spielt gegen komplett unpassende Gegner!**

---

## 🏆 Liga-Ranking (LPI System)

### Was ist der League Performance Index (LPI)?

**LPI = Durchschnittliche Elo-Punkte pro Spieltag**

**Formel:**
```
LPI = Summe aller Elo-Deltas / Anzahl Spieltage
```

### Warum LPI statt Gesamt-Punkte?

**Problem mit Gesamt-Punkten:**
```
Spieler A: 5 Tage, +90 Punkte → Verliert
Spieler B: 12 Tage, +144 Punkte → Gewinnt

Aber: Spieler A hat bessere Performance pro Tag! (+18 vs +12)
```

**Lösung mit LPI:**
```
Spieler A: +90 / 5 = +18.0 LPI → Gewinnt! ✅
Spieler B: +144 / 12 = +12.0 LPI
```

### 📊 LPI-Berechnung Beispiel

**Spieler: Max Müller**

| Spieltag | Matches | Elo-Deltas | Tages-Summe |
|----------|---------|------------|-------------|
| Tag 1 | 3 Matches | +2.5, -1.2, +3.8 | +5.1 |
| Tag 2 | 4 Matches | +1.0, +2.2, -0.5, +4.1 | +6.8 |
| Tag 3 | 3 Matches | -2.1, +5.5, +1.8 | +5.2 |
| Tag 4 | 4 Matches | +3.2, +0.8, +2.9, +1.5 | +8.4 |
| Tag 5 | 3 Matches | +4.2, -1.1, +2.6 | +5.7 |

**Berechnung:**
```
Gesamt-Punkte: +5.1 +6.8 +5.2 +8.4 +5.7 = +31.2
Spieltage: 5
LPI = 31.2 / 5 = +6.24 Punkte pro Tag
```

**Im Dashboard sichtbar:**
- `league_performance`: +31.2 (Gesamt-Summe)
- `played_games`: 5 (Anzahl Spieltage)
- `average_delta`: +1.88 (Ø pro Match)
- **LPI (für Ranking)**: +6.24

### 🎯 Qualifikation & Ranking

**Mindest-Anforderung:**
- **7 Spieltage** (konfigurierbar in Liga-Einstellungen)
- Wer weniger hat → nicht im Ranking

**Ranking-Reihenfolge:**
1. Qualifizierte Spieler (≥7 Tage), sortiert nach LPI
2. Nicht-Qualifizierte Spieler, sortiert nach LPI (ohne Rang)

**Beispiel-Tabelle:**

| Rang | Spieler | Spieltage | Gesamt-Punkte | LPI | Status |
|------|---------|-----------|---------------|-----|--------|
| 🥇 1 | Anna Schmidt | 10 | +95.5 | **+9.55** | Qualifiziert |
| 🥈 2 | Max Müller | 7 | +52.8 | **+7.54** | Qualifiziert |
| 🥉 3 | Lisa Weber | 12 | +84.0 | **+7.00** | Qualifiziert |
| 4 | Tom Fischer | 8 | +48.2 | **+6.03** | Qualifiziert |
| - | Ben Klein | 5 | +35.0 | +7.00 | ⚠️ Nicht qualifiziert |
| - | Sarah Lang | 4 | +28.5 | +7.13 | ⚠️ Nicht qualifiziert |

**Hinweis:** Ben und Sarah haben gute LPIs, aber zu wenig Spieltage!

### 💡 Strategie-Tipps für Liga-Erfolg

1. **Konsistenz > Anwesenheit**
   - Lieber 7 Tage mit +8 LPI als 15 Tage mit +4 LPI
   
2. **Qualität der Siege**
   - Deutliche Siege gegen gute Gegner bringen am meisten
   - Knappe Siege gegen Schwache können negativ sein
   
3. **Mindest-Tage erfüllen**
   - Erst ab 7 Tagen bist du im Ranking
   - Danach: Performance > Anwesenheit

4. **Long-term Denken**
   - Dein Global Elo steigt/fällt langfristig zu deinem wahren Level
   - Liga-Performance ist kurzfristig (jede Saison neu)

---

## 📋 Beispiel-Szenarien

### Szenario 1: "Der Teilzeit-Profi"

**Situation:**
- Anna kommt nur 7x (Mindest-Anforderung)
- Spielt konstant sehr gut
- Global Elo: 1650 (stark)

**Performance:**
```
7 Spieltage × Ø +9 Elo/Tag = +63 Gesamt-Punkte
LPI = +63 / 7 = +9.0
```

**Ergebnis:** ✅ Rang 1-3 möglich trotz minimaler Anwesenheit!

---

### Szenario 2: "Der Fleißige Durchschnitt"

**Situation:**
- Ben kommt 15x (sehr oft da)
- Spielt durchschnittlich
- Global Elo: 1500 (Mittelfeld)

**Performance:**
```
15 Spieltage × Ø +4 Elo/Tag = +60 Gesamt-Punkte
LPI = +60 / 15 = +4.0
```

**Ergebnis:** ⚠️ Trotz mehr Gesamt-Punkten → nur Mittelfeld (Rang 10-15)

---

### Szenario 3: "Der Aufsteiger"

**Situation:**
- Lisa startet bei 1450 Elo (schwach)
- Verbessert sich stark über die Saison
- Endet bei 1580 Elo (gut)

**Performance:**
```
Erste 5 Tage: Ø +10 Elo/Tag (schnelle Verbesserung)
Nächste 7 Tage: Ø +5 Elo/Tag (stabilisiert sich)
12 Spieltage, +85 Gesamt-Punkte
LPI = +85 / 12 = +7.08
```

**Ergebnis:** ✅ Top 5 trotz schwachem Start!

---

### Szenario 4: "Die Pech-Strähne"

**Situation:**
- Tom ist eigentlich stark (1620 Elo)
- Hat 3 Spieltage mit viel Pech/schlechten Partnern
- Verliert -15 Punkte in 3 Tagen

**Performance:**
```
Schlechte 3 Tage: -15 Punkte
Gute 7 Tage: +60 Punkte
10 Spieltage, +45 Gesamt-Punkte
LPI = +45 / 10 = +4.5
```

**Ergebnis:** ⚠️ Pech-Strähnen schaden dem LPI, aber erholt sich über Zeit

**Wichtig:** System misst Durchschnitt → einzelne schlechte Tage fallen nicht so stark ins Gewicht

---

### Szenario 5: "Der Sandbagger"

**Situation:**
- Jemand versucht absichtlich schlecht zu spielen um schwächere Gegner zu bekommen
- Verliert erste 3 Tage absichtlich (-30 Elo)
- Dann "aufwachen" und dominieren

**Warum das nicht funktioniert:**
```
3 schlechte Tage: -30 Punkte (LPI: -10)
7 gute Tage: +70 Punkte (LPI: +10)
10 Tage gesamt: +40 Punkte
LPI = +40 / 10 = +4.0 (nur Mittelfeld!)
```

**Ergebnis:** ❌ Sandbaggern schadet deinem LPI massiv
**Grund:** Jeder Tag zählt gleich viel → schlechte Tage kann man nicht "aufholen"

---

## ❓ FAQ (Häufige Fragen)

### **Q: Warum habe ich Punkte verloren obwohl ich gewonnen habe?**

**A:** Du warst Favorit und hast zu knapp gewonnen.

**Beispiel:**
- Dein Team: Ø 1600 Elo
- Gegner: Ø 1500 Elo
- Erwartung: 11:7 Sieg
- Tatsächlich: 11:9 Sieg
- **Ergebnis: -2 Elo** (schlechtere Performance als erwartet)

**Lösung:** Als Favorit musst du deutlich gewinnen!

---

### **Q: Ich bin oft da, aber im Ranking weit hinten. Warum?**

**A:** Das System bewertet **Performance pro Tag**, nicht Gesamt-Anwesenheit.

**Vergleich:**
- Du: 15 Tage, +45 Punkte → LPI: +3.0
- Anna: 8 Tage, +64 Punkte → LPI: +8.0
- **Anna gewinnt** (bessere Performance)

**Das ist fair:** Sonst hätten Teilzeit-Spieler keine Chance.

---

### **Q: Warum spiele ich nie mit den besten/schlechtesten Spielern?**

**A:** Das System hält ±150 Elo Spread ein.

**Beispiel:**
- Du: 1500 Elo
- Spieler A: 1720 Elo (Top)
- Spieler B: 1280 Elo (Anfänger)

**Differenz zu A:** 220 > 150 → kein Pairing
**Differenz zu B:** 220 > 150 → kein Pairing

**Du spielst mit:** 1350-1650 Bereich (deine Liga)

**Vorteil:** 
- Faire Spiele
- Top-Spieler frustriert nicht Anfänger
- Du lernst gegen ähnlich Starke

---

### **Q: Ich hatte heute 3 Matches, andere hatten 4. Unfair?**

**A:** Bei ungerader Spielerzahl mathematisch unmöglich alle gleich zu verteilen.

**Beispiel:** 19 Spieler, 16 Matches
- 16 Matches × 4 Spieler = 64 Slots
- 64 Slots / 19 Spieler = 3.37 Matches/Person

**System-Lösung:**
- Bevorzugt Spieler mit weniger Spielen
- Bevorzugt Spieler mit weniger Liga-Erfahrung
- Am Ende: 15 Spieler mit 3 Matches, 4 Spieler mit 4 Matches

**LPI gleicht aus:** Dein Durchschnitt wird über Tage berechnet, nicht Matches

---

### **Q: Kann ich mein Elo "farmen" gegen schwache Spieler?**

**A:** Nein, aus zwei Gründen:

1. **Pairing-System:** Du spielst nur gegen ±150 Elo (nicht gegen viel Schwächere)
2. **Diminishing Returns:** Siege gegen Schwächere bringen kaum Punkte

**Beispiel:**
- Du (1600) vs Schwächerer (1500)
- Sieg 11:5 → nur +1.6 Elo
- 10 solcher Siege → +16 Elo gesamt
- **1 Upset-Sieg gegen 1700 → +8 Elo**

**Fazit:** Spiele gegen Stärkere lohnen sich mehr!

---

### **Q: Mein Partner war schlecht, ich habe trotzdem Punkte verloren. Fair?**

**A:** Das ist ein bekanntes Problem im Doppel-Elo.

**Warum das System trotzdem fair ist:**
- Über viele Spiele gleicht sich das aus
- Manchmal hast du den starken Partner, manchmal den schwachen
- Diversität-System sorgt für Variation
- **Langfristig** zeigt dein Elo deine wahre Stärke

**Tipp:** Fokus auf Konsistenz über viele Tage, nicht einzelne Matches

---

### **Q: Warum sehe ich keinen Rang obwohl ich Punkte habe?**

**A:** Du hast das Minimum von 7 Spieltagen nicht erreicht.

**Grund:** Verhindert "Lucky Streaks"
- Jemand kommt 2x, hat Glück, +20 Punkte
- Kommt nie wieder
- Ohne Minimum: "Gewinner" der Liga

**Lösung:** Spiel mindestens 7 Tage, dann kriegst du deinen Rang!

---

### **Q: Mein Global Elo ist 1600, aber ich bin nur Rang 15. Warum?**

**A:** **Global Elo ≠ Liga-Ranking**

- **Global Elo:** Deine Allzeit-Stärke (über alle Spiele)
- **Liga-Ranking:** Deine Performance **in dieser Saison**

**Mögliche Gründe:**
- Du hattest schlechte Tage in dieser Liga
- Andere spielten in dieser Liga besser
- Du hast dich in dieser Saison nicht verbessert

**Nächste Saison:** Neuer Start! Liga-Performance wird zurückgesetzt.

---

## 🔥 Edge-Cases

### Edge-Case 1: "Nur 4 Spieler am Spieltag"

**Situation:** Nur 4 Spieler erscheinen.

**System-Verhalten:**
- 1 Match wird generiert: [Spieler 1+4] vs [Spieler 2+3]
- Power Pairing funktioniert normal
- Keine Diversität nötig (nur 1 Match)

**Ergebnis:** ✅ System funktioniert, aber wenig Variation

---

### Edge-Case 2: "Extreme Elo-Verteilung"

**Situation:** 1 Profi (1800), 18 Anfänger (1300-1400)

**System-Verhalten:**
- Profi-Spread: 1650-1950 → findet nur Spieler im 1400er Bereich
- System **erweitert** automatisch den Spread
- Profi spielt mit besten verfügbaren Spielern (1400)

**Ergebnis:**
- Profi verliert wahrscheinlich Elo (zu schwache Partner/Gegner)
- Anfänger gewinnen Elo bei Upsets
- **Über Zeit:** System balanciert sich (Profi fällt, Anfänger steigen)

**Wichtig:** Das ist temporär! Nach 5-10 Tagen stabilisiert sich das Elo-Feld.

---

### Edge-Case 3: "Alle hatten schon alle als Partner"

**Situation:** Spieltag 15, kleine Gruppe, jeder hatte schon jeden als Partner.

**System-Verhalten:**
- Diversität-Penalties werden addiert
- System nimmt trotzdem beste Elo-Match
- Partner mit niedrigstem Penalty-Score werden gewählt

**Beispiel:**
- Spieler A war 3x Partner → Penalty: 3000
- Spieler B war 1x Partner → Penalty: 1000
- **System wählt Spieler B** (niedrigerer Penalty)

**Ergebnis:** ✅ System bevorzugt seltene Pairings, erzwingt sie aber nicht

---

### Edge-Case 4: "Jemand spielt nur an Tagen mit vielen Anfängern"

**Situation:** Spieler kommt nur wenn viele Schwache da sind → versucht easy Wins.

**Warum das nicht funktioniert:**
1. **Pairing-System:** Selbst wenn viele Schwache da sind, spielst du mit ähnlichen Elos (±150)
2. **Diminishing Returns:** Siege gegen Schwächere bringen kaum Punkte
3. **LPI-System:** Durchschnitt über Tage → einige gute Tage reichen nicht

**Ergebnis:** ❌ "Cherry-Picking" von Spieltagen bringt keinen Vorteil

---

### Edge-Case 5: "Negativer LPI aber im Ranking"

**Situation:** Spieler hat -5.2 LPI aber Rang 25.

**Erklärung:**
- Spieler ist qualifiziert (≥7 Tage)
- Andere qualifizierte Spieler haben noch schlechteren LPI
- Spieler ist "bester der Schlechten"

**Wichtig:** 
- Negativer LPI = Durchschnittlich schlechter als erwartet
- **Aber:** Immer noch besser als nicht qualifiziert zu sein!

---

### Edge-Case 6: "Zwei Spieler haben gleichen LPI"

**Situation:** Anna und Ben haben beide +7.52 LPI.

**System-Verhalten:**
- Sortierung in PHP ist stabil (behält ursprüngliche Reihenfolge bei)
- In Praxis: Minimale Dezimal-Unterschiede (7.524 vs 7.518)

**Falls wirklich identisch:**
- Beide kriegen gleichen Rang
- Nächster Spieler überspringt eine Nummer

**Beispiel:**
```
Rang 3: Anna (+7.52)
Rang 3: Ben (+7.52)
Rang 5: Lisa (+7.40) ← Rang 4 wird übersprungen
```

---

## 🎓 Zusammenfassung für Kompetitive Spieler

### Was du wissen musst:

1. **Elo ist ehrlich**
   - Knappe Siege gegen Schwache = Punktverlust
   - Upsets gegen Starke = großer Gewinn
   - System "lernt" dein wahres Level in ~20 Spielen

2. **LPI belohnt Performance, nicht Anwesenheit**
   - Durchschnitt pro Tag zählt
   - 7 sehr gute Tage > 15 mittelmäßige Tage
   - Konsistenz ist King

3. **Pairing ist fair und diversitäts-orientiert**
   - ±150 Elo Spread → passende Gegner
   - Neue Partner/Gegner bevorzugt
   - Power Pairing verhindert unfaire Teams

4. **Keine Exploits**
   - Sandbaggern schadet deinem LPI
   - Cherry-Picking funktioniert nicht
   - Langfristig gewinnen die Besten

5. **Jede Saison ist ein Neustart**
   - Global Elo bleibt (Allzeit-Wertung)
   - Liga-Performance wird zurückgesetzt
   - Neue Chance für jeden!

---

## 📞 Fragen oder Feedback?

Bei Unklarheiten oder Verbesserungsvorschlägen zum Liga-System, sprich mit deinem Liga-Admin!

**Viel Erfolg in der Saison! 🏆**