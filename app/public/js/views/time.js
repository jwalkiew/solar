import { h, dl } from '../dom.js';
import { tr } from '../i18n.js';
import { DASH, nf, unit, hms, dateTimeOf, longDateOf, offsetOf, clockInTz, isNum } from '../format.js';

export function render(root, { sky, live }) {
  const tm = sky.time || {};
  const tz = sky.timezone || {};
  const inst = tm.instant || null;
  const zone = [tz.id, tz.abbr].filter(Boolean).join(' · ') || DASH;
  const dst = tz.dst === true ? tr('time.dst') : tz.dst === false ? tr('time.std') : '';
  const clock = h('div', { class: 'clock', id: 'clock', role: 'timer', 'aria-label': tr('time.clock') }, DASH);
  root.replaceChildren(
    h('div', { class: 'clock-wrap' }, h('div', { class: 'clock-label' }, tr('time.clock')), clock,
      h('div', { class: 'muted', id: 'clock-date' }, longDateOf(inst))),
    dl([
      [tr('time.utc'), inst && inst.utc ? inst.utc.replace('T', ' ').replace('Z', '') : DASH],
      [tr('time.local'), `${dateTimeOf(inst, true)} (UTC${offsetOf(inst)})`],
      [tr('time.zone'), [zone, dst].filter(Boolean).join(', ')],
      [tr('time.unix'), isNum(inst && inst.unix) ? String(Math.round(inst.unix)) : DASH],
      [tr('time.jd'), nf(inst && inst.jd, 6)],
      [tr('time.mjd'), nf(tm.mjd, 6)],
      [tr('time.jde'), nf(tm.jde, 6)],
      [tr('time.deltaT'), unit(tm.deltaT, 's', 1)],
      [tr('time.gmst'), hms(tm.gmst, 1)],
      [tr('time.gast'), hms(tm.gast, 1)],
      [tr('time.lmst'), hms(tm.lmst, 1)],
      [tr('time.last'), hms(tm.last, 1)],
      [tr('time.doy'), isNum(tm.dayOfYear) ? String(tm.dayOfYear) : DASH],
      [tr('time.week'), isNum(tm.isoWeek) ? String(tm.isoWeek) : DASH],
      [tr('time.julianDate'), tm.julianCalendarDate || DASH],
      [tr('time.julianEpoch'), nf(tm.julianEpoch, 4)],
    ]),
  );
  tick(root, { sky, live });
}

// Called every second by the app; live mode shows the running wall clock in the location's zone.
export function tick(root, { sky, live }) {
  const el = root.querySelector('#clock');
  if (!el) return;
  const tzId = sky.timezone && sky.timezone.id;
  const inst = sky.time && sky.time.instant;
  if (!tzId) {
    el.textContent = DASH;
    return;
  }
  const unix = live ? Date.now() / 1000 : inst && inst.unix;
  el.textContent = clockInTz(unix, tzId);
}
