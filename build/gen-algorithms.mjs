// Renders the Polish description of the algorithms (Markdown with $...$ / $$...$$ TeX) into a static page.
// Formulas are rendered at build time with KaTeX, so the page needs no JavaScript and no external URLs.
// usage: node gen-algorithms.mjs <source.md> <publicDir>
import { readFileSync, writeFileSync, mkdirSync, readdirSync, copyFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import katex from 'katex'
import { marked } from 'marked'

const [source, publicDir] = process.argv.slice(2)
if (!source || !publicDir) {
  console.error('usage: node gen-algorithms.mjs <source.md> <publicDir>')
  process.exit(1)
}

const store = []
const keep = (html) => {
  store.push(html)
  return `@@MATH${store.length - 1}@@`
}
// a malformed formula must fail the build instead of showing raw TeX
const tex = (formula, displayMode) => keep(katex.renderToString(formula, { displayMode, throwOnError: true, strict: 'error' }))

let md = readFileSync(source, 'utf8')
md = md.replace(/\$\$([\s\S]+?)\$\$/g, (m, f) => tex(f, true))
md = md.replace(/\$([^$\n]+?)\$/g, (m, f) => tex(f, false))
if (md.includes('$')) {
  throw new Error('unbalanced $ in ' + source)
}

const title = (md.match(/^# (.+)$/m) || [])[1] || 'Algorytmy'
const body = marked.parse(md.replace(/^# .+\n/m, ''), { gfm: true })
  .replace(/@@MATH(\d+)@@/g, (m, i) => store[Number(i)])
  .replace(/<table>/g, '<div class="scroll"><table class="data">')
  .replace(/<\/table>/g, '</table></div>')

const page = `<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark light">
  <title>${title} — Niebo teraz</title>
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="vendor/katex/katex.min.css">
  <link rel="stylesheet" href="css/algorithms.css">
</head>
<body>
  <header class="top">
    <div class="titles">
      <h1>${title}</h1>
      <p class="muted"><a href="./">← Niebo teraz</a></p>
    </div>
  </header>
  <main class="doc">
${body}
  </main>
</body>
</html>
`
writeFileSync(join(publicDir, 'algorytmy.html'), page)

// KaTeX stylesheet and its woff2 fonts (every current browser supports woff2)
const require = createRequire(import.meta.url)
const dist = dirname(require.resolve('katex/dist/katex.min.css'))
const vendor = join(publicDir, 'vendor', 'katex')
mkdirSync(join(vendor, 'fonts'), { recursive: true })
const css = readFileSync(join(dist, 'katex.min.css'), 'utf8')
  .replace(/,url\(fonts\/[^)]+\.(woff|ttf)\) format\("(woff|truetype)"\)/g, '')
writeFileSync(join(vendor, 'katex.min.css'), css)
let fonts = 0
for (const f of readdirSync(join(dist, 'fonts'))) {
  if (f.endsWith('.woff2')) {
    copyFileSync(join(dist, 'fonts', f), join(vendor, 'fonts', f))
    fonts++
  }
}
copyFileSync(require.resolve('katex/LICENSE'), join(vendor, 'LICENSE'))
console.log(`algorytmy.html: ${store.length} formulas, ${fonts} fonts`)
