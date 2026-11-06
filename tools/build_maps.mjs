// Builds assets/maps/world.svg and assets/maps/thailand.svg (and config/countries.php) from Natural Earth.
// Run it only when the maps must be rebuilt; the SVG files are committed because the host has no build step.
//
//   mkdir /tmp/mapbuild && cd /tmp/mapbuild && npm i d3-geo mapshaper
//   curl -LO https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_50m_admin_0_countries.geojson
//   curl -LO https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_10m_admin_1_states_provinces.geojson
//   cp <repo>/tools/build_maps.mjs . && node build_maps.mjs <repo>
//
// Natural Earth is public domain (https://www.naturalearthdata.com/about/terms-of-use/).
import { readFileSync, writeFileSync } from 'node:fs';
import { geoArea, geoEqualEarth, geoGraticule10, geoMercator, geoPath } from 'd3-geo';
import mapshaper from 'mapshaper';

const repo = process.argv[2] || '.';
const WORLD_KEEP = 0.04;     // share of vertices kept (Visvalingam, keep-shapes so small countries survive)
const THAI_KEEP = 0.10;

async function simplify(file, keep, filterExpr, extra = '') {
  const input = readFileSync(file);
  const cmd = `-i in.json ${filterExpr ? `-filter '${filterExpr}'` : ''} ${extra} -simplify visvalingam percentage=${keep} keep-shapes -o out.json format=geojson precision=0.001`;
  const out = await mapshaper.applyCommands(cmd, { 'in.json': input });
  const data = JSON.parse(out['out.json'].toString());
  data.features.forEach(rewind);
  return data;
}
// d3 reads rings on the sphere: an exterior ring wound the wrong way means "everything except this shape".
// Mapshaper writes GeoJSON the other way round, so reverse any polygon that covers more than a hemisphere.
function rewind(feature) {
  const g = feature.geometry;
  if (!g) return;
  const fix = (poly) => (geoArea({ type: 'Polygon', coordinates: poly }) > 2 * Math.PI ? poly.map((ring) => ring.slice().reverse()) : poly);
  if (g.type === 'Polygon') g.coordinates = fix(g.coordinates);
  else if (g.type === 'MultiPolygon') g.coordinates = g.coordinates.map(fix);
}
// Kasetsart University, Bangkhen: where the arcs on the world map start and the home marker on the map of Thailand.
const ORIGIN = [100.5686, 13.8476];
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const num = (n) => Math.round(n * 10) / 10;

// Largest polygon of a feature, used to place markers for small countries (France must not use French Guiana).
function mainPart(feature) {
  const g = feature.geometry;
  if (g.type === 'Polygon') return feature;
  let best = null, bestArea = -1;
  const area = (poly) => geoPath().area({ type: 'Polygon', coordinates: poly });
  for (const poly of g.coordinates) { const a = area(poly); if (a > bestArea) { bestArea = a; best = poly; } }
  return { type: 'Feature', geometry: { type: 'Polygon', coordinates: best }, properties: feature.properties };
}

// ---------- World ----------
const SUBREGION = {
  'South-Eastern Asia': 'sea', 'Eastern Asia': 'ea', 'Southern Asia': 'sa', 'Central Asia': 'sa', 'Western Asia': 'wa',
  'Northern Europe': 'eu', 'Western Europe': 'eu', 'Eastern Europe': 'eu', 'Southern Europe': 'eu',
  'Northern America': 'na', 'Central America': 'na', 'Caribbean': 'na', 'South America': 'sam',
  'Northern Africa': 'af', 'Western Africa': 'af', 'Middle Africa': 'af', 'Eastern Africa': 'af', 'Southern Africa': 'af',
  'Australia and New Zealand': 'oc', 'Melanesia': 'oc', 'Micronesia': 'oc', 'Polynesia': 'oc',
};
// CLDR gives long official names for these; the site uses the short forms.
const SHORT_TH = { HK: 'ฮ่องกง', MO: 'มาเก๊า', MM: 'เมียนมา' };
{
  const raw = await simplify('ne_50m_admin_0_countries.geojson', WORLD_KEEP, `ISO_A2_EH !== "AQ"`);
  const feats = raw.features;
  const projection = geoEqualEarth().fitExtent([[2, 2], [958, 500]], { type: 'Sphere' });
  const path = geoPath(projection).digits(1);
  // One feature per ISO code: dependencies that share a code (Indian Ocean Ter. -> AU) keep no code.
  const best = new Map();
  for (const f of feats) {
    const code = f.properties.ISO_A2_EH;
    if (!/^[A-Z]{2}$/.test(code)) continue;
    const a = geoPath().area(f);
    if (!best.has(code) || a > best.get(code).a) best.set(code, { f, a });
  }
  const rows = []; let minX = 1e9, minY = 1e9, maxX = -1e9, maxY = -1e9;
  for (const f of feats) {
    const d = path(f);
    if (!d) continue;
    const [[x0, y0], [x1, y1]] = path.bounds(f);
    minX = Math.min(minX, x0); minY = Math.min(minY, y0); maxX = Math.max(maxX, x1); maxY = Math.max(maxY, y1);
    const code = f.properties.ISO_A2_EH;
    const owner = /^[A-Z]{2}$/.test(code) && best.get(code).f === f;
    let attrs = '';
    if (owner) {
      const [cx, cy] = path.centroid(mainPart(f));
      const small = (x1 - x0) * (y1 - y0) < 90;
      const region = SUBREGION[f.properties.SUBREGION] || '';
      attrs = ` data-code="${code}" data-name="${esc(f.properties.NAME)}" data-region="${region}" data-cx="${num(cx)}" data-cy="${num(cy)}"${small ? ' data-small="1"' : ''}`;
    }
    rows.push(`<path${attrs} d="${d}"/>`);
  }
  const pad = 4;
  const vb = [num(minX - pad), num(minY - pad), num(maxX - minX + 2 * pad), num(maxY - minY + 2 * pad)];
  const [ox, oy] = projection(ORIGIN);
  // Sphere outline and a 10° graticule sit under the countries (classes netmap__sphere / netmap__grid in network.css).
  const backdrop = `<path class="netmap__sphere" d="${path({ type: 'Sphere' })}"/>\n<path class="netmap__grid" d="${path(geoGraticule10())}"/>`;
  const svg = `<svg class="netmap__svg" xmlns="http://www.w3.org/2000/svg" viewBox="${vb.join(' ')}" data-vb="${vb.join(' ')}" data-origin="${num(ox)} ${num(oy)}" focusable="false">\n${backdrop}\n<g class="netmap__land">\n${rows.join('\n')}\n</g>\n</svg>\n`;
  writeFileSync(`${repo}/assets/maps/world.svg`, svg);
  console.log('world.svg', svg.length, 'bytes,', rows.length, 'shapes, viewBox', vb.join(' '));

  // Thai names of every country that has a code (CLDR via Intl), for the admin drop-down and the public list.
  const names = [];
  const dn = new Intl.DisplayNames(['th'], { type: 'region' });
  const dnEn = new Intl.DisplayNames(['en'], { type: 'region' });
  for (const [code, { f }] of best) {
    let th = code, en = f.properties.NAME;
    try { th = dn.of(code); en = dnEn.of(code) || en; } catch (e) { /* keep the Natural Earth name */ }
    if (SHORT_TH[code]) th = SHORT_TH[code];
    names.push([code, th, en]);
  }
  names.sort((a, b) => a[1].localeCompare(b[1], 'th'));
  const php = `<?php\n// Countries on config world map (assets/maps/world.svg): ISO 3166-1 alpha-2 => [Thai name, English name, region slug].\n// Generated by tools/build_maps.mjs (Thai names from CLDR). Edit a Thai name here if a different spelling is preferred.\nreturn [\n` +
    names.map(([c, th, en]) => ` '${c}' => ['${th.replace(/'/g, "\\'")}', '${en.replace(/'/g, "\\'")}', '${SUBREGION[best.get(c).f.properties.SUBREGION] || ''}'],`).join('\n') + '\n];\n';
  writeFileSync(`${repo}/config/countries.php`, php);
  console.log('countries.php', names.length, 'countries');
}

// ---------- Thailand ----------
{
  const raw = await simplify('ne_10m_admin_1_states_provinces.geojson', THAI_KEEP, `iso_a2 === "TH"`);
  const feats = raw.features.filter((f) => /^TH-\d\d$/.test(f.properties.iso_3166_2));
  if (feats.length !== 77) throw new Error('expected 77 provinces, got ' + feats.length);
  const col = { type: 'FeatureCollection', features: feats };
  const projection = geoMercator().fitExtent([[4, 4], [516, 876]], col);
  const path = geoPath(projection).digits(1);
  const [[x0, y0], [x1, y1]] = path.bounds(col);
  const rows = feats.sort((a, b) => a.properties.iso_3166_2.localeCompare(b.properties.iso_3166_2)).map((f) => {
    const p = f.properties;
    const [cx, cy] = path.centroid(mainPart(f));
    return `<path data-code="${p.iso_3166_2}" data-name="${esc(p.name)}" data-cx="${num(cx)}" data-cy="${num(cy)}" d="${path(f)}"/>`;
  });
  const pad = 4;
  const vb = [num(x0 - pad), num(y0 - pad), num(x1 - x0 + 2 * pad), num(y1 - y0 + 2 * pad)];
  // Neighbouring countries, drawn faintly so Thailand does not float in empty space when the map fills a wide screen.
  const near = await simplify('ne_50m_admin_0_countries.geojson', 0.25, `["MM","LA","KH","VN","MY","CN","ID"].indexOf(ISO_A2_EH) >= 0`, '-clip bbox=88,-2,114,26');
  const context = near.features.map((f) => `<path d="${path(f)}"/>`).join('\n');
  const labels = [['เมียนมา', 96.3, 19.6], ['ลาว', 102.6, 19.9], ['กัมพูชา', 104.9, 12.7], ['เวียดนาม', 107.9, 14.6], ['มาเลเซีย', 101.9, 4.6], ['อ่าวไทย', 101.2, 10.4], ['ทะเลอันดามัน', 96.9, 9.6]]
    .map(([t, lon, lat]) => { const [x, y] = projection([lon, lat]); return `<text class="netmap__label" x="${num(x)}" y="${num(y)}">${t}</text>`; }).join('\n');
  const [ox, oy] = projection(ORIGIN);
  const svg = `<svg class="netmap__svg" xmlns="http://www.w3.org/2000/svg" viewBox="${vb.join(' ')}" data-vb="${vb.join(' ')}" data-origin="${num(ox)} ${num(oy)}" focusable="false">\n<g class="netmap__context">\n${context}\n</g>\n${labels}\n<g class="netmap__land">\n${rows.join('\n')}\n</g>\n</svg>\n`;
  writeFileSync(`${repo}/assets/maps/thailand.svg`, svg);
  console.log('thailand.svg', svg.length, 'bytes,', rows.length, 'provinces, viewBox', vb.join(' '));
}
