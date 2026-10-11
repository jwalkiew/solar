import { h, table } from '../dom.js';
import { tr, BODY_NAMES } from '../i18n.js';
import { DASH, deg, nf, pct, signed, dms, raHms, timeOf, isNum } from '../format.js';

export function isVisibleNow(body, sun) {
  const alt = body && body.horizontal && body.horizontal.alt;
  const sunAlt = sun && sun.horizontal && sun.horizontal.alt;
  return isNum(alt) && isNum(sunAlt) && alt > 0 && sunAlt < -6;
}

function elongText(v) {
  if (!isNum(v)) return DASH;
  if (v === 0) return deg(0, 1);
  return `${nf(Math.abs(v), 1)}° ${v > 0 ? 'E' : 'W'}`;
}

export function render(root, { sky }) {
  const list = Array.isArray(sky.planets) ? sky.planets : [];
  if (!list.length) {
    root.replaceChildren(h('p', { class: 'muted' }, tr('noData')));
    return;
  }
  const rows = list.map((p) => {
    const eq = p.equatorial || {};
    const hz = p.horizontal || {};
    const name = h('span', null, BODY_NAMES[p.id] || p.id,
      isVisibleNow(p, sky.sun) ? h('span', { class: 'badge ok' }, tr('pl.visible')) : null);
    return [
      name,
      `${raHms(eq.ra, 0)} / ${dms(eq.dec)}`,
      `${deg(hz.alt, 1)} / ${deg(hz.az, 1)}`,
      nf(p.distanceAu, 3),
      isNum(p.lightTimeMin) ? `${nf(p.lightTimeMin, 1)} min` : DASH,
      isNum(p.magnitude) ? signed(p.magnitude, 1) : DASH,
      elongText(p.elongation),
      pct(p.illuminated, 0),
      isNum(p.angularDiameterArcsec) ? `${nf(p.angularDiameterArcsec, 1)}″` : DASH,
      timeOf(p.rise),
      timeOf(p.transit),
      timeOf(p.set),
    ];
  });
  root.replaceChildren(
    table([tr('pl.body'), tr('pl.radec'), tr('pl.altaz'), tr('pl.dist'), tr('pl.light'), tr('pl.mag'), tr('pl.elong'),
      tr('pl.illum'), tr('pl.diam'), tr('pl.rise'), tr('pl.transit'), tr('pl.set')], rows, { rowHeader: true, class: 'data wide' }),
    h('p', { class: 'muted small' }, `${tr('pl.east')}; ${tr('pl.west')}.`),
  );
}
