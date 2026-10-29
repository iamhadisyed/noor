# Launch checklist

What is built is tested, but a few things can only be done by you, the owner. Work through this before real families use Kindo.

## 1. Accounts and passwords
- [ ] Set `ADMIN_EMAIL`, `ADMIN_PIN` (6 digits) and `SUPER_ADMIN_EMAILS` in `.env`, then `php artisan kindo:install`. The built-in defaults (`admin@kindo.local` / `482916`) exist **only when `APP_ENV` is not `production`**. Anyone who reads this repository knows them, so never use them on a live site.
- [ ] Demo accounts: set `DEMO_ENABLED=true`, `DEMO_PARENT_PIN`, `DEMO_CHILD_PIN` only if you want a public demo; otherwise leave them off. They reset every night.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL` with https, `APP_TIMEZONE` (for example `Asia/Karachi`).
- [ ] Working email (`MAIL_*`): sign-in codes, Pro instructions and account deletion all depend on it.

## 2. Taking payment for Pro
- [ ] Write `PAYMENT_INSTRUCTIONS` (your bank account, Easypaisa or JazzCash number) and `PAYMENT_CONTACT_EMAIL`.
- [ ] Decide `PRO_DEFAULT_DAYS` (365 = one year).
- [ ] Each time: open **Pro requests** in the owner console, check the money really arrived, tap **Make Pro**.
- [ ] Check with an accountant whether you need to register or charge tax. You can also give Pro by voucher code (Vouchers page).

## 3. Phone apps
- [ ] Firebase project, `google-services.json` (Android) and `GoogleService-Info.plist` (iOS), and the service account at `storage/app/firebase-service-account.json` (see `docs/APP_HANDOFF.md`). Without these, push alerts do not reach the parent's phone.
- [ ] Google Play ($25 once) and Apple Developer ($99 a year) accounts; store listings, screenshots (`apps/tool/live-demo` makes some), privacy answers.
- [ ] Build: `flutter build appbundle --dart-define=KINDO_API=https://YOUR-DOMAIN/api/v1`. Test on at least one real Android phone and one iPhone.
- [ ] Final artwork and sounds: icons and picture cards are placeholders (shapes and emoji); animal pictures and sounds are uploaded in **Game library**.

## 4. Adverts (Google AdMob)
Kindo is for children, so adverts are heavily restricted. What the code does: adverts are only for **free parents**, only in the **parent app** (never in the child's app), always **child-directed** and **not personalised**, and are **off** unless `ADS_ENABLED=true`. Demo, owner and Pro accounts never get them.
- [ ] Create an AdMob account and add both apps. When asked, say the apps are **directed at children** (Tag for Child-Directed Treatment). Choose a **max ad content rating of G**.
- [ ] Add the AdMob SDK (`google_mobile_ads`) to the Parent app and put the unit ids in `.env` (`ADMOB_ANDROID_APP_ID`, `ADMOB_IOS_APP_ID`, `ADMOB_BANNER_ANDROID`, `ADMOB_BANNER_IOS`). The placeholder box `AdSlot` in `apps/kindo_parent/lib/screens/family_tools.dart` is where the banner goes. **This SDK is not added yet and no advert has been tested.**
- [ ] Follow Google Play Families Policy and Apple Kids Category rules; Apple does not allow third-party adverts in the Kids category, so choose the category with that in mind.
- [ ] Update the privacy notice to say what the adverts collect.

## 5. Legal and safety
- [ ] Have a lawyer review `config/legal.php` (terms and privacy). It is a draft. Raise `version` to ask parents to accept a new text.
- [ ] Have a doctor and a child-development expert read `config/guidance.php` (autism guide), `config/learn.php` (Discover facts, especially the AI topic) and the AI report prompt. They are written as general information, with "ask your child's doctor" first and last, but they have not been medically reviewed.
- [ ] Children's data law applies in your country and in the countries your users live in (for example COPPA in the USA, GDPR-K in Europe). Kindo stores a child's first name, birth date and learning results, kept private and deletable by the parent. Decide who your "data controller" is and write it in the privacy notice.
- [ ] Review the blocked-word list in `config/moderation.php` for Urdu and English.

## 6. Running it
- [ ] Cron jobs (see README, shared hosting) for the scheduler and the queue.
- [ ] `KINDO_BACKUP_PASSWORD`, a copy of the backup somewhere other than the server (`docs/BACKUPS.md`), and a restore test.
- [ ] Watch the owner console **Health** page and the audit log.
- [ ] Rate limits and the PIN lockout are on; do not disable them.
