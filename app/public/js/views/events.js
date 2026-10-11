import { h, table } from '../dom.js';
import { tr, BODY_NAMES, PHASE_NAMES, SOLAR_TYPES, LUNAR_TYPES, LUNAR_VISIBILITY, SEASONS, APSIDES, PLANET_EVENTS, LUNAR_CONTACTS } from '../i18n.js';
import { DASH, nf, pct, unit, dateTimeOf, timeOf, hasT, isNum, deg } from '../format.js';

const name = (id) => BODY_NAMES[id] || id || DASH;

function when(T, actions) {
  if (!hasT(T)) return DASH;
  return h('button', { type: 'button', class: 'link', onclick: () => actions.pickTime(T.unix) }, dateTimeOf(T));
}

function section(title, content) {
  return h('section', { class: 'sub' }, h('h3', null, title), content);
}

function empty() {
  return h('p', { class: 'muted' }, tr('noData'));
}

function solarLocal(e) {
  const l = e.local;
  if (!l) return tr('ev.notVisible');
  if (l.visible === false) return `${tr('ev.belowHorizon')} (${deg(l.sunAlt, 1)})`;
  return h('span', null,
    `${tr('ev.visible')}: ${tr('ev.obscuration').toLowerCase()} ${pct(l.obscuration, 0)}, ${tr('ev.magnitude').toLowerCase()} ${nf(l.magnitude, 3)}`,
    h('br'),
    `${timeOf(l.start)} – ${timeOf(l.maximum)} – ${timeOf(l.end)}, ${deg(l.sunAlt, 1)}`);
}

function lunarContacts(e) {
  const c = e.contacts;
  if (!c) return DASH;
  const items = Object.keys(LUNAR_CONTACTS).filter((k) => hasT(c[k]));
  if (!items.length) return DASH;
  return h('span', null, ...items.flatMap((k, i) => [i ? h('br') : null, `${k.toUpperCase()} ${timeOf(c[k])}`]));
}

export function render(root, { events, actions }) {
  if (!events) {
    root.replaceChildren(h('p', { class: 'muted' }, tr('ev.loading')));
    return;
  }
  const ecl = events.eclipses || {};
  const solar = Array.isArray(ecl.solar) ? ecl.solar : [];
  const lunar = Array.isArray(ecl.lunar) ? ecl.lunar : [];
  const phases = Array.isArray(events.moonPhases) ? events.moonPhases : [];
  const seasons = Array.isArray(events.seasons) ? events.seasons : [];
  const apsides = Array.isArray(events.moonApsides) ? events.moonApsides : [];
  const pe = Array.isArray(events.planetEvents) ? events.planetEvents : [];
  const conj = Array.isArray(events.conjunctions) ? events.conjunctions : [];

  root.replaceChildren(
    h('p', { class: 'muted small' }, tr('ev.hint')),
    section(tr('ev.solar'), solar.length ? table(
      [tr('ev.date'), tr('ev.type'), tr('ev.gamma'), tr('ev.magnitude'), tr('ev.local')],
      solar.map((e) => [when(e.time, actions), SOLAR_TYPES[e.type] || DASH, nf(e.gamma, 3), nf(e.magnitude, 3), solarLocal(e)])) : empty()),
    section(tr('ev.lunar'), lunar.length ? table(
      [tr('ev.date'), tr('ev.type'), tr('ev.gamma'), tr('ev.magnitude'), tr('ev.penMag'), tr('ev.contacts'), tr('ev.local')],
      lunar.map((e) => [when(e.time, actions), LUNAR_TYPES[e.type] || DASH, nf(e.gamma, 3), nf(e.magnitude, 3), nf(e.penumbralMagnitude, 3), lunarContacts(e),
        e.local ? `${LUNAR_VISIBILITY[e.local.visibility] || DASH}, ${tr('ev.moonAlt').toLowerCase()} ${deg(e.local.moonAltAtMax, 1)}` : DASH])) : empty()),
    section(tr('ev.phases'), phases.length ? table(
      [tr('ev.date'), tr('ev.type')],
      phases.map((p) => [when(p.time, actions), h('span', { class: p.past ? 'muted' : '' }, PHASE_NAMES[p.phase] || DASH, p.past ? h('span', { class: 'badge' }, tr('moon.past')) : null)])) : empty()),
    section(tr('ev.seasons'), seasons.length ? table(
      [tr('ev.date'), tr('ev.event')],
      seasons.map((e) => [when(e.time, actions), SEASONS[e.type] || DASH])) : empty()),
    section(tr('ev.apsides'), apsides.length ? table(
      [tr('ev.date'), tr('ev.type'), tr('ev.distance')],
      apsides.map((e) => [when(e.time, actions), APSIDES[e.type] || DASH, unit(e.distanceKm, 'km', 0)])) : empty()),
    section(tr('ev.planets'), pe.length ? table(
      [tr('ev.date'), tr('ev.planet'), tr('ev.event'), tr('ev.elong')],
      pe.map((e) => [when(e.time, actions), name(e.planet), PLANET_EVENTS[e.type] || DASH, isNum(e.elongation) ? deg(e.elongation, 1) : DASH])) : empty()),
    section(tr('ev.conj'), conj.length ? table(
      [tr('ev.date'), tr('ev.pair'), tr('ev.separation')],
      conj.map((e) => [when(e.time, actions), `${name(e.a)} – ${name(e.b)}`, deg(e.separation, 2)])) : empty()),
  );
}
