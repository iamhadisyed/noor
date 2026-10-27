import { api, auth } from '../api.js';
import { h, icon, fmt, toast, errorText, confirmBox, modal } from '../ui.js';
import { state, signOut, go } from '../main.js';
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
      h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Account'), h('button', { class: 'btn soft block', onclick: signOut }, icon('logout', 20), 'Sign out'),
        h('p', { class: 'hint', style: 'margin-top:12px' }, h('a', { href: '#/terms' }, 'Terms and conditions'), u.terms_version ? ` (version ${u.terms_version})` : '')),
      u.role === 'super_admin' ? null : privacyCard())));
}

/** The parent's rights: a full copy of their data, and complete deletion confirmed by an emailed code. */
function privacyCard() {
  const dl = h('button', { class: 'btn soft block', 'data-act': 'export', onclick: async () => {
    dl.disabled = true;
    try {
      const res = await fetch('/api/v1/me/export', { headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/zip' } });
      if (!res.ok) throw new Error(res.status === 429 ? 'You can download your data a few times an hour. Please try again later.' : 'We could not prepare your data. Please try again.');
      const blob = await res.blob();
      const a = h('a', { href: URL.createObjectURL(blob), download: `kindo-my-data-${new Date().toISOString().slice(0, 10)}.zip` });
      document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 10000);
      toast('Your data is downloading.');
    } catch (e) { toast(e.message || errorText(e), 'err'); } finally { dl.disabled = false; }
  } }, icon('download', 20), 'Download my data');

  const del = h('button', { class: 'btn danger block', 'data-act': 'delete', onclick: () => deleteFlow() }, icon('trash', 20), 'Delete my account');
  return h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:6px' }, 'Your data'),
    h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'Get a copy of everything we hold (one file with your children, lessons, pictures and voices), or delete your account for good.'), h('div', { class: 'list' }, dl, del));
}

function deleteFlow() {
  modal((box, close) => {
    const err = h('div', { class: 'msg-err', role: 'alert' });
    const step1 = () => {
      const send = h('button', { class: 'btn danger', onclick: async () => {
        send.disabled = true;
        try { await api('/me/delete-code', { method: 'POST' }); step2(); } catch (e) { send.disabled = false; err.textContent = errorText(e); }
      } }, 'Email me a confirmation code');
      box.replaceChildren(h('h2', null, 'Delete your account?'),
        h('p', { class: 'muted' }, 'This permanently removes your account, your children’s profiles, every lesson, picture and voice, and your alerts and reports. It cannot be undone. Consider downloading your data first.'),
        err, h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Keep my account'), send));
    };
    const step2 = () => {
      err.textContent = '';
      const code = h('input', { class: 'input', id: 'dc', inputmode: 'numeric', autocomplete: 'one-time-code', maxlength: '6', 'aria-label': '6-digit code', placeholder: '6-digit code', oninput: (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6); ok.disabled = e.target.value.length !== 6; } });
      const ok = h('button', { class: 'btn danger', disabled: true, onclick: async () => {
        ok.disabled = true; err.textContent = '';
        try {
          await api('/me', { method: 'DELETE', body: { code: code.value } });
          auth.token = null; auth.user = null; state.user = null; state.children = null; state.childId = null;
          close(); go('#/login'); toast('Your account and everything in it was deleted.');
        } catch (e) { ok.disabled = false; err.textContent = errorText(e); }
      } }, 'Delete everything');
      box.replaceChildren(h('h2', null, 'Check your email'), h('p', { class: 'muted' }, 'Enter the 6-digit code we sent to confirm. The code works once.'),
        h('div', { class: 'field', style: 'margin-top:16px' }, h('label', { for: 'dc' }, 'Confirmation code'), code), err,
        h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Cancel'), ok));
      code.focus();
    };
    step1();
  });
}
