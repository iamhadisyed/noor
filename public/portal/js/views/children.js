import { api } from '../api.js';
import { h, icon, modal, confirmBox, toast, errorText, fmt, emptyState, initial } from '../ui.js';
import { loadChildren, selectChild, state } from '../main.js';

let META = null;
export async function meta() { return (META ??= await api('/meta')); }

const NEURO = { neurotypical: 'No diagnosis', autism_spectrum: 'Autism spectrum', speech_delay: 'Speech delay', adhd: 'ADHD', down_syndrome: 'Down syndrome' };
export const neuroLabel = (n) => NEURO[n] || n;
const age = (dob) => { const d = new Date(dob), n = new Date(); let a = n.getFullYear() - d.getFullYear(); if (n < new Date(n.getFullYear(), d.getMonth(), d.getDate())) a -= 1; return a; };
export const supportLine = (s) => [`Magnet ${s.magnet_px}px`, s.pace === 'slow' ? 'slow pace' : 'normal pace', `${s.max_choices} choices`, s.no_timers ? 'no timers' : null, s.first_then ? 'First-Then' : null].filter(Boolean).join(' · ');

export async function render(ctx, params, query) {
  const m = await meta();
  await loadChildren(true);
  const page = h('div', { class: 'page' });
  const draw = () => {
    const kids = state.children;
    const limit = state.user.plan === 'pro' ? m.limits.children_pro : m.limits.children_free;
    page.replaceChildren(
      h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Children'), h('p', null, 'Each child has their own lessons, settings and reports.')),
        h('button', { class: 'btn primary', onclick: () => (kids.length >= limit ? upgradeNote(limit) : edit(null)) }, icon('plus', 20), 'Add a child')),
      kids.length ? h('div', { class: 'cards' }, kids.map(card)) : emptyState('No children yet', 'Add your first child to start building lessons.', h('button', { class: 'btn primary', onclick: () => edit(null) }, icon('plus', 20), 'Add a child')));
  };
  const card = (c) => h('article', { class: 'card kid' },
    h('div', { class: 'top' }, h('div', { class: 'avatar' }, initial(c.name)), h('div', { style: 'flex:1;min-width:0' }, h('h2', null, c.name), h('span', { class: 'muted' }, `${age(c.date_of_birth)} years old`)),
      h('span', { class: `chip ${c.track === 'special' ? 'orchid' : 'butter'}` }, c.track === 'special' ? 'Special support' : 'Standard')),
    h('div', { class: 'row' }, c.neurotype !== 'neurotypical' ? h('span', { class: 'chip' }, neuroLabel(c.neurotype)) : null, c.sensory_mode ? h('span', { class: 'chip' }, 'Calm mode') : null),
    h('p', { class: 'hint' }, supportLine(c.support)),
    h('div', { class: 'row' },
      h('button', { class: 'btn soft sm', onclick: () => edit(c) }, icon('edit', 18), 'Edit'),
      h('a', { class: 'btn soft sm', href: '#/lessons', onclick: () => selectChild(c.id) }, icon('book', 18), 'Lessons'),
      h('a', { class: 'btn soft sm', href: '#/reports', onclick: () => selectChild(c.id) }, icon('chart', 18), 'Reports')));
  const upgradeNote = (limit) => modal((box, close) => box.append(h('h2', null, 'Child limit reached'), h('p', { class: 'muted' }, `Your plan allows ${limit} child profile${limit === 1 ? '' : 's'}. Pro allows up to ${m.limits.children_pro}.`), h('div', { class: 'actions' }, h('button', { class: 'btn primary', onclick: close }, 'OK'))));

  function edit(child) {
    const isNew = !child;
    const defaults = (track) => ({ ...m.support_defaults[track] });
    const f = { name: child?.name || '', date_of_birth: child?.date_of_birth?.slice(0, 10) || '', neurotype: child?.neurotype || 'neurotypical', track: child?.track || 'standard', sensory_mode: child ? !!child.sensory_mode : false, support: child ? { ...child.support } : defaults('standard') };
    let trackTouched = !isNew;

    modal((box, close) => {
      const err = h('div', { class: 'msg-err', role: 'alert' });
      const sup = h('div');
      const drawSupport = () => sup.replaceChildren(
        h('div', { class: 'setting' }, h('div', { class: 't' }, h('b', null, 'Tracing magnet'), h('span', null, 'How far a finger can drift from the guide line and still count. Larger helps unsteady hands.')),
          h('div', { style: 'width:240px' }, h('input', { type: 'range', min: 15, max: 40, step: 1, value: f.support.magnet_px, 'aria-label': 'Tracing magnet in pixels', oninput: (e) => { f.support.magnet_px = +e.target.value; mv.textContent = `${f.support.magnet_px} px`; } }), (mv = h('span', { class: 'hint' }, `${f.support.magnet_px} px`)))),
        seg('Pace', 'Slower pace gives more time between tasks.', 'pace', [['slow', 'Slow'], ['normal', 'Normal']]),
        seg('Answer choices', 'The most choices shown on a question.', 'max_choices', [[2, '2'], [3, '3'], [4, '4']]),
        sw('No timers', 'Never show a countdown.', 'no_timers'),
        sw('First, Then card', 'Show a visual First, Then plan before each lesson.', 'first_then'),
        seg('Praise', 'Quiet is calm and brief; lively adds sounds and stars.', 'praise', [['quiet', 'Quiet'], ['lively', 'Lively']]),
        h('div', { class: 'setting' }, h('div', { class: 't' }, h('b', null, 'Calm mode'), h('span', null, 'Softer colours, no motion, gentler sounds.')), swEl(f.sensory_mode, (v) => { f.sensory_mode = v; })));
      let mv;
      const seg = (title, sub, key, opts) => h('div', { class: 'setting' }, h('div', { class: 't' }, h('b', null, title), h('span', null, sub)),
        h('div', { class: 'seg', role: 'radiogroup', 'aria-label': title }, opts.map(([v, l]) => h('button', { type: 'button', role: 'radio', 'aria-checked': String(f.support[key] === v), onclick: (e) => { f.support[key] = v; e.currentTarget.parentElement.querySelectorAll('button').forEach((b) => b.setAttribute('aria-checked', String(b === e.currentTarget))); } }, l))));
      const swEl = (on, set) => { const b = h('button', { type: 'button', class: 'switch', role: 'switch', 'aria-checked': String(!!on), onclick: () => { const v = b.getAttribute('aria-checked') !== 'true'; b.setAttribute('aria-checked', String(v)); set(v); } }); return b; };
      const sw = (title, sub, key) => h('div', { class: 'setting' }, h('div', { class: 't' }, h('b', null, title), h('span', null, sub)), (() => { const b = swEl(f.support[key], (v) => { f.support[key] = v; }); b.setAttribute('aria-label', title); return b; })());

      const nameI = h('input', { class: 'input', id: 'cn', value: f.name, maxlength: 100, autofocus: true, autocomplete: 'off' });
      const dobI = h('input', { class: 'input', id: 'cd', type: 'date', value: f.date_of_birth, max: new Date().toISOString().slice(0, 10) });
      const neuro = h('select', { class: 'select', id: 'cz', onchange: (e) => {
        f.neurotype = e.target.value;
        if (isNew && !trackTouched) { setTrack(f.neurotype === 'neurotypical' ? 'standard' : 'special'); }
      } }, Object.entries(NEURO).map(([v, l]) => h('option', { value: v }, l)));
      neuro.value = f.neurotype;
      const trackBox = h('div', { class: 'track-pick', role: 'radiogroup', 'aria-label': 'Learning track' });
      const setTrack = (t, fromUser) => {
        f.track = t; if (isNew) { f.support = defaults(t); f.sensory_mode = t === 'special'; }
        if (fromUser) trackTouched = true; paintTrack(); drawSupport();
      };
      const paintTrack = () => trackBox.replaceChildren(...[['standard', 'Standard', 'Typical pace, lively feedback, up to four choices.'], ['special', 'Special support', 'Slower pace, calmer feedback, a larger tracing magnet, no timers.']].map(([v, t, s]) =>
        h('label', null, h('input', { type: 'radio', name: 'track', value: v, checked: f.track === v, onchange: () => setTrack(v, true) }), h('b', null, t), h('span', null, s))));
      paintTrack(); drawSupport();

      const save = h('button', { class: 'btn primary', onclick: async () => {
        err.textContent = ''; f.name = nameI.value.trim(); f.date_of_birth = dobI.value;
        if (!f.name) { err.textContent = 'Please enter a name.'; return nameI.focus(); }
        if (!f.date_of_birth) { err.textContent = 'Please enter a date of birth.'; return dobI.focus(); }
        save.disabled = true;
        const body = { name: f.name, date_of_birth: f.date_of_birth, neurotype: f.neurotype, track: f.track, sensory_mode: f.sensory_mode, support_profile: f.support };
        try {
          const saved = isNew ? await api('/children', { method: 'POST', body }) : await api(`/children/${child.id}`, { method: 'PATCH', body });
          await loadChildren(true); if (isNew) selectChild(saved.id);
          toast(isNew ? `${saved.name} was added.` : 'Changes saved.'); close(); draw();
        } catch (e) { save.disabled = false; err.textContent = e.status === 402 ? e.message : errorText(e); }
      } }, isNew ? 'Add child' : 'Save changes');

      box.append(h('h2', null, isNew ? 'Add a child' : `Edit ${child.name}`),
        h('div', { class: 'grid2', style: 'margin-top:16px' }, h('div', { class: 'field' }, h('label', { for: 'cn' }, 'Name'), nameI), h('div', { class: 'field' }, h('label', { for: 'cd' }, 'Date of birth'), dobI)),
        h('div', { class: 'field' }, h('label', { for: 'cz' }, 'Needs (optional)'), neuro, h('span', { class: 'hint' }, 'This only chooses gentler starting settings. You can change everything below.')),
        h('div', { class: 'field' }, h('span', { class: 'label' }, 'Learning track'), trackBox),
        h('h3', { style: 'margin:8px 0 0' }, 'Support settings'), sup, err,
        h('div', { class: 'actions' }, !isNew ? h('button', { class: 'btn danger', style: 'margin-right:auto', onclick: () => remove(child, close) }, icon('trash', 18), 'Delete') : null, h('button', { class: 'btn soft', onclick: close }, 'Cancel'), save));
    });
  }
  async function remove(child, closeEdit) {
    const ok = await confirmBox({ title: `Delete ${child.name}?`, body: 'This permanently removes their lessons, pictures, sounds, alerts and reports. It cannot be undone.', confirm: 'Delete everything', danger: true, typeToConfirm: child.name });
    if (!ok) return;
    try { await api(`/children/${child.id}`, { method: 'DELETE' }); await loadChildren(true); closeEdit(); toast(`${child.name} and all their data were deleted.`); draw(); } catch (e) { toast(errorText(e), 'err'); }
  }

  draw();
  if (query.new === '1' && !state.children.length) setTimeout(() => edit(null), 50);
  return page;
}
