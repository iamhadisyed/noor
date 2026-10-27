# Kindo / Noor — Project Scope (status vs. specification)

Status key: ✅ done and tested · 🟡 partly done / needs follow-up · ❌ not started · 📱 belongs to the mobile apps (not in this repo)

Repo: `iamhadisyed/noor` · Stack: Laravel 12, PHP 8.2+, Sanctum, DomPDF · Tests: 9 feature tests, all passing
(tests use faked HTTP; nothing has been run against real Groq, Gemini or FCM yet)

---

## 1. Delivered so far (merged to `main`)

| Area | What exists | Status |
|---|---|---|
| Database (spec §7) | All 12 tables as migrations + Eloquent models: users (extended), children, device_tokens, family_members, aac_cards, child_needs_logs, syllabus_modules, syllabus_words, practice_sessions, practice_mistake_logs, ai_diagnostic_reports | ✅ |
| AI orchestrator (§6.2, §8.1) | Groq pool → Gemini pool failover, per-key cooldown on 429, 3-day result cache, JSON parsing guard | ✅ (fake-HTTP tested) |
| Report job (§8.2) | `GeneratePediatricReportJob`, last-7-days data, one report per child per week (re-runs update, not duplicate) | ✅ |
| Weekly scheduling | Sunday 20:00 job dispatch for every child of an active Pro parent | ✅ |
| Push alerts (§5.1, §9.2 server side) | `FcmService`: FCM HTTP v1 via service-account JWT, Android HIGH priority + channel `urgent_needs_channel`, iOS critical interruption, dead-token cleanup | 🟡 payload tested; never sent to real FCM |
| Need/potty alert API (§9.1 server side) | `POST child/alert` logs the need and pushes to all parent devices; live feed; acknowledge | ✅ |
| Auth | Passwordless email-code sign-in, roles (parent, super admin), sessions, audit log, device tokens | ✅ (see `docs/PORTAL_PRD.md` for the web portal plan) |
| Child profiles (§4 multi-profile) | List/create; free = 1 profile, Pro = 4 (HTTP 402 beyond limit) | ✅ |
| Family cards (§5.1) | List/create with photo (≤300 KB) and audio (≤150 KB) upload; free = 2 cards. Game and add-card screens designed (`design/`); game results not recorded server-side | 🟡 (no edit/delete, no game results) |
| AAC cards (§5.1, §4) | Stock-card seeder (13 cards, EN + Urdu labels); free tier sees only non-Pro cards | 🟡 (no custom-card CRUD, no icon images shipped) |
| Practice sync (§5.2 data side) | `POST children/{id}/sessions` stores a session + its mistakes | ✅ |
| AI reports API (§4 Pro) | Generate (queued, Pro only) and fetch latest | ✅ |
| Printable PECS PDF (§8.3) | Endpoint + Blade view; free = 4-card sample, Pro = chosen cards, A4/Letter. Urdu labels render joined and in the right order (Noto Naskh Arabic, drawn as images); verified against a Chromium reference. Style is Naskh, not Nastaliq | ✅ |
| UI design system (§3) | Interactive prototype of the Kindo visual language (Welcome, Child Home, Parent Plan, Needs board with sent and Feelings states, Parent alert feed with detail and lock-screen push, Family card game with celebration and parent add-card flow) in `design/prototype`, measured from the approved reference. Original placeholder art; not yet in Flutter | 🟡 design reference only |
| Hosting (§6.1) | Database queue, cPanel cron lines and `schedule:run` documented in README | ✅ |

## 2. Deliberate deviations from the spec

1. **Laravel 12 instead of 11.** Laravel 11 is end-of-life and has a critical auth-bypass advisory; 12 still supports PHP 8.2.
2. **Keys read from `config/kindo.php`**, not `env()` at runtime (needed for `config:cache`).
3. **Gemini:** key sent in a header, not the URL; model configurable, default `gemini-2.0-flash` (1.5-flash is retired).
4. **Age calculation** reversed (`dob->diffInYears(now())`); the spec's form returns a negative number.
5. **`dietary_chaining_tips`** now has its own AI output field (spec stored `parent_tips` there). `language_dominance` is now filled too.
6. **Unique index** on `ai_diagnostic_reports (child_id, report_week_start)`.
7. **`child/alert` requires login** and is rate-limited (spec endpoint was open).
8. **PDF font** is DejaVu Sans (Latin) instead of Helvetica Neue; Urdu labels use bundled Noto Naskh Arabic (SIL OFL) as images, not Jameel Noori Nastaleeq.

## 3. In progress right now

Nothing open. Last completed: Urdu in the PECS PDF. DomPDF cannot shape Arabic-script text, so `UrduShaper` joins the letters itself and `UrduText` draws each label to a PNG with GD/FreeType. True Nastaliq style is not possible this way (it needs a HarfBuzz-class shaper), so Naskh is used.

## 4. Not done — backend (can be built in this repo)

| Spec item | Gap |
|---|---|
| Syllabus & Camera importer (§5.2, Pro) | No syllabus endpoints at all; no image upload, no Groq/Gemini OCR; tables exist but unused |
| Therapist/teacher portal (§5.3) | No 6-digit access code, no read-only view, no assigned exercises |
| WhatsApp brag cards (§5.3) | No milestone aggregation or shareable card generation |
| First-Then routine engine (§5.1) | No data model or endpoints |
| Desensitization Sound Lab (§5.1) | Nothing |
| Free-tier "basic accuracy score" (§4) | No stats endpoint |
| Subscriptions / billing (§4) | `subscription_tier` exists, but nothing sets it (no Play/App Store receipt or payment handling) |
| Account basics | No password reset, email verification, profile update/delete |
| CRUD gaps | Update/delete child, delete family member, custom AAC cards, report history list |
| Needs analytics for parent | No potty-timing summary endpoint beyond the AI report; no alert-feed history, reply-time or "seen" endpoints, and acknowledging does not yet notify the child (see `design/README.md`) |
| Tier gating of lesson content (§4) | Urdu letters Alif–Se, 50 CVC words etc. are content/client rules; not modelled server-side |
| Seed content | No icon/audio assets, no Urdu letter or CVC word data |
| Admin | No admin panel, no abuse/moderation controls |

## 5. Not done — mobile apps (📱 outside this repo)

Kindo Junior and Kindo Parent apps in full (the visual style now exists as a prototype in `design/`, see §1): sensory-safe palette variant, Nastaliq tracing engine with snap-to-spline and dot placement (§5.2), phonetic keyboard, offline-first SQLite, family face/voice matching game, AAC board UI, sound lab, haptics/audio feedback, client-side image/audio compression, the §9.1 Dart dispatcher, and the §9.2 Android notification-channel code. The server contracts they need (alerts, sync, device tokens) already exist.

## 6. Setup items only you can supply

- Groq and Gemini API keys (3 each) in `.env`
- Firebase project id + service-account JSON at `storage/app/firebase-service-account.json`
- Hosting (GoDaddy) deployment, domain, and cron jobs
- Fonts / icons / audio assets and their licences
- Decision (optional): true Nastaliq style in print needs a real text shaper (e.g. rendering labels in the mobile app and uploading them, or a Chromium/HarfBuzz-based PDF engine, which shared hosting rarely allows)

## 7. Working agreement

New work is done on a branch from `main`, tested, pushed, and merged into `main` by me once tests pass. `main` should be set as the default branch in GitHub settings (I cannot change that setting).
