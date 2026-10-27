# Games and rewards

Games are a reward, not a distraction. Practice earns **play minutes**; a game can only be started while there are minutes to spend, and it stops when they run out. Everything below is an API the app and the portal share.

## 1. Earning (all numbers are per-child settings)

A practice session is uploaded with `POST /children/{id}/sessions` (as before). The response now carries a `reward`:

```json
{ "id": 91, "reward": { "earned": 8, "reasons": [{"reason":"good_score","minutes":5},{"reason":"improvement","minutes":3}], "balance_minutes": 14, "capped": false } }
```

| Reason | When | Default |
|---|---|---|
| `good_score` | accuracy at or above the bar | 70% (60% for a child on the special track) → 5 min |
| `improvement` | accuracy beats the child's own average of their last 5 sessions by 10 points (needs 3 earlier sessions in 14 days) | 3 min, even if below the usual bar |
| `streak` | practised today and the days before, N days in a row (once a day) | 3 days → 3 min |
| `effort` | a session below the bar, if "reward for trying" is on | off (on for the special track) → 2 min |
| `parent_bonus` | the parent adds minutes by hand | 1–60 min, audited |

Guards: a session needs at least 5 tries (3 on the special track) to earn anything; at most 30 minutes can be earned a day; at most 60 can be saved up. A retried upload (same `Idempotency-Key`, or the same session) can never pay twice, because each reward row is tied to the session id. "Today" follows `APP_TIMEZONE`, so set it (for Pakistan: `Asia/Karachi`).

## 2. Playing

```
GET  /games                                  catalog, and whether this install has the content each game needs
GET  /games/library                          icon names and the animals (signed picture and sound links) for the app to cache
GET  /children/{id}/rewards                  balance, rules, ledger, each game's state: unlocked | locked | switched off | needs content, level, plays
POST /children/{id}/games/{game}/start       {rounds?: 3-20}  → session id, level, allowed_seconds, rounds[]   (423 if locked, with a reason)
POST /game-sessions/{id}/finish              {rounds_played, correct, seconds_played} → minutes spent, balance, new level
```

- `423 Locked` carries `reason`: `no_minutes` (with `how_to_earn`), `switched_off`, or `no_content`.
- The app receives the **whole session at once**, answers included, so it plays offline and reports at the end. Rewards are earned by practice, never by game scores, so there is nothing to gain by peeking.
- Only the time actually played is spent (rounded up to the minute, capped at what was allowed). A game that is never reported spends nothing. Reporting twice changes nothing.
- **Adaptive difficulty** per child and game, levels 1 to 5: 85% or better moves up, below 50% moves down, otherwise stays. Sessions of fewer than 5 rounds do not change the level.
- Number of answer choices follows the child's support profile (2 to 4). "Odd one out" always shows at least 3.

## 3. The games

| Game | Category | What the child does |
|---|---|---|
| `count` | Maths | counts pictures, picks the number (1–3 up to 1–15) |
| `add_sub` | Maths | small sums with pictures: add to 5, to 10, take-away, up to 20 |
| `number_order` | Maths | fills the gap in a number line: by 1, 2, 5, 10, counting down |
| `animal_sound` | Listening | hears a sound, picks the animal (a roar → the lion) |
| `sound_guess` | Listening | sees the animal, picks the sound it makes |
| `memory_match` | Brain | flips cards to find pairs (2 up to 8 pairs) |
| `odd_one_out` | Brain | picks the one that does not belong |
| `pattern` | Brain | finishes a pattern (AB, AAB, ABC, ABB, ABBC) |

Round shapes are in `app/Services/GameEngine.php`; the tests check every game at every level and choice count (valid answers, no duplicate options, correct maths).

## 4. Content that has to be supplied

- **Animal pictures and sounds.** The two listening games use the animal library. The 12 animal names are seeded (`php artisan db:seed --class=GameItemSeeder` on an existing install); the super admin uploads a picture and the sound each animal makes in **Game library**. An animal is used only when it has both, and the listening games open when at least 4 are ready. Use sounds you have the right to use (your own recordings, or public-domain / CC0 libraries) and keep them under 150 KB (about 3 to 5 seconds). No animal sounds are shipped with the code.
- **Icon artwork.** Counting, add and subtract, memory, odd-one-out and pattern use named icons (apple, ball, circle ...). The server sends only keys and names in English and Urdu (`GET /games/library`); the app draws them, so original artwork has to come from design.
- **Game screens in the app** are not built yet (the app is not in this repo). The API above is the contract.

## 5. Parent controls (portal **Rewards** page and the same API)
Rewards on or off, the bar for a good session, minutes per good session, bonus for improving, reward for trying, daily limit, longest single play, switching individual games off, and bonus minutes by hand. The balance is part of the child record, so it syncs to every device (`GET /sync`).
