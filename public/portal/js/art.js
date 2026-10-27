// Illustrations reused from the design prototype (original artwork). Static markup only.
export const ART = {
  water: `<defs><linearGradient id="wg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7fc8ff"/><stop offset="1" stop-color="#3b8fe0"/></linearGradient></defs>
  <ellipse cx="48" cy="88" rx="26" ry="4" fill="#5a4a8a" opacity=".14"/>
  <path d="M24 18h48l-6 64q-1 5-6 5H36q-5 0-6-5z" fill="#eaf5ff"/>
  <path d="M27 40h42l-3.400 41q-1 4-5.600 4H36q-4.600 0-5.600-4z" fill="url(#wg)"/>
  <path d="M27 40q10-6 21 0t21 0" fill="none" stroke="#bfe3ff" stroke-width="3" stroke-linecap="round"/>
  <path d="M31 24l3 56" stroke="#fff" stroke-width="4" stroke-linecap="round" opacity=".7"/>
  <circle cx="52" cy="62" r="3.500" fill="#fff" opacity=".6"/><circle cx="60" cy="54" r="2.500" fill="#fff" opacity=".6"/><circle cx="46" cy="72" r="2.200" fill="#fff" opacity=".6"/>
  <path d="M74 14c4 6 7 9 7 13a7 7 0 0 1-14 0c0-4 3-7 7-13z" fill="#5fb3f5"/><path d="M71 26a4 4 0 0 0 3 4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/>`,
  potty: `<ellipse cx="48" cy="90" rx="30" ry="4" fill="#5a4a8a" opacity=".14"/>
  <rect x="26" y="12" width="44" height="26" rx="8" fill="#f4effc"/><rect x="26" y="12" width="44" height="8" rx="4" fill="#e3d9f6"/>
  <circle cx="48" cy="16" r="3.500" fill="#e697f2"/>
  <path d="M20 42h56q2 24-14 33v11H34V75Q18 66 20 42z" fill="#fff"/>
  <path d="M62 52q0 14-10 20v14h10V75q14-9 12-33z" fill="#e8e0f6" opacity=".7"/>
  <ellipse cx="48" cy="42" rx="30" ry="10" fill="#d9c8f5"/><ellipse cx="48" cy="42" rx="21" ry="6.500" fill="#b79fe4"/>
  <path d="M30 40q18 7 36 0" stroke="#fff" stroke-width="2" fill="none" opacity=".6"/>`,
  food: `<ellipse cx="46" cy="88" rx="32" ry="4" fill="#5a4a8a" opacity=".14"/>
  <g stroke="#e6c4a0" stroke-width="2.500" fill="none" stroke-linecap="round" opacity=".9"><path d="M36 22c-4-5 4-8 0-13M48 20c-4-5 4-8 0-13M60 22c-4-5 4-8 0-13"/></g>
  <path d="M14 46q2-22 34-24 32 2 34 24z" fill="#fff"/>
  <g fill="#ececf4"><ellipse cx="34" cy="36" rx="5" ry="2.500" transform="rotate(-20 34 36)"/><ellipse cx="50" cy="30" rx="5" ry="2.500"/><ellipse cx="62" cy="38" rx="5" ry="2.500" transform="rotate(25 62 38)"/><ellipse cx="44" cy="42" rx="5" ry="2.500"/></g>
  <path d="M10 46h76q0 28-38 34Q10 74 10 46z" fill="#f08a3c"/><path d="M10 46h76" stroke="#f6a35a" stroke-width="7" stroke-linecap="round"/>
  <path d="M18 54q4 12 18 18" stroke="#fff" stroke-width="3.500" fill="none" stroke-linecap="round" opacity=".45"/>
  <g transform="rotate(28 78 40)"><rect x="75" y="24" width="6" height="42" rx="3" fill="#f7c63f"/><ellipse cx="78" cy="22" rx="8" ry="10" fill="#fbd65a"/></g>`,
  sleep: `<ellipse cx="48" cy="90" rx="34" ry="4" fill="#5a4a8a" opacity=".14"/>
  <path d="M52 8a30 30 0 1 0 28 44A24 24 0 0 1 52 8z" fill="#ffd95a"/><path d="M40 16a24 24 0 0 0-8 30" stroke="#fff3b8" stroke-width="4" fill="none" stroke-linecap="round" opacity=".8"/>
  <g fill="#ffd95a"><path d="M76 14l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/><path d="M20 52l1.500 3.500 3.500 1.500-3.500 1.500L20 62l-1.500-3.500L15 57l3.500-1.500z"/></g>
  <rect x="12" y="58" width="72" height="28" rx="14" fill="#fff"/><path d="M12 72a14 14 0 0 0 14 14h44a14 14 0 0 0 14-14c-20 6-52 6-72 0z" fill="#e3d9f6"/>
  <text x="66" y="52" font-family="Lexend, Onest, sans-serif" font-weight="700" font-size="14" fill="#8f6fd4">z</text><text x="74" y="42" font-family="Lexend, Onest, sans-serif" font-weight="700" font-size="10" fill="#8f6fd4">z</text>`,
  hug: `<defs><linearGradient id="hg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff9cc0"/><stop offset="1" stop-color="#f2578a"/></linearGradient></defs>
  <ellipse cx="48" cy="90" rx="28" ry="4" fill="#5a4a8a" opacity=".14"/>
  <path d="M48 84C8 56 12 18 34 18c8 0 12 6 14 10 2-4 6-10 14-10 22 0 26 38-14 66z" fill="url(#hg)"/>
  <path d="M26 30c-6 6-6 14-2 22" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" opacity=".5"/>
  <path d="M34 50c2-3 5-3 7 0M55 50c2-3 5-3 7 0" stroke="#7a2a48" stroke-width="3" fill="none" stroke-linecap="round"/>
  <path d="M40 58q8 8 16 0" stroke="#7a2a48" stroke-width="3" fill="none" stroke-linecap="round"/>
  <ellipse cx="30" cy="58" rx="5" ry="3.500" fill="#ffd0dd" opacity=".8"/><ellipse cx="66" cy="58" rx="5" ry="3.500" fill="#ffd0dd" opacity=".8"/>
  <path d="M10 74c10-6 22-2 28 6" stroke="#f4c6a0" stroke-width="9" fill="none" stroke-linecap="round"/><path d="M86 74c-10-6-22-2-28 6" stroke="#f4c6a0" stroke-width="9" fill="none" stroke-linecap="round"/>`,
  pain: `<ellipse cx="48" cy="86" rx="30" ry="4" fill="#5a4a8a" opacity=".14"/>
  <g transform="rotate(-32 48 48)"><rect x="8" y="34" width="80" height="28" rx="14" fill="#f2bf95"/><rect x="34" y="34" width="28" height="28" fill="#fbe3c8"/>
  <g fill="#e0a678"><circle cx="18" cy="42" r="1.800"/><circle cx="18" cy="54" r="1.800"/><circle cx="26" cy="48" r="1.800"/><circle cx="78" cy="42" r="1.800"/><circle cx="78" cy="54" r="1.800"/><circle cx="70" cy="48" r="1.800"/></g>
  <rect x="8" y="34" width="80" height="6" rx="3" fill="#fff" opacity=".35"/></g>
  <g fill="#ff8a8a"><path d="M76 12l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/></g>`,
};

export function art(name, size = 72) {
  const wrap = document.createElement('span');
  wrap.innerHTML = `<svg width="${size}" height="${size}" viewBox="0 0 96 96" aria-hidden="true">${ART[name] || ART.hug}</svg>`;   // constant markup, never user data
  return wrap.firstChild;
}
