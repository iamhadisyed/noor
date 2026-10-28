import { api, fetchBlob } from '../api.js';
import { h, icon, fmt, toast, errorText, emptyState, spinner, modal } from '../ui.js';
import { loadChildren, currentChild, childPicker } from '../main.js';

const CAT = { math: 'Maths', listening: 'Listening', brain: 'Brain games', language: 'Letters and words', concepts: 'Colours and groups', social: 'Feelings', daily_living: 'Daily routines' };
const REASON = { good_score: 'Good score', effort: 'Tried hard', improvement: 'Improving', streak: 'Practice streak', parent_bonus: 'Bonus from you', play: 'Played a game' };

/** Practice earns play minutes; games are unlocked while there are minutes to spend. Parents set every rule. */
export async function render(ctx) {
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Rewards and games are set up per child.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  const page = h('div', { class: 'page' });
  const body = h('div', null, spinner());

  async function load() {
    const child = currentChild();
    const [r, ms] = await Promise.all([api(`/children/${child.id}/rewards`), api(`/children/${child.id}/milestones`)]);
    body.replaceChildren(summary(child, r), h('div', { class: 'grid2', style: 'align-items:start' }, h('div', { class: 'list', style: 'gap:24px' }, celebrate(child, ms.milestones), games(r), ledger(r)), h('div', { class: 'list', style: 'gap:24px' }, rules(child, r), bonus(child))));
  }

  const summary = (child, r) => h('div', { class: 'tiles' },
    tile('t1', 'Play time saved', r.balance_minutes, 'min', r.balance_minutes ? 'ready to spend' : 'practise to earn some'),
    tile('t2', 'Earned today', r.earned_today, `/ ${r.settings.daily_cap_minutes}`, 'minutes, daily limit'),
    tile('t3', 'Practice streak', r.streak_days, r.streak_days === 1 ? 'day' : 'days', `${r.settings.streak_days} days in a row earns a bonus`));

  /** Milestones the child has really reached, each with a picture to share (for example on WhatsApp). */
  const celebrate = (child, list) => h('section', { class: 'card lav', 'data-card': 'milestones' }, h('h2', { style: 'margin-bottom:6px' }, 'Celebrate'), h('p', { style: 'color:var(--ink-2);margin-bottom:12px' }, `Proud of ${child.name}? Share a card with family.`),
    list.length ? h('div', { class: 'list' }, list.slice(0, 6).map((m) => h('div', { class: 'item', 'data-milestone': m.key },
      h('div', { class: 'grow' }, h('b', null, m.title.en), h('span', null, m.earned_on ? fmt.date(m.earned_on) : '')),
      h('button', { class: 'btn soft sm', 'data-act': 'share-card', onclick: () => shareCard(child, m) }, icon('share', 18), 'Share')))) : h('p', { style: 'color:var(--ink-2)' }, 'Nothing to celebrate yet. A 3-day streak, an hour of practice or a mastered letter will show up here.'));

  function shareCard(child, m) {
    modal((box, close) => {
      let lang = 'en', showName = true, blob = null, url = null;
      const img = h('img', { class: 'card-preview', alt: `Milestone card: ${m.title.en}`, style: 'width:100%;max-width:360px;border-radius:20px;display:block;margin:12px auto' });
      const err = h('div', { class: 'msg-err', role: 'alert' });
      const load2 = async () => {
        err.textContent = '';
        try { blob = await fetchBlob(`/children/${child.id}/milestones/${m.key}/card.png?lang=${lang}&name=${showName ? 1 : 0}`); if (url) URL.revokeObjectURL(url); url = URL.createObjectURL(blob); img.src = url; } catch (e) { err.textContent = errorText(e); }
      };
      const langSel = h('select', { class: 'select', id: 'cl', 'aria-label': 'Card language', onchange: () => { lang = langSel.value; load2(); } }, h('option', { value: 'en' }, 'English'), h('option', { value: 'ur' }, 'اردو'));
      const nameSw = h('button', { class: 'switch', role: 'switch', 'aria-label': `Show ${child.name}'s first name`, 'aria-checked': 'true', onclick: () => { showName = !showName; nameSw.setAttribute('aria-checked', String(showName)); load2(); } });
      const file = () => new File([blob], 'kindo-milestone.png', { type: 'image/png' });
      const canShare = () => blob && navigator.canShare && navigator.canShare({ files: [file()] });
      box.append(h('h2', null, m.title.en), h('p', { class: 'muted' }, 'The card shows only what is on the picture: the first name if you leave it on, the milestone, and Kindo. No photo or details.'), img,
        h('div', { class: 'row', style: 'justify-content:space-between;margin:8px 0' }, h('label', { for: 'cl' }, 'Language'), langSel),
        h('div', { class: 'item' }, h('div', { class: 'grow' }, h('b', null, 'Show first name')), nameSw), err,
        h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: () => { if (url) URL.revokeObjectURL(url); close(); } }, 'Close'),
          h('button', { class: 'btn soft', 'data-act': 'download-card', onclick: () => { if (!blob) return; const a = Object.assign(document.createElement('a'), { href: url, download: 'kindo-milestone.png' }); document.body.append(a); a.click(); a.remove(); } }, 'Save picture'),
          h('button', { class: 'btn primary', 'data-act': 'send-card', onclick: async () => { try { if (canShare()) await navigator.share({ files: [file()], title: 'Kindo milestone' }); else toast('Sharing is not available here. Save the picture and send it from your phone.'); } catch (e) { if (e.name !== 'AbortError') toast('Could not share. Save the picture instead.', 'err'); } } }, 'Share')));
      load2();
    });
  }

  const games = (r) => h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:6px' }, 'Games'), h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'They get harder as your child does well, and easier if it is a struggle.'),
    h('div', { class: 'list' }, r.games.map((g) => h('div', { class: 'item', 'data-game': g.game },
      h('div', { class: 'grow' }, h('b', null, g.title.en), h('span', null, `${CAT[g.category]} · level ${g.level} · played ${g.plays}×${g.last_accuracy == null ? '' : ` · last ${g.last_accuracy}%`}`)),
      g.for && g.for.length === 1 && g.for[0] === 'special' ? h('span', { class: 'chip', title: 'Made with extra support in mind' }, 'Extra support') : null,
      !g.available ? h('span', { class: 'chip' }, 'Needs content') : !g.enabled ? h('span', { class: 'chip warn' }, 'Switched off') : g.unlocked ? h('span', { class: 'chip sage' }, 'Unlocked') : h('span', { class: 'chip ink' }, 'Locked'),
      h('button', { class: 'switch', role: 'switch', 'aria-label': `${g.title.en} on or off`, 'aria-checked': String(g.enabled), onclick: async (e) => {
        const enabledNow = r.games.filter((x) => x.enabled).map((x) => x.game);
        const next = g.enabled ? enabledNow.filter((k) => k !== g.game) : [...enabledNow, g.game];
        try { await api(`/children/${currentChild().id}/rewards/settings`, { method: 'PATCH', body: { enabled_games: next.length === r.games.length ? null : next } }); load(); } catch (er) { toast(errorText(er), 'err'); }
      } })))));

  const ledger = (r) => h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:12px' }, 'Recent minutes'),
    r.ledger.length ? h('div', { class: 'list' }, r.ledger.slice(0, 12).map((l) => h('div', { class: 'item' }, h('div', { class: 'grow' }, h('b', null, REASON[l.reason] || l.reason), h('span', null, `${fmt.dateTime(l.created_at)}${l.note ? ` · ${l.note}` : ''}`)), h('span', { class: `chip ${l.minutes > 0 ? 'sage' : ''}` }, `${l.minutes > 0 ? '+' : ''}${l.minutes} min`))))
      : h('p', { class: 'muted' }, 'Nothing yet. Minutes appear here when your child practises.'));

  const rules = (child, r) => {
    const s = r.settings;
    const num = (id, label, val, min, max, hint) => ({ id, el: h('div', { class: 'field' }, h('label', { for: id }, label), h('input', { class: 'input', id, type: 'number', min, max, value: val, inputmode: 'numeric' }), hint ? h('span', { class: 'hint' }, hint) : null) });
    const f = [num('r-thr', 'Good score is at least (%)', s.threshold_pct, 30, 100, 'Accuracy in a practice session'), num('r-min', 'Minutes for a good session', s.minutes_per_good_session, 1, 30),
      num('r-imp', 'Bonus minutes for improving', s.improvement_minutes, 0, 20, `Beating their own recent average by ${s.improvement_points} points`), num('r-cap', 'Most minutes earned per day', s.daily_cap_minutes, 5, 120),
      num('r-play', 'Longest single play (minutes)', s.max_session_minutes, 1, 60)];
    let effort = !!s.reward_for_effort, enabled = !!s.enabled;
    const sw = (label, get, set) => { const b = h('button', { class: 'switch', role: 'switch', 'aria-label': label, 'aria-checked': String(get()), onclick: () => { set(!get()); b.setAttribute('aria-checked', String(get())); } }); return b; };
    const err = h('div', { class: 'msg-err', role: 'alert' });
    return h('section', { class: 'card' }, h('h2', { style: 'margin-bottom:6px' }, 'Rules for ' + child.name), h('p', { class: 'muted', style: 'margin-bottom:16px' }, child.track === 'special' ? 'Gentler starting rules are used for a child with extra support.' : 'Change what counts as a good practice.'),
      h('div', { class: 'item' }, h('div', { class: 'grow' }, h('b', null, 'Rewards on'), h('span', null, 'Switch off to stop earning and playing')), sw('Rewards on', () => enabled, (v) => (enabled = v))),
      h('div', { class: 'item', style: 'margin:10px 0 16px' }, h('div', { class: 'grow' }, h('b', null, 'Reward for trying'), h('span', null, `${s.effort_minutes} minutes for finishing a session, even with a low score`)), sw('Reward for trying', () => effort, (v) => (effort = v))),
      f.map((x) => x.el), err,
      h('button', { class: 'btn primary', 'data-act': 'save-rules', onclick: async () => {
        err.textContent = '';
        const v = (id) => +document.getElementById(id).value;
        try { await api(`/children/${child.id}/rewards/settings`, { method: 'PATCH', body: { enabled, reward_for_effort: effort, threshold_pct: v('r-thr'), minutes_per_good_session: v('r-min'), improvement_minutes: v('r-imp'), daily_cap_minutes: v('r-cap'), max_session_minutes: v('r-play') } }); toast('Saved.'); load(); } catch (e) { err.textContent = errorText(e); }
      } }, 'Save rules'));
  };

  const bonus = (child) => {
    const mins = h('input', { class: 'input', id: 'b-min', type: 'number', min: 1, max: 60, value: 10, inputmode: 'numeric', 'aria-label': 'Bonus minutes' });
    const note = h('input', { class: 'input', id: 'b-note', maxlength: 120, placeholder: 'For example: kind to his sister', 'aria-label': 'Reason' });
    return h('section', { class: 'card lav' }, h('h2', { style: 'margin-bottom:6px' }, 'Give bonus time'), h('p', { style: 'color:var(--ink-2);margin-bottom:12px' }, 'For a good day, or anything else you want to reward.'),
      h('div', { class: 'grid2' }, h('div', { class: 'field' }, h('label', { for: 'b-min' }, 'Minutes'), mins), h('div', { class: 'field' }, h('label', { for: 'b-note' }, 'Reason (optional)'), note)),
      h('button', { class: 'btn primary', 'data-act': 'bonus', onclick: async () => { try { await api(`/children/${child.id}/rewards/bonus`, { method: 'POST', body: { minutes: +mins.value, note: note.value.trim() || null } }); toast(`${mins.value} minutes added for ${child.name}.`); load(); } catch (e) { toast(errorText(e), 'err'); } } }, icon('plus', 20), 'Add minutes'));
  };

  function draw() {
    const child = currentChild();
    page.replaceChildren(h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Rewards and games'), h('p', null, `Practice earns play time. ${child.name}'s games unlock while there is time to spend.`)), h('div', { class: 'row' }, childPicker(draw))), body);
    body.replaceChildren(spinner());
    load().catch((e) => body.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))));
  }
  draw();
  return page;
}
const tile = (cls, label, big, unit, sub) => h('div', { class: `tile ${cls}` }, h('span', { class: 'lbl' }, label), h('span', { class: 'big' }, big, unit ? h('small', null, unit) : null), h('span', { class: 'sub' }, sub));
