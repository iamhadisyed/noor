import { h } from './ui.js';

/** Hatched, outlined bars in the Kindo style. rows: [{label, value}] */
export function bars(rows, { unit = '', thin = false, name = 'Chart' } = {}) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  const top = Math.max(...rows.map((r) => r.value));
  return h('div', { class: `bars${thin ? ' thin' : ''}`, role: 'img', 'aria-label': `${name}: ${rows.map((r) => `${r.label} ${r.value}${unit}`).join(', ')}` },
    rows.map((r) => h('div', { class: 'col' },
      h('div', { class: `bar${r.value === top && r.value > 0 ? ' hot' : ''}`, style: `height:${Math.max(4, (r.value / max) * 100)}%` }, !thin && r.value === top && r.value > 0 ? h('span', { class: 'v' }, `${r.value}${unit}`) : null),
      h('span', { class: 'day', style: thin && rows.length > 12 && Number(r.label) % 3 !== 0 ? 'visibility:hidden' : null, 'aria-hidden': thin && rows.length > 12 && Number(r.label) % 3 !== 0 ? 'true' : null }, r.label))));
}

/** Horizontal bars for a {label: count} map. */
export function hbars(map, { fmt = (n) => n, empty = 'Nothing yet.' } = {}) {
  const entries = Object.entries(map || {});
  if (!entries.length) return h('p', { class: 'muted' }, empty);
  const max = Math.max(1, ...entries.map(([, v]) => v));
  return h('div', null, entries.map(([k, v]) => h('div', { class: 'hbar' }, h('span', null, k.replaceAll('_', ' ')), h('div', { class: 'track' }, h('div', { class: 'fill', style: `width:${(v / max) * 100}%` })), h('b', null, fmt(v)))));
}
