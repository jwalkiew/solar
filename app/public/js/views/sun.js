import { h, dl, table } from '../dom.js';
import { tr } from '../i18n.js';
import { deg, nf, unit, dms, raHms, timeOf, dur } from '../format.js';

export function render(root, { sky }) {
  const s = sky.sun;
  if (!s) {
    root.replaceChildren(h('p', { class: 'muted' }, tr('noData')));
    return;
  }
  const tw = s.twilight || {};
  const row = (key, o) => [tr(key), timeOf(o && o.dawn), timeOf(o && o.dusk)];
  const notes = [];
  if (s.alwaysAbove) notes.push(h('p', { class: 'note' }, tr('sun.alwaysAbove')));
  if (s.alwaysBelow) notes.push(h('p', { class: 'note' }, tr('sun.alwaysBelow')));
  const hz = s.horizontal || {};
  const eq = s.equatorial || {};
  root.replaceChildren(
    ...notes,
    dl([
      [tr('sun.rise'), timeOf(s.rise)],
      [tr('sun.transit'), `${timeOf(s.transit)} (${deg(s.transitAlt, 1)})`],
      [tr('sun.set'), timeOf(s.set)],
      [tr('sun.noon'), timeOf(s.solarNoon)],
      [tr('sun.dayLength'), dur(s.dayLengthSec)],
      [tr('sun.eot'), unit(s.equationOfTimeMin, 'min', 1)],
      [tr('sun.altaz'), `${deg(hz.alt)} / ${deg(hz.az)}`],
      [tr('sun.radec'), `${raHms(eq.ra, 1)} / ${dms(eq.dec)}`],
      [tr('sun.distance'), `${nf(s.distanceAu, 5)} AU`],
    ]),
    h('h3', null, tr('sun.twilight')),
    table(['', tr('sun.dawn'), tr('sun.dusk')], [
      row('sun.civil', tw.civil),
      row('sun.nautical', tw.nautical),
      row('sun.astronomical', tw.astronomical),
    ], { rowHeader: true }),
  );
}
