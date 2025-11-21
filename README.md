# Padel Americano League

A lightweight PHP/MySQL web app for running friendly Americano-format padel tournaments.

## Requirements
- PHP 8+
- MySQL 5.7+ / MariaDB with InnoDB
- Web server configured to serve the `public_html/` directory

## Setup
1. Create the database and tables:
   ```sql
   SOURCE schema.sql;
   ```
2. Copy `public_html/includes/config.php` and update the database credentials (`db_host`, `db_name`, `db_user`, `db_pass`). On hosted servers where `root` login is disabled or requires a password, set the correct user/password here. You can also provide overrides via environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_CHARSET` if you prefer not to modify the file.
3. Point your web server document root to `public_html/` (or visit `http://localhost:8000/index.php` when running with PHP's built-in server).
   ```bash
   php -S localhost:8000 -t public_html
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
