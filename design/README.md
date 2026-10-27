# Kindo UI prototype

Interactive, pixel-measured prototype of the Kindo visual language, built from the approved reference
(three screens: Welcome, Child Home, Parent Learning Plan). It is the visual spec for the Flutter apps.

Open `design/prototype/index.html` in a browser (no build step, no network; fonts are bundled).

- Side-by-side view of all three phones (scales to the window).
- `?screen=welcome`, `?screen=home` or `?screen=plan` shows one screen; on a phone-sized viewport it drops the device frame and fills the display.
- Interactive: tap a bar, drag the progress slider, tap the bottom navigation.
- `preview.png` is a screenshot of the side-by-side view.

## Needs board (`design/prototype/needs.html`)

Three states of the AAC board, same visual language:

1. **Board** (child): "What do you need?" with Urdu subtitle, category chips (All / Needs / Food / Feelings / Play), 2-column cards with illustration tile, English and Nastaliq label, and a round speaker button in the card notch. Free tier shows the four core cards unlocked; Hug and Pain are Pro (soft fill, PRO pill, lock button). Tap sends the alert and speaks the card; a toast with a 4-second **Undo** follows. Tapping a locked card gives a gentle wobble and "Ask a grown-up to unlock this card", never a buzzer.
2. **Alert sent**: shows the card, a check badge, "Mama is on the way" in English and Urdu, who was notified, a Sent / Seen / On the way stepper in the hatch-slider style, and "I'm okay now" / "Send again".
3. **Feelings tab** (Pro account): six emotion cards in the same grid.

`?screen=board|sent|feel` shows one screen. Card hit areas are extended to 64dp, Potty has a slow, low-amplitude pulse (disabled under `prefers-reduced-motion`), and success is sage green, not red or neon.

Open decisions: Potty stays one-touch as in the spec, protected only by Undo and the server rate limit (30 alerts/min); say if you want a confirm step. The speaker button both speaks and notifies; a "speak only" mode for practice would need its own control.

## Parent alert feed (`design/prototype/alerts.html`)

Three connected states of the parent app's alert experience:

1. **Feed**: header with the child and a day summary, a live **hero card** for the unanswered alert ("Ayan needs the potty", NEW badge, "I'm on it" and call buttons), then a "Today" sheet of answered alerts with the responder and reply time.
2. **Alert detail**: the need, who was notified, a Sent / Seen / On the way stepper, "I'm on my way", Call / Message, and a Pro teaser for the potty-timing pattern from the AI report.
3. **Lock-screen push**: the urgent notification with inline "I'm on it" and "Call Papa" actions, and the quieter earlier notification below it.

Replying from any of the three updates the other two (shared state): the hero turns calm lavender, the detail screen shows "You're on your way", the notification flips to REPLIED, a new row joins the feed, and an Undo toast appears. `?screen=feed|detail|lock` shows one screen.

Deviation from the first prototype: the hero title and subtitle use dark ink instead of white, because a time-critical alert must be readable (white on lilac is about 1.9:1).

### Backend gaps this design exposes
- The FCM payload currently says "Ayan needs: Potty / Tap to acknowledge"; the design's copy is "Ayan needs the potty / Tap to respond", with Android and iOS notification actions ("I'm on it", "Call").
- `POST needs/{id}/acknowledge` exists, but it does not yet notify the child app ("Mama is on the way") or record which parent replied.
- No "Seen" event: opening the alert should mark it seen for the stepper.
- Feed endpoints for per-day history, reply times and the "avg reply" figure do not exist yet, and the pattern teaser needs the Pro report data.

## Family card game "Apno Ki Pehchan" (`design/prototype/family.html`)

1. **Game (child)**: "Who is this?" with an Urdu line, a tilted photo card with a play button and the recorded 3-second voice clip, three large name choices (English and Urdu), and "Hear it again". A wrong pick gives a gentle wobble and "Let's try one more time!"; a right pick marks the choice with a check, dims the others and advances the round dashes. No red, no buzzer.
2. **Celebration**: "That's Mama!" (English and Urdu), a check badge, soft confetti that drifts slowly (off under `prefers-reduced-motion`), a star, the replayable voice clip, round progress, and "Next face".
3. **Add a family card (parent)**: photo preview with Retake, a relation picker that fills the shown name and its Urdu form, a 3-second voice recorder with countdown, a live free-tier meter ("1 of 2 free cards used", Pro unlocks unlimited), and Save.

`?screen=game|win|add` shows one screen. Voice playback and recording are visual only in the prototype.

Notes for the build: photos of children's relatives are personal data, so the app needs a clear parental consent line and a delete control; the photo is cropped client-side to 600 x 600 and the clip kept under 150 KB, matching the API limits.

### Backend gaps this design exposes
- Family cards can be listed and created, but not renamed, replaced or deleted; the screen needs update and delete.
- Game rounds are not recorded: `practice_sessions` has only `urdu` / `english`, so family-recognition results need a game type (or their own table) before the AI report can use them.
- The Pro meter needs the card count and limit returned by the API rather than computed on the device.

## Tracing canvas (`design/prototype/tracing.html`)

This one is a working prototype of the interaction, not only a picture: the stroke engine runs in the page, so it can be tried with a mouse or finger.

1. **Trace (child)**: the letter Be (ب) on a guide band with a glowing numbered start, direction chevrons, an animated hand, a magnet-zone halo, a progress bar and a phonics hint ("ب سے بلی, Be for Billi, the cat"). Touch near the start and follow the guide. The drawn line **snaps to the guide** while the finger stays within the magnetic threshold; drifting outside it just pauses progress with "Stay near the glow" and never fails. The raw finger trail is shown faintly so the smoothing is visible. On finishing, a "Beautiful!" badge shows a steadiness score (mean deviation from the guide, the value `practice_mistake_logs.stroke_deviation_score` is meant to hold) and "Place the dot" unlocks.
2. **Dot lab (child)**: the body of the letter is drawn first and the nuqta is a separate step, as in the spec. Drag one, two or three dots to the ring above or below the body: 1 below = Be (ب), 3 below = Pe (پ), 2 above = Te (ت), 3 above = Se (ث). Drops magnetise within a 52px radius; a wrong number gives a gentle "Let's try one more time!". Found letters collect in a strip.
3. **Tracing assist (parent)**: "Magnetic strength" from 15 to 40px (Light, Medium, Strong presets) with a live **shaky hand test** showing the finger path against what is drawn, plus toggles for the animated hand, the magnet zone, haptics, and dots as a separate step. Changing strength changes the trace screen immediately (zone width and tolerance).

`?screen=trace|dots|assist` shows one screen.

Known limits of the prototype: the guide is a hand-drawn Be, not an extracted Nastaliq outline (the real engine needs per-letter spline data from the font, and joined forms for the Pro ligature assembly); sound and haptics are not played here; the forward-only window lets a very fast scribble along the guide complete a stroke, which the app should limit by speed or by requiring continuous contact.

### Backend gaps this design exposes
- Practice sessions can store a stroke deviation per mistake but have no place for per-letter tracing results (steadiness, attempts, assist strength used). A per-child `tracing_settings` (threshold 15-40, hint, haptics, dots step) is needed so the parent's choice reaches the child device.
- Letter content (guide splines, base strokes and dot positions) needs a data format and a seed for the free set (Alif to Se) versus the Pro alphabet.

## Design tokens

| Token | Value | Use |
|---|---|---|
| lavender | `#CABAEF` | Welcome background, activity card, tiles |
| lavender-soft | `#D7C6F5` | secondary tile |
| lilac | `#EEADFE` | hero card |
| orchid | `#E697F2` | active nav item, selected bar, slider end |
| butter | `#FFF0AD` | highlight, yellow cards |
| cream | `#FCE9B4` | image tile behind the book |
| ink | `#120C15` | text, buttons, nav pill |
| ink-2 | `#2A242E` | inactive nav circles |
| mist | `#EEF2F5` | Child Home background |
| gray | `#8B8A90` | secondary text |

Typography: **Onest** for UI (closest to the reference's widths), **Lexend** for learning content such as phonics letters
(as the spec requires for dyslexia-friendly reading), **Noto Nastaliq Urdu** for Urdu text. Weights used: 400, 500, 600, 700, 800.
All three families are SIL OFL licensed.

Shape language: 36-38px card radii, 50-62px circular buttons, a concave notch where a circular button overlaps a card,
a 3px white device ring, 3px black "sticker" offset on bars, hatch-fill for progress.
Phone canvas is 426 x 918 px; every position in `styles.css` is an absolute measurement from the reference.

## Kindo adaptations

- Copy is Kindo's: "Welcome to Kindo", "Alif se Ye Mastery Hub", Urdu Letters / Phonics cards with family avatars, parent-side Urdu Letters / English Words / Potty Alerts tiles, and weekly practice time.
- The progress knob sits at the real value (78%) instead of the reference's fixed position.
- Touch targets: circular buttons are 58-62px visually but get a 4px invisible extension, giving the spec's 64dp minimum.

## Known gaps and decisions needed

- **Illustrations are original placeholders.** The reference artwork is someone else's work and cannot be reused; the mascot, trophy girl, avatars and tile images here are simple drawings made for this prototype. Final character art should come from an illustrator.
- **Contrast.** White title and subtitle on the lilac hero card (about 1.9:1) copy the reference but fail WCAG AA. Needs a decision (darker ink on the hero) before launch, especially for a parent-facing app.
- **Sensory-safe palette.** The reference's saturated orchid and lilac are livelier than the spec's oatmeal/sage palette. A `sensory_mode` variant (desaturated tokens, no motion) should be defined; the CSS already respects `prefers-reduced-motion`.
- Not yet designed in this style: subscription screen.
