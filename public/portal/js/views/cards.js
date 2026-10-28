import { api } from '../api.js';
import { h, icon, toast, errorText, emptyState, spinner, modal, confirmBox } from '../ui.js';
import { loadChildren, currentChild, childPicker } from '../main.js';

const CAT = { needs: 'Needs', food: 'Food', emotions: 'Feelings', activities: 'Activities' };

/** A parent's own picture cards. They sync to the app and appear on the child's board next to the stock cards. */
export async function render() {
  const kids = await loadChildren();
  if (!kids.length) return emptyState('Add a child first', 'Picture cards belong to a child.', h('a', { class: 'btn primary', href: '#/children?new=1' }, icon('plus', 20), 'Add a child'));
  const page = h('div', { class: 'page' });
  const body = h('div', null, spinner());

  async function load() {
    const child = currentChild();
    const all = await api('/aac-cards');
    const mine = all.filter((c) => c.custom && c.child_id === child.id);
    body.replaceChildren(h('section', { class: 'card', 'data-card': 'my-cards' },
      h('h2', { style: 'margin-bottom:6px' }, `${child.name}'s own cards`), h('p', { class: 'muted', style: 'margin-bottom:16px' }, 'A favourite toy, a person, a place. Add a picture and, if you like, your voice saying the word.'),
      mine.length ? h('div', { class: 'list' }, mine.map((c) => h('div', { class: 'item', 'data-card-id': c.id },
        h('img', { src: c.icon_url, alt: '', width: 56, height: 56, style: 'border-radius:14px;object-fit:cover' }),
        h('div', { class: 'grow' }, h('b', null, c.english_label), h('span', null, `${CAT[c.category] || c.category}${c.urdu_label && c.urdu_label !== c.english_label ? ` · ${c.urdu_label}` : ''}${c.audio_url ? ' · has your voice' : ''}`)),
        h('button', { class: 'icon-btn', 'aria-label': `Edit ${c.english_label}`, 'data-act': 'edit-card', onclick: () => form(child, c) }, icon('edit', 18)),
        h('button', { class: 'icon-btn', 'aria-label': `Delete ${c.english_label}`, 'data-act': 'delete-card', onclick: async () => {
          if (!(await confirmBox({ title: `Delete "${c.english_label}"?`, body: 'The card disappears from the app too.', confirm: 'Delete', danger: true }))) return;
          try { await api(`/aac-cards/${c.id}`, { method: 'DELETE' }); toast('Card deleted.'); load(); } catch (e) { toast(errorText(e), 'err'); }
        } }, icon('trash', 18))))) : h('p', { class: 'muted' }, 'No cards of your own yet.'),
      h('div', { style: 'margin-top:16px' }, h('button', { class: 'btn primary', 'data-act': 'new-card', onclick: () => form(child, null) }, icon('plus', 20), 'New card'))));
  }

  function form(child, card) {
    modal((box, close) => {
      const en = h('input', { class: 'input', id: 'ac-en', maxlength: 100, value: card?.english_label || '', placeholder: 'e.g. Blue car', autofocus: true });
      const ur = h('input', { class: 'input', id: 'ac-ur', maxlength: 100, value: card && card.urdu_label !== card.english_label ? card.urdu_label : '', placeholder: 'Optional', dir: 'auto' });
      const cat = h('select', { class: 'select', id: 'ac-cat' }, Object.entries(CAT).map(([v, l]) => h('option', { value: v }, l)));
      cat.value = card?.category || 'activities';
      const pic = h('input', { class: 'input', id: 'ac-pic', type: 'file', accept: 'image/*' });
      const snd = h('input', { class: 'input', id: 'ac-snd', type: 'file', accept: 'audio/*' });
      const err = h('div', { class: 'msg-err', role: 'alert' });
      const ok = h('button', { class: 'btn primary', 'data-act': 'save-card', onclick: async () => {
        err.textContent = '';
        if (!en.value.trim()) { err.textContent = 'Give the card a name.'; return en.focus(); }
        if (!card && !pic.files[0]) { err.textContent = 'Choose a picture.'; return; }
        const f = new FormData(); f.append('english_label', en.value.trim()); f.append('urdu_label', ur.value.trim()); f.append('category', cat.value);
        if (pic.files[0]) f.append('icon', pic.files[0]);
        if (snd.files[0]) f.append('audio', snd.files[0]);
        ok.disabled = true;
        try { await api(card ? `/aac-cards/${card.id}` : `/children/${child.id}/aac-cards`, { method: 'POST', form: f }); close(); toast(card ? 'Card saved.' : 'Card added. It will appear in the app.'); load(); }
        catch (e) { ok.disabled = false; err.textContent = errorText(e); }
      } }, card ? 'Save card' : 'Add card');
      box.append(h('h2', null, card ? `Edit "${card.english_label}"` : `New card for ${child.name}`),
        h('div', { class: 'field', style: 'margin-top:16px' }, h('label', { for: 'ac-en' }, 'Name'), en),
        h('div', { class: 'field' }, h('label', { for: 'ac-ur' }, 'Name in Urdu'), ur),
        h('div', { class: 'field' }, h('label', { for: 'ac-cat' }, 'Group'), cat),
        h('div', { class: 'field' }, h('label', { for: 'ac-pic' }, card ? 'New picture (optional)' : 'Picture'), pic),
        h('div', { class: 'field' }, h('label', { for: 'ac-snd' }, 'Your voice saying it (optional)'), snd, h('span', { class: 'hint' }, 'A short recording, up to 150 KB.')), err,
        h('div', { class: 'actions' }, h('button', { class: 'btn soft', onclick: close }, 'Cancel'), ok));
    });
  }

  function draw() {
    const child = currentChild();
    page.replaceChildren(h('div', { class: 'page-head' }, h('div', null, h('h1', null, 'Picture cards'), h('p', null, `Cards you make for ${child.name} sit beside the standard ones on their board.`)), h('div', { class: 'row' }, childPicker(draw))), body);
    body.replaceChildren(spinner());
    load().catch((e) => body.replaceChildren(h('p', { class: 'msg-err' }, errorText(e))));
  }
  draw();
  return page;
}
