import { api } from '../api.js';
import { h, icon, emptyState, spinner, errorText } from '../ui.js';

const EMOJI = { tests: '🩺', food: '🥗', exercise: '🏃', daily: '🌙', doctor: '🚑' };

/** Parent guide for a child with autism (Pro). General information only: the warning is shown first and last, in the chosen language. */
export async function render() {
  const page = h('div', { class: 'page' });
  const body = h('div', null, spinner());
  let lang = localStorage.getItem('kindo.guide.lang') || 'en';
  let data = null;

  const t = (v) => (v && (v[lang] || v.en)) || '';

  const notice = (title, text, kind) => h('section', { class: 'card', 'data-card': kind, style: 'background:var(--butter,#fff0ad);display:flex;gap:14px;align-items:flex-start' },
    h('div', { style: 'font-size:26px', 'aria-hidden': 'true' }, '⚠️'), h('div', null, h('h2', { style: 'margin:0 0 6px;font-size:1.05rem' }, title), h('p', { style: 'margin:0' }, text)));

  function show() {
    const langSel = h('select', { class: 'select', id: 'guide-lang', 'aria-label': 'Language', onchange: () => { lang = langSel.value; localStorage.setItem('kindo.guide.lang', lang); show(); } },
      h('option', { value: 'en', selected: lang === 'en' }, 'English'), h('option', { value: 'ur', selected: lang === 'ur' }, 'اردو'));
    body.replaceChildren(h('div', { dir: lang === 'ur' ? 'rtl' : 'ltr', lang, style: 'display:grid;gap:16px' },
      h('div', { class: 'row', style: 'justify-content:space-between;align-items:center' }, h('h1', { style: 'margin:0' }, t(data.title)), langSel),
      notice(lang === 'ur' ? 'پہلے یہ پڑھیں' : 'Please read first', t(data.disclaimer), 'disclaimer'),
      ...data.sections.map((s, i) => h('details', { class: 'card', 'data-section': s.key, open: i === 0 },
        h('summary', { style: 'cursor:pointer;font-weight:800;font-size:1.05rem;display:flex;gap:10px;align-items:center' }, h('span', { 'aria-hidden': 'true', style: 'font-size:24px' }, EMOJI[s.key] || '📘'), t(s.title)),
        h('p', { style: 'margin:12px 0' }, t(s.intro)),
        h('div', { class: 'list' }, s.items.map((it) => h('div', { class: 'item', style: 'display:block' }, h('b', null, t(it.title)), h('p', { style: 'margin:4px 0 0' }, t(it.text))))))),
      notice(lang === 'ur' ? 'یاد رکھیں' : 'Remember', t(data.closing), 'closing')));
  }

  try {
    data = await api('/guidance/autism');
    show();
  } catch (e) {
    if (e.status === 402) {
      body.replaceChildren(h('section', { class: 'card', 'data-card': 'guide-locked' }, h('h1', null, e.data.title?.en || 'Parent guide'),
        h('p', null, h('b', null, 'This guide is part of Kindo Pro.')), h('p', { class: 'muted' }, 'Inside:'),
        h('ul', null, (e.data.preview || []).map((p) => h('li', null, p.title.en))),
        h('p', { class: 'muted' }, 'It is general information only, written to take to your own doctor. It never replaces your child\'s doctor.'),
        h('a', { class: 'btn primary', href: '#/settings' }, icon('star', 18), 'See plans')));
    } else {
      body.replaceChildren(emptyState('Could not load the guide', errorText(e)));
    }
  }
  page.append(body);
  return page;
}
