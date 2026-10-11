import { h, s } from '../dom.js';
import { tr } from '../i18n.js';
import { isNum, nf } from '../format.js';

const R = Math.PI / 180;
const W = 720;
const HT = 360;
const X = (lon) => (lon + 180) * 2;
const Y = (lat) => (90 - lat) * 2;
const LAYERS = [0, -6, -12, -18];

function ringPath(ring) {
  let d = '';
  ring.forEach((p, i) => {
    if (!Array.isArray(p) || !isNum(p[0]) || !isNum(p[1])) return;
    d += `${i === 0 ? 'M' : 'L'}${X(p[0]).toFixed(1)} ${Y(p[1]).toFixed(1)}`;
  });
  return d + 'Z';
}

function geometryPaths(g, out) {
  if (!g) return;
  if (g.type === 'Polygon') out.push(g.coordinates.map(ringPath).join(''));
  else if (g.type === 'MultiPolygon') for (const poly of g.coordinates) out.push(poly.map(ringPath).join(''));
  else if (g.type === 'GeometryCollection') for (const x of g.geometries || []) geometryPaths(x, out);
}

// Accepts Feature, FeatureCollection or bare geometry.
export function landPath(land) {
  const out = [];
  try {
    if (!land) return '';
    if (land.type === 'FeatureCollection') for (const f of land.features || []) geometryPaths(f.geometry, out);
    else if (land.type === 'Feature') geometryPaths(land.geometry, out);
    else geometryPaths(land, out);
  } catch {
    return '';
  }
  return out.join('');
}

// Sun altitude for each cell from the great-circle relation
// sin(alt) = sin(lat) sin(dec) + cos(lat) cos(dec) cos(dLon), with (dec, lon) = subsolar point.
// For each threshold (0, -6, -12, -18 deg) the cells below it are drawn as merged vertical runs; stacked layers
// give the day/civil/nautical/astronomical/night gradation. Each layer is one path: overlapping columns inside a
// single path are painted once, so the translucent fill shows no seams.
function nightPaths(sub) {
  const sd = Math.sin(sub.lat * R);
  const cd = Math.cos(sub.lat * R);
  const d = LAYERS.map(() => '');
  const latStep = 1;
  for (let i = 0; i < 360; i++) {
    const lon = i - 180 + 0.5;
    const cdl = Math.cos((lon - sub.lon) * R);
    const runs = LAYERS.map(() => []);
    const open = LAYERS.map(() => -1);
    for (let j = 0; j <= 180; j++) {
      const lat = 90 - j * latStep - 0.5;
      const alt = j === 180 ? 99 : Math.asin(Math.max(-1, Math.min(1, Math.sin(lat * R) * sd + Math.cos(lat * R) * cd * cdl))) / R;
      LAYERS.forEach((th, k) => {
        if (alt < th && j < 180) { if (open[k] < 0) open[k] = j; }
        else if (open[k] >= 0) { runs[k].push([open[k], j]); open[k] = -1; }
      });
    }
    LAYERS.forEach((th, k) => {
      for (const [a, b] of runs[k]) d[k] += `M${i * 2} ${a * 2}h2.2v${(b - a) * 2}h-2.2Z`;
    });
  }
  return d.filter(Boolean).map((p) => s('path', { d: p, class: 'night' }));
}

function marker(cls, lon, lat, label) {
  const g = s('g', { class: cls });
  g.append(s('title', null, label));
  g.append(s('circle', { cx: X(lon).toFixed(1), cy: Y(lat).toFixed(1), r: 5 }));
  return g;
}

export function render(root, { sky, land, actions }) {
  const svg = s('svg', { viewBox: `0 0 ${W} ${HT}`, class: 'map-svg', role: 'img', 'aria-label': tr('map.aria') });
  svg.append(s('rect', { x: 0, y: 0, width: W, height: HT, class: 'map-ocean' }));
  const d = landPath(land);
  if (d) svg.append(s('path', { d, class: 'map-land' }));
  for (let lon = -180; lon <= 180; lon += 30) svg.append(s('line', { x1: X(lon), x2: X(lon), y1: 0, y2: HT, class: 'map-grid' }));
  for (let lat = -60; lat <= 60; lat += 30) svg.append(s('line', { x1: 0, x2: W, y1: Y(lat), y2: Y(lat), class: lat === 0 ? 'map-grid eq' : 'map-grid' }));
  const sub = sky.sun && sky.sun.subsolar;
  if (sub && isNum(sub.lat) && isNum(sub.lon)) {
    const g = s('g', { class: 'night-layer' });
    g.append(...nightPaths(sub));
    svg.append(g);
    svg.append(marker('mk-sun', sub.lon, sub.lat, tr('map.subsolar')));
  }
  const sm = sky.moon && sky.moon.sublunar;
  if (sm && isNum(sm.lat) && isNum(sm.lon)) svg.append(marker('mk-moon', sm.lon, sm.lat, tr('map.sublunar')));
  const loc = sky.location;
  if (loc && isNum(loc.lat) && isNum(loc.lon)) {
    const g = marker('mk-obs', loc.lon, loc.lat, tr('map.observer'));
    g.append(s('line', { x1: X(loc.lon) - 9, x2: X(loc.lon) + 9, y1: Y(loc.lat), y2: Y(loc.lat) }));
    g.append(s('line', { x1: X(loc.lon), x2: X(loc.lon), y1: Y(loc.lat) - 9, y2: Y(loc.lat) + 9 }));
    svg.append(g);
  }
  svg.addEventListener('click', (e) => {
    const pt = svg.createSVGPoint();
    pt.x = e.clientX;
    pt.y = e.clientY;
    const ctm = svg.getScreenCTM();
    if (!ctm) return;
    const p = pt.matrixTransform(ctm.inverse());
    const lon = Math.max(-180, Math.min(180, p.x / 2 - 180));
    const lat = Math.max(-90, Math.min(90, 90 - p.y / 2));
    actions.pickLocation(Math.round(lat * 100) / 100, Math.round(lon * 100) / 100);
  });
  const legend = h('ul', { class: 'legend' },
    h('li', null, h('span', { class: 'swatch mk-sun-sw' }), tr('map.subsolar')),
    h('li', null, h('span', { class: 'swatch mk-moon-sw' }), tr('map.sublunar')),
    h('li', null, h('span', { class: 'swatch mk-obs-sw' }), `${tr('map.observer')} (${nf(loc && loc.lat, 2)}°, ${nf(loc && loc.lon, 2)}°)`));
  root.replaceChildren(h('p', { class: 'muted small' }, tr('map.hint')), h('div', { class: 'svg-wrap' }, svg), legend);
}
