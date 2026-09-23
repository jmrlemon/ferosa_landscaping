import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

const [inputPath, outputPath = 'resources/data/philippine-addresses.json'] = process.argv.slice(2);

if (!inputPath) {
  console.error('Usage: node scripts/generate-philippine-address-data.mjs <barangay_flat.json> [output.json]');
  process.exit(1);
}

const records = JSON.parse(await readFile(resolve(inputPath), 'utf8'));
const byCode = new Map(records.map(record => [record.psgc_id, record]));
const byName = new Map(records.map(record => [`${record.type}:${record.name}`, record]));
const childrenByParent = new Map();

for (const record of records) {
  if (!childrenByParent.has(record.parent_psgc_id)) {
    childrenByParent.set(record.parent_psgc_id, []);
  }
  childrenByParent.get(record.parent_psgc_id).push(record);
}

const METRO_MANILA_CODE = '1300000000';
const SPECIAL_GEOGRAPHIC_AREA_CODE = '1999900000';
const localityTypes = new Set([
  'municipality',
  'component_city',
  'independent_component_city',
  'highly_urbanized_city',
]);

const geographicProvinceByLocality = {
  '1430300000': 'Benguet',
  '1731500000': 'Palawan',
  '1830200000': 'Negros Occidental',
  '0330100000': 'Pampanga',
  '0331400000': 'Zambales',
  '0431200000': 'Quezon',
  '0990100000': 'Basilan',
  '0931700000': 'Zamboanga del Sur',
  '0631000000': 'Iloilo',
  '0730600000': 'Cebu',
  '0731100000': 'Cebu',
  '0731300000': 'Cebu',
  '0831600000': 'Leyte',
  '1030500000': 'Misamis Oriental',
  '1030900000': 'Lanao del Norte',
  '1130700000': 'Davao del Sur',
  '1230800000': 'South Cotabato',
  '1630400000': 'Agusan del Norte',
};

function option(record) {
  return { code: record.psgc_id, name: record.name.trim() };
}

function compareNames(left, right) {
  return left.name.localeCompare(right.name, 'en-PH');
}

function areaCodeFor(locality) {
  const parent = byCode.get(locality.parent_psgc_id);

  if (parent?.type === 'province' || parent?.type === 'special_geographic_area') {
    return parent.psgc_id;
  }

  if (parent?.psgc_id === METRO_MANILA_CODE) {
    return METRO_MANILA_CODE;
  }

  const provinceName = geographicProvinceByLocality[locality.psgc_id]
    ?? geographicProvinceByLocality[parent?.psgc_id];
  const province = provinceName ? byName.get(`province:${provinceName}`) : null;

  if (!province) {
    throw new Error(`No province/area mapping for ${locality.name} (${locality.psgc_id}).`);
  }

  return province.psgc_id;
}

function barangaysFor(locality) {
  const children = childrenByParent.get(locality.psgc_id) ?? [];
  const direct = children.filter(record => record.type === 'barangay');
  const nested = children
    .filter(record => record.type === 'submunicipality')
    .flatMap(record => childrenByParent.get(record.psgc_id) ?? [])
    .filter(record => record.type === 'barangay');

  return [...direct, ...nested].map(option).sort(compareNames);
}

const provinces = records.filter(record => record.type === 'province');
const specialArea = byCode.get(SPECIAL_GEOGRAPHIC_AREA_CODE);
const areas = [
  ...provinces.map(option),
  { code: METRO_MANILA_CODE, name: 'Metro Manila' },
  option(specialArea),
].sort(compareNames);

const localities = {};
const barangays = {};

const visibleLocalities = records.filter(record => {
  if (!localityTypes.has(record.type)) return false;

  return !(childrenByParent.get(record.psgc_id) ?? []).some(child => localityTypes.has(child.type));
});

for (const locality of visibleLocalities) {
  const areaCode = areaCodeFor(locality);
  localities[areaCode] ??= [];
  localities[areaCode].push(option(locality));
  barangays[locality.psgc_id] = barangaysFor(locality);
}

for (const options of Object.values(localities)) {
  options.sort(compareNames);
}

const barangayCount = Object.values(barangays).reduce((total, options) => total + options.length, 0);

if (areas.length !== 84 || Object.keys(barangays).length !== 1642 || barangayCount !== 42010) {
  throw new Error(`Unexpected PSGC counts: ${areas.length} areas, ${Object.keys(barangays).length} localities, ${barangayCount} barangays.`);
}

const output = {
  version: '2026-07-13',
  source: 'https://github.com/bendlikeabamboo/barangay/blob/main/barangay/data/barangay_flat.json',
  areas,
  localities,
  barangays,
};

const resolvedOutputPath = resolve(outputPath);
await mkdir(dirname(resolvedOutputPath), { recursive: true });
await writeFile(resolvedOutputPath, `${JSON.stringify(output)}\n`, 'utf8');
console.log(`Wrote ${areas.length} areas, ${Object.keys(barangays).length} localities, and ${barangayCount} barangays to ${outputPath}.`);
