import { fetchSky, fetchEvents, fetchLand } from './api.js';
import { applyStaticText, tr, STEPS } from './i18n.js';
import { inputValue, isNum, nf } from './format.js';
import { h } from './dom.js';
import * as timeView from './views/time.js';
import * as sunView from './views/sun.js';
import * as moonView from './views/moon.js';
import * as planetsView from './views/planets.js';
import * as domeView from './views/dome.js';
import * as orreryView from './views/orrery.js';
import * as eclipticView from './views/ecliptic.js';
import * as chartView from './views/chart.js';
import * as mapView from './views/map.js';
import * as eventsView from './views/events.js';
import * as separationsView from './views/separations.js';

const PANELS = {
  time: timeView,
  sun: sunView,
  moon: moonView,
  planets: planetsView,
  dome: domeView,
  orrery: orreryView,
  ecliptic: eclipticView,
  chart: chartView,
  map: mapView,
  events: eventsView,
  separations: separationsView,
};
const EVENT_PANELS = ['moon', 'events'];
const DEBOUNCE_MS = 300;
const SKY_REFRESH_MS = 30000;
const EVENTS_REFRESH_MS = 600000;

const state = { lat: null, lon: null, elev: null, t: null, local: null, live: false };
let sky = null;
let events = null;
let land = null;
let skyCtl = null;
let eventsCtl = null;
let debounceTimer = null;
let pendingPush = false;
const pending = { sky: false, events: false };

const $ = (id) => document.getElementById(id);
const els = {};

function num(v) {
  if (v === null || v === undefined || String(v).trim() === '') return null;
  const x = Number(v);
  return Number.isFinite(x) ? x : null;
}

function readUrl() {
  const p = new URLSearchParams(location.search);
  state.lat = num(p.get('lat'));
  state.lon = num(p.get('lon'));
  if (state.lat === null || state.lon === null) {
    state.lat = null;
    state.lon = null;
  }
  state.elev = num(p.get('elev'));
  state.t = num(p.get('t'));
  state.local = state.t === null && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(p.get('local') || '') ? p.get('local') : null;
  state.live = p.get('live') === '1';
  if (!state.live && state.t === null && state.local === null) state.live = true;
  if (state.live) {
    state.t = null;
    state.local = null;
  }
}

function urlFor() {
  const p = new URLSearchParams();
  if (state.lat !== null && state.lon !== null) {
    p.set('lat', state.lat);
    p.set('lon', state.lon);
  }
  if (state.elev !== null) p.set('elev', state.elev);
  if (state.live) p.set('live', '1');
  else if (state.t !== null) p.set('t', state.t);
  else if (state.local) p.set('local', state.local);
  const q = p.toString();
  return location.pathname + (q ? `?${q}` : '');
}

function writeUrl(push) {
  const url = urlFor();
  if (url === location.pathname + location.search) return;
  if (push) history.pushState(null, '', url);
  else history.replaceState(null, '', url);
}

function showError(msg) {
  els.errorText.textContent = msg;
  els.error.hidden = false;
}

function clearError() {
  els.error.hidden = true;
  els.errorText.textContent = '';
}

function setBusy() {
  const busy = pending.sky || pending.events;
  els.loading.hidden = !busy;
  document.body.classList.toggle('busy', busy);
  document.body.setAttribute('aria-busy', busy ? 'true' : 'false');
}

function ctxNow() {
  return { sky, events, land, live: state.live, actions };
}

function renderPanel(id) {
  const body = document.querySelector(`#p-${id} .panel-body`);
  if (!body) return;
  if (!sky) return;
  try {
    PANELS[id].render(body, ctxNow());
  } catch (e) {
    console.error(id, e);
    body.replaceChildren(h('p', { class: 'muted' }, tr('err.panel')));
  }
}

function renderPanels(ids) {
  for (const id of ids) renderPanel(id);
}

// nearest town from the API; the country is added (in Polish) only outside Poland
function placeLabel(place) {
  if (!place || !place.name) return '';
  let text = place.name;
  if (isNum(place.distanceKm) && place.distanceKm >= 3) text += ` (${nf(place.distanceKm, 0)} km)`;
  if (place.country && place.country !== 'PL') {
    let country = place.country;
    try {
      country = new Intl.DisplayNames(['pl'], { type: 'region' }).of(place.country) || place.country;
    } catch {
      // unknown region code: keep the ISO code
    }
    text += `, ${country}`;
  }
  return text;
}

function syncControls(fromResponse = false) {
  els.live.checked = state.live;
  els.liveBadge.hidden = !state.live;
  if (state.lat !== null) {
    els.lat.value = state.lat;
    els.lon.value = state.lon;
  } else {
    els.lat.value = '';
    els.lon.value = '';
  }
  els.elev.value = state.elev !== null ? state.elev : '';
  if (!sky) return;
  const loc = sky.location || {};
  els.lat.placeholder = isNum(loc.lat) ? nf(loc.lat, 4) : '';
  els.lon.placeholder = isNum(loc.lon) ? nf(loc.lon, 4) : '';
  els.elev.placeholder = isNum(loc.elev) ? nf(loc.elev, 0) : '';
  const label = loc.name || placeLabel(loc.place);
  if (label) els.place.textContent = `${label}${loc.isDefault ? ` (${tr('ctl.defaultPlace')})` : ''}`;
  else els.place.textContent = loc.isDefault ? tr('ctl.defaultPlace') : tr('ctl.noPlace');
  if (!fromResponse) return;
  const v = inputValue(sky.time && sky.time.instant);
  if (v) els.datetime.value = v;
}

async function loadSky() {
  if (skyCtl) skyCtl.abort();
  const ctl = new AbortController();
  skyCtl = ctl;
  pending.sky = true;
  setBusy();
  try {
    const data = await fetchSky({ ...state }, ctl.signal);
    if (ctl !== skyCtl) return;
    sky = data;
    clearError();
    syncControls(true);
    renderPanels(Object.keys(PANELS));
  } catch (e) {
    if (ctl !== skyCtl || e.name === 'AbortError') return;
    showError(e.kind === 'server' ? `${tr('err.prefix')}: ${e.message}` : e.message || tr('err.network'));
  } finally {
    if (ctl === skyCtl) {
      pending.sky = false;
      setBusy();
    }
  }
}

async function loadEvents() {
  if (eventsCtl) eventsCtl.abort();
  const ctl = new AbortController();
  eventsCtl = ctl;
  pending.events = true;
  setBusy();
  try {
    const data = await fetchEvents({ ...state }, ctl.signal);
    if (ctl !== eventsCtl) return;
    events = data;
    renderPanels(EVENT_PANELS);
  } catch (e) {
    if (ctl !== eventsCtl || e.name === 'AbortError') return;
    showError(e.kind === 'server' ? `${tr('err.prefix')}: ${e.message}` : e.message || tr('err.network'));
  } finally {
    if (ctl === eventsCtl) {
      pending.events = false;
      setBusy();
    }
  }
}

function loadAll() {
  loadSky();
  loadEvents();
}

// User actions: debounce, then mirror the state into the URL (history entry) and refetch.
function schedule() {
  pendingPush = true;
  syncControls();
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    debounceTimer = null;
    if (pendingPush) writeUrl(true);
    pendingPush = false;
    loadAll();
  }, DEBOUNCE_MS);
}

function currentUnix() {
  return sky && sky.time && sky.time.instant && isNum(sky.time.instant.unix) ? sky.time.instant.unix : Date.now() / 1000;
}

function setTime(unix) {
  state.t = Math.round(unix);
  state.local = null;
  state.live = false;
}

const actions = {
  pickTime(unix) {
    if (!isNum(unix)) return;
    setTime(unix);
    schedule();
  },
  pickLocation(lat, lon) {
    state.lat = lat;
    state.lon = lon;
    state.elev = null;
    schedule();
  },
};

function applyLocationInputs() {
  const latS = els.lat.value.trim();
  const lonS = els.lon.value.trim();
  const lat = num(latS);
  const lon = num(lonS);
  if (latS === '' && lonS === '') {
    state.lat = null;
    state.lon = null;
  } else if (lat === null || lon === null || Math.abs(lat) > 90 || Math.abs(lon) > 180) {
    showError(tr('err.coords'));
    return;
  } else {
    state.lat = lat;
    state.lon = lon;
  }
  state.elev = num(els.elev.value);
  clearError();
  schedule();
}

function bindControls() {
  els.form.addEventListener('submit', (e) => {
    e.preventDefault();
    applyLocationInputs();
  });
  for (const el of [els.lat, els.lon, els.elev]) el.addEventListener('change', applyLocationInputs);
  els.myLocation.addEventListener('click', () => {
    if (!navigator.geolocation) {
      showError(tr('err.geoUnavailable'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        state.lat = Math.round(pos.coords.latitude * 10000) / 10000;
        state.lon = Math.round(pos.coords.longitude * 10000) / 10000;
        state.elev = isNum(pos.coords.altitude) ? Math.round(pos.coords.altitude) : null;
        clearError();
        schedule();
      },
      (err) => showError(err && err.code === 1 ? tr('err.geoDenied') : tr('err.geoUnavailable')),
      { timeout: 10000 },
    );
  });
  els.defaultLoc.addEventListener('click', () => {
    state.lat = null;
    state.lon = null;
    state.elev = null;
    clearError();
    schedule();
  });
  els.datetime.addEventListener('change', () => {
    if (!els.datetime.value) return;
    state.local = els.datetime.value;
    state.t = null;
    state.live = false;
    schedule();
  });
  els.now.addEventListener('click', () => {
    setTime(Date.now() / 1000);
    schedule();
  });
  els.live.addEventListener('change', () => {
    state.live = els.live.checked;
    if (state.live) {
      state.t = null;
      state.local = null;
    } else {
      setTime(currentUnix());
    }
    schedule();
  });
  for (const [label, sec] of STEPS) {
    els.steps.append(h('button', { type: 'button', onclick: () => { setTime(currentUnix() + sec); schedule(); } }, label));
  }
  els.errorClose.addEventListener('click', clearError);
  window.addEventListener('popstate', () => {
    readUrl();
    clearTimeout(debounceTimer);
    pendingPush = false;
    syncControls();
    loadAll();
  });
}

function startTimers() {
  setInterval(() => {
    if (state.live && !document.hidden && !debounceTimer) loadSky();
  }, SKY_REFRESH_MS);
  setInterval(() => {
    if (state.live && !document.hidden && !debounceTimer) loadEvents();
  }, EVENTS_REFRESH_MS);
  setInterval(() => {
    if (!sky) return;
    const body = document.querySelector('#p-time .panel-body');
    if (body) timeView.tick(body, { sky, live: state.live });
  }, 1000);
}

function init() {
  applyStaticText();
  document.title = tr('app.title');
  for (const id of ['form', 'lat', 'lon', 'elev', 'datetime', 'now', 'live', 'steps', 'error', 'loading', 'place']) els[id] = $(id);
  els.errorText = $('error-text');
  els.errorClose = $('error-close');
  els.myLocation = $('my-location');
  els.defaultLoc = $('default-location');
  els.liveBadge = $('live-badge');
  readUrl();
  writeUrl(false);
  bindControls();
  syncControls();
  startTimers();
  fetchLand().then((l) => {
    land = l;
    renderPanel('map');
  });
  loadAll();
}

init();
