# Project demo graphics

Every picture made for this project, in one place, for demos and reviews. Nothing here is used by the running app (the 13 stock card pictures the app serves live in `public/aac`; the copies here are for showing).

| Folder | What it shows |
|---|---|
| `app-screens/` | The visual language for the two apps, drawn as a working HTML prototype (`design/prototype`) and captured here: welcome, child home, parent plan, needs board and feelings, parent alert feed with detail and lock-screen push, family card game with celebration and add-card flow, tracing canvas with dot lab and assist settings. Files starting with `_overview` put a whole flow on one sheet. |
| `portal-desktop/` | The parent web portal and super admin console at desktop size, captured by the browser tests: terms step, specialist code and view, rewards, game library, picture cards, vouchers, plan, announcements, household invite, notifications, privacy settings, admin content review, lesson editor with the alphabet spelling keyboard, letter-by-letter report, photo import, milestone card dialog. |
| `portal-phone/` | The same portal at phone width (375 px): home, lesson editor with the Urdu spelling keyboard, reports, alerts and the More menu. |
| `stock-cards/` | The 13 placeholder pictures for the stock picture cards (water, potty, milk, bed, food, sleep, hug, pain, help, happy, sad, scared, play) and a contact sheet. Original artwork, drawn by `php artisan kindo:stock-icons`. |
| `share-cards/` | Milestone cards a parent can share on WhatsApp, in English and Urdu. |
| `pdf-samples/` | A printable PECS sheet with all 13 cards, a progress report, and a picture of the first page of each. |
| `design-iterations/` | Earlier rounds of the same designs, kept to show how they were refined. |

## Refreshing the screenshots

`bash demo/refresh.sh` runs the browser test suite and copies its newest screenshots into `portal-desktop/`. (`app-screens/` come from `design/prototype`; open `design/prototype/index.html` in a browser.)
