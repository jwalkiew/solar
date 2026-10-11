import { tr } from './i18n.js';

export class ApiError extends Error {
  constructor(message, kind) {
    super(message);
    this.kind = kind;
  }
}

function query(state) {
  const p = new URLSearchParams();
  if (state.lat !== null && state.lon !== null) {
    p.set('lat', state.lat);
    p.set('lon', state.lon);
  }
  if (state.elev !== null) p.set('elev', state.elev);
  if (!state.live) {
    if (state.t !== null) p.set('t', state.t);
    else if (state.local) p.set('local', state.local);
  }
  return p.toString();
}

async function getJson(url, signal) {
  let res;
  try {
    res = await fetch(url, { signal, headers: { Accept: 'application/json' } });
  } catch (e) {
    if (e.name === 'AbortError') throw e;
    throw new ApiError(tr('err.network'), 'network');
  }
  let body = null;
  try {
    body = await res.json();
  } catch {
    body = null;
  }
  if (!res.ok) {
    const msg = body && typeof body.error === 'string' ? body.error : `HTTP ${res.status}`;
    throw new ApiError(msg, 'server');
  }
  if (body === null) throw new ApiError(tr('err.network'), 'network');
  return body;
}

export function fetchSky(state, signal) {
  return getJson(`api/sky.php?${query(state)}`, signal);
}

export function fetchEvents(state, signal) {
  return getJson(`api/events.php?${query(state)}`, signal);
}

// Land is optional: any failure yields null and the map simply renders without land.
export async function fetchLand(signal) {
  try {
    return await getJson('data/land.json', signal);
  } catch {
    return null;
  }
}
