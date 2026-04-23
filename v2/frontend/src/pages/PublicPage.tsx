import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import cityuLogo from '../assets/city-university-logo.svg'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import type { AuthUser } from '../types/auth'

/* ── Submission types data ────────────────────────────────────────────────── */
interface StageDetail { title: string; desc: string; badge?: string }
interface SubmissionType {
  icon: string; title: string; subtitle: string; eligible: string; desc: string
  stageDetails: StageDetail[]
}
const SUBMISSION_TYPES: SubmissionType[] = [
  {
    icon: '🎓',
    title: 'Doctoral Dissertation',
    subtitle: 'PhD and doctoral research',
    eligible: 'PhD & research doctoral candidates at CityU',
    desc: 'Submit doctoral dissertation chapters and manuscripts for program committee review and sign-off. The portal supports PhD and research-doctoral candidates by coordinating advisor matching, consultation, feasibility assessment, and milestone monitoring throughout the doctoral journey.',
    stageDetails: [
      { title: 'Student Submits', desc: 'The doctoral student uploads the dissertation document and all required supporting materials through the portal to begin the approval chain.' },
      { title: 'Chair Review & Approval', desc: 'The dissertation chair reviews the submission for academic quality and completeness. The chair may request revisions before approving the work to advance to the committee.' },
      { title: 'Committee Member Review', desc: 'All assigned committee members independently review the dissertation. The submission advances only when every committee member has provided their individual approval—a single pending approval holds the process.', badge: 'All members must approve' },
      { title: 'Program Director Approval', desc: 'The Program Director conducts a final academic review and confirms the dissertation meets all program requirements and institutional standards before it progresses to the final authority.' },
      { title: 'Dissertation Director Sign-Off', desc: 'The Dissertation Director provides the final institutional approval and formally signs off on the completed work. Once signed off, the dissertation is officially complete.' },
    ],
  },
  {
    icon: '🎒',
    title: 'Capstone Project',
    subtitle: 'Final-year and student research projects',
    eligible: 'Final-year undergraduate and graduate students at CityU',
    desc: 'Submit final-year capstone projects for advisor review and departmental approval. The portal coordinates the advisor–student relationship and the full project approval workflow from proposal through final sign-off.',
    stageDetails: [
      { title: 'Student Submits', desc: 'The student submits their capstone project proposal and supporting materials to initiate the review process.' },
      { title: 'Advisor Matching', desc: 'The coordinator matches the project to an appropriate faculty advisor based on the project topic and advisor availability.' },
      { title: 'Advisor Consultation', desc: 'The assigned advisor reviews the project scope, provides initial guidance, and approves it to advance to feasibility review.' },
      { title: 'Feasibility Check', desc: 'A senior reviewer or department chair assesses the project for academic feasibility, resource requirements, and overall merit.' },
      { title: 'Director Approval', desc: 'The program director grants final approval for the project to proceed to active supervision and execution.' },
    ],
  },
  {
    icon: '🔬',
    title: 'Research Paper',
    subtitle: 'Internal faculty research',
    eligible: 'Faculty members conducting internal research at CityU',
    desc: 'Submit internal research papers for peer review and program director approval. Designed for faculty producing research intended for internal dissemination or as a precursor to external publication.',
    stageDetails: [
      { title: 'Author Submits', desc: 'The faculty member submits the research paper with abstract, full text, and any supplementary data files.' },
      { title: 'Peer Review', desc: 'A peer reviewer with appropriate expertise evaluates the research for methodological soundness, originality, and academic contribution.' },
      { title: 'Program Director Approval', desc: 'The Program Director reviews the peer feedback and provides final institutional approval or revision guidance.' },
    ],
  },
  {
    icon: '📰',
    title: 'Journal Publication',
    subtitle: 'Journal and publication submissions',
    eligible: 'Faculty members and research staff at CityU',
    desc: 'Submit manuscripts for journal publication review and faculty endorsement. The review process ensures academic rigour and institutional alignment before the work is submitted to an external journal.',
    stageDetails: [
      { title: 'Author Submits', desc: 'The author submits the manuscript and any co-author declarations to begin the publication review process.' },
      { title: 'Administrative Check', desc: 'The coordinator verifies the submission for formatting, plagiarism compliance, and co-author consent before assigning a reviewer.' },
      { title: 'Reviewer Matching', desc: 'A subject-matter expert reviewer is identified and assigned based on the manuscript topic and methodology.' },
      { title: 'Expert Review', desc: 'The assigned expert evaluates the manuscript for originality, methodology, and contribution to the academic field.' },
      { title: 'Director Assessment', desc: 'The program director reviews the expert assessment and provides institutional endorsement or requests revisions from the author.' },
      { title: 'Final Decision', desc: 'A final accept or revise decision is issued, with detailed reviewer comments provided to the author for action.' },
    ],
  },
  {
    icon: '💰',
    title: 'Grant Proposal',
    subtitle: 'Internal and external funding proposals',
    eligible: 'Faculty and research staff seeking internal or external funding',
    desc: 'Submit external or internal grant applications for multi-criteria committee review. The portal ensures proposals meet compliance requirements and receive expert evaluation across multiple dimensions.',
    stageDetails: [
      { title: 'Researcher Submits', desc: 'The researcher submits the grant proposal with budget justification, research plan, and all required supporting documents.' },
      { title: 'Compliance Check', desc: 'The grants coordinator verifies that the proposal meets the funder eligibility criteria and institutional submission requirements.' },
      { title: 'Review Assignment', desc: 'Expert reviewers with relevant domain knowledge are assigned to evaluate specific criteria of the proposal.' },
      { title: 'Multi-Criteria Review', desc: 'Reviewers independently assess the proposal across multiple dimensions: innovation, methodology, feasibility, and budget appropriateness.', badge: 'Multi-criteria scoring' },
      { title: 'Committee Meeting', desc: 'The review committee convenes to discuss scores and reach a consensus recommendation for the proposal.' },
      { title: 'Final Decision', desc: 'The institutional authority issues the final endorsement or rejection, with detailed feedback provided to the researcher.' },
    ],
  },
  {
    icon: '🎤',
    title: 'Conference Paper',
    subtitle: 'Applied Research Symposium, Doctor of IT Forum',
    eligible: 'Faculty & doctoral researchers at CityU',
    desc: 'Submit research papers for international and local conference proceedings and symposiums. Each paper goes through initial screening before being assigned to expert peer reviewers for evaluation.',
    stageDetails: [
      { title: 'Author Submits', desc: 'The author uploads the conference paper along with any supplementary materials to initiate the review process.' },
      { title: 'Initial Screening', desc: 'The coordinator checks the paper for format compliance, scope relevance, and completeness before assigning reviewers.' },
      { title: 'Reviewer Assignment', desc: 'The coordinator identifies and assigns domain-appropriate reviewers from the managed reviewer pool, ensuring expertise alignment.' },
      { title: 'Peer Review', desc: 'Assigned reviewers independently evaluate the paper for academic merit, originality, and contribution to the field.' },
      { title: 'Review Consolidation', desc: 'The coordinator consolidates reviewer feedback and scores to produce an overall recommendation for final decision.' },
      { title: 'Final Decision', desc: 'The program director reviews the consolidated feedback and issues the final accept, revise, or reject decision.' },
    ],
  },
]

/* ── Scroll-aware fade-in hook ────────────────────────────────────────────── */
function useFadeIn(threshold = 0.15) {
  const ref = useRef<HTMLDivElement>(null)
  const [visible, setVisible] = useState(false)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    const obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) setVisible(true) }, { threshold })
    obs.observe(el)
    return () => obs.disconnect()
  }, [threshold])
  return { ref, visible }
}

/* ── CSS injected once ────────────────────────────────────────────────────── */
const GLOBAL_STYLES = `
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
  html { scroll-behavior: smooth; }
  body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
  * { box-sizing: border-box; }
  @keyframes pulse-ring {
    0%   { transform: scale(0.9); opacity: 0.6; }
    100% { transform: scale(1.4); opacity: 0; }
  }
  .rrp-card-hover {
    transition: transform 0.22s ease, box-shadow 0.22s ease;
  }
  .rrp-card-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(29,78,216,0.14) !important;
  }
  .rrp-btn-primary {
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
  }
  .rrp-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(29,78,216,0.4);
  }
  .rrp-fade { opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .rrp-fade.visible { opacity: 1; transform: translateY(0); }
  .rrp-fade-delay-1 { transition-delay: 0.1s; }
  .rrp-fade-delay-2 { transition-delay: 0.2s; }
  .rrp-fade-delay-3 { transition-delay: 0.3s; }
`

/* ── Main component ───────────────────────────────────────────────────────── */
export default function PublicPage() {
  const navigate = useNavigate()
  const { setAuth } = useAuthStore()
  const [activeView, setActiveView] = useState<'about' | 'submissions'>('about')

  // Handle SSO callback: exchange short-lived code for Sanctum token
  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const ssoCode = params.get('sso_code')
    if (!ssoCode) return

    // Remove the code from the URL immediately to prevent it appearing in history
    window.history.replaceState({}, '', window.location.pathname)

    api.post<{ token: string }>('/auth/sso-exchange', { code: ssoCode })
      .then(async (res) => {
        const token = res.data.token
        const meRes = await api.get<AuthUser>('/auth/me', {
          headers: { Authorization: `Bearer ${token}` },
        })
        setAuth(meRes.data, token)
        navigate('/dashboard', { replace: true })
      })
      .catch(() => {
        // Exchange failed — stay on public page; user can retry login
      })
  }, [navigate, setAuth])

  const NAV_ITEMS = [
    { label: 'About', view: 'about' as const },
    { label: 'Submission Types', view: 'submissions' as const },
  ]

  return (
    <div style={{ fontFamily: "'Inter', -apple-system, sans-serif", background: '#F8FAFC', color: '#0F172A', overflowX: 'hidden' }}>
      <style>{GLOBAL_STYLES}</style>

      {/* ──────────────────── STICKY NAVBAR ──────────────────── */}
      <nav style={{
        position: 'fixed', top: 0, left: 0, right: 0, zIndex: 1000,
        background: '#0d1f3c',
        borderBottom: '1px solid rgba(255,255,255,0.07)',
      }}>
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: '0 2rem', height: 56, display: 'flex', alignItems: 'center' }}>
          {/* Left: logo + title */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexShrink: 0 }}>
            <img
              src={cityuLogo}
              alt="City University of Seattle"
              style={{ height: 32, width: 'auto', objectFit: 'contain', flexShrink: 0, filter: 'brightness(0) invert(1)' }}
            />
            <div style={{ width: 1, height: 28, background: 'rgba(255,255,255,0.2)', flexShrink: 0 }} />
            <span style={{ fontWeight: 700, fontSize: '1.15rem', color: '#fff', letterSpacing: '-0.3px', whiteSpace: 'nowrap' }}>
              Research Review Portal
            </span>
          </div>

          {/* Spacer */}
          <div style={{ flex: 1 }} />

          {/* Right: nav links + login */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
            {NAV_ITEMS.map(({ label, view }) => (
              <button key={view} onClick={() => setActiveView(view)} style={{
                background: 'none', border: 'none', cursor: 'pointer',
                fontSize: '0.875rem', fontWeight: activeView === view ? 600 : 400,
                color: activeView === view ? '#fff' : 'rgba(255,255,255,0.72)',
                padding: '0.4rem 0.8rem', borderRadius: 6,
                transition: 'color 0.15s', whiteSpace: 'nowrap',
                borderBottom: activeView === view ? '2px solid rgba(255,255,255,0.6)' : '2px solid transparent',
              }}
                onMouseEnter={(e) => { (e.currentTarget as HTMLButtonElement).style.color = '#fff' }}
                onMouseLeave={(e) => { (e.currentTarget as HTMLButtonElement).style.color = activeView === view ? '#fff' : 'rgba(255,255,255,0.72)' }}
              >{label}</button>
            ))}
            <button onClick={() => navigate('/login')} style={{
              background: '#fff', color: '#0d1f3c', border: 'none', borderRadius: 999,
              padding: '0.42rem 1.1rem', fontSize: '0.875rem', fontWeight: 600, cursor: 'pointer',
              marginLeft: '0.5rem', whiteSpace: 'nowrap',
            }}>Login</button>
          </div>
        </div>
      </nav>

      {/* ──────────────────── VIEWS ──────────────────── */}
      {activeView === 'about' && <AboutSection onLogin={() => navigate('/login')} />}
      {activeView === 'submissions' && <SubmissionsSection onLogin={() => navigate('/login')} />}

      {/* ──────────────────── FOOTER ──────────────────── */}
      <footer style={{ background: '#0d1f3c', padding: '2rem 1.5rem', textAlign: 'center' }}>
        <p style={{ color: 'rgba(255,255,255,0.55)', fontSize: '0.82rem', marginBottom: '0.5rem' }}>
          © {new Date().getFullYear()} City University of Seattle · Research Review Portal · School of Technology and Computing (STC)
        </p>
        <p style={{ color: 'rgba(255,255,255,0.4)', fontSize: '0.78rem' }}>
          Designed &amp; developed by{' '}
          <a href="mailto:vejendlakirankumar@cityu.edu" style={{ color: '#60a5fa', fontWeight: 600, textDecoration: 'none' }}>Kiran Kumar Vejendla</a>
          {' · vejendlakirankumar@cityu.edu and '}
          <a href="mailto:garrisjemell@cityu.edu" style={{ color: '#60a5fa', fontWeight: 600, textDecoration: 'none' }}>Jemell Garris</a>
          {' · garrisjemell@cityu.edu'}
        </p>
      </footer>
    </div>
  )
}

/* ─────────────────────────────────────────────────────────────────────────────
   ABOUT SECTION
   ───────────────────────────────────────────────────────────────────────────── */
function AboutSection(_: { onLogin: () => void }) {
  const { ref, visible } = useFadeIn()
  const ABOUT_FEATURES = [
    { icon: '📋', title: 'Structured Multi-Stage Review', desc: 'Every submission follows a workflow defined for its type, ensuring rigorous and consistent evaluation at each stage.' },
    { icon: '👁️', title: 'Full Transparency', desc: 'Submitters can track exactly which stage their work is at, who is reviewing it, and what feedback has been provided.' },
    { icon: '👥', title: 'Expert Reviewer Coordination', desc: 'Coordinators assign qualified reviewers from a managed pool, ensuring domain expertise and conflict-of-interest checks.' },
    { icon: '📄', title: 'Document Management', desc: 'Upload supporting materials, save drafts, and attach revisions—all version-controlled and linked to your submission.' },
    { icon: '🔔', title: 'Automated Notifications', desc: 'Reviewers and submitters receive email alerts when action is required, keeping the process moving without manual chasing.' },
    { icon: '📊', title: 'Coordinator Dashboard', desc: "Admins and coordinators get a bird's-eye view of all active submissions, deadlines, and overdue reviews." },
  ]
  return (
    <section id="about" style={{ padding: '5rem 1.5rem 4rem', paddingTop: 'calc(56px + 3.5rem)', background: '#fff' }}>
      <div ref={ref} className={`rrp-fade ${visible ? 'visible' : ''}`} style={{ maxWidth: 960, margin: '0 auto' }}>
        <h2 style={{ fontSize: 'clamp(1.8rem, 3vw, 2.4rem)', fontWeight: 900, color: '#0F172A', marginBottom: '1rem', letterSpacing: '-0.5px' }}>
          What is the Research Review Portal?
        </h2>
        <p style={{ color: '#374151', fontSize: '1rem', lineHeight: 1.8, marginBottom: '1.5rem' }}>
          The Research Review Portal is a centralized platform designed to simplify and elevate the way academic work is submitted, reviewed, and approved. It unifies every stage of the research lifecycle—submission, evaluation, feedback, and final decision—into a single, transparent, and easy-to-manage system.
        </p>
        <p style={{ color: '#374151', fontSize: '1rem', lineHeight: 1.8, marginBottom: '3rem' }}>
          Built for efficiency and clarity, the portal enables seamless collaboration between researchers, reviewers, and administrators. With real-time progress tracking, structured workflows, and organized document management, it ensures a consistent, reliable, and professional review experience from start to finish.
        </p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(210px, 1fr))', gap: '1rem' }}>
          {ABOUT_FEATURES.map(({ icon, title, desc }) => (
            <div key={title} className="rrp-card-hover" style={{
              background: '#fff', border: '1px solid #E2E8F0', borderRadius: 14,
              padding: '1.5rem 1.25rem', boxShadow: '0 1px 4px rgba(0,0,0,0.04)',
            }}>
              <div style={{ fontSize: '2rem', marginBottom: '0.85rem', lineHeight: 1 }}>{icon}</div>
              <h3 style={{ fontWeight: 700, fontSize: '0.95rem', color: '#0F172A', marginBottom: '0.45rem', lineHeight: 1.3 }}>{title}</h3>
              <p style={{ fontSize: '0.83rem', color: '#64748B', lineHeight: 1.65, margin: 0 }}>{desc}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}



/* ─────────────────────────────────────────────────────────────────────────────
   SUBMISSIONS SECTION
   ───────────────────────────────────────────────────────────────────────────── */
function SubmissionsSection({ onLogin }: { onLogin: () => void }) {
  const [activeIdx, setActiveIdx] = useState(0)
  const { ref, visible } = useFadeIn()
  const active = SUBMISSION_TYPES[activeIdx]

  return (
    <section id="submissions" style={{ padding: '5rem 1.5rem 4rem', background: '#F8FAFC' }}>
      <div ref={ref} className={`rrp-fade ${visible ? 'visible' : ''}`} style={{ maxWidth: 960, margin: '0 auto' }}>
        <h2 style={{ fontSize: 'clamp(1.8rem, 3vw, 2.4rem)', fontWeight: 900, color: '#0F172A', marginBottom: '1rem', letterSpacing: '-0.5px' }}>
          Review Process by Submission Type
        </h2>
        <p style={{ color: '#374151', fontSize: '1rem', maxWidth: 660, lineHeight: 1.8, marginBottom: '2rem' }}>
          Select a submission type to see the complete staged review workflow. Each stage must be approved before the submission advances to the next.
        </p>

        {/* Tabs — equal-width grid, no scrollbar */}
        <div style={{ borderBottom: '2px solid #E2E8F0', marginBottom: '2.5rem' }}>
          <div style={{ display: 'grid', gridTemplateColumns: `repeat(${SUBMISSION_TYPES.length}, 1fr)` }}>
            {SUBMISSION_TYPES.map(({ icon, title }, idx) => (
              <button key={title} onClick={() => setActiveIdx(idx)} style={{
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem',
                padding: '0.65rem 0.5rem', background: 'none', border: 'none',
                cursor: 'pointer', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                fontSize: '0.82rem', fontWeight: idx === activeIdx ? 600 : 400,
                color: idx === activeIdx ? '#1D4ED8' : '#64748B',
                borderBottom: idx === activeIdx ? '2px solid #1D4ED8' : '2px solid transparent',
                marginBottom: '-2px', transition: 'color 0.15s',
              }}
                onMouseEnter={(e) => { if (idx !== activeIdx) (e.currentTarget as HTMLButtonElement).style.color = '#374151' }}
                onMouseLeave={(e) => { if (idx !== activeIdx) (e.currentTarget as HTMLButtonElement).style.color = '#64748B' }}
              >
                <span style={{ fontSize: '1rem', flexShrink: 0 }}>{icon}</span>
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis' }}>{title}</span>
              </button>
            ))}
          </div>
        </div>

        {/* Active submission header */}
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '1.25rem', marginBottom: '2.25rem' }}>
          <span style={{ fontSize: '2.8rem', lineHeight: 1, flexShrink: 0 }}>{active.icon}</span>
          <div style={{ flex: 1 }}>
            <h3 style={{ fontSize: '1.4rem', fontWeight: 800, color: '#0F172A', marginBottom: '0.5rem' }}>{active.title}</h3>
            <p style={{ color: '#374151', fontSize: '0.95rem', lineHeight: 1.75, marginBottom: '0.85rem', maxWidth: 680 }}>{active.desc}</p>
            <span style={{
              display: 'inline-flex', alignItems: 'center',
              background: '#F0F9FF', color: '#0284C7', border: '1px solid #BAE6FD',
              borderRadius: 999, fontSize: '0.78rem', fontWeight: 500,
              padding: '0.2rem 0.75rem',
            }}>Eligible: {active.eligible}</span>
          </div>
        </div>

        {/* Vertical timeline */}
        <div style={{ paddingLeft: '0.25rem' }}>
          {active.stageDetails.map(({ title, desc, badge }, i) => {
            const isLast = i === active.stageDetails.length - 1
            return (
              <div key={i} style={{ display: 'flex', gap: '1rem', position: 'relative' }}>
                {!isLast && (
                  <div style={{ position: 'absolute', left: 14, top: 32, bottom: 0, width: 2, background: '#CBD5E1', zIndex: 0 }} />
                )}
                <div style={{
                  width: 30, height: 30, borderRadius: '50%', flexShrink: 0,
                  background: isLast ? '#1E4D3B' : '#1D4ED8',
                  color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: '0.78rem', fontWeight: 700, zIndex: 1, position: 'relative',
                }}>{i + 1}</div>
                <div style={{ paddingBottom: isLast ? 0 : '1.75rem', flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', marginBottom: '0.35rem', flexWrap: 'wrap' }}>
                    <h4 style={{ fontWeight: 700, fontSize: '1rem', color: '#0F172A', margin: 0 }}>{title}</h4>
                    {badge && (
                      <span style={{ background: '#1D4ED8', color: '#fff', borderRadius: 999, fontSize: '0.72rem', fontWeight: 600, padding: '0.15rem 0.65rem' }}>{badge}</span>
                    )}
                  </div>
                  <p style={{ color: '#475569', fontSize: '0.9rem', lineHeight: 1.75, margin: 0 }}>{desc}</p>
                </div>
              </div>
            )
          })}
        </div>

        {/* CTA */}
        <div style={{ marginTop: '3rem', paddingTop: '2.5rem', borderTop: '1px solid #E2E8F0', textAlign: 'center' }}>
          <p style={{ color: '#64748B', fontSize: '0.95rem', marginBottom: '1.25rem' }}>Ready to submit your research?</p>
          <button onClick={onLogin} className="rrp-btn-primary" style={{
            display: 'inline-flex', alignItems: 'center', gap: '0.5rem',
            background: 'linear-gradient(135deg,#1e3a8a,#3b82f6)', color: '#fff',
            border: 'none', borderRadius: 999, padding: '0.75rem 1.75rem',
            fontSize: '1rem', fontWeight: 600, cursor: 'pointer',
            boxShadow: '0 4px 16px rgba(29,78,216,0.25)',
          }}>Login to Submit Your Work <ArrowRight size={17} /></button>
        </div>
      </div>
    </section>
  )
}
