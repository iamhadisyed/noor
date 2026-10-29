import { api, auth } from './api.js';
import { h, clear, icon, spinner, toast, initial, errorText } from './ui.js';

const root = document.getElementById('app');

/* ---------- messages from the platform owner, shown above every page until dismissed ---------- */
const announce = h('div', { class: 'announce', 'aria-live': 'polite' });
let announcedAt = 0;
async function refreshAnnouncements(force = false) {
  if (!auth.token || (!force && Date.now() - announcedAt < 60000)) return;
  announcedAt = Date.now();
  try {
    const rows = await api('/announcements');
    announce.replaceChildren(...rows.map((a) => h('div', { class: `ann ${a.level}`, role: 'status', 'data-announcement': a.id },
      h('div', { class: 'grow' }, h('b', null, a.title), h('span', null, a.body)),
      h('button', { class: 'icon-btn', 'aria-label': 'Dismiss this message', onclick: async () => { try { await api(`/announcements/${a.id}/dismiss`, { method: 'POST' }); } catch { /* shown again next time */ } refreshAnnouncements(true); } }, icon('x', 18)))));
  } catch { /* a missing banner is not worth an error */ }
}
export const state = { user: null, children: null, childId: null };

/* ---------- routes (hash based, so it works on any static host) ---------- */
const lazy = (f) => () => import(f);
const routes = [
  { path: '/login', view: lazy('./views/login.js'), public: true },
  { path: '/terms', view: lazy('./views/terms.js'), public: true },
  { path: '/welcome', view: lazy('./views/login.js'), name: 'welcome' },
  { path: '/', view: lazy('./views/dashboard.js'), role: 'parent', nav: 'home' },
  { path: '/children', view: lazy('./views/children.js'), role: 'parent', nav: 'children' },
  { path: '/lessons', view: lazy('./views/lessons.js'), role: 'parent', nav: 'lessons' },
  { path: '/lessons/:id', view: lazy('./views/lesson-editor.js'), role: 'parent', nav: 'lessons' },
  { path: '/specialists', view: lazy('./views/specialists.js'), role: 'parent', nav: 'specialists' },
  { path: '/shared', view: lazy('./views/shared.js'), role: 'parent', nav: 'shared' },
  { path: '/cards', view: lazy('./views/cards.js'), role: 'parent', nav: 'cards' },
  { path: '/rewards', view: lazy('./views/rewards.js'), role: 'parent', nav: 'rewards' },
  { path: '/reports', view: lazy('./views/reports.js'), role: 'parent', nav: 'reports' },
  { path: '/alerts', view: lazy('./views/alerts.js'), role: 'parent', nav: 'alerts' },
  { path: '/activity', view: lazy('./views/family.js'), role: 'parent', nav: 'activity', name: 'activity' },
  { path: '/screen-time', view: lazy('./views/family.js'), role: 'parent', nav: 'screen-time', name: 'screen-time' },
  { path: '/guide', view: lazy('./views/guidance.js'), role: 'parent', nav: 'guide' },
  { path: '/settings', view: lazy('./views/settings.js'), nav: 'settings' },
  { path: '/admin', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-overview', name: 'overview' },
  { path: '/admin/usage', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-usage', name: 'usage' },
  { path: '/admin/vouchers', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-vouchers', name: 'vouchers' },
  { path: '/admin/announcements', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-news', name: 'announcements' },
  { path: '/admin/games', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-games', name: 'games' },
  { path: '/admin/content', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-content', name: 'content' },
  { path: '/admin/quality', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-quality', name: 'quality' },
  { path: '/admin/alerts', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-alerts', name: 'alerts' },
  { path: '/admin/ai', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-ai', name: 'ai' },
  { path: '/admin/upgrades', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-upgrades', name: 'upgrades' },
  { path: '/admin/accounts', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-accounts', name: 'accounts' },
  { path: '/admin/audit', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-audit', name: 'audit' },
  { path: '/admin/health', view: lazy('./views/admin.js'), role: 'super_admin', nav: 'a-health', name: 'health' },
];
const NAV = {
  parent: [['home', '#/', 'home', 'Dashboard'], ['children', '#/children', 'kids', 'Children'], ['lessons', '#/lessons', 'book', 'Lessons'], ['cards', '#/cards', 'image', 'Cards'], ['rewards', '#/rewards', 'star', 'Rewards'], ['specialists', '#/specialists', 'shield', 'Specialists'], ['activity', '#/activity', 'pulse', 'Activity'], ['screen-time', '#/screen-time', 'heart', 'Screen time'], ['reports', '#/reports', 'chart', 'Reports'], ['alerts', '#/alerts', 'bell', 'Alerts'], ['guide', '#/guide', 'spark', 'Parent guide'], ['settings', '#/settings', 'gear', 'Settings']],
  super_admin: [['a-overview', '#/admin', 'pulse', 'Overview'], ['a-usage', '#/admin/usage', 'chart', 'Usage'], ['a-quality', '#/admin/quality', 'shield', 'Quality'], ['a-content', '#/admin/content', 'flag', 'Content review'], ['a-games', '#/admin/games', 'star', 'Game library'], ['a-news', '#/admin/announcements', 'bell', 'Announcements'], ['a-vouchers', '#/admin/vouchers', 'list', 'Vouchers'], ['a-alerts', '#/admin/alerts', 'bell', 'Alerts'], ['a-ai', '#/admin/ai', 'spark', 'AI reports'], ['a-upgrades', '#/admin/upgrades', 'star', 'Pro requests'], ['a-accounts', '#/admin/accounts', 'users', 'Accounts'], ['a-audit', '#/admin/audit', 'list', 'Audit log'], ['a-health', '#/admin/health', 'heart', 'Health'], ['settings', '#/settings', 'gear', 'Settings']],
};

export const go = (hash) => { if (location.hash === hash) route(); else location.hash = hash; };

function match(hash) {
  const [pathPart, qs = ''] = hash.replace(/^#/, '').split('?');
  const path = pathPart || '/';
  const segs = path.split('/').filter(Boolean);
  for (const r of routes) {
    const rs = r.path.split('/').filter(Boolean);
    if (rs.length !== segs.length) continue;
    const params = {};
    if (rs.every((s, i) => (s.startsWith(':') ? ((params[s.slice(1)] = decodeURIComponent(segs[i])), true) : s === segs[i]))) return { route: r, params, query: Object.fromEntries(new URLSearchParams(qs)) };
  }
  return null;
}

let navId = 0, cleanup = null, termsAsked = false;
async function route() {
  const my = ++navId;
  if (cleanup) { try { cleanup(); } catch { /* view gone */ } cleanup = null; }
  const m = match(location.hash || '#/');

  if (!m) return go(auth.token ? '#/' : '#/login');
  if (!m.route.public && !auth.token) return go('#/login');
  if (m.route.public && auth.token && m.route.path === '/login') return go(homeFor());

  if (auth.token && !state.user) {
    try { state.user = await api('/me'); auth.user = state.user; }
    catch (e) { auth.token = null; return go('#/login'); }
    if (my !== navId) return;
  }
  if (m.route.role && state.user && state.user.role !== m.route.role) return go(homeFor());
  if (state.user?.terms_required && !termsAsked) { termsAsked = true; import('./views/terms.js').then((t) => t.askAgain((me) => { state.user = me; })); }

  clear(root).append(m.route.public ? spinner() : shell(m.route.nav, spinner()));
  const mod = await m.route.view();
  if (my !== navId) return;
  const ctx = { state, go, api, setCleanup: (f) => { cleanup = f; }, shell: (el) => place(m.route.nav, el), navId: () => navId, isCurrent: () => my === navId };
  try {
    const out = await mod.render(ctx, m.params, m.query, m.route.name);
    if (my !== navId) return;
    if (out) m.route.public ? clear(root).append(out) : place(m.route.nav, out);
  } catch (e) {
    if (my !== navId) return;
    console.error(e);
    const bad = h('div', { class: 'card' }, h('h2', null, 'We could not load this page'), h('p', { class: 'muted', style: 'margin:8px 0 16px' }, errorText(e)), h('button', { class: 'btn primary', onclick: () => route() }, 'Try again'));
    m.route.public ? clear(root).append(bad) : place(m.route.nav, bad);
  }
  document.title = `${titleOf(m)} · Kindo`;
  if (!m.route.public) refreshAnnouncements();
}
const titleOf = (m) => (m.route.nav ? (Object.values(NAV).flat().find((n) => n[0] === m.route.nav) || [])[3] || 'Kindo' : 'Sign in');

export const homeFor = () => (state.user?.role === 'super_admin' ? '#/admin' : '#/');

/* ---------- shell ---------- */
function place(active, content) {
  const slot = document.getElementById('content');
  if (!slot) return clear(root).append(shell(active, content));
  clear(slot).append(announce, content);
  slot.focus({ preventScroll: true });
}
function shell(active, content) {
  const role = state.user?.role || 'parent';
  const items = NAV[role].slice();
  if (role === 'parent' && state.user?.shared_children > 0) items.splice(items.length - 1, 0, ['shared', '#/shared', 'users', 'Shared with me']);   // you are also someone's therapist or teacher
  const link = (n, cls) => h('a', { href: n[1], 'aria-current': n[0] === active ? 'page' : null }, icon(n[2], 22), cls === 'bottom' ? h('span', null, n[3]) : n[3]);
  // On a phone the bar holds the four things used most; everything else, and Sign out, is under More so no page is out of reach.
  const PRIMARY = role === 'parent' ? ['home', 'lessons', 'cards', 'alerts'] : ['a-overview', 'a-content', 'a-accounts', 'a-quality'];
  const bottom = items.filter((n) => PRIMARY.includes(n[0])).map((n) => (n[0] === 'home' ? [n[0], n[1], n[2], 'Home'] : n));
  const rest = items.filter((n) => !PRIMARY.includes(n[0]));
  const sheet = h('div', { class: 'more-sheet', id: 'more-sheet', role: 'group', 'aria-label': 'More pages', hidden: true },
    rest.map((n) => link(n)), h('button', { class: 'btn soft', onclick: signOut }, icon('logout', 20), 'Sign out'));
  const moreBtn = h('button', { class: 'more-btn', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'more-sheet', 'aria-current': rest.some((n) => n[0] === active) ? 'page' : null,
    onclick: () => { const open = sheet.hidden; sheet.hidden = !open; moreBtn.setAttribute('aria-expanded', String(open)); if (open) sheet.querySelector('a')?.focus(); } }, icon('list', 22), h('span', null, 'More'));
  sheet.addEventListener('keydown', (e) => { if (e.key === 'Escape') { sheet.hidden = true; moreBtn.setAttribute('aria-expanded', 'false'); moreBtn.focus(); } });
  return h('div', { class: 'shell' },
    h('a', { class: 'skip', href: '#content', onclick: (e) => { e.preventDefault(); document.getElementById('content')?.focus(); } }, 'Skip to content'),
    h('aside', { class: 'side' },
      h('div', { class: 'brand' }, h('div', { class: 'logo', 'aria-hidden': 'true' }, 'K'), 'Kindo'),
      h('nav', { class: 'nav', 'aria-label': 'Main' }, items.map((n) => link(n))),
      h('div', { class: 'foot' },
        h('div', { class: 'who' }, h('div', { class: 'av' }, initial(state.user?.name || state.user?.email)), h('div', null, h('b', null, state.user?.name || state.user?.email), h('span', null, role === 'super_admin' ? 'Super admin' : state.user?.plan === 'pro' ? 'Pro plan' : 'Free plan'))),
        h('button', { class: 'btn soft', onclick: signOut }, icon('logout', 20), 'Sign out'))),
    h('main', { class: 'main', id: 'content', tabindex: '-1' }, announce, content),
    h('nav', { class: 'bottom-nav', 'aria-label': 'Main' }, bottom.map((n) => link(n, 'bottom')), moreBtn), sheet);
}
export async function signOut() {
  try { await api('/auth/logout', { method: 'POST' }); } catch { /* token may already be gone */ }
  auth.token = null; auth.user = null; state.user = null; state.children = null; state.childId = null;
  announce.replaceChildren(); announcedAt = 0;
  go('#/login');
}
auth.onExpire = () => { state.user = null; toast('Your session ended. Please sign in again.', 'err'); go('#/login'); };

/* ---------- children (shared by several views) ---------- */
export async function loadChildren(force = false) {
  if (!state.children || force) state.children = await api('/children');
  let saved = null; try { saved = +localStorage.getItem('kindo.child'); } catch { /* ignore */ }
  if (!state.children.some((c) => c.id === state.childId)) state.childId = state.children.find((c) => c.id === saved)?.id ?? state.children[0]?.id ?? null;
  return state.children;
}
export function selectChild(id) { state.childId = +id; try { localStorage.setItem('kindo.child', String(id)); } catch { /* ignore */ } }
export const currentChild = () => state.children?.find((c) => c.id === state.childId) || null;

/** The picker shown at the top of child-specific pages. */
export function childPicker(onChange) {
  if (!state.children || state.children.length < 2) return null;
  const sel = h('select', { class: 'select', 'aria-label': 'Child', style: 'width:auto;min-width:180px', onchange: (e) => { selectChild(e.target.value); onChange(); } },
    state.children.map((c) => h('option', { value: c.id, selected: c.id === state.childId ? true : null }, c.name)));
  sel.value = state.childId;
  return sel;
}

addEventListener('hashchange', route);
route();
