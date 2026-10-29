# Kindo brand

The name is **Kindo** (نور, "noor", means light). The mark is a bold **K** with a small spark.

| | |
|---|---|
| Ink | `#120c15` |
| Orchid (the K on dark) | `#e697f2` |
| Lavender | `#cabaef` |
| Butter (the spark) | `#fff0ad` |
| Fonts | Lexend and Onest (web portal), Noto Naskh / Nastaliq for Urdu |

## Where the mark appears
- **Web portal:** browser tab icon, sign-in page, side menu, and the Apple touch icon (`public/brand/`, `public/favicon.ico`).
- **PDFs:** the printable picture cards and the progress report (header and footer).
- **Share cards** (milestone pictures) and **all emails**.
- **Apps:** launcher icons for Android and iOS and the web builds, the sign-in screen (`KindoMark` in `kindo_ui`). Kindo Parent uses the dark tile; Kindo Junior uses the lavender tile so the two are easy to tell apart.

## Changing the logo
Everything comes from one script: `python3 design/brand/make_brand.py` (needs Pillow). It redraws every PNG, the favicon and every app icon. To use your own artwork, either change the shapes in that script, or replace the files it writes:
- `public/brand/*.png`, `public/brand/logo.svg`, `public/favicon.ico`
- `apps/kindo_parent` and `apps/kindo_junior`: `android/app/src/main/res/mipmap-*/ic_launcher.png`, `ios/Runner/Assets.xcassets/AppIcon.appiconset/*`, `web/favicon.png`, `web/icons/*`
- the in-app `KindoMark` widget (`apps/kindo_ui/lib/src/widgets/kindo_mark.dart`) and the `K` drawn in `app/Services/BragCard.php` use the same mark.

App icons for the Play Store (512 px) and App Store (1024 px) are `public/brand/mark-512.png` and `mark-1024.png`. The App Store icon must have no transparency; the iOS files the script writes already follow that.
