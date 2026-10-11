import { LOCALE } from './i18n.js';

export const DASH = '—';

export function isNum(v) {
  return typeof v === 'number' && Number.isFinite(v);
}

const nfCache = new Map();
function nfFor(d) {
  if (!nfCache.has(d)) nfCache.set(d, new Intl.NumberFormat(LOCALE, { minimumFractionDigits: d, maximumFractionDigits: d }));
  return nfCache.get(d);
}

export function nf(v, d = 2) {
  return isNum(v) ? nfFor(d).format(v).replace('-', '−') : DASH;
}

export function signed(v, d = 2) {
  if (!isNum(v)) return DASH;
  return (v > 0 ? '+' : '') + nf(v, d);
}

export function deg(v, d = 2) {
  return isNum(v) ? nf(v, d) + '°' : DASH;
}

export function pct(v, d = 0) {
  return isNum(v) ? nf(v * 100, d) + ' %' : DASH;
}

export function unit(v, u, d = 2) {
  return isNum(v) ? nf(v, d) + ' ' + u : DASH;
}

function pad(n) {
  return String(n).padStart(2, '0');
}

// Hours -> "20h 27m 24s" (wrapped into 0..24).
export function hms(hours, secDecimals = 0) {
  if (!isNum(hours)) return DASH;
  const k = 10 ** secDecimals;
  let total = Math.round((((hours % 24) + 24) % 24) * 3600 * k);
  const max = 24 * 3600 * k;
  if (total >= max) total -= max;
  const sec = total / k;
  const hh = Math.floor(sec / 3600);
  const mm = Math.floor((sec % 3600) / 60);
  const ss = sec % 60;
  return `${hh}h ${pad(mm)}m ${secDecimals ? nf(ss, secDecimals) : pad(ss)}s`;
}

export function raHms(raDeg, secDecimals = 0) {
  return isNum(raDeg) ? hms(raDeg / 15, secDecimals) : DASH;
}

// Signed degrees -> "+12° 34′ 56″"
export function dms(v) {
  if (!isNum(v)) return DASH;
  const sign = v < 0 ? '−' : '+';
  const total = Math.round(Math.abs(v) * 3600);
  const d = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  return `${sign}${d}° ${pad(m)}′ ${pad(total % 60)}″`;
}

export function dur(sec) {
  if (!isNum(sec)) return DASH;
  const m = Math.round(sec / 60);
  return `${Math.floor(m / 60)} h ${pad(m % 60)} min`;
}

// Location-local wall clock is read from the T.local string, never from the browser zone.
export function hasT(t) {
  return !!t && typeof t.local === 'string' && t.local.length >= 16;
}

export function timeOf(t, withSeconds = false) {
  if (!hasT(t)) return DASH;
  return t.local.slice(11, withSeconds ? 19 : 16);
}

export function dateOf(t) {
  if (!hasT(t)) return DASH;
  const l = t.local;
  return `${l.slice(8, 10)}.${l.slice(5, 7)}.${l.slice(0, 4)}`;
}

export function dateTimeOf(t, withSeconds = false) {
  return hasT(t) ? `${dateOf(t)} ${timeOf(t, withSeconds)}` : DASH;
}

const longDateFmt = new Intl.DateTimeFormat(LOCALE, { timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// Weekday/month names from the local calendar date; the date is treated as UTC only to feed Intl.
export function longDateOf(t) {
  if (!hasT(t)) return DASH;
  const l = t.local;
  const ms = Date.UTC(+l.slice(0, 4), +l.slice(5, 7) - 1, +l.slice(8, 10));
  return Number.isFinite(ms) ? longDateFmt.format(new Date(ms)) : DASH;
}

export function offsetOf(t) {
  return hasT(t) && t.local.length > 19 ? t.local.slice(19) : DASH;
}

export function inputValue(t) {
  return hasT(t) ? t.local.slice(0, 16) : '';
}

const tzFmtCache = new Map();
export function tzFormatter(tzId, opts) {
  const key = tzId + JSON.stringify(opts);
  if (!tzFmtCache.has(key)) {
    let f;
    try {
      f = new Intl.DateTimeFormat(LOCALE, { timeZone: tzId, ...opts });
    } catch {
      f = new Intl.DateTimeFormat(LOCALE, { timeZone: 'UTC', ...opts });
    }
    tzFmtCache.set(key, f);
  }
  return tzFmtCache.get(key);
}

export function unixInTz(unix, tzId, opts) {
  if (!isNum(unix)) return DASH;
  return tzFormatter(tzId, opts).format(new Date(unix * 1000));
}

export function clockInTz(unix, tzId) {
  return unixInTz(unix, tzId, { hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
}

export function norm360(x) {
  return ((x % 360) + 360) % 360;
}
