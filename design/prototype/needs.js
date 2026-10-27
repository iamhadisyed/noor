(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---- fit stage; ?screen=board|sent|feel shows one phone ---- */
  const stage = $('#stage');
  const solo = new URLSearchParams(location.search).get('screen');
  if (solo) {
    document.body.dataset.solo = '';
    const target = $(`.phone[data-screen="${solo}"]`);
    if (target) target.classList.add('solo');
  }
  const fit = () => {
    const k = Math.min(innerWidth / stage.offsetWidth, innerHeight / stage.offsetHeight, solo ? 1.6 : 1);
    stage.style.transform = `translate(-50%, -50%) scale(${k})`;
  };
  addEventListener('resize', fit); fit();

  /* ---- bottom nav (pill joins the items right of the active one) ---- */
  const PITCH = 63.7;
  $$('.nav').forEach((nav) => {
    const items = $$('button', nav), pill = $('.pill', nav);
    const pillL = pill.cloneNode(); pillL.classList.add('pill-l'); nav.insertBefore(pillL, pill);
    const layout = () => {
      const active = items.findIndex((b) => b.getAttribute('aria-current') === 'page');
      items.forEach((b, i) => { b.style.left = `${i * PITCH}px`; });
      const first = active + 1;
      pill.style.left = `${first * PITCH}px`;
      pill.style.width = first >= items.length ? 0 : `${(items.length - first - 1) * PITCH + 69}px`;
      pillL.style.left = '0px';
      pillL.style.width = active <= 0 ? 0 : `${(active - 1) * PITCH + 69}px`;
    };
    items.forEach((b) => b.addEventListener('click', () => {
      items.forEach((x) => x.removeAttribute('aria-current'));
      b.setAttribute('aria-current', 'page'); layout();
    }));
    layout();
  });

  /* ---- boards: category filter, tap-to-send, Pro lock ---- */
  let toastTimer;
  $$('.phone--board, .phone--feel').forEach((phone) => {
    const grid = $('.grid', phone), cards = $$('.need', phone), chips = $$('.chips button', phone);
    const toast = $('.toast', phone), msg = $('.msg', phone), undo = $('.undo', phone);
    let last = null;

    const reset = () => { cards.forEach((c) => { c.classList.remove('is-sent'); $('.send use', c)?.setAttribute('href', '#i-speaker'); }); };
    const showToast = (text, canUndo) => {
      if (!toast) return;
      msg.textContent = text; undo.hidden = !canUndo; toast.classList.add('show');
      clearTimeout(toastTimer); toastTimer = setTimeout(() => toast.classList.remove('show'), 4000);
    };

    chips.forEach((chip) => chip.addEventListener('click', () => {
      chips.forEach((c) => c.setAttribute('aria-selected', c === chip ? 'true' : 'false'));
      const cat = chip.dataset.cat; let shown = 0;
      cards.forEach((c) => { const on = cat === 'all' || c.dataset.cat === cat; c.hidden = !on; shown += on; });
      $('.empty', grid)?.remove();
      if (!shown) { const p = document.createElement('p'); p.className = 'empty'; p.textContent = 'More cards are coming soon.'; grid.append(p); }
    }));

    cards.forEach((card) => card.addEventListener('click', () => {
      if (card.classList.contains('need--locked')) {
        card.classList.remove('is-wobble'); void card.offsetWidth; card.classList.add('is-wobble');   // gentle wobble, no buzzer
        showToast('Ask a grown-up to unlock this card', false);
        return;
      }
      reset(); last = card;
      card.classList.add('is-sent'); $('.send use', card)?.setAttribute('href', '#i-check');
      showToast("Sent to Mama & Papa", true);
    }));

    undo?.addEventListener('click', () => { reset(); last = null; toast.classList.remove('show'); });
  });
})();
