# Portal end-to-end tests

Drive the real web portal in a browser against a throwaway local Laravel server. They cover the paths that matter most:
sign-in with an emailed code, onboarding, the lesson builder with picture and sound (including the "needs sound" publish rule and in-browser recording),
alerts and reports, the super admin console, suspension, cross-site-scripting, and phone-width layout.

```bash
npm i -g playwright && npx playwright install chromium   # once
tests/e2e/run.sh
```

`run.sh` starts the app on port 8099 with `MAIL_MAILER=log`, so the 6-digit code is read from `storage/logs/laravel.log`, exactly as a person would read it from email.
Screenshots go to `tests/e2e/.out/` (ignored by git). The PHP tests (`php artisan test`) run in CI; these browser tests are run by hand before a release.
