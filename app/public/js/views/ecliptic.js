import { h, s } from '../dom.js';
import { tr, BODY_NAMES, BODY_COLORS, ZODIAC } from '../i18n.js';
import { isNum, nf, norm360 } from '../format.js';

const W = 1000;
const H = 190;
const X0 = 10;
const SPAN = W - 20;
const BAND_Y = 70;
const BAND_H = 34;
const xOf = (lon) => X0 + (norm360(lon) / 360) * SPAN;

export function render(root, { sky }) {
  const svg = s('svg', { viewBox: `0 0 ${W} ${H}`, class: 'ecl-svg', role: 'img', 'aria-label': tr('ecl.aria') });
  ZODIAC.forEach(([name, sym], i) => {
    const x = xOf(i * 30);
    svg.append(s('rect', { x, y: BAND_Y, width: SPAN / 12, height: BAND_H, class: i % 2 ? 'zod zod-b' : 'zod zod-a' }));
    svg.append(s('text', { x: x + SPAN / 24, y: BAND_Y + 15, class: 'zod-sym', 'text-anchor': 'middle' }, sym));
    svg.append(s('text', { x: x + SPAN / 24, y: BAND_Y + 29, class: 'zod-name', 'text-anchor': 'middle' }, name));
    svg.append(s('text', { x, y: BAND_Y + BAND_H + 14, class: 'zod-deg', 'text-anchor': 'middle' }, `${i * 30}°`));
  });
  svg.append(s('text', { x: W - X0, y: BAND_Y + BAND_H + 14, class: 'zod-deg', 'text-anchor': 'end' }, '360°'));

  const items = [sky.sun, sky.moon, ...(sky.planets || [])]
    .filter((b) => b && b.ecliptic && isNum(b.ecliptic.lon))
    .sort((a, b) => a.ecliptic.lon - b.ecliptic.lon);
  const rows = 4;
  items.forEach((b, i) => {
    const x = xOf(b.ecliptic.lon);
    const up = i % 2 === 0;
    const level = Math.floor(i / 2) % (rows / 2);
    const ty = up ? BAND_Y - 14 - level * 22 : BAND_Y + BAND_H + 34 + level * 22;
    const name = BODY_NAMES[b.id] || b.id;
    svg.append(s('line', { x1: x, y1: up ? BAND_Y : BAND_Y + BAND_H, x2: x, y2: up ? ty + 3 : ty - 12, stroke: BODY_COLORS[b.id], class: 'ecl-stem' }));
    const g = s('g');
    g.append(s('title', null, `${name}: ${nf(b.ecliptic.lon, 2)}°`));
    g.append(s('circle', { cx: x, cy: up ? BAND_Y : BAND_Y + BAND_H, r: 5, fill: BODY_COLORS[b.id] || '#fff', class: 'ecl-dot' }));
    g.append(s('text', { x: Math.min(W - 30, Math.max(30, x)), y: ty, class: 'ecl-label', 'text-anchor': 'middle' }, name));
    svg.append(g);
  });
  const sun = sky.sun && sky.sun.ecliptic;
  if (sun && isNum(sun.lon)) {
    const x = xOf(sun.lon + 180);
    svg.append(s('line', { x1: x, y1: BAND_Y - 4, x2: x, y2: BAND_Y + BAND_H + 4, class: 'anti-line' }));
    svg.append(s('path', { d: `M${x - 6} ${BAND_Y + BAND_H + 10} L${x + 6} ${BAND_Y + BAND_H + 10} L${x} ${BAND_Y + BAND_H + 2} Z`, class: 'anti-mark' }));
    svg.append(s('text', { x, y: 16, class: 'anti-label', 'text-anchor': x > W - 140 ? 'end' : x < 140 ? 'start' : 'middle' }, `${tr('ecl.antisun')} (${nf(norm360(sun.lon + 180), 1)}°)`));
    svg.append(s('line', { x1: x, y1: 20, x2: x, y2: BAND_Y - 4, class: 'anti-line' }));
  }
  root.replaceChildren(h('div', { class: 'scroll' }, h('div', { class: 'min-w' }, svg)));
}
