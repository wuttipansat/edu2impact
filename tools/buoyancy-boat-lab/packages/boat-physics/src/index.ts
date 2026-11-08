export type Material = 'wood' | 'aluminum' | 'plastic' | 'steel' | 'paper';
export type BoatPartId = 'keel' | 'deck' | 'cabin' | 'outriggers' | 'flotation' | 'mast' | 'rudder';
export type BoatPart = { id: BoatPartId; material: Material; scale: number; quantity?: number };
export type HullShape = 'tray' | 'box' | 'canoe' | 'cylinder' | 'barge' | 'catamaran' | 'pontoon' | 'triangular';
export type BoatDesign = {
  materialMix: { material: Material; share: number }[];
  parts: BoatPart[];
  shape: HullShape;
  length: number;
  width: number;
  height: number;
  thickness: number;
};
export const flotationQuantity = { min: 1, max: 4, default: 2 } as const;
export const materialDensities: Record<Material, number> = {
  wood: 650, aluminum: 2700, plastic: 950, steel: 7850, paper: 800,
};
export const shapeFactor: Record<HullShape, number> = {
  tray: 0.84, box: 0.94, canoe: 0.67, cylinder: 0.72,
  barge: 0.98, catamaran: 0.78, pontoon: 0.74, triangular: 0.58,
};

type PartDefinition = {
  id: BoatPartId;
  label: string;
  description: string;
  placement: string;
  volumeFactor: number;
  materialFraction: number;
  displacesLiquid: boolean;
};

// Classroom approximation: sealed side floats and underwater parts displace liquid;
// above-water additions increase mass only. All displacing parts share one draft.
export const partOptions: PartDefinition[] = [
  { id: 'keel', label: 'กระดูกงู', description: 'คานยาวใต้ท้องเรือ', placement: 'ใต้น้ำ', volumeFactor: 0.7 * 0.08 * 0.06, materialFraction: 1, displacesLiquid: true },
  { id: 'deck', label: 'ดาดฟ้า', description: 'แผ่นปิดด้านบนสำหรับยืน', placement: 'เหนือน้ำ', volumeFactor: 0.85 * 0.85 * 0.04, materialFraction: 1, displacesLiquid: false },
  { id: 'cabin', label: 'ห้องโดยสาร', description: 'โครงห้องโปร่งบนดาดฟ้า', placement: 'เหนือน้ำ', volumeFactor: 0.27 * 0.35 * 0.35, materialFraction: 0.3, displacesLiquid: false },
  { id: 'outriggers', label: 'ทุ่นข้างคู่', description: 'ทุ่นปิดสนิทสองข้าง เพิ่มแรงพยุง', placement: 'แนวน้ำ', volumeFactor: 2 * 0.72 * 0.18 * 0.34, materialFraction: 0.24, displacesLiquid: true },
  { id: 'flotation', label: 'ทุ่นลอยเสริม', description: 'ทุ่นกลวงปิดสนิท ติดเพิ่มได้ 1–4 ลูก', placement: 'แนวน้ำ', volumeFactor: 0.45 * 0.22 * 0.32, materialFraction: 0.15, displacesLiquid: true },
  { id: 'mast', label: 'เสากระโดง', description: 'เสากลมเหนือดาดฟ้า', placement: 'เหนือน้ำ', volumeFactor: Math.PI / 4 * 0.06 * 0.06 * 0.85, materialFraction: 1, displacesLiquid: false },
  { id: 'rudder', label: 'หางเสือ', description: 'แผ่นบังคับทิศทางท้ายเรือ', placement: 'ใต้น้ำ', volumeFactor: 0.12 * 0.08 * 0.34, materialFraction: 1, displacesLiquid: true },
];

export const defaultPart = (id: BoatPartId): BoatPart => ({
  id, material: id === 'flotation' ? 'plastic' : 'wood', scale: 1,
  ...(id === 'flotation' ? { quantity: flotationQuantity.default } : {}),
});

export function calculateParts(
  parts: BoatPart[],
  dimensions: { length: number; width: number; height: number },
  densities: Record<Material, number>,
) {
  const summaries = Object.fromEntries(partOptions.map((definition) => {
    const part = parts.find((item) => item.id === definition.id);
    const scale = part ? Math.min(1.5, Math.max(0.5, part.scale)) : 0;
    const quantity = definition.id === 'flotation' && part
      ? Math.min(flotationQuantity.max, Math.max(flotationQuantity.min, Math.round(part.quantity ?? flotationQuantity.default)))
      : 1;
    const boundingVolume = Math.max(0, dimensions.length) * Math.max(0, dimensions.width) * Math.max(0, dimensions.height);
    const outerCm3 = boundingVolume * definition.volumeFactor * scale ** 3 * quantity;
    const materialVolume = outerCm3 * definition.materialFraction / 1_000_000;
    return [definition.id, {
      materialVolume,
      displacementVolume: definition.displacesLiquid ? outerCm3 / 1_000_000 : 0,
      mass: materialVolume * (densities[part?.material ?? 'wood'] ?? densities.wood),
    }];
  })) as Record<BoatPartId, { materialVolume: number; displacementVolume: number; mass: number }>;
  const installed = parts.map((part) => summaries[part.id]);
  return {
    summaries,
    mass: installed.reduce((sum, item) => sum + item.mass, 0),
    materialVolume: installed.reduce((sum, item) => sum + item.materialVolume, 0),
    displacementVolume: installed.reduce((sum, item) => sum + item.displacementVolume, 0),
  };
}
