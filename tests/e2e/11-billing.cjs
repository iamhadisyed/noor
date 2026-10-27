// Vouchers: the admin makes codes, a parent redeems one for Pro time, it works once, and the admin sees who used it.
const path = require('path');
const { BASE, OUT, launch, login, check } = require('./lib.cjs');
(async () => {
  const { browser, page, errors } = await launch();
  const stamp = Date.now();
  await login(page, 'admin@kindo.test'); await page.waitForSelector('.kpis');
  await page.click('nav.nav >> text=Vouchers'); await page.waitForSelector('#vd');
  await page.selectOption('#vd', '90'); await page.fill('#vn', '2'); await page.fill('#vt', 'e2e batch'); await page.click('[data-act=make-vouchers]');
  await page.waitForSelector('[data-fresh] pre'); await page.waitForTimeout(300);
  const codes = (await page.textContent('[data-fresh] pre')).trim().split(/\s+/);
  check('two readable codes are made', codes.length === 2 && codes.every((c) => /^[A-Z2-9]{4}-[A-Z2-9]{4}$/.test(c)));
  await page.screenshot({ path: path.join(OUT, 'vouchers.png') });

  const mk = async (name) => { const c = await browser.newContext({ viewport: { width: 1360, height: 900 } }); const p = await c.newPage(); await login(p, `${name}${stamp}@kindo.test`); await p.waitForSelector('#nm'); await p.fill('#nm', name); await p.click('text=Continue'); await p.waitForSelector('.modal'); await p.keyboard.press('Escape'); return p; };
  const mama = await mk('mama');
  await mama.goto(`${BASE}/portal/#/settings`); await mama.waitForSelector('[data-card=plan] #vc'); await mama.waitForTimeout(300);
  check('a new parent is on the free plan', /Free plan/.test(await mama.textContent('[data-card=plan]')));
  await mama.fill('#vc', 'ZZZZ-ZZZZ'); await mama.click('[data-act=redeem]'); await mama.waitForTimeout(700);
  check('a wrong code gets a plain refusal', /not valid, has already been used, or has expired/.test(await mama.textContent('[data-card=plan]')));
  await mama.fill('#vc', codes[0].toLowerCase()); await mama.click('[data-act=redeem]'); await mama.waitForSelector('[data-card=plan] h2:has-text("Kindo Pro")');
  check('a good code (typed in lower case) turns on Pro for 90 days', /Pro until/.test(await mama.textContent('[data-card=plan]')) && /8[89] days left|90 days left/.test(await mama.textContent('[data-card=plan]')));
  await mama.screenshot({ path: path.join(OUT, 'plan-pro.png') });

  const baba = await mk('baba');
  await baba.goto(`${BASE}/portal/#/settings`); await baba.waitForSelector('[data-card=plan] #vc');
  await baba.fill('#vc', codes[0]); await baba.click('[data-act=redeem]'); await baba.waitForTimeout(700);
  check('the same code cannot be used again', /Free plan/.test(await baba.textContent('[data-card=plan]')) && /not valid/.test(await baba.textContent('[data-card=plan]')));

  await page.reload(); await page.waitForSelector('#vd'); await page.click('.seg >> text=Used'); await page.waitForTimeout(600);
  check('the admin sees who used it', new RegExp(`mama${stamp}@kindo.test`).test(await page.textContent('[data-voucher]')));

  await page.click('nav.nav >> text=Accounts'); await page.waitForSelector('tbody tr'); await page.fill('input[type=search]', `baba${stamp}`); await page.waitForTimeout(900);
  await page.click(`tr[data-email="baba${stamp}@kindo.test"] [data-act=give-pro]`); await page.fill('#gp-days', '14'); await page.click('[data-act=confirm-give-pro]'); await page.waitForTimeout(800);
  await baba.reload(); await baba.waitForSelector('[data-card=plan] h2:has-text("Kindo Pro")');
  check('a gift from the admin shows up as Pro for the parent', /Gift from Kindo/.test(await baba.textContent('[data-card=plan]')));
  check('no unexpected browser errors', errors.length === 0, errors.join(' | '));
  await browser.close();
})();
