import type { CSSProperties } from 'react';
import { Anchor, LockKeyhole, Plus, X } from 'lucide-react';
import type { BoatPart, BoatPartId, Material } from '../lib/boatParts';
import { flotationQuantity, partOptions } from '../lib/boatParts';
import './boat-parts.css';

type PartSummary = { mass: number; materialVolume: number };

type BoatPartsPanelProps = {
  parts: BoatPart[];
  onToggle: (id: BoatPartId) => void;
  onChange: (id: BoatPartId, patch: Partial<Pick<BoatPart, 'material' | 'scale' | 'quantity'>>) => void;
  summaries: Record<BoatPartId, PartSummary>;
  materialOptions: { id: Material; label: string; density: number }[];
  hullMass: number;
  hullMaterialVolume: number;
  totalBoatMass: number;
  boatDensity: number;
};

const partOrder: BoatPartId[] = ['keel', 'deck', 'cabin', 'outriggers', 'flotation', 'mast', 'rudder'];
const partIndex: Record<BoatPartId, string> = {
  keel: '01', deck: '02', cabin: '03', outriggers: '04', flotation: '05', mast: '06', rudder: '07',
};

const kilograms = (value: number) => `${Number.isFinite(value) ? value.toFixed(value < 0.1 && value !== 0 ? 3 : 2) : '0.00'} กก.`;
const cubicCentimeters = (value: number) => `${Number.isFinite(value) ? (value * 1_000_000).toLocaleString('th-TH', { maximumFractionDigits: 0 }) : '0'} ซม³`;

export default function BoatPartsPanel({
  parts, onToggle, onChange, summaries, materialOptions,
  hullMass, hullMaterialVolume, totalBoatMass, boatDensity,
}: BoatPartsPanelProps) {
  return (
    <section className="boat-parts-panel" aria-labelledby="boat-parts-title" data-testid="boat-parts-panel">
      <div className="boat-parts-heading">
        <div>
          <span className="boat-parts-kicker">BUILD / 01—07</span>
          <h3 id="boat-parts-title">ต่อเติมเรือ</h3>
          <p>เลือกชิ้นส่วน แล้วลองดูว่าเรือเปลี่ยนไปอย่างไร</p>
        </div>
        <span className="boat-parts-count" data-testid="text-installed-parts">{parts.length}<small> / {partOrder.length}</small></span>
      </div>

      <div className="boat-parts-hull" data-testid="card-fixed-hull">
        <span className="boat-parts-hull-icon" aria-hidden="true"><Anchor size={17} strokeWidth={2} /></span>
        <div className="boat-parts-hull-copy">
          <strong>ลำเรือหลัก <span>ชิ้นส่วนพื้นฐาน</span></strong>
          <small>มวล {kilograms(hullMass)} · วัสดุ {cubicCentimeters(hullMaterialVolume)}</small>
        </div>
        <LockKeyhole size={13} className="boat-parts-lock" aria-label="ถอดไม่ได้" />
      </div>

      <div className="boat-parts-list">
        {partOrder.map((id) => {
          const option = partOptions.find((item) => item.id === id);
          const part = parts.find((item) => item.id === id);
          const summary = summaries[id];
          if (!option) return null;
          return (
            <article className={`boat-part-card${part ? ' is-installed' : ''}`} key={id} data-testid={`card-boat-part-${id}`}>
              <div className="boat-part-top">
                <span className={`boat-part-thumbnail boat-part-thumbnail-${id}`} aria-hidden="true"><i /></span>
                <div className="boat-part-identity">
                  <span className="boat-part-placement">{partIndex[id]} / {option.placement}</span>
                  <strong>{option.label}</strong>
                  <small>{option.description}</small>
                </div>
                <button
                  type="button"
                  className={`boat-part-toggle${part ? ' is-installed' : ''}`}
                  onClick={() => onToggle(id)}
                  aria-label={`${part ? 'ถอด' : 'เพิ่ม'}${option.label}`}
                  aria-pressed={!!part}
                  data-testid={`button-toggle-part-${id}`}
                >
                  {part ? <X size={16} strokeWidth={2.3} /> : <Plus size={17} strokeWidth={2.3} />}
                </button>
              </div>
              {part && (
                <div className="boat-part-controls" data-testid={`controls-part-${id}`}>
                  <div className="boat-part-fields">
                    <label className="boat-part-material-field">
                      <span>วัสดุ</span>
                      <select
                        value={part.material}
                        onChange={(event) => onChange(id, { material: event.target.value as Material })}
                        aria-label={`วัสดุ${option.label}`}
                        data-testid={`select-part-material-${id}`}
                      >
                        {materialOptions.map((material) => (
                          <option key={material.id} value={material.id}>{material.label}</option>
                        ))}
                      </select>
                    </label>
                    <label className="boat-part-scale-field">
                      <span>ขนาด <output data-testid={`text-part-scale-${id}`}>{Math.round(part.scale * 100)}%</output></span>
                      <input
                        type="range"
                        min="0.5"
                        max="1.5"
                        step="0.1"
                        value={part.scale}
                        onChange={(event) => onChange(id, { scale: Number(event.target.value) })}
                        aria-label={`ขนาด${option.label}`}
                        data-testid={`input-part-scale-${id}`}
                        style={{ '--range-progress': `${((part.scale - 0.5) / 1) * 100}%` } as CSSProperties}
                      />
                    </label>
                  </div>
                  {id === 'flotation' && (
                    <label className="boat-part-quantity-field">
                      <span>จำนวนทุ่นลอยเสริม</span>
                      <select
                        value={part.quantity ?? flotationQuantity.default}
                        onChange={(event) => onChange(id, { quantity: Number(event.target.value) })}
                        aria-label="จำนวนทุ่นลอยเสริม"
                        data-testid="select-flotation-quantity"
                      >
                        {Array.from({ length: flotationQuantity.max - flotationQuantity.min + 1 }, (_, index) => index + flotationQuantity.min)
                          .map((count) => <option key={count} value={count}>{count} ลูก</option>)}
                      </select>
                    </label>
                  )}
                  <div className="boat-part-metrics">
                    <span title="มวลของชิ้นส่วน">มวล <b data-testid={`text-part-mass-${id}`}>{kilograms(summary?.mass ?? 0)}</b></span>
                    <span title="ปริมาตรวัสดุที่ใช้">วัสดุ <b data-testid={`text-part-material-volume-${id}`}>{cubicCentimeters(summary?.materialVolume ?? 0)}</b></span>
                  </div>
                </div>
              )}
            </article>
          );
        })}
      </div>

      <div className="boat-parts-total" aria-live="polite">
        <span>เรือที่ประกอบแล้ว</span>
        <div><span>มวลเรือ <strong data-testid="text-total-boat-mass">{kilograms(totalBoatMass)}</strong></span><span>ความหนาแน่นเรือเปล่า <strong data-testid="text-boat-density">{Number.isFinite(boatDensity) ? boatDensity.toLocaleString('th-TH', { maximumFractionDigits: 0 }) : '0'} กก./ม³</strong></span></div>
      </div>
    </section>
  );
}

/** Place inside .boat-group, alongside .boat-hull. Decorative and deliberately not to scale. */
export function BoatPartsVisual({ parts }: { parts: BoatPart[] }) {
  return (
    <div className="boat-parts-visual" aria-hidden="true" data-testid="visual-boat-parts">
      {parts.map((part) => (
        <span
          key={part.id}
          className={`boat-visual-piece boat-visual-${part.id}`}
          data-material={part.material}
          data-testid={`visual-boat-part-${part.id}`}
          style={{ '--piece-scale': Math.min(1.5, Math.max(0.5, part.scale)) } as CSSProperties}
        >
          {part.id === 'outriggers' ? <><i /><i /></> : part.id === 'flotation'
            ? Array.from({ length: Math.min(flotationQuantity.max, Math.max(flotationQuantity.min, part.quantity ?? flotationQuantity.default)) }, (_, index) => <i key={index} />)
            : <i />}
        </span>
      ))}
    </div>
  );
}