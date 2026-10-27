import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, confirmBox } from '../ui.js';
import { state, signOut } from '../main.js';
import { meta } from './children.js';

export async function render() {
  const m = await meta();
  const u = state.user;
  const name = h('input', { class: 'input', id: 'sn', value: u.name || '', maxlength: 150 });
  const phone = h('input', { class: 'input', id: 'sp', value: u.phone || '', maxlength: 30, inputmode: 'tel', autocomplete: 'tel' });
  const err = h('div', { class: 'msg-err', role: 'alert' });
  const sessionsBox = h('div', { class: 'list' });
  const loadSessions = async () => {
    const rows = await api('/me/sessions');
    sessionsBox.replaceChildren(...rows.map((s) => h('div', { class: 'item' }, h('div', { class: 'ico' }, icon('shield', 24)),
      h('div', { class: 'grow' }, h('b', null, s.device === 'web' ? 'Web browser' : s.device), h('span', null, `Signed in ${fmt.dateTime(s.created_at)} · last used ${fmt.ago(s.last_used_at || s.created_at)}`)),
      s.current ? h('span', { class: 'chip sage' }, 'This device') : h('button', { class: 'btn soft sm', onclick: async () => { try { await api(`/me/sessions/${s.id}`, { method: 'DELETE' }); toast('That device was signed out.'); loadSessions(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Sign out'))));
  };
  loadSessions();
  const pro = u.plan === 'pro';
  return h('div', { class: 'page' }, h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Settings'), h('p', null, u.email))),
    h('div', { class: 'cols' }, h('div', { class: 'list', style: 'gap:24px' },
      h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:16px' }, 'Your profile'),
        h('div', { class: 'field' }, h('label', { for: 'sn' }, 'Name'), name), h('div', { class: 'field' }, h('label', { for: 'sp' }, 'Phone (optional)'), phone), err,
        h('button', { class: 'btn primary', onclick: async () => { err.textContent = ''; try { state.user = await api('/me', { method: 'PATCH', body: { name: name.value.trim(), phone: phone.value.trim() || null } }); toast('Saved.'); } catch (e) { err.textContent = errorText(e); } } }, 'Save')),
      h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:6px' }, 'Signed-in devices'), h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'Sign out any device you do not recognise.'), sessionsBox)),
    h('div', { class: 'list', style: 'gap:24px' },
      u.role === 'super_admin' ? h('section', { class: 'card lav' }, h('h2', null, 'Super admin'), h('p', { style: 'color:var(--ink-2)' }, 'Admin sessions end after 12 hours. Every account change you make is written to the audit log.')) : h('section', { class: `card ${pro ? 'butter' : 'lav'}` }, h('h2', null, pro ? 'Kindo Pro' : 'Free plan'),
        h('ul', { style: 'padding-left:20px;margin-top:10px;display:grid;gap:6px' },
          h('li', null, pro ? `Up to ${m.limits.children_pro} children` : `${m.limits.children_free} child profile`), h('li', null, pro ? 'Unlimited lessons' : `${m.limits.lessons_per_child_free} lessons per child`), h('li', null, pro ? 'Weekly AI insight, unlimited family cards and printable cards' : 'Weekly insight, extra family cards and printing are in Pro')),
        pro ? null : h('p', { class: 'hint', style: 'margin-top:12px;color:var(--ink-2)' }, 'Online upgrade is coming soon. Contact us to switch on Pro.')),
      h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Account'), h('button', { class: 'btn soft block', onclick: signOut }, icon('logout', 20), 'Sign out')))));
}
