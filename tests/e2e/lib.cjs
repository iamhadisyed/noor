// Shared helpers for the portal end-to-end suite. Needs Playwright and the local server from run.sh.
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

const BASE = process.env.KINDO_BASE || 'http://127.0.0.1:8099';
const LOG = process.env.KINDO_LOG || path.resolve(__dirname, '../../storage/logs/laravel.log');   // MAIL_MAILER=log writes the sign-in email here
const OUT = path.resolve(__dirname, '.out');
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'kindo-e2e-'));
fs.mkdirSync(OUT, { recursive: true });

const codes = () => fs.readFileSync(LOG, 'utf8').match(/sign-in code: \d{6}/g) || [];
const deleteCodes = () => fs.readFileSync(LOG, 'utf8').match(/Confirm deleting your Kindo account: \d{6}/g) || [];
const lastCode = () => { const c = codes(); return c.length ? c[c.length - 1].slice(-6) : null; };

async function launch(opts = {}) {
  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 }, permissions: ['microphone'], ...opts });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/status of (404|422|403)/.test(m.text())) errors.push('console: ' + m.text()); });   // expected API refusals are not failures
  return { browser, ctx, page, errors };
}

/** Sign in through the real screen, reading the code from the logged email. */
async function login(page, email) {
  await page.goto(BASE + '/portal/');
  await page.waitForSelector('#em');
  await page.fill('#em', email);
  const before = codes().length;
  await page.click('text=Email me a code');
  await page.waitForSelector('.codes input');
  for (let i = 0; i < 50 && codes().length === before; i++) await page.waitForTimeout(100);
  const code = lastCode();
  if (!code) throw new Error('No sign-in code was logged');
  await page.locator('.codes input').first().click();
  await page.keyboard.type(code, { delay: 30 });
  await page.waitForTimeout(900);
  if (await page.$('#tac')) {   // a new account has to read and accept the terms first
    page.sawTerms = true;
    await page.check('#tac');
    await page.click('text=Create my account');
    await page.waitForTimeout(900);
  }
}

function check(label, ok, detail = '') {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`);
  if (!ok) process.exitCode = 1;
}
function wav(file) {
  const data = Buffer.alloc(8000), h = Buffer.alloc(44);
  h.write('RIFF', 0); h.writeUInt32LE(36 + data.length, 4); h.write('WAVEfmt ', 8); h.writeUInt32LE(16, 16); h.writeUInt16LE(1, 20); h.writeUInt16LE(1, 22);
  h.writeUInt32LE(8000, 24); h.writeUInt32LE(16000, 28); h.writeUInt16LE(2, 32); h.writeUInt16LE(16, 34); h.write('data', 36); h.writeUInt32LE(data.length, 40);
  fs.writeFileSync(file, Buffer.concat([h, data]));
}
/** A real JPEG-able picture (a hand-typed base64 PNG is easy to get subtly wrong). */
function picture(file) {
  const { execFileSync } = require('child_process');
  execFileSync('python3', ['-c', `from PIL import Image, ImageDraw\nim=Image.new('RGB',(900,700),'#f8c9f4'); d=ImageDraw.Draw(im); d.ellipse((250,150,650,550),fill='#f6c9a0'); im.save(r'${file}')`]);
}
module.exports = { codes, deleteCodes, lastCode, BASE, OUT, TMP, launch, login, check, wav, picture };
