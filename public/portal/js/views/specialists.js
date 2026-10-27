import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner, modal, confirmBox } from '../ui.js';
import { loadChildren, currentChild, childPicker } from '../main.js';

const ROLE = { therapist: 'Therapist', teacher: 'Teacher' };

/** The parent decides which therapist or teacher sees their child's progress, and reads the exercises they set. */
export async function render(ctx) {
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Therapists and teachers are connected to one child.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  const page = h('div', { class: 'page' });
  const body = h('div', null, spinner());

  async function load() {
    const child = currentChild();
    const [people, work] = await Promise.all([api(`/children/${child.id}/specialists`), api(`/children/${child.id}/assignments`)]);
    body.replaceChildren(h('div', { class: 'grid2', style: 'align-items:start' }, access(child, people), exercises(child, work)));
  }

  const showCode = (child, who) => modal((box, close) => box.append(h('div', null,
    h('h2', null, `Code for ${who.label}`),
    h('p', { class: 'muted' }, 'Give this code to them. They sign in to Kindo with their own email and enter it under Settings. It works once and ends in 24 hours. We will not show it again.'),
    h('div', { 'data-code': who.code, style: 'text-align:center;font-size:44px;font-weight:800;letter-spacing:10px;background:var(--butter);border-radius:24px;padding:20px 0;margin:16px 0' }, `${who.code.slice(0, 3)} ${who.code.slice(3)}`),
    h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: async () => { try { await navigator.clipboard.writeText(who.code); toast('Copied.'); } catch { toast('Select the code and copy it.', 'err'); } } }, icon('copy', 16), 'Copy'), h('button', { class: 'btn primary', onclick: () => { close(); load(); } }, 'Done')))));

  const access = (child, people) => {
    const role = h('select', { class: 'select', id: 'sp-role', 'aria-label': 'Role' }, Object.entries(ROLE).map(([v, l]) => h('option', { value: v }, l)));
    const label = h('input', { class: 'input', id: 'sp-label', maxlength: 80, placeholder: 'For example: Ms Sara, speech therapist', 'aria-label': 'Who is it for' });
    const err = h('div', { class: 'msg-err', role: 'alert' });
    return h('section', { class: 'card', 'data-card': 'access' }, h('h2', { style: 'margin-bottom:6px' }, `Who can see ${child.name}'s progress`),
      h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'A therapist or teacher sees practice progress and lesson titles, and can set exercises for you. They never see alerts, pictures, voices or your family. You can end it at any time.'),
      people.length ? h('div', { class: 'list', style: 'margin-bottom:16px' }, people.map((p) => h('div', { class: 'item', 'data-access': p.label },
        h('div', { class: 'grow' }, h('b', null, p.label), h('span', null, p.status === 'active' ? `${ROLE[p.role]} · ${p.specialist?.name || p.specialist?.email} · ${p.last_viewed_at ? `last looked ${fmt.ago(p.last_viewed_at)}` : 'has not looked yet'}` : `${ROLE[p.role]} · ${p.code_expired ? 'code ended' : 'waiting for them to enter the code'}`)),
        h('span', { class: `chip ${p.status === 'active' ? 'sage' : 'butter'}` }, p.status === 'active' ? 'Has access' : 'Waiting'),
        p.status === 'pending' ? h('button', { class: 'btn soft sm', onclick: async () => { try { showCode(child, { ...(await api(`/specialists/${p.id}/new-code`, { method: 'POST' })), label: p.label }); } catch (e) { toast(errorText(e), 'err'); } } }, 'New code') : null,
        h('button', { class: 'btn danger sm', 'data-act': 'revoke', onclick: async () => {
          if (!(await confirmBox({ title: p.status === 'active' ? `Stop sharing with ${p.label}?` : 'Cancel this code?', body: p.status === 'active' ? 'They lose access straight away.' : 'It will stop working.', confirm: p.status === 'active' ? 'Stop sharing' : 'Cancel code', danger: true }))) return;
          try { await api(`/specialists/${p.id}`, { method: 'DELETE' }); load(); } catch (e) { toast(errorText(e), 'err'); }
        } }, p.status === 'active' ? 'Stop' : 'Cancel')))) : h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'Nobody yet.'),
      h('div', { class: 'grid2' }, h('div', { class: 'field' }, h('label', { for: 'sp-role' }, 'They are a'), role), h('div', { class: 'field' }, h('label', { for: 'sp-label' }, 'Name or note'), label)), err,
      h('button', { class: 'btn primary', 'data-act': 'make-code', onclick: async () => {
        err.textContent = '';
        if (!label.value.trim()) { err.textContent = 'Say who the code is for.'; return; }
        try { const r = await api(`/children/${child.id}/specialists`, { method: 'POST', body: { role: role.value, label: label.value.trim() } }); showCode(child, r); } catch (e) { err.textContent = errorText(e); }
      } }, icon('plus', 20), 'Make a code'));
  };

  const exercises = (child, work) => h('section', { class: 'card', 'data-card': 'exercises' }, h('h2', { style: 'margin-bottom:6px' }, 'Exercises to do together'),
    h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'Set by your therapist or teacher. Tick them off when you have done them.'),
    work.length ? h('div', { class: 'list' }, work.map((a) => h('article', { class: 'item', 'data-exercise': a.title, style: `align-items:flex-start;${a.status === 'done' ? 'opacity:.6' : ''}` },
      h('button', { class: 'switch', role: 'switch', 'aria-label': `${a.title} done`, 'aria-checked': String(a.status === 'done'), onclick: async () => { try { await api(`/assignments/${a.id}/done`, { method: 'POST', body: { done: a.status !== 'done' } }); load(); } catch (e) { toast(errorText(e), 'err'); } } }),
      h('div', { class: 'grow' }, h('b', null, a.title), a.note ? h('span', null, a.note) : null, h('span', { class: 'hint' }, `${a.from ? `From ${a.from.name || a.from.email}` : ''}${a.due_on ? ` · due ${fmt.date(a.due_on)}` : ''}`))))) : h('p', { class: 'muted' }, 'No exercises yet.'));

  function draw() {
    const child = currentChild();
    page.replaceChildren(h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Therapists and teachers'), h('p', null, `Share ${child.name}'s progress with the people who help.`)), h('div', { class: 'row' }, childPicker(draw))), body);
    body.replaceChildren(spinner());
    load().catch((e) => body.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))));
  }
  draw();
  return page;
}
