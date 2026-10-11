import { h, s, checkbox } from '../dom.js';
import { tr, BODY_NAMES, BODY_COLORS, BODY_ORDER } from '../i18n.js';
import { isNum, nf, unixInTz, DASH } from '../format.js';

const W = 900;
const H = 380;
const M = { l: 44, r: 14, t: 12, b: 28 };
const PW = W - M.l - M.r;
const PH = H - M.t - M.b;
const ALT_MIN = -90;
const ALT_MAX = 90;
const prefs = { visible: new Set(BODY_ORDER) };

const BANDS = [
  { max: -18, cls: 'band-night', label: 'chart.night' },
  { max: -12, cls: 'band-astro', label: 'chart.astronomical' },
  { max: -6, cls: 'band-naut', label: 'chart.nautical' },
  { max: 0, cls: 'band-civil', label: 'chart.civil' },
  { max: Infinity, cls: 'band-day', label: 'chart.day' },
];
const bandOf = (alt) => BANDS.find((b) => alt < b.max) || BANDS[BANDS.length - 1];

export function render(root, ctx) {
  const { sky } = ctx;
  const c = sky.curves;
  const tzId = sky.timezone && sky.timezone.id;
  const series = c && c.series ? c.series : null;
  const first = series ? Object.values(series).find((a) => Array.isArray(a) && a.length > 1) : null;
  if (!c || !c.start || !isNum(c.start.unix) || !isNum(c.stepSec) || !first) {
    root.replaceChildren(h('p', { class: 'muted' }, tr('noData')));
    return;
  }
  const n = first.length;
  const t0 = c.start.unix;
  const step = c.stepSec;
  const t1 = Math.max(c.end && isNum(c.end.unix) ? c.end.unix : 0, t0 + (n - 1) * step);
  const X = (t) => M.l + ((t - t0) / (t1 - t0)) * PW;
  const Y = (a) => M.t + ((ALT_MAX - a) / (ALT_MAX - ALT_MIN)) * PH;

  const svg = s('svg', { viewBox: `0 0 ${W} ${H}`, class: 'chart-svg', role: 'img', 'aria-label': tr('chart.aria') });
  // twilight bands from the Sun altitude, merged into runs
  const sunS = Array.isArray(series.sun) ? series.sun : null;
  if (sunS) {
    let runStart = 0;
    let runBand = bandOf(sunS[0]);
    for (let i = 1; i <= sunS.length; i++) {
      const b = i < sunS.length ? bandOf(sunS[i]) : null;
      if (b !== runBand) {
        const xa = X(t0 + runStart * step);
        const xb = i < sunS.length ? X(t0 + i * step) : X(t0 + (sunS.length - 1) * step);
        svg.append(s('rect', { x: xa.toFixed(1), y: M.t, width: Math.max(0, xb - xa).toFixed(1), height: PH, class: runBand.cls }));
        runStart = i;
        runBand = b;
      }
    }
  }
  for (let a = -90; a <= 90; a += 30) {
    svg.append(s('line', { x1: M.l, x2: W - M.r, y1: Y(a), y2: Y(a), class: a === 0 ? 'horizon-line' : 'grid-line' }));
    svg.append(s('text', { x: M.l - 6, y: Y(a) + 4, class: 'axis-text', 'text-anchor': 'end' }, `${a}°`));
  }
  const tickStep = 3 * 3600;
  for (let t = t0; t <= t1 + 1; t += tickStep) {
    svg.append(s('line', { x1: X(t), x2: X(t), y1: M.t, y2: M.t + PH, class: 'grid-line v' }));
    svg.append(s('text', { x: X(t), y: H - 8, class: 'axis-text', 'text-anchor': 'middle' }, unixInTz(t, tzId, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })));
  }
  const ids = BODY_ORDER.filter((id) => Array.isArray(series[id]));
  for (const id of ids) {
    if (!prefs.visible.has(id)) continue;
    let d = '';
    let pen = 'M';
    series[id].forEach((a, i) => {
      if (!isNum(a)) { pen = 'M'; return; }
      d += `${pen}${X(t0 + i * step).toFixed(1)} ${Y(a).toFixed(1)}`;
      pen = 'L';
    });
    svg.append(s('path', { d, stroke: BODY_COLORS[id], class: 'curve' }));
  }
  const ref = sky.time && sky.time.instant && sky.time.instant.unix;
  if (isNum(ref) && ref >= t0 && ref <= t1) {
    svg.append(s('line', { x1: X(ref), x2: X(ref), y1: M.t, y2: M.t + PH, class: 'ref-line' }));
    svg.append(s('text', { x: X(ref) + 4, y: M.t + 12, class: 'ref-text' }, tr('chart.now')));
  }
  const hoverLine = s('line', { y1: M.t, y2: M.t + PH, class: 'hover-line', visibility: 'hidden' });
  svg.append(hoverLine);
  const overlay = s('rect', { x: M.l, y: M.t, width: PW, height: PH, fill: 'transparent', class: 'chart-overlay' });
  svg.append(overlay);

  const tip = h('div', { class: 'tooltip', hidden: true, role: 'status' });
  const wrap = h('div', { class: 'chart-wrap' }, svg, tip);

  function showAt(clientX) {
    const rect = svg.getBoundingClientRect();
    if (!rect.width) return;
    const vx = ((clientX - rect.left) / rect.width) * W;
    const t = t0 + ((vx - M.l) / PW) * (t1 - t0);
    const i = Math.max(0, Math.min(n - 1, Math.round((t - t0) / step)));
    const x = X(t0 + i * step);
    hoverLine.setAttribute('x1', x);
    hoverLine.setAttribute('x2', x);
    hoverLine.setAttribute('visibility', 'visible');
    const rows = ids.filter((id) => prefs.visible.has(id)).map((id) => h('div', null,
      h('span', { class: 'swatch', style: `background:${BODY_COLORS[id]}` }), `${BODY_NAMES[id]}: ${isNum(series[id][i]) ? nf(series[id][i], 1) + '°' : DASH}`));
    tip.replaceChildren(h('strong', null, `${tr('chart.time')}: ${unixInTz(t0 + i * step, tzId, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })}`), ...rows);
    tip.hidden = false;
    const left = (x / W) * rect.width;
    tip.style.left = `${left > rect.width / 2 ? left - tip.offsetWidth - 10 : left + 10}px`;
  }
  function hide() {
    tip.hidden = true;
    hoverLine.setAttribute('visibility', 'hidden');
  }
  overlay.addEventListener('pointermove', (e) => showAt(e.clientX));
  overlay.addEventListener('pointerleave', hide);

  const toggles = h('div', { class: 'toggles' }, ...ids.map((id) => checkbox(`chart-${id}`, BODY_NAMES[id] || id, prefs.visible.has(id), (v) => {
    if (v) prefs.visible.add(id); else prefs.visible.delete(id);
    render(root, ctx);
  })));
  const legend = h('ul', { class: 'legend' }, ...BANDS.map((b) => h('li', null, h('span', { class: `swatch ${b.cls}` }), tr(b.label))));
  root.replaceChildren(toggles, h('div', { class: 'scroll' }, h('div', { class: 'min-w' }, wrap)), legend);
}
