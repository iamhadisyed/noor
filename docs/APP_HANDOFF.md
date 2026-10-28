# Hand-off checklist for the apps team

For whoever builds **Kindo Junior** (the child's app) and **Kindo Parent** (the parent's app). The server and the parent web portal are finished and live in this repo. The apps are not. This page says what to build, in what order, and which server rule or document each part must follow.

Everything goes through one REST API, `/api/v1`. The web portal uses exactly the same API, so anything the portal can do, the app can do, and the two stay in step by construction. Read these first:

| Document | What it gives you |
|---|---|
| `README.md` | the full endpoint table and server setup |
| `docs/SYNC.md` | the contract for staying in step with the server and working offline |
| `docs/GAMES.md` | earning play time, the eight games and how a game session runs |
| `docs/PROJECT_SCOPE.md` | what exists on the server and what is still missing |
| `docs/PORTAL_PRD.md` | product rules (roles, terms, privacy, what a specialist may see) |
| `design/prototype` | the approved visual language and the working tracing prototype |

Tick a box only when it works against a real server, not a mock.

---

## 0. Set-up before any code

- [ ] A test server with a real `.env` (`APP_URL` must be the public https address, because picture and sound links are signed with it). Ask the owner for one.
- [ ] A Firebase project and the two config files for Android and iOS. The server needs the service account (`FCM_PROJECT_ID`, `FCM_SERVICE_ACCOUNT_PATH`). **Real push has never been sent from this server**, so the first end-to-end push test is yours and the owner's.
- [ ] A test parent account. Sign in by email code; with `MAIL_MAILER=log` the code is written to `storage/logs/laravel.log`.
- [ ] The two Android notification channels and the iOS critical-alert entitlement (see section 5).

## 1. Rules that apply to every request

- Base path `/api/v1`, JSON in and out, header `Accept: application/json`, header `Authorization: Bearer <token>`.
- Files go as `multipart/form-data`. To change a record **with** files from a phone, use `POST` (the routes accept `POST` and `PATCH`).
- Writes that may be queued offline carry an `Idempotency-Key` header (8 to 80 characters, for example `<device>-<counter>`). Resending the same request with the same key is safe. See `docs/SYNC.md` section 3.
- Errors you must handle:

| Status | Meaning | What the app does |
|---|---|---|
| 401 | token expired or revoked | sign the parent out and go to sign-in |
| 402 | plan limit; body has `upgrade: true` and a plain `message` | show the message and the plan screen; never retry |
| 403 | wrong role, or an account marked inactive | show the message |
| 404 | not found **or not yours** (the server never says which) | treat as gone |
| 422 | validation; body has `message` and `errors` | show `message`; for lessons it lists exactly what is missing |
| 423 | a game is locked; body has `reason` | show the reason (see section 4) |
| 429 | rate limit | back off |

- Messages are written for parents. Show `message` as is.
- Picture and sound links (`*_url`) are signed and **expire after 15 minutes**. Download media when you sync and keep the file. Never store the link.
- Dates are ISO 8601 in UTC. Reward "today" and quiet hours follow the server's `APP_TIMEZONE` and the parent's own timezone setting.

## 2. Kindo Parent app

### 2.1 Sign-in and account
- [ ] `POST auth/code {email}` then `POST auth/verify {email, code}` returns a bearer token. The answer to `auth/code` is always neutral, so do not try to tell the parent whether the email exists.
- [ ] First sign-in of a new account: `auth/verify` answers 422 with `terms_required: true`. Show the terms (`GET legal/terms`), then send the **same code again** with `accept_terms: true`.
- [ ] Existing parent after a terms change: `GET me` has `terms_required`; show the terms and `POST me/terms`.
- [ ] 6-digit codes are single-use and short-lived; resend has a cooldown (about 60 seconds). Show it.
- [ ] Register the phone for push after sign-in: `POST devices {token, device_type: android|ios}`. Re-send when Firebase rotates the token.
- [ ] Settings: edit profile `PATCH me`; list and sign out other devices `GET/DELETE me/sessions`; notification settings `GET/PATCH me/notifications` (quiet hours with timezone, muted kinds); data export `GET me/export` (a zip); delete account is two steps, `POST me/delete-code` (emails a code) then `DELETE me` with that code.

### 2.2 Children and support profile
- [ ] `GET/POST children`, `GET/PATCH/DELETE children/{id}`. Free plan: 1 child; Pro: 4.
- [ ] `track` is `standard` or `special`. The **support profile** (`support`) holds `magnet_px`, `pace`, `max_choices`, `no_timers`, `first_then`, `praise`, `guide` (none, dotted, arrows) and `fade_assist`. `GET meta` returns the defaults for both tracks and the upload limits; build the settings screen from it.
- [ ] Only the owner can delete a child or the account. Other family members cannot.

### 2.3 Lessons and tasks (the builder)
- [ ] `GET task-types` gives each task type's rules (which need a picture, which need a sound, whether there is a target). **Drive the form from this**, do not hard-code it.
- [ ] Lessons: list, create, edit, duplicate, publish, unpublish, archive (`DELETE` archives). Starter templates: `GET lesson-templates`, then `POST children/{id}/lessons/from-template/{id}`.
- [ ] Tasks: create, edit, reorder, delete; media with `POST tasks/{id}/media` (`kind=image` or `audio`). Images are re-encoded to a 600 px JPEG by the server; sounds are checked by content and must be 150 KB or less. Compress on the phone first.
- [ ] `POST lessons/{id}/publish` answers 422 with the exact tasks and what each lacks. Show it as a checklist. `GET lessons/{id}/readiness` gives the same list without publishing.
- [ ] A lesson held for moderator review cannot be published until the review is finished; a bad-word match never blocks **saving**, only publishing.
- [ ] Free plan: 3 own lessons per child (402 after that).
- [ ] **Pro: lesson from a photo.** `POST children/{id}/lessons/import` (multipart `photo`, optional `language`, `week_number`) returns a **draft** lesson. Tell the parent plainly that the photo is read once and not kept. 422 means the page could not be read; offer to retake. Limit: 10 per hour.

### 2.4 Alerts (the most important screen)
- [ ] Alerts arrive as a push (section 5) **and** through sync. `GET children/{id}/needs` is the live feed.
- [ ] Alert states are `sent`, `seen`, `on_the_way`. Mark seen with `POST needs/{id}/seen` when the parent opens it, and answer with `POST needs/{id}/acknowledge`. Show who replied and how fast.
- [ ] Notification actions on the push: acknowledge and call (`actions: "ack,call"`).
- [ ] Urgent needs (potty, pain, help) are always pushed, even in quiet hours; the parent can mute other kinds. The server decides, the app only displays.

### 2.5 Family cards
- [ ] `GET/POST children/{id}/family`, `POST family/{id}` (change names or swap a file), `DELETE family/{id}`. The create call needs `consent=1`, so the screen must ask the parent to confirm they may use this person's photo and voice. Free plan: 2 cards.
- [ ] Photo is re-encoded to 600 px; voice is 150 KB at most.

### 2.6 Picture cards (AAC)
- [ ] `GET aac-cards` returns the stock cards and the parent's own. Stock cards have `icon_path` (an asset path; the stock icon pictures are **not shipped yet**, see section 8). The parent's own cards have `custom: true` and signed `icon_url` / `audio_url`.
- [ ] Create, edit and delete own cards: `POST children/{id}/aac-cards`, `POST|PATCH aac-cards/{id}`, `DELETE aac-cards/{id}` (multipart: `english_label`, `urdu_label` optional, `category` one of `needs`, `food`, `emotions`, `activities`, `icon`, `audio` optional). Free plan: 3 per child (402 after). Words on the blocked list are refused with 422.
- [ ] Printable PECS sheet: `GET pecs.pdf` (free plan gets the four core cards).

### 2.7 Rewards, games and specialists
- [ ] Rewards screen: `GET children/{id}/rewards` (balance, rules, recent minutes, each game's state). Settings: `PATCH children/{id}/rewards/settings`. Bonus minutes: `POST children/{id}/rewards/bonus`.
- [ ] Specialists: `GET/POST children/{id}/specialists` (the parent makes a 6-digit code to give a therapist or teacher), `POST specialists/{id}/new-code`, `DELETE specialists/{id}` (ends access at once). Exercises they set arrive as `assignment` in sync; mark done with `POST assignments/{id}/done`.
- [ ] A therapist signs in to the **same app** and enters the code (`POST specialist/redeem`); they then use `specialist/children...` (read-only summary, no alerts, no photos, no family; they can set exercises).

### 2.8 Reports, milestones and sharing
- [ ] Reports: `GET children/{id}/insights/summary` and `insights/daily` (query `days`, `tz`), `insights/export.csv`, `insights/report.pdf` (printable A4 report).
- [ ] Letter by letter: `GET children/{id}/letters`.
- [ ] Celebrate: `GET children/{id}/milestones`, then `GET children/{id}/milestones/{key}/card.png?lang=en|ur&name=0|1`. The response is a 1080 x 1080 PNG. **Share it through the phone's share sheet** (WhatsApp appears there). Let the parent switch the language and hide the child's name before sharing. The card carries no photo or other detail.
- [ ] Pro: AI report `POST children/{id}/reports` (queued, returns 202) then `GET children/{id}/reports/latest`. 402 on the free plan.
- [ ] Household: invite a partner or grandparent (`POST household/invite`), accept or decline invitations, leave a family. Everyone in a household gets every change.
- [ ] Plan: `GET billing`, redeem a voucher code `POST billing/redeem`. **Pro is sold by voucher and admin grant only.** In-app purchases are not built (section 8).
- [ ] Announcements from the owner: `GET announcements`, `POST announcements/{id}/dismiss`.

## 3. Kindo Junior app (the child's app)

Design rule: no clutter, no timers when `no_timers` is on, slow pace when `pace` is slow, praise style from `praise`, number of choices from `max_choices` (2 to 4). All of these come from the child's `support` object. See `design/prototype` for the look.

- [ ] **Pairing:** the child app uses the parent's sign-in on the same phone or tablet. Decide with the owner whether it is a mode inside Kindo Parent or a second app; the API does not care.
- [ ] **Needs board and alerts:** show stock and custom cards from `GET aac-cards`; a tap sends `POST child/alert {child_id, need_type, card_id?}`. It must work offline: queue it and send with an `Idempotency-Key`. Play the card's voice when the child taps it. A custom card can only be used for its own child.
- [ ] **Lessons:** play the child's published lessons from the sync data. Task types are in `GET task-types`. Everything must work offline once synced.
- [ ] **Tracing canvas** (see section 6).
- [ ] **Family card game:** match the face and the voice from the child's family cards.
- [ ] **Upload practice results:** `POST children/{id}/sessions` at the end of each session (section 6 for the body). The response carries `reward` (minutes earned and why); show a small, kind celebration.
- [ ] **Games** (section 4).

## 4. Games and play time

Read `docs/GAMES.md`. Summary:
- [ ] Show `GET children/{id}/rewards`: balance, and for each game `unlocked`, `locked`, `switched off` or `needs content`.
- [ ] `POST children/{id}/games/{game}/start` returns the **whole session** (rounds and answers) so it plays offline. A 423 carries `reason`: `no_minutes` (with `how_to_earn`), `switched_off` or `no_content`.
- [ ] Stop the game when `allowed_seconds` runs out. When it ends, `POST game-sessions/{id}/finish {rounds_played, correct, seconds_played}`. Only the time actually played is spent. Reporting twice changes nothing.
- [ ] Draw the icons yourself: the server sends only icon names (`apple`, `ball` ...) in `GET games/library`, with English and Urdu names. Animal pictures and sounds come as signed links; cache them.
- [ ] Rewards are earned by **practice**, never by game scores. Do not add a score-for-minutes loop.

## 5. Push notifications (Firebase)

- [ ] **Alert push** (`data.type = "need_alert"`): fields `log_id`, `child_id`, `need_type`, `actions`. Android: high priority on channel `urgent_needs_channel` (create it at install, with the `alert_tone` sound). iOS: critical interruption level (needs Apple's critical-alerts entitlement).
- [ ] **Silent sync nudge** (`data.type = "sync"`, no banner): run a sync. The server sends at most one every 5 seconds per parent. Pushes can be dropped, so also sync on app start, on return to the foreground, and on a timer.
- [ ] Android 13 and later need the notification permission prompt; ask at a sensible moment, not on the first screen.
- [ ] Test on a real phone with the app killed, with battery saver on, and with do-not-disturb on.

## 6. Tracing: what the app sends and what it reads

- [ ] Per-child assist settings come from `support`: `magnet_px` (how strongly the stroke snaps, 15 to 40), `guide` (none, dotted or arrows) and `fade_assist` (reduce help as the child improves).
- [ ] At the end of a tracing session upload:

```json
POST /api/v1/children/3/sessions
{ "language": "urdu", "total_attempts": 10, "successful_attempts": 7, "duration_seconds": 300,
  "mistakes": [{ "target_string": "ب", "attempted_string": "پ", "stroke_deviation_score": 12.5, "mistake_type": "dot_misplacement" }],
  "letters":  [{ "letter": "ب", "attempts": 4, "successes": 1, "assisted": 3, "deviation": 12.5 }] }
```

`mistake_type` is one of `phonetic_substitution`, `letter_omission`, `dot_misplacement`, `stroke_inversion`. `letters` is optional, but it is what powers the Letter by letter report, the "letters mastered" milestones and the therapist's view, so **send it**. `assisted` counts the tries that needed the guide.
- [ ] The letter outline data (the stroke splines for the snap-to-guide engine) is **content that does not exist yet** (section 8). Until it does, the prototype's engine can be used for demos only.

## 7. Offline and sync

Follow `docs/SYNC.md` exactly. In short:
- [ ] First launch: `GET sync` returns everything plus a cursor. Afterwards `GET sync?cursor=N`. Apply changes **in order**, save the cursor after applying, repeat while `has_more` is true.
- [ ] If `reset` is true, throw away local data and use the snapshot in that response (happens after 30 days away, or when the parent joins or leaves a household).
- [ ] Entities: `child`, `lesson` (always whole, with tasks), `family_member`, `aac_card` (own cards only), `assignment`, `need`. `op: "delete"` means remove it locally.
- [ ] Queue offline writes in order, each with an `Idempotency-Key`; if one is refused with 422, show the parent what to fix and do not silently drop the rest.
- [ ] Last write wins field by field; the server's version is the truth after a conflict.

## 8. Not available yet (do not wait for these, but plan for them)

| Item | Status | Who |
|---|---|---|
| Stock AAC card icon pictures | the 13 stock cards have labels but no pictures | design |
| Animal pictures and sounds for the two listening games | the owner uploads them in the portal's Game library; games open when at least 4 animals have both | owner |
| Icon artwork for the counting, memory and pattern games | the server sends names only | design |
| Letter outline (spline) data for tracing | does not exist | content and design |
| In-app purchases (Google Play, App Store) | not built; needs store accounts and receipt checking. The plan ledger already has a `store` source | owner and apps team |
| Real push delivery | payload tested, never sent to real Firebase | owner and apps team |
| The Sound Lab | not specified in detail | owner |

## 9. Quality bar before release

- [ ] Every screen works with no internet after one sync, and recovers when the connection returns.
- [ ] Every screen has its empty, loading, error, offline and "plan limit" states.
- [ ] Urdu and English throughout, right-to-left layout for Urdu, a font that shapes Urdu correctly (the server's PDFs and cards use Noto Naskh Arabic).
- [ ] Touch targets of at least 48 dp; no information carried by colour alone; screen-reader labels on every control.
- [ ] A child can never reach the parent area, the plan screen, or delete anything without a parent gesture.
- [ ] No tracking or ad libraries in the child's app. Nothing about the child leaves the server except to the parent's own devices.
- [ ] No secrets in the app: only the bearer token, kept in the platform's secure storage.
- [ ] The terms and privacy text is shown at sign-up and accepted before the account exists.
- [ ] Test against the server with a **free** account and a **Pro** account, and with a household of two parents.

## 10. Questions to settle with the owner before building

1. Is the child's app a mode inside Kindo Parent or a separate app on the same phone?
2. Which languages besides Urdu and English, if any, and who supplies the translations?
3. Will the apps be released under one store listing or two?
4. Who records the stock voices (the 13 stock cards have no audio)?
5. Is a tablet layout needed for the child's app?
