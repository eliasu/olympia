# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a **Statamic CMS** application (flat-file, Laravel + Git powered) for managing sports league gamedays (badminton/doubles tennis). The system handles player management, session planning, match generation, and ELO rating calculations.

### Core Concepts

- **Players**: Tracked with global ELO ratings, total games played, and status (active/inactive)
- **Leagues**: Seasons with configurable rules (K-factor, point limits, mixed days count)
- **gamedays**: Individual game days within a league with court/match configurations
- **Matches**: Generated doubles matches (4 players: 2v2) with score tracking and ELO updates

### Data Architecture

All content is stored as **flat files** in the `content/` directory:
- `content/collections/players/` - Player entries (markdown/YAML)
- `content/collections/leagues/` - League/season configurations
- `content/collections/gamedays/` - Game day gamedays
- `content/collections/matches/` - Generated match results

**Important**: Use Statamic's Entry facade (`Statamic\Facades\Entry`) to interact with content, not direct file manipulation. Content changes auto-save to flat files.

### ELO System

The `LeagueService` implements a doubles ELO rating system:
1. **Fair Mode**: After mixed period, teams are balanced by ELO (strongest + weakest vs middle two)
2. **Score-Based Calculation**: ELO delta considers point differential, not just win/loss

## Development Commands

### Initial Setup
```bash
composer setup  # Installs deps, creates .env, generates key, runs migrations, builds assets
```

### Development Server
```bash
composer dev    # Runs ALL services concurrently: Laravel server, queue worker, logs (Pail), and Vite
```

This starts 4 processes:
- `php artisan serve` - Laravel development server
- `php artisan queue:listen` - Background job processing
- `php artisan pail` - Real-time log viewing
- `npm run dev` - Vite asset compilation with HMR

### Testing
```bash
composer test              # Runs all PHPUnit tests (Feature + Unit)
php artisan test           # Alternative Laravel test runner
php artisan test --filter=TestName  # Run specific test
```

### Asset Management
```bash
npm run dev     # Start Vite dev server with HMR
npm run build   # Build production assets
```

### Statamic CLI (`please`)
```bash
./please list              # Show all available Statamic commands
./please make:user         # Create control panel user
./please cache:clear       # Clear Statamic's Stache cache
./please support:details   # Show environment information
```

### Database
```bash
php artisan migrate        # Run migrations (uses SQLite by default)
php artisan db:seed        # Run database seeders
php seed_players.php       # Custom script to seed tennis player examples
```

### Code Quality
```bash
vendor/bin/pint            # Laravel Pint (PHP code formatter)
```

## Project Structure

```
app/
├── Http/Controllers/
│   └── LeagueController.php    # API endpoints for league operations
└── Services/
    └── LeagueService.php        # Core business logic: matchmaking, ELO calculations

content/
├── collections/                 # Flat-file content storage
│   ├── players/                 # Player entries
│   ├── leagues/                 # League configurations
│   ├── gamedays/                # Session/game day data
│   └── matches/                 # Match results

resources/
├── blueprints/                  # Statamic content schemas
│   └── collections/
│       ├── players/players.yaml
│       ├── leagues/leagues.yaml
│       ├── gamedays/gamedays.yaml
│       └── matches/matches.yaml
├── views/                       # Antlers templates (.antlers.html)
│   ├── layout.antlers.html
│   ├── leagues/
│   └── players/
├── css/site.css                 # Tailwind CSS entry
└── js/site.js                   # Alpine.js + custom JS

routes/
└── web.php                      # Custom Laravel routes (API endpoints)
```

## Key Technical Details

### Statamic Specifics

- **Template Engine**: Antlers (Laravel Blade-like with `{{ }}` syntax)
- **Frontend Stack**: Alpine.js + Tailwind CSS 4 + Vite
- **Content Cache**: Statamic uses "Stache" - a file-based cache of flat-file content
- **Blueprints**: Define content schemas (like database schemas but for flat files)

### Laravel/PHP Version

- PHP 8.2+ required
- Laravel 12.x
- Statamic 5.x

### Custom API Endpoints

All POST routes in `routes/web.php`:
- `/league/generate-plan` - Generate matches for a session
- `/league/update-players` - Update present players for a session
- `/league/update-score` - Record match scores
- `/league/finish-session` - Finalize session and calculate ELO updates

### Environment Configuration

Copy `.env.example` to `.env` and configure:
- `STATAMIC_LICENSE_KEY` - Required for Pro features
- `STATAMIC_PRO_ENABLED` - Enable Statamic Pro features
- `DB_CONNECTION=sqlite` - Default database (flat-file friendly)

## Development Workflow

1. **Content Changes**: Edit via Statamic Control Panel (usually `/cp`) or directly modify files in `content/`
2. **Schema Changes**: Update blueprint YAML files in `resources/blueprints/`
3. **Business Logic**: Modify `LeagueService.php` for matchmaking/ELO algorithms
4. **Frontend**: Edit Antlers templates and compile assets with Vite
5. **Testing**: Write Feature tests for API endpoints, Unit tests for services

### Important Notes

- Always use `Entry::find()` and `Entry::query()` for content access, not file I/O
- When generating matches, the service creates new Entry objects programmatically
- ELO calculations happen on session finalization, not during match score updates
- The system supports relationship tracking via the `stillat/relationships` package
- Content is version-controlled (flat files), so changes can be committed to Git

## Common Tasks

### Add a New Player
```php
$entry = Entry::make()
    ->collection('players')
    ->slug('player-name')
    ->data([
        'title' => 'Player Name',
        'global_elo' => 1500.0,
        'total_games' => 0,
        'played_game_days' => 0,
        'player_status' => 'active'
    ]);
$entry->save();
```

### Query Matches for a Session
```php
$matches = Entry::query()
    ->where('collection', 'matches')
    ->where('session', $sessionId)
    ->get();
```

### Clear Statamic Cache
```bash
./please cache:clear
# Or during development with auto-watch:
# Set STATAMIC_STACHE_WATCHER=auto in .env
```
