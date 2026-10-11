const SVG_NS = 'http://www.w3.org/2000/svg';

function apply(node, attrs, children) {
  if (attrs) {
    for (const [k, v] of Object.entries(attrs)) {
      if (v === null || v === undefined || v === false) continue;
      if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
      else if (k === 'text') node.textContent = v;
      else node.setAttribute(k, v === true ? '' : String(v));
    }
  }
  for (const c of children.flat()) {
    if (c === null || c === undefined || c === false) continue;
    node.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
  return node;
}

export function h(tag, attrs, ...children) {
  return apply(document.createElement(tag), attrs, children);
}

export function s(tag, attrs, ...children) {
  return apply(document.createElementNS(SVG_NS, tag), attrs, children);
}

export function clear(node) {
  node.replaceChildren();
  return node;
}

export function table(headers, rows, opts = {}) {
  const head = h('tr', null, ...headers.map((x) => h('th', { scope: 'col' }, x)));
  const body = rows.map((r) => h('tr', null, ...r.map((c, i) => (i === 0 && opts.rowHeader ? h('th', { scope: 'row' }, c) : h('td', null, c)))));
  const t = h('table', { class: opts.class || 'data' }, h('thead', null, head), h('tbody', null, ...body));
  return h('div', { class: 'scroll' }, t);
}

export function dl(pairs) {
  return h('dl', { class: 'kv' }, ...pairs.flatMap(([k, v]) => [h('dt', null, k), h('dd', null, v)]));
}

export function checkbox(id, label, checked, onChange) {
  return h('label', { class: 'check', for: id },
    h('input', { type: 'checkbox', id, checked: checked ? true : null, onchange: (e) => onChange(e.target.checked) }),
    label);
}
