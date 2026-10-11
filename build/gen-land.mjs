// Converts Natural Earth 1:110m land (world-atlas, TopoJSON) into a compact GeoJSON
// MultiPolygon for the world map in the browser.
// usage: node gen-land.mjs <outFile>
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname } from 'node:path'
import { feature } from 'topojson-client'

const outFile = process.argv[2]
if (!outFile) {
  console.error('usage: node gen-land.mjs <outFile>')
  process.exit(1)
}

const require = createRequire(import.meta.url)
const topo = JSON.parse(readFileSync(require.resolve('world-atlas/land-110m.json'), 'utf8'))
const land = feature(topo, topo.objects.land)

const round = (v) => Math.round(v * 100) / 100

// rings crossing the antimeridian jump by ~360 deg of longitude; make them continuous so an equirectangular
// renderer draws no stripe across the map; a ring winding around a pole is closed through that pole
function unwrap (ring) {
  const out = [[ring[0][0], ring[0][1]]]
  let shift = 0
  for (let i = 1; i < ring.length; i++) {
    const d = ring[i][0] - ring[i - 1][0]
    if (d > 180) shift -= 360
    else if (d < -180) shift += 360
    out.push([ring[i][0] + shift, ring[i][1]])
  }
  if (shift !== 0) {
    const pole = out.reduce((s, p) => s + p[1], 0) < 0 ? -90 : 90
    const last = out[out.length - 1][0]
    out.push([last, pole], [out[0][0], pole])
  }
  return out
}

const polygons = []
for (const f of land.features) {
  const g = f.geometry
  const list = g.type === 'Polygon' ? [g.coordinates] : g.coordinates
  for (const poly of list) {
    const rings = poly.map(unwrap)
    const lons = rings.flat().map((p) => p[0])
    const offsets = [0]
    if (Math.max(...lons) > 180) offsets.push(-360)
    if (Math.min(...lons) < -180) offsets.push(360)
    for (const off of offsets) {
      polygons.push(rings.map((ring) => ring.map(([lon, lat]) => [round(lon + off), round(lat)])))
    }
  }
}

const out = {
  type: 'Feature',
  properties: { source: 'Natural Earth 1:110m land via world-atlas 2.0.2 (public domain / ISC)' },
  geometry: { type: 'MultiPolygon', coordinates: polygons }
}

mkdirSync(dirname(outFile), { recursive: true })
writeFileSync(outFile, JSON.stringify(out))
console.log(`land.json: ${polygons.length} polygons`)
