import { api } from '../api.js';
import { h, icon, fmt, toast, errorText, debounce, spinner, confirmBox, modal } from '../ui.js';
import { bars, hbars } from '../charts.js';
import { state } from '../main.js';

/** Super admin console. Everything here is aggregate or operational; no child names, pictures or voices. */
export async function render(ctx, params, query, name) {
  const view = { overview, usage, quality, content, games, announcements, alerts, ai, accounts, audit, health }[name] || overview;
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
      h('td', null, u.children), h('td', null, fmt.ago(u.last_login_at)),
      h('td', null, h('span', { class: `chip ${u.status === 'active' ? 'sage' : 'warn'}` }, STATUS[u.status] || u.status), u.status_reason ? h('div', { class: 'hint' }, u.status_reason) : null),
      h('td', null, admin || self ? null : u.status === 'active'
        ? h('button', { class: 'btn danger sm', onclick: async () => {
            const reason = await reasonBox({ title: `Make ${u.email} inactive?`, body: 'They are signed out everywhere at once and cannot sign in until you make the account active again. Their lessons stay in place.', confirm: 'Make inactive', label: 'Reason (kept in the audit log)' });
            if (reason === null) return;
            try { await api(`/admin/users/${u.id}`, { method: 'PATCH', body: { status: 'inactive', reason } }); toast('Account is now inactive.'); load(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Make inactive')
        : h('button', { class: 'btn soft sm', onclick: async () => { try { await api(`/admin/users/${u.id}`, { method: 'PATCH', body: { status: 'active' } }); toast('Account is active again.'); load(); } catch (e) { toast(errorText(e), 'err'); } } }, 'Make active')));
  };
  const input = h('input', { class: 'input', type: 'search', 'aria-label': 'Search accounts', placeholder: 'Search by email or name', style: 'min-width:280px', oninput: debounce((e) => { search = e.target.value.trim(); pg = 1; load(); }, 350) });
  page.replaceChildren(head('Accounts', 'Search, make accounts inactive or active again, and change plans. Every change is audited.', input), body);
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

const STATUS = { active: 'Active', inactive: 'Inactive', suspended: 'Inactive' };

/** Ask for a short written reason. Resolves with the text ('' if left empty) or null if cancelled. */
function reasonBox({ title, body, confirm, label, danger = true }) {
  return new Promise((resolve) => modal((box, close) => {
    const ta = h('textarea', { class: 'textarea', id: 'why', maxlength: '300', 'aria-label': label });
    box.append(h('h2', null, title), h('p', { class: 'muted' }, body), h('div', { class: 'field', style: 'margin-top:16px' }, h('label', { for: 'why' }, label), ta),
      h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: () => { close(); resolve(null); } }, 'Cancel'), h('button', { class: `btn ${danger ? 'danger' : 'primary'}`, onclick: () => { close(); resolve(ta.value.trim()); } }, confirm)));
  }));
}

/* ---------- content review: what parents post for children ---------- */
async function content(ctx, page) {
  let tab = 'queue';
  const body = h('div', null);
  const draw = async () => {
    body.replaceChildren(spinner());
    const flagsOpen = (await api('/admin/content/flags?status=open')).meta.open;
    const tabs = h('div', { class: 'tabs', role: 'tablist' }, [['queue', `Review queue${flagsOpen ? ` (${flagsOpen})` : ''}`], ['browse', 'All lessons'], ['words', 'Blocked words']].map(([k, l]) =>
      h('button', { role: 'tab', 'aria-selected': String(tab === k), onclick: () => { tab = k; draw(); } }, l)));
    const inner = await ({ queue: queueTab, browse: browseTab, words: wordsTab }[tab])();
    body.replaceChildren(tabs, inner);
  };
  page.replaceChildren(head('Content review', 'Lessons, pictures and sounds that parents make for children. Opening a lesson is recorded in the audit log.'), body);
  await draw();

  async function queueTab() {
    const r = await api('/admin/content/flags?status=open');
    if (!r.data.length) return h('div', { class: 'card' }, h('h2', null, 'Nothing to review'), h('p', { class: 'muted' }, 'New flags appear here when the word filter finds something or you flag a lesson yourself.'));
    return h('div', { class: 'card' }, h('div', { class: 'table-wrap' }, h('table', null,
      h('thead', null, h('tr', null, ['When', 'Parent', 'Why flagged', 'Text', ''].map((t) => h('th', null, t)))),
      h('tbody', null, r.data.map((f) => h('tr', { 'data-flag': f.id },
        h('td', null, fmt.ago(f.created_at)), h('td', null, f.owner.email || `#${f.owner.id}`, f.owner.status && f.owner.status !== 'active' ? h('div', { class: 'hint' }, STATUS[f.owner.status]) : null),
        h('td', null, f.reason === 'bad_word' ? h('span', { class: 'chip bad' }, `Word: ${f.term}`) : h('span', { class: 'chip' }, 'Flagged by admin')),
        h('td', null, f.excerpt || f.note || '—'),
        h('td', null, h('div', { class: 'row wrap' },
          f.lesson_id ? h('button', { class: 'btn soft sm', onclick: () => openLesson(f.lesson_id) }, icon('eye', 16), 'Open') : null,
          f.family_member_id ? h('button', { class: 'btn soft sm', onclick: () => openFamily(f.family_member_id) }, icon('eye', 16), 'Open') : null,
          h('button', { class: 'btn soft sm', 'data-act': 'dismiss', onclick: () => decide(f, 'dismiss') }, 'Looks fine'),
          h('button', { class: 'btn danger sm', 'data-act': 'remove', onclick: () => decide(f, 'remove') }, 'Remove'),
          f.owner.status === 'active' ? h('button', { class: 'btn danger sm', 'data-act': 'deactivate_owner', onclick: () => decide(f, 'deactivate_owner') }, 'Remove and deactivate') : null))))))));
  }

  async function decide(f, action) {
    const T = { dismiss: ['Looks fine?', 'The flag is closed and the lesson can be published again. The same text will not be flagged twice.', 'Looks fine', false],
      remove: ['Remove this content?', f.family_member_id ? 'The family card and its files are deleted.' : 'The lesson is archived and hidden from the child.', 'Remove', true],
      deactivate_owner: ['Remove it and make the parent inactive?', 'The content is removed, and the parent is signed out and cannot sign in until you make the account active again.', 'Remove and deactivate', true] }[action];
    const note = await reasonBox({ title: T[0], body: T[1], confirm: T[2], label: 'Note (kept in the audit log)', danger: T[3] });
    if (note === null) return;
    try { await api(`/admin/content/flags/${f.id}/resolve`, { method: 'POST', body: { action, note } }); toast('Done.'); draw(); } catch (e) { toast(errorText(e), 'err'); }
  }

  /** Everything in the lesson: text, pictures you can see and sounds you can play. */
  async function openLesson(id) {
    let l;
    try { l = await api(`/admin/content/lessons/${id}`); } catch (e) { return toast(errorText(e), 'err'); }
    modal((box, close) => {
      box.classList.add('wide');
      box.append(h('div', null, h('div', { class: 'row between' }, h('div', null, h('h2', null, l.title), h('p', { class: 'muted' }, `${l.owner?.email || ''} · child ${l.child_name || '—'} · ${l.status}`)), h('button', { class: 'icon-btn', 'aria-label': 'Close', onclick: close }, icon('x', 20))),
        l.description ? h('p', null, l.description) : null,
        l.flags.filter((f) => f.status === 'open').map((f) => h('div', { class: 'chip bad', style: 'margin:4px 8px 4px 0' }, f.reason === 'bad_word' ? `Flagged word: ${f.term}` : 'Flagged by admin')),
        l.tasks.map((t) => h('div', { class: 'review-task' },
          t.image_url ? h('img', { src: t.image_url, alt: t.image_alt || `Picture for ${t.title}`, loading: 'lazy' }) : h('div', { class: 'noimg' }, 'No picture'),
          h('div', null, h('b', null, `${t.position}. ${t.title}`), h('div', { class: 'hint' }, t.type.replace(/_/g, ' '), t.target ? ` · ${t.target}` : ''), t.instruction ? h('p', null, t.instruction) : null,
            t.audio_url ? h('audio', { controls: true, preload: 'none', src: t.audio_url, 'aria-label': `Sound for ${t.title}` }) : h('div', { class: 'hint' }, 'No sound')))),
        h('div', { class: 'actions' }, l.flags.some((f) => f.status === 'open') ? null : h('button', { class: 'btn danger', onclick: async () => {
          const note = await reasonBox({ title: 'Flag this lesson?', body: 'It is taken off the child’s device until you review it.', confirm: 'Flag for review', label: 'Note' });
          if (note === null) return;
          try { await api(`/admin/content/lessons/${id}/flag`, { method: 'POST', body: { note } }); close(); toast('Flagged.'); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('flag', 18), 'Flag for review'),
          h('button', { class: 'btn soft', onclick: close }, 'Close'))));
    });
  }

  /** A flagged family card: the name, the photo, and the voice. */
  async function openFamily(id) {
    let m;
    try { m = await api(`/admin/content/family/${id}`); } catch (e) { return toast(errorText(e), 'err'); }
    modal((box, close) => box.append(h('div', null, h('div', { class: 'row between' }, h('div', null, h('h2', null, `${m.relation_title}: ${m.display_name}`), h('p', { class: 'muted' }, `${m.owner?.email || ''} · child ${m.child_name || '—'} · family card`)), h('button', { class: 'icon-btn', 'aria-label': 'Close', onclick: close }, icon('x', 20))),
      h('div', { class: 'review-task' }, m.photo_url ? h('img', { src: m.photo_url, alt: `Photo of ${m.display_name}` }) : h('div', { class: 'noimg' }, 'No photo'),
        h('div', null, m.audio_url ? h('audio', { controls: true, preload: 'none', src: m.audio_url, 'aria-label': `Voice for ${m.display_name}` }) : h('div', { class: 'hint' }, 'No voice'))),
      h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Close')))));
  }

  async function browseTab() {
    let search = '', flagged = false, pg = 1;
    const list = h('div', null, spinner());
    const load = async () => {
      const r = await api(`/admin/content/lessons?search=${encodeURIComponent(search)}&flagged=${flagged ? 1 : 0}&page=${pg}`);
      list.replaceChildren(h('div', { class: 'card' }, h('div', { class: 'table-wrap' }, h('table', null,
        h('thead', null, h('tr', null, ['Lesson', 'Parent', 'Child', 'Tasks', 'Status', ''].map((t) => h('th', null, t)))),
        h('tbody', null, r.data.length ? r.data.map((l) => h('tr', null, h('td', null, h('b', null, l.title)), h('td', null, l.owner_email), h('td', null, l.child || '—'), h('td', null, l.tasks),
          h('td', null, h('span', { class: `chip ${l.open_flags ? 'bad' : l.status === 'published' ? 'sage' : ''}` }, l.open_flags ? 'Flagged' : l.status)),
          h('td', null, h('button', { class: 'btn soft sm', onclick: () => openLesson(l.id) }, icon('eye', 16), 'Open')))) : h('tr', null, h('td', { colspan: 6, class: 'muted' }, 'No lessons match.'))))),
        h('div', { class: 'pager' }, h('span', { class: 'muted' }, `${r.meta.total} lessons · page ${r.meta.page} of ${r.meta.last_page}`), h('button', { class: 'btn soft sm', disabled: pg <= 1, onclick: () => { pg -= 1; load(); } }, 'Previous'), h('button', { class: 'btn soft sm', disabled: pg >= r.meta.last_page, onclick: () => { pg += 1; load(); } }, 'Next'))));
    };
    const input = h('input', { class: 'input', type: 'search', 'aria-label': 'Search lessons', placeholder: 'Search by lesson title or parent email', style: 'max-width:420px', oninput: debounce((e) => { search = e.target.value.trim(); pg = 1; load(); }, 350) });
    const only = h('label', { class: 'check' }, h('input', { type: 'checkbox', onchange: (e) => { flagged = e.target.checked; pg = 1; load(); } }), ' Flagged only');
    const wrap = h('div', null, h('div', { class: 'row wrap', style: 'margin-bottom:16px;align-items:center' }, input, only), list);
    await load();
    return wrap;
  }

  async function wordsTab() {
    const r = await api('/admin/content/terms');
    const input = h('input', { class: 'input', 'aria-label': 'Word or phrase to block', placeholder: 'Add a word or short phrase', maxlength: '60', style: 'max-width:340px' });
    const add = async (e) => { e.preventDefault(); if (!input.value.trim()) return; try { await api('/admin/content/terms', { method: 'POST', body: { term: input.value } }); toast('Added.'); draw(); } catch (er) { toast(errorText(er), 'err'); } };
    return h('div', { class: 'card' }, h('h2', null, 'Blocked words'),
      h('p', { class: 'muted', style: 'margin:6px 0 16px' }, `${r.built_in_count} words are built in. Add your own (any language, including Urdu script). Matching is whole word and sees through tricks like f.u.c.k. A match never blocks saving: it flags the lesson for you and keeps it off the child’s device until you decide.`),
      h('form', { class: 'row wrap', onsubmit: add, style: 'margin-bottom:16px' }, input, h('button', { class: 'btn primary', type: 'submit' }, 'Add word')),
      r.custom.length ? h('div', { class: 'row wrap' }, r.custom.map((t) => h('span', { class: 'chip' }, t.term, h('button', { class: 'icon-btn', style: 'width:24px;height:24px;min-height:0', 'aria-label': `Remove ${t.term}`, onclick: async () => { try { await api(`/admin/content/terms/${t.id}`, { method: 'DELETE' }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('x', 14))))) : h('p', { class: 'hint' }, 'No custom words yet.'));
  }
}

/* ---------- game library: the animals the sound games use ---------- */
async function games(ctx, page) {
  const body = h('div', null, spinner());
  page.replaceChildren(head('Game library', 'Animals for the listening games. Each needs a picture and the sound it makes before children can play with it.'), body);

  const draw = async () => {
    const r = await api('/admin/games/items');
    const ready = r.complete >= r.needed;
    const name = h('input', { class: 'input', 'aria-label': 'Animal name in English', placeholder: 'Name in English', maxlength: '60', style: 'max-width:220px' });
    const nameUr = h('input', { class: 'input', 'aria-label': 'Animal name in Urdu', placeholder: 'اردو نام', maxlength: '60', dir: 'rtl', style: 'max-width:220px' });
    body.replaceChildren(
      h('div', { class: 'kpis' }, kpi('Ready to use', `${r.complete} of ${r.items.length}`, ready ? 'the sound games are open' : `${r.needed - r.complete} more needed to open the sound games`, ready ? '' : 'warn')),
      h('section', { class: 'card', style: 'margin-bottom:16px' }, h('h2', { style: 'margin-bottom:10px' }, 'Add an animal'),
        h('form', { class: 'row wrap', onsubmit: async (e) => { e.preventDefault(); if (!name.value.trim() || !nameUr.value.trim()) return toast('Enter both names.', 'err'); try { await api('/admin/games/items', { method: 'POST', body: { name_en: name.value.trim(), name_ur: nameUr.value.trim() } }); toast('Added. Now upload its picture and sound.'); draw(); } catch (er) { toast(errorText(er), 'err'); } } },
          name, nameUr, h('button', { class: 'btn primary', type: 'submit' }, icon('plus', 20), 'Add'))),
      h('div', { class: 'list' }, r.items.map((i) => card(i))));
  };

  const card = (i) => {
    const send = async (field, file) => {
      const form = new FormData(); form.append(field, file);
      try { await api(`/admin/games/items/${i.id}`, { method: 'POST', form }); toast('Saved.'); draw(); } catch (e) { toast(errorText(e), 'err'); }
    };
    const pick = (field, accept, label) => h('label', { class: 'btn soft sm', style: 'cursor:pointer' }, icon(field === 'image' ? 'image' : 'mic', 16), label,
      h('input', { type: 'file', accept, hidden: true, 'aria-label': `${label} for ${i.name_en}`, 'data-slot': field, onchange: (e) => { if (e.target.files[0]) send(field, e.target.files[0]); } }));
    return h('article', { class: 'item', 'data-slug': i.slug, style: 'align-items:flex-start' },
      i.image_url ? h('img', { src: i.image_url, alt: i.name_en, style: 'width:72px;height:72px;border-radius:18px;object-fit:cover;flex:none' }) : h('div', { class: 'review-task noimg', style: 'width:72px;height:72px;display:grid;place-items:center;border-radius:18px;background:var(--lavender-tint);font-size:12px;color:var(--muted,#8a7e90);flex:none' }, 'No picture'),
      h('div', { class: 'grow' }, h('b', null, `${i.name_en}  ·  `, h('span', { dir: 'rtl' }, i.name_ur)),
        h('div', { class: 'row wrap', style: 'margin-top:8px;align-items:center' }, i.audio_url ? h('audio', { controls: true, preload: 'none', src: i.audio_url, 'aria-label': `Sound of ${i.name_en}`, style: 'max-width:240px;height:36px' }) : h('span', { class: 'hint' }, 'No sound yet'),
          pick('image', 'image/*', i.has_image ? 'Replace picture' : 'Upload picture'), pick('audio', 'audio/*,video/webm,video/mp4', i.has_audio ? 'Replace sound' : 'Upload sound'))),
      h('div', { class: 'row wrap', style: 'justify-content:flex-end' }, h('span', { class: `chip ${i.complete ? 'sage' : 'warn'}` }, i.complete ? 'Ready' : !i.active ? 'Off' : 'Incomplete'),
        h('button', { class: 'btn soft sm', onclick: async () => { try { const f = new FormData(); f.append('active', i.active ? '0' : '1'); await api(`/admin/games/items/${i.id}`, { method: 'POST', form: f }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, i.active ? 'Turn off' : 'Turn on'),
        h('button', { class: 'icon-btn', 'aria-label': `Remove ${i.name_en}`, onclick: async () => { if (!(await confirmBox({ title: `Remove ${i.name_en}?`, body: 'Its picture and sound are deleted.', confirm: 'Remove', danger: true }))) return; try { await api(`/admin/games/items/${i.id}`, { method: 'DELETE' }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('trash', 18))));
  };
  await draw();
}

/* ---------- announcements: messages everyone sees until they end ---------- */
async function announcements(ctx, page) {
  const body = h('div', null, spinner());
  page.replaceChildren(head('Announcements', 'A message shown at the top of the portal and in the app until you end it. Parents can dismiss it.'), body);
  const local = (iso) => iso ? new Date(iso).toLocaleString() : 'no limit';
  const draw = async () => {
    const rows = await api('/admin/announcements');
    const title = h('input', { class: 'input', id: 'an-title', maxlength: '120', placeholder: 'Short headline', 'aria-label': 'Headline' });
    const text = h('textarea', { class: 'textarea', id: 'an-body', maxlength: '2000', placeholder: 'What do you want parents to know?', 'aria-label': 'Message' });
    const level = h('select', { class: 'select', id: 'an-level', 'aria-label': 'Importance' }, [['info', 'Information'], ['important', 'Important']].map(([v, l]) => h('option', { value: v }, l)));
    const from = h('input', { class: 'input', type: 'datetime-local', id: 'an-from', 'aria-label': 'Show from (optional)' });
    const until = h('input', { class: 'input', type: 'datetime-local', id: 'an-until', 'aria-label': 'Show until (optional)' });
    const err = h('div', { class: 'msg-err', role: 'alert' });
    const iso = (el) => (el.value ? new Date(el.value).toISOString() : null);
    body.replaceChildren(
      h('section', { class: 'card', style: 'margin-bottom:16px' }, h('h2', { style: 'margin-bottom:12px' }, 'New announcement'),
        h('div', { class: 'field' }, h('label', { for: 'an-title' }, 'Headline'), title), h('div', { class: 'field' }, h('label', { for: 'an-body' }, 'Message'), text),
        h('div', { class: 'grid2' }, h('div', { class: 'field' }, h('label', { for: 'an-level' }, 'Importance'), level), h('div', { class: 'field' }, h('label', { for: 'an-from' }, 'Show from (optional)'), from)),
        h('div', { class: 'field' }, h('label', { for: 'an-until' }, 'Show until (optional)'), until), err,
        h('button', { class: 'btn primary', 'data-act': 'publish', onclick: async () => {
          err.textContent = '';
          try { await api('/admin/announcements', { method: 'POST', body: { title: title.value.trim(), body: text.value.trim(), level: level.value, starts_at: iso(from), ends_at: iso(until) } }); toast('Published.'); draw(); } catch (e) { err.textContent = errorText(e); }
        } }, icon('plus', 20), 'Publish')),
      h('div', { class: 'list' }, rows.length ? rows.map((a) => h('article', { class: 'item', 'data-announcement-row': a.id, style: 'align-items:flex-start' },
        h('div', { class: 'grow' }, h('b', null, a.title), h('span', null, a.body), h('span', { class: 'hint' }, `Shown ${local(a.starts_at)} until ${local(a.ends_at)} · dismissed by ${a.dismissed_by}`)),
        h('span', { class: `chip ${a.live ? 'sage' : ''}` }, a.live ? 'Live' : !a.active ? 'Ended' : a.starts_at && new Date(a.starts_at) > new Date() ? 'Scheduled' : 'Over'),
        a.level === 'important' ? h('span', { class: 'chip orchid' }, 'Important') : null,
        a.active ? h('button', { class: 'btn soft sm', onclick: async () => { try { await api(`/admin/announcements/${a.id}`, { method: 'PATCH', body: { active: false } }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, 'End now') : null,
        h('button', { class: 'icon-btn', 'aria-label': `Delete ${a.title}`, onclick: async () => { if (!(await confirmBox({ title: `Delete "${a.title}"?`, body: 'It disappears for everyone.', confirm: 'Delete', danger: true }))) return; try { await api(`/admin/announcements/${a.id}`, { method: 'DELETE' }); draw(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('trash', 18))))
        : h('p', { class: 'muted' }, 'No announcements yet.')));
  };
  await draw();
}
