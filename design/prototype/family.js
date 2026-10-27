(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---- fit; ?screen=game|win|add shows one phone ---- */
  const stage = $('#stage');
  const solo = new URLSearchParams(location.search).get('screen');
  if (solo) { document.body.dataset.solo = ''; $(`.phone[data-screen="${solo}"]`)?.classList.add('solo'); }
  const fit = () => {
    const k = Math.min(innerWidth / stage.offsetWidth, innerHeight / stage.offsetHeight, solo ? 1.6 : 1);
    stage.style.transform = `translate(-50%, -50%) scale(${k})`;
  };
  addEventListener('resize', fit); fit();

  /* ---- voice clips: purely visual here (the app plays the recorded 3-second clip) ---- */
  const playClip = (box) => { box.classList.remove('playing'); void box.offsetWidth; box.classList.add('playing'); setTimeout(() => box.classList.remove('playing'), 3200); };
  $$('.play').forEach((b) => b.addEventListener('click', () => playClip(b.parentElement)));
  $('#listen')?.addEventListener('click', () => playClip($('.voice')));

  /* ---- game: gentle feedback only, never a buzzer ---- */
  const toast = $('#toast'), msg = $('#toast-msg'), tick = $('#tick'), choices = $$('.choice'), dashes = $$('#dashes i');
  let timer;
  const say = (text, soft) => { msg.textContent = text; tick.classList.toggle('soft', !!soft); toast.classList.add('show'); clearTimeout(timer); timer = setTimeout(() => toast.classList.remove('show'), 2600); };
  const reset = () => { choices.forEach((c) => c.classList.remove('is-right', 'dim')); dashes[1].className = 'cur'; dashes[2].className = ''; };
  choices.forEach((c) => c.addEventListener('click', () => {
    if (c.dataset.correct === 'true') {
      reset(); c.classList.add('is-right'); choices.filter((x) => x !== c).forEach((x) => x.classList.add('dim'));
      dashes[1].className = 'done'; dashes[2].className = 'cur';
      say("That's Mama!", false); playClip($('.voice'));
      setTimeout(reset, 4200);
    } else {
      c.classList.remove('is-wobble'); void c.offsetWidth; c.classList.add('is-wobble');
      say("Let's try one more time!", true);
    }
  }));

  /* ---- parent: add a family card ---- */
  const ur = { Mama: 'ماما', Papa: 'بابا', Dada: 'دادا', Dadi: 'دادی', Nani: 'نانی', Nana: 'نانا' };
  const nm = $('#nm'), urp = $('#urp');
  const faces = { Mama: ['#av-mama', '#f8c9f4'], Papa: ['#av-papa', '#fde8b8'], Dada: ['#av-papa', '#fde8b8'], Dadi: ['#av-dadi', '#cdbdf0'], Nani: ['#av-mama', '#f8c9f4'], Nana: ['#av-papa', '#fde8b8'] };
  $$('.phone--add .chips button').forEach((b) => b.addEventListener('click', () => {
    $$('.phone--add .chips button').forEach((x) => x.setAttribute('aria-checked', x === b ? 'true' : 'false'));
    nm.value = b.dataset.rel; urp.textContent = ur[b.dataset.rel];
    const [href, bg] = faces[b.dataset.rel]; $('.shot use').setAttribute('href', href); $('.shot').style.background = bg;
  }));
  const chipRow = $('.phone--add .chips');
  chipRow.addEventListener('scroll', () => chipRow.classList.toggle('scrolled', chipRow.scrollLeft > 4), { passive: true });
  nm.addEventListener('input', () => { urp.textContent = ur[nm.value.trim()] || ''; });

  const rec = $('#rec'), mic = $('#mic'), recTime = $('#recTime');
  let recTimer;
  mic.addEventListener('click', () => {
    if (rec.classList.contains('recording')) return;
    rec.classList.remove('done'); rec.classList.add('recording');
    $('#recTitle').textContent = 'Recording…'; $('#recSub').textContent = 'Say hello, nice and clear';
    let s = 0; recTime.textContent = '0:00';
    recTimer = setInterval(() => {
      s += 1; recTime.textContent = `0:0${s}`;
      if (s >= 3) { clearInterval(recTimer); rec.classList.remove('recording'); rec.classList.add('done'); $('#recTitle').textContent = 'Say hello · 3 sec'; $('#recSub').textContent = 'Recorded · 48 KB'; }
    }, 1000);
  });

  const save = $('#save'), qDone = $('#qDone'), qKnob = $('#qKnob');
  let saved = false;
  save.addEventListener('click', () => {
    saved = !saved;
    save.classList.toggle('saved', saved); save.textContent = saved ? `Saved · ${nm.value || 'Family card'} ✓` : 'Save card';
    qDone.style.width = saved ? '347px' : '188px'; qKnob.style.left = saved ? '332px' : '188px';
    $('#qTitle').textContent = saved ? '2 of 2 free cards used' : '1 of 2 free cards used';
  });
})();
