import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, debounce, spinner, confirmBox } from '../ui.js';
import { bars, hbars } from '../charts.js';
import { state } from '../main.js';

/** Super admin console. Everything here is aggregate or operational; no child names, pictures or voices. */
export async function render(ctx, params, query, name) {
  const view = { overview, usage, quality, alerts, ai, accounts, audit, health }[name] || overview;
  const page = h('div', { class: 'page' }, spinner());
  const out = await view(ctx, page);
  return out || page;
}
const head = (title, sub, ...actions) => h('div', { class: 'page-head' }, h('div', null, h('h1', null, title), sub ? h('p', null, sub) : null), actions.length ? h('div', { class: 'row' }, actions) : null);
const kpi = (label, n, sub, cls = '') => h('div', { class: `kpi ${cls}` }, h('div', { class: 'l' }, label), h('div', { class: 'n' }, n), sub ? h('div', { class: 's' }, sub) : null);
const seg = (value, opts, on) => h('div', { class: 'seg', role: 'radiogroup' }, opts.map(([v, l]) => h('button', { role: 'radio', 'aria-checked': String(value === v), onclick: () => on(v) }, l)));

async function overview(ctx, page) {
  const o = await api('/admin/overview');
  page.replaceChildren(head('Overview', 'The whole platform at a glance.'),
    h('div', { class: 'kpis' },
      kpi('Accounts', fmt.num(o.accounts.total), `${o.accounts.new_7d} new this week`), kpi('Active this week', fmt.num(o.accounts.active_7d), 'signed in at least once'),
      kpi('Pro / Free', `${o.accounts.pro} / ${o.accounts.free}`, `${o.accounts.suspended} suspended`), kpi('Children', fmt.num(o.children.total), `${o.children.special_track} on the special track`)),
    h('div', { class: 'kpis' },
      kpi('Lessons published', fmt.num(o.lessons.published), `${o.lessons.drafts} drafts · ${o.lessons.tasks} tasks`), kpi('Alerts (7 days)', fmt.num(o.alerts_7d.total), `${o.alerts_7d.answered} answered`),
      kpi('Average reply', o.alerts_7d.avg_reply_seconds == null ? '—' : fmt.secs(o.alerts_7d.avg_reply_seconds), 'to a child’s alert'), kpi('Waiting over 15 min', fmt.num(o.alerts_7d.unanswered_over_15m), o.alerts_7d.unanswered_over_15m ? 'needs attention' : 'all clear')),
    h('div', { class: 'kpis' }, kpi('Practice (7 days)', `${fmt.num(o.practice_7d.minutes)} min`, `${o.practice_7d.sessions} sessions`), kpi('AI reports (7 days)', fmt.num(o.ai_reports_7d.generated), 'weekly insights')));
}

async function usage(ctx, page) {
  let days = 14, metric = 'logins';
  const MET = [['logins', 'Sign-ins'], ['signups', 'New accounts'], ['lessons_published', 'Lessons published'], ['alerts', 'Alerts'], ['practice_sessions', 'Practice sessions']];
  const draw = async () => {
    const rows = await api(`/admin/usage?days=${days}`);
    const total = rows.reduce((a, r) => a + r[metric], 0);
    page.replaceChildren(head('Usage', 'Daily activity across the platform.', seg(days, [[14, '14 days'], [30, '30 days'], [90, '90 days']], (v) => { days = v; draw(); })),
      h('div', { class: 'tabs', role: 'tablist' }, MET.map(([k, l]) => h('button', { role: 'tab', 'aria-selected': String(metric === k), onclick: () => { metric = k; draw(); } }, l))),
      h('section', { class: 'card' }, h('div', { class: 'row between' }, h('h2', null, MET.find((x) => x[0] === metric)[1]), h('span', { class: 'chip ink' }, `${fmt.num(total)} in ${days} days`)),
        bars(rows.map((r) => ({ label: String(new Date(r.date + 'T12:00:00').getDate()), value: r[metric] })), { thin: true, name: MET.find((x) => x[0] === metric)[1] })));
  };
  await draw();
}

async function quality(ctx, page) {
  const q = await api('/admin/quality');
  page.replaceChildren(head('Content quality', 'How complete the lessons parents build are.'),
    h('div', { class: 'kpis' }, kpi('Draft lessons', q.draft_lessons, `${q.draft_lessons_blocked} cannot be published yet`), kpi('Tasks missing a required sound', q.tasks_missing_required_sound, q.tasks_missing_required_sound ? 'parents may need help' : 'none')),
    h('div', { class: 'grid2' }, h('section', { class: 'card' }, h('h2', null, 'Tasks by type'), hbars(q.tasks_by_type, { empty: 'No tasks yet.' })),
      h('section', { class: 'card' }, h('h2', null, 'Reply time, last 30 days'), hbars(q.reply_time_30d, { empty: 'No alerts yet.' }))));
}

async function alerts(ctx, page) {
  const a = await api('/admin/alerts?hours=168');
  page.replaceChildren(head('Alerts', 'Child requests over the last 7 days.'),
    h('div', { class: 'grid2' }, h('section', { class: 'card' }, h('h2', null, 'By hour of day'), bars(Object.entries(a.by_hour).map(([k, v]) => ({ label: k, value: v })), { thin: true, name: 'Alerts by hour' })),
      h('section', { class: 'card' }, h('h2', null, 'By need'), hbars(a.by_need, { empty: 'No alerts yet.' }))),
    h('section', { class: 'card', style: 'margin-top:24px' }, h('h2', { style: 'margin-bottom:12px' }, 'Unanswered for more than 15 minutes'),
      a.unanswered_over_15m.length ? h('div', { class: 'table-wrap' }, h('table', null, h('thead', null, h('tr', null, ['Alert', 'Child ID', 'Need', 'Waiting'].map((t) => h('th', null, t)))),
        h('tbody', null, a.unanswered_over_15m.map((r) => h('tr', null, h('td', null, `#${r.id}`), h('td', null, r.child_id), h('td', null, r.need_type), h('td', null, h('span', { class: 'chip warn' }, `${r.minutes_waiting} min`))))))) : h('p', { class: 'muted' }, 'None. Every alert has been answered or is recent.')));
}

async function ai(ctx, page) {
  const r = await api('/admin/ai-reports');
  page.replaceChildren(head('AI reports', 'Weekly insights and which provider wrote them.'),
    h('div', { class: 'kpis' }, kpi('Generated (7 days)', r.generated_7d), kpi('Failed jobs (7 days)', r.failed_7d, r.failed_7d ? 'check the keys and quotas' : 'none')),
    h('section', { class: 'card' }, h('h2', null, 'By provider'), hbars(r.by_provider_7d, { empty: 'No reports generated yet.' })));
}

async function accounts(ctx, page) {
  let search = '', pg = 1;
  const body = h('div', null, spinner());
  const load = async () => {
    const r = await api(`/admin/users?search=${encodeURIComponent(search)}&page=${pg}`);
    body.replaceChildren(h('div', { class: 'card' }, h('div', { class: 'table-wrap' }, h('table', null,
      h('thead', null, h('tr', null, ['Email', 'Role', 'Plan', 'Children', 'Last sign-in', 'Status', ''].map((t) => h('th', null, t)))),
      h('tbody', null, r.data.length ? r.data.map(row) : h('tr', null, h('td', { colspan: 7, class: 'muted' }, 'No accounts match.'))))),
      h('div', { class: 'pager' }, h('span', { class: 'muted' }, `${r.meta.total} accounts · page ${r.meta.page} of ${r.meta.last_page}`), h('button', { class: 'btn soft sm', disabled: pg <= 1, onclick: () => { pg -= 1; load(); } }, 'Previous'), h('button', { class: 'btn soft sm', disabled: pg >= r.meta.last_page, onclick: () => { pg += 1; load(); } }, 'Next'))));
  };
  const row = (u) => {
    const self = u.id === state.user.id, admin = u.role === 'super_admin';
    return h('tr', { 'data-email': u.email },
      h('td', null, h('b', null, u.email), h('div', { class: 'hint' }, u.name)), h('td', null, admin ? h('span', { class: 'chip ink' }, 'Super admin') : 'Parent'),
      h('td', null, (() => { const s = h('select', { class: 'select', style: 'min-height:40px;width:110px;font-size:14px', 'aria-label': `Plan for ${u.email}`, onchange: async (e) => { try { await api(`/admin/users/${u.id}`, { method: 'PATCH', body: { plan: e.target.value } }); toast('Plan updated.'); } catch (er) { toast(errorText(er), 'err'); load(); } } }, [['free', 'Free'], ['pro', 'Pro']].map(([v, l]) => h('option', { value: v }, l))); s.value = u.plan; return s; })()),
      h('td', null, u.children), h('td', null, fmt.ago(u.last_login_at)), h('td', null, h('span', { class: `chip ${u.status === 'active' ? 'sage' : 'warn'}` }, u.status === 'active' ? 'Active' : 'Suspended')),
      h('td', null, admin || self ? null : u.status === 'active'
        ? h('button', { class: 'btn danger sm', onclick: async () => { if (!(await confirmBox({ title: `Suspend ${u.email}?`, body: 'They are signed out everywhere at once and cannot sign in until you restore them.', confirm: 'Suspend', danger: true }))) return; try { await api(`/admin/users/${u.id}`, { method: 'PATCH', body: { status: 'suspended' } }); toast('Account suspended.'); load(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Suspend')
        : h('button', { class: 'btn soft sm', onclick: async () => { try { await api(`/admin/users/${u.id}`, { method: 'PATCH', body: { status: 'active' } }); toast('Account restored.'); load(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Restore')));
  };
  const input = h('input', { class: 'input', type: 'search', 'aria-label': 'Search accounts', placeholder: 'Search by email or name', style: 'min-width:280px', oninput: debounce((e) => { search = e.target.value.trim(); pg = 1; load(); }, 350) });
  page.replaceChildren(head('Accounts', 'Search, suspend or restore, and change plans. Every change is audited.', input), body);
  await load();
}

async function audit(ctx, page) {
  let action = '', pg = 1;
  const body = h('div', null, spinner());
  const load = async () => {
    const r = await api(`/admin/audit?action=${encodeURIComponent(action)}&page=${pg}`);
    body.replaceChildren(h('div', { class: 'card' }, h('div', { class: 'table-wrap' }, h('table', null, h('thead', null, h('tr', null, ['When', 'Action', 'Actor', 'Subject', 'IP'].map((t) => h('th', null, t)))),
      h('tbody', null, r.data.map((a) => h('tr', null, h('td', null, fmt.dateTime(a.created_at)), h('td', null, h('span', { class: 'chip' }, a.action)), h('td', null, a.actor_id ?? '—'), h('td', null, a.subject_type ? `${a.subject_type} #${a.subject_id}` : '—'), h('td', { class: 'muted' }, a.ip || '—')))))),
      h('div', { class: 'pager' }, h('span', { class: 'muted' }, `${r.meta.total} entries · page ${r.meta.page} of ${r.meta.last_page}`), h('button', { class: 'btn soft sm', disabled: pg <= 1, onclick: () => { pg -= 1; load(); } }, 'Previous'), h('button', { class: 'btn soft sm', disabled: pg >= r.meta.last_page, onclick: () => { pg += 1; load(); } }, 'Next'))));
  };
  const sel = h('select', { class: 'select', 'aria-label': 'Filter by action', style: 'width:auto;min-width:200px', onchange: (e) => { action = e.target.value; pg = 1; load(); } },
    [['', 'All actions'], ['auth.', 'Sign-ins and sessions'], ['lesson.', 'Lessons'], ['child.', 'Child deletions'], ['admin.', 'Admin changes']].map(([v, l]) => h('option', { value: v }, l)));
  page.replaceChildren(head('Audit log', 'Who did what, and when.', sel), body);
  await load();
}

async function health(ctx, page) {
  const s = await api('/admin/health');
  const row = (label, ok, detail) => h('div', { class: 'item' }, h('span', { class: `dot${ok ? '' : ' bad'}`, role: 'img', 'aria-label': ok ? 'Healthy' : 'Problem' }), h('div', { class: 'grow' }, h('b', null, label), h('span', null, detail)));
  page.replaceChildren(head('System health', 'Checked just now.'),
    h('section', { class: 'card' }, h('div', { class: 'list' },
      row('Database', s.database, s.database ? 'Connected' : 'Cannot connect'),
      row('Scheduler (cron)', s.scheduler.alive, s.scheduler.last_run ? `Last ran ${fmt.ago(s.scheduler.last_run)}` : 'Has not run. Check the cPanel cron job.'),
      row('Queue', (s.queue.failed ?? 0) === 0, `${s.queue.pending ?? 0} waiting · ${s.queue.failed ?? 0} failed`),
      row('Email', s.mail.driver !== 'log', s.mail.driver === 'log' ? 'Mail driver is "log": codes are not being emailed. Set MAIL_* in .env.' : `Sending with ${s.mail.driver}`),
      row('Stored media', true, `${s.storage.media_files} pictures and sounds`),
      row('Server', true, `PHP ${s.php}`))));
}
