# Kindo apps

Two Flutter apps on two shared packages. Everything goes through the same `/api/v1` as the web portal, so a change made on the phone shows up in the portal and the other way round (the apps keep a saved copy with `GET /sync`, and writes made offline wait in an outbox).

| Folder | What it is |
|---|---|
| `kindo_core` | Pure Dart (no Flutter): API client, models, sync engine, outbox, game runner, spelling keyboards, tracing engine, practice log. See its README. |
| `kindo_ui` | Flutter package both apps use: theme, session (sign-in, auto-sync, push hook), media cache, spelling keyboard, tracing canvas, breathing bubble, sign-in screen with the terms step, voice recorder, picture picker, Urdu font (Noto Naskh Arabic, SIL OFL). |
| `kindo_parent` | The parent's app: alerts, lesson builder, picture and family cards, rewards, reports and sharing, therapists, household, plan, settings. |
| `kindo_junior` | The child's app: picture board, lessons, games, calm activities, family card game. Behind a grown-ups-only gate for settings. Behaves by the child's support profile (no timers, slow pace, quiet praise, first-then, fewer choices). |
| `tool/live-demo` | Runs both web builds against a throwaway real server and takes screenshots (`demo/apps-live`). |

## Run them

```
cd apps/kindo_parent          # or kindo_junior
flutter pub get
flutter run --dart-define=KINDO_API=https://your-site.example/api/v1
```

`KINDO_API` is the server address (also changeable in the parent app under More, Server). Add `--dart-define=KINDO_PUSH=true` once the Firebase files are in place (`google-services.json` for Android, `GoogleService-Info.plist` for iOS); without them push quietly does nothing and the apps sync on a timer instead. Plain `http://` works in debug builds only (the debug manifest allows it); release builds need `https://`.

The child's app uses the parent's sign-in on the same device: the parent signs in once, then the child picks their name. Settings, sign-out and switching child sit behind a small sum.

## Test them

```
cd apps/kindo_core  && dart test                 # unit tests; add -t live against a server (see its README)
cd apps/kindo_ui    && flutter test              # keyboard, tracing canvas, media cache
cd apps/kindo_parent && flutter analyze && flutter test
cd apps/kindo_junior && flutter analyze && flutter test
apps/tool/live-demo/run.sh --build               # both apps in a browser against a real server, with screenshots
```

The widget tests run the real apps against a pretend server (a few canned answers), so they check what a parent or child sees and which requests go out. The live walk-through checks the same against the real Laravel app: sign-in, sync, a whole lesson with tracing and spelling, the play-time reward, games, calm activities, picture cards and the family game.

## Things to know

- Pictures and sounds are cached by their path plus a version code (`image_v`, `audio_v`, `icon_v`, `photo_v`), not by the signed link, so a link that changes every 15 minutes does not download the file again, and a replaced file does.
- Only the first try at an answer counts towards a score, and "say it", "listen and repeat" and routine steps are not scored (they would only measure whether the child pressed a button).
- Tracing is judged on the line between touch points, not just the points, so a quick swipe is not marked down.
- The pictures for games and routines are shapes, faces and emoji stand-ins (`kindo_ui/lib/src/widgets/art.dart`). Replace them with final artwork there.
- Stock picture cards have no recorded voice, so the child's phone says the word with its own voice (needs an Urdu voice installed for Urdu). A parent's own cards play the parent's recording.
- Games need the internet to start (the server makes the rounds); the rounds then play with no connection and the result is sent when there is one.
