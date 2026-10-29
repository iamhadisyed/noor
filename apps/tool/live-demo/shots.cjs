const { chromium } = require('playwright');
const fs = require('fs');
const [,, app, port, token, outDir] = process.argv;
(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  await ctx.addInitScript(([t]) => { try { localStorage.setItem('flutter.kindo.token', JSON.stringify(t)); } catch (e) {} }, [token]);
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.goto(`http://127.0.0.1:${port}/`);
  await page.waitForTimeout(6000);
  // turn on the accessibility tree so we can find buttons by their text
  await page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click());
  await page.waitForTimeout(1500);
  const shot = async (name) => { await page.waitForTimeout(1200); await page.screenshot({ path: `${outDir}/${name}.png` }); console.log('shot', name); };
  // click where the element is, like a finger would: Flutter listens to real pointer events on the canvas
  const press = async (loc) => {
    const n = await loc.count();
    for (let i = 0; i < n; i++) {
      const box = await loc.nth(i).boundingBox({ timeout: 3000 }).catch(() => null);
      if (box && box.width > 4 && box.height > 4) { await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2); return; }
    }
    throw new Error('nothing visible to press');
  };
  const tap = async (label, opts = {}) => {
    const exact = opts.exact ?? false;
    for (const role of ['tab', 'button', 'checkbox', 'switch']) {
      const l = page.getByRole(role, { name: label, exact });
      if (await l.count()) return press(l);
    }
    const byLabel = page.locator(opts.exact ? `[aria-label="${label}"]` : `[aria-label*="${label}"]`);
    if (await byLabel.count()) return press(byLabel);
    return press(page.getByText(label, { exact }));
  };
  const steps = JSON.parse(fs.readFileSync(process.env.STEPS, 'utf8'));
  for (const s of steps) {
    try {
      if (s.tap) await tap(s.tap, s);
      if (s.scroll) { await page.mouse.move(195, 500); await page.mouse.wheel(0, s.scroll); await page.waitForTimeout(600); }
      if (s.click) await page.mouse.click(s.click[0], s.click[1]);
      if (s.draw) { // canvas strokes in CSS pixels: [[x,y],...] per stroke
        for (const stroke of s.draw) {
          await page.mouse.move(stroke[0][0], stroke[0][1]); await page.mouse.down();
          for (const [x, y] of stroke.slice(1)) await page.mouse.move(x, y, { steps: 12 });
          await page.mouse.up();
        }
      }
      if (s.back) await page.goBack().catch(() => {});
      if (s.wait) await page.waitForTimeout(s.wait);
      if (s.shot) await shot(s.shot);
    } catch (e) { console.log('step failed', JSON.stringify(s), String(e).split('\n')[0]); await shot('failed-' + (s.shot || s.tap || 'x').toString().replace(/\W+/g, '_')); }
  }
  console.log('page errors:', errors.length ? errors.slice(0, 5) : 'none');
  await browser.close();
})();
