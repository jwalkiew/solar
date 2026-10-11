import { h, s, checkbox } from '../dom.js';
import { tr, BODY_NAMES, BODY_COLORS, BODY_ORDER } from '../i18n.js';
import { isNum, nf, norm360 } from '../format.js';

const R = Math.PI / 180;
const prefs = { below: true, ecliptic: false };
const CX = 0;
const CY = 0;
const RAD = 200;
const MIN_ALT = -30;

// Sky seen looking up: zenith at the centre, north up, east on the left (as on a planisphere).
function project(alt, az) {
  const a = Math.max(MIN_ALT, Math.min(90, alt));
  const r = (RAD * (90 - a)) / 90;
  return [CX - r * Math.sin(az * R), CY - r * Math.cos(az * R)];
}

function radius(b) {
  if (b.id === 'sun') return 10;
  if (b.id === 'moon') return 9;
  if (!isNum(b.magnitude)) return 4;
  return Math.max(3, Math.min(8, 5.5 - b.magnitude * 0.9));
}

function eclipticPath(sky) {
  const eps = 23.4393;
  const lst = sky.time && sky.time.lmst;
  const lat = sky.location && sky.location.lat;
  if (!isNum(lst) || !isNum(lat)) return null;
  const pts = [];
  for (let l = 0; l <= 360; l += 3) {
    const sl = Math.sin(l * R);
    const ra = Math.atan2(sl * Math.cos(eps * R), Math.cos(l * R));
    const dec = Math.asin(Math.sin(eps * R) * sl);
    const H = lst * 15 * R - ra;
    const sa = Math.sin(lat * R) * Math.sin(dec) + Math.cos(lat * R) * Math.cos(dec) * Math.cos(H);
    const alt = Math.asin(sa) / R;
    const az = norm360(Math.atan2(-Math.cos(dec) * Math.sin(H), Math.sin(dec) * Math.cos(lat * R) - Math.cos(dec) * Math.cos(H) * Math.sin(lat * R)) / R);
    pts.push({ alt, az });
  }
  const segs = [];
  let cur = [];
  for (const p of pts) {
    if (p.alt >= 0) cur.push(project(p.alt, p.az));
    else if (cur.length) { segs.push(cur); cur = []; }
  }
  if (cur.length) segs.push(cur);
  return segs.map((seg) => 'M' + seg.map((q) => `${q[0].toFixed(1)} ${q[1].toFixed(1)}`).join('L')).join(' ');
}

export function render(root, ctx) {
  const { sky } = ctx;
  const bodies = [sky.sun, sky.moon, ...(sky.planets || [])].filter((b) => b && b.horizontal && isNum(b.horizontal.alt) && isNum(b.horizontal.az));
  bodies.sort((a, b) => BODY_ORDER.indexOf(a.id) - BODY_ORDER.indexOf(b.id));
  const V = RAD * 1.7;
  const svg = s('svg', { viewBox: `${-V} ${-V} ${V * 2} ${V * 2}`, class: 'dome-svg', role: 'img', 'aria-label': tr('dome.aria') });
  svg.append(s('circle', { cx: CX, cy: CY, r: RAD, class: 'dome-sky' }));
  for (const alt of [60, 30]) {
    svg.append(s('circle', { cx: CX, cy: CY, r: (RAD * (90 - alt)) / 90, class: 'dome-ring' }));
    svg.append(s('text', { x: 4, y: -(RAD * (90 - alt)) / 90 - 3, class: 'dome-ringlabel' }, `${alt}°`));
  }
  svg.append(s('line', { x1: 0, y1: -RAD, x2: 0, y2: RAD, class: 'dome-ring' }));
  svg.append(s('line', { x1: -RAD, y1: 0, x2: RAD, y2: 0, class: 'dome-ring' }));
  svg.append(s('circle', { cx: CX, cy: CY, r: RAD, class: 'dome-horizon' }));
  for (const [label, az] of [['N', 0], ['E', 90], ['S', 180], ['W', 270]]) {
    const [x, y] = project(0, az);
    svg.append(s('text', { x: x * 1.1, y: y * 1.1 + 6, class: 'dome-cardinal', 'text-anchor': 'middle' }, label));
  }
  if (prefs.ecliptic) {
    const d = eclipticPath(sky);
    if (d) svg.append(s('path', { d, class: 'dome-ecliptic' }));
  }
  for (const b of bodies) {
    const { alt, az } = b.horizontal;
    const below = alt < 0;
    if (below && !prefs.below) continue;
    const [x, y] = project(alt, az);
    const r = radius(b);
    const name = BODY_NAMES[b.id] || b.id;
    const g = s('g', { class: below ? 'dome-body below' : 'dome-body' });
    g.append(s('title', null, `${name}: ${nf(alt, 1)}° / ${nf(az, 1)}°`));
    g.append(s('circle', { cx: x.toFixed(1), cy: y.toFixed(1), r, fill: BODY_COLORS[b.id] || '#fff', class: b.id === 'sun' ? 'dome-sun' : b.id === 'moon' ? 'dome-moon' : 'dome-planet' }));
    g.append(s('text', { x: (x + r + 3).toFixed(1), y: (y + 4).toFixed(1), class: 'dome-label' }, name));
    svg.append(g);
  }
  root.replaceChildren(
    h('div', { class: 'toggles' },
      checkbox('dome-below', tr('dome.showBelow'), prefs.below, (v) => { prefs.below = v; render(root, ctx); }),
      checkbox('dome-ecl', tr('dome.ecliptic'), prefs.ecliptic, (v) => { prefs.ecliptic = v; render(root, ctx); })),
    h('div', { class: 'svg-wrap square' }, svg),
  );
}
