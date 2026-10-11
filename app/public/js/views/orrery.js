import { h, s, checkbox } from '../dom.js';
import { tr, BODY_NAMES, BODY_COLORS } from '../i18n.js';
import { isNum, nf } from '../format.js';

const R = Math.PI / 180;
const prefs = { lines: true, scale: 'sqrt' };
const MAX_R = 270;
const MAX_AU = 31;
// Mean orbital radii (AU), used only to draw reference orbit circles.
const ORBITS = [['mercury', 0.387], ['venus', 0.723], ['earth', 1], ['mars', 1.524], ['jupiter', 5.203], ['saturn', 9.537], ['uranus', 19.19], ['neptune', 30.07]];

function radial(au) {
  if (prefs.scale === 'log') return (MAX_R * Math.log10(1 + au * 10)) / Math.log10(1 + MAX_AU * 10);
  return MAX_R * Math.sqrt(au / MAX_AU);
}

// Heliocentric ecliptic longitude counted counter-clockwise from +x, which points to 0 deg Aries.
function pos(lon, au) {
  const r = radial(au);
  return [r * Math.cos(lon * R), -r * Math.sin(lon * R)];
}

export function render(root, ctx) {
  const { sky } = ctx;
  const earth = sky.sun && sky.sun.earthHeliocentric;
  const V = MAX_R + 60;
  const svg = s('svg', { viewBox: `${-V} ${-V} ${V * 2} ${V * 2}`, class: 'orrery-svg', role: 'img', 'aria-label': tr('orrery.aria') });
  for (const [id, au] of ORBITS) {
    const r = radial(au);
    svg.append(s('circle', { cx: 0, cy: 0, r: r.toFixed(1), class: 'orbit' }));
    // inner orbits are too close together to label; their planets carry labels anyway
    if (au > 5) svg.append(s('text', { x: 3, y: (-r - 2).toFixed(1), class: 'orbit-label' }, BODY_NAMES[id]));
  }
  svg.append(s('line', { x1: 0, y1: 0, x2: MAX_R + 25, y2: 0, class: 'aries-line' }));
  svg.append(s('text', { x: MAX_R + 25, y: 20, class: 'aries-label', 'text-anchor': 'end' }, `♈ ${tr('orrery.aries')}`));
  svg.append(s('circle', { cx: 0, cy: 0, r: 7, fill: BODY_COLORS.sun, class: 'orrery-sun' }));

  let ex = null;
  let ey = null;
  if (earth && isNum(earth.lon) && isNum(earth.r)) {
    [ex, ey] = pos(earth.lon, earth.r);
    svg.append(s('circle', { cx: ex.toFixed(1), cy: ey.toFixed(1), r: 5, class: 'orrery-earth' }));
    svg.append(s('text', { x: (ex + 8).toFixed(1), y: (ey + 4).toFixed(1), class: 'orrery-label' }, tr('orrery.earth')));
  }
  for (const p of sky.planets || []) {
    const hc = p.heliocentric;
    if (!hc || !isNum(hc.lon) || !isNum(hc.r)) continue;
    const [x, y] = pos(hc.lon, hc.r);
    if (prefs.lines && ex !== null) svg.append(s('line', { x1: ex.toFixed(1), y1: ey.toFixed(1), x2: x.toFixed(1), y2: y.toFixed(1), stroke: BODY_COLORS[p.id], class: 'earth-line' }));
    const g = s('g');
    g.append(s('title', null, `${BODY_NAMES[p.id] || p.id}: ${nf(hc.r, 3)} AU, ${nf(hc.lon, 1)}°`));
    g.append(s('circle', { cx: x.toFixed(1), cy: y.toFixed(1), r: 5, fill: BODY_COLORS[p.id] || '#fff', class: 'orrery-planet' }));
    g.append(s('text', { x: (x + 8).toFixed(1), y: (y + 4).toFixed(1), class: 'orrery-label' }, BODY_NAMES[p.id] || p.id));
    svg.append(g);
  }
  const sel = h('select', { id: 'orrery-scale', onchange: (e) => { prefs.scale = e.target.value; render(root, ctx); } },
    h('option', { value: 'sqrt', selected: prefs.scale === 'sqrt' ? true : null }, tr('orrery.sqrt')),
    h('option', { value: 'log', selected: prefs.scale === 'log' ? true : null }, tr('orrery.log')));
  root.replaceChildren(
    h('div', { class: 'toggles' },
      checkbox('orrery-lines', tr('orrery.lines'), prefs.lines, (v) => { prefs.lines = v; render(root, ctx); }),
      h('label', { for: 'orrery-scale', class: 'inline' }, tr('orrery.scale') + ': ', sel)),
    h('div', { class: 'svg-wrap square' }, svg),
  );
}
