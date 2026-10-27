import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner } from '../ui.js';
import { art } from '../art.js';
import { loadChildren, currentChild, childPicker, state } from '../main.js';
import { invitationBanner } from './household.js';

const PHRASE = { potty: 'needs the potty', water: 'wants water', food: 'is hungry', sleep: 'is sleepy', hug: 'needs a hug', pain: 'is in pain', help: 'needs help' };
export const needPhrase = (n) => PHRASE[(n || '').toLowerCase()] || `needs ${(n || 'something').toLowerCase()}`;
export const artFor = (n) => (['water', 'potty', 'food', 'sleep', 'hug', 'pain'].includes((n || '').toLowerCase()) ? n.toLowerCase() : 'hug');

export async function render(ctx) {
  const kids = await loadChildren();
  const banner = await invitationBanner(() => { state.children = null; ctx.go(location.hash); });
  if (!kids.length) {
    return h('div', { class: 'page' }, banner, h('div', { class: 'page-head' }, h('div', null, h('h1', null, greeting(), ', ', firstName()), h('p', null, 'Let us set up your first child.'))),
      emptyState('Add your first child', 'Tell us a little about them, then build a first lesson with your own pictures and voice.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child')));
  }
  const child = currentChild();
  const wrap = h('div', { class: 'page' }, spinner());
  const withBanner = (...els) => wrap.replaceChildren(...[banner, ...els].filter(Boolean));
  const load = async () => {
    const [summary, daily, needs, lessons] = await Promise.all([
      api(`/children/${child.id}/insights/summary?days=7`), api(`/children/${child.id}/insights/daily?days=7`),
      api(`/children/${child.id}/needs`), api(`/children/${child.id}/lessons`),
    ]);
    const open = needs.find((n) => n.status !== 'on_the_way');
    if (open && open.status === 'sent') api(`/needs/${open.id}/seen`, { method: 'POST' }).catch(() => {});   // the parent is looking at it now

    const days = daily.map((d) => ({ d, label: new Date(d.date + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short' }) }));
    const max = Math.max(1, ...daily.map((d) => d.minutes));
    withBanner(
      h('div', { class: 'page-head' }, h('div', null, h('h1', null, greeting(), ', ', firstName()), h('p', null, `Here is how ${child.name} is doing this week.`)), childPicker(() => ctx.go(location.hash))),
      open ? hero(child, open, load) : h('div', { class: 'alert-hero calm' }, h('div', { class: 'art' }, art('hug', 72)), h('div', { class: 'tx' }, h('h2', null, `No alerts waiting for ${child.name}`), h('p', null, 'You will see a message here the moment they ask for something.'))),
      h('div', { class: 'tiles' },
        tile('t1', 'Practice this week', summary.practice.minutes, 'min', `${summary.practice.sessions} sessions`),
        tile('t2', 'Accuracy', summary.practice.accuracy_pct ?? '—', summary.practice.accuracy_pct == null ? '' : '%', `${summary.practice.attempts} tries`),
        tile('t3', 'Alerts', summary.needs.total, '', `${summary.needs.answered} answered`),
        tile('t4', 'Average reply', summary.needs.avg_reply_seconds == null ? '—' : fmt.secs(summary.needs.avg_reply_seconds), '', 'to alerts')),
      h('div', { class: 'cols' },
        h('section', { class: 'card', 'aria-labelledby': 'pt' }, h('h2', { id: 'pt' }, 'Practice minutes'), h('p', { class: 'muted' }, 'The last 7 days'),
          h('div', { class: 'bars', role: 'img', 'aria-label': `Practice minutes per day: ${days.map((x) => `${x.label} ${x.d.minutes}`).join(', ')}` },
            days.map((x, i) => h('div', { class: 'col' }, h('div', { class: `bar${x.d.minutes === Math.max(...daily.map((d) => d.minutes)) && x.d.minutes > 0 ? ' hot' : ''}`, style: `height:${Math.max(4, (x.d.minutes / max) * 100)}%` }, x.d.minutes === Math.max(...daily.map((d) => d.minutes)) && x.d.minutes > 0 ? h('span', { class: 'v' }, `${x.d.minutes} min`) : null), h('span', { class: 'day' }, x.label))))),
        h('section', { class: 'card', 'aria-labelledby': 'lt' }, h('div', { class: 'row between' }, h('h2', { id: 'lt' }, 'Lessons'), h('a', { class: 'btn soft sm', href: '#/lessons' }, 'All lessons')),
          h('div', { class: 'list', style: 'margin-top:16px' }, lessons.length ? lessons.slice(0, 5).map((l) => lessonRow(l)) : [h('p', { class: 'muted' }, 'No lessons yet. Start from a template or build your own.'), h('a', { class: 'btn primary', href: '#/lessons' }, icon('plus', 20), 'Create a lesson')]))));
  };
  await load();
  const t = setInterval(() => { if (ctx.isCurrent()) load().catch(() => {}); }, 20000);
  ctx.setCleanup(() => clearInterval(t));
  return wrap;
}

function hero(child, n, reload) {
  const btn = h('button', { class: 'btn primary', onclick: async () => {
    btn.disabled = true;
    try { await api(`/needs/${n.id}/acknowledge`, { method: 'POST' }); toast(`${child.name} was told you are on your way.`); reload(); } catch (e) { btn.disabled = false; toast(errorText(e), 'err'); }
  } }, "I'm on my way");
  return h('div', { class: 'alert-hero', role: 'status' }, h('div', { class: 'art' }, art(artFor(n.need_type), 72)),
    h('div', { class: 'tx' }, h('span', { class: 'live' }, h('i'), 'NEEDS YOU'), h('h2', null, `${child.name} ${needPhrase(n.need_type)}`), h('p', null, `Sent ${fmt.ago(n.created_at)} · ${fmt.time(n.created_at)}`)),
    btn, h('a', { class: 'btn white', href: '#/alerts' }, 'Open'));
}
const tile = (cls, label, big, unit, sub) => h('div', { class: `tile ${cls}` }, h('span', { class: 'lbl' }, label), h('span', { class: 'big' }, big, unit ? h('small', null, unit) : null), h('span', { class: 'sub' }, sub));
function lessonRow(l) {
  const problems = l.problems.filter((p) => p.task_id);
  return h('a', { class: 'item', href: `#/lessons/${l.id}`, style: 'text-decoration:none' }, h('div', { class: 'ico' }, icon('book', 24)),
    h('div', { class: 'grow' }, h('b', null, l.title), h('span', null, `${l.tasks.length} tasks`)),
    l.status === 'published' ? h('span', { class: 'chip sage' }, 'Published') : problems.length ? h('span', { class: 'chip warn' }, `${problems.length} to fix`) : h('span', { class: 'chip butter' }, 'Ready'));
}
function greeting() { const hr = new Date().getHours(); return hr < 12 ? 'Good morning' : hr < 18 ? 'Good afternoon' : 'Good evening'; }
function firstName() { const n = state.user?.name || ''; return n.includes('@') ? 'there' : n.split(' ')[0] || 'there'; }
