import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { getGetRaceBoatReviewQueryKey, useGetRaceBoatReview, usePostRaceBoatComment } from '@workspace/api-client-react';
import { ArrowLeft, ArrowRight, FlaskConical, MessageCircle, RefreshCw, Send, X } from 'lucide-react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import './boat-review.css';

const materials: Record<string, string> = { wood: 'ไม้', aluminum: 'อะลูมิเนียม', plastic: 'พลาสติก', steel: 'เหล็ก', paper: 'กระดาษ' };
const shapes: Record<string, string> = { tray: 'เรือถาด', box: 'เรือกล่อง', canoe: 'เรือแคนู', cylinder: 'เรือทรงกระบอก', barge: 'เรือท้องแบน', catamaran: 'เรือสองท้อง', pontoon: 'เรือแพ', triangular: 'เรือสามเหลี่ยม' };
const parts: Record<string, string> = { keel: 'กระดูกงู', deck: 'ดาดฟ้า', cabin: 'ห้องโดยสาร', outriggers: 'ทุ่นข้าง', flotation: 'ทุ่นลอย', mast: 'เสากระโดง', rudder: 'หางเสือ' };
const liquids: Record<string, string> = { freshwater: 'น้ำจืด', seawater: 'น้ำทะเล', 'vegetable-oil': 'น้ำมันพืช', 'machine-oil': 'น้ำมันเครื่อง', alcohol: 'แอลกอฮอล์', glycerin: 'กลีเซอรีน', syrup: 'น้ำเชื่อม', honey: 'น้ำผึ้ง', mercury: 'ปรอท' };
const fmt = (value: number, digits = 1) => Number.isFinite(value) ? value.toLocaleString('th-TH', { maximumFractionDigits: digits }) : '—';
const dateLabel = (value: string) => {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' });
};

type ReviewProps = { code: string; boatId: string; onClose: () => void };

function BoatReview({ code, boatId, onClose }: ReviewProps) {
  const queryClient = useQueryClient();
  const { data: review, isLoading, isError, error, refetch, isFetching } = useGetRaceBoatReview(code, boatId, {
    query: { enabled: !!code && !!boatId, queryKey: getGetRaceBoatReviewQueryKey(code, boatId), refetchInterval: 4000 },
  });
  const postComment = usePostRaceBoatComment();
  const [body, setBody] = useState('');
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [postingError, setPostingError] = useState('');
  const [success, setSuccess] = useState('');
  let participantToken: string | null = null;
  let hostToken: string | null = null;
  try {
    participantToken = localStorage.getItem(`boat-race-participant-${code}`);
    hostToken = localStorage.getItem(`boat-race-host-${code}`);
  } catch { /* storage may be disabled */ }
  const canComment = !!participantToken || !!hostToken;
  const closeRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    document.body.style.overflow = 'hidden';
    closeRef.current?.focus();
    function handleKey(event: KeyboardEvent) {
      if (event.key === 'Escape') { onClose(); return; }
      if (event.key !== 'Tab' || !dialogRef.current) return;
      const focusable = Array.from(dialogRef.current.querySelectorAll<HTMLElement>('button:not([disabled]),textarea:not([disabled]),a[href],summary,[tabindex]:not([tabindex="-1"])'));
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (!first || !last) return;
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    document.addEventListener('keydown', handleKey);
    return () => { document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', handleKey); previouslyFocused?.focus(); };
  }, [onClose]);

  async function submit(event: FormEvent<HTMLFormElement>, parentId?: string) {
    event.preventDefault();
    const text = (parentId ? replyBody : body).trim();
    if (!canComment || !text || text.length > 500 || postComment.isPending) return;
    setPostingError('');
    setSuccess('');
    try {
      await postComment.mutateAsync({ code, boatId, data: {
        body: text, ...(parentId ? { parentId } : {}),
        ...(participantToken ? { participantToken } : { hostToken: hostToken! }),
      } });
      await queryClient.invalidateQueries({ queryKey: getGetRaceBoatReviewQueryKey(code, boatId) });
      if (parentId) { setReplyBody(''); setReplyTo(null); } else setBody('');
      setSuccess(parentId ? 'ส่งคำตอบแล้ว' : 'ส่งความคิดเห็นแล้ว');
    } catch (cause) {
      setPostingError(cause instanceof Error ? cause.message : 'ส่งไม่สำเร็จ กรุณาลองอีกครั้ง');
    }
  }

  const experiments = review?.experiments ?? [];
  const plot = experiments.map((item, index) => ({ ...item, trial: index + 1 }));
  const roots = review?.comments.filter(comment => !comment.parentId) ?? [];

  return (
    <div className="boat-review-backdrop" onMouseDown={event => { if (event.target === event.currentTarget) onClose(); }}>
      <div className="boat-review" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="boat-review-title" aria-describedby="boat-review-intro" data-testid="dialog-boat-review">
        <div className="boat-review-toolbar">
          <span>FIELD NOTE / POST-RACE REVIEW</span>
          <button type="button" ref={closeRef} onClick={onClose} aria-label="ปิดบันทึกเรือ" data-testid="button-close-boat-review"><X size={19} /></button>
        </div>
        {isLoading ? (
          <div className="boat-review-loading" aria-label="กำลังโหลดบันทึกเรือ" data-testid="status-boat-review-loading"><div /><div /><div /><p>กำลังเปิดบันทึกเรือ…</p></div>
        ) : isError || !review ? (
          <div className="boat-review-error" role="alert" data-testid="status-boat-review-error">
            <FlaskConical size={30} /><h2 id="boat-review-title">เปิดบันทึกไม่สำเร็จ</h2>
            <p id="boat-review-intro">{error instanceof Error ? error.message : 'ข้อมูลเรือลำนี้ยังไม่พร้อม กรุณาลองอีกครั้ง'}</p>
            <button type="button" onClick={() => refetch()} data-testid="button-retry-boat-review"><RefreshCw size={16} /> ลองอีกครั้ง</button>
          </div>
        ) : (
          <>
            <header className="boat-review-hero">
              <div><span className="boat-review-eyebrow">DESIGN RECORD / แบบเรือที่ส่งจริง</span><h2 id="boat-review-title">{review.name}</h2><p id="boat-review-intro">เปิดดูแบบเรือและผลการทดลองก่อนแข่งของเพื่อนร่วมชั้นได้ที่นี่ ข้อมูลนี้อ่านได้อย่างเดียว</p></div>
              <div className="boat-review-stamp"><span>BOAT</span><strong>{shapes[review.design.shape] ?? review.design.shape}</strong><small>บันทึกหลังการแข่งขัน</small></div>
            </header>

            <section className="boat-review-section" aria-labelledby="review-design-title">
              <div className="boat-review-section-head"><span>01 / CONSTRUCTION</span><h3 id="review-design-title">แบบเรือที่ลงสนาม</h3></div>
              <div className="boat-review-design">
                <div className="boat-review-materials">
                  <h4>สัดส่วนวัสดุ</h4>
                  {review.design.materialMix.map((mix, index) => <div className="boat-review-mix" key={`${mix.material}-${index}`}>
                    <div><span>{materials[mix.material] ?? mix.material}</span><strong>{fmt(mix.share)}%</strong></div>
                    <div className="boat-review-mix-track"><i style={{ transform: `scaleX(${Math.max(0, Math.min(100, mix.share)) / 100})` }} /></div>
                  </div>)}
                </div>
                <div className="boat-review-spec">
                  <h4>รูปทรงและขนาด</h4>
                  <dl>
                    <div><dt>รูปทรง</dt><dd>{shapes[review.design.shape] ?? review.design.shape}</dd></div>
                    <div><dt>ความยาว</dt><dd>{fmt(review.design.length, 2)} ซม.</dd></div>
                    <div><dt>ความกว้าง</dt><dd>{fmt(review.design.width, 2)} ซม.</dd></div>
                    <div><dt>ความสูง</dt><dd>{fmt(review.design.height, 2)} ซม.</dd></div>
                    <div><dt>ความหนา</dt><dd>{fmt(review.design.thickness, 2)} ซม.</dd></div>
                  </dl>
                </div>
              </div>
              <div className="boat-review-parts"><h4>ชิ้นส่วนเพิ่มเติม <span>{review.design.parts.length} รายการ</span></h4>
                {review.design.parts.length ? <ul>{review.design.parts.map((part, index) => <li key={`${part.id}-${index}`}><strong>{parts[part.id] ?? part.id}</strong><span>{materials[part.material] ?? part.material} · ขนาด ×{fmt(part.scale, 2)} · จำนวน {part.quantity ?? 1} ชิ้น</span></li>)}</ul> : <p>ไม่มีชิ้นส่วนเพิ่มเติมในแบบเรือลำนี้</p>}
              </div>
            </section>

            <section className="boat-review-section" aria-labelledby="review-lab-title">
              <div className="boat-review-section-head"><span>02 / PRIOR LAB</span><h3 id="review-lab-title">ผลทดลองก่อนลงสนาม</h3><p>ข้อมูลที่ผู้ส่งเรือแนบมา ไม่ใช่ผลการแข่งขันและระบบไม่ได้ตรวจสอบค่าทดลองย้อนหลัง</p></div>
              {!experiments.length ? <div className="boat-review-empty" data-testid="status-boat-review-no-experiments"><FlaskConical size={25} /><strong>ไม่มีบันทึกการทดลองก่อนแข่ง</strong><p>ผู้ส่งเรือลำนี้ไม่ได้แนบผลการทดลอง จึงยังไม่มีข้อมูลสำหรับกราฟทั้งสอง</p></div> : (
                <>
                  <div className="boat-review-charts">
                    <div className="boat-review-chart-card"><div className="boat-review-chart-heading"><span>GRAPH A / WEIGHT</span><h4>น้ำหนักที่วัดได้</h4><p>แกนตั้ง: นิวตัน (N)</p></div><div className="boat-review-chart" role="img" aria-label="กราฟน้ำหนักชั่งเหนือน้ำ น้ำหนักชั่งใต้น้ำ และน้ำหนักของเหลวที่แทนที่ตามลำดับการทดลอง"><ResponsiveContainer width="100%" height="100%"><LineChart data={plot} margin={{ top: 12, right: 14, bottom: 4, left: 0 }}><CartesianGrid vertical={false} stroke="#d7e9e5" strokeDasharray="3 5" /><XAxis dataKey="trial" tickLine={false} axisLine={false} tick={{ fill: '#6e8b91', fontSize: 11 }} allowDecimals={false} /><YAxis width={48} tickLine={false} axisLine={false} tick={{ fill: '#6e8b91', fontSize: 11 }} tickFormatter={value => fmt(Number(value), 0)} /><Tooltip labelFormatter={value => `ครั้งที่ ${value}`} formatter={(value, name) => [`${fmt(Number(value), 2)} N`, name]} contentStyle={{ background: '#f7fbf8', border: '1px solid #c7e1e2', borderRadius: 10, fontFamily: 'IBM Plex Sans Thai', fontSize: 12 }} /><Legend verticalAlign="bottom" wrapperStyle={{ fontSize: 11, paddingTop: 8 }} /><Line type="linear" dataKey="aboveWaterWeight" name="ชั่งเหนือน้ำ" stroke="#087f91" strokeWidth={2.5} dot={{ r: 3 }} isAnimationActive={false} /><Line type="linear" dataKey="underwaterWeight" name="ชั่งใต้น้ำ" stroke="#e77958" strokeWidth={2.5} dot={{ r: 3 }} isAnimationActive={false} /><Line type="linear" dataKey="displacedLiquidWeight" name="ของเหลวที่แทนที่" stroke="#b28528" strokeWidth={2.5} dot={{ r: 3 }} isAnimationActive={false} /></LineChart></ResponsiveContainer></div><div className="boat-review-x-axis">ลำดับการทดลอง <ArrowRight size={13} /></div></div>
                    <div className="boat-review-chart-card"><div className="boat-review-chart-heading"><span>GRAPH B / DENSITY</span><h4>ความหนาแน่น</h4><p>แกนตั้ง: กก./ม³ (kg/m³)</p></div><div className="boat-review-chart" role="img" aria-label="กราฟความหนาแน่นรวมและความหนาแน่นของเหลวตามลำดับการทดลอง"><ResponsiveContainer width="100%" height="100%"><LineChart data={plot} margin={{ top: 12, right: 14, bottom: 4, left: 0 }}><CartesianGrid vertical={false} stroke="#d7e9e5" strokeDasharray="3 5" /><XAxis dataKey="trial" tickLine={false} axisLine={false} tick={{ fill: '#6e8b91', fontSize: 11 }} allowDecimals={false} /><YAxis width={58} tickLine={false} axisLine={false} tick={{ fill: '#6e8b91', fontSize: 11 }} tickFormatter={value => fmt(Number(value), 0)} /><Tooltip labelFormatter={value => `ครั้งที่ ${value}`} formatter={(value, name) => [`${fmt(Number(value), 2)} กก./ม³`, name]} contentStyle={{ background: '#f7fbf8', border: '1px solid #c7e1e2', borderRadius: 10, fontFamily: 'IBM Plex Sans Thai', fontSize: 12 }} /><Legend verticalAlign="bottom" wrapperStyle={{ fontSize: 11, paddingTop: 8 }} /><Line type="linear" dataKey="totalDensity" name="ความหนาแน่นรวม" stroke="#087f91" strokeWidth={2.5} dot={{ r: 3 }} isAnimationActive={false} /><Line type="linear" dataKey="liquidDensity" name="ความหนาแน่นของเหลว" stroke="#e77958" strokeWidth={2.5} dot={{ r: 3 }} isAnimationActive={false} /></LineChart></ResponsiveContainer></div><div className="boat-review-x-axis">ลำดับการทดลอง <ArrowRight size={13} /></div></div>
                  </div>
                  <details className="boat-review-data"><summary data-testid="summary-boat-review-data">อ่านค่าทดลองทั้งหมดเป็นตาราง</summary><div className="boat-review-table-scroll"><table><caption>บันทึกการทดลองก่อนแข่งตามลำดับที่ส่งมา</caption><thead><tr><th scope="col">ครั้งที่</th><th scope="col">บันทึกเมื่อ</th><th scope="col">ของเหลว</th><th scope="col">เหนือน้ำ (N)</th><th scope="col">ใต้น้ำ (N)</th><th scope="col">ของเหลวที่แทนที่ (N)</th><th scope="col">ความหนาแน่นรวม (กก./ม³)</th><th scope="col">ความหนาแน่นของเหลว (กก./ม³)</th><th scope="col">ส่วนที่จม (%)</th></tr></thead><tbody>{experiments.map((item, index) => <tr key={index}><th scope="row">{index + 1}</th><td>{dateLabel(item.recordedAt)}</td><td>{liquids[item.liquid] ?? item.liquid}</td><td>{fmt(item.aboveWaterWeight, 2)}</td><td>{fmt(item.underwaterWeight, 2)}</td><td>{fmt(item.displacedLiquidWeight, 2)}</td><td>{fmt(item.totalDensity, 2)}</td><td>{fmt(item.liquidDensity, 2)}</td><td>{fmt(item.submergedPercent, 2)}</td></tr>)}</tbody></table></div></details>
                </>
              )}
            </section>

            <section className="boat-review-section boat-review-discussion" aria-labelledby="review-comments-title">
              <div className="boat-review-section-head"><span>03 / CLASS NOTES</span><h3 id="review-comments-title">แลกเปลี่ยนข้อสังเกต</h3><p>กลุ่มที่ส่งเรือและเจ้าของห้องถามหรือตอบได้ โดยทุกข้อความแสดงเป็น “ไม่ระบุชื่อ”</p></div>
              {canComment ? <form className="boat-review-comment-form" onSubmit={submit}>
                <label htmlFor="boat-review-comment">เขียนข้อสังเกตแบบไม่ระบุชื่อ</label>
                <textarea id="boat-review-comment" value={body} onChange={event => setBody(event.target.value)} maxLength={500} rows={3} placeholder="เช่น วัสดุหรือรูปทรงส่งผลต่อการลอยอย่างไร?" data-testid="input-boat-review-comment" />
                <div className="boat-review-form-foot"><span>{body.length}/500 ตัวอักษร</span><button type="submit" disabled={!body.trim() || postComment.isPending} data-testid="button-post-boat-review-comment"><Send size={15} /> {postComment.isPending && !replyTo ? 'กำลังส่ง…' : 'ส่งข้อสังเกต'}</button></div>
              </form> : <p className="boat-review-comment-notice">อ่านความคิดเห็นได้ทุกคน แต่เฉพาะกลุ่มที่ส่งเรือหรือเจ้าของห้องบนอุปกรณ์นี้เท่านั้นที่แสดงความคิดเห็นได้</p>}
              {postingError && <p className="boat-review-post-error" role="alert" data-testid="status-boat-review-post-error">{postingError}</p>}
              {success && <p className="boat-review-post-success" role="status" data-testid="status-boat-review-post-success">{success}</p>}
              <div className="boat-review-comments-head"><strong>บันทึกจากห้องเรียน <span>{review.comments.length}</span></strong>{isFetching && <small>กำลังอัปเดต</small>}</div>
              {review.comments.length === 0 ? <div className="boat-review-empty" data-testid="status-boat-review-no-comments"><MessageCircle size={25} /><strong>ยังไม่มีข้อสังเกต</strong><p>เริ่มต้นชวนเพื่อนคุยเกี่ยวกับเรือลำนี้ได้เลย</p></div> : <div className="boat-review-comments">{roots.map(comment => (
                <article className="boat-review-comment" key={comment.id} data-testid={`comment-boat-review-${comment.id}`}>
                  <div className="boat-review-comment-meta"><strong>ไม่ระบุชื่อ</strong><time dateTime={comment.createdAt}>{dateLabel(comment.createdAt)}</time></div><p>{comment.body}</p>
                  {canComment && <button type="button" className="boat-review-reply-button" onClick={() => { setReplyTo(replyTo === comment.id ? null : comment.id); setReplyBody(''); setPostingError(''); setSuccess(''); }} data-testid={`button-reply-boat-review-${comment.id}`}>{replyTo === comment.id ? 'ยกเลิก' : 'ตอบกลับ'}</button>}
                  {review.comments.filter(reply => reply.parentId === comment.id).map(reply => <div className="boat-review-reply" key={reply.id} data-testid={`reply-boat-review-${reply.id}`}><div className="boat-review-comment-meta"><strong>ไม่ระบุชื่อ</strong><time dateTime={reply.createdAt}>{dateLabel(reply.createdAt)}</time></div><p>{reply.body}</p></div>)}
                  {replyTo === comment.id && <form className="boat-review-comment-form boat-review-reply-form" onSubmit={event => submit(event, comment.id)}><label htmlFor={`boat-review-reply-${comment.id}`}>ตอบกลับแบบไม่ระบุชื่อ</label><textarea id={`boat-review-reply-${comment.id}`} value={replyBody} onChange={event => setReplyBody(event.target.value)} maxLength={500} rows={2} placeholder="เขียนคำตอบของคุณ" data-testid={`input-reply-boat-review-${comment.id}`} /><div className="boat-review-form-foot"><span>{replyBody.length}/500 ตัวอักษร</span><button type="submit" disabled={!replyBody.trim() || postComment.isPending} data-testid={`button-post-reply-boat-review-${comment.id}`}><Send size={14} /> {postComment.isPending ? 'กำลังส่ง…' : 'ส่งคำตอบ'}</button></div></form>}
                </article>
              ))}</div>}
            </section>
            <div className="boat-review-bottom"><button type="button" onClick={onClose} data-testid="button-back-to-race"><ArrowLeft size={16} /> กลับไปดูผลการแข่งขัน</button><span>READ ONLY / CLASSROOM ARCHIVE</span></div>
          </>
        )}
      </div>
    </div>
  );
}

export default BoatReview;