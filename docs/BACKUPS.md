# Backups and restoring

Kindo holds children's information, so backups are **encrypted** and **checked** every time. They need no `mysqldump` (shared hosting often lacks it).

## What is in a backup
One zip: every table as JSON (accounts, children, lessons, alerts, rewards ...), and every picture and sound (lessons, family cards, game animals). Left out on purpose: sign-in tokens, emailed codes, sessions, caches, queued jobs, the sync log. After a restore everyone signs in again.

## Set it up (once)
1. In `.env`: `KINDO_BACKUP_PASSWORD=` a long passphrase. **Keep a copy somewhere safe that is not this server.** Without it a backup cannot be opened. Optional: `KINDO_BACKUP_KEEP=7` (how many to keep).
2. The scheduler already runs a backup every night at 02:30 once the password is set (it needs the normal cPanel cron line `* * * * * php /path/to/artisan schedule:run`).
3. The admin **Health** page shows the last backup and turns red if none ran in the last 36 hours.

Run one by hand any time: `php artisan kindo:backup` (it prints what it saved and checks it). Add `--copy-to=/path/to/folder` to also copy it to another folder, for example a drive that syncs elsewhere.

## Keep a copy off the server (important)
Backups are written to `storage/app/private/backups/` on the same server. If the server is lost, so are they. Copy them away regularly: download from cPanel File Manager, use cPanel's own backup/"Backup Wizard", or schedule `--copy-to` onto a synced folder. A backup that exists only on the machine it protects is not a backup.

## Restore drill (do this once before you need it)
On a new or empty install (same code version or newer):
```
composer install --no-dev
cp .env.example .env     # fill in the same APP_KEY, database, and KINDO_BACKUP_PASSWORD
php artisan migrate --force
php artisan kindo:restore /path/to/kindo-2026-01-01-023000.zip --force
```
Use the same `APP_KEY` as before (it signs links and codes). The restore **replaces all data and pictures** with the backup's, keeps the new server's own schema record, and refuses a damaged backup or one from a newer version of Kindo before touching anything. Without `--force` it asks first.

Test it on a copy first: restore into a scratch database and open the portal.

## What is not covered
- Your `.env` file and the Firebase service-account file are not in the backup; keep them safely too.
- Backups are only as fresh as the last run (up to a day old).
