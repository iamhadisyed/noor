import { api } from '../api.js';
import { h, icon, modal, confirmBox, toast, errorText, debounce, spinner } from '../ui.js';
import { loadChildren, go, state } from '../main.js';
import { meta, supportLine } from './children.js';
import { statusChip } from './lessons.js';

const LANG = { urdu: 'Urdu', english: 'English', both: 'Urdu and English' };
const TARGET_HINT = { trace_letter: 'The letter to trace, for example ب', spell_word: 'The word to spell, for example cat', listen_repeat: 'The phrase to repeat' };

export async function render(ctx, params) {
  const m = await meta();
  await loadChildren();
  let lesson = await api(`/lessons/${params.id}`);
  const child = () => state.children.find((c) => c.id === lesson.child_id);
  const typeInfo = (t) => m.task_types.find((x) => x.type === t);
  const cards = new Map();                        // task id -> card controller
  const page = h('div', { class: 'page' });
  const list = h('div', { class: 'list', style: 'gap:18px' });
  const panel = h('aside', { class: 'panel' });
  const saveState = h('span', { class: 'save-state', role: 'status' }, 'All changes saved');
  const statusSlot = h('span'), publishBtn = h('button', { class: 'btn primary' });

  const saving = () => { saveState.textContent = 'Saving…'; };
  const saved = () => { saveState.textContent = 'All changes saved'; };

  /* ---------- keep the page in step with the server ---------- */
  async function refresh() {
    lesson = await api(`/lessons/${params.id}`);
    for (const t of lesson.tasks) cards.get(t.id)?.update(t);
    drawHead(); drawPanel();
  }
  const refreshSoon = debounce(() => refresh().catch(() => {}), 450);

  /* ---------- header ---------- */
  const titleInput = h('input', { class: 'input', style: 'font-size:22px;font-weight:700;min-height:56px', 'aria-label': 'Lesson title', value: lesson.title, maxlength: 150,
    onchange: async (e) => { if (!e.target.value.trim()) { e.target.value = lesson.title; return; } saving(); try { await api(`/lessons/${lesson.id}`, { method: 'PATCH', body: { title: e.target.value.trim() } }); lesson.title = e.target.value.trim(); saved(); } catch (er) { toast(errorText(er), 'err'); } } });
  function drawHead() {
    statusSlot.replaceChildren(statusChip(lesson.status));
    const pub = lesson.status === 'published';
    publishBtn.className = `btn ${pub ? 'soft' : 'primary'}`;
    publishBtn.replaceChildren(icon(pub ? 'x' : 'check', 20), pub ? 'Unpublish' : 'Publish lesson');
    publishBtn.onclick = pub ? unpublish : publish;
  }
  async function publish() {
    publishBtn.disabled = true;
    try { lesson = await api(`/lessons/${lesson.id}/publish`, { method: 'POST' }); toast('Published. Your child can open this lesson now.'); for (const t of lesson.tasks) cards.get(t.id)?.update(t); drawHead(); drawPanel(); }
    catch (e) {
      if (e.status === 422 && e.problems.length) { lesson.problems = e.problems; lesson.ready = false; drawPanel(); toast('Not ready yet. The checklist shows what is missing.', 'err'); jump(e.problems.find((p) => p.task_id)); }
      else toast(errorText(e), 'err');
    } finally { publishBtn.disabled = false; }
  }
  async function unpublish() { try { lesson = await api(`/lessons/${lesson.id}/unpublish`, { method: 'POST' }); toast('Moved back to draft.'); drawHead(); drawPanel(); } catch (e) { toast(errorText(e), 'err'); } }
  function jump(p) { if (!p?.task_id) return; const el = document.getElementById(`task-${p.task_id}`); if (!el) return; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.remove('flash'); void el.offsetWidth; el.classList.add('flash'); }

  /* ---------- right panel: checklist and lesson settings ---------- */
  function drawPanel() {
    const bad = lesson.problems;
    const c = child();
    panel.replaceChildren(
      h('section', { class: 'card tight', 'aria-labelledby': 'ck' }, h('h3', { id: 'ck', style: 'margin-bottom:12px' }, 'Before you publish'),
        lesson.ready ? h('div', { class: 'check' }, h('div', { class: 'ok' }, icon('check', 20), lesson.status === 'published' ? 'Published and complete' : 'Everything is in place'))
          : h('div', { class: 'check' }, bad.map((p) => h('button', { onclick: () => jump(p) }, icon('alert', 18, { style: 'margin-top:2px' }), p.message)))),
      h('section', { class: 'card tight', 'aria-labelledby': 'ls' }, h('h3', { id: 'ls', style: 'margin-bottom:12px' }, 'Lesson settings'),
        h('div', { class: 'field' }, h('label', { for: 'lg' }, 'Language'), (() => { const s = h('select', { class: 'select', id: 'lg', onchange: (e) => patchLesson({ language: e.target.value }) }, Object.entries(LANG).map(([v, l]) => h('option', { value: v }, l))); s.value = lesson.language; return s; })()),
        h('div', { class: 'field' }, h('label', { for: 'wk' }, 'Week'), h('input', { class: 'input', id: 'wk', type: 'number', min: 1, max: 52, value: lesson.week_number, onchange: (e) => patchLesson({ week_number: Math.min(52, Math.max(1, +e.target.value || 1)) }) })),
        overrides()),
      h('section', { class: 'card tight soft' }, h('h3', null, c?.name || 'Child'), h('p', { class: 'hint', style: 'margin:6px 0 10px' }, c ? supportLine(lesson.settings ? { ...c.support, ...lesson.settings } : c.support) : ''),
        h('a', { class: 'btn soft sm', href: '#/children' }, 'Edit support settings')),
      h('section', { class: 'card tight butter' }, h('h3', { style: 'margin-bottom:6px' }, 'Recording tips'), h('p', { class: 'hint', style: 'color:#4a4258' }, 'Use a quiet room, hold the phone close, and speak slowly. 3 to 10 seconds is plenty. Use the voice your child knows best.')),
      h('button', { class: 'btn danger', onclick: archiveLesson }, icon('trash', 18), 'Archive lesson'));
  }
  async function patchLesson(body) { saving(); try { lesson = { ...lesson, ...(await api(`/lessons/${lesson.id}`, { method: 'PATCH', body })) }; saved(); drawHead(); drawPanel(); } catch (e) { toast(errorText(e), 'err'); } }
  function overrides() {
    const on = !!lesson.settings && Object.keys(lesson.settings).length > 0;
    const base = { ...(child()?.support || {}), ...(lesson.settings || {}) };
    const box = h('div');
    const toggle = h('div', { class: 'setting', style: 'border-top:0;padding-top:0' }, h('div', { class: 't' }, h('b', null, "Different settings for this lesson"), h('span', null, `Otherwise ${child()?.name || 'the child'}'s settings are used.`)),
      h('button', { class: 'switch', role: 'switch', 'aria-label': 'Different settings for this lesson', 'aria-checked': String(on), onclick: async () => {
        if (on) { await patchLesson({ settings: null }); } else { await patchLesson({ settings: { pace: base.pace, max_choices: base.max_choices, no_timers: base.no_timers } }); }
      } }));
    box.append(toggle);
    if (on) {
      const upd = (k, v) => patchLesson({ settings: { ...lesson.settings, [k]: v } });
      box.append(
        h('div', { class: 'field' }, h('label', { for: 'ov-mx' }, 'Most answer choices'), (() => { const s = h('select', { class: 'select', id: 'ov-mx', onchange: (e) => upd('max_choices', +e.target.value) }, [2, 3, 4].map((n) => h('option', { value: n }, n))); s.value = base.max_choices; return s; })()),
        h('div', { class: 'field' }, h('label', { for: 'ov-pc' }, 'Pace'), (() => { const s = h('select', { class: 'select', id: 'ov-pc', onchange: (e) => upd('pace', e.target.value) }, [['slow', 'Slow'], ['normal', 'Normal']].map(([v, l]) => h('option', { value: v }, l))); s.value = base.pace; return s; })()));
    }
    return box;
  }
  async function archiveLesson() {
    if (!(await confirmBox({ title: 'Archive this lesson?', body: 'It leaves your child’s list. Reports keep their history.', confirm: 'Archive', danger: true }))) return;
    try { await api(`/lessons/${lesson.id}`, { method: 'DELETE' }); toast('Lesson archived.'); go('#/lessons'); } catch (e) { toast(errorText(e), 'err'); }
  }

  /* ---------- media helpers ---------- */
  async function shrinkImage(file) {
    try {
      const bmp = await createImageBitmap(file); const k = Math.min(1, m.limits.image_max_side / Math.max(bmp.width, bmp.height));
      const cv = Object.assign(document.createElement('canvas'), { width: Math.round(bmp.width * k), height: Math.round(bmp.height * k) });
      const g = cv.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, cv.width, cv.height); g.drawImage(bmp, 0, 0, cv.width, cv.height);
      const blob = await new Promise((r) => cv.toBlob(r, 'image/jpeg', 0.85));
      return blob ? new File([blob], 'picture.jpg', { type: 'image/jpeg' }) : file;
    } catch { return file; }
  }
  async function upload(card, kind, file) {
    const form = new FormData(); form.append('kind', kind); form.append('file', kind === 'image' ? await shrinkImage(file) : file);
    saving(); card.busy(kind, true);
    try { const r = await api(`/tasks/${card.task.id}/media`, { method: 'POST', form }); card.update(r); saved(); toast(kind === 'image' ? 'Picture added.' : 'Sound added.'); await refresh(); }
    catch (e) { toast(errorText(e), 'err'); } finally { card.busy(kind, false); }
  }

  /* ---------- one task card ---------- */
  function buildCard(initial, index) {
    const card = { task: initial, el: null };
    const el = h('article', { class: 'task', id: `task-${initial.id}`, 'aria-label': `Task ${index + 1}` });
    card.el = el;
    let recState = null, recTimer = null;

    const body = h('div', { class: 'list', style: 'gap:14px' });
    const mediaBox = h('div', { class: 'media' });
    const choicesBox = h('div');

    const patch = async (b) => {
      saving();
      try { const r = await api(`/tasks/${card.task.id}`, { method: 'PATCH', body: b }); Object.assign(card.task, r); saved(); if (r.lesson_unpublished) toast('This lesson went back to draft because a task is no longer complete.', 'err'); refreshSoon(); return r; }
      catch (e) { toast(errorText(e), 'err'); throw e; }
    };
    const patchSoon = debounce((b) => patch(b).catch(() => {}), 600);

    const rule = () => typeInfo(card.task.type);
    const head = () => {
      const t = card.task;
      const typeSel = h('select', { class: 'select', style: 'width:auto;min-height:40px;font-size:14px', 'aria-label': `Type of task ${index + 1}`, onchange: async (e) => { try { await patch({ type: e.target.value }); paint(); refresh(); } catch { e.target.value = t.type; } } }, m.task_types.map((x) => h('option', { value: x.type }, x.label)));
      typeSel.value = t.type;
      return h('div', { class: 'head' }, h('div', { class: 'num', 'aria-hidden': 'true' }, index + 1),
        h('div', { class: 'title' }, h('input', { 'aria-label': `Title of task ${index + 1}`, value: t.title, maxlength: 120, oninput: (e) => { t.title = e.target.value; if (e.target.value.trim()) patchSoon({ title: e.target.value.trim() }); } })),
        typeSel,
        h('button', { class: 'icon-btn flat', 'aria-label': `Move task ${index + 1} up`, disabled: index === 0, onclick: () => move(index, -1) }, icon('up', 20)),
        h('button', { class: 'icon-btn flat', 'aria-label': `Move task ${index + 1} down`, disabled: index === lesson.tasks.length - 1, onclick: () => move(index, 1) }, icon('down', 20)),
        h('button', { class: 'icon-btn flat', 'aria-label': `Delete task ${index + 1}`, onclick: () => removeTask(card.task) }, icon('trash', 20)));
    };

    const fields = () => {
      const t = card.task, r = rule(), soundForced = r.audio === 'required';
      return h('div', { class: 'list', style: 'gap:12px' },
        h('div', { class: 'field', style: 'margin:0' }, h('label', { for: `in-${t.id}` }, 'What should the child do?'), h('textarea', { class: 'textarea', id: `in-${t.id}`, style: 'min-height:64px', maxlength: 400, placeholder: 'A short, kind instruction', oninput: (e) => patchSoon({ instruction: e.target.value || null }) }, t.instruction || '')),
        r.target ? h('div', { class: 'field', style: 'margin:0' }, h('label', { for: `tg-${t.id}` }, r.target_label), h('input', { class: `input${(t.target || '').trim() ? '' : ' err'}`, id: `tg-${t.id}`, value: t.target || '', maxlength: 80, placeholder: TARGET_HINT[t.type], oninput: (e) => { e.target.classList.toggle('err', !e.target.value.trim()); patchSoon({ target: e.target.value }); } })) : null,
        h('div', { class: 'setting', style: 'border-top:0;padding:4px 0' }, h('div', { class: 't' }, h('b', null, 'This task needs a sound'), h('span', null, soundForced ? 'Always needed for this kind of task.' : 'Turn on if your child needs to hear something here.')),
          h('button', { class: 'switch', role: 'switch', 'aria-label': 'This task needs a sound', 'aria-checked': String(soundForced || t.needs_audio), disabled: soundForced, onclick: async (e) => { const on = e.currentTarget.getAttribute('aria-checked') !== 'true'; e.currentTarget.setAttribute('aria-checked', String(on)); try { await patch({ needs_audio: on }); await refresh(); } catch { e.currentTarget.setAttribute('aria-checked', String(!on)); } } })));
    };

    const choices = () => {
      choicesBox.replaceChildren();
      if (card.task.type !== 'pick_picture') return;
      const t = card.task; const opt = t.options || { choices: ['', ''], correct: 0 }; const cs = [...(opt.choices.length ? opt.choices : ['', ''])]; let ok = opt.correct || 0;
      const maxC = child()?.support?.max_choices || 4;
      const save = () => { const clean = cs.map((c) => c.trim()); if (clean.filter(Boolean).length >= 2 && clean.every(Boolean) && ok < clean.length) patchSoon({ options: { choices: clean, correct: ok } }); };
      const draw = () => choicesBox.replaceChildren(h('div', { class: 'field', style: 'margin:0' }, h('span', { class: 'label' }, 'Answer choices'), h('div', { class: 'choices', role: 'radiogroup', 'aria-label': 'Choices. Select the correct one.' },
        cs.map((c, i) => h('div', { class: 'c' }, h('input', { type: 'radio', name: `ok-${t.id}`, checked: ok === i, 'aria-label': `Choice ${i + 1} is correct`, onchange: () => { ok = i; save(); } }), h('input', { class: 'input', 'aria-label': `Choice ${i + 1}`, value: c, maxlength: 40, placeholder: `Choice ${i + 1}`, oninput: (e) => { cs[i] = e.target.value; save(); } }),
          cs.length > 2 ? h('button', { class: 'icon-btn flat', 'aria-label': `Remove choice ${i + 1}`, onclick: () => { cs.splice(i, 1); if (ok >= cs.length) ok = 0; draw(); save(); } }, icon('x', 18)) : null)),
        cs.length < 4 ? h('button', { class: 'btn soft sm', style: 'justify-self:start', onclick: () => { cs.push(''); draw(); } }, icon('plus', 18), 'Add a choice') : null,
        cs.length > maxC ? h('span', { class: 'hint', style: 'color:var(--warn)' }, `${child()?.name} sees at most ${maxC} choices. Remove ${cs.length - maxC} to publish.`) : h('span', { class: 'hint' }, 'Tick the correct answer.'))));
      draw();
    };

    const slotImage = () => {
      const t = card.task, need = t.requires_image, miss = need && !t.has_image;
      const file = h('input', { type: 'file', accept: 'image/*', hidden: true, 'aria-label': `Upload a picture for task ${index + 1}`, onchange: (e) => e.target.files[0] && upload(card, 'image', e.target.files[0]) });
      const s = h('div', { class: `slot${miss ? ' missing' : ''}`, 'data-slot': 'image' },
        h('div', { class: 'sh' }, h('span', null, 'Picture'), h('span', { class: `chip ${miss ? 'warn' : need ? 'butter' : ''}` }, miss ? 'Missing' : need ? 'Needed' : 'Optional')),
        t.has_image ? h('img', { src: t.image_url, alt: t.image_alt || `Picture for ${t.title}`, onerror: () => refresh().catch(() => {}) }) : h('p', { class: 'hint' }, need ? 'Add a picture to publish this task.' : 'A picture helps, but is not required.'),
        t.has_image ? h('input', { class: 'input', style: 'min-height:44px;font-size:14px', 'aria-label': 'Describe the picture for screen readers', placeholder: 'Describe the picture (for screen readers)', value: t.image_alt || '', maxlength: 160, oninput: (e) => patchSoon({ image_alt: e.target.value || null }) }) : null,
        h('div', { class: 'btns' }, file, h('button', { class: 'btn soft sm', 'data-act': 'upload-image', onclick: () => file.click() }, icon('image', 18), t.has_image ? 'Replace' : 'Upload picture'),
          t.has_image ? h('button', { class: 'btn soft sm', onclick: () => removeMedia('image') }, icon('trash', 18), 'Remove') : null));
      s.addEventListener('dragover', (e) => { e.preventDefault(); s.classList.add('drag'); }); s.addEventListener('dragleave', () => s.classList.remove('drag'));
      s.addEventListener('drop', (e) => { e.preventDefault(); s.classList.remove('drag'); const f = e.dataTransfer.files[0]; if (f && f.type.startsWith('image/')) upload(card, 'image', f); });
      return s;
    };
    const slotSound = () => {
      const t = card.task, need = t.requires_sound, miss = need && !t.has_sound;
      const file = h('input', { type: 'file', accept: 'audio/*', hidden: true, 'aria-label': `Upload a sound for task ${index + 1}`, onchange: (e) => e.target.files[0] && upload(card, 'audio', e.target.files[0]) });
      const recording = !!recState;
      return h('div', { class: `slot${miss ? ' missing' : ''}`, 'data-slot': 'sound' },
        h('div', { class: 'sh' }, h('span', null, 'Sound'), h('span', { class: `chip ${miss ? 'warn' : need ? 'butter' : ''}` }, miss ? 'Missing' : need ? 'Needed' : 'Optional')),
        recording ? h('div', { class: 'rec-live', role: 'status' }, h('i'), h('span', { id: `rt-${t.id}` }, 'Recording 0:00'))
          : t.has_sound ? h('audio', { controls: true, src: t.audio_url, 'aria-label': `Sound for ${t.title}`, onerror: () => refresh().catch(() => {}) }) : h('p', { class: 'hint' }, need ? 'Record or upload a sound to publish this task.' : 'Add a sound if it helps.'),
        h('div', { class: 'btns' }, file,
          recording ? h('button', { class: 'btn primary sm', 'data-act': 'stop', onclick: stopRec }, icon('stop', 18), 'Stop and save')
            : [h('button', { class: 'btn soft sm', 'data-act': 'record', onclick: startRec }, icon('mic', 18), t.has_sound ? 'Record again' : 'Record'), h('button', { class: 'btn soft sm', 'data-act': 'upload-sound', onclick: () => file.click() }, 'Upload'),
              t.has_sound ? h('button', { class: 'btn soft sm', onclick: () => removeMedia('audio') }, icon('trash', 18), 'Remove') : null]));
    };

    async function startRec() {
      if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') return toast('This browser cannot record. Upload a sound file instead.', 'err');
      let stream;
      try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch { return toast('The microphone is blocked. Allow it in your browser, or upload a sound file.', 'err'); }
      const mime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].find((x) => MediaRecorder.isTypeSupported(x));
      const rec = new MediaRecorder(stream, mime ? { mimeType: mime, audioBitsPerSecond: 32000 } : undefined); const chunks = []; let secs = 0;
      rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
      rec.onstop = async () => {
        stream.getTracks().forEach((x) => x.stop()); clearInterval(recTimer); recState = null; paintMedia();
        const blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' });
        if (blob.size > m.limits.audio_max_kb * 1024) return toast(`That recording is too long. Keep it under about 10 seconds.`, 'err');
        if (blob.size < 500) return toast('Nothing was recorded. Try again.', 'err');
        await upload(card, 'audio', new File([blob], 'recording', { type: blob.type }));
      };
      rec.start(); recState = rec; paintMedia();
      recTimer = setInterval(() => { secs += 1; const n = document.getElementById(`rt-${card.task.id}`); if (n) n.textContent = `Recording 0:${String(secs).padStart(2, '0')}`; if (secs >= 10) stopRec(); }, 1000);
    }
    function stopRec() { try { recState?.stop(); } catch { /* already stopped */ } }
    async function removeMedia(kind) {
      saving(); try { const r = await api(`/tasks/${card.task.id}/media/${kind}`, { method: 'DELETE' }); card.update(r); saved(); if (r.lesson_unpublished) toast('This lesson went back to draft because a task is no longer complete.', 'err'); await refresh(); } catch (e) { toast(errorText(e), 'err'); }
    }
    const paintMedia = () => mediaBox.replaceChildren(slotImage(), slotSound());
    const paint = () => { body.replaceChildren(fields(), choicesBox, mediaBox); choices(); paintMedia(); el.replaceChildren(head(), body); };

    card.update = (fresh) => { const retype = fresh.type !== card.task.type; Object.assign(card.task, fresh); if (retype) paint(); else paintMedia(); };
    card.busy = (kind, on) => el.querySelectorAll(`[data-slot="${kind === 'image' ? 'image' : 'sound'}"] .btn`).forEach((b) => { b.disabled = on; });
    paint();
    return card;
  }

  /* ---------- list management ---------- */
  function drawList() {
    cards.clear();
    list.replaceChildren(...lesson.tasks.map((t, i) => { const c = buildCard({ ...t }, i); cards.set(t.id, c); return c.el; }),
      lesson.tasks.length ? null : h('div', { class: 'card empty' }, h('h3', null, 'No tasks yet'), h('p', { class: 'muted' }, 'Add the first task. Each one can have a picture and a sound.')),
      h('button', { class: 'btn soft', style: 'justify-self:start', 'data-act': 'add-task', onclick: addTask }, icon('plus', 20), 'Add a task'));
  }
  function addTask() {
    modal((box, close) => box.append(h('h2', null, 'What kind of task?'), h('p', { class: 'muted' }, 'You can change this later.'),
      h('div', { class: 'types', style: 'margin-top:16px' }, m.task_types.map((t) => {
        const needs = [t.image === 'required' ? 'a picture' : null, t.audio === 'required' ? 'a sound' : null].filter(Boolean);
        return h('button', { 'data-type': t.type, onclick: async () => {
          try { await api(`/lessons/${lesson.id}/tasks`, { method: 'POST', body: { type: t.type, title: t.label } }); close(); await refresh(); drawList(); const last = list.querySelectorAll('.task'); last[last.length - 1]?.querySelector('.title input')?.select(); }
          catch (e) { toast(errorText(e), 'err'); }
        } }, h('b', null, t.label), h('span', null, needs.length ? `Needs ${needs.join(' and ')}` : 'Picture and sound are optional'));
      })), h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Cancel'))));
  }
  async function move(i, d) {
    const ids = lesson.tasks.map((t) => t.id); [ids[i], ids[i + d]] = [ids[i + d], ids[i]];
    try { await api(`/lessons/${lesson.id}/tasks/reorder`, { method: 'POST', body: { ids } }); await refresh(); drawList(); } catch (e) { toast(errorText(e), 'err'); }
  }
  async function removeTask(t) {
    if (!(await confirmBox({ title: `Delete "${t.title}"?`, body: 'Its picture and sound are deleted too.', confirm: 'Delete task', danger: true }))) return;
    try { const r = await api(`/tasks/${t.id}`, { method: 'DELETE' }); if (r.unpublished) toast('This lesson went back to draft because it is no longer complete.', 'err'); await refresh(); drawList(); } catch (e) { toast(errorText(e), 'err'); }
  }

  drawHead(); drawPanel(); drawList();
  page.append(
    h('a', { class: 'btn soft sm', href: '#/lessons', style: 'margin-bottom:18px' }, icon('left', 18), 'All lessons'),
    h('div', { class: 'row between', style: 'margin-bottom:24px;gap:16px' }, h('div', { style: 'flex:1;min-width:260px' }, titleInput), h('div', { class: 'row' }, saveState, statusSlot, publishBtn)),
    h('div', { class: 'editor' }, h('section', { 'aria-label': 'Tasks' }, list), panel));
  return page;
}
