# Keeping the app and the web portal in step

For the mobile app developer. The web portal and the app use the same `/api/v1`, so they share every rule. This page covers how a phone stays current, works offline, and retries safely.

## 1. Catching up: `GET /sync`

```
GET /api/v1/sync                 first launch (or after "reset"): everything, plus a cursor
GET /api/v1/sync?cursor=412      afterwards: only what changed since 412
```

Response:

```json
{ "snapshot": false, "reset": false, "cursor": 431, "has_more": false,
  "changes": [
    { "entity": "lesson", "id": 7, "op": "upsert", "data": { "...whole lesson with tasks..." } },
    { "entity": "need",   "id": 90, "op": "delete" } ] }
```

- **Entities:** `child`, `lesson`, `family_member`, `need` (alerts from the last 30 days on a first call).
- A **lesson is always sent whole**, with its tasks and fresh picture and sound links. A change to a single task arrives as its lesson.
- `op: "delete"` means remove it locally. Archived lessons arrive as an upsert with `status: "archived"`; hide them.
- Apply changes **in order**, save the new `cursor` after applying, and repeat while `has_more` is true.
- If `reset` is true, your cursor was older than what the server keeps (30 days): throw away local data and use the snapshot in the same response.
- Picture and sound links expire after 15 minutes. **Download media when you sync** and keep the files; do not store the links.
- Only the signed-in parent's own data is ever returned.

## 2. When to sync
- App start, and when the app returns to the foreground.
- When a silent push arrives: `{"type": "sync"}` (data only, no banner). The server sends at most one every 5 seconds per parent, however many things changed.
- After your own queued writes succeed.
- Pushes can be dropped, so also sync on a timer (every few minutes while the app is open).

## 3. Working offline
Keep a local database and a queue of pending writes. Every write goes out with an `Idempotency-Key` header (any unique 8 to 80 characters, for example `<device>-<counter>`):

```
POST /api/v1/children/3/lessons
Idempotency-Key: pixel7-00042
```

- If the connection drops, **send the same request again with the same key.** The server answers with the first result (header `Idempotent-Replay: true`) instead of creating a second lesson.
- Reuse a key only for the identical request. A key reused for a different URL is refused (422).
- Keys are remembered for 2 days. Failed requests (4xx) are not remembered, so you can fix and resend with the same key.
- Send queued writes in the order the parent made them. If one is refused (422), show the parent what to fix; do not drop the rest silently.

## 4. Conflicts
Last write wins, field by field as sent. Rules that never bend, whichever device edits:
- A lesson cannot be published while a task is missing a required picture or sound; the server refuses with the exact list. If an edit on one device removes a required sound, a published lesson returns to draft and the next sync shows it.
- A lesson held for moderator review cannot be published until the review is done.
- After a conflict the app simply syncs; the server's version is the truth.

## 5. What is not in the feed yet
Practice sessions (uploaded by the child app with `POST children/{id}/sessions`), reports, and family-card media (still on the public disk until phase 5). Reports are fetched on demand.
