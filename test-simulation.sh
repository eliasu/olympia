#!/bin/bash

# Quick Validation Test for Realistic League Simulation
# This script tests the new skill-based progression system

echo "🧪 Testing Realistic League Simulation System"
echo "=============================================="
echo ""

# Step 1: Clean slate
echo "📝 Step 1: Cleaning existing data..."
php artisan delete:matches
php artisan delete:gamedays
php artisan delete:players
echo "✅ Data cleaned"
echo ""

# Step 2: Create players (all start at 1500 Elo)
echo "📝 Step 2: Creating players with equal starting Elo (1500)..."
php artisan create:players --beginners=2 --intermediates=2 --advanced=2 --pros=2
echo "✅ Players created"
echo ""

# Step 3: Create gamedays
echo "📝 Step 3: Creating 10 gamedays..."
php artisan create:gamedays btv-new --count=10
echo "✅ Gamedays created"
echo ""

# Step 4: Simulate league
echo "📝 Step 4: Simulating league..."
php artisan simulate:league btv-new
echo "✅ League simulated"
echo ""

echo "=============================================="
echo "✅ Test Complete!"
echo ""
echo "📊 Next Steps:"
echo "1. Visit /leagues/btv-new to view the leaderboard"
echo "2. Check if Pro players are rising to the top"
echo "3. Verify Beginners are falling to the bottom"
echo "4. Validate that all players started at 1500 Elo"
echo ""
echo "💡 Expected Result:"
echo "   - Pro players should have higher Elo after simulation"
echo "   - Beginner players should have lower Elo after simulation"
echo "   - This validates skill-based progression works!"
