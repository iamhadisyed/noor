(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const NS = 'http://www.w3.org/2000/svg';

  /* ---- fit; ?screen=trace|dots|assist shows one phone ---- */
  const stage = $('#stage');
  const solo = new URLSearchParams(location.search).get('screen');
  if (solo) { document.body.dataset.solo = ''; $(`.phone[data-screen="${solo}"]`)?.classList.add('solo'); }
  const fit = () => { const k = Math.min(innerWidth / stage.offsetWidth, innerHeight / stage.offsetHeight, solo ? 1.6 : 1); stage.style.transform = `translate(-50%, -50%) scale(${k})`; };
  addEventListener('resize', fit); fit();

  /* ---- guide path, sampled once ---- */
  const gp = $('#gp'), L = gp.getTotalLength(), N = 240;
  const pts = Array.from({ length: N + 1 }, (_, i) => { const p = gp.getPointAtLength((L * i) / N); return { x: p.x, y: p.y }; });
  const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
  const toSvg = (svg, e) => { const p = svg.createSVGPoint(); p.x = e.clientX; p.y = e.clientY; return p.matrixTransform(svg.getScreenCTM().inverse()); };

  /* direction chevrons along the guide */
  [0.2, 0.5, 0.8].forEach((t) => {
    const i = Math.round(N * t), a = pts[i - 2], b = pts[i + 2], ang = (Math.atan2(b.y - a.y, b.x - a.x) * 180) / Math.PI;
    const g = document.createElementNS(NS, 'path'); g.setAttribute('d', 'M-6 -9L5 0L-6 9'); g.setAttribute('transform', `translate(${pts[i].x} ${pts[i].y}) rotate(${ang})`); $('#arrows').append(g);
  });

  /* ---- shared assist settings ---- */
  let T = 28;
  const setT = (v) => { T = v; document.documentElement.style.setProperty('--T', v); $('#tOut').innerHTML = `${v}<small>px</small>`; $('#tRange').value = v;
    $$('.presets button').forEach((b) => b.setAttribute('aria-checked', String(+b.dataset.v === v))); drawPreview(); };

  /* ================= 1 · tracing ================= */
  const svg = $('#tsvg'), canvas = $('#canvas'), ink = $('#ink'), ink2 = $('#ink2'), raw = $('#raw'), start = $('#start'), finger = $('#finger'), hand = $('#hand');
  const soft = $('#soft'), next = $('#next'), pct = $('#pct'), pDone = $('#pDone'), pKnob = $('#pKnob');
  let prog = 0, tracing = false, trail = [], devs = [], softTimer, finished = false;
  const showSoft = (t, warm) => { soft.textContent = t; soft.classList.toggle('warm', !!warm); soft.classList.add('show'); clearTimeout(softTimer); softTimer = setTimeout(() => soft.classList.remove('show'), 1800); };
  const draw = () => {
    const d = (L * prog) / N;
    [ink, ink2].forEach((p) => { p.style.strokeDasharray = `${d} ${L + 50}`; });
    const v = Math.round((prog / N) * 100); pct.textContent = `${v}%`;
    const w = Math.max(15, (v / 100) * 383 - 0); pDone.style.width = `${(v / 100) * 190}px`; pKnob.style.left = `${Math.max(15, (v / 100) * 190)}px`;
  };
  const reset = () => { prog = 0; tracing = false; trail = []; devs = []; finished = false; canvas.classList.remove('done'); raw.setAttribute('points', ''); start.classList.remove('hide'); hand.classList.remove('hide'); finger.setAttribute('visibility', 'hidden'); next.disabled = true; draw(); };
  const finish = () => {
    finished = true; tracing = false; prog = N; draw();
    const mean = devs.length ? devs.reduce((a, b) => a + b, 0) / devs.length : 0;   // stroke_deviation_score, in px
    const steady = Math.max(60, Math.round(100 - (mean / Math.max(T, 1)) * 28));
    $('#steady').textContent = `Steadiness ${steady}%`; canvas.classList.add('done'); next.disabled = false; finger.setAttribute('visibility', 'hidden');
    if (navigator.vibrate && $('#tHaptic').getAttribute('aria-checked') === 'true') navigator.vibrate(18);
  };
  const near = (i0, i1, p) => { let best = -1, bd = 1e9; for (let i = Math.max(0, i0); i <= Math.min(N, i1); i++) { const d = dist(pts[i], p); if (d < bd) { bd = d; best = i; } } return { i: best, d: bd }; };

  svg.addEventListener('pointerdown', (e) => {
    if (finished) return;
    const p = toSvg(svg, e), origin = pts[prog];
    if (dist(p, origin) <= 46 + T) {
      svg.setPointerCapture(e.pointerId); tracing = true; trail = [p]; start.classList.add('hide'); hand.classList.add('hide');
      finger.setAttribute('transform', `translate(${p.x} ${p.y})`); finger.setAttribute('visibility', 'visible');
    } else { showSoft(prog ? 'Start where the pink line ends' : 'Start at the glowing 1', true); }
  });
  svg.addEventListener('pointermove', (e) => {
    if (!tracing) return;
    const p = toSvg(svg, e); trail.push(p); if (trail.length > 70) trail.shift();
    raw.setAttribute('points', trail.map((q) => `${q.x.toFixed(1)},${q.y.toFixed(1)}`).join(' '));
    finger.setAttribute('transform', `translate(${p.x} ${p.y})`);
    const hit = near(prog - 6, prog + 36, p);            // forward-only window: no shortcuts across the bowl
    if (hit.d <= T) { if (hit.i > prog) { prog = hit.i; devs.push(hit.d); draw(); if (navigator.vibrate && $('#tHaptic').getAttribute('aria-checked') === 'true' && prog % 40 === 0) navigator.vibrate(6); } if (prog >= N - 4) finish(); }
    else showSoft('Stay near the glow', false);           // gentle, never an error
  });
  const up = () => { if (tracing) { tracing = false; finger.setAttribute('visibility', 'hidden'); raw.setAttribute('points', ''); } };
  svg.addEventListener('pointerup', up); svg.addEventListener('pointercancel', up);
  $('#clear').addEventListener('click', reset);
  $('#hintBtn').addEventListener('click', () => { if (finished) return; hand.classList.remove('hide'); start.classList.remove('hide'); showSoft('Follow the little hand', true); });
  $('#next').addEventListener('click', () => { showSoft('Dot lab is on the next screen →', true); });
  draw();

  /* ================= 2 · dot lab ================= */
  const dsvg = $('#dsvg'), dcanvas = $('#dcanvas'), placed = $('#placed'), dsoft = $('#dsoft'), tray = $('#tray');
  const ZONES = { above: { x: 190, y: 226 }, below: { x: 190, y: 340 } };
  const LETTERS = { 'below1': ['ب', 'Be', 'One dot below makes Be'], 'below3': ['پ', 'Pe', 'Three dots below make Pe'], 'above2': ['ت', 'Te', 'Two dots above make Te'], 'above3': ['ث', 'Se', 'Three dots above make Se'] };
  let dtimer; const dsay = (t) => { dsoft.textContent = t; dsoft.classList.add('show', 'warm'); clearTimeout(dtimer); dtimer = setTimeout(() => dsoft.classList.remove('show'), 2200); };
  const found = new Set(['ب']);
  const markFound = () => $$('.fam').forEach((f) => f.classList.toggle('found', found.has(f.dataset.l)));
  const showDots = (zone, n) => {
    placed.innerHTML = ''; const c = ZONES[zone]; const gap = 26;
    for (let k = 0; k < n; k++) {
      const x = c.x + (k - (n - 1) / 2) * gap, y = c.y + (n === 3 ? (k === 1 ? (zone === 'above' ? -10 : 10) : 0) : 0);
      const dot = document.createElementNS(NS, 'circle'); dot.setAttribute('cx', x); dot.setAttribute('cy', y); dot.setAttribute('r', 11); dot.setAttribute('class', 'placed-dot');
      dot.style.transformBox = 'fill-box'; dot.style.transformOrigin = 'center'; dot.animate([{ transform: 'scale(1.8)' }, { transform: 'scale(.85)' }, { transform: 'scale(1)' }], { duration: 420, easing: 'ease-out' }); placed.append(dot);
    }
  };
  const result = $('#result');
  const reveal = (zone, n) => {
    const hit = LETTERS[zone + n];
    if (hit) { $('#rGlyph').textContent = hit[0]; $('#rName').textContent = `That makes ${hit[1]}!`; $('#rSub').textContent = hit[2]; result.classList.remove('got'); void result.offsetWidth; result.classList.add('got'); found.add(hit[0]); markFound(); }
    else { $('#rGlyph').textContent = '؟'; $('#rName').textContent = 'Not a letter yet'; $('#rSub').textContent = 'Try a different number of dots'; result.classList.remove('got'); dsay("Let's try one more time!"); }
  };
  $$('.tok').forEach((tok) => tok.addEventListener('pointerdown', (e) => {
    e.preventDefault(); const n = +tok.dataset.n, rect = () => dcanvas.getBoundingClientRect(), sc = () => rect().width / 383;
    const ghost = document.createElement('div'); ghost.className = 'ghost'; ghost.innerHTML = '<i></i>'.repeat(n); dcanvas.append(ghost);
    const pos = (ev) => { const r = rect(), k = sc(); return { x: (ev.clientX - r.left) / k, y: (ev.clientY - r.top) / k }; };
    const move = (ev) => { const q = pos(ev); ghost.style.left = `${q.x}px`; ghost.style.top = `${q.y}px`;
      let hot = null; for (const [k, z] of Object.entries(ZONES)) if (Math.hypot(q.x - z.x, q.y - z.y) < 52) hot = k;
      $('#zAbove').classList.toggle('hot', hot === 'above'); $('#zBelow').classList.toggle('hot', hot === 'below'); };
    move(e);
    const end = (ev) => {
      removeEventListener('pointermove', move); removeEventListener('pointerup', end); removeEventListener('pointercancel', end);
      const q = pos(ev); let zone = null; for (const [k, z] of Object.entries(ZONES)) if (Math.hypot(q.x - z.x, q.y - z.y) < 52) zone = k;   // magnetic drop radius
      ghost.remove(); $('#zAbove').classList.remove('hot'); $('#zBelow').classList.remove('hot');
      if (zone) { showDots(zone, n); reveal(zone, n); } else { dsay('Drop it near a ring'); }
    };
    addEventListener('pointermove', move); addEventListener('pointerup', end); addEventListener('pointercancel', end);
  }));
  $$('.tok').forEach((t) => t.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); const n = +t.dataset.n; showDots('below', n); reveal('below', n); } }));
  showDots('below', 1); reveal('below', 1); $('#rName').textContent = 'That makes Be!'; markFound();

  /* ================= 3 · assist ================= */
  const rnd = (i) => Math.sin(i * 12.9898) * 43758.5453 % 1;
  function drawPreview() {
    const rawPts = [], snapPts = []; let snapped = 0, count = 0;
    for (let i = 0; i <= N; i += 3) {
      const a = 26, nx = a * (Math.sin(i * 0.31 + 0.8) + 0.55 * Math.sin(i * 0.93 + 2.1)) / 1.55, ny = a * (Math.cos(i * 0.27 + 1.3) + 0.55 * Math.sin(i * 0.81 + 0.4)) / 1.55;
      const p = { x: pts[i].x + nx, y: pts[i].y + ny }; rawPts.push(p);
      const h = near(0, N, p); count++;
      if (h.d <= T) { snapped++; snapPts.push(pts[h.i]); } else snapPts.push(p);
    }
    const f = (a) => a.map((q) => `${q.x.toFixed(1)},${q.y.toFixed(1)}`).join(' ');
    $('#pvRaw').setAttribute('points', f(rawPts)); $('#pvSnap').setAttribute('points', f(snapPts)); $('#pvSnap2').setAttribute('points', f(snapPts));
    const r = snapped / count; $('#pvNote').textContent = r > 0.9 ? 'Almost all wobble is smoothed' : r > 0.6 ? 'Most wobble is smoothed' : 'Only small wobbles are smoothed';
  }
  $('#tRange').addEventListener('input', (e) => setT(+e.target.value));
  $$('.presets button').forEach((b) => b.addEventListener('click', () => setT(+b.dataset.v)));
  $$('.tg').forEach((t) => t.addEventListener('click', () => {
    const on = t.getAttribute('aria-checked') !== 'true'; t.setAttribute('aria-checked', String(on));
    if (t.id === 'tZone') document.body.classList.toggle('no-zone', !on);
    if (t.id === 'tHint') document.body.classList.toggle('no-hint', !on);
    if (t.id === 'tDots') $$('.steps2').forEach((s) => { s.style.display = on ? '' : 'none'; });
  }));
  setT(28);
})();
