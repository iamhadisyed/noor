import { api, download } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner } from '../ui.js';
import { bars, hbars } from '../charts.js';
import { loadChildren, currentChild, childPicker } from '../main.js';

export async function render(ctx) {
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Reports appear once a child has used Kindo.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  let days = 7;
  const page = h('div', { class: 'page' });

  async function draw() {
    const child = currentChild();
    const body = h('div', null, spinner());
    page.replaceChildren(
      h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Reports'), h('p', null, `How ${child.name} is getting on.`)),
        h('div', { class: 'row' }, childPicker(draw),
          h('div', { class: 'seg', role: 'radiogroup', 'aria-label': 'Period' }, [[7, '7 days'], [30, '30 days'], [90, '90 days']].map(([d, l]) => h('button', { role: 'radio', 'aria-checked': String(days === d), onclick: () => { days = d; draw(); } }, l))),
          h('button', { class: 'btn soft', onclick: async () => { try { await download(`/children/${child.id}/insights/export.csv?days=${days}`, `kindo-${child.name}-report.csv`); } catch (e) { toast(errorText(e), 'err'); } } }, icon('download', 20), 'Export CSV'),
          h('button', { class: 'btn soft', 'data-act': 'export-pdf', onclick: async () => { try { await download(`/children/${child.id}/insights/report.pdf?days=${days}`, `kindo-${child.name}-progress-report.pdf`); } catch (e) { toast(errorText(e), 'err'); } } }, icon('download', 20), 'Print report'))),
      body);
    try {
      const [s, daily, letters] = await Promise.all([api(`/children/${child.id}/insights/summary?days=${days}`), api(`/children/${child.id}/insights/daily?days=${days}`), api(`/children/${child.id}/letters?days=${days}`)]);
      const series = days > 14 ? weekly(daily) : daily.map((d) => ({ label: new Date(d.date + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short' }), value: d.minutes }));
      body.replaceChildren(
        h('div', { class: 'tiles' },
          tile('t1', 'Practice time', s.practice.minutes, 'min', `${s.practice.sessions} sessions`), tile('t2', 'Accuracy', s.practice.accuracy_pct ?? '—', s.practice.accuracy_pct == null ? '' : '%', `${s.practice.attempts} tries`),
          tile('t3', 'Alerts', s.needs.total, '', `${s.needs.unanswered} unanswered`), tile('t4', 'Average reply', s.needs.avg_reply_seconds == null ? '—' : fmt.secs(s.needs.avg_reply_seconds), '', 'to alerts')),
        h('div', { class: 'cols' },
          h('div', { class: 'list', style: 'gap:24px' },
            h('section', { class: 'card' }, h('h2', null, 'Practice minutes'), h('p', { class: 'muted' }, days > 14 ? 'Per week' : 'Per day'), bars(series, { unit: ' min', name: 'Practice minutes' })),
            h('section', { class: 'card' }, h('h2', null, 'When alerts happen'), h('p', { class: 'muted' }, 'Count by hour of day'), bars(Object.entries(s.needs.by_hour).map(([hr, v]) => ({ label: hr, value: v })), { thin: true, name: 'Alerts by hour' })),
            aiSection(child)),
          h('div', { class: 'list', style: 'gap:24px' },
            h('section', { class: 'card' }, h('h2', null, 'By language'), Object.keys(s.practice.by_language).length ? h('div', null, Object.entries(s.practice.by_language).map(([k, v]) => h('div', { class: 'item', style: 'margin-top:10px' }, h('div', { class: 'grow' }, h('b', null, k === 'urdu' ? 'Urdu' : 'English'), h('span', null, `${v.minutes} min`)), h('span', { class: 'chip ink' }, fmt.pct(v.accuracy_pct))))) : h('p', { class: 'muted', style: 'margin-top:8px' }, 'No practice yet.')),
            h('section', { class: 'card' }, h('h2', null, 'Needs and requests'), hbars(s.needs.by_type, { empty: 'No alerts in this period.' })),
            s.calm && s.calm.sessions ? h('section', { class: 'card', 'data-card': 'calm' }, h('h2', null, 'Calm activities'), h('p', { class: 'muted' }, `${s.calm.sessions} times · ${s.calm.minutes} min`), hbars(s.calm.by_activity, { empty: '' })) : null,
            lettersCard(letters),
            h('section', { class: 'card' }, h('h2', null, 'Letters to practise'), h('p', { class: 'muted' }, 'Most missed targets'), hbars(s.practice.hardest_targets, { empty: 'No mistakes recorded. Nice.' }),
              s.practice.avg_stroke_deviation != null ? h('p', { class: 'hint', style: 'margin-top:10px' }, `Average tracing drift: ${s.practice.avg_stroke_deviation} px`) : null))));
    } catch (e) { body.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))); }
  }
  await draw();
  return page;
}
const STATUS = { mastered: ['sage', 'Got it'], practising: ['', 'Getting there'], needs_help: ['rose', 'Needs help'], new: ['', 'New'] };
const TREND = { up: '↑ improving', down: '↓ slipping', steady: '→ steady' };
/** Per-letter tracing results: a row per letter with how often it was right, how much guide it needed and the trend. */
export function lettersCard(l) {
  const rows = (l?.letters || []).slice(0, 12);
  return h('section', { class: 'card', id: 'letters-card' }, h('h2', null, 'Letter by letter'), h('p', { class: 'muted' }, l?.focus?.length ? `Focus on: ${l.focus.join('  ')}` : 'Tracing results per letter'),
    rows.length ? h('div', { class: 'list', style: 'margin-top:10px' }, rows.map((r) => h('div', { class: 'item', 'data-letter': r.letter },
      h('b', { style: 'font-size:22px;min-width:36px;text-align:center', lang: r.language === 'urdu' ? 'ur' : 'en' }, r.letter),
      h('div', { class: 'grow' }, h('b', null, `${r.accuracy_pct ?? '—'}% right`), h('span', null, `${r.attempts} tries · guide used ${r.assisted_pct}%${r.avg_deviation != null ? ` · drift ${r.avg_deviation}` : ''}${r.trend ? ` · ${TREND[r.trend]}` : ''}`)),
      h('span', { class: `chip ${STATUS[r.status][0]}` }, STATUS[r.status][1])))) : h('p', { class: 'muted', style: 'margin-top:8px' }, 'No tracing results yet.'),
    l?.mastered ? h('p', { class: 'hint', style: 'margin-top:10px' }, `${l.mastered} letter${l.mastered === 1 ? '' : 's'} mastered`) : null);
}
const tile = (cls, label, big, unit, sub) => h('div', { class: `tile ${cls}` }, h('span', { class: 'lbl' }, label), h('span', { class: 'big' }, big, unit ? h('small', null, unit) : null), h('span', { class: 'sub' }, sub));
function weekly(daily) {
  const out = []; for (let i = 0; i < daily.length; i += 7) { const chunk = daily.slice(i, i + 7); out.push({ label: new Date(chunk[0].date + 'T12:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' }), value: chunk.reduce((a, d) => a + d.minutes, 0) }); } return out;
}

/** The weekly AI report (Pro). Generation is queued, so we poll for the result. */
function aiSection(child) {
  const box = h('section', { class: 'card lav' }, h('h2', null, 'Weekly insight'), h('p', { style: 'color:var(--ink-2)' }, 'A plain-language summary of what is going well and what to practise next.'));
  const out = h('div', { style: 'margin-top:16px' });
  const show = (r) => out.replaceChildren(
    h('p', { class: 'hint', style: 'color:var(--ink-2)' }, `Week of ${fmt.date(r.report_week_start)}`),
    r.sensory_behavior_summary ? h('p', { style: 'margin:8px 0' }, r.sensory_behavior_summary) : null,
    r.potty_timing_analysis?.summary ? h('p', { style: 'margin:8px 0' }, r.potty_timing_analysis.summary) : null,
    (r.phonetic_blindspots || []).length ? h('div', null, h('h3', { style: 'margin:12px 0 6px' }, 'Sounds to practise'), h('ul', { style: 'padding-left:20px' }, r.phonetic_blindspots.map((b) => h('li', null, `${b.issue || ''} ${b.root_cause ? '– ' + b.root_cause : ''}`)))) : null,
    (r.actionable_parent_exercises || []).length ? h('div', null, h('h3', { style: 'margin:12px 0 6px' }, 'Try this at home'), h('ul', { style: 'padding-left:20px' }, r.actionable_parent_exercises.map((x) => h('li', null, h('b', null, x.title), x.instructions ? ` – ${x.instructions}` : '')))) : null);
  const btn = h('button', { class: 'btn primary', onclick: async () => {
    btn.disabled = true; btn.textContent = 'Preparing…';
    try {
      await api(`/children/${child.id}/reports`, { method: 'POST' });
      for (let i = 0; i < 20; i++) { await new Promise((r) => setTimeout(r, 3000)); try { show(await api(`/children/${child.id}/reports/latest`)); btn.remove(); return; } catch (e) { if (e.status !== 404) throw e; } }
      toast('The report is still being prepared. Check back in a few minutes.');
    } catch (e) { toast(e.status === 402 ? 'Weekly insight is part of Kindo Pro.' : errorText(e), e.status === 402 ? 'ok' : 'err'); }
    btn.disabled = false; btn.textContent = 'Create this week’s insight';
  } }, 'Create this week’s insight');
  api(`/children/${child.id}/reports/latest`).then(show).catch(() => {});
  box.append(out, h('div', { style: 'margin-top:16px' }, btn));
  return box;
}
