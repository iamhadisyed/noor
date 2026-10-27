// Rewards and games: practice earns play time, the parent sets the rules, the admin stocks the animal library.
const path = require('path');
const { BASE, OUT, TMP, launch, login, check, picture, wav } = require('./lib.cjs');
(async () => {
  const pic = path.join(TMP, 'lion.png'); picture(pic);
  const snd = path.join(TMP, 'roar.wav'); wav(snd);
  const { browser, page, errors } = await launch();
  const email = `games${Date.now()}@kindo.test`;
  await login(page, email);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Games Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Ayan'); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  const api = (method, url, body) => page.evaluate(async ([m, u, b]) => {
    const t = localStorage.getItem('kindo.token');
    const res = await fetch('/api/v1' + u, { method: m, headers: { Authorization: 'Bearer ' + t, 'Content-Type': 'application/json', Accept: 'application/json' }, body: b ? JSON.stringify(b) : undefined });
    return { status: res.status, body: await res.json() };
  }, [method, url, body]);
  const kid = (await api('GET', '/children')).body[0];

  await page.goto(`${BASE}/portal/#/rewards`); await page.waitForSelector('.tiles'); await page.waitForTimeout(500);
  check('a new child has no play time and every game is locked', /0\s*min/.test(await page.textContent('.tile.t1')) && (await page.locator('[data-game] .chip:has-text("Locked")').count()) >= 5);
  check('the sound games say they need content', (await page.locator('[data-game="animal_sound"] .chip:has-text("Needs content")').count()) === 1);
  const locked = await api('POST', `/children/${kid.id}/games/count/start`);
  check('the server refuses to start a game with no minutes', locked.status === 423 && locked.body.reason === 'no_minutes');

  const practice = await api('POST', `/children/${kid.id}/sessions`, { language: 'urdu', total_attempts: 10, successful_attempts: 9, duration_seconds: 100 });
  check('a good practice session pays out', practice.body.reward.earned === 5);
  await page.reload(); await page.waitForSelector('.tiles'); await page.waitForTimeout(500);
  check('the parent sees the minutes and the games unlock', /5\s*min/.test(await page.textContent('.tile.t1')) && (await page.locator('[data-game="count"] .chip:has-text("Unlocked")').count()) === 1);
  check('the history says why', /Good score/.test(await page.textContent('#content')));
  await page.screenshot({ path: path.join(OUT, 'rewards.png'), fullPage: true });

  await page.fill('#b-min', '10'); await page.fill('#b-note', 'Kind to his sister'); await page.click('[data-act=bonus]'); await page.waitForTimeout(800);
  check('a bonus from the parent adds minutes', /15\s*min/.test(await page.textContent('.tile.t1')) && /Bonus from you/.test(await page.textContent('#content')));

  await page.fill('#r-thr', '95'); await page.click('[data-act=save-rules]'); await page.waitForTimeout(700);
  const after = await api('POST', `/children/${kid.id}/sessions`, { language: 'urdu', total_attempts: 10, successful_attempts: 9, duration_seconds: 100 });
  check('a stricter bar means a 90% session no longer pays', !after.body.reward.reasons.some((r) => r.reason === 'good_score'));

  await page.click('[data-game="add_sub"] button.switch'); await page.waitForTimeout(700);
  check('a single game can be switched off', (await page.locator('[data-game="add_sub"] .chip:has-text("Switched off")').count()) === 1 && (await api('POST', `/children/${kid.id}/games/add_sub/start`)).status === 423);

  const run = await api('POST', `/children/${kid.id}/games/count/start`);
  check('an unlocked game hands over its rounds and a time allowance', run.status === 201 && run.body.rounds.length === 10 && run.body.allowed_seconds > 0);
  await api('POST', `/game-sessions/${run.body.session_id}/finish`, { rounds_played: 10, correct: 10, seconds_played: 70 });
  await page.reload(); await page.waitForSelector('.tiles'); await page.waitForTimeout(500);
  check('playing spends only the time played and raises the level', /13\s*min/.test(await page.textContent('.tile.t1')) && /level 2/.test(await page.textContent('[data-game="count"]')));

  // the administrator stocks the library
  const a = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const ap = await a.newPage();
  await login(ap, 'admin@kindo.test'); await ap.waitForSelector('.kpis');
  await ap.click('nav.nav >> text=Game library'); await ap.waitForSelector('[data-slug=lion]');
  check('the library lists the animals as incomplete', /0 of 12/.test(await ap.textContent('.kpis')));
  for (const slug of ['lion', 'cow', 'dog', 'cat']) {
    const row = ap.locator(`[data-slug=${slug}]`);
    await row.locator('input[data-slot=image]').setInputFiles(pic); await ap.waitForTimeout(700);
    await ap.locator(`[data-slug=${slug}] input[data-slot=audio]`).setInputFiles(snd); await ap.waitForTimeout(700);
  }
  check('four finished animals open the sound games', /4 of 12/.test(await ap.textContent('.kpis')) && /sound games are open/.test(await ap.textContent('.kpis')));
  await ap.screenshot({ path: path.join(OUT, 'game-library.png') });

  const sound = await api('POST', `/children/${kid.id}/games/animal_sound/start`, { rounds: 5 });
  check('the listening game now starts, with a signed sound and pictures', sound.status === 201 && sound.body.rounds[0].audio_url.includes('signature=') && sound.body.rounds[0].options.every((o) => o.image_url.includes('signature=')));
  const audio = await page.evaluate(async (u) => (await fetch(u)).headers.get('content-type'), sound.body.rounds[0].audio_url);
  check('the sound is served as audio', /audio\//.test(audio));
  const guess = await api('POST', `/children/${kid.id}/games/sound_guess/start`, { rounds: 5 });
  check('the reverse game (see the animal, pick the sound) starts too', guess.status === 201 && guess.body.rounds[0].options[0].audio_url.includes('signature='));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
