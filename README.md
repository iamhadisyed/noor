# Kindo (نور)

**Kindo is a learning and communication app for children aged 3 to 10, made for English and Urdu, with extra support for children who need it** (autism, speech delay, and others).
It has two phone apps and a website, all on one server:

| | For | What it does |
|---|---|---|
| **Kindo Junior** | the child | big friendly tiles: "I need..." picture cards that alert the parent, lessons with tracing and spelling, 39 games, Discover (animals, ocean, space, chemistry, AI), calm activities, and a gentle **cool time** screen when screen time is over |
| **Kindo Parent** | mum, dad, carers | make lessons with your own pictures and voice, see **what your child did**, set **screen time** and quiet hours, reports, picture cards, a guide for parents of children with autism |
| **Web portal** (`/portal`) | parents and the owner | everything the Parent app does, plus the **owner console** (accounts, Pro requests, content review, game library, health) |
| **Laravel server** (this repo) | | the API, sync, reports, AI insights, push alerts, emails, backups |

Parents are in charge. A child never needs an account of their own: the parent signs in once and the Junior app opens for each child.

## What is inside

**For children (Junior app)**
- **I need...**: picture cards (water, toilet, food, help, hug...) in English and Urdu with voice. One tap alerts the parent's phone at once, even in quiet hours.
- **Learn**: the lessons the parent made, with pictures and voices, tracing (A to Z, a to z, 0 to 9 and the whole Urdu alphabet), and spelling on an alphabet-order keyboard.
- **Play**: 39 games that are a *reward* for practising: counting, adding, times tables, completing a times-table row, sharing (division), number words, reading the clock, big and small letters, first and last letters, missing letters, word puzzles, guess the word, opposites, days and months, colours, feelings, daily routines, memory match, patterns, and animal sounds. Each game has an age range (3 to 10) and gets harder as the child does well.
- **Discover** (free, no play minutes needed): short facts and a quiz for each age on **animals and where they live, under the sea, space and galaxies, matter and chemistry, and AI**, including how AI helps in health, studies and farming, and how to use it safely. A "Did you know?" card follows every right answer.
- **Calm**: breathing bubble, balloon belly and other calm-down activities.
- **Cool time**: when the parent's screen time is used up, or in quiet hours, a calm moon-and-stars screen replaces the games and suggests things to do away from the screen. The "I need..." board always still works.

**For parents (Parent app and web portal)**
- Add children, choose how each one learns (pace, number of choices, quiet praise, sensory mode).
- Build lessons with photos and your own voice; import a lesson from a photo of a worksheet (Pro).
- **What your child did**: practice, games, calm times, requests and rewards, newest first. *Free: today. Pro: the last two weeks.*
- **Screen time** (*Pro*): a daily allowance, quiet hours, today's use, a weekly chart, "give 15 more minutes". The child's app counts the time and enforces the limit **even with no internet**.
- Reports: weekly numbers, PDF report, per-letter tracing results, milestone cards to share with family, and a weekly AI insight (*Pro*).
- A **guide for parents of a child with autism** (*Pro*): health checks doctors often ask about, food, exercise, sleep and routines, when to see a doctor soon. It is general information only and says so first and last: *every child is special, ask your own child's doctor.*
- Family sharing: invite another parent or a specialist.

**Free and Pro**

| | Free | Pro |
|---|---|---|
| Children, lessons, picture cards | 1 child, a few lessons and cards | more children, unlimited lessons, more cards |
| What my child did | today | last 14 days |
| Screen time and quiet hours | no | yes |
| Reports, AI insight, parent guide | basic | full |
| Adverts for the parent (parent app only) | may show | none |

**How a parent gets Pro (no card payment is built in).** In the app or portal tap **Make me Pro** → we email how to pay (bank, Easypaisa, JazzCash... you write the text in `PAYMENT_INSTRUCTIONS`) → the parent pays and taps **I have paid** with the transaction details → the owner opens **Pro requests** in the console, checks the money arrived, and taps **Make Pro** (choose the days). The parent is emailed and every device switches to Pro.

## Try it now: demo and owner accounts

`php artisan migrate --seed` (or `php artisan kindo:install`) creates these **when `APP_ENV` is not `production`**. Sign in with **"I have a PIN"** on the sign-in screen (web portal or either app).

| Account | Email | PIN | Can do |
|---|---|---|---|
| **Owner / super admin** | `admin@kindo.local` | `482916` | the owner console: all accounts, Pro requests, content, games, health. Sessions last 12 hours |
| **Demo parent** | `demo.parent@kindo.local` | `135790` | everything, **no purchase**: Pro features, two children (Ayan, Zara) with a week of activity, screen time on, lessons, cards |
| **Demo child account** | `demo.child@kindo.local` | `246801` | a parent account holding two children (Mina, 5 and Hamza, 9): sign in on the Kindo Junior app and choose a child |

Demo accounts cannot email, delete, invite or use AI, and they are **reset every night at 03:40** (`kindo:demo-reset`). They have no adverts.
**On a live site** these defaults are *not* created. Set your own in `.env` (`ADMIN_EMAIL`, `ADMIN_PIN`, `DEMO_ENABLED=true`, `DEMO_PARENT_PIN`, `DEMO_CHILD_PIN`) and run `php artisan kindo:install`, or give any account a PIN with `php artisan kindo:set-pin you@example.com 123456`. Change the default PINs everywhere before you share the site.

## Quick start (developers)

Needs PHP 8.2+ and Composer. The apps need Flutter; the browser tests need Node and Playwright.

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # tables, stock cards, game library, owner and demo accounts (not in production)
php artisan serve                   # API at /api/v1 and the web portal at /portal/
php artisan test                    # 350+ PHP tests
```
```bash
cd apps/kindo_core && dart test --exclude-tags live     # shared logic
cd apps/kindo_parent && flutter test && cd ../kindo_junior && flutter test && cd ../kindo_ui && flutter test
tests/e2e/run.sh                    # the web portal in a real browser, 21 walkthroughs
apps/tool/live-demo/run.sh --build  # builds the Flutter apps for the web and runs everything together with screenshots
```
Build the phone apps with `flutter build apk --dart-define=KINDO_API=https://YOUR-DOMAIN/api/v1` (see `apps/README.md` and `docs/APP_HANDOFF.md`).

## Configuration (`.env`)

| Setting | What it is for |
|---|---|
| `APP_URL`, `APP_TIMEZONE` | the public https address (used in signed links) and the family's time zone, for example `Asia/Karachi` |
| `MAIL_*` | SMTP so sign-in codes and Pro emails can be sent |
| `SUPER_ADMIN_EMAILS`, `ADMIN_EMAIL`, `ADMIN_PIN` | who is the owner; the PIN for the owner account |
| `DEMO_ENABLED`, `DEMO_PARENT_PIN`, `DEMO_CHILD_PIN` | the demo accounts |
| `PAYMENT_CONTACT_EMAIL`, `PAYMENT_INSTRUCTIONS`, `PRO_DEFAULT_DAYS` | what the "how to pay" email says, and the default length of Pro |
| `ADS_ENABLED`, `ADMOB_BANNER_ANDROID`, `ADMOB_BANNER_IOS` | Google adverts for free parents in the Parent app (see `docs/LAUNCH_CHECKLIST.md`) |
| `GROQ_API_KEYS`, `GEMINI_API_KEYS` | AI reports and lesson import (optional) |
| `FCM_PROJECT_ID` + the Firebase service account file | push alerts to the parent's phone |
| `KINDO_BACKUP_PASSWORD` | nightly encrypted backups |

## How to use it

**Parent, first day.** Sign in (email code) → add your child → open **Picture cards** and check the words → make a first **lesson** → install **Kindo Junior** on the child's phone or tablet, sign in with the same email, choose the child → turn on **Screen time** (Pro) if you want limits.
**Child.** Tap *I need...* to ask for something, *Learn* for lessons, *Play* for games (minutes are earned by practising), *Discover* to explore, *Calm* when upset.
**Owner.** Sign in to `/portal/` as the owner → *Pro requests* to approve payments, *Content review* for flagged lessons, *Game library* to add animal pictures and sounds, *Health* to check the server.

## Documentation

| File | Contents |
|---|---|
| `docs/LAUNCH_CHECKLIST.md` | what you still need to do before real families use it: Firebase, store accounts, adverts, legal and medical review, backups |
| `docs/API.md` | every endpoint |
| `docs/GAMES.md` | the games, levels, rewards, Discover and screen time rules |
| `docs/APP_HANDOFF.md` | building and releasing the apps |
| `docs/SYNC.md` | how offline sync works |
| `docs/BACKUPS.md` | backups and restore |
| `docs/PORTAL_PRD.md`, `docs/PROJECT_SCOPE.md` | plan and status |

## Honest limits

- No phone app has been built or run on a phone from this repository; the code, widget tests and web builds are tested. You build the APK and iOS app and publish them.
- **Adverts**: the AdMob banner is wired into the Parent app for free parents (child-directed, non-personalised, G-rated, off until `ADS_ENABLED=true`), using Google's test ids until you add yours. It has not been run on a phone. Adverts for children are strictly regulated (COPPA, GDPR-K, Google Families policy): read `docs/LAUNCH_CHECKLIST.md` first.
- Payments are handled by hand on purpose. There is no card or store payment.
- The autism guide and the AI texts are **general information**. Have a doctor and a child-safety expert read them before launch. The terms and privacy text in `config/legal.php` is a draft for a lawyer.
- The urdu tracing outlines are simple geometric shapes, not calligraphy. The picture cards and icons are placeholders until a designer supplies final art.
- Push alerts have not been sent through real Firebase; the AI providers have only been tested with fake responses.

---

# Technical reference

## Setup details
```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_*, MAIL_* (SMTP), SUPER_ADMIN_EMAILS, GROQ_API_KEYS="k1,k2,k3", GEMINI_API_KEYS="k1,k2,k3", FCM_PROJECT_ID
php artisan migrate --seed       # seeds stock AAC cards
php artisan storage:link
# place the Firebase service account at storage/app/firebase-service-account.json
php artisan test
```

## Shared hosting (GoDaddy cPanel)
`QUEUE_CONNECTION=database`, plus two cron jobs:
```
* * * * *   cd /home/USER/public_html && php artisan schedule:run >> /dev/null 2>&1
*/2 * * * * cd /home/USER/public_html && php artisan queue:work --stop-when-empty --tries=2 --timeout=60 >/dev/null 2>&1
```
Run `php artisan config:cache` in production; keys are read through `config/kindo.php`, never `env()` at runtime.

The full endpoint list is in [`docs/API.md`](docs/API.md).

Apps: the two Flutter apps live in `apps/` (see `apps/README.md`): Kindo Parent and Kindo Junior, on one shared API client, offline sync and widget package. They talk to this server through the same `/api/v1` and `GET /sync` as the web portal, so a change made in one place shows up in the others. What still needs the owner (Firebase files, store accounts, final artwork) is listed in `docs/APP_HANDOFF.md`.

Stock card pictures: `php artisan kindo:stock-icons` draws original placeholder pictures into `public/aac` (kept if a file already exists, `--force` to redraw); replace any file with final artwork.

Urdu in the PECS PDF: DomPDF cannot shape Arabic script, so `App\Support\UrduShaper` joins letters and `UrduText` draws each label with GD/FreeType (needs the `gd` extension with FreeType) using Noto Naskh Arabic from `resources/fonts` (SIL OFL licence included).

## Web portal (`/portal`)
Parents and the super admin sign in at `https://YOUR-DOMAIN/portal/` with an email and a 6-digit code; it uses the same `/api/v1` as the app. Set in `.env`:
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` (SMTP from cPanel or a mail service) so codes can be sent
- `SUPER_ADMIN_EMAILS=you@example.com` (comma separated)
- `APP_URL` as the public https address (used in signed picture and sound links)
- `KINDO_OTP_COOLDOWN` seconds between codes (default 60)

Content review: the super admin's **Content review** page lists flagged lessons, lets them open any lesson (pictures and sounds included, audited), manage blocked words, and make accounts inactive from **Accounts**. The terms text lives in `config/legal.php` (draft: have it reviewed, and raise `version` to ask parents to accept again); the starter blocked-word list is `config/moderation.php`.

Family-card photos and voices are private (signed links only). On an installation that already has cards, run once after deploying: `php artisan kindo:privatize-media` (moves old public files to private storage, safe to repeat).

Games: practice earns play minutes and games unlock while there are minutes (see `docs/GAMES.md`). Set `APP_TIMEZONE` (for example `Asia/Karachi`) so "today" matches the family's day. After deploying run `php artisan migrate` and, on an existing install, `php artisan db:seed --class=GameItemSeeder`, then upload each animal's picture and sound in the admin **Game library** (the listening games open once 4 animals are complete).

Privacy: parents can download all their data and delete their account from Settings (`GET /api/v1/me/export`, `POST /me/delete-code` then `DELETE /me`). Deletion needs working email, since it asks for a code.

Billing: Pro is sold or given as one-time voucher codes (admin **Vouchers** page; parents enter them in Settings under Plan). The daily job `kindo:billing-daily` (scheduled 04:05) emails a warning three days before Pro ends and moves ended plans back to free. Buying inside the app stores is not built yet.
Backups: set `KINDO_BACKUP_PASSWORD` in `.env`; a nightly encrypted backup then runs by itself (`php artisan kindo:backup` by hand, `php artisan kindo:restore FILE --force` to restore). Read `docs/BACKUPS.md`, especially the part about keeping a copy off the server.

Browser tests: `tests/e2e/run.sh` (see `tests/e2e/README.md`).
