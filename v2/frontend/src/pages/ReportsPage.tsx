import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  BarChart2, TrendingUp, Users, Clock, CheckCircle2, AlertTriangle, FileText,
  Activity, Ban, UserCheck, Inbox, ArrowRight, Timer,
} from 'lucide-react'
import api from '../lib/axios'

// ── Types ─────────────────────────────────────────────────────────────────────

interface OverviewData {
  total_submissions: number
  total_active_users: number
  active_users_30d: number
  pending_review: number
  completed_this_month: number
  by_status: Record<string, number>
  by_type: Record<string, number>
  monthly_trend: { month: string; count: number }[]
  // Extended stats
  active_submissions: number
  pending_assignment: number
  overdue_reviews: number
  total_reviews: number
  completed_reviews: number
  under_review: number
  revision_required: number
  cancelled: number
  withdrawn: number
  active_reviewers: number
}

interface MetricsData {
  late_alerts: number
  avg_time_to_decision: number | null
  avg_stages: number | null
  mean_reviewer_load: number | null
}

interface TurnaroundData {
  by_type: { type_label: string; total: number; avg_days: number; min_days: number; max_days: number }[]
  by_stage: { stage_role_label: string; total: number; avg_days: number }[]
}

interface ReviewerRow {
  id: string; name: string; email: string
  pending_count: number; completed_count: number; total_count: number
  completion_rate: number
  avg_turnaround_days: number | null
  on_time_rate: number | null
  overdue_count: number
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, string> = {
  DRAFT: 'bg-gray-300', SUBMITTED: 'bg-blue-400', IN_REVIEW: 'bg-amber-400',
  REVISION_REQUIRED: 'bg-orange-400', ACCEPTED: 'bg-green-500',
  CONDITIONALLY_ACCEPTED: 'bg-teal-500', REJECTED: 'bg-red-400', WITHDRAWN: 'bg-gray-400',
}
const STATUS_LABELS: Record<string, string> = {
  DRAFT: 'Draft', SUBMITTED: 'Submitted', IN_REVIEW: 'In Review',
  REVISION_REQUIRED: 'Revision Required', ACCEPTED: 'Accepted',
  CONDITIONALLY_ACCEPTED: 'Cond. Accepted', REJECTED: 'Rejected', WITHDRAWN: 'Withdrawn',
}

function StatCard({ icon: Icon, label, value, color }: {
  icon: React.ElementType; label: string; value: number; color?: string
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 flex items-start gap-4">
      <div className={`p-2.5 rounded-lg ${color ?? 'bg-gray-100'}`}>
        <Icon className="w-5 h-5 text-white" />
      </div>
      <div>
        <p className="text-2xl font-bold text-gray-900">{value}</p>
        <p className="text-sm text-gray-500">{label}</p>
      </div>
    </div>
  )
}

function HBar({ label, value, max, color }: { label: string; value: number; max: number; color?: string }) {
  const pct = max > 0 ? Math.round((value / max) * 100) : 0
  return (
    <div className="flex items-center gap-3">
      <span className="text-xs text-gray-500 w-36 truncate flex-shrink-0">{label}</span>
      <div className="flex-1 bg-gray-100 rounded-full h-3">
        <div className={`h-3 rounded-full ${color ?? 'bg-brand-600'}`} style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs font-semibold text-gray-700 w-8 text-right">{value}</span>
    </div>
  )
}

function SparkBar({ data }: { data: { month: string; count: number }[] }) {
  const max = Math.max(...data.map(d => d.count), 1)
  return (
    <div className="flex items-end gap-1 h-20">
      {data.map(d => (
        <div key={d.month} className="flex-1 flex flex-col items-center gap-1 group relative">
          <div className="w-full bg-brand-600 rounded-t hover:bg-brand-500 transition-colors"
            style={{ height: `${Math.round((d.count / max) * 56)}px`, minHeight: 2 }} />
          <span className="text-[9px] text-gray-400">{d.month.slice(5)}</span>
          <div className="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover:block z-10">
            <div className="bg-gray-800 text-white text-[10px] px-1.5 py-0.5 rounded whitespace-nowrap">
              {d.month}: {d.count}
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

// ── Tabs ──────────────────────────────────────────────────────────────────────

function OverviewTab() {
  const { data, isLoading } = useQuery<{ data: OverviewData }>({
    queryKey: ['analytics-overview'],
    queryFn: () => api.get('/admin/analytics/overview').then(r => r.data),
    staleTime: 60_000,
  })
  const { data: metricsData } = useQuery<{ data: MetricsData }>({
    queryKey: ['analytics-metrics'],
    queryFn: () => api.get('/admin/analytics/metrics').then(r => r.data),
    staleTime: 60_000,
  })
  const d = data?.data
  const m = metricsData?.data
  if (isLoading) return <div className="h-40 bg-gray-100 rounded-xl animate-pulse" />
  const maxStatus = d ? Math.max(...Object.values(d.by_status), 1) : 1
  const maxType   = d ? Math.max(...Object.values(d.by_type), 1)   : 1
  return (
    <div className="space-y-6">
      {/* Row 1: Original summary cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard icon={FileText}      label="Total Submissions"    value={d?.total_submissions ?? 0}    color="bg-brand-800" />
        <StatCard icon={AlertTriangle} label="Pending Review"       value={d?.pending_review ?? 0}       color="bg-amber-500" />
        <StatCard icon={CheckCircle2}  label="Completed This Month" value={d?.completed_this_month ?? 0} color="bg-green-600" />
        <StatCard icon={Users}         label="Active Users (30d)"   value={d?.active_users_30d ?? 0}     color="bg-blue-600"  />
      </div>

      {/* Row 2: Extended status stats */}
      <div>
        <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Submission & Review Breakdown</h3>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
          <StatCard icon={Activity}      label="Active Submissions"  value={d?.active_submissions ?? 0}  color="bg-indigo-600" />
          <StatCard icon={Inbox}         label="Pending Assignment"  value={d?.pending_assignment ?? 0}  color="bg-amber-600"  />
          <StatCard icon={AlertTriangle} label="Overdue Reviews"     value={d?.overdue_reviews ?? 0}     color="bg-red-500"    />
          <StatCard icon={FileText}      label="Total Reviews"       value={d?.total_reviews ?? 0}       color="bg-slate-600"  />
          <StatCard icon={CheckCircle2}  label="Completed Reviews"   value={d?.completed_reviews ?? 0}   color="bg-green-600"  />
          <StatCard icon={Clock}         label="Under Review"        value={d?.under_review ?? 0}        color="bg-blue-500"   />
          <StatCard icon={ArrowRight}    label="Revision Required"   value={d?.revision_required ?? 0}   color="bg-orange-500" />
          <StatCard icon={Ban}           label="Cancelled"           value={d?.cancelled ?? 0}           color="bg-gray-500"   />
          <StatCard icon={Ban}           label="Withdrawn"           value={d?.withdrawn ?? 0}           color="bg-gray-400"   />
          <StatCard icon={Users}         label="Active Users"        value={d?.total_active_users ?? 0}  color="bg-blue-700"   />
          <StatCard icon={UserCheck}     label="Active Reviewers"    value={d?.active_reviewers ?? 0}    color="bg-teal-600"   />
        </div>
      </div>

      {/* Row 3: Key metrics */}
      {m && (
        <div>
          <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Key Performance Metrics</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white rounded-xl border border-red-200 p-5 flex flex-col gap-1">
              <div className="flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-red-500" />
                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Late Alerts</span>
              </div>
              <p className="text-2xl font-bold text-red-600 mt-1">{m.late_alerts}</p>
              <p className="text-xs text-gray-400">Overdue reviewer assignments</p>
            </div>
            <div className="bg-white rounded-xl border border-blue-200 p-5 flex flex-col gap-1">
              <div className="flex items-center gap-2">
                <Timer className="w-4 h-4 text-blue-500" />
                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Avg. Time to Decision</span>
              </div>
              <p className="text-2xl font-bold text-blue-600 mt-1">{m.avg_time_to_decision != null ? `${m.avg_time_to_decision}d` : '—'}</p>
              <p className="text-xs text-gray-400">Submission to final decision</p>
            </div>
            <div className="bg-white rounded-xl border border-indigo-200 p-5 flex flex-col gap-1">
              <div className="flex items-center gap-2">
                <TrendingUp className="w-4 h-4 text-indigo-500" />
                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Avg. Stages / Submission</span>
              </div>
              <p className="text-2xl font-bold text-indigo-600 mt-1">{m.avg_stages ?? '—'}</p>
              <p className="text-xs text-gray-400">Mean distinct review stages</p>
            </div>
            <div className="bg-white rounded-xl border border-teal-200 p-5 flex flex-col gap-1">
              <div className="flex items-center gap-2">
                <UserCheck className="w-4 h-4 text-teal-500" />
                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Mean Reviewer Load</span>
              </div>
              <p className="text-2xl font-bold text-teal-600 mt-1">{m.mean_reviewer_load ?? '—'}</p>
              <p className="text-xs text-gray-400">Avg. pending assignments / reviewer</p>
            </div>
          </div>
        </div>
      )}

      {/* Charts */}
      <div className="grid md:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">By Status</h3>
          <div className="space-y-2.5">
            {d && Object.entries(d.by_status).sort((a,b)=>b[1]-a[1]).map(([s,c]) => (
              <HBar key={s} label={STATUS_LABELS[s]??s} value={c} max={maxStatus} color={STATUS_COLORS[s]} />
            ))}
          </div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">By Type</h3>
          <div className="space-y-2.5">
            {d && Object.entries(d.by_type).sort((a,b)=>b[1]-a[1]).map(([l,c]) => (
              <HBar key={l} label={l??'Unknown'} value={c} max={maxType} />
            ))}
            {d && !Object.keys(d.by_type).length && <p className="text-sm text-gray-400 text-center py-4">No data</p>}
          </div>
        </div>
      </div>
      {d?.monthly_trend && d.monthly_trend.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Monthly Submissions (last 12 months)</h3>
          <SparkBar data={d.monthly_trend} />
        </div>
      )}
    </div>
  )
}

function TurnaroundTab() {
  const { data, isLoading } = useQuery<{ data: TurnaroundData }>({
    queryKey: ['analytics-turnaround'],
    queryFn: () => api.get('/admin/analytics/turnaround').then(r => r.data),
    staleTime: 60_000,
  })
  const d = data?.data
  if (isLoading) return <div className="h-40 bg-gray-100 rounded-xl animate-pulse" />
  return (
    <div className="space-y-6">
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-5 py-3 border-b border-gray-100">
          <h3 className="text-sm font-semibold text-gray-700">Avg Turnaround by Submission Type (days)</h3>
        </div>
        {!d?.by_type?.length
          ? <p className="text-sm text-gray-400 text-center py-12">No completed submissions yet.</p>
          : <table className="w-full text-sm">
              <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                <tr>
                  <th className="px-5 py-2.5 text-left">Type</th>
                  <th className="px-5 py-2.5 text-right">Total</th>
                  <th className="px-5 py-2.5 text-right">Avg</th>
                  <th className="px-5 py-2.5 text-right">Min</th>
                  <th className="px-5 py-2.5 text-right">Max</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {d.by_type.map(r => (
                  <tr key={r.type_label} className="hover:bg-gray-50">
                    <td className="px-5 py-3 font-medium text-gray-900">{r.type_label ?? 'Unknown'}</td>
                    <td className="px-5 py-3 text-right text-gray-600">{r.total}</td>
                    <td className="px-5 py-3 text-right font-semibold text-brand-700">{r.avg_days ?? '—'}</td>
                    <td className="px-5 py-3 text-right text-gray-500">{r.min_days ?? '—'}</td>
                    <td className="px-5 py-3 text-right text-gray-500">{r.max_days ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
        }
      </div>
      {d?.by_stage && d.by_stage.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div className="px-5 py-3 border-b border-gray-100">
            <h3 className="text-sm font-semibold text-gray-700">Avg Review Time by Stage Role</h3>
          </div>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
              <tr>
                <th className="px-5 py-2.5 text-left">Stage Role</th>
                <th className="px-5 py-2.5 text-right">Decisions</th>
                <th className="px-5 py-2.5 text-right">Avg Days</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {d.by_stage.map(r => (
                <tr key={r.stage_role_label} className="hover:bg-gray-50">
                  <td className="px-5 py-3 capitalize text-gray-900">{r.stage_role_label}</td>
                  <td className="px-5 py-3 text-right text-gray-600">{r.total}</td>
                  <td className="px-5 py-3 text-right font-semibold text-brand-700">{r.avg_days ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function ReviewerLoadTab() {
  const { data, isLoading } = useQuery<{ data: ReviewerRow[] }>({
    queryKey: ['analytics-reviewer-load'],
    queryFn: () => api.get('/admin/analytics/reviewer-load').then(r => r.data),
    staleTime: 60_000,
  })
  if (isLoading) return <div className="h-40 bg-gray-100 rounded-xl animate-pulse" />
  const rows = data?.data ?? []
  return (
    <div className="space-y-6">
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-5 py-3 border-b border-gray-100">
          <h3 className="text-sm font-semibold text-gray-700">Reviewer Workload & Performance</h3>
        </div>
        {!rows.length
          ? <p className="text-sm text-gray-400 text-center py-12">No reviewer data yet.</p>
          : <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                  <tr>
                    <th className="px-5 py-2.5 text-left">Reviewer</th>
                    <th className="px-5 py-2.5 text-right">Pending</th>
                    <th className="px-5 py-2.5 text-right">Completed</th>
                    <th className="px-5 py-2.5 text-right">Overdue</th>
                    <th className="px-5 py-2.5 text-right">Total</th>
                    <th className="px-5 py-2.5 text-right">Avg. Days</th>
                    <th className="px-5 py-2.5 text-right">On-Time</th>
                    <th className="px-5 py-2.5 text-right">Completion</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {rows.map(r => (
                    <tr key={r.id} className="hover:bg-gray-50">
                      <td className="px-5 py-3">
                        <p className="font-medium text-gray-900">{r.name}</p>
                        <p className="text-xs text-gray-400">{r.email}</p>
                      </td>
                      <td className="px-5 py-3 text-right">
                        {r.pending_count > 0
                          ? <span className="font-semibold text-amber-600">{r.pending_count}</span>
                          : <span className="text-gray-400">0</span>}
                      </td>
                      <td className="px-5 py-3 text-right text-gray-600">{r.completed_count}</td>
                      <td className="px-5 py-3 text-right">
                        {r.overdue_count > 0
                          ? <span className="font-semibold text-red-500">{r.overdue_count}</span>
                          : <span className="text-gray-400">0</span>}
                      </td>
                      <td className="px-5 py-3 text-right text-gray-600">{r.total_count}</td>
                      <td className="px-5 py-3 text-right font-semibold text-brand-700">
                        {r.avg_turnaround_days != null ? `${r.avg_turnaround_days}d` : '—'}
                      </td>
                      <td className="px-5 py-3 text-right">
                        {r.on_time_rate != null
                          ? <span className={r.on_time_rate >= 80 ? 'text-green-600 font-semibold' : r.on_time_rate >= 50 ? 'text-amber-600' : 'text-red-500 font-semibold'}>
                              {r.on_time_rate}%
                            </span>
                          : <span className="text-gray-400">—</span>}
                      </td>
                      <td className="px-5 py-3 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <div className="w-16 bg-gray-100 rounded-full h-1.5">
                            <div className="h-1.5 rounded-full bg-green-500" style={{ width: `${r.completion_rate ?? 0}%` }} />
                          </div>
                          <span className="text-xs text-gray-600 w-8">{r.completion_rate ?? 0}%</span>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
        }
      </div>
    </div>
  )
}

// ── Page ──────────────────────────────────────────────────────────────────────

type Tab = 'overview' | 'turnaround' | 'reviewer-load'

export default function ReportsPage() {
  const [tab, setTab] = useState<Tab>('overview')
  const tabs: { id: Tab; label: string; icon: React.ElementType }[] = [
    { id: 'overview',       label: 'Overview',      icon: BarChart2 },
    { id: 'turnaround',     label: 'Turnaround',    icon: Clock },
    { id: 'reviewer-load',  label: 'Reviewer Load', icon: Users },
  ]
  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
          <p className="text-sm text-gray-500 mt-1">Submission metrics, review performance, and workload data</p>
        </div>
        <TrendingUp className="w-6 h-6 text-gray-300" />
      </div>
      <div className="flex gap-1 p-1 bg-gray-100 rounded-lg mb-6 w-fit">
        {tabs.map(t => (
          <button key={t.id} onClick={() => setTab(t.id)}
            className={`flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
              tab === t.id ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
            }`}>
            <t.icon className="w-3.5 h-3.5" />{t.label}
          </button>
        ))}
      </div>
      {tab === 'overview'      && <OverviewTab />}
      {tab === 'turnaround'    && <TurnaroundTab />}
      {tab === 'reviewer-load' && <ReviewerLoadTab />}
    </div>
  )
}

