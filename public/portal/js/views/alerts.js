import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner } from '../ui.js';
import { art } from '../art.js';
import { loadChildren, currentChild, childPicker } from '../main.js';
import { needPhrase, artFor } from './dashboard.js';

const LABEL = { sent: 'Waiting for you', seen: 'Seen', on_the_way: 'On the way' };

export async function render(ctx) {
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Alerts from your child appear here.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  let filter = 'all';
  const page = h('div', { class: 'page' });
  const listBox = h('div', { class: 'list' }, spinner());

  async function load() {
    const child = currentChild();
    const rows = await api(`/children/${child.id}/needs`);
    rows.filter((r) => r.status === 'sent').forEach((r) => api(`/needs/${r.id}/seen`, { method: 'POST' }).catch(() => {}));   // listed on screen, so seen
    const shown = rows.filter((r) => filter === 'all' || (filter === 'waiting' ? r.status !== 'on_the_way' : r.status === 'on_the_way'));
    listBox.replaceChildren(...(shown.length ? shown.map((r) => row(child, r)) : [emptyState(filter === 'waiting' ? 'Nothing is waiting' : 'No alerts yet', 'When your child taps a need on their device, it shows up here at once.')]));
  }
  const row = (child, r) => {
    const waiting = r.status !== 'on_the_way';
    return h('article', { class: 'item', style: waiting ? 'background:var(--lilac)' : '' },
      h('div', { class: 'ico', style: 'background:#fbe0ff' }, art(artFor(r.need_type), 40)),
      h('div', { class: 'grow' }, h('b', null, `${child.name} ${needPhrase(r.need_type)}`), h('span', null, `${fmt.dateTime(r.created_at)}${r.status === 'on_the_way' ? ` · ${r.replied_by || 'Someone'} replied in ${fmt.secs(r.reply_seconds)}` : ''}`)),
      h('span', { class: `chip ${waiting ? 'ink' : 'sage'}` }, LABEL[r.status] || r.status),
      waiting ? h('button', { class: 'btn primary sm', onclick: async (e) => { e.currentTarget.disabled = true; try { await api(`/needs/${r.id}/acknowledge`, { method: 'POST' }); toast(`${child.name} was told you are on your way.`); load(); } catch (er) { e.currentTarget.disabled = false; toast(errorText(er), 'err'); } } }, "I'm on my way") : null);
  };
  function draw() {
    const child = currentChild();
    page.replaceChildren(h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Alerts'), h('p', null, `Everything ${child.name} has asked for.`)),
      h('div', { class: 'row' }, childPicker(() => { draw(); }), h('div', { class: 'seg', role: 'radiogroup', 'aria-label': 'Filter' }, [['all', 'All'], ['waiting', 'Waiting'], ['done', 'Answered']].map(([k, l]) => h('button', { role: 'radio', 'aria-checked': String(filter === k), onclick: () => { filter = k; draw(); } }, l))))), listBox);
    load().catch((e) => listBox.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))));
  }
  draw();
  const t = setInterval(() => { if (ctx.isCurrent()) load().catch(() => {}); }, 10000);
  ctx.setCleanup(() => clearInterval(t));
  return page;
}
