import { useQuery } from '@tanstack/react-query'
import {
  TrendingUp,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Users,
  Target,
  Timer,
  MessageSquare,
  RefreshCw,
} from 'lucide-react'
import api from '../lib/axios'

// ── Types ────────────────────────────────────────────────────────────────────

interface MyStats {
  summary: {
    total_assigned: number
    pending: number
    completed: number
    overdue: number
    on_time: number
    avg_turnaround_days: number | null
    completion_rate: number | null
    on_time_rate: number | null
  }
  decision_breakdown: { decision: string; count: number }[]
  monthly_trend: { month: string; count: number }[]
}

interface DailyActivity {
  day: string
  approve: number
  reject: number
  revise: number
}

interface BreakdownData {
  by_status: { status_bucket: string; cnt: number }[]
  by_type: { type_label: string; cnt: number }[]
}

// ── Metric Card ──────────────────────────────────────────────────────────────

function MetricCard({
  icon: Icon,
  label,
  value,
  color,
  sub,
}: {
  icon: React.ElementType
  label: string
  value: string | number
  color: string
  sub?: string
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <div className="flex items-center gap-3 mb-3">
        <span className={`p-2 rounded-lg ${color} bg-opacity-10`}>
          <Icon size={18} className={color.replace('bg-', 'text-').replace('-100', '-600')} />
        </span>
        <span className="text-sm font-medium text-gray-500">{label}</span>
      </div>
      <p className="text-2xl font-bold text-gray-900">{value}</p>
      {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
    </div>
  )
}

// ── Mini Sparkline Line Chart (SVG, no deps) ─────────────────────────────────

function LineChart({ data }: { data: DailyActivity[] }) {
  if (!data.length) return null

  const W = 800
  const H = 160
  const PAD = { top: 12, right: 16, bottom: 28, left: 32 }
  const iW = W - PAD.left - PAD.right
  const iH = H - PAD.top - PAD.bottom

  const maxVal = Math.max(
    ...data.map((d) => d.approve + d.reject + d.revise),
    1,
  )

  function xPos(i: number) {
    return PAD.left + (i / (data.length - 1)) * iW
  }
  function yPos(val: number) {
    return PAD.top + iH - (val / maxVal) * iH
  }

  function polyline(key: 'approve' | 'reject' | 'revise') {
    return data
      .map((d, i) => `${xPos(i)},${yPos(d[key])}`)
      .join(' ')
  }

  // X-axis labels: show every 5th day
  const xLabels = data
    .map((d, i) => ({ i, label: d.day.slice(5) })) // MM-DD
    .filter((_, i) => i % 5 === 0 || i === data.length - 1)

  const series = [
    { key: 'approve' as const, color: '#22c55e', label: 'Approved' },
    { key: 'reject' as const, color: '#ef4444', label: 'Rejected' },
    { key: 'revise' as const, color: '#f59e0b', label: 'Revision' },
  ]

  return (
    <div>
      <div className="flex items-center gap-6 mb-3">
        {series.map((s) => (
          <span key={s.key} className="flex items-center gap-1.5 text-xs font-medium text-gray-600">
            <span className="w-3 h-0.5 rounded-full inline-block" style={{ backgroundColor: s.color }} />
            {s.label}
          </span>
        ))}
      </div>
      <svg
        viewBox={`0 0 ${W} ${H}`}
        className="w-full"
        style={{ height: 160 }}
        aria-label="Daily activity chart"
      >
        {/* Grid lines */}
        {[0, 0.25, 0.5, 0.75, 1].map((t) => {
          const y = PAD.top + iH - t * iH
          return (
            <g key={t}>
              <line x1={PAD.left} y1={y} x2={W - PAD.right} y2={y} stroke="#f3f4f6" strokeWidth={1} />
              {t > 0 && (
                <text x={PAD.left - 4} y={y + 4} textAnchor="end" fontSize={9} fill="#9ca3af">
                  {Math.round(maxVal * t)}
                </text>
              )}
            </g>
          )
        })}

        {/* X-axis labels */}
        {xLabels.map(({ i, label }) => (
          <text
            key={i}
            x={xPos(i)}
            y={H - 4}
            textAnchor="middle"
            fontSize={9}
            fill="#9ca3af"
          >
            {label}
          </text>
        ))}

        {/* Lines */}
        {series.map((s) => (
          <polyline
            key={s.key}
            points={polyline(s.key)}
            fill="none"
            stroke={s.color}
            strokeWidth={2}
            strokeLinejoin="round"
            strokeLinecap="round"
          />
        ))}

        {/* Dots on last point */}
        {series.map((s) => {
          const last = data[data.length - 1]
          return (
            <circle
              key={s.key}
              cx={xPos(data.length - 1)}
              cy={yPos(last[s.key])}
              r={3}
              fill={s.color}
            />
          )
        })}
      </svg>
    </div>
  )
}

// ── Horizontal Bar Chart ─────────────────────────────────────────────────────

function HBar({
  items,
  colorMap,
}: {
  items: { label: string; count: number; color?: string }[]
  colorMap?: Record<string, string>
}) {
  const max = Math.max(...items.map((i) => i.count), 1)
  return (
    <div className="space-y-3">
      {items.map((item) => {
        const pct = Math.round((item.count / max) * 100)
        const color = item.color ?? colorMap?.[item.label] ?? 'bg-indigo-400'
        return (
          <div key={item.label}>
            <div className="flex justify-between text-xs text-gray-600 mb-1">
              <span className="font-medium truncate max-w-[200px]">{item.label}</span>
              <span className="ml-2 text-gray-400">{item.count}</span>
            </div>
            <div className="h-2.5 bg-gray-100 rounded-full overflow-hidden">
              <div
                className={`h-full rounded-full transition-all duration-500 ${color}`}
                style={{ width: `${pct}%` }}
              />
            </div>
          </div>
        )
      })}
      {items.length === 0 && (
        <p className="text-sm text-gray-400 text-center py-4">No data yet</p>
      )}
    </div>
  )
}

// ── Section wrapper ──────────────────────────────────────────────────────────

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <h2 className="text-sm font-semibold text-gray-700 mb-4">{title}</h2>
      {children}
    </div>
  )
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ReviewerAnalyticsPage() {
  const { data: statsData, isLoading: statsLoading, refetch: refetchStats } = useQuery<{ data: MyStats }>({
    queryKey: ['reviewer-my-stats'],
    queryFn: () => api.get('/analytics/my-stats').then((r) => r.data),
  })

  const { data: dailyData, isLoading: dailyLoading } = useQuery<{ data: DailyActivity[] }>({
    queryKey: ['reviewer-daily'],
    queryFn: () => api.get('/analytics/my-daily').then((r) => r.data),
  })

  const { data: breakdownData, isLoading: breakdownLoading } = useQuery<{ data: BreakdownData }>({
    queryKey: ['reviewer-breakdown'],
    queryFn: () => api.get('/analytics/my-submission-breakdown').then((r) => r.data),
  })

  const stats = statsData?.data
  const daily = dailyData?.data ?? []
  const breakdown = breakdownData?.data

  const summary = stats?.summary

  // Decision breakdown (approved / revision / rejected)
  const decisionItems = (stats?.decision_breakdown ?? []).map((d) => ({
    label: d.decision === 'approve' ? 'Approved' : d.decision === 'reject' ? 'Rejected' : 'Revision Requested',
    count: Number(d.count),
    color:
      d.decision === 'approve'
        ? 'bg-green-500'
        : d.decision === 'reject'
        ? 'bg-red-400'
        : 'bg-amber-400',
  }))

  // By-status horizontal bar
  const STATUS_LABELS: Record<string, string> = {
    completed: 'Completed',
    pending: 'Pending',
    overdue: 'Overdue',
  }
  const STATUS_COLORS: Record<string, string> = {
    completed: 'bg-green-400',
    pending: 'bg-blue-400',
    overdue: 'bg-red-400',
  }
  const statusItems = (breakdown?.by_status ?? []).map((s) => ({
    label: STATUS_LABELS[s.status_bucket] ?? s.status_bucket,
    count: Number(s.cnt),
    color: STATUS_COLORS[s.status_bucket] ?? 'bg-gray-400',
  }))

  // By-type horizontal bar
  const typeItems = (breakdown?.by_type ?? []).map((t) => ({
    label: t.type_label,
    count: Number(t.cnt),
    color: 'bg-indigo-400',
  }))

  // Avg feedback length — not tracked server-side yet, show N/A
  const avgFeedback = 'N/A'

  // Revision trigger %
  const revisionCount = (stats?.decision_breakdown ?? []).find((d) => d.decision === 'revise')?.count ?? 0
  const revisionTrigger =
    summary && Number(summary.completed) > 0
      ? `${Math.round((Number(revisionCount) / Number(summary.completed)) * 100)}%`
      : 'N/A'

  const isLoading = statsLoading || dailyLoading || breakdownLoading

  return (
    <div>
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Analytics</h1>
          <p className="text-sm text-gray-500 mt-0.5">Your personal review performance and activity</p>
        </div>
        <button
          onClick={() => refetchStats()}
          className="flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
        >
          <RefreshCw size={14} />
          Refresh
        </button>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="bg-white rounded-xl border border-gray-200 p-5 animate-pulse h-28" />
          ))}
        </div>
      ) : (
        <>
          {/* ── Top Metrics ───────────────────────────────────────────────── */}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <MetricCard
              icon={Users}
              label="Total Assigned"
              value={Number(summary?.total_assigned ?? 0)}
              color="bg-blue-100"
            />
            <MetricCard
              icon={CheckCircle2}
              label="Completed"
              value={Number(summary?.completed ?? 0)}
              color="bg-green-100"
            />
            <MetricCard
              icon={Clock}
              label="Pending Review"
              value={Number(summary?.pending ?? 0)}
              color="bg-amber-100"
            />
            <MetricCard
              icon={AlertTriangle}
              label="Overdue"
              value={Number(summary?.overdue ?? 0)}
              color="bg-red-100"
            />
            <MetricCard
              icon={Timer}
              label="Avg Time to Decision"
              value={summary?.avg_turnaround_days != null ? `${summary.avg_turnaround_days}d` : 'N/A'}
              color="bg-purple-100"
              sub="days average"
            />
          </div>

          {/* ── My Performance ────────────────────────────────────────────── */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <MetricCard
              icon={Target}
              label="Total Decisions"
              value={Number(summary?.completed ?? 0)}
              color="bg-indigo-100"
            />
            <MetricCard
              icon={CheckCircle2}
              label="On-time Rate"
              value={summary?.on_time_rate != null ? `${summary.on_time_rate}%` : 'N/A'}
              color="bg-green-100"
            />
            <MetricCard
              icon={TrendingUp}
              label="Revision Trigger"
              value={revisionTrigger}
              color="bg-amber-100"
              sub="of completed decisions"
            />
            <MetricCard
              icon={MessageSquare}
              label="Avg Feedback Length"
              value={avgFeedback}
              color="bg-blue-100"
              sub="characters"
            />
          </div>

          {/* ── Charts ────────────────────────────────────────────────────── */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
            <Section title="Daily Activity (Last 30 Days)">
              {daily.length > 0 ? (
                <LineChart data={daily} />
              ) : (
                <p className="text-sm text-gray-400 text-center py-8">No decisions in the last 30 days</p>
              )}
            </Section>

            <Section title="Decision Breakdown">
              <HBar items={decisionItems} />
            </Section>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <Section title="Assigned Submissions by Status">
              <HBar items={statusItems} />
            </Section>

            <Section title="Assignments by Type">
              <HBar items={typeItems} />
            </Section>
          </div>
        </>
      )}
    </div>
  )
}
