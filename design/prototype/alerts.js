(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---- fit; ?screen=feed|detail|lock shows one phone ---- */
  const stage = $('#stage');
  const solo = new URLSearchParams(location.search).get('screen');
  if (solo) { document.body.dataset.solo = ''; $(`.phone[data-screen="${solo}"]`)?.classList.add('solo'); }
  const fit = () => {
    const k = Math.min(innerWidth / stage.offsetWidth, innerHeight / stage.offsetHeight, solo ? 1.6 : 1);
    stage.style.transform = `translate(-50%, -50%) scale(${k})`;
  };
  addEventListener('resize', fit); fit();

  /* ---- bottom nav ---- */
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
    items.forEach((b) => b.addEventListener('click', () => { items.forEach((x) => x.removeAttribute('aria-current')); b.setAttribute('aria-current', 'page'); layout(); }));
    layout();
  });

  /* ---- one shared reply state across the three screens ---- */
  const hero = $('#hero'), detail = $('.phone--detail'), notif = $('#notif'), list = $('#list'), toast = $('#toast');
  const svgCheck = (w, sw) => `<svg width="${w}" height="${w}" fill="none" stroke="#fff" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round"><use href="#i-check"/></svg>`;
  const svgClip = '<svg width="26" height="26" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><use href="#i-clip"/></svg>';
  let timer, newRow = null;

  function setReplied(on, announce = true) {
    hero.classList.toggle('acked', on); detail.classList.toggle('acked', on); notif.classList.toggle('acked', on);
    $('#liveTxt').textContent = on ? 'REPLIED' : 'NEW';
    $('#heroTitle').innerHTML = on ? "You're on<br>your way" : 'Ayan needs<br>the potty';
    $('#heroSub').textContent = on ? 'Ayan was told at 9:42 PM' : '2 min ago · 9:40 PM';
    $('#heroGo').textContent = on ? 'Undo' : "I'm on it";

    $('#detTitle').textContent = on ? "You're on your way" : 'Ayan needs the potty';
    $('#detSub').textContent = on ? 'Ayan was told at 9:42 PM' : 'Sent 9:40 PM · 2 min ago';
    $('#detBadge').innerHTML = on ? svgCheck(28, 2.6) : svgClip;
    $('#whoTitle').textContent = on ? 'You replied in 2 min' : 'Mama and Papa notified';
    $('#whoSub').textContent = on ? 'Ayan sees "Mama is on the way"' : 'Nobody has replied yet';
    $('#seenMama').classList.toggle('seen', on);
    $('#stepDone').style.width = on ? '364px' : '70px'; $('#stepKnob').style.left = on ? '364px' : '70px';
    $('#l1').classList.toggle('on', !on); $('#l3').classList.toggle('on', on);
    $('#detGoTxt').textContent = on ? 'Reply sent · Undo' : "I'm on my way";

    $('#nTag').textContent = on ? 'REPLIED' : 'URGENT';
    $('#nTitle').textContent = on ? "You're on your way" : 'Ayan needs the potty';
    $('#nBody').textContent = on ? 'Ayan was told at 9:42 PM' : 'Tap to respond · 9:40 PM';
    $('#nTime').textContent = on ? '9:42 PM' : 'now';

    if (on && !newRow) {
      newRow = list.firstElementChild.cloneNode(true); newRow.classList.add('fresh');
      newRow.style.setProperty('--c', '#eeadfe');
      $('.tile', newRow).style.background = '#fbe0ff'; $('.tile use', newRow).setAttribute('href', '#art-potty');
      $('.t', newRow).textContent = 'Potty'; $('.s', newRow).textContent = '9:40 PM · You replied in 2 min';
      list.prepend(newRow);
    } else if (!on && newRow) { newRow.remove(); newRow = null; }

    if (announce) {
      $('#toast-msg').textContent = on ? "Ayan was told you're coming" : 'Reply withdrawn';
      toast.classList.add('show'); clearTimeout(timer); timer = setTimeout(() => toast.classList.remove('show'), 4000);
    }
  }
  let replied = false;
  const toggle = () => { replied = !replied; setReplied(replied); };
  $('#heroGo').addEventListener('click', toggle);
  $('#detGo').addEventListener('click', toggle);
  $('#nGo').addEventListener('click', toggle);
  $('#undo').addEventListener('click', () => { replied = false; setReplied(false, false); toast.classList.remove('show'); });
})();
