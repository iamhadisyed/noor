# Kindo Web Portal and Super Admin — Product Spec

Status: draft for approval · Owner: product · Scope: web portal for parents, super admin console, and the API changes that keep the parent app and the web portal identical.

## 1. Goals

1. Parents can sign in on the web **or** the app and see and do exactly the same things.
2. Parents can build lessons for ordinary children and for children who need extra support, attach **images and sound** to each task, and be stopped from publishing a task that needs sound but has none.
3. Parents can see reports: progress, tracing steadiness, family-card game, needs alerts.
4. The super admin (the platform owner) can see all reporting across the platform, manage accounts, and review content, without being able to casually browse children's photos or voices.
5. Sign-in uses only an email and a one-time code. No passwords.

## 2. Roles

| Role | Who | Can do |
|---|---|---|
| `parent` | Mother, father, guardian (several can share one household) | Everything about their own children, lessons, media, alerts, reports, plan |
| `super_admin` | Platform owner | Platform reporting, account directory, suspend/restore accounts, content review, audit log, plan overrides |
| `therapist` / `teacher` (later, spec §5.3) | Invited by a parent with a 6-digit code | Read progress and assign exercises for one child, read-only otherwise |

A super admin is identified by the `SUPER_ADMIN_EMAILS` setting (comma-separated). It cannot be requested through the UI. Everyone else is a parent.

## 3. One API, two clients (parity by construction)

The web portal is a browser client of the **same `/api/v1` REST API** the parent app uses. There is no separate "web backend", so a feature that exists in the API exists in both. Rules that keep it true:

- Every new capability ships as an API endpoint first, with a test; the web and the app only call it.
- Validation lives in the API (for example "a task that needs sound must have sound"); clients only mirror the messages.
- The parity matrix below is part of the definition of done for every feature.

| Capability | API | Parent app | Web portal |
|---|---|---|---|
| Email code sign-in, sessions | `/auth/*`, `/me` | yes | yes |
| Children and support profile | `/children` | yes | yes |
| Lesson builder, tasks, media | `/children/{id}/lessons`, `/lessons/{id}/tasks`, `/tasks/{id}/media` | yes | yes |
| Starter templates | `/lesson-templates` | yes | yes |
| Reports | `/children/{id}/reports/*` | yes | yes |
| Alerts feed | `/children/{id}/needs` | yes | yes |
| Family cards | `/children/{id}/family`, `/family/{id}` | yes | API ready; portal screen not built yet |
| Household members | `/household/*` | yes | yes |
| Data export / delete | `/me/export`, `/children/{id}` DELETE | yes | yes |
| Super admin console | `/admin/*` | no | web only |

## 4. Sign-in (email and one-time code)

1. The user enters an email. `POST /auth/code` always answers the same neutral message, whether or not the email has an account (no account enumeration).
2. A 6-digit code is emailed. It is stored only as a hash, expires after **10 minutes**, can be tried **5 times**, and is single use.
3. `POST /auth/verify {email, code, device_name}` returns a bearer token. The first successful verify creates the account and marks the email verified.
4. Limits: one new code per email per 60 seconds, 5 requests per minute per email and IP.
5. Tokens expire after 30 days of the token being issued; the user can list and revoke their sessions. Suspended accounts cannot sign in.
6. Super admin sessions are shorter (12 hours) and need the code again after expiry.
7. Every sign-in, sign-out and suspension is written to the audit log.

## 5. Parent portal

### 5.1 Children and support level
Each child has a **track**: `standard` or `special`. A special track carries a support profile (all optional, all editable later):

| Setting | Meaning |
|---|---|
| `neurotype` | autism spectrum, speech delay, ADHD, Down syndrome, other (already in the schema) |
| `sensory_mode` | calmer palette, no motion, no sound effects by default |
| `magnet_px` | tracing magnet strength 15 to 40 (matches the tracing assist screen) |
| `pace` | slow, normal |
| `max_choices` | 2, 3 or 4 answer options per question |
| `no_timers` | never show countdowns |
| `first_then` | show the visual First, Then routine card before each lesson |
| `praise` | quiet or lively |

Lessons inherit the child's profile and can override it for a single lesson.

### 5.2 Lesson builder
- A **lesson** belongs to one child, has a title, language (Urdu, English or both), a week, and is a draft until published.
- A lesson has an ordered list of **tasks**. Task types: trace a letter, spell a word, pick the picture, match a face, listen and repeat, routine step.
- Each task has instructions, a target (letter or word), an optional **image** (JPEG, resized to 600 x 600, at most 300 KB) and an optional **sound** (at most 150 KB, about 3 to 10 seconds).
- **Sound rule:** a task is flagged *needs sound* by its type (listen and repeat, spell a word) or by the parent ticking "this task needs sound". A lesson cannot be **published** while any task that needs sound has none, or any task whose type requires an image has none. The API answers `422` with a list of the exact tasks and what is missing; drafts can always be saved. The web shows a red "Missing sound" badge per task and a checklist before Publish; the app shows the same list.
- Sound can be recorded in the browser or uploaded; the app records on the device. Both are compressed client-side.
- **Starter templates** for standard and special tracks (for example "Alif to Se, slow pace, two choices"). Copying a template creates an editable draft for the child.
- Duplicate a lesson to another child, reorder tasks, archive instead of delete.

### 5.3 Reports
Per child and date range: practice time, accuracy, tracing steadiness by letter, family-card game results, needs alerts (count, time of day, average reply time), and the weekly AI report (Pro). Export as PDF or CSV. Parents see only their own household.

### 5.4 Household and sharing
Invite a second parent by email (same code sign-in); both receive alerts and either can reply. Invite a therapist or teacher by a 6-digit code with read-only access to one child (phase 3).

### 5.5 Privacy tools
**Built:** `GET /me/export` (a zip with `data.json` and every picture and voice), and account deletion confirmed by an emailed code (`POST /me/delete-code`, then `DELETE /me`), in both the portal Settings and the API for the app. Deletion removes children, lessons, files, devices, sessions and flags; the audit log keeps only that it happened, with no email or name. A delete code cannot sign anyone in, and a sign-in code cannot delete. Administrators cannot delete themselves here. Still to do: a per-photo consent record.

Export all data for a child, delete a child with everything attached (photos and voices included), and a visible consent record for family photos and voices.

## 6. Super admin console (web only)

The super admin sees **aggregates and operational data**, not children's media.

| Area | What it shows or does |
|---|---|
| Overview | Total accounts, active this week, new this week, children, lessons, alerts sent, average reply time, Pro vs free |
| Usage | Daily and weekly active users, lessons published, tasks by type, practice minutes |
| Quality | Tasks and lessons blocked for missing media, alert reply time distribution, tracing steadiness trend |
| Alerts | Volume by hour and by need, unanswered alerts older than 15 minutes |
| AI reports | Generated, failed, provider used (Groq or Gemini), failover and cooldown counts |
| System health | Queue depth, failed jobs, last scheduler run, storage used, email send failures |
| Accounts | Search by email; see role, plan, verified, last sign-in, number of children; suspend or restore; change plan |
| Content review | Flagged or reported media, with remove and warn actions (media is shown only on a flagged item) |
| Audit log | Who did what and when, filterable, exportable |
| Announcements | A message shown in the portal and app |

Support access to a parent's data is **not** impersonation. It is a time-limited read-only "support view" that the parent must approve, and every use is audited. This is phase 3.

## 7. Recommended features that were not in the brief

**P0, required before real families use it**
1. Private media: family and lesson images and sounds are stored on a private disk and served through short-lived signed links, never public URLs. **Done**, including family cards (`php artisan kindo:privatize-media` moves older files).
2. Audit log, account suspension, and neutral error messages on sign-in.
3. Consent and privacy: parental consent text, data export, full delete, retention rules. Children's data triggers COPPA, GDPR-K and local rules.
4. Backups of the database and media, and a restore test.
5. Per-user and per-IP rate limits; upload size and type checks; virus-safe file handling (re-encode images, transcode audio).

**P1, strongly recommended**
6. Household with two parents sharing alerts and replies.
7. Notification preferences (quiet hours for non-urgent items; urgent potty alerts always on).
8. Lesson templates library for standard and special tracks, maintained by the super admin.
9. Media library per child, so an image or sound can be reused across tasks.
10. Scheduling: assign lessons to days and a daily routine with the First, Then card.
11. Report PDF and CSV export; weekly email summary.
12. Urdu and English portal, right-to-left layout, keyboard and screen-reader support.
13. Billing: plan, receipts, and store receipt validation for the apps.

**P2, later**
14. Therapist and teacher access with a 6-digit code and assignments.
15. Camera-to-syllabus import creating a draft lesson.
16. Content review queue with automatic checks, and multi-language voices.
17. Offline edits in the app with conflict handling.

## 8. Data model additions

`users`: `role`, `status`, `last_login_at`, `password` becomes optional. New tables: `login_codes`, `audit_logs`, `lessons`, `lesson_tasks` (with `needs_audio`, `image_path`, `audio_path`), `lesson_templates`, `household_members`, `support_profiles` (per child), `media_flags`. The old `syllabus_modules` and `syllabus_words` tables from the first spec are superseded by `lessons` and `lesson_tasks` and are kept only until the syllabus importer targets lessons.

## 9. Delivery plan

| Phase | Deliverable | Done when |
|---|---|---|
| 1 | Email code sign-in, roles, sessions, audit log, this spec | **Done.** Tests pass; super admin from the setting can sign in |
| 2 | Support profile, lessons and tasks, media upload with the sound rule, templates | **Done.** Publishing is blocked with the exact missing items; tests cover both tracks |
| 3 | Reports and alerts endpoints for parents, admin reporting endpoints | **Done.** Numbers match seeded data in tests |
| 4 | Web portal (parent and admin screens) in the Kindo visual style | **Done.** Served at `/portal`; browser tests walk the parent journey, reports, admin console, XSS and phone widths. The Flutter parent app is not built yet; it will call the same API |
| 5 | Household, privacy tools, private media, backups, notification preferences | P0 list closed |
| 6 | Therapist access, billing, announcements, content review | P1 list closed |

## 10. Decisions taken and questions

Taken: email-code-only sign-in; super admin set by configuration; one shared API; the portal is a plain HTML and JavaScript client served from the same host (no build step, suits shared hosting); media stays private.

Please confirm:
1. Should a parent be able to see lessons created by another household? (Assumed no.)
2. Is the super admin allowed to open a child's photos for a flagged report only? (Assumed yes, audited.)
3. Which email provider will send the codes on the GoDaddy host (cPanel SMTP or a service such as Postmark or SES)?
4. Are therapists and teachers needed for launch or after?

## 10a. Mobile first: one source of truth

Most families will use the app, so the app is the main client and the portal must never disagree with it. The server owns the truth. Phones keep a local copy for offline use and catch up through `GET /sync` (cursor and deletes), send writes with an `Idempotency-Key` so retries are safe, and are nudged by a silent push when another device changes something. The contract is in `docs/SYNC.md`; a test plays a phone syncing in steps against a web client editing the same data and requires the same result as a fresh install.

## 10b. Decisions confirmed by the owner

- **Email**: codes are sent over SMTP (cPanel mailbox or any SMTP service); no third-party mail API.
- **Super admin may see all parent-posted content**, any time, not only flagged items, because it is children's content. Every lesson the admin opens, and every decision, is written to the audit log.
- **Bad words are watched**: parent-written text (lesson, task and family-card text) is checked against a built-in list plus words the admin adds. A match never blocks saving; it opens a flag, keeps the lesson off the child's device (publish is refused, a published lesson returns to draft) and waits for the admin to dismiss it, remove the content, or remove it and make the parent inactive.
- **Accounts can be made inactive** by the super admin with a written reason. Inactive means signed out everywhere at once and unable to sign in until made active again.
- **Terms and conditions** must be read and accepted before a new account is created (the sign-in code stays valid while the parent reads). Acceptance and version are stored; bumping `config/legal.php` `version` asks every parent again. The text in `config/legal.php` is a draft and needs legal review.

Not yet covered: the filter reads text only (pictures and sounds are reviewed by a person); parents are not emailed when content is removed.

## 11. Decisions made while building phase 2

- The free plan allows 3 lessons per child (`kindo.free_limits.lessons_per_child`); Pro is unlimited. This number is an assumption: change it in one place.
- Task rules: spell a word, match a face and listen and repeat require sound; pick the picture, match a face and routine step require a picture; any task can also be marked *needs sound* by the parent.
- Editing a published lesson so it is no longer complete (removing a sound, deleting a task's picture) returns it to draft at once, so a child never meets a broken task.
- Audio is validated by content, not file name, and capped at 150 KB. The server cannot transcode on shared hosting, so apps and the browser compress before upload.
- Pictures are re-encoded server-side to a JPEG of at most 600 px, which strips metadata and anything hidden in the file.
- Lesson media is on the private disk. Family-card media now uses the same private disk and signed links (phase 5).
