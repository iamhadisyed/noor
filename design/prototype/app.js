(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---- fit the stage to the viewport; ?screen=welcome|home|plan shows one phone ---- */
  const stage = $('#stage');
  const solo = new URLSearchParams(location.search).get('screen');
  if (solo) {
    document.body.dataset.solo = '';
    const target = $(`.phone[data-screen="${solo}"]`);
    if (target) target.classList.add('solo');
  }
  function fit() {
    const w = stage.offsetWidth, h = stage.offsetHeight;
    const k = Math.min(innerWidth / w, innerHeight / h, solo ? 1.6 : 1);
    stage.style.transform = `translate(-50%, -50%) scale(${k})`;
  }
  addEventListener('resize', fit); fit();

  /* ---- bottom nav: black pill joins the items to the right of the active one ---- */
  const PITCH = 63.7;
  $$('.nav').forEach((nav) => {
    const items = $$('button', nav), pill = $('.pill', nav);
    const layout = () => {
      const active = items.findIndex((b) => b.getAttribute('aria-current') === 'page');
      items.forEach((b, i) => { b.style.left = `${i * PITCH}px`; });
      const first = active + 1;
      if (first >= items.length) { pill.style.width = 0; return; }
      pill.style.left = `${first * PITCH}px`;
      pill.style.width = `${(items.length - first - 1) * PITCH + 69}px`;
    };
    items.forEach((b) => b.addEventListener('click', () => {
      items.forEach((x) => x.removeAttribute('aria-current'));
      b.setAttribute('aria-current', 'page');
      layout();
    }));
    layout();
  });

  /* ---- weekly bar chart ---- */
  const heights = [97, 65, 104, 123, 152, 104, 65];
  const bars = $('#bars');
  const label = (px) => `${(px / 152 * 4.5).toFixed(1).replace('.', ',')}hr`;
  heights.forEach((px, i) => {
    const b = document.createElement('button');
    b.className = 'bar'; b.style.height = `${px}px`;
    b.setAttribute('aria-pressed', i === 4 ? 'true' : 'false');
    b.setAttribute('aria-label', `${['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][i]}, ${label(px)}`);
    b.innerHTML = `<span class="face"></span><span class="tip">${label(px)}</span>`;
    b.addEventListener('click', () => $$('.bar', bars).forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false')));
    bars.appendChild(b);
  });

  /* ---- progress slider ---- */
  const slider = $('#slider'), knob = $('#knob'), done = $('#done'), pct = $('#pct');
  const set = (v) => {
    v = Math.max(0, Math.min(100, Math.round(v)));
    const w = slider.offsetWidth, x = Math.max(19, Math.min(w - 19, w * v / 100));
    knob.style.left = `${x}px`; done.style.width = `${x}px`;
    pct.textContent = `${v}%`; slider.setAttribute('aria-valuenow', v);
  };
    const fromEvent = (e) => { const r = slider.getBoundingClientRect(); set(((e.clientX - r.left) / r.width) * 100); };
  slider.addEventListener('pointerdown', (e) => { slider.setPointerCapture(e.pointerId); fromEvent(e); });
  slider.addEventListener('pointermove', (e) => { if (slider.hasPointerCapture(e.pointerId)) fromEvent(e); });
  slider.addEventListener('keydown', (e) => {
    const v = +slider.getAttribute('aria-valuenow');
    if (e.key === 'ArrowRight') set(v + 1); if (e.key === 'ArrowLeft') set(v - 1);
  });
  set(78);
})();
