// Small UI toolkit: a DOM builder that never injects HTML from data, icons, toasts and modals.
export function h(tag, props, ...kids) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(props || {})) {
    if (v == null || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
    else if (k === 'dataset') Object.assign(el.dataset, v);
    else if (k === 'value') el.value = v;
    else if (k === 'checked') el.checked = !!v;
    else if (k === 'disabled' || k === 'hidden' || k === 'required') { if (v) el.setAttribute(k, ''); }
    else el.setAttribute(k, v === true ? '' : v);
  }
  add(el, kids);
  return el;
}
function add(el, kids) {
  for (const k of kids.flat(Infinity)) {
    if (k == null || k === false) continue;
    el.append(k instanceof Node ? k : document.createTextNode(String(k)));
  }
}
export const $ = (s, r = document) => r.querySelector(s);
export const clear = (el) => { while (el.firstChild) el.removeChild(el.firstChild); return el; };

const P = {
  home: '<path d="M4.5 10.4 12 4.2l7.5 6.2V18a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2z"/><path d="M9.400 14.300c1.400 1.300 4 1.300 5.200 0"/>',
  kids: '<circle cx="9" cy="8" r="3.200"/><path d="M3.500 19c.6-3.200 2.700-5 5.500-5s4.900 1.800 5.500 5"/><circle cx="17" cy="9" r="2.400"/><path d="M16 14.200c2.500-.2 4.200 1.200 4.700 3.800"/>',
  book: '<path d="M7.500 3.800h9A1.500 1.500 0 0 1 18 5.300v13.400a1.500 1.500 0 0 1-1.500 1.500h-9A1.500 1.500 0 0 1 6 18.700V5.300a1.500 1.500 0 0 1 1.500-1.500z"/><path d="M6 17.200h12M9.200 7.800h5.600M9.200 10.800h3.400"/>',
  chart: '<path d="M5 19V9M10 19V5M15 19v-7M20 19v-4"/>',
  bell: '<path d="M6.400 17.400h11.200l-1.600-2.100v-4.200a4.400 4.400 0 0 0-8.800 0v4.200z"/><path d="M10.200 19.400a2 2 0 0 0 3.600 0"/>',
  gear: '<circle cx="12" cy="12" r="3.300"/><path d="M10.200 3.600h3.600l.5 2.300 1.600.9 2.200-.8 1.800 3.100-1.700 1.600v1.800l1.700 1.600-1.800 3.100-2.200-.8-1.600.9-.5 2.300h-3.600l-.5-2.300-1.600-.9-2.200.8-1.800-3.100 1.700-1.600v-1.800L4.100 9.200l1.800-3.100 2.200.8 1.600-.9z"/>',
  plus: '<path d="M12 5v14M5 12h14"/>', check: '<path d="m5.500 12.500 4.200 4.200 8.800-9.400"/>', x: '<path d="M6 6l12 12M18 6 6 18"/>',
  arrowUp: '<path d="M7.500 16.500 16.500 7.500M9 7.500h7.500V15"/>', left: '<path d="m14.500 6-6 6 6 6"/>', up: '<path d="m6 14.500 6-6 6 6"/>', down: '<path d="m6 9.500 6 6 6-6"/>',
  mic: '<rect x="9" y="3.500" width="6" height="11" rx="3"/><path d="M5.800 11.500a6.200 6.200 0 0 0 12.400 0M12 17.800V21M9 21h6"/>',
  image: '<rect x="3.500" y="5" width="17" height="14" rx="3"/><circle cx="9" cy="10" r="1.800"/><path d="m4 17 5-4.500 3.500 3L16 12l4 4"/>',
  speaker: '<path d="M5 9.800h3l4.500-3.600v11.600L8 14.200H5z"/><path d="M15.500 9.200a4 4 0 0 1 0 5.600M17.800 6.800a7.400 7.400 0 0 1 0 10.400"/>',
  trash: '<path d="M5 7h14M10 7V4.500h4V7M7 7l.8 12.500h8.400L17 7M10.500 11v5.500M13.500 11v5.500"/>',
  copy: '<rect x="8" y="8" width="11" height="11" rx="2.500"/><path d="M5 15V6.500A1.500 1.500 0 0 1 6.500 5H15"/>',
  download: '<path d="M12 4v11M7.500 11 12 15.500 16.500 11M5 19.500h14"/>',
  logout: '<path d="M10 4.500H6.500A1.500 1.500 0 0 0 5 6v12a1.500 1.500 0 0 0 1.500 1.500H10M14 8l4 4-4 4M18 12H9.500"/>',
  shield: '<path d="M12 3.500 5 6v5.500c0 4.200 2.700 7.200 7 9 4.300-1.800 7-4.800 7-9V6z"/><path d="m9 12 2.200 2.200L15.200 10"/>',
  users: '<circle cx="9" cy="8" r="3.200"/><path d="M3.500 19c.6-3.200 2.700-5 5.500-5s4.900 1.800 5.500 5M16 5.500a3 3 0 0 1 0 5.800M17.500 14.400c1.700.7 2.800 2.200 3.200 4.600"/>',
  list: '<path d="M8 6.500h11M8 12h11M8 17.500h11M4.500 6.500h.01M4.500 12h.01M4.500 17.500h.01"/>',
  pulse: '<path d="M3 12h4l2.500-6 4 12 2.500-6H21"/>', spark: '<path d="m12 3 2 5.500L19.500 10.500 14 12.500 12 18l-2-5.500L4.500 10.500 10 8.500z"/>',
  heart: '<path d="M12 20s-7-4.400-7-10a4 4 0 0 1 7-2.500A4 4 0 0 1 19 10c0 5.600-7 10-7 10z"/>',
  edit: '<path d="M4.500 19.500 5.200 15 15.500 4.700a1.800 1.800 0 0 1 2.500 0l1.300 1.300a1.800 1.800 0 0 1 0 2.500L9 18.800z"/>', play: '<path d="M8.500 5.800v12.400L18.500 12z"/>', stop: '<rect x="6.500" y="6.500" width="11" height="11" rx="2"/>',
  flag: '<path d="M6 20.500V4.500M6 5h11l-2 4 2 4H6"/>', eye: '<path d="M2.800 12S6 6 12 6s9.200 6 9.200 6-3.200 6-9.200 6S2.800 12 2.800 12z"/><circle cx="12" cy="12" r="2.800"/>',
  star: '<path d="m12 3.800 2.600 5.300 5.800.800-4.200 4.100 1 5.800L12 17l-5.200 2.800 1-5.800L3.600 9.900l5.800-.800z"/>',
  share: '<circle cx="6.500" cy="12" r="2.500"/><circle cx="17.500" cy="6.500" r="2.500"/><circle cx="17.500" cy="17.500" r="2.500"/><path d="m8.700 10.800 6.600-3.100M8.700 13.200l6.600 3.100"/>',
  camera: '<path d="M4 8.500h3l1.500-2.500h7L17 8.500h3v10H4z"/><circle cx="12" cy="13.200" r="3.200"/>',
  alert: '<path d="M12 4 3 19.500h18z"/><path d="M12 10v4.500M12 17.200v.1"/>', lock: '<rect x="5.500" y="10.500" width="13" height="9.500" rx="2.800"/><path d="M8.500 10.500V8a3.500 3.500 0 0 1 7 0v2.500"/>',
};
export function icon(name, size = 22, extra = {}) {
  const wrap = document.createElement('span');
  wrap.innerHTML = `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[name] || ''}</svg>`;   // constant markup
  const svg = wrap.firstChild;
  for (const [k, v] of Object.entries(extra)) svg.setAttribute(k, v);
  return svg;
}

const toastRoot = () => document.getElementById('toasts') || document.body.appendChild(h('div', { id: 'toasts', class: 'toasts', 'aria-live': 'polite', role: 'status' }));
export function toast(message, kind = 'ok') {
  const t = h('div', { class: `toast ${kind}` }, kind === 'ok' ? icon('check', 18) : icon('alert', 18), message);
  toastRoot().append(t); setTimeout(() => t.remove(), kind === 'err' ? 6000 : 3200);
}

/** A modal that traps focus, closes on Escape and returns focus to what opened it. */
export function modal(build) {
  const opener = document.activeElement;
  const close = () => { document.removeEventListener('keydown', onKey); overlay.remove(); opener?.focus?.(); };
  const box = h('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true' });
  const overlay = h('div', { class: 'overlay', onmousedown: (e) => { if (e.target === overlay) close(); } }, box);
  const onKey = (e) => {
    if (e.key === 'Escape') close();
    if (e.key === 'Tab') {
      const f = [...box.querySelectorAll('button,input,select,textarea,a[href],[tabindex]:not([tabindex="-1"])')].filter((x) => !x.disabled && x.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  };
  document.addEventListener('keydown', onKey);
  document.body.append(overlay);
  build(box, close);
  (box.querySelector('[autofocus]') || box.querySelector('input,select,textarea,button'))?.focus();
  return close;
}
export function confirmBox({ title, body, confirm = 'Confirm', danger = false, typeToConfirm }) {
  return new Promise((resolve) => modal((box, close) => {
    const input = typeToConfirm ? h('input', { class: 'input', 'aria-label': `Type ${typeToConfirm} to confirm`, autocomplete: 'off', oninput: () => { ok.disabled = input.value.trim() !== typeToConfirm; } }) : null;
    const ok = h('button', { class: `btn ${danger ? 'danger' : 'primary'}`, disabled: !!typeToConfirm, onclick: () => { close(); resolve(true); } }, confirm);
    box.append(h('h2', null, title), h('p', { class: 'muted' }, body),
      typeToConfirm ? h('div', { class: 'field', style: 'margin-top:16px' }, h('label', null, `Type "${typeToConfirm}" to confirm`), input) : null,
      h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: () => { close(); resolve(false); } }, 'Cancel'), ok));
  }));
}

export const fmt = {
  date: (d) => d ? new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—',
  time: (d) => d ? new Date(d).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '—',
  dateTime: (d) => d ? `${fmt.date(d)}, ${fmt.time(d)}` : '—',
  ago(d) { if (!d) return '—'; const s = Math.max(0, (Date.now() - new Date(d)) / 1000); if (s < 60) return 'just now'; if (s < 3600) return `${Math.floor(s / 60)} min ago`; if (s < 86400) return `${Math.floor(s / 3600)} h ago`; return fmt.date(d); },
  secs(s) { if (s == null) return '—'; if (s < 60) return `${s}s`; const m = Math.round(s / 60); return m < 60 ? `${m} min` : `${(m / 60).toFixed(1)} h`; },
  pct: (v) => (v == null ? '—' : `${v}%`),
  num: (n) => (n == null ? '—' : Number(n).toLocaleString()),
};
export const initial = (s) => (s || '?').trim().charAt(0).toUpperCase();
export function debounce(fn, ms = 600) { let t; const d = (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; d.flush = (...a) => { clearTimeout(t); return fn(...a); }; return d; }
export function errorText(e) {
  if (e?.fieldErrors && Object.keys(e.fieldErrors).length) return Object.values(e.fieldErrors).flat()[0];
  return e?.message || 'Something went wrong. Please try again.';
}
export function spinner() { return h('div', { class: 'spinner', role: 'status', 'aria-label': 'Loading' }); }
export function emptyState(title, text, action) {
  return h('div', { class: 'empty card' }, h('div', { class: 'ill' }, icon('spark', 40)), h('h3', null, title), h('p', { class: 'muted' }, text), action);
}
