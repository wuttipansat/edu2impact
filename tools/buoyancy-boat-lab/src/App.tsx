import { type CSSProperties, useEffect, useMemo, useRef, useState } from 'react';
import {
  Download,
  FlaskConical,
  Info,
  Plus,
  RotateCcw,
  Scale,
  Trash2,
  Waves,
} from 'lucide-react';
import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import BoatPartsPanel, { BoatPartsVisual } from './components/BoatPartsPanel';
import { siteRoot } from './lib/basePath';
import { calculateParts, defaultPart, flotationQuantity, partOptions, type BoatPart, type BoatPartId, type Material } from './lib/boatParts';

type HullShape = 'tray' | 'box' | 'canoe' | 'cylinder' | 'barge' | 'catamaran' | 'pontoon' | 'triangular';
type Status = 'float' | 'overflow' | 'sink';
type WaterImpact = { id: number; strength: 'light' | 'heavy' };
type MaterialMix = { material: Material; share: number };
type LiquidId = 'freshwater' | 'seawater' | 'vegetable-oil' | 'machine-oil' | 'alcohol' | 'glycerin' | 'syrup' | 'honey' | 'mercury';

type Experiment = {
  id: number;
  recordedAt: string;
  material: Material;
  materialMix?: MaterialMix[];
  parts?: BoatPart[];
  shape: HullShape;
  length: number;
  width: number;
  height: number;
  thickness: number;
  liquid: LiquidId;
  liquidDensity: number;
  totalMass: number;
  totalDensity: number;
  submerged: number;
  emerged: number;
  displacedVolume: number;
  submergedPercent: number;
  aboveWaterWeight: number;
  underwaterWeight: number;
  status: Status;
};

const materials: { id: Material; label: string; density: number; color: string }[] = [
  { id: 'wood', label: 'ไม้', density: 650, color: '#b9854a' },
  { id: 'aluminum', label: 'อะลูมิเนียม', density: 2700, color: '#a8bfc0' },
  { id: 'plastic', label: 'พลาสติก', density: 950, color: '#d86452' },
  { id: 'steel', label: 'เหล็ก', density: 7850, color: '#536b77' },
  { id: 'paper', label: 'กระดาษ', density: 800, color: '#e7d4ad' },
];
const liquids: { id: LiquidId; label: string; density: number; color: string }[] = [
  { id: 'freshwater', label: 'น้ำจืด', density: 1000, color: '#4dbfc8' },
  { id: 'seawater', label: 'น้ำทะเล', density: 1025, color: '#246c94' },
  { id: 'vegetable-oil', label: 'น้ำมันพืช', density: 920, color: '#b59b45' },
  { id: 'alcohol', label: 'แอลกอฮอล์', density: 789, color: '#aab2ad' },
  { id: 'glycerin', label: 'กลีเซอรีน', density: 1260, color: '#aba397' },
  { id: 'syrup', label: 'น้ำเชื่อม', density: 1300, color: '#894a42' },
  { id: 'honey', label: 'น้ำผึ้ง', density: 1420, color: '#c2852d' },
  { id: 'mercury', label: 'ปรอท', density: 13534, color: '#a1abb3' },
];
// Keep removed liquids available only to read and label previously saved experiments.
const archivedLiquids: { id: LiquidId; label: string; density: number }[] = [
  { id: 'machine-oil', label: 'น้ำมันเครื่อง', density: 850 },
];
const knownLiquids = [...liquids, ...archivedLiquids];

const shapes: { id: HullShape; label: string }[] = [
  { id: 'tray', label: 'ถาด' },
  { id: 'box', label: 'กล่อง' },
  { id: 'canoe', label: 'เรือแคนู' },
  { id: 'cylinder', label: 'ทรงกระบอก' },
  { id: 'barge', label: 'เรือท้องแบน' },
  { id: 'catamaran', label: 'เรือสองท้อง' },
  { id: 'pontoon', label: 'เรือแพ' },
  { id: 'triangular', label: 'เรือสามเหลี่ยม' },
];

const shapeFactor: Record<HullShape, number> = {
  tray: 0.84,
  box: 0.94,
  canoe: 0.67,
  cylinder: 0.72,
  barge: 0.98,
  catamaran: 0.78,
  pontoon: 0.74,
  triangular: 0.58,
};
const materialLabel = Object.fromEntries(materials.map((item) => [item.id, item.label])) as Record<Material, string>;
const liquidLabel = Object.fromEntries(knownLiquids.map((item) => [item.id, item.label])) as Record<LiquidId, string>;
const shapeLabel = Object.fromEntries(shapes.map((item) => [item.id, item.label])) as Record<HullShape, string>;
const initialForm = {
  materialMix: [{ material: 'wood' as Material, share: 100 }],
  parts: [] as BoatPart[],
  liquid: 'freshwater' as LiquidId,
  shape: 'tray' as HullShape,
  length: 42,
  width: 25,
  height: 14,
  thickness: 1.2,
  cargoMass: 2.4,
  massStep: 0.5,
};

const fmt = (value: number, digits = 2) => Number.isFinite(value) ? value.toFixed(digits) : '0.00';
const fmtDimension = (value: number) => Number.isFinite(value) ? value.toLocaleString('th-TH', { maximumFractionDigits: 2 }) : '0';
// Keep extreme user-entered dimensions inside the basin while preserving visible relative changes.
const visualRatio = (value: number, baseline: number, min: number, max: number) =>
  Math.min(max, Math.max(min, (Number.isFinite(value) ? Math.max(0, value) : baseline) / baseline));
const materialSummary = (mix?: MaterialMix[]) => (mix?.length ? mix.filter((item) => item.share > 0).map((item) => `${materialLabel[item.material]} ${fmt(item.share, 0)}%`).join(' + ') : '');
const partsSummary = (parts?: BoatPart[]) => parts?.length ? parts.map((part) => `${partOptions.find((item) => item.id === part.id)?.label ?? part.id}${part.id === 'flotation' ? ` ${part.quantity ?? flotationQuantity.default} ลูก` : ''} (${materialLabel[part.material]}, ${fmt(part.scale, 2)}×)`).join(' + ') : 'ตัวเรือหลัก';
const normalizeMix = (mix: MaterialMix[]) => {
  const total = mix.reduce((sum, item) => sum + Math.max(0, item.share), 0);
  return total > 0 ? mix.map((item) => ({ ...item, share: (Math.max(0, item.share) / total) * 100 })) : [{ material: mix[0]?.material ?? 'wood', share: 100 }];
};
const readBoatForm = (): typeof initialForm => {
  try {
    const saved: unknown = JSON.parse(localStorage.getItem('buoyancy-lab-current-boat') ?? 'null');
    if (!saved || typeof saved !== 'object') return initialForm;
    const item = saved as Partial<typeof initialForm>;
    const materialMix = Array.isArray(item.materialMix) ? item.materialMix.filter((mix): mix is MaterialMix =>
      !!mix && materials.some(material => material.id === mix.material) && Number.isFinite(mix.share) && mix.share >= 0 && mix.share <= 100,
    ).slice(0, 3) : [];
    const parts = Array.isArray(item.parts) ? item.parts.filter((part): part is BoatPart =>
      !!part && partOptions.some(option => option.id === part.id) && materials.some(material => material.id === part.material) &&
      Number.isFinite(part.scale) && part.scale >= 0.5 && part.scale <= 1.5 &&
      (part.id !== 'flotation' || part.quantity === undefined || (Number.isInteger(part.quantity) && part.quantity >= 1 && part.quantity <= 4)),
    ).filter((part, index, list) => list.findIndex(other => other.id === part.id) === index) : [];
    const dimension = (value: unknown, fallback: number) => typeof value === 'number' && Number.isFinite(value) ? value : fallback;
    return {
      materialMix: materialMix.length ? normalizeMix(materialMix) : initialForm.materialMix,
      parts,
      shape: shapes.some(shape => shape.id === item.shape) ? item.shape! : initialForm.shape,
      liquid: liquids.some(liquid => liquid.id === item.liquid) ? item.liquid! : initialForm.liquid,
      length: dimension(item.length, initialForm.length),
      width: dimension(item.width, initialForm.width),
      height: dimension(item.height, initialForm.height),
      thickness: dimension(item.thickness, initialForm.thickness),
      cargoMass: dimension(item.cargoMass, initialForm.cargoMass),
      massStep: dimension(item.massStep, initialForm.massStep),
    };
  } catch { return initialForm; }
};
const finiteNumber = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value);
const readExperiments = (): Experiment[] => {
  try {
    const stored = localStorage.getItem('buoyancy-lab-experiments');
    if (!stored) return [];
    const parsed: unknown = JSON.parse(stored);
    if (!Array.isArray(parsed)) return [];
    const normalized = parsed.map((item, index) => {
      const record = item as Partial<Experiment> & { boatMass?: number; cargoMass?: number; displaced?: number; buoyant?: number; totalWeight?: number };
      const legacyTotalWeight = finiteNumber(record.totalWeight) ? record.totalWeight : 0;
      const totalMass = finiteNumber(record.totalMass)
        ? record.totalMass
        : legacyTotalWeight > 0
          ? legacyTotalWeight / 9.81
          : (finiteNumber(record.boatMass) ? record.boatMass : 0) + (finiteNumber(record.cargoMass) ? record.cargoMass : 0);
      const liquid = record.liquid && knownLiquids.some((option) => option.id === record.liquid) ? record.liquid : 'freshwater';
      const selectedLiquid = knownLiquids.find((option) => option.id === liquid) ?? liquids[0];
      const legacyOuterVolume = finiteNumber(record.length) && finiteNumber(record.width) && finiteNumber(record.height)
        ? record.length * record.width * record.height * (shapeFactor[record.shape ?? 'tray'] ?? shapeFactor.tray) / 1_000_000
        : 0;
      const displacedVolume = finiteNumber(record.displacedVolume)
        ? record.displacedVolume
        : finiteNumber(record.displaced) ? record.displaced * 1000 : 0;
      const submergedPercent = finiteNumber(record.submergedPercent)
        ? record.submergedPercent
        : finiteNumber(record.submerged) && finiteNumber(record.emerged) && record.submerged + record.emerged > 0
          ? (record.submerged / (record.submerged + record.emerged)) * 100
          : 0;
      return {
        id: finiteNumber(record.id) ? record.id : Date.now() + index,
        recordedAt: typeof record.recordedAt === 'string' ? record.recordedAt : '',
        material: materials.some((option) => option.id === record.material) ? record.material : 'wood',
        ...(Array.isArray(record.materialMix) ? { materialMix: record.materialMix } : {}),
        parts: Array.isArray(record.parts) ? record.parts.filter((part): part is BoatPart =>
          !!part && partOptions.some((option) => option.id === part.id) && materials.some((material) => material.id === part.material) && finiteNumber(part.scale) && part.scale >= 0.5 && part.scale <= 1.5
          && (part.id !== 'flotation' || part.quantity === undefined || (Number.isInteger(part.quantity) && part.quantity >= flotationQuantity.min && part.quantity <= flotationQuantity.max)),
        ).filter((part, partIndex, parts) => parts.findIndex((item) => item.id === part.id) === partIndex) : [],
        shape: shapes.some((option) => option.id === record.shape) ? record.shape : 'tray',
        length: finiteNumber(record.length) ? record.length : initialForm.length,
        width: finiteNumber(record.width) ? record.width : initialForm.width,
        height: finiteNumber(record.height) ? record.height : initialForm.height,
        thickness: finiteNumber(record.thickness) ? record.thickness : initialForm.thickness,
        liquid,
        liquidDensity: finiteNumber(record.liquidDensity) ? record.liquidDensity : selectedLiquid.density,
        totalMass,
        totalDensity: finiteNumber(record.totalDensity) ? record.totalDensity : legacyOuterVolume > 0 ? totalMass / legacyOuterVolume : 0,
        submerged: finiteNumber(record.submerged) ? record.submerged : 0,
        emerged: finiteNumber(record.emerged) ? record.emerged : 0,
        displacedVolume,
        submergedPercent,
        aboveWaterWeight: finiteNumber(record.aboveWaterWeight) ? record.aboveWaterWeight : totalMass * 9.81,
        underwaterWeight: finiteNumber(record.underwaterWeight) ? record.underwaterWeight : 0,
        status: record.status === 'overflow' || record.status === 'sink' ? record.status : 'float',
      } as Experiment;
    });
    localStorage.setItem('buoyancy-lab-experiments', JSON.stringify(normalized));
    return normalized;
  } catch {
    return [];
  }
};

function LabPage() {
  const [form, setForm] = useState(readBoatForm);
  const [experiments, setExperiments] = useState<Experiment[]>(readExperiments);
  useEffect(() => {
    try { localStorage.setItem('buoyancy-lab-current-boat', JSON.stringify(form)); } catch { /* private browsing */ }
  }, [form]);
  const hullVisualHeight = 106 * visualRatio(form.height, initialForm.height, 0.6, 1.6);
  const beamVisualScale = visualRatio(form.width, initialForm.width, 0.55, 1.8);
  const cargoMass = Math.max(0, form.cargoMass);
  const cargoUnitMass = Number.isFinite(form.massStep) && form.massStep > 0 ? form.massStep : null;
  const cargoUnits = cargoUnitMass === null ? null : cargoMass / cargoUnitMass;
  const cargoCount = cargoMass === 0 ? 0 : cargoUnits !== null && Number.isSafeInteger(Math.ceil(cargoUnits))
    ? Math.max(0, Math.ceil(cargoUnits - 1e-8)) : null;
  const cargoCountExact = cargoMass === 0 || cargoUnits !== null && Math.abs(cargoUnits - Math.round(cargoUnits)) < 1e-8;
  const visibleCargoCount = Math.min(cargoCount ?? 0, 8);
  const weightChartRef = useRef<HTMLDivElement>(null);
  const densityChartRef = useRef<HTMLDivElement>(null);
  const chronologicalExperiments = useMemo(() => [...experiments].reverse(), [experiments]);
  const weightChartData = useMemo(() => chronologicalExperiments.map((experiment, index) => ({
    trial: index + 1,
    aboveWater: experiment.aboveWaterWeight,
    underwater: experiment.underwaterWeight,
    displacedLiquid: experiment.displacedVolume / 1_000_000 * experiment.liquidDensity * 9.81,
  })), [chronologicalExperiments]);
  const densityChartData = useMemo(() => chronologicalExperiments.map((experiment, index) => ({
    trial: index + 1,
    totalDensity: experiment.totalDensity,
    liquidDensity: experiment.liquidDensity,
  })), [chronologicalExperiments]);

  const result = useMemo(() => {
    const activeMix = normalizeMix(form.materialMix);
    const materialDensity = activeMix.reduce((sum, item) => {
      const density = materials.find((material) => material.id === item.material)?.density ?? 650;
      return sum + density * (item.share / 100);
    }, 0);
    const selectedLiquid = liquids.find((liquid) => liquid.id === form.liquid) ?? liquids[0];
    const visualMaterial = activeMix.reduce((best, item) => item.share > best.share ? item : best, activeMix[0]).material;
    const outerVolume = (form.length * form.width * form.height * shapeFactor[form.shape]) / 1_000_000;
    const safeThickness = Math.min(form.thickness, Math.min(form.width, form.height) / 2);
    const innerVolume = Math.max(0, ((form.length - safeThickness * 2) * (form.width - safeThickness * 2) * (form.height - safeThickness) * shapeFactor[form.shape]) / 1_000_000);
    const hullMaterialVolume = Math.max(0, outerVolume - innerVolume);
    const hullMass = hullMaterialVolume * materialDensity;
    const partResults = calculateParts(form.parts, form, Object.fromEntries(materials.map((item) => [item.id, item.density])) as Record<Material, number>);
    const boatMass = hullMass + partResults.mass;
    const effectiveVolume = outerVolume + partResults.displacementVolume;
    const capacityMass = effectiveVolume * selectedLiquid.density;
    const innerCapacityMass = (innerVolume + partResults.displacementVolume) * selectedLiquid.density;
    const totalMass = boatMass + Math.max(0, form.cargoMass);
    const submergedVolume = Math.min(effectiveVolume, totalMass / selectedLiquid.density);
    const emergedVolume = Math.max(0, effectiveVolume - submergedVolume);
    const submergedRatio = effectiveVolume > 0 ? submergedVolume / effectiveVolume : 0;
    const totalDensity = effectiveVolume > 0 ? totalMass / effectiveVolume : 0;
    const submergedPercent = submergedRatio * 100;
    const totalWeight = totalMass * 9.81;
    const status: Status = totalMass <= innerCapacityMass ? 'float' : totalMass <= capacityMass ? 'overflow' : 'sink';
    const underwaterWeight = status === 'sink' ? Math.max(0, (totalMass - capacityMass) * 9.81) : 0;
    return {
      materialDensity, selectedLiquid, visualMaterial, outerVolume, innerVolume, hullMaterialVolume, hullMass, partResults, effectiveVolume, boatMass, capacityMass, totalMass, submergedVolume, emergedVolume,
      submergedRatio, submergedPercent, totalDensity, innerCapacityMass, underwaterWeight,
      aboveWaterWeight: totalWeight, status,
    };
  }, [form]);
  const nearOverflow = result.status === 'float' && result.innerCapacityMass > 0
    && result.totalMass / result.innerCapacityMass >= 0.86;
  const waterMood = result.status === 'float' ? (nearOverflow ? 'near' : 'calm') : result.status;
  const [waterImpact, setWaterImpact] = useState<WaterImpact | null>(null);
  const previousCargoMass = useRef(cargoMass);
  const nextImpactId = useRef(0);
  const impactTimeout = useRef<number | null>(null);
  useEffect(() => () => {
    if (impactTimeout.current !== null) window.clearTimeout(impactTimeout.current);
  }, []);
  useEffect(() => {
    const previous = previousCargoMass.current;
    previousCargoMass.current = cargoMass;
    if (cargoMass > previous) {
      if (impactTimeout.current !== null) window.clearTimeout(impactTimeout.current);
      nextImpactId.current += 1;
      setWaterImpact({ id: nextImpactId.current, strength: waterMood === 'calm' ? 'light' : 'heavy' });
      impactTimeout.current = window.setTimeout(() => {
        setWaterImpact(null);
        impactTimeout.current = null;
      }, 1600);
    } else if (cargoMass < previous) {
      if (impactTimeout.current !== null) window.clearTimeout(impactTimeout.current);
      impactTimeout.current = null;
      setWaterImpact(null);
    }
  }, [cargoMass, waterMood]);

  const setField = (field: keyof typeof form, value: string | number) => {
    setForm((current) => ({ ...current, [field]: value }));
  };
  const updateMaterial = (index: number, material: Material) => {
    setForm((current) => ({
      ...current,
      materialMix: current.materialMix.map((item, itemIndex) => itemIndex === index ? { ...item, material } : item),
    }));
  };
  const updateMaterialShare = (index: number, value: number) => {
    setForm((current) => {
      if (current.materialMix.length === 1) return { ...current, materialMix: [{ ...current.materialMix[0], share: 100 }] };
      const nextShare = Math.min(100, Math.max(0, Number.isFinite(value) ? value : 0));
      const next = current.materialMix.map((item) => ({ ...item }));
      next[index].share = nextShare;
      const remaining = 100 - nextShare;
      const otherIndexes = next.map((_, itemIndex) => itemIndex).filter((itemIndex) => itemIndex !== index);
      const otherTotal = otherIndexes.reduce((sum, itemIndex) => sum + Math.max(0, current.materialMix[itemIndex].share), 0);
      otherIndexes.forEach((itemIndex, position) => {
        next[itemIndex].share = otherTotal > 0
          ? Math.max(0, current.materialMix[itemIndex].share) * remaining / otherTotal
          : position === 0 ? remaining : 0;
      });
      return { ...current, materialMix: next };
    });
  };
  const addMaterial = () => {
    setForm((current) => current.materialMix.length >= 3 ? current : {
      ...current,
      materialMix: [...current.materialMix, { material: materials.find((item) => !current.materialMix.some((selected) => selected.material === item.id))?.id ?? 'wood', share: 0 }],
    });
  };
  const removeMaterial = (index: number) => {
    setForm((current) => current.materialMix.length <= 1 ? current : { ...current, materialMix: normalizeMix(current.materialMix.filter((_, itemIndex) => itemIndex !== index)) });
  };
  const togglePart = (id: BoatPartId) => {
    setForm((current) => ({
      ...current,
      parts: current.parts.some((part) => part.id === id)
        ? current.parts.filter((part) => part.id !== id)
        : [...current.parts, defaultPart(id)],
    }));
  };
  const changePart = (id: BoatPartId, patch: Partial<Pick<BoatPart, 'material' | 'scale' | 'quantity'>>) => {
    setForm((current) => ({
      ...current,
      parts: current.parts.map((part) => part.id === id ? {
        ...part,
        ...(patch.material && materials.some((material) => material.id === patch.material) ? { material: patch.material } : {}),
        ...(patch.scale !== undefined && Number.isFinite(patch.scale) ? { scale: Math.min(1.5, Math.max(0.5, patch.scale)) } : {}),
        ...(id === 'flotation' && patch.quantity !== undefined && Number.isFinite(patch.quantity)
          ? { quantity: Math.min(flotationQuantity.max, Math.max(flotationQuantity.min, Math.round(patch.quantity))) } : {}),
      } : part),
    }));
  };
  const persist = (items: Experiment[]) => {
    setExperiments(items);
    localStorage.setItem('buoyancy-lab-experiments', JSON.stringify(items));
  };
  const addExperiment = () => {
    const next: Experiment = {
      id: Date.now(),
       recordedAt: new Date().toLocaleString('th-TH', { dateStyle: 'short', timeStyle: 'short' }),
      material: result.visualMaterial,
      materialMix: normalizeMix(form.materialMix),
      parts: form.parts.map((part) => ({ ...part })),
      shape: form.shape,
      length: form.length,
      width: form.width,
      height: form.height,
      thickness: form.thickness,
      liquid: form.liquid,
      liquidDensity: result.selectedLiquid.density,
      totalMass: result.totalMass,
      totalDensity: result.totalDensity,
      submerged: result.submergedVolume * 1_000_000,
      emerged: result.emergedVolume * 1_000_000,
      displacedVolume: result.submergedVolume * 1_000_000,
      submergedPercent: result.submergedPercent,
      aboveWaterWeight: result.aboveWaterWeight,
      underwaterWeight: result.underwaterWeight,
      status: result.status,
    };
    persist([next, ...experiments]);
  };
  const deleteExperiment = (id: number) => persist(experiments.filter((experiment) => experiment.id !== id));
  const clearExperiments = () => {
    if (experiments.length > 0 && window.confirm('ลบผลการทดลองทั้งหมดหรือไม่')) persist([]);
  };
  const exportCsv = () => {
    if (!experiments.length) return;
    const header = ['บันทึกเมื่อ', 'วัสดุ', 'ชิ้นส่วน', 'ของเหลว', 'รูปทรง', 'ยาว (ซม.)', 'กว้าง (ซม.)', 'สูง (ซม.)', 'มวลรวม (กก.)', 'ความหนาแน่นมวลรวม (กก./ม³)', 'ความหนาแน่นของเหลว (กก./ม³)', 'ปริมาตรจม (ซม³)', 'ปริมาตรพ้นน้ำ (ซม³)', 'ปริมาตรของเหลวที่ถูกแทนที่ (ซม³)', 'ส่วนที่จม (%)', 'น้ำหนักชั่งเหนือน้ำ (N)', 'น้ำหนักชั่งใต้น้ำ (N)', 'สถานะ'];
    const rows = chronologicalExperiments.map((item) => [
      item.recordedAt, item.materialMix ? materialSummary(item.materialMix) : materialLabel[item.material], partsSummary(item.parts), liquidLabel[item.liquid] ?? liquidLabel.freshwater, shapeLabel[item.shape], item.length, item.width, item.height, fmt(item.totalMass),
      fmt(item.totalDensity, 0), fmt(item.liquidDensity, 0), fmt(item.submerged, 0), fmt(item.emerged, 0), fmt(item.displacedVolume, 0), fmt(item.submergedPercent, 0),
      fmt(item.aboveWaterWeight), fmt(item.underwaterWeight), statusText(item.status),
    ]);
    const csv = [header, ...rows].map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'ผลการทดลองเรือรับน้ำหนัก.csv';
    anchor.click();
    URL.revokeObjectURL(url);
  };
  const exportChartsPdf = async () => {
    if (!experiments.length || !weightChartRef.current || !densityChartRef.current) return;
    const escapeXml = (value: string) => value.replace(/[<>&'"]/g, (character) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' })[character] ?? character);
    const svgToPng = async (svgMarkup: string, width: number, height: number) => {
      const svgUrl = URL.createObjectURL(new Blob([svgMarkup], { type: 'image/svg+xml;charset=utf-8' }));
      try {
        const image = await new Promise<HTMLImageElement>((resolve, reject) => {
          const nextImage = new Image();
          nextImage.onload = () => resolve(nextImage);
          nextImage.onerror = reject;
          nextImage.src = svgUrl;
        });
        const canvas = document.createElement('canvas');
        const scale = 2;
        canvas.width = width * scale;
        canvas.height = height * scale;
        const context = canvas.getContext('2d');
        if (!context) return null;
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.scale(scale, scale);
        context.drawImage(image, 0, 0, width, height);
        return canvas.toDataURL('image/png');
      } finally {
        URL.revokeObjectURL(svgUrl);
      }
    };
    const chartToPng = async (container: HTMLDivElement, title: string, subtitle: string, legend: { color: string; label: string }[]) => {
      const source = container.querySelector('svg');
      if (!source) return null;
      const width = Math.max(720, Math.round(source.getBoundingClientRect().width));
      const height = Math.max(280, Math.round(source.getBoundingClientRect().height));
      const headerHeight = 100;
      const legendMarkup = legend.map((entry, index) => {
        const x = 24 + index * (width - 48) / legend.length;
        return `<line x1="${x}" y1="76" x2="${x + 22}" y2="76" stroke="${entry.color}" stroke-width="3"/>
          <text x="${x + 29}" y="80" fill="#3e6269" font-size="12">${escapeXml(entry.label)}</text>`;
      }).join('');
      const svgMarkup = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height + headerHeight}" viewBox="0 0 ${width} ${height + headerHeight}">
        <rect width="100%" height="100%" fill="#ffffff"/>
        <style>text{font-family:'IBM Plex Sans Thai',Arial,sans-serif}.recharts-cartesian-axis-tick-value{fill:#789397;font-size:10px}.recharts-legend-item-text{fill:#789397;font-size:10px}</style>
        <text x="24" y="29" fill="#0e4d61" font-family="IBM Plex Sans Thai, sans-serif" font-size="20" font-weight="700">${escapeXml(title)}</text>
        <text x="24" y="50" fill="#789397" font-family="IBM Plex Sans Thai, sans-serif" font-size="11">${escapeXml(subtitle)}</text>
        ${legendMarkup}
        <g transform="translate(0 ${headerHeight})">${source.innerHTML}</g>
      </svg>`;
      return svgToPng(svgMarkup, width, height + headerHeight);
    };
    const tablePageToPng = async (pageExperiments: Experiment[], pageNumber: number) => {
      const columns = [
        { label: 'ลำดับ', width: 65 },
         { label: 'ชิ้นส่วน', width: 85 },
        { label: 'มวลรวม (กก.)', width: 90 },
        { label: 'ρ มวลรวม (กก./ม³)', width: 120 },
        { label: 'ρ ของเหลว (กก./ม³)', width: 120 },
        { label: 'จม / พ้นน้ำ (ซม³)', width: 135 },
        { label: 'ปริมาตรแทนที่ (ซม³)', width: 140 },
        { label: 'ส่วนที่จม (%)', width: 90 },
        { label: 'ชั่งเหนือน้ำ (N)', width: 120 },
        { label: 'ชั่งใต้น้ำ (N)', width: 120 },
        { label: 'สถานะ', width: 100 },
      ];
      const width = columns.reduce((total, column) => total + column.width, 0);
      const titleHeight = 76;
      const headerHeight = 50;
      const rowHeight = 42;
      const footerHeight = 38;
      const height = titleHeight + headerHeight + pageExperiments.length * rowHeight + footerHeight;
      const headings = columns.map((column, index) => {
        const x = columns.slice(0, index).reduce((total, item) => total + item.width, 0);
        return `<rect x="${x}" y="${titleHeight}" width="${column.width}" height="${headerHeight}" fill="#eaf5f3" stroke="#cde2df"/>
          <text x="${x + column.width / 2}" y="${titleHeight + headerHeight / 2}" text-anchor="middle" dominant-baseline="middle" fill="#174f61" font-size="12" font-weight="700">${escapeXml(column.label)}</text>`;
      }).join('');
      const rows = pageExperiments.map((item, index) => {
        const y = titleHeight + headerHeight + index * rowHeight;
        const values = [
          String((pageNumber - 1) * 12 + index + 1).padStart(2, '0'),
           item.parts?.length ? `+${item.parts.length} ชิ้น` : 'ตัวเรือ',
          `${fmt(item.totalMass)} กก.`,
          fmt(item.totalDensity, 0),
          fmt(item.liquidDensity, 0),
          `${fmt(item.submerged, 0)} / ${fmt(item.emerged, 0)}`,
          fmt(item.displacedVolume, 0),
          `${fmt(item.submergedPercent, 0)}%`,
          fmt(item.aboveWaterWeight),
          fmt(item.underwaterWeight),
          statusText(item.status),
        ];
        const cells = values.map((value, columnIndex) => {
          const column = columns[columnIndex];
          const x = columns.slice(0, columnIndex).reduce((total, itemColumn) => total + itemColumn.width, 0);
          return `<rect x="${x}" y="${y}" width="${column.width}" height="${rowHeight}" fill="${index % 2 ? '#ffffff' : '#f8fcfb'}" stroke="#dceceb"/>
            <text x="${x + column.width / 2}" y="${y + rowHeight / 2}" text-anchor="middle" dominant-baseline="middle" fill="#3e6269" font-size="13">${escapeXml(value)}</text>`;
        }).join('');
        return cells;
      }).join('');
      const svgMarkup = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
        <rect width="100%" height="100%" fill="#ffffff"/>
        <style>text{font-family:'IBM Plex Sans Thai',Arial,sans-serif}</style>
        <text x="18" y="32" fill="#0e4d61" font-size="22" font-weight="700">ผลการทดลองเรือรับน้ำหนัก</text>
        <text x="18" y="56" fill="#789397" font-size="13">เรียงลำดับจากข้อมูลเก่าสุดไปใหม่ · หน้า ${pageNumber}</text>
        ${headings}${rows}
      </svg>`;
      return svgToPng(svgMarkup, width, height);
    };
    await document.fonts.ready;
    const pageSize = 12;
    const experimentPages = await Promise.all(Array.from(
      { length: Math.ceil(chronologicalExperiments.length / pageSize) },
      (_, pageIndex) => tablePageToPng(chronologicalExperiments.slice(pageIndex * pageSize, (pageIndex + 1) * pageSize), pageIndex + 1),
    ));
    const [weightImage, densityImage] = await Promise.all([
      chartToPng(weightChartRef.current, 'กราฟน้ำหนักจากผลการทดลอง', 'หน่วย: นิวตัน (N) · ลำดับ 1 เป็นข้อมูลเก่าสุด', [
        { color: '#176b7c', label: 'ชั่งเหนือน้ำ' },
        { color: '#e47b62', label: 'ชั่งใต้น้ำ' },
        { color: '#d2a52f', label: 'น้ำหนักของเหลวที่แทนที่' },
      ]),
      chartToPng(densityChartRef.current, 'กราฟเปรียบเทียบความหนาแน่น', 'หน่วย: กก./ม³ · ลำดับ 1 เป็นข้อมูลเก่าสุด', [
        { color: '#176b7c', label: 'ความหนาแน่นมวลรวม' },
        { color: '#d2a52f', label: 'ความหนาแน่นของเหลว' },
      ]),
    ]);
    if (experimentPages.some((image) => !image) || !weightImage || !densityImage) {
      window.alert('สร้างไฟล์ PDF ไม่สำเร็จ กรุณาลองอีกครั้ง');
      return;
    }
    const { jsPDF } = await import('jspdf');
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const addImagePage = (image: string, pageTitle: string) => {
      pdf.setFillColor(248, 252, 251);
      pdf.rect(0, 0, pageWidth, pageHeight, 'F');
      pdf.setTextColor(14, 77, 97);
      pdf.setFontSize(10);
      pdf.text(`BUOYANCY BOAT LAB  |  ${pageTitle}`, 36, 24);
      const { width, height } = pdf.getImageProperties(image);
      const scale = Math.min((pageWidth - 72) / width, (pageHeight - 74) / height);
      const imageWidth = width * scale;
      const imageHeight = height * scale;
      pdf.addImage(image, 'PNG', (pageWidth - imageWidth) / 2, 38 + (pageHeight - 74 - imageHeight) / 2, imageWidth, imageHeight, undefined, 'FAST');
    };
    experimentPages.forEach((image, pageIndex) => {
      if (image) {
        addImagePage(image, 'EXPERIMENT RESULTS');
        if (pageIndex < experimentPages.length - 1) pdf.addPage();
      }
    });
    pdf.addPage();
    addImagePage(weightImage, 'WEIGHT CHART');
    pdf.addPage();
    addImagePage(densityImage, 'DENSITY CHART');
    pdf.save('ผลการทดลองและกราฟเรือรับน้ำหนัก.pdf');
  };

  return (
    <div className="lab-shell">
      <header className="lab-header">
        <div className="brand-lockup" data-testid="brand-lockup">
          <div className="brand-mark"><Waves size={22} strokeWidth={2.5} /></div>
          <div className="brand-text"><strong>ห้องทดลองเรือรับน้ำหนัก</strong><span>BUOYANCY / FIELD NOTE 01</span></div>
        </div>
        <nav className="lab-header-actions">
          <a className="lab-site-link" href={siteRoot} data-testid="link-site-home">← KUEDU2IMPACT</a>
          <div className="header-badge"><FlaskConical size={14} /> ทดลอง · สังเกต · อธิบาย</div>
        </nav>
      </header>

      <main className="lab-main">
        <section className="intro">
          <div className="eyebrow">ห้องทดลองแรงลอยตัว</div>
          <h1>เรือลำนี้<br />รับได้แค่ไหน?</h1>
            <p>เปลี่ยนวัสดุ ออกแบบรูปทรง ต่อเติมหรือถอดชิ้นส่วนเรือ แล้วเติมมวลทีละขั้นในอ่างยูเรก้า สังเกตระดับเรือและจดผลการทดลองเพื่อคุยกันในห้องเรียน</p>
        </section>

        <section className="workspace">
          <aside className="surface control-panel" aria-label="แผงควบคุมการทดลอง">
            <div className="panel-heading">
              <div><h2>สร้างเรือของคุณ</h2><p>ปรับค่าแล้วดูการเปลี่ยนแปลงในสระ</p></div>
              <span className="panel-scroll-hint" aria-hidden="true">เลื่อนรายการ ↓</span>
            </div>

            <div className="control-panel-scroll" role="region" aria-label="ตัวเลือกสร้างเรือ" tabIndex={0}>
            <div className="section-label">วัสดุของลำเรือ</div>
            <div className="material-mix-card">
              <div className="material-mix-heading">
                <span>ผสมวัสดุได้สูงสุด 3 ชนิด</span>
                <strong>รวม {fmt(form.materialMix.reduce((sum, item) => sum + item.share, 0), 0)}%</strong>
              </div>
              <div className="material-mix-list">
                {form.materialMix.map((mix, index) => {
                  const material = materials.find((item) => item.id === mix.material) ?? materials[0];
                  return (
                    <div className="material-mix-row" key={`${index}-${mix.material}`}>
                      <span className="material-swatch" style={{ backgroundColor: material.color }} />
                      <select value={mix.material} onChange={(event) => updateMaterial(index, event.target.value as Material)} aria-label={`วัสดุชั้นที่ ${index + 1}`} data-testid={`select-material-${index}`}>
                        {materials.map((option) => <option key={option.id} value={option.id}>{option.label} · {option.density.toLocaleString('th-TH')} กก./ม³</option>)}
                      </select>
                      <div className="share-input-wrap">
                        <input type="number" min="0" max="100" step="5" value={Math.round(mix.share)} onChange={(event) => updateMaterialShare(index, Number(event.target.value))} aria-label={`สัดส่วน${material.label}`} data-testid={`input-material-share-${index}`} />
                        <span>%</span>
                      </div>
                      {form.materialMix.length > 1 && <button type="button" className="mix-remove" onClick={() => removeMaterial(index)} aria-label={`ลบวัสดุชั้นที่ ${index + 1}`}>×</button>}
                    </div>
                  );
                })}
              </div>
              {form.materialMix.length < 3 && <button type="button" className="mix-add" onClick={addMaterial}>+ เพิ่มวัสดุผสม</button>}
            </div>

            <div className="section-label">ของเหลวในอ่าง</div>
            <div className="liquid-select-card">
              <span className="liquid-swatch" style={{ backgroundColor: result.selectedLiquid.color }} />
              <div className="liquid-select-content">
                <select value={form.liquid} onChange={(event) => setField('liquid', event.target.value as LiquidId)} aria-label="เลือกของเหลวในอ่าง" data-testid="select-liquid">
                  {liquids.map((liquid) => <option key={liquid.id} value={liquid.id}>{liquid.label} · {liquid.density.toLocaleString('th-TH')} กก./ม³</option>)}
                </select>
                <span className={form.liquid === 'mercury' ? 'liquid-safety-note' : undefined}>{form.liquid === 'mercury' ? 'ปรอทเป็นพิษ · ใช้เพื่อจำลองเท่านั้น ห้ามทดลองจริง' : 'ค่าความหนาแน่นโดยประมาณ · เลือกเพื่อเปรียบเทียบการจมของเรือ'}</span>
              </div>
            </div>

            <div className="section-label">รูปทรงตัวเรือ</div>
             <div className="shape-grid" aria-label="เลือกรูปทรงเรือ">
              {shapes.map((shape) => (
                <button key={shape.id} type="button" className={`shape-button ${form.shape === shape.id ? 'active' : ''}`} onClick={() => setField('shape', shape.id)} data-testid={`button-shape-${shape.id}`}>
                  <span className={`shape-icon icon-${shape.id}`} /><span>{shape.label}</span>
                </button>
              ))}
            </div>

            <div className="section-label">ขนาดและมวล</div>
            <div className="field-grid">
              <NumberField label="ความยาว" unit="ซม." value={form.length} onChange={(value) => setField('length', value)} id="length" />
              <NumberField label="ความกว้าง" unit="ซม." value={form.width} onChange={(value) => setField('width', value)} id="width" />
              <NumberField label="ความสูง" unit="ซม." value={form.height} onChange={(value) => setField('height', value)} id="height" />
              <NumberField label="ความหนาผนัง" unit="ซม." value={form.thickness} onChange={(value) => setField('thickness', value)} id="thickness" step="0.1" />
              <DerivedField label="มวลเรือเปล่า" unit="กก." value={result.boatMass} id="boat-mass" />
              <NumberField label="มวลสินค้า" unit="กก." value={form.cargoMass} onChange={(value) => setField('cargoMass', value)} id="cargo-mass" step="0.1" />
            </div>
            <div className="derived-volume-note">
               ปริมาตรวัสดุเรือประกอบ <strong data-testid="value-material-volume">{fmt((result.hullMaterialVolume + result.partResults.materialVolume) * 1_000_000, 0)} ซม³</strong>
               <span>· ตัวเรือหลัก ρ {fmt(result.materialDensity, 0)} กก./ม³ · ตัวคูณรูปทรง {fmt(shapeFactor[form.shape], 2)}</span>
            </div>
             <BoatPartsPanel
               parts={form.parts}
               onToggle={togglePart}
               onChange={changePart}
               summaries={result.partResults.summaries}
               materialOptions={materials}
               hullMass={result.hullMass}
               hullMaterialVolume={result.hullMaterialVolume}
               totalBoatMass={result.boatMass}
               boatDensity={result.effectiveVolume > 0 ? result.boatMass / result.effectiveVolume : 0}
             />
            <div className="mass-test-card">
              <div className="mass-test-heading">
                 <div><strong>ทดลองเพิ่มมวล</strong><span>เพิ่มทีละชิ้น แล้วสังเกตระดับเรือกับสถานะ</span></div>
              </div>
              <div className="mass-test-row">
                 <NumberField label="มวลต่อชิ้น" unit="กก." value={form.massStep} onChange={(value) => setField('massStep', value)} id="mass-step" step="0.1" />
                <div className="mass-test-actions">
                   <button type="button" className="button primary" onClick={() => setField('cargoMass', Number((Math.max(0, form.cargoMass) + form.massStep).toFixed(2)))} disabled={cargoUnitMass === null} data-testid="button-add-mass">+ เพิ่ม 1 ชิ้น</button>
                  <button type="button" className="button" onClick={() => setField('cargoMass', 0)} data-testid="button-reset-mass">เริ่มมวลใหม่</button>
                </div>
              </div>
               <p className="mass-test-note">จำนวนชิ้น = มวลสินค้ารวม ÷ มวลต่อชิ้น หากหารไม่ลงตัวจะแสดงเป็นจำนวนประมาณ</p>
            </div>
             <div className="control-note"><Info size={13} style={{ verticalAlign: 'middle', marginRight: 5 }} /> ชิ้นส่วนเหนือน้ำเพิ่มมวลแต่ไม่เพิ่มแรงพยุง · ชิ้นส่วนใต้น้ำและทุ่นปิดสนิทเพิ่มปริมาตรแทนที่ (แบบจำลองโดยประมาณ)</div>
            </div>
          </aside>

          <section className="surface sim-panel" aria-label="ภาพจำลองเรือ">
            <div className="sim-toolbar">
              <div><h2>สนามทดลอง · {result.selectedLiquid.label}</h2><span>ปรับค่าทางซ้ายเพื่อทดสอบ</span></div>
              <span className={`status-pill status-${result.status}`} data-testid="status-message">{statusText(result.status)}</span>
            </div>
             <div className="cargo-inventory" role="status" aria-live="polite" data-testid="cargo-count">
               <span>วัตถุบนเรือ</span>
               <strong>{cargoCount === null ? 'ยังนับจำนวนไม่ได้' : `${cargoCountExact ? '' : 'ประมาณ '}${fmtDimension(cargoCount)} ชิ้น`}</strong>
               <small>{cargoUnitMass === null ? 'กำหนดมวลต่อชิ้นให้มากกว่า 0' : `มวลรวม ${fmtDimension(cargoMass)} กก. · ชิ้นละ ${fmtDimension(cargoUnitMass)} กก.`}</small>
             </div>
               <div className="pool" data-liquid={form.liquid} data-mood={waterMood} data-impact={waterImpact?.strength ?? 'none'} data-testid="simulation-liquid">
               <div className="basin-rim basin-rim-left" />
               <div className="basin-rim basin-rim-right" />
               <span className="pool-label">LARGE EUREKA BASIN / ρ = {result.selectedLiquid.density.toLocaleString('en-US')} KG/M³</span>
                <span className="boat-dimensions" data-testid="simulation-boat-dimensions">
                  ลำเรือ {fmtDimension(form.length)} × {fmtDimension(form.width)} × {fmtDimension(form.height)} ซม. · ผนัง {fmtDimension(form.thickness)} ซม.
                </span>
              <div className="boat-stage">
                 <div className="water-surface-fx" aria-hidden="true">
                   <span className="surface-ring" /><span className="surface-ring" /><span className="surface-ring" />
                 </div>
                 {waterImpact && <div key={waterImpact.id} className={`splash-burst splash-burst-${waterImpact.strength}`} aria-hidden="true" data-testid="water-impact">
                   <span className="splash-ring" /><span className="splash-ring" />
                   {Array.from({ length: 8 }).map((_, index) => <span key={index} className={`splash-drop splash-drop-${index + 1}`} />)}
                 </div>}
                <div
                  className={`boat-group is-${result.status}`}
                  style={{
                      transform: `translateY(calc(${-51 + result.submergedRatio * hullVisualHeight}px + var(--sink-offset, 0px)))`,
                    '--submerged-cut': `${Math.round((1 - result.submergedRatio) * 100)}%`,
                     '--boat-length-scale': visualRatio(form.length, initialForm.length, 0.55, 1.65),
                     '--hull-height': `${hullVisualHeight}px`,
                     '--boat-beam-depth': `${20 * beamVisualScale}px`,
                     '--boat-beam-inset': `${8 / beamVisualScale}%`,
                     '--hull-wall': `${3 * visualRatio(form.thickness, initialForm.thickness, 0.65, 3)}px`,
                  } as CSSProperties}
                   aria-label={`ภาพเรือยาว ${fmtDimension(form.length)} กว้าง ${fmtDimension(form.width)} สูง ${fmtDimension(form.height)} เซนติเมตร สถานะ${statusText(result.status)} จมประมาณ ${fmt(result.submergedRatio * 100, 0)} เปอร์เซ็นต์ของตัวเรือ`}
                >
                  <div key={waterImpact?.id ?? 'rest'} className={`boat-motion${waterImpact ? ' boat-motion-hit' : ''}`}>
                  <div className="boat-shadow" />
                  <div className={`boat-hull shape-${form.shape} material-${result.visualMaterial}`} />
                  <div className={`boat-hull submerged-hull shape-${form.shape} material-${result.visualMaterial}`} aria-hidden="true" />
                   <span className="boat-spill boat-spill-left" aria-hidden="true" />
                   <span className="boat-spill boat-spill-right" aria-hidden="true" />
                  <BoatPartsVisual parts={form.parts} />
                   {visibleCargoCount > 0 && <div className="cargo-stack" aria-hidden="true">
                     {visibleCargoCount > 4 && <div className="cargo-stack-row">
                       {Array.from({ length: visibleCargoCount - 4 }).map((_, index) => <span key={`top-${index}`} className="cargo-crate" />)}
                     </div>}
                     <div className="cargo-stack-row">
                       {Array.from({ length: Math.min(visibleCargoCount, 4) }).map((_, index) => <span key={`base-${index}`} className="cargo-crate" />)}
                     </div>
                     {cargoCount !== null && cargoCount > 8 && <span className="cargo-stack-overflow">+{fmtDimension(cargoCount - 8)}</span>}
                   </div>}
                  </div>
                </div>
                 <div className="underwater-veil" aria-hidden="true" />
                <div className="waterline" aria-hidden="true"><span>ระดับน้ำ</span></div>
                <div className="water-part-label water-part-label-above"><i className="part-swatch part-swatch-above" />ส่วนพ้นน้ำ</div>
                <div className="water-part-label water-part-label-below"><i className="part-swatch part-swatch-below" />ส่วนจม</div>
              </div>
              <div className={`visual-status visual-status-${result.status}`}>
                 <strong>{nearOverflow ? 'ใกล้น้ำล้น!' : statusText(result.status)}</strong>
                  <span>{result.status === 'float' ? (nearOverflow ? 'เพิ่มอีกชิ้น เรือจะยังลอยไหม?' : 'เรืออยู่เหนือระดับน้ำ') : result.status === 'overflow' ? 'ขอบเรือจม น้ำกำลังล้นเข้าลำเรือ' : 'เรือจมมิดใต้ผิวของเหลว'}</span>
              </div>
               <div className="boat-readout">ปริมาตรจม<strong>{fmt(result.submergedVolume * 1_000_000, 0)} ซม³</strong></div>
              <div className="pool-depth">อ่างยูเรก้าขนาดใหญ่ · {result.selectedLiquid.label} · {materialLabel[result.visualMaterial]}</div>
            </div>
            <div className="sim-footnote"><span>สังเกตเส้นระดับน้ำ เพื่อเปรียบเทียบส่วนที่จมกับส่วนที่พ้นน้ำ</span><strong>ส่วนที่จม {fmt(result.submergedPercent, 0)}%</strong></div>
          </section>
        </section>

        <section className="metric-strip" aria-label="ค่าผลการคำนวณ">
          <Metric label="ปริมาตรจม" value={fmt(result.submergedVolume * 1_000_000, 0)} unit="ซม³" testId="metric-submerged" />
          <Metric label="ปริมาตรพ้นน้ำ" value={fmt(result.emergedVolume * 1_000_000, 0)} unit="ซม³" testId="metric-emerged" />
           <Metric label="มวลรวม" value={fmt(result.totalMass)} unit="กก." testId="metric-total-mass" />
           <Metric label="ปริมาตรเรือที่ช่วยพยุง" value={fmt(result.effectiveVolume * 1_000_000, 0)} unit="ซม³" testId="metric-effective-volume" />
           <Metric label="ความหนาแน่นเฉลี่ยทั้งลำ" value={fmt(result.totalDensity, 0)} unit="กก./ม³" testId="metric-total-density" />
          <Metric label="ปริมาตรของเหลวที่ถูกแทนที่" value={fmt(result.submergedVolume * 1_000_000, 0)} unit="ซม³" testId="metric-displaced-volume" />
           <Metric label="น้ำหนักชั่งเหนือน้ำ" value={fmt(result.aboveWaterWeight)} unit="N" testId="metric-above-water-weight" />
           <Metric label="น้ำหนักชั่งใต้น้ำ" value={fmt(result.underwaterWeight)} unit="N" testId="metric-underwater-weight" />
        </section>

        <section className="lower-grid">
          <div className="surface results-card">
            <div className="section-heading">
              <div><h2>สมุดบันทึกผลการทดลอง</h2><p>{experiments.length ? `${experiments.length} การทดลองที่บันทึกไว้ในเครื่องนี้` : 'บันทึกหลายแบบเพื่อเปรียบเทียบกัน'}</p></div>
              <div className="action-row">
                <button type="button" className="button primary" onClick={addExperiment} data-testid="button-add-experiment"><Plus size={14} /> เพิ่มการทดลองนี้</button>
                <button type="button" className="button" onClick={exportCsv} disabled={!experiments.length} data-testid="button-export-csv"><Download size={14} /> CSV</button>
                <button type="button" className="button danger" onClick={clearExperiments} disabled={!experiments.length} data-testid="button-clear-experiments"><RotateCcw size={14} /> ล้างทั้งหมด</button>
              </div>
            </div>
            <div className="table-wrap">
              {experiments.length ? (
                <table className="results-table">
                   <thead><tr><th>#</th><th>ชิ้นส่วน</th><th>มวลรวม</th><th>ρ มวลรวม</th><th>ρ ของเหลว</th><th>จม / พ้นน้ำ (ซม³)</th><th>ปริมาตรแทนที่ (ซม³)</th><th>ส่วนที่จม</th><th>เหนือน้ำ</th><th>ใต้น้ำ</th><th>สถานะ</th><th /></tr></thead>
                  <tbody>{chronologicalExperiments.map((experiment, index) => (
                    <tr key={experiment.id} data-testid={`row-experiment-${experiment.id}`}>
                      <td className="row-index">{String(index + 1).padStart(2, '0')}</td>
                      <td title={partsSummary(experiment.parts)} data-testid={`text-parts-${experiment.id}`}>{experiment.parts?.length ? `+${experiment.parts.length} ชิ้น` : 'ตัวเรือหลัก'}</td>
                      <td className="font-mono">{fmt(experiment.totalMass)} กก.</td>
                      <td className="font-mono">{fmt(experiment.totalDensity, 0)} กก./ม³</td>
                      <td className="font-mono">{fmt(experiment.liquidDensity, 0)} กก./ม³</td>
                      <td className="font-mono">{fmt(experiment.submerged, 0)} / {fmt(experiment.emerged, 0)}</td>
                      <td className="font-mono">{fmt(experiment.displacedVolume, 0)}</td>
                      <td className="font-mono">{fmt(experiment.submergedPercent, 0)}%</td>
                       <td className="font-mono">{fmt(experiment.aboveWaterWeight)} N</td>
                       <td className="font-mono">{fmt(experiment.underwaterWeight)} N</td>
                      <td><span className={`table-status status-${experiment.status}`}>{statusText(experiment.status)}</span></td>
                      <td><button type="button" className="button" aria-label={`ลบการทดลองที่ ${index + 1}`} onClick={() => deleteExperiment(experiment.id)} data-testid={`button-delete-experiment-${experiment.id}`}><Trash2 size={13} /></button></td>
                    </tr>
                  ))}</tbody>
                </table>
              ) : <div className="empty-state"><Scale size={22} color="#95b4b5" /><strong>ยังไม่มีผลการทดลอง</strong><span>ตั้งค่าเรือ แล้วกด “เพิ่มการทดลองนี้” เพื่อเริ่มสะสมข้อมูล</span></div>}
            </div>
          </div>

          <div className="charts-column">
          <section className="surface chart-card">
            <div className="section-heading">
              <div><h2>น้ำหนักต่อครั้งที่เก็บข้อมูล</h2><p>{experiments.length ? 'เปรียบเทียบค่าน้ำหนักของการทดลองที่บันทึกไว้' : 'เพิ่มการทดลองเพื่อเริ่มสร้างกราฟ'}</p></div>
              <div className="chart-heading-actions">
                <button type="button" className="button" onClick={() => { void exportChartsPdf().catch(() => window.alert('สร้างไฟล์ PDF ไม่สำเร็จ กรุณาลองอีกครั้ง')); }} disabled={!experiments.length} data-testid="button-export-charts-pdf"><Download size={14} /> ผลทดลอง + กราฟ PDF</button>
                <Waves size={19} color="#1b7282" />
              </div>
            </div>
            {experiments.length ? (
              <div className="weight-chart" ref={weightChartRef} role="img" aria-label="กราฟเปรียบเทียบน้ำหนักชั่งเหนือน้ำ น้ำหนักชั่งใต้น้ำ และน้ำหนักของน้ำที่แทนที่">
                <ResponsiveContainer width="100%" height={280}>
                  <LineChart data={weightChartData} margin={{ top: 8, right: 8, left: -18, bottom: 8 }}>
                    <CartesianGrid stroke="#dceceb" strokeDasharray="3 3" />
                    <XAxis dataKey="trial" tickFormatter={(value) => `ครั้งที่ ${value}`} tick={{ fill: '#789397', fontSize: 10 }} axisLine={{ stroke: '#bdd8d7' }} tickLine={false} />
                    <YAxis tick={{ fill: '#789397', fontSize: 10 }} axisLine={{ stroke: '#bdd8d7' }} tickLine={false} width={42} />
                    <Tooltip
                      labelFormatter={(label) => `ครั้งที่ ${label}`}
                      formatter={(value, name) => [`${fmt(Number(value))} N`, name === 'aboveWater' ? 'ชั่งเหนือน้ำ' : name === 'underwater' ? 'ชั่งใต้น้ำ' : 'น้ำที่แทนที่']}
                      contentStyle={{ border: '1px solid #cde5e5', borderRadius: 9, fontSize: 11, color: '#174f61' }}
                      labelStyle={{ color: '#174f61', fontWeight: 700 }}
                    />
                    <Legend formatter={(value) => value === 'aboveWater' ? 'ชั่งเหนือน้ำ' : value === 'underwater' ? 'ชั่งใต้น้ำ' : 'น้ำที่แทนที่'} wrapperStyle={{ fontSize: 10, paddingTop: 8 }} />
                    <Line type="monotone" dataKey="aboveWater" name="aboveWater" stroke="#176b7c" strokeWidth={2.5} dot={{ r: 3, fill: '#176b7c' }} activeDot={{ r: 5 }} />
                    <Line type="monotone" dataKey="underwater" name="underwater" stroke="#e47b62" strokeWidth={2.5} dot={{ r: 3, fill: '#e47b62' }} activeDot={{ r: 5 }} />
                    <Line type="monotone" dataKey="displacedLiquid" name="displacedLiquid" stroke="#d2a52f" strokeWidth={2.5} dot={{ r: 3, fill: '#d2a52f' }} activeDot={{ r: 5 }} />
                  </LineChart>
                </ResponsiveContainer>
                <p className="chart-unit">หน่วย: นิวตัน (N) · น้ำหนักของของเหลวที่แทนที่คำนวณจากปริมาตรแทนที่และของเหลวที่เลือก</p>
              </div>
            ) : (
              <div className="chart-empty"><Waves size={24} color="#95b4b5" /><strong>ยังไม่มีข้อมูลสำหรับกราฟ</strong><span>ตั้งค่าเรือ แล้วกด “เพิ่มการทดลองนี้” เพื่อบันทึกครั้งแรก</span></div>
            )}
          </section>
          <section className="surface chart-card">
            <div className="section-heading">
              <div><h2>ความหนาแน่นเปรียบเทียบ</h2><p>{experiments.length ? 'เปรียบเทียบความหนาแน่นมวลรวมกับของเหลวในแต่ละครั้ง' : 'เพิ่มการทดลองเพื่อเริ่มสร้างกราฟ'}</p></div>
              <Scale size={19} color="#1b7282" />
            </div>
            {experiments.length ? (
              <div className="density-chart" ref={densityChartRef} role="img" aria-label="กราฟเปรียบเทียบความหนาแน่นของมวลรวมกับความหนาแน่นของของเหลว">
                <ResponsiveContainer width="100%" height={280}>
                  <LineChart data={densityChartData} margin={{ top: 8, right: 8, left: -18, bottom: 8 }}>
                    <CartesianGrid stroke="#dceceb" strokeDasharray="3 3" />
                    <XAxis dataKey="trial" tickFormatter={(value) => `ครั้งที่ ${value}`} tick={{ fill: '#789397', fontSize: 10 }} axisLine={{ stroke: '#bdd8d7' }} tickLine={false} />
                    <YAxis tick={{ fill: '#789397', fontSize: 10 }} axisLine={{ stroke: '#bdd8d7' }} tickLine={false} width={48} />
                    <Tooltip
                      labelFormatter={(label) => `ครั้งที่ ${label}`}
                      formatter={(value, name) => [`${fmt(Number(value), 0)} กก./ม³`, name === 'totalDensity' ? 'ความหนาแน่นมวลรวม' : 'ความหนาแน่นของเหลว']}
                      contentStyle={{ border: '1px solid #cde5e5', borderRadius: 9, fontSize: 11, color: '#174f61' }}
                      labelStyle={{ color: '#174f61', fontWeight: 700 }}
                    />
                    <Legend formatter={(value) => value === 'totalDensity' ? 'ความหนาแน่นมวลรวม' : 'ความหนาแน่นของเหลว'} wrapperStyle={{ fontSize: 10, paddingTop: 8 }} />
                    <Line type="monotone" dataKey="totalDensity" name="totalDensity" stroke="#176b7c" strokeWidth={2.5} dot={{ r: 3, fill: '#176b7c' }} activeDot={{ r: 5 }} />
                    <Line type="monotone" dataKey="liquidDensity" name="liquidDensity" stroke="#d2a52f" strokeWidth={2.5} dot={{ r: 3, fill: '#d2a52f' }} activeDot={{ r: 5 }} />
                  </LineChart>
                </ResponsiveContainer>
                <p className="chart-unit">หน่วย: กิโลกรัมต่อลูกบาศก์เมตร (กก./ม³)</p>
              </div>
            ) : (
              <div className="chart-empty"><Scale size={24} color="#95b4b5" /><strong>ยังไม่มีข้อมูลสำหรับกราฟ</strong><span>ตั้งค่าเรือ แล้วกด “เพิ่มการทดลองนี้” เพื่อบันทึกครั้งแรก</span></div>
            )}
          </section>
          </div>
        </section>
        <p className="footer-note">ค่าทั้งหมดคำนวณจากแบบจำลองของเหลวที่เลือก · ปัดเศษเพื่อการเรียนรู้ · ผลการทดลองเก็บไว้ในเบราว์เซอร์นี้</p>
      </main>
    </div>
  );
}

export default function App() {
  return <LabPage />;
}

function NumberField({ label, unit, value, onChange, id, step = '1' }: { label: string; unit: string; value: number; onChange: (value: number) => void; id: string; step?: string }) {
  return (
    <div className="field">
      <label htmlFor={`input-${id}`}>{label}</label>
      <div className="input-wrap">
        <input id={`input-${id}`} type="number" min="0" step={step} value={value} onChange={(event) => onChange(Number(event.target.value))} data-testid={`input-${id}`} />
        <span className="unit">{unit}</span>
      </div>
    </div>
  );
}

function DerivedField({ label, unit, value, id }: { label: string; unit: string; value: number; id: string }) {
  return (
    <div className="field derived-field">
      <label htmlFor={`input-${id}`}>{label}<span className="derived-tag">คำนวณ</span></label>
      <div className="input-wrap">
        <input id={`input-${id}`} type="text" value={fmt(value)} readOnly aria-readonly="true" data-testid={`input-${id}`} />
        <span className="unit">{unit}</span>
      </div>
    </div>
  );
}

function Metric({ label, value, unit, testId }: { label: string; value: string; unit: string; testId: string }) {
  return <div className="metric" data-testid={testId}><span className="metric-label">{label}</span><span className="metric-value">{value} <span className="metric-unit">{unit}</span></span></div>;
}

function statusText(status: Status) {
  if (status === 'float') return 'ลอยตัว';
  if (status === 'overflow') return 'น้ำล้น';
  return 'จมถึงก้น';
}
