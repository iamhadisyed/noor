import { api } from '../api.js';
import { h, icon, modal, confirmBox, toast, errorText, emptyState, spinner } from '../ui.js';
import { loadChildren, currentChild, childPicker, selectChild, go, state } from '../main.js';
import { meta } from './children.js';

const LANG = { urdu: 'Urdu', english: 'English', both: 'Urdu and English' };
export const statusChip = (s) => h('span', { class: `chip ${s === 'published' ? 'sage' : 'butter'}` }, s === 'published' ? 'Published' : 'Draft');
const issues = (l) => l.problems.filter((p) => p.task_id);

export async function render(ctx, params, query) {
  const m = await meta();
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Lessons belong to a child, so we need one to start.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  let tab = query.tab === 'templates' ? 'templates' : 'mine', trackFilter = 'all';
  const page = h('div', { class: 'page' });

  const upgrade = (msg) => modal((box, close) => box.append(h('h2', null, 'Kindo Pro needed'), h('p', { class: 'muted' }, msg), h('div', { class: 'actions' }, h('button', { class: 'btn primary', onclick: close }, 'OK'))));

  async function draw() {
    const child = currentChild();
    const head = h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Lessons'), h('p', null, `Lessons for ${child.name}. Each task can have its own picture and sound.`)), h('div', { class: 'row' }, childPicker(draw),
      h('button', { class: 'btn soft', 'data-act': 'import-photo', onclick: () => importPhoto(child) }, icon('camera', 20), 'From a photo'),
      h('button', { class: 'btn primary', onclick: () => newLesson(child) }, icon('plus', 20), 'New lesson')));
    const tabs = h('div', { class: 'tabs', role: 'tablist' }, [['mine', 'My lessons'], ['templates', 'Starter templates']].map(([k, l]) => h('button', { role: 'tab', 'aria-selected': String(tab === k), onclick: () => { tab = k; draw(); } }, l)));
    const body = h('div', null, spinner());
    page.replaceChildren(head, tabs, body);
    try { body.replaceChildren(tab === 'mine' ? await mine(child) : await templates(child)); } catch (e) { body.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))); }
  }

  async function mine(child) {
    const lessons = await api(`/children/${child.id}/lessons`);
    if (!lessons.length) return emptyState('No lessons yet', 'Start from a ready-made template or build one from scratch.',
      h('div', { class: 'row' }, h('button', { class: 'btn primary', onclick: () => newLesson(child) }, icon('plus', 20), 'Build a lesson'), h('button', { class: 'btn soft', onclick: () => { tab = 'templates'; draw(); } }, 'Browse templates')));
    return h('div', { class: 'cards' }, lessons.map((l) => {
      const bad = issues(l);
      return h('article', { class: 'card lesson-card' },
        h('div', { class: 'row between' }, statusChip(l.status), h('span', { class: `chip ${l.track === 'special' ? 'orchid' : ''}` }, l.track === 'special' ? 'Special support' : 'Standard')),
        h('h2', null, l.title), h('p', { class: 'muted' }, `${LANG[l.language]} · ${l.tasks.length} task${l.tasks.length === 1 ? '' : 's'} · week ${l.week_number}`),
        l.status === 'published' ? h('div', { class: 'ready' }, icon('check', 18), 'Live for your child')
          : bad.length ? h('div', { class: 'ready', style: 'color:var(--warn)' }, icon('alert', 18), `${bad.length} task${bad.length === 1 ? '' : 's'} need${bad.length === 1 ? 's' : ''} attention`)
          : h('div', { class: 'ready' }, icon('check', 18), l.tasks.length ? 'Ready to publish' : 'Add your first task'),
        h('div', { class: 'row' }, h('a', { class: 'btn primary sm', href: `#/lessons/${l.id}` }, icon('edit', 18), 'Open'),
          h('button', { class: 'btn soft sm', 'aria-label': `Duplicate ${l.title}`, onclick: () => duplicate(l) }, icon('copy', 18), 'Duplicate'),
          h('button', { class: 'btn soft sm', 'aria-label': `Archive ${l.title}`, onclick: () => archive(l) }, icon('trash', 18))));
    }));
  }

  async function templates(child) {
    const all = await api('/lesson-templates');
    const list = all.filter((t) => trackFilter === 'all' || t.track === trackFilter);
    const seg = h('div', { class: 'seg', role: 'radiogroup', 'aria-label': 'Track', style: 'margin-bottom:20px' }, [['all', 'All'], ['standard', 'Standard'], ['special', 'Special support']].map(([k, l]) => h('button', { role: 'radio', 'aria-checked': String(trackFilter === k), onclick: () => { trackFilter = k; draw(); } }, l)));
    return h('div', null, seg, h('div', { class: 'cards' }, list.map((t) => h('article', { class: 'card lesson-card' },
      h('div', { class: 'row between' }, h('span', { class: `chip ${t.track === 'special' ? 'orchid' : 'butter'}` }, t.track === 'special' ? 'Special support' : 'Standard'), h('span', { class: 'chip' }, LANG[t.language])),
      h('h2', null, t.title), h('p', { class: 'muted' }, t.description),
      h('div', { class: 'row' }, t.tasks.slice(0, 6).map((k) => h('span', { class: 'chip' }, k.title))),
      h('p', { class: 'hint' }, 'You add your own pictures and voice. We will tell you exactly what is missing.'),
      h('button', { class: 'btn primary', onclick: async (e) => {
        e.currentTarget.disabled = true;
        try { const l = await api(`/children/${child.id}/lessons/from-template/${t.id}`, { method: 'POST' }); toast('Template copied to your lessons.'); go(`#/lessons/${l.id}`); }
        catch (err) { e.currentTarget.disabled = false; err.status === 402 ? upgrade(err.message) : toast(errorText(err), 'err'); }
      } }, `Use for ${child.name}`)))));
  }

  function newLesson(child) {
    modal((box, close) => {
      const title = h('input', { class: 'input', id: 'lt', maxlength: 150, placeholder: 'e.g. Week 1: Alif to Se', autofocus: true });
      const lang = h('select', { class: 'select', id: 'll' }, Object.entries(LANG).map(([v, l]) => h('option', { value: v }, l)));
      const week = h('input', { class: 'input', id: 'lw', type: 'number', min: 1, max: 52, value: 1 });
      const err = h('div', { class: 'msg-err', role: 'alert' });
      const go1 = h('button', { class: 'btn primary', onclick: async () => {
        if (!title.value.trim()) { err.textContent = 'Give the lesson a title.'; return title.focus(); }
        go1.disabled = true;
        try { const l = await api(`/children/${child.id}/lessons`, { method: 'POST', body: { title: title.value.trim(), language: lang.value, week_number: +week.value || 1 } }); close(); go(`#/lessons/${l.id}`); }
        catch (e) { go1.disabled = false; if (e.status === 402) { close(); upgrade(e.message); } else err.textContent = errorText(e); }
      } }, 'Create lesson');
      box.append(h('h2', null, `New lesson for ${child.name}`), h('div', { class: 'field', style: 'margin-top:16px' }, h('label', { for: 'lt' }, 'Title'), title),
        h('div', { class: 'grid2' }, h('div', { class: 'field' }, h('label', { for: 'll' }, 'Language'), lang), h('div', { class: 'field' }, h('label', { for: 'lw' }, 'Week'), week)), err,
        h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Cancel'), go1));
    });
  }
  /** A photo of a syllabus or worksheet page becomes a draft lesson. The photo is read once and not kept. */
  function importPhoto(child) {
    modal((box, close) => {
      const file = h('input', { class: 'input', id: 'ip', type: 'file', accept: 'image/jpeg,image/png,image/webp', capture: 'environment', 'aria-label': 'Photo of the page' });
      const lang = h('select', { class: 'select', id: 'il' }, Object.entries(LANG).map(([v, l]) => h('option', { value: v }, l)));
      lang.value = 'both';
      const err = h('div', { class: 'msg-err', role: 'alert' });
      const ok = h('button', { class: 'btn primary', 'data-act': 'run-import', onclick: async () => {
        if (!file.files[0]) { err.textContent = 'Choose or take a photo of the page first.'; return; }
        const f = new FormData(); f.append('photo', file.files[0]); f.append('language', lang.value);
        ok.disabled = true; ok.textContent = 'Reading the page…'; err.textContent = '';
        try { const l = await api(`/children/${child.id}/lessons/import`, { method: 'POST', form: f }); close(); toast('Draft lesson made. Check it, then add sounds and pictures.'); go(`#/lessons/${l.id}`); }
        catch (e) { ok.disabled = false; ok.textContent = 'Make a draft'; if (e.status === 402) { close(); upgrade(e.message); } else err.textContent = errorText(e); }
      } }, 'Make a draft');
      box.append(h('h2', null, `Lesson from a photo for ${child.name}`), h('p', { class: 'muted' }, 'Take a clear photo of one page of the syllabus or worksheet. We turn it into a draft you can edit. The photo is read once by our AI helper and is not saved.'),
        h('div', { class: 'field', style: 'margin-top:16px' }, h('label', { for: 'ip' }, 'Photo'), file), h('div', { class: 'field' }, h('label', { for: 'il' }, 'Language on the page'), lang), err,
        h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Cancel'), ok));
    });
  }
  function duplicate(l) {
    modal((box, close) => {
      const sel = h('select', { class: 'select', 'aria-label': 'Copy to', autofocus: true }, state.children.map((c) => h('option', { value: c.id }, c.name)));
      sel.value = l.child_id;
      const ok = h('button', { class: 'btn primary', onclick: async () => {
        ok.disabled = true;
        try { const c = await api(`/lessons/${l.id}/duplicate`, { method: 'POST', body: { child_id: +sel.value } }); close(); toast('Lesson copied, with its pictures and sounds.'); go(`#/lessons/${c.id}`); }
        catch (e) { ok.disabled = false; e.status === 402 ? (close(), upgrade(e.message)) : toast(errorText(e), 'err'); }
      } }, 'Copy lesson');
      box.append(h('h2', null, `Copy "${l.title}"`), h('p', { class: 'muted' }, 'The copy keeps every picture and sound and starts as a draft.'), h('div', { class: 'field', style: 'margin-top:16px' }, h('label', null, 'Copy to'), sel), h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Cancel'), ok));
    });
  }
  async function archive(l) {
    if (!(await confirmBox({ title: `Archive "${l.title}"?`, body: 'It disappears from the list and from your child. Reports keep their history.', confirm: 'Archive', danger: true }))) return;
    try { await api(`/lessons/${l.id}`, { method: 'DELETE' }); toast('Lesson archived.'); draw(); } catch (e) { toast(errorText(e), 'err'); }
  }

  await draw();
  return page;
}
