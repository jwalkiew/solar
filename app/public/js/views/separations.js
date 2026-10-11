import { h } from '../dom.js';
import { tr, BODY_NAMES, BODY_ORDER } from '../i18n.js';
import { DASH, nf, isNum } from '../format.js';

const short = (id) => BODY_NAMES[id] || id;

export function render(root, { sky }) {
  const list = Array.isArray(sky.separations) ? sky.separations : [];
  if (!list.length) {
    root.replaceChildren(h('p', { class: 'muted' }, tr('noData')));
    return;
  }
  const map = new Map();
  for (const p of list) {
    map.set(`${p.a}|${p.b}`, p.deg);
    map.set(`${p.b}|${p.a}`, p.deg);
  }
  const ids = BODY_ORDER.filter((id) => list.some((p) => p.a === id || p.b === id));
  const head = h('tr', null, h('td', null), ...ids.map((id) => h('th', { scope: 'col' }, short(id))));
  const rows = ids.map((a) => h('tr', null, h('th', { scope: 'row' }, short(a)),
    ...ids.map((b) => {
      if (a === b) return h('td', { class: 'diag' }, DASH);
      const v = map.get(`${a}|${b}`);
      const close = isNum(v) && v < 5;
      return h('td', { class: close ? 'close' : '' }, isNum(v) ? `${nf(v, 1)}°` : DASH, close ? h('span', { class: 'sr-only' }, ' < 5°') : null);
    })));
  root.replaceChildren(
    h('p', { class: 'muted small' }, tr('sep.note')),
    h('div', { class: 'scroll' }, h('table', { class: 'data matrix', 'aria-label': tr('sep.aria') }, h('thead', null, head), h('tbody', null, ...rows))),
  );
}
