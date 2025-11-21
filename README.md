# Padel Americano League

A lightweight PHP/MySQL web app for running friendly Americano-format padel tournaments.

## Requirements
- PHP 8+
- MySQL 5.7+ / MariaDB with InnoDB
- Web server configured to serve the `public/` directory

## Setup
1. Create the database and tables:
   ```sql
   SOURCE schema.sql;
   ```
2. Copy `includes/config.php` and update the database credentials (`db_host`, `db_name`, `db_user`, `db_pass`).
3. Point your web server document root to `public/` (or visit `http://localhost:8000/index.php` when running with PHP's built-in server). The `includes/` directory must live alongside `public/` (not above it) so the app can load shared helpers; if you deploy files directly into a single `public_html` directory, copy `includes/` into the same directory.
   ```bash
   php -S localhost:8000 -t public
   ```
4. Open the app in your browser and start adding players and tournaments.

## Features
- Player management with level tracking and safe deletion when unused.
- Tournament creation, cloning, and setup with player selection or inline creation.
- Americano live management with automatic game assignment per court, D-level pairing safeguards, and score entry.
- Live leaderboard plus final leaderboard with CSV export once finished.

## Notes
- The game assignment heuristic balances games played and avoids repeated teammate/opponent pairings while respecting the two-D-player rule.
- Courts can be marked finished when no further games should be played on them.
