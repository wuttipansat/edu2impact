import { useCallback, useMemo, useState, type CSSProperties } from 'react';
import { useLocation } from 'wouter';
import { useQueryClient } from '@tanstack/react-query';
import { getGetRaceQueryKey, useCreateRace, useGetRace, useStartRace } from '@workspace/api-client-react';
import { QRCodeSVG } from 'qrcode.react';
import { ArrowLeft, ArrowRight, Check, Copy, Crown, FlaskConical, Link2, Play, RefreshCw, Ship, Users, Waves } from 'lucide-react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import BoatReview from './BoatReview';
import { BoatPartsVisual } from '../components/BoatPartsPanel';
import { appPath, appRoute } from '../lib/basePath';
import './race.css';

const colors = ['#087f91', '#e77958', '#b28528', '#568b78', '#766b9b', '#d15f7d', '#4479a8', '#8e754c'];
const fmt = (n: number, digits = 1) => Number.isFinite(n) ? n.toLocaleString('th-TH', { minimumFractionDigits: digits, maximumFractionDigits: digits }) : '—';
const shapeNames: Record<string, string> = {
  tray: 'เรือถาด', box: 'เรือกล่อง', canoe: 'เรือแคนู', cylinder: 'เรือทรงกระบอก',
  barge: 'เรือท้องแบน', catamaran: 'เรือสองท้อง', pontoon: 'เรือแพ', triangular: 'เรือสามเหลี่ยม',
};
const materialNames: Record<string, string> = { wood: 'ไม้', aluminum: 'อะลูมิเนียม', plastic: 'พลาสติก', steel: 'เหล็ก', paper: 'กระดาษ' };
const partNames: Record<string, string> = { keel: 'กระดูกงู', deck: 'ดาดฟ้า', cabin: 'ห้องโดยสาร', outriggers: 'ทุ่นข้าง', flotation: 'ทุ่นลอย', mast: 'เสากระโดง', rudder: 'หางเสือ' };

function RacePage() {
  const [, navigate] = useLocation();
  const queryClient = useQueryClient();
  const code = appRoute().match(/^\/race\/([^/]+)\/?$/)?.[1] ?? '';
  const createRace = useCreateRace();
  const startRace = useStartRace();
  const { data: room, isLoading, isError, error, refetch, isFetching } = useGetRace(code, {
    query: { enabled: !!code, queryKey: getGetRaceQueryKey(code), refetchInterval: 1000 },
  });
  const [actionError, setActionError] = useState('');
  const [copied, setCopied] = useState(false);
  const [tableOpen, setTableOpen] = useState(false);
  const [selectedBoatId, setSelectedBoatId] = useState<string | null>(null);
  const closeReview = useCallback(() => setSelectedBoatId(null), []);
  let hostToken: string | null = null;
  try { hostToken = code ? localStorage.getItem(`boat-race-host-${code}`) : null; }
  catch { hostToken = null; }
  const inviteUrl = code ? `${window.location.origin}${appPath('/')}?room=${encodeURIComponent(code)}` : '';
  const boats = room?.boats ?? [];
  const phase = room?.phase ?? 'waiting';
  const plotData = useMemo(() => {
    if (!room) return [];
    return Array.from({ length: Math.max(0, room.step) + 1 }, (_, step) => {
      const point: Record<string, number> = { mass: step * room.massStep };
      room.boats.forEach((boat) => {
        point[boat.id] = Math.min(100, Math.max(0, boat.capacityMass > 0 ? ((boat.boatMass + step * room.massStep) / boat.capacityMass) * 100 : 100));
      });
      return point;
    });
  }, [room]);

  async function create() {
    setActionError('');
    try {
      const result = await createRace.mutateAsync();
      localStorage.setItem(`boat-race-host-${result.room.code}`, result.hostToken);
      navigate(appPath(`/race/${result.room.code}`));
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'สร้างห้องไม่สำเร็จ กรุณาลองอีกครั้ง');
    }
  }

  async function start() {
    if (!code || !hostToken || boats.length < 2) return;
    setActionError('');
    try {
      await startRace.mutateAsync({ code, data: { hostToken } });
      await queryClient.invalidateQueries({ queryKey: getGetRaceQueryKey(code) });
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'เริ่มการแข่งขันไม่สำเร็จ กรุณาลองอีกครั้ง');
    }
  }

  async function copyInvite() {
    try {
      await navigator.clipboard.writeText(inviteUrl);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2500);
    } catch {
      setActionError('คัดลอกลิงก์ไม่สำเร็จ กรุณาคัดลอกจากแถบที่อยู่');
    }
  }

  return (
    <div className="race-page">
      <header className="race-header">
        <a href={appPath('/')} className="race-brand" data-testid="link-race-home" aria-label="กลับไปห้องทดลอง">
          <span className="race-brand-mark"><Waves size={22} strokeWidth={2.5} /></span>
          <span className="race-brand-type"><strong>ห้องทดลองเรือรับน้ำหนัก</strong><small>BUOYANCY / FIELD NOTE 01</small></span>
        </a>
        <a className="race-back" href={appPath('/')} data-testid="link-race-builder"><ArrowLeft size={15} /> กลับไปทดลองเรือ</a>
      </header>

      {!code ? (
        <main className="race-create">
          <div className="race-create-copy">
            <div className="race-kicker"><span className="race-kicker-line" /> CLASSROOM / RACE MODE</div>
            <h1>เรือของใคร<br /><em>จะลอยได้นานที่สุด?</em></h1>
            <p className="race-lede">เปลี่ยนการทดลองของทุกคนให้เป็นสนามเดียวกัน ออกแบบเรือ ส่งเข้าห้อง แล้วดูไปพร้อมกันว่าเมื่อน้ำหนักเพิ่มขึ้นทีละกิโลกรัม เรือแต่ละลำจะเป็นอย่างไร</p>
            <div className="race-create-action">
              <button type="button" className="race-primary" onClick={create} disabled={createRace.isPending} data-testid="button-create-race">
                {createRace.isPending ? 'กำลังเตรียมห้อง…' : 'สร้างห้องแข่งขัน'} <ArrowRight size={19} />
              </button>
              <span>สำหรับครูผู้สอน · ไม่ต้องสมัครสมาชิก</span>
            </div>
            {actionError && <div className="race-alert" role="alert" data-testid="status-create-error">{actionError} <button type="button" onClick={create} data-testid="button-retry-create">ลองอีกครั้ง</button></div>}
          </div>
          <div className="race-create-art" aria-hidden="true">
            <div className="race-art-label">THE FLOAT TEST <span>01—03</span></div>
            <div className="race-art-water">
              <div className="race-art-buoy buoy-one"><span>01</span><div className="race-art-hull" /></div>
              <div className="race-art-buoy buoy-two"><span>02</span><div className="race-art-hull" /></div>
              <div className="race-art-buoy buoy-three"><span>03</span><div className="race-art-hull" /></div>
              <div className="race-art-wave wave-one" /><div className="race-art-wave wave-two" /><div className="race-art-wave wave-three" />
            </div>
            <div className="race-art-bottom"><span>น้ำหนักเพิ่มขึ้น</span><span>เรือที่ยังลอยอยู่คือผู้ชนะ</span></div>
          </div>
          <div className="race-how">
            <div><span className="race-how-index">01 / เตรียมสนาม</span><strong>สร้างห้องและแชร์รหัส</strong><p>ฉาย QR ให้ทั้งห้องสแกน หรือส่งลิงก์ให้เพื่อนร่วมทดลอง</p></div>
            <div><span className="race-how-index">02 / ออกแบบ</span><strong>ทุกคนสร้างเรือของตัวเอง</strong><p>เลือกรูปทรง วัสดุ และขนาด ก่อนส่งเรือเข้าห้อง</p></div>
            <div><span className="race-how-index">03 / สังเกต</span><strong>ปล่อยน้ำหนักพร้อมกัน</strong><p>ดูเรือค่อย ๆ จม และอ่านกราฟการจมที่บันทึกให้อัตโนมัติ</p></div>
          </div>
        </main>
      ) : (
        <main className="race-room">
          {isLoading ? (
            <div className="race-loading" aria-label="กำลังโหลดห้องแข่งขัน" data-testid="status-race-loading">
              <div className="race-skeleton race-skeleton-heading" /><div className="race-skeleton race-skeleton-large" /><div className="race-skeleton race-skeleton-small" />
              <p>กำลังเตรียมสนามแข่งขัน…</p>
            </div>
          ) : isError || !room ? (
            <div className="race-problem" role="alert" data-testid="status-race-error">
              <span className="race-kicker">ROOM / UNAVAILABLE</span>
              <h1>ยังเข้าห้องนี้ไม่ได้</h1>
              <p>{error instanceof Error ? error.message : 'ไม่พบห้องแข่งขัน หรือการเชื่อมต่อขัดข้อง ลองตรวจรหัสห้องอีกครั้ง'}</p>
              <button type="button" className="race-primary" onClick={() => refetch()} data-testid="button-retry-race"><RefreshCw size={17} /> ลองเชื่อมต่อใหม่</button>
            </div>
          ) : (
            <>
              <section className="race-room-hero">
                <div className="race-room-title">
                  <div className="race-kicker"><span className="race-kicker-line" /> LIVE CLASSROOM EXPERIMENT</div>
                  <h1>สนามทดสอบ<span>การลอยตัว</span></h1>
                  <p>เรือทุกลำรับน้ำหนักเพิ่มขึ้นพร้อมกัน ใครจะยังลอยอยู่จนถึงกิโลกรัมสุดท้าย?</p>
                  <div className="race-room-state" data-testid="status-race-phase"><span className={`race-state-dot ${phase}`} />{phase === 'waiting' ? 'กำลังรอเรือเข้าห้อง' : phase === 'running' ? 'กำลังแข่งขัน · อัปเดตอัตโนมัติ' : 'การแข่งขันสิ้นสุดแล้ว'}{isFetching && <span className="race-sync-dot" title="กำลังอัปเดต" />}</div>
                </div>
                <div className="race-code-card">
                  <span>ROOM CODE / รหัสห้อง</span>
                  <strong data-testid="text-room-code">{room.code}</strong>
                  <small>ให้นักเรียนสแกนหรือใช้ลิงก์ด้านล่างเพื่อส่งเรือ</small>
                </div>
              </section>

              <div className="race-room-grid">
                <div className="race-room-main">
                  {phase === 'finished' && (
                    <section className="race-winner-banner" aria-live="polite" data-testid="status-race-winner">
                      <Crown size={27} strokeWidth={1.8} />
                      <div><span>RESULT / ผลการแข่งขัน</span><h2>{room.winnerIds.length ? 'เรือที่ลอยได้นานที่สุด' : 'สิ้นสุดการทดลอง'}</h2><p>{room.winnerIds.length ? boats.filter((boat) => room.winnerIds.includes(boat.id)).map((boat) => boat.name).join(' · ') : 'เรือทุกลำผ่านการทดสอบครบทุกขั้น'}</p></div>
                    </section>
                  )}

                  <section className="race-stage">
                    <div className="race-section-top">
                      <div><span className="race-section-number">01 / LIVE BASIN</span><h2>อ่างทดลอง</h2></div>
                      <div className="race-stage-counter"><span>น้ำหนักบรรทุกเพิ่ม</span><strong data-testid="text-added-mass">+{fmt(room.step * room.massStep, 0)} <small>กก.</small></strong></div>
                    </div>
                    <div className="race-stage-progress" aria-label={`ขั้นที่ ${room.step} จาก ${room.maxSteps}`}><div style={{ transform: `scaleX(${room.maxSteps ? Math.min(1, room.step / room.maxSteps) : 0})` }} /></div>
                    <div className="race-stage-subline"><span>ขั้นที่ {room.step} / {room.maxSteps}</span><span>เพิ่มครั้งละ {fmt(room.massStep, 0)} กก. · อัปเดตทุกวินาที</span></div>
                    {boats.length === 0 ? (
                      <div className="race-empty-basin" data-testid="status-race-empty"><div className="race-empty-hull"><Ship size={40} strokeWidth={1.5} /></div><strong>ยังไม่มีเรือในอ่าง</strong><p>แชร์รหัสห้องให้นักเรียนออกแบบและส่งเรือเข้ามา<br />เรือลำแรกจะปรากฏตรงนี้</p></div>
                    ) : (
                      <div className="race-boats" data-testid="list-race-boats">
                        {boats.map((boat, index) => {
                          const percent = Math.max(0, Math.min(100, boat.submergedPercent));
                          const sunk = boat.status === 'sink';
                          const winner = phase === 'finished' && room.winnerIds.includes(boat.id);
                           const { appearance } = boat;
                           const hullHeight = 60 * appearance.heightScale;
                           const shapeHeight = appearance.shape === 'cylinder' ? 0.88 : appearance.shape === 'barge' ? 0.93 : appearance.shape === 'triangular' ? 1.06 : 1;
                           const shapeBottomOffset = appearance.shape === 'cylinder' ? 7 : appearance.shape === 'triangular' ? -4 : 0;
                           const visualStyle = {
                             '--boat-color': colors[index % colors.length],
                             '--race-depth': `${shapeBottomOffset + .6 + percent / 100 * hullHeight * shapeHeight}px`,
                             '--race-sunk-offset': sunk ? '58px' : '0px',
                             '--race-roll': sunk ? '-19deg' : boat.status === 'overflow' ? '-4deg' : '0deg',
                             '--race-width': `${Math.max(92, Math.min(206, 132 * appearance.lengthScale))}px`,
                             '--hull-height': `${hullHeight}px`,
                             '--boat-beam-depth': `${Math.round(14 * appearance.beamScale)}px`,
                             '--boat-beam-inset': `${8 / appearance.beamScale}%`,
                             '--hull-wall': `${Math.max(2, 2.5 * appearance.wallScale)}px`,
                             '--submerged-cut': `${100 - percent}%`,
                           } as CSSProperties;
                          return (
                              <article className={`race-boat-card ${sunk ? 'is-sunk' : ''} ${winner ? 'is-winner' : ''} ${phase === 'finished' ? 'is-reviewable' : ''}`} key={boat.id} style={visualStyle} role={phase === 'finished' ? 'button' : undefined} tabIndex={phase === 'finished' ? 0 : undefined} aria-label={phase === 'finished' ? `อ่านบันทึกแบบเรือและผลทดลองของ ${boat.name}` : undefined} onClick={phase === 'finished' ? () => setSelectedBoatId(boat.id) : undefined} onKeyDown={phase === 'finished' ? event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); setSelectedBoatId(boat.id); } } : undefined} data-testid={`card-race-boat-${boat.id}`}>
                              <div className="race-boat-head"><div><span className="race-boat-index">BOAT {String(index + 1).padStart(2, '0')}</span><h3 data-testid={`text-boat-name-${boat.id}`}>{boat.name}</h3></div>{winner && <span className="race-crown" aria-label="เรือชนะ"><Crown size={19} fill="currentColor" /> ผู้ชนะ</span>}</div>
                                <div className="race-boat-pool" role="img" aria-label={`${shapeNames[appearance.shape]} วัสดุ ${materialNames[appearance.material]} ${appearance.parts.length ? `ติดตั้ง ${appearance.parts.map(part => `${partNames[part.id]}${part.id === 'flotation' ? ` ${part.quantity ?? 1} ลูก` : ''} วัสดุ ${materialNames[part.material]}`).join(', ')}` : 'ไม่มีชิ้นส่วนเสริม'} สถานะ ${sunk ? 'จมแล้ว' : boat.status === 'overflow' ? 'น้ำเข้าเรือ' : 'ลอยอยู่'}`} data-testid={`visual-race-boat-${boat.id}`}>
                                  <div className="race-boat-group boat-group" data-testid={`visual-race-hull-${boat.id}`} aria-hidden="true">
                                    <div className="boat-shadow" />
                                    <div className={`boat-hull shape-${appearance.shape} material-${appearance.material}`} />
                                    <div className={`boat-hull submerged-hull shape-${appearance.shape} material-${appearance.material}`} />
                                    <BoatPartsVisual parts={appearance.parts} />
                                  </div>
                                  <div className="race-boat-water" aria-hidden="true"><span className="race-boat-ripple" /><span className="race-boat-ripple second" /></div>
                                  <div className="race-boat-surface" aria-hidden="true" />
                                  <div className="race-boat-bubbles" aria-hidden="true"><i /><i /><i /><i /><i /></div>
                                  <span className="race-boat-waterlabel">{sunk ? 'จมแล้ว' : boat.status === 'overflow' ? 'น้ำเข้าเรือ' : phase === 'waiting' ? 'พร้อมลงน้ำ' : 'ยังลอยอยู่'}</span>
                              </div>
                                <div className="race-boat-data"><span>{shapeNames[appearance.shape]} · {materialNames[appearance.material]}{appearance.parts.length ? ` · ${appearance.parts.length} ชิ้นส่วน` : ''}</span><strong data-testid={`text-boat-percent-${boat.id}`}>{fmt(percent, 0)}% <small>ส่วนที่จม</small></strong></div>
                                {appearance.parts.length > 0 && <div className="race-boat-parts" data-testid={`list-race-parts-${boat.id}`}>{appearance.parts.map(part => <span key={part.id} data-testid={`text-race-part-${boat.id}-${part.id}`}>{partNames[part.id]}{part.id === 'flotation' ? ` ${part.quantity ?? 1} ลูก` : ''} <b>{materialNames[part.material]}</b></span>)}</div>}
                              <div className="race-boat-meter"><i style={{ transform: `scaleX(${percent / 100})` }} /></div>
                              <div className="race-boat-detail"><span>มวลเรือ {fmt(boat.boatMass, 2)} กก.</span><span>บรรทุกได้สูงสุด {fmt(Math.max(0, boat.capacityMass - boat.boatMass), 2)} กก.</span></div>
                              <div className="race-boat-detail"><span>ปริมาตรแทนที่ {fmt(boat.effectiveVolume, 3)} ม³</span><span>ยังรับเพิ่มได้ {fmt(Math.max(0, boat.capacityMass - boat.boatMass - room.step * room.massStep), 2)} กก.</span></div>
                              {phase !== 'waiting' && <div className={`race-boat-outcome ${sunk ? 'sunk' : ''}`}>{sunk ? `จมที่ขั้น ${boat.sinkStep}` : 'ยังรับน้ำหนักได้'}</div>}
                               {phase === 'finished' && <div className="race-boat-review-cue">เปิดบันทึกเรือลำนี้ <ArrowRight size={14} /></div>}
                            </article>
                          );
                        })}
                      </div>
                    )}
                  </section>

                  <section className="race-chart-section">
                    <div className="race-section-top"><div><span className="race-section-number">02 / FIELD DATA</span><h2>กราฟระดับการจม</h2></div><span className="race-chart-live">บันทึกอัตโนมัติ</span></div>
                    <p>เปรียบเทียบสัดส่วนเรือที่จมใต้ผิวน้ำ เมื่อน้ำหนักบรรทุกเพิ่มขึ้นพร้อมกันทีละ {fmt(room.massStep, 0)} กก.</p>
                    {boats.length === 0 ? <div className="race-chart-empty">กราฟจะเริ่มแสดงเมื่อมีเรือเข้าร่วม</div> : (
                      <>
                        <div className="race-chart-wrap" data-testid="chart-race-submerged">
                          <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={plotData} margin={{ top: 12, right: 17, bottom: 7, left: -14 }}>
                              <CartesianGrid stroke="#dbe9e6" strokeDasharray="3 5" vertical={false} />
                              <XAxis dataKey="mass" tickLine={false} axisLine={false} tick={{ fill: '#789399', fontSize: 11 }} tickFormatter={(value: number) => `${value}`} type="number" domain={[0, 'dataMax']} allowDecimals={false} />
                              <YAxis domain={[0, 100]} tickLine={false} axisLine={false} tick={{ fill: '#789399', fontSize: 11 }} tickFormatter={(value: number) => `${value}%`} />
                              <Tooltip contentStyle={{ background: '#f9fcf9', border: '1px solid #c6dedc', borderRadius: 12, fontFamily: 'IBM Plex Sans Thai', fontSize: 12 }} labelFormatter={(value) => `น้ำหนักบรรทุกเพิ่ม ${value} กก.`} formatter={(value, name) => [`${fmt(Number(value), 1)}%`, boats.find((boat) => boat.id === name)?.name ?? String(name)]} />
                              {boats.map((boat, index) => <Line key={boat.id} type="monotone" dataKey={boat.id} name={boat.id} stroke={colors[index % colors.length]} strokeWidth={2.5} dot={plotData.length < 25 ? { r: 2.5, strokeWidth: 0 } : false} activeDot={{ r: 5 }} isAnimationActive={false} />)}
                            </LineChart>
                          </ResponsiveContainer>
                        </div>
                        <div className="race-chart-axis">น้ำหนักบรรทุกที่เพิ่ม (กก.) <ArrowRight size={13} /></div>
                        <div className="race-chart-legend">{boats.map((boat, index) => <span key={boat.id}><i style={{ background: colors[index % colors.length] }} />{boat.name}</span>)}</div>
                        <details className="race-chart-table-details" onToggle={event => setTableOpen(event.currentTarget.open)}>
                          <summary>ดูข้อมูลกราฟเป็นตาราง</summary>
                          {tableOpen && <div className="race-chart-table-scroll">
                            <table>
                              <caption>ส่วนที่จมของเรือแต่ละลำตามมวลบรรทุกที่เพิ่มขึ้น</caption>
                              <thead><tr><th scope="col">มวลเพิ่ม (กก.)</th>{boats.map(boat => <th scope="col" key={boat.id}>{boat.name} (%)</th>)}</tr></thead>
                              <tbody>{plotData.map(point => <tr key={point.mass}><th scope="row">{fmt(point.mass, 0)}</th>{boats.map(boat => <td key={boat.id}>{fmt(point[boat.id], 1)}</td>)}</tr>)}</tbody>
                            </table>
                          </div>}
                        </details>
                      </>
                    )}
                    <div className="race-science-note"><FlaskConical size={19} /><p><strong>สังเกตอะไร?</strong> เส้นที่สูงขึ้นหมายถึงเรือจมลึกขึ้น เมื่อมวลรวมมากกว่ามวลน้ำที่เรือแทนที่ได้ เรือจะจม กราฟหยุดที่ 100%</p></div>
                  </section>
                </div>

                <aside className="race-sidebar">
                  <section className="race-invite-card">
                    <div className="race-aside-heading"><span className="race-section-number">INVITE / ชวนเข้าห้อง</span><Link2 size={18} /></div>
                    <h2>มาลองลอยเรือด้วยกัน</h2>
                    <p>ให้นักเรียนสแกน QR เพื่อไปที่หน้าออกแบบเรือ เมื่อส่งเรือแล้วจะปรากฏในสนามนี้ทันที</p>
                    <div className="race-qr" data-testid="image-race-qr"><QRCodeSVG value={inviteUrl} size={164} bgColor="#f8fcf8" fgColor="#114e5d" level="M" marginSize={0} /></div>
                    <div className="race-invite-url" title={inviteUrl} data-testid="text-invite-url">{inviteUrl}</div>
                    <button type="button" className="race-copy" onClick={copyInvite} data-testid="button-copy-invite">{copied ? <Check size={16} /> : <Copy size={16} />}{copied ? 'คัดลอกแล้ว' : 'คัดลอกลิงก์เข้าร่วม'}</button>
                    <a href={inviteUrl} className="race-invite-link" data-testid="link-join-race">เปิดหน้าออกแบบเรือ <ArrowRight size={14} /></a>
                  </section>
                  <section className="race-control-card">
                    <div className="race-aside-heading"><span className="race-section-number">TEACHER / ควบคุมการทดลอง</span><Users size={18} /></div>
                    <div className="race-count"><strong data-testid="text-boat-count">{boats.length}</strong><span>ลำเข้าร่วมแล้ว</span></div>
                    {phase === 'waiting' ? (
                      hostToken ? (
                        <>
                          <button type="button" className="race-start" onClick={start} disabled={boats.length < 2 || startRace.isPending} data-testid="button-start-race"><Play size={18} fill="currentColor" />{startRace.isPending ? 'กำลังเริ่ม…' : 'เริ่มการแข่งขัน'}</button>
                          <p className="race-control-hint">{boats.length < 2 ? `รออีก ${2 - boats.length} ลำเพื่อเริ่มการแข่งขัน` : 'พร้อมแล้ว! ทุกลำจะรับน้ำหนักเพิ่มขึ้นพร้อมกัน'}</p>
                        </>
                      ) : <p className="race-control-hint">เฉพาะครูที่สร้างห้องบนอุปกรณ์นี้เท่านั้นที่เริ่มการแข่งขันได้</p>
                    ) : <p className="race-control-hint">{phase === 'running' ? 'การแข่งขันเริ่มแล้ว ผลจะอัปเดตให้ทุกคนโดยอัตโนมัติ' : 'บันทึกผลเรียบร้อยแล้ว ดูผู้ชนะและกราฟด้านซ้าย'}</p>}
                    {actionError && <div className="race-alert" role="alert" data-testid="status-start-error">{actionError}</div>}
                  </section>
                  <div className="race-aside-foot"><span>LAB NOTE / 02</span><p>ยิ่งเรือแทนที่น้ำได้มาก ก็ยิ่งรับน้ำหนักได้มาก ลองเทียบเรือแต่ละลำดู</p></div>
                </aside>
              </div>
            </>
          )}
        </main>
      )}
      <footer className="race-footer"><span>ห้องทดลองเรือรับน้ำหนัก</span><span>ทดลอง · สังเกต · อธิบาย</span></footer>
      {phase === 'finished' && selectedBoatId && boats.some(boat => boat.id === selectedBoatId) && <BoatReview key={selectedBoatId} code={code} boatId={selectedBoatId} onClose={closeReview} />}
    </div>
  );
}

export default RacePage;