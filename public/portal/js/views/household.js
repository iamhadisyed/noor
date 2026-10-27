import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, confirmBox } from '../ui.js';

/** Share the children with a second parent. Invited by email; they sign in with that email and accept. */
export function familyCard(onChange = () => {}) {
  const box = h('section', { class: 'card', 'data-card': 'family' }, h('h2', null, 'Family'), h('div', { class: 'spinner', role: 'status', 'aria-label': 'Loading' }));

  const draw = async () => {
    let r;
    try { r = await api('/household'); } catch (e) { box.replaceChildren(h('h2', null, 'Family'), h('p', { class: 'msg-err' }, errorText(e))); return; }
    const email = h('input', { class: 'input', type: 'email', id: 'hh-email', placeholder: 'Their email address', 'aria-label': 'Email of the person to invite', autocomplete: 'off' });
    const err = h('div', { class: 'msg-err', role: 'alert' });
    box.replaceChildren(h('h2', { style: 'margin-bottom:6px' }, 'Family'),
      h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'Share your children with a partner or grandparent. They see lessons and progress, and answer alerts with you. Only you can delete a child or your account.'),
      r.invitations.map((i) => h('div', { class: 'item', style: 'background:var(--lilac);margin-bottom:10px', 'data-invite': i.id },
        h('div', { class: 'grow' }, h('b', null, `${i.from.name || i.from.email} invited you`), h('span', null, 'Accept to see and work with their children')),
        h('button', { class: 'btn primary sm', 'data-act': 'accept', onclick: async () => { try { await api(`/household/invitations/${i.id}/accept`, { method: 'POST' }); toast('You can now see their children.'); onChange(true); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Accept'),
        h('button', { class: 'btn soft sm', onclick: async () => { try { await api(`/household/invitations/${i.id}/decline`, { method: 'POST' }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Decline'))),
      r.joined.map((j) => h('div', { class: 'item', style: 'margin-bottom:10px' },
        h('div', { class: 'grow' }, h('b', null, j.owner.name || j.owner.email), h('span', null, 'You share their children')),
        h('button', { class: 'btn soft sm', onclick: async () => { if (!(await confirmBox({ title: 'Leave this family?', body: 'You will no longer see their children.', confirm: 'Leave', danger: true }))) return; try { await api(`/household/joined/${j.id}`, { method: 'DELETE' }); onChange(true); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Leave'))),
      r.members.length ? h('div', { class: 'list', style: 'margin-bottom:12px' }, r.members.map((m) => h('div', { class: 'item', 'data-member': m.email },
        h('div', { class: 'grow' }, h('b', null, m.name || m.email), h('span', null, m.name ? m.email : 'Invitation sent')),
        h('span', { class: `chip ${m.status === 'active' ? 'sage' : 'butter'}` }, m.status === 'active' ? 'Shares your children' : 'Waiting for them'),
        h('button', { class: 'icon-btn', 'aria-label': `Remove ${m.email}`, onclick: async () => { if (!(await confirmBox({ title: m.status === 'active' ? `Stop sharing with ${m.name || m.email}?` : 'Cancel this invitation?', body: m.status === 'active' ? 'They will no longer see your children or get their alerts.' : 'They will not be able to accept it.', confirm: m.status === 'active' ? 'Stop sharing' : 'Cancel invitation', danger: true }))) return; try { await api(`/household/members/${m.id}`, { method: 'DELETE' }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('x', 18))))) : null,
      r.members.length < r.max_members ? h('form', { class: 'row wrap', novalidate: true, onsubmit: async (e) => {
        e.preventDefault(); err.textContent = '';
        if (!/^\S+@\S+\.\S+$/.test(email.value.trim())) { err.textContent = 'Enter a valid email address.'; return; }
        try { await api('/household/invite', { method: 'POST', body: { email: email.value.trim() } }); toast('Invitation sent.'); draw(); } catch (er) { err.textContent = errorText(er); }
      } }, h('div', { style: 'flex:1;min-width:200px' }, email), h('button', { class: 'btn primary', type: 'submit', 'data-act': 'invite' }, icon('plus', 20), 'Invite')) : h('p', { class: 'hint' }, `You can share with up to ${r.max_members} people.`),
      err);
  };
  draw();
  return box;
}

/** A banner on the dashboard when someone has invited you. */
export async function invitationBanner(onChange) {
  let r;
  try { r = await api('/household'); } catch { return null; }
  if (!r.invitations.length) return null;
  const i = r.invitations[0];
  return h('div', { class: 'alert-hero', 'data-banner': 'invite' }, h('div', { class: 'tx' }, h('h2', null, `${i.from.name || i.from.email} invited you to share their children`), h('p', null, 'Accept to see their lessons and progress and answer their alerts.')),
    h('button', { class: 'btn primary', 'data-act': 'accept-banner', onclick: async () => { try { await api(`/household/invitations/${i.id}/accept`, { method: 'POST' }); toast('You can now see their children.'); onChange(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Accept'),
    h('button', { class: 'btn soft', onclick: async () => { try { await api(`/household/invitations/${i.id}/decline`, { method: 'POST' }); onChange(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Decline'));
}
