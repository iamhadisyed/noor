import { api, ApiError, auth } from '../api.js';
import { h, icon, toast, errorText } from '../ui.js';
import { state, go, homeFor } from '../main.js';
import { termsBody } from './terms.js';

export function render(ctx, params, query, name) {
  return name === 'welcome' ? welcome() : signIn();
}

function signIn() {
  let email = '', cooldownTimer = null;
  const box = h('div', { class: 'box' });
  const left = h('section', { class: 'left' },
    h('div', { class: 'brand' }, h('img', { class: 'logo', src: '../brand/mark-96.png', alt: '', width: '44', height: '44' }), 'Kindo'),
    h('h1', null, 'Learning that fits ', h('mark', null, 'every'), ' child.'),
    h('p', null, 'Build Urdu and English lessons with your own pictures and voices, for ordinary learners and for children who need extra support. Follow every step from one place.'));
  const right = h('section', { class: 'right' }, box);

  const stepEmail = () => {
    const input = h('input', { class: 'input', type: 'email', id: 'em', autocomplete: 'email', inputmode: 'email', placeholder: 'you@example.com', required: true, value: email, autofocus: true });
    const err = h('div', { class: 'msg-err', role: 'alert' });
    const btn = h('button', { class: 'btn primary block', type: 'submit' }, 'Email me a code');
    const form = h('form', { novalidate: true, onsubmit: async (e) => {
      e.preventDefault(); err.textContent = '';
      email = input.value.trim().toLowerCase();
      if (!/^\S+@\S+\.\S+$/.test(email)) { input.classList.add('err'); err.textContent = 'Enter a valid email address.'; return; }
      btn.disabled = true; btn.textContent = 'Sending…';
      try { await api('/auth/code', { method: 'POST', body: { email } }); stepCode(60); }
      catch (e2) { if (e2 instanceof ApiError && e2.status === 429) stepCode(e2.data.retry_after || 60); else { err.textContent = errorText(e2); btn.disabled = false; btn.textContent = 'Email me a code'; } }
    } },
      h('div', { class: 'field' }, h('label', { for: 'em' }, 'Email'), input, h('span', { class: 'hint' }, 'We will send a 6-digit code. No password needed.')), err, btn,
      h('p', { class: 'hint' }, 'By continuing you will be asked to accept our ', h('a', { href: '#/terms' }, 'terms and conditions'), '.'),
      h('button', { class: 'btn soft sm', type: 'button', style: 'justify-self:start', 'data-act': 'use-pin', onclick: () => { email = input.value.trim().toLowerCase(); stepPin(); } }, 'I have a PIN'));
    box.replaceChildren(h('h1', null, 'Sign in'), h('p', { class: 'muted' }, 'Parents and administrators sign in here, with the same email as the Kindo app.'), form);
    input.focus();
  };

  const finish = (res) => {
    auth.token = res.token; state.user = res.user; state.children = null;
    go(res.is_new && res.user.role !== 'super_admin' ? '#/welcome' : homeFor());
  };

  /** New accounts read and accept the terms before anything is created. The code is still valid. */
  const stepTerms = async (codeValue) => {
    const err = h('div', { class: 'msg-err', role: 'alert' });
    const check = h('input', { type: 'checkbox', id: 'tac', onchange: () => { go_.disabled = !check.checked; } });
    const go_ = h('button', { class: 'btn primary block', type: 'button', disabled: true, onclick: async () => {
      go_.disabled = true; err.textContent = '';
      try { finish(await api('/auth/verify', { method: 'POST', body: { email, code: codeValue, device_name: 'web', accept_terms: true } })); }
      catch (e) { err.textContent = errorText(e) + ' You may need a new code.'; go_.disabled = false; }
    } }, 'Create my account');
    box.replaceChildren(h('h1', null, 'Before you start'), h('p', { class: 'muted' }, 'Please read and accept these terms to create your account.'), h('div', { class: 'spinner', role: 'status', 'aria-label': 'Loading' }));
    let body;
    try { body = await termsBody(); } catch (e) { err.textContent = errorText(e); box.append(err); return; }
    box.replaceChildren(h('h1', null, 'Before you start'), h('p', { class: 'muted' }, 'Please read and accept these terms to create your account.'), body.el,
      h('label', { class: 'check', for: 'tac' }, check, ' I am the parent or guardian of every child I add, and I accept the terms and the privacy notice.'), err, go_,
      h('button', { class: 'btn soft sm', type: 'button', style: 'justify-self:start', onclick: stepEmail }, 'Cancel'));
  };

  /** Accounts given a 6-digit PIN by the owner (the demo accounts, the administrator) sign in with it. */
  const stepPin = () => {
    const em = h('input', { class: 'input', type: 'email', id: 'pe', autocomplete: 'username', value: email, required: true });
    const pin = h('input', { class: 'input', type: 'password', id: 'pp', inputmode: 'numeric', maxlength: '6', autocomplete: 'current-password', placeholder: '6 digits' });
    const err = h('div', { class: 'msg-err', role: 'alert' });
    const btn = h('button', { class: 'btn primary block', type: 'submit' }, 'Sign in');
    box.replaceChildren(h('h1', null, 'Sign in with a PIN'), h('p', { class: 'muted' }, 'For accounts that were given a 6-digit PIN, such as the demo accounts.'),
      h('form', { novalidate: true, onsubmit: async (e) => {
        e.preventDefault(); err.textContent = ''; btn.disabled = true;
        try { finish(await api('/auth/pin', { method: 'POST', body: { email: em.value.trim().toLowerCase(), pin: pin.value.trim(), device_name: 'web' } })); }
        catch (e2) { err.textContent = errorText(e2); btn.disabled = false; }
      } }, h('div', { class: 'field' }, h('label', { for: 'pe' }, 'Email'), em), h('div', { class: 'field' }, h('label', { for: 'pp' }, 'PIN'), pin), err, btn),
      h('button', { class: 'btn soft sm', type: 'button', style: 'justify-self:start', onclick: stepEmail }, 'Email me a code instead'));
    (email ? pin : em).focus();
  };

  const stepCode = (wait) => {
    const inputs = Array.from({ length: 6 }, (_, i) => h('input', { type: 'text', inputmode: 'numeric', autocomplete: i === 0 ? 'one-time-code' : 'off', maxlength: '1', 'aria-label': `Digit ${i + 1}`, pattern: '[0-9]*' }));
    const err = h('div', { class: 'msg-err', role: 'alert' });
    const resend = h('button', { class: 'btn soft sm', type: 'button', disabled: true }, '');
    const code = () => inputs.map((i) => i.value).join('');
    const verify = async () => {
      if (code().length !== 6) return;
      inputs.forEach((i) => (i.disabled = true)); err.textContent = '';
      try {
        finish(await api('/auth/verify', { method: 'POST', body: { email, code: code(), device_name: 'web' } }));
      } catch (e) {
        if (e.data?.terms_required) return stepTerms(code());
        inputs.forEach((i) => { i.disabled = false; i.value = ''; i.classList.add('err'); });
        err.textContent = e.status === 403 ? 'This account is unavailable. Contact support.' : e.status === 429 ? 'Too many tries. Wait a minute and try again.' : errorText(e);
        inputs[0].focus();
      }
    };
    inputs.forEach((inp, i) => {
      inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, '').slice(-1); inputs.forEach((x) => x.classList.remove('err')); if (inp.value && inputs[i + 1]) inputs[i + 1].focus(); if (code().length === 6) verify(); });
      inp.addEventListener('keydown', (e) => { if (e.key === 'Backspace' && !inp.value && inputs[i - 1]) { inputs[i - 1].focus(); inputs[i - 1].value = ''; } });
      inp.addEventListener('paste', (e) => {
        const d = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!d) return; e.preventDefault();
        d.split('').forEach((c, k) => { inputs[k].value = c; }); inputs[Math.min(d.length, 5)].focus(); if (d.length === 6) verify();
      });
    });
    const tick = (s) => { resend.disabled = s > 0; resend.textContent = s > 0 ? `Send a new code in ${s}s` : 'Send a new code'; };
    let left = wait; tick(left); clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => { left -= 1; tick(Math.max(left, 0)); if (left <= 0) clearInterval(cooldownTimer); if (!document.body.contains(box)) clearInterval(cooldownTimer); }, 1000);
    resend.onclick = async () => { try { await api('/auth/code', { method: 'POST', body: { email } }); toast('A new code is on its way.'); left = 60; tick(left); clearInterval(cooldownTimer); cooldownTimer = setInterval(() => { left -= 1; tick(Math.max(left, 0)); if (left <= 0) clearInterval(cooldownTimer); }, 1000); } catch (e) { toast(errorText(e), 'err'); } };

    box.replaceChildren(
      h('button', { class: 'btn soft sm', type: 'button', style: 'justify-self:start', onclick: stepEmail }, icon('left', 18), 'Change email'),
      h('h1', null, 'Check your email'),
      h('p', { class: 'muted' }, 'We sent a 6-digit code to ', h('b', { style: 'color:var(--ink)' }, email), '. It works once and expires in 10 minutes.'),
      h('div', { class: 'codes', role: 'group', 'aria-label': 'Sign-in code' }, inputs), err, resend);
    inputs[0].focus();
  };

  stepEmail();
  return h('div', { class: 'login' }, left, right);
}

function welcome() {
  const input = h('input', { class: 'input', id: 'nm', autocomplete: 'name', placeholder: 'Your name', value: state.user?.name?.includes('@') ? '' : state.user?.name || '', autofocus: true });
  const err = h('div', { class: 'msg-err', role: 'alert' });
  const form = h('form', { onsubmit: async (e) => {
    e.preventDefault(); if (!input.value.trim()) { err.textContent = 'Please tell us what to call you.'; return; }
    try { state.user = await api('/me', { method: 'PATCH', body: { name: input.value.trim() } }); go('#/children?new=1'); } catch (e2) { err.textContent = errorText(e2); }
  } }, h('div', { class: 'field' }, h('label', { for: 'nm' }, 'What should we call you?'), input), err, h('button', { class: 'btn primary block', type: 'submit' }, 'Continue'));
  return h('div', { class: 'login' },
    h('section', { class: 'left' }, h('div', { class: 'brand' }, h('img', { class: 'logo', src: '../brand/mark-96.png', alt: '', width: '44', height: '44' }), 'Kindo'), h('h1', null, 'Welcome to ', h('mark', null, 'Kindo')), h('p', null, 'Your email is verified. Next, add your child and build a first lesson together.')),
    h('section', { class: 'right' }, h('div', { class: 'box' }, h('h1', null, 'Nice to meet you'), form)));
}
