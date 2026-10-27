import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner, confirmBox } from '../ui.js';
import { bars, hbars } from '../charts.js';
import { state, go } from '../main.js';

const ROLE = { therapist: 'Therapist', teacher: 'Teacher' };
const tile = (cls, label, big, unit, sub) => h('div', { class: `tile ${cls}` }, h('span', { class: 'lbl' }, label), h('span', { class: 'big' }, big, unit ? h('small', null, unit) : null), h('span', { class: 'sub' }, sub));

/** What a therapist or teacher sees: progress for the children a parent has shared, and a place to set exercises. */
export async function render(ctx, params, query) {
  const list = await api('/specialist/children');
  const page = h('div', { class: 'page' });
  if (!list.length) return h('div', { class: 'page' }, h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Shared with me'), h('p', null, 'Children whose parents have given you access.'))), redeemCard());

  let currentId = list.find((c) => String(c.child_id) === query.child)?.child_id ?? list[0].child_id;
  const body = h('div', null, spinner());

  async function load() {
    const [s, work] = await Promise.all([api(`/specialist/children/${currentId}/summary?days=14`), api(`/specialist/children/${currentId}/assignments`)]);
    const p = s.practice;
    body.replaceChildren(
      h('div', { class: 'kid', style: 'margin-bottom:16px' }, h('div', { class: 'grow' }, h('b', null, s.name), h('div', { class: 'hint' }, `${s.age_years} years · ${s.track === 'special' ? 'extra support' : 'standard'}${s.neurotype && s.neurotype !== 'neurotypical' ? ` · ${s.neurotype.replace(/_/g, ' ')}` : ''} · slow pace ${s.support.pace === 'slow' ? 'on' : 'off'}, ${s.support.max_choices} choices${s.support.no_timers ? ', no timers' : ''}`))),
      h('div', { class: 'tiles' }, tile('t1', 'Practice time', p.minutes, 'min', `${p.sessions} sessions, last 14 days`), tile('t2', 'Accuracy', p.accuracy_pct ?? '—', p.accuracy_pct == null ? '' : '%', `${p.attempts} tries`),
        tile('t3', 'Steadiness', p.avg_stroke_deviation ?? '—', '', 'average stroke deviation (lower is steadier)')),
      h('div', { class: 'grid2', style: 'align-items:start' },
        h('div', { class: 'list', style: 'gap:24px' },
          h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Minutes per day'), bars(s.daily.map((d) => ({ label: String(new Date(d.date + 'T12:00:00').getDate()), value: d.minutes })), { thin: true, name: 'Minutes per day' })),
          h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Hardest letters and words'), hbars(p.hardest_targets, { empty: 'No mistakes recorded yet.' }), h('h2', { style: 'margin:20px 0 12px' }, 'Kinds of mistake'), hbars(p.mistake_types, { empty: 'None yet.' })),
          h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Lessons'), s.lessons.length ? h('div', { class: 'list' }, s.lessons.map((l) => h('div', { class: 'item' }, h('div', { class: 'grow' }, h('b', null, l.title), h('span', null, `Week ${l.week} · ${l.tasks} tasks · ${l.language}`)), h('span', { class: `chip ${l.status === 'published' ? 'sage' : ''}` }, l.status)))) : h('p', { class: 'muted' }, 'No lessons yet.'))),
        h('div', { class: 'list', style: 'gap:24px' }, exerciseForm(s, work), h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Game levels'), s.games.length ? h('div', { class: 'list' }, s.games.map((g) => h('div', { class: 'item' }, h('div', { class: 'grow' }, h('b', null, g.game.replace(/_/g, ' ')), h('span', null, `played ${g.plays}×${g.last_accuracy == null ? '' : ` · last ${g.last_accuracy}%`}`)), h('span', { class: 'chip' }, `Level ${g.level}`)))) : h('p', { class: 'muted' }, 'No games played yet.')),
          h('button', { class: 'btn soft', 'data-act': 'leave', onclick: async () => { if (!(await confirmBox({ title: `Stop seeing ${s.name}?`, body: 'You will need a new code from the parent to see them again.', confirm: 'Stop', danger: true }))) return; try { await api(`/specialist/children/${currentId}`, { method: 'DELETE' }); state.user = await api('/me'); go('#/shared'); } catch (e) { toast(errorText(e), 'err'); } } }, 'Stop seeing this child'))));
  }

  const exerciseForm = (s, work) => {
    const title = h('input', { class: 'input', id: 'ex-title', maxlength: 120, placeholder: 'For example: Trace Alif slowly', 'aria-label': 'Exercise' });
    const note = h('textarea', { class: 'textarea', id: 'ex-note', maxlength: 1000, placeholder: 'How to do it (optional)', 'aria-label': 'Instructions' });
    const due = h('input', { class: 'input', id: 'ex-due', type: 'date', min: new Date().toISOString().slice(0, 10), 'aria-label': 'Due date (optional)' });
    const err = h('div', { class: 'msg-err', role: 'alert' });
    return h('section', { class: 'card lav', 'data-card': 'exercise-form' }, h('h2', { style: 'margin-bottom:6px' }, 'Set an exercise'), h('p', { style: 'color:var(--ink-2);margin-bottom:12px' }, `${s.name}'s parents are told and can tick it off.`),
      h('div', { class: 'field' }, h('label', { for: 'ex-title' }, 'Exercise'), title), h('div', { class: 'field' }, h('label', { for: 'ex-note' }, 'Instructions'), note), h('div', { class: 'field' }, h('label', { for: 'ex-due' }, 'Due (optional)'), due), err,
      h('button', { class: 'btn primary', 'data-act': 'assign', onclick: async () => {
        err.textContent = '';
        try { await api(`/specialist/children/${currentId}/assignments`, { method: 'POST', body: { title: title.value.trim(), note: note.value.trim() || null, due_on: due.value || null } }); toast('Sent to the parents.'); load(); } catch (e) { err.textContent = errorText(e); }
      } }, icon('plus', 20), 'Send'),
      work.length ? h('div', { style: 'margin-top:20px' }, h('b', null, 'Your exercises'), h('div', { class: 'list', style: 'margin-top:8px' }, work.map((a) => h('div', { class: 'item', 'data-exercise': a.title, style: 'background:rgba(255,255,255,.6)' }, h('div', { class: 'grow' }, h('b', null, a.title), h('span', null, `${a.status === 'done' ? 'Done' : 'Open'}${a.due_on ? ` · due ${fmt.date(a.due_on)}` : ''}`)),
        h('button', { class: 'icon-btn', 'aria-label': `Delete ${a.title}`, onclick: async () => { try { await api(`/specialist/assignments/${a.id}`, { method: 'DELETE' }); load(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('trash', 18)))))) : null);
  };

  function draw() {
    const cur = list.find((c) => c.child_id === currentId);
    const picker = list.length > 1 ? h('select', { class: 'select', 'aria-label': 'Child', style: 'width:auto;min-width:180px', onchange: (e) => { currentId = +e.target.value; draw(); } }, list.map((c) => h('option', { value: c.child_id, selected: c.child_id === currentId ? true : null }, c.name))) : null;
    page.replaceChildren(h('div', { class: 'page-head' }, h('div', null, h('h1', null, cur.name), h('p', null, `You see this child as: ${cur.label} (${ROLE[cur.role]}). Read-only, except for the exercises you set.`)), picker ? h('div', { class: 'row' }, picker) : null), body);
    body.replaceChildren(spinner());
    load().catch((e) => body.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))));
  }
  draw();
  return page;
}

/** Enter a code from a parent. Shown here when you have nothing shared, and in Settings. */
export function redeemCard() {
  const code = h('input', { class: 'input', id: 'sc', inputmode: 'numeric', maxlength: '7', placeholder: '123 456', autocomplete: 'off', 'aria-label': '6-digit access code', style: 'letter-spacing:6px;font-size:22px;text-align:center' });
  const err = h('div', { class: 'msg-err', role: 'alert' });
  return h('section', { class: 'card lav', 'data-card': 'specialist-code' }, h('h2', { style: 'margin-bottom:6px' }, 'Are you a therapist or teacher?'),
    h('p', { style: 'color:var(--ink-2);margin-bottom:12px' }, 'A parent can give you a 6-digit code to see their child\'s progress. Enter it here.'),
    h('form', { novalidate: true, onsubmit: async (e) => {
      e.preventDefault(); err.textContent = '';
      if (code.value.replace(/\D/g, '').length !== 6) { err.textContent = 'The code has 6 digits.'; return; }
      try { await api('/specialist/redeem', { method: 'POST', body: { code: code.value } }); state.user = await api('/me'); toast('You can now see this child.'); go('#/shared'); } catch (er) { err.textContent = errorText(er); }
    } }, h('div', { class: 'field' }, code), err, h('button', { class: 'btn primary', type: 'submit', 'data-act': 'redeem-specialist' }, 'Use code')));
}
