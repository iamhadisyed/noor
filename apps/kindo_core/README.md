# kindo_core

Shared Dart code for both Kindo apps (Parent and Junior). Pure Dart: no Flutter needed, so it is tested quickly and can run on the command line.

| Part | What it does |
|---|---|
| `ApiClient`, `KindoApi` | One typed method per server endpoint. Always asks for JSON, sends the bearer token, turns every failure into an `ApiException` (`needsUpgrade` for a plan limit, `lockedReason` for a locked game, `termsRequired` for sign-up, `isRetryable` for no connection). |
| models | `Child`, `Support`, `Lesson`, `LessonTask`, `AacCard`, `Need`, `FamilyMember`, `GameInfo`, `CalmActivity`, `Milestone`, `LetterStat`, `UserProfile`. |
| `SyncEngine` | Keeps a copy of the family's data on the phone from `GET /sync` (cursor, deletes, reset, paging) and saves it through a `LocalStore`. |
| `Outbox` | Writes made offline wait here and go out in order, each with its own `Idempotency-Key`; a refused write is set aside, never dropped silently. |
| `GameRunner`, `MemoryBoard` | Plays a game session the server created: scoring (first try only), retries, the clock, and the numbers for `finish`. |
| `KeyboardLayout` | The alphabet-order spelling keyboards from `GET /meta`. |
| `TracingEngine`, `StrokeLibrary`, `PracticeLog` | Judges a child's tracing (coverage, deviation, wrong direction, missing dots), pulls a line onto the shape (the magnet), and builds the practice session with per-letter results. The library holds simple geometric outlines for 0-9, A-Z and four Urdu letters; final Urdu calligraphy is design content. |

```
dart test                      # unit tests, no server needed
KINDO_BASE=http://127.0.0.1:8000/api/v1 KINDO_LOG=../../storage/logs/laravel.log dart test -t live
```

The live tests sign in with a code read from the server's log (`MAIL_MAILER=log`), then run the apps' whole contract: terms, children, lessons and tasks, alerts and retries, tracing sessions with letters, all 17 playable games, calm activities, picture cards, sync and reports.
