import { h, s, dl } from '../dom.js';
import { tr, PHASE_NAMES } from '../i18n.js';
import { DASH, deg, pct, unit, timeOf, dateTimeOf, isNum, nf } from '../format.js';

const R = Math.PI / 180;
const RAD = 50;

// Parallactic angle q (deg) from hour angle H = LST - RA, observer latitude and declination.
function parallactic(sky) {
  const m = sky.moon;
  const lstH = sky.time && sky.time.lmst;
  if (!isNum(lstH) || !m.equatorial || !isNum(m.equatorial.ra) || !isNum(m.equatorial.dec) || !sky.location || !isNum(sky.location.lat)) return null;
  const H = (lstH * 15 - m.equatorial.ra) * R;
  const phi = sky.location.lat * R;
  const d = m.equatorial.dec * R;
  return Math.atan2(Math.sin(H), Math.tan(phi) * Math.cos(d) - Math.sin(d) * Math.cos(H)) / R;
}

// The lit shape is drawn with its bright limb on the +x side, then rotated. brightLimbAngle is a position angle
// from celestial north through east; subtracting the parallactic angle gives the angle from the zenith, so the
// drawing shows the Moon as seen in the sky (zenith up). Without a parallactic angle the north-up view is used.
export function phaseSvg(sky) {
  const m = sky.moon;
  const k = isNum(m.illumination) ? Math.min(1, Math.max(0, m.illumination)) : null;
  const svg = s('svg', { viewBox: '-60 -60 120 120', class: 'moon-svg', role: 'img', 'aria-label': tr('moon.drawing') });
  svg.append(s('circle', { r: RAD, class: 'moon-dark' }));
  if (k === null) return svg;
  const q = parallactic(sky);
  const limb = isNum(m.brightLimbAngle) ? m.brightLimbAngle - (q ?? 0) : 270;
  const rot = -limb - 90;
  const rx = RAD * Math.abs(1 - 2 * k);
  let d;
  if (k >= 0.999) d = `M 0 ${-RAD} A ${RAD} ${RAD} 0 1 1 0 ${RAD} A ${RAD} ${RAD} 0 1 1 0 ${-RAD} Z`;
  else if (k <= 0.001) d = '';
  else {
    const sweep = k > 0.5 ? 1 : 0;
    d = `M 0 ${-RAD} A ${RAD} ${RAD} 0 0 1 0 ${RAD} A ${rx} ${RAD} 0 0 ${sweep} 0 ${-RAD} Z`;
  }
  if (d) svg.append(s('path', { d, class: 'moon-lit', transform: `rotate(${rot.toFixed(2)})` }));
  svg.append(s('circle', { r: RAD, class: 'moon-rim' }));
  return svg;
}

export function render(root, { sky, events }) {
  const m = sky.moon;
  if (!m) {
    root.replaceChildren(h('p', { class: 'muted' }, tr('noData')));
    return;
  }
  const hz = m.horizontal || {};
  const phases = events && Array.isArray(events.moonPhases) ? events.moonPhases : null;
  const phaseList = phases
    ? h('ul', { class: 'plain' }, ...phases.slice(0, 5).map((p) => h('li', null,
      h('span', { class: p.past ? 'muted' : '' }, `${dateTimeOf(p.time)} — ${PHASE_NAMES[p.phase] || DASH}`),
      p.past ? h('span', { class: 'badge' }, tr('moon.past')) : null)))
    : h('p', { class: 'muted' }, tr('ev.loading'));
  root.replaceChildren(
    h('div', { class: 'moon-layout' },
      phaseSvg(sky),
      dl([
        [tr('moon.phase'), PHASE_NAMES[m.phaseName] || DASH],
        [tr('moon.illumination'), pct(m.illumination, 1)],
        [tr('moon.age'), unit(m.age, 'd', 1)],
        [tr('moon.distance'), unit(m.distanceKm, 'km', 0)],
        [tr('moon.diameter'), isNum(m.angularDiameterArcsec) ? `${nf(m.angularDiameterArcsec / 60, 1)}′` : DASH],
        [tr('moon.lunation'), isNum(m.lunation) ? String(m.lunation) : DASH],
        [tr('sun.altaz'), `${deg(hz.alt)} / ${deg(hz.az)}`],
        [tr('sun.rise'), timeOf(m.rise)],
        [tr('sun.transit'), timeOf(m.transit)],
        [tr('sun.set'), timeOf(m.set)],
      ])),
    h('h3', null, tr('moon.nextPhases')),
    phaseList,
  );
}
