import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  FileText, Plus, Search, Filter, RefreshCw,
  XCircle, ChevronRight, ShieldCheck, AlertTriangle, Clock,
} from 'lucide-react'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import type { SubmissionListItem, SubmissionStatus } from '../types/submissions'
import { STATUS_LABELS, STATUS_COLORS } from '../types/submissions'

// ── Reviewer Queue View ──────────────────────────────────────────────────────

interface ReviewQueueItem {
  assignment_id: string
  due_at: string | null
  is_overdue: boolean
  is_due_soon: boolean
  /** The stage this reviewer is personally assigned to. */
  stage: { id: string; name: string; role: string } | null
  submission: {
    id: string
    title: string
    status: SubmissionStatus
    current_version: number
    submission_type: { id: string; slug: string; label: string } | null
    submitter: { id: string; name: string; email: string }
    created_at: string
    program?: { id: string; name: string } | null
    /** The submission's current active review stage — identical for all users. */
    current_stage: { id: string; name: string } | null
    current_stage_entered_at: string | null
  }
}

function ReviewerQueueView() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')

  const { data, isLoading, isError, refetch } = useQuery<{ data: ReviewQueueItem[] }>({
    queryKey: ['my-reviews-queue', 'submissions'],
    queryFn: () => api.get('/submissions/my-reviews', { params: { mode: 'submissions' } }).then((r) => r.data),
    staleTime: 30_000,
  })

  const allItems = data?.data ?? []

  const items = search
    ? allItems.filter(
        (i) =>
          i.submission.title.toLowerCase().includes(search.toLowerCase()) ||
          (i.submission.submission_type?.label ?? '').toLowerCase().includes(search.toLowerCase()),
      )
    : allItems

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Review Queue</h1>
          <p className="text-sm text-gray-500 mt-0.5">Submissions assigned to you for review</p>
        </div>
        <button
          onClick={() => refetch()}
          aria-label="Refresh"
          className="flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
        >
          <RefreshCw className="w-4 h-4" aria-hidden="true" />
        </button>
      </div>

      {/* Search */}
      <div className="mb-5">
        <div className="relative flex-1 min-w-[200px] max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" aria-hidden="true" />
          <input
            className="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Search by title or type…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            aria-label="Search review queue"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <table className="w-full">
            <tbody>
              {Array.from({ length: 5 }).map((_, i) => (
                <SkeletonRow key={i} cols={8} />
              ))}
            </tbody>
          </table>
        ) : isError ? (
          <div className="flex flex-col items-center py-20 text-center">
            <XCircle className="w-10 h-10 text-red-300 mb-3" />
            <p className="text-gray-500">Failed to load review queue.</p>
            <button onClick={() => refetch()} className="mt-3 text-blue-600 text-sm hover:underline">Retry</button>
          </div>
        ) : items.length === 0 ? (
          <div className="flex flex-col items-center py-20 text-center">
            <ShieldCheck className="w-12 h-12 text-gray-200 mb-4" />
            <p className="text-base font-medium text-gray-500">
              {search ? 'No items match your search.' : 'No submissions waiting for your review.'}
            </p>
            <p className="text-sm text-gray-400 mt-1">Submissions assigned to you will appear here.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[880px]">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50">
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Title</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Type</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Program</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Status</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Current Stage</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Stage Since</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Ver.</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Due</th>
                  <th scope="col" className="px-4 py-3 w-8" />
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {items.map((item) => (
                  <tr
                    key={item.assignment_id}
                    className="hover:bg-gray-50 cursor-pointer"
                    onClick={() => navigate(`/submissions/${item.submission.id}`)}
                  >
                    <td className="px-4 py-3">
                      <p className="text-sm font-medium text-gray-900 truncate max-w-[240px]">
                        {item.submission.title}
                      </p>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                      {item.submission.submission_type?.label ?? '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                      {item.submission.program?.name ?? '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[item.submission.status]}`}>
                        {STATUS_LABELS[item.submission.status]}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                      {item.submission.current_stage?.name ?? '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-400 whitespace-nowrap">
                      {item.submission.current_stage_entered_at
                        ? new Date(item.submission.current_stage_entered_at).toLocaleDateString()
                        : '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {item.submission.current_version > 0 ? `v${item.submission.current_version}` : '—'}
                    </td>
                    <td className="px-4 py-3">
                      {item.due_at ? (
                        <span className={`flex items-center gap-1 text-xs whitespace-nowrap ${
                          item.is_overdue ? 'text-red-600 font-semibold' : item.is_due_soon ? 'text-amber-600 font-medium' : 'text-gray-500'
                        }`}>
                          {item.is_overdue ? <AlertTriangle size={11} /> : item.is_due_soon ? <Clock size={11} /> : null}
                          {new Date(item.due_at).toLocaleDateString()}
                        </span>
                      ) : (
                        <span className="text-xs text-gray-300">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <ChevronRight className="w-4 h-4 text-gray-300" />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

// ── Status Badge ──────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: SubmissionStatus }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[status]}`}>
      {STATUS_LABELS[status]}
    </span>
  )
}

// ── Skeleton row (desktop) ────────────────────────────────────────────────────

function SkeletonRow({ cols }: { cols: number }) {
  return (
    <tr className="border-b border-gray-50">
      {Array.from({ length: cols }).map((_, i) => (
        <td key={i} className="px-4 py-3">
          <div className="h-3.5 bg-gray-100 rounded animate-pulse" style={{ width: i === 0 ? '60%' : '50%' }} />
        </td>
      ))}
    </tr>
  )
}

// ── Skeleton card (mobile) ────────────────────────────────────────────────────

function SkeletonCard() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-4 animate-pulse space-y-2">
      <div className="h-4 w-3/4 bg-gray-100 rounded" />
      <div className="h-3 w-1/2 bg-gray-100 rounded" />
      <div className="flex gap-2 mt-2">
        <div className="h-5 w-20 bg-gray-100 rounded-full" />
        <div className="h-5 w-16 bg-gray-100 rounded-full" />
      </div>
    </div>
  )
}

// ── Page ──────────────────────────────────────────────────────────────────────

interface SubmissionResponse {
  data: SubmissionListItem[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ── Compact review queue for admin/coordinator users who also have reviewer assignments ──

function AdminReviewerQueueSection() {
  const navigate = useNavigate()
  const { data, isLoading } = useQuery<{ data: ReviewQueueItem[] }>({
    queryKey: ['my-reviews-queue', 'assignments'],
    queryFn: () => api.get('/submissions/my-reviews', { params: { mode: 'assignments' } }).then((r) => r.data),
    staleTime: 30_000,
  })

  const items = (data?.data ?? []).filter(
    (i) => i.submission.status === 'IN_REVIEW',
  )

  if (!isLoading && items.length === 0) return null

  return (
    <div className="mt-8">
      <div className="flex items-center gap-2 mb-3">
        <ShieldCheck className="w-4 h-4 text-blue-600" />
        <h2 className="text-base font-semibold text-gray-900">My Review Assignments</h2>
        {!isLoading && (
          <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
            {items.length} pending
          </span>
        )}
      </div>
      <div className="bg-white rounded-xl border border-blue-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <table className="w-full"><tbody>{Array.from({ length: 3 }).map((_, i) => <SkeletonRow key={i} cols={6} />)}</tbody></table>
        ) : (
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-100 bg-blue-50">
                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-2.5">Title</th>
                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-2.5">Type</th>
                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-2.5">Current Stage</th>
                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-2.5">Stage Since</th>
                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-2.5">Due</th>
                <th className="px-4 py-2.5 w-8" />
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {items.map((item) => (
                <tr
                  key={item.assignment_id}
                  className="hover:bg-blue-50 cursor-pointer"
                  onClick={() => navigate(`/submissions/${item.submission.id}`)}
                >
                  <td className="px-4 py-3 text-sm font-medium text-gray-900 max-w-[280px] truncate">
                    {item.submission.title}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                    {item.submission.submission_type?.label ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                    {item.submission.current_stage?.name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-400 whitespace-nowrap">
                    {item.submission.current_stage_entered_at
                      ? new Date(item.submission.current_stage_entered_at).toLocaleDateString()
                      : '—'}
                  </td>
                  <td className="px-4 py-3">
                    {item.due_at ? (
                      <span className={`flex items-center gap-1 text-xs whitespace-nowrap ${
                        item.is_overdue ? 'text-red-600 font-semibold' : item.is_due_soon ? 'text-amber-600 font-medium' : 'text-gray-500'
                      }`}>
                        {item.is_overdue ? <AlertTriangle size={11} /> : item.is_due_soon ? <Clock size={11} /> : null}
                        {new Date(item.due_at).toLocaleDateString()}
                      </span>
                    ) : (
                      <span className="text-xs text-gray-300">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <ChevronRight className="w-4 h-4 text-gray-300" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}

export default function SubmissionsPage() {
  const navigate = useNavigate()
  const user = useAuthStore((s) => s.user)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)

  const isAdmin = user?.roles?.some((r: string) => ['admin', 'coordinator'].includes(r))
  const hasReviewerRole = user?.roles?.includes('reviewer') ?? false
  // A pure reviewer (no admin/coordinator) sees only their review queue.
  // A multi-role user (e.g. admin + reviewer) stays on the admin view but also
  // sees a review queue section below (handled by isAssignedReviewer flag).
  const isPureReviewer = !isAdmin && hasReviewerRole
  const canSubmit = user?.roles?.includes('student')

  // Reviewer role: show the review queue instead of own submissions
  if (isPureReviewer) {
    return <ReviewerQueueView />
  }

  const { data, isLoading, isError, refetch } = useQuery<SubmissionResponse>({
    queryKey: ['submissions', page, search, statusFilter],
    queryFn: () =>
      api.get('/submissions', {
        params: { page, per_page: 20, search: search || undefined, status: statusFilter || undefined },
      }).then((r) => r.data),
  })

  const statuses: SubmissionStatus[] = [
    'DRAFT', 'SUBMITTED', 'IN_REVIEW', 'REVISION_REQUIRED',
    'ACCEPTED', 'CONDITIONALLY_ACCEPTED', 'REJECTED', 'WITHDRAWN',
  ]

  const colCount = isAdmin ? 10 : 9

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isAdmin ? 'All Submissions' : 'My Submissions'}
          </h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {isAdmin
              ? 'View and manage all research submissions'
              : 'Track and manage your research submissions'}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => refetch()}
            aria-label="Refresh submissions"
            className="flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          >
            <RefreshCw className="w-4 h-4" aria-hidden="true" />
          </button>
          {canSubmit && (
            <button
              onClick={() => navigate('/submissions/new')}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
            >
              <Plus className="w-4 h-4" aria-hidden="true" />
              <span className="hidden sm:inline">New Submission</span>
              <span className="sm:hidden">New</span>
            </button>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <div className="relative flex-1 min-w-[200px] max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" aria-hidden="true" />
          <input
            className="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Search submissions…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            aria-label="Search submissions"
          />
        </div>
        <div className="relative">
          <Filter className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" aria-hidden="true" />
          <select
            className="pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
            value={statusFilter}
            onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
            aria-label="Filter by status"
          >
            <option value="">All statuses</option>
            {statuses.map((s) => (
              <option key={s} value={s}>{STATUS_LABELS[s]}</option>
            ))}
          </select>
        </div>
      </div>

      {/* ── Mobile card list (< md) ─────────────────────────────────── */}
      <div className="md:hidden space-y-3">
        {isLoading ? (
          Array.from({ length: 5 }).map((_, i) => <SkeletonCard key={i} />)
        ) : isError ? (
          <div className="flex flex-col items-center py-16 text-center">
            <XCircle className="w-10 h-10 text-red-300 mb-3" aria-hidden="true" />
            <p className="text-gray-500 text-sm">Failed to load submissions.</p>
            <button onClick={() => refetch()} className="mt-3 text-blue-600 text-sm hover:underline">Retry</button>
          </div>
        ) : !data?.data?.length ? (
          <MobileEmptyState hasFilters={!!(search || statusFilter)} onNew={canSubmit ? () => navigate('/submissions/new') : undefined} />
        ) : (
          <>
            {data.data.map((sub) => (
              <button
                key={sub.id}
                className="w-full text-left bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:border-blue-200 hover:shadow-md transition-all active:scale-[0.99]"
                onClick={() => navigate(`/submissions/${sub.id}`)}
              >
                <div className="flex items-start justify-between gap-2">
                  <p className="text-sm font-semibold text-gray-900 leading-snug flex-1">{sub.title}</p>
                  <ChevronRight className="w-4 h-4 text-gray-300 flex-shrink-0 mt-0.5" aria-hidden="true" />
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  {sub.submission_type?.label ?? '—'}
                  {isAdmin && sub.submitter ? ` · ${sub.submitter.name}` : ''}
                </p>
                <div className="flex flex-wrap items-center gap-2 mt-2">
                  <StatusBadge status={sub.status} />
                  {sub.current_version > 0 && (
                    <span className="text-xs text-gray-400">v{sub.current_version}</span>
                  )}
                  <span className="text-xs text-gray-400 ml-auto">
                    {new Date(sub.updated_at).toLocaleDateString()}
                  </span>
                </div>
              </button>
            ))}
            <MobilePagination
              meta={data.meta}
              page={page}
              setPage={setPage}
            />
          </>
        )}
      </div>

      {/* ── Desktop table (≥ md) ────────────────────────────────────── */}
      <div className="hidden md:block bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <table className="w-full">
            <tbody>
              {Array.from({ length: 8 }).map((_, i) => (
                <SkeletonRow key={i} cols={colCount} />
              ))}
            </tbody>
          </table>
        ) : isError ? (
          <div className="flex flex-col items-center py-20 text-center">
            <XCircle className="w-10 h-10 text-red-300 mb-3" aria-hidden="true" />
            <p className="text-gray-500">Failed to load submissions.</p>
            <button onClick={() => refetch()} className="mt-3 text-blue-600 text-sm hover:underline">Retry</button>
          </div>
        ) : !data?.data?.length ? (
          <div className="flex flex-col items-center py-20 text-center">
            <FileText className="w-12 h-12 text-gray-200 mb-4" aria-hidden="true" />
            <p className="text-base font-medium text-gray-500">
              {search || statusFilter ? 'No submissions match your filters.' : 'No submissions yet.'}
            </p>
            {!search && !statusFilter && canSubmit && (
              <button
                onClick={() => navigate('/submissions/new')}
                className="mt-4 flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
              >
                <Plus className="w-4 h-4" aria-hidden="true" />
                Create your first submission
              </button>
            )}
          </div>
        ) : (
          <>
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50">
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Title</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Type</th>
                  {isAdmin && (
                    <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Submitter</th>
                  )}
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Program</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Status</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Current Stage</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Stage Since</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Ver.</th>
                  <th scope="col" className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Updated</th>
                  <th scope="col" className="px-4 py-3 w-8" />
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {data.data.map((sub) => (
                  <tr
                    key={sub.id}
                    className="hover:bg-gray-50 cursor-pointer"
                    onClick={() => navigate(`/submissions/${sub.id}`)}
                  >
                    <td className="px-4 py-3">
                      <p className="text-sm font-medium text-gray-900 truncate max-w-[260px]">{sub.title}</p>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                      {sub.submission_type?.label ?? '—'}
                    </td>
                    {isAdmin && (
                      <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{sub.submitter.name}</td>
                    )}
                    <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                      {sub.program?.name ?? '—'}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={sub.status} />
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                      {sub.current_stage?.name ?? '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-400 whitespace-nowrap">
                      {sub.current_stage_entered_at
                        ? new Date(sub.current_stage_entered_at).toLocaleDateString()
                        : '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {sub.current_version > 0 ? `v${sub.current_version}` : '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-400 whitespace-nowrap">
                      {new Date(sub.updated_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-3">
                      <ChevronRight className="w-4 h-4 text-gray-300" aria-hidden="true" />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Pagination */}
            {data.meta.last_page > 1 && (
              <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
                <p className="text-sm text-gray-500">
                  Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} total
                </p>
                <div className="flex gap-2">
                  <button
                    disabled={page <= 1}
                    onClick={() => setPage((p) => p - 1)}
                    className="px-3 py-1.5 border border-gray-200 rounded text-sm disabled:opacity-40 hover:bg-white"
                  >
                    Previous
                  </button>
                  <button
                    disabled={page >= data.meta.last_page}
                    onClick={() => setPage((p) => p + 1)}
                    className="px-3 py-1.5 border border-gray-200 rounded text-sm disabled:opacity-40 hover:bg-white"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* ── Review queue section for admin/coordinator users who are also assigned reviewers ── */}
      {hasReviewerRole && <AdminReviewerQueueSection />}
    </div>
  )
}

// ── Mobile helpers ────────────────────────────────────────────────────────────

function MobileEmptyState({ hasFilters, onNew }: { hasFilters: boolean; onNew?: () => void }) {
  return (
    <div className="flex flex-col items-center py-16 text-center bg-white rounded-xl border border-gray-200">
      <FileText className="w-12 h-12 text-gray-200 mb-4" aria-hidden="true" />
      <p className="text-base font-medium text-gray-500">
        {hasFilters ? 'No submissions match your filters.' : 'No submissions yet.'}
      </p>
      {!hasFilters && onNew && (
        <button
          onClick={onNew}
          className="mt-4 flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
        >
          <Plus className="w-4 h-4" aria-hidden="true" />
          Create your first submission
        </button>
      )}
    </div>
  )
}

function MobilePagination({
  meta,
  page,
  setPage,
}: {
  meta: { current_page: number; last_page: number; total: number }
  page: number
  setPage: React.Dispatch<React.SetStateAction<number>>
}) {
  if (meta.last_page <= 1) return null
  return (
    <div className="flex items-center justify-between pt-2">
      <span className="text-xs text-gray-500">Page {meta.current_page} / {meta.last_page}</span>
      <div className="flex gap-2">
        <button
          disabled={page <= 1}
          onClick={() => setPage((p) => p - 1)}
          className="px-3 py-1.5 border border-gray-200 rounded text-sm disabled:opacity-40 bg-white"
        >
          Prev
        </button>
        <button
          disabled={page >= meta.last_page}
          onClick={() => setPage((p) => p + 1)}
          className="px-3 py-1.5 border border-gray-200 rounded text-sm disabled:opacity-40 bg-white"
        >
          Next
        </button>
      </div>
    </div>
  )
}

