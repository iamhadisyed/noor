import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner } from '../ui.js';
import { loadChildren, currentChild, childPicker, state } from '../main.js';
import { bars } from '../charts.js';

const KIND = { practice: '✏️', game: '🎮', calm: '🌿', need: '🗣️', reward: '⭐' };
const hhmm = (iso) => { const d = new Date(iso); return `${d.getDate()}/${d.getMonth() + 1}  ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`; };

/** "What did my child do?" and the screen time rules. Activity: today for everyone, two weeks with Pro. Screen time: Pro. */
export async function render(ctx, params, query, name) {
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Activity and screen time are shown per child.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  return name === 'screen-time' ? screenTime() : activity();
}

const lockedCard = (what) => h('section', { class: 'card butter', 'data-card': 'pro-locked' }, h('h2', null, 'This is part of Kindo Pro'), h('p', null, `${what} Ask for Pro in Settings. We email you how to pay.`),
  h('a', { class: 'btn primary', href: '#/settings' }, icon('star', 18), 'Make me Pro'));

function activity() {
  const page = h('div', { class: 'page' });
  const body = h('div', null, spinner());
  let days = 7;
  const isPro = () => state.user?.plan === 'pro' || state.user?.is_pro;
  async function load() {
    const child = currentChild();
    let a;
    try { a = await api(`/children/${child.id}/activity?days=${isPro() ? days : 1}&tz=${encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone)}`); } catch (e) { body.replaceChildren(emptyState('Could not load', errorText(e))); return; }
    const s = a.summary;
    body.replaceChildren(
      h('div', { class: 'tiles' },
        tile('t1', 'Practising', s.practice_minutes, 'min'), tile('t2', 'Playing', s.game_minutes, 'min'), tile('t3', 'Calm times', s.calm_sessions, ''), tile('t1', 'Asked for something', s.asked_for, 'times')),
      a.screen_time ? h('section', { class: 'card', 'data-card': 'screen-week' }, h('h2', null, 'Screen time (minutes)'), bars(a.screen_time.map((d) => ({ label: String(new Date(d.date + 'T12:00:00').getDate()), value: Math.round(d.seconds / 60) })), { thin: true, name: 'Screen time' })) : null,
      a.limited ? lockedCard('You are seeing today only. Pro shows the last two weeks and screen time.') : null,
      h('section', { class: 'card', 'data-card': 'timeline' }, h('h2', null, 'Timeline'),
        a.items.length ? h('div', { class: 'list' }, a.items.map((i) => h('div', { class: 'item', 'data-kind': i.kind }, h('span', { 'aria-hidden': 'true', style: 'font-size:24px' }, KIND[i.kind] || '•'),
          h('div', { class: 'grow' }, h('b', null, i.title), h('span', null, [i.detail, hhmm(i.at), i.minutes ? `${i.minutes} min` : null].filter(Boolean).join(' · ')))))) : h('p', { class: 'muted' }, 'Nothing yet. When your child plays, practises or asks for something, it shows here.')));
  }
  const tile = (cls, label, n, unit) => h('div', { class: `tile ${cls}` }, h('div', { class: 'l' }, label), h('div', { class: 'n' }, String(n ?? 0), unit ? h('small', null, ` ${unit}`) : null));
  const head = h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'What your child did'), h('p', null, 'Practice, games, calm times and requests, newest first.')),
    h('div', { class: 'row' }, isPro() ? h('select', { class: 'select', id: 'act-days', 'aria-label': 'Period', onchange: (e) => { days = +e.target.value; load(); } }, [[1, 'Today'], [7, '7 days'], [14, '14 days']].map(([v, l]) => h('option', { value: v, selected: v === days }, l))) : null, childPicker(load)));
  page.append(head, body);
  load();
  return page;
}

function screenTime() {
  const page = h('div', { class: 'page' });
  const body = h('div', null, spinner());
  async function load() {
    const child = currentChild();
    let d;
    try { d = await api(`/children/${child.id}/screen-time?tz=${encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone)}`); }
    catch (e) { body.replaceChildren(e.status === 402 ? lockedCard('Screen time limits, quiet hours and the weekly chart are Pro features.') : emptyState('Could not load', errorText(e))); return; }
    const st = d.settings, t = d.today;
    const save = async (changes) => { try { await api(`/children/${child.id}/screen-time`, { method: 'PATCH', body: changes }); toast('Saved.'); load(); } catch (e) { toast(errorText(e), 'err'); load(); } };
    const sw = h('button', { class: 'switch', role: 'switch', 'aria-label': `Limit ${child.name}'s screen time`, 'aria-checked': String(!!st.enabled), 'data-act': 'toggle-screen-time', onclick: () => save({ enabled: !st.enabled }) });
    const mins = h('input', { class: 'input', type: 'number', id: 'st-min', min: '10', max: '600', value: st.daily_minutes, style: 'max-width:120px' });
    const qs = h('input', { class: 'input', type: 'time', id: 'st-qs', value: st.quiet_start || '' }), qe = h('input', { class: 'input', type: 'time', id: 'st-qe', value: st.quiet_end || '' });
    const used = Math.round(t.used_seconds / 60), allowed = Math.round(t.allowed_seconds / 60);
    body.replaceChildren(
      h('section', { class: 'card lav', 'data-card': 'screen-rules' },
        h('div', { class: 'item' }, h('div', { class: 'grow' }, h('b', null, `Limit ${child.name}'s screen time`), h('span', null, 'When time is up the app shows a calm "cool time" screen. The needs board still works, and it keeps working without internet.')), sw),
        st.enabled ? h('div', { style: 'display:grid;gap:14px;margin-top:14px' },
          h('div', { class: 'field' }, h('label', { for: 'st-min' }, 'Minutes a day'), mins),
          h('div', { class: 'row' }, h('div', { class: 'field' }, h('label', { for: 'st-qs' }, 'Quiet hours from'), qs), h('div', { class: 'field' }, h('label', { for: 'st-qe' }, 'until'), qe)),
          h('div', { class: 'actions' }, h('button', { class: 'btn primary', 'data-act': 'save-screen-time', onclick: () => save({ daily_minutes: +mins.value, quiet_start: qs.value || null, quiet_end: qe.value || null }) }, 'Save'))) : null),
      st.enabled ? h('section', { class: 'card', 'data-card': 'screen-today' }, h('h2', null, 'Today'), h('p', null, `Used ${used} of ${allowed} minutes${t.locked ? ' · cool time has started' : ''}.`),
        h('div', { class: 'row' }, [10, 15, 30].map((m) => h('button', { class: 'btn soft', 'data-act': `extend-${m}`, onclick: async () => { try { await api(`/children/${child.id}/screen-time/extend?tz=${encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone)}`, { method: 'POST', body: { minutes: m } }); toast(`${m} more minutes given.`); load(); } catch (e) { toast(errorText(e), 'err'); } } }, `+${m} min`)))) : null,
      st.enabled ? h('section', { class: 'card', 'data-card': 'screen-week' }, h('h2', null, 'This week (minutes)'), bars(d.week.map((x) => ({ label: String(new Date(x.date + 'T12:00:00').getDate()), value: Math.round(x.seconds / 60) })), { thin: true, name: 'Screen time' })) : null);
  }
  page.append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Screen time'), h('p', null, 'A daily allowance and quiet hours. Pro.')), childPicker(load)), body);
  load();
  return page;
}
