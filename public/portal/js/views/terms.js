import { api } from '../api.js';
import { h, modal, toast, errorText } from '../ui.js';

/** The terms text, as plain elements (built from data with textContent, never as HTML). */
export async function termsBody() {
  const t = await api('/legal/terms');
  return { version: t.version, el: h('div', { class: 'terms', tabindex: '0', 'aria-label': t.title },
    h('p', { class: 'hint' }, `Version ${t.version} · updated ${t.updated}`),
    t.sections.map((s) => h('section', null, h('h3', null, s.heading), h('p', null, s.body)))) };
}

/** Public page at #/terms. */
export async function render() {
  const { el } = await termsBody();
  return h('div', { class: 'login' }, h('section', { class: 'right', style: 'grid-column:1/-1' },
    h('div', { class: 'box', style: 'max-width:720px' }, h('a', { class: 'btn soft sm', href: '#/login', style: 'justify-self:start' }, 'Back'), h('h1', null, 'Terms and Conditions'), el)));
}

/** A parent who signed up under an older version is asked once more. */
export async function askAgain(onDone) {
  let body;
  try { body = await termsBody(); } catch { return; }
  modal((box, close) => {
    const check = h('input', { type: 'checkbox', id: 'tac', onchange: () => { ok.disabled = !check.checked; } });
    const ok = h('button', { class: 'btn primary', disabled: true, onclick: async () => {
      try { const me = await api('/me/terms', { method: 'POST', body: { accept: true } }); close(); onDone(me); toast('Thank you.'); } catch (e) { toast(errorText(e), 'err'); }
    } }, 'Accept and continue');
    box.append(h('h2', null, 'Our terms have been updated'), body.el, h('label', { class: 'check', for: 'tac' }, check, ' I am the parent or guardian and I accept these terms.'), h('div', { class: 'actions' }, ok));
  });
}
