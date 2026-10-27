// Thin client for the Kindo REST API. The portal and the parent app talk to exactly the same endpoints.
const BASE = '/api/v1';
const KEY = 'kindo.token';

export class ApiError extends Error {
  constructor(status, data) { super(data?.message || `Request failed (${status})`); this.status = status; this.data = data || {}; }
  get problems() { return this.data.problems || []; }
  get fieldErrors() { return this.data.errors || {}; }
}

export const auth = {
  get token() { try { return localStorage.getItem(KEY); } catch { return null; } },
  set token(t) { try { t ? localStorage.setItem(KEY, t) : localStorage.removeItem(KEY); } catch { /* private mode */ } },
  user: null,
  onExpire: () => {},
};

export async function api(path, { method = 'GET', body, form, signal } = {}) {
  const headers = { Accept: 'application/json' };
  if (auth.token) headers.Authorization = `Bearer ${auth.token}`;
  let payload;
  if (form) payload = form;
  else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }

  let res;
  try { res = await fetch(BASE + path, { method, headers, body: payload, signal }); }
  catch (e) { if (e.name === 'AbortError') throw e; throw new ApiError(0, { message: 'Cannot reach Kindo. Check your connection and try again.' }); }

  if (res.status === 204) return null;
  const type = res.headers.get('content-type') || '';
  const data = type.includes('json') ? await res.json().catch(() => ({})) : null;

  if (res.status === 401 && auth.token) { auth.token = null; auth.user = null; auth.onExpire(); }
  if (!res.ok) throw new ApiError(res.status, data);
  return data;
}

/** Download an authenticated file (the CSV export) as a browser download. */
export async function download(path, filename) {
  const res = await fetch(BASE + path, { headers: { Authorization: `Bearer ${auth.token}` } });
  if (!res.ok) throw new ApiError(res.status, {});
  const url = URL.createObjectURL(await res.blob());
  const a = Object.assign(document.createElement('a'), { href: url, download: filename });
  document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url), 5000);
}
