// Tester findings kept as a test: hostile text never runs as code, long names never push a phone screen sideways,
// and every page is reachable from the phone's bottom bar (More holds the rest).
const { BASE, launch, login, check } = require('./lib.cjs');
(async () => {
  const X = '<img src=x onerror=window.__xss=1>';
  const { browser, page, errors } = await launch({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true });
  let dialogs = 0; page.on('dialog', (d) => { dialogs++; d.dismiss(); });
  await login(page, `mobile${Date.now()}@kindo.test`);
  await page.waitForSelector('#nm'); await page.fill('#nm', 'Mama'); await page.click('text=Continue');
  await page.waitForSelector('.modal'); await page.fill('#cn', 'Kid' + X); await page.fill('#cd', '2020-05-04'); await page.click('.modal >> text=Add child'); await page.waitForSelector('.kid');

  await page.evaluate(async (X) => {
    const H = { Authorization: 'Bearer ' + localStorage.getItem('kindo.token'), 'Content-Type': 'application/json', Accept: 'application/json' };
    const kid = (await (await fetch('/api/v1/children', { headers: H })).json())[0];
    const l = await (await fetch(`/api/v1/children/${kid.id}/lessons`, { method: 'POST', headers: H, body: JSON.stringify({ title: X, description: X }) })).json();
    await fetch(`/api/v1/lessons/${l.id}/tasks`, { method: 'POST', headers: H, body: JSON.stringify({ type: 'trace_letter', title: X, instruction: X, target: X }) });
    await fetch('/api/v1/child/alert', { method: 'POST', headers: H, body: JSON.stringify({ child_id: kid.id, need_type: X.slice(0, 50) }) });
    await fetch(`/api/v1/children/${kid.id}/specialists`, { method: 'POST', headers: H, body: JSON.stringify({ role: 'therapist', label: X }) });
  }, X);

  const bad = [];
  for (const r of ['#/', '#/children', '#/lessons', '#/cards', '#/rewards', '#/specialists', '#/reports', '#/alerts', '#/settings']) {
    await page.goto(`${BASE}/portal/${r}`); await page.reload(); await page.waitForSelector('h1'); await page.waitForTimeout(700);
    const i = await page.evaluate(() => ({ xss: window.__xss === 1, over: document.documentElement.scrollWidth - window.innerWidth }));
    if (i.xss || i.over > 0) bad.push(`${r} ${JSON.stringify(i)}`);
  }
  check('hostile text never runs and nothing is wider than the phone', bad.length === 0 && dialogs === 0, bad.join(' | '));

  await page.goto(`${BASE}/portal/#/`); await page.reload(); await page.waitForSelector('.bottom-nav');
  check('the phone bar shows four pages and More', (await page.$$eval('.bottom-nav a', (a) => a.length)) === 4 && (await page.locator('.more-btn').count()) === 1);
  check('the Home label is not broken across lines', (await page.$$eval('.bottom-nav a span', (s) => s.every((x) => x.getBoundingClientRect().height < 20))));
  await page.click('.more-btn');
  const more = await page.$$eval('.more-sheet a', (a) => a.map((x) => x.textContent.trim()));
  check('More reaches Children, Rewards, Specialists, Reports and Settings', ['Children', 'Rewards', 'Specialists', 'Reports', 'Settings'].every((n) => more.includes(n)), more.join(','));
  await page.click('.more-sheet a:has-text("Reports")'); await page.waitForSelector('h1:has-text("Reports")');
  check('More closes after choosing and stays highlighted', (await page.$eval('.more-sheet', (e) => e.hidden)) && (await page.getAttribute('.more-btn', 'aria-current')) === 'page');
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
