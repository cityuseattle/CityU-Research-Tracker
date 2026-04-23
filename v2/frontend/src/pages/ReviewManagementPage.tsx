import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import {
  CalendarDays, UserX, Inbox as InboxIcon, ChevronRight,
  CheckCircle2, AlertCircle, Loader2,
} from 'lucide-react'
import api from '../lib/axios'
import { useToastHelpers } from '../lib/toast'

// ── Types ─────────────────────────────────────────────────────────────────────

interface PendingExtension {
  id: string
  submission_id: string
  submission_title: string | null
  submission_type: string | null
  stage_name: string | null
  reviewer_name: string | null
  reviewer_email: string | null
  extension_reason: string | null
  extension_requested_days: number | null
  extension_requested_at: string | null
  due_at: string | null
}

interface ConflictDeclaration {
  id: string
  submission_id: string
  submission_title: string | null
  submission_type: string | null
  stage_name: string | null
  reviewer_name: string | null
  reviewer_email: string | null
  conflict_reason: string | null
  conflict_flagged_at: string | null
}

interface IncomingSubmission {
  id: string
  title: string
  type: string | null
  submitter: string | null
  status: string
  created_at: string
}

// ── Extension Requests Panel ──────────────────────────────────────────────────

function ExtensionPanel({ items }: { items: PendingExtension[] }) {
  const qc = useQueryClient()
  const toast = useToastHelpers()

  const resolve = useMutation({
    mutationFn: ({ id, submissionId, action }: { id: string; submissionId: string; action: 'approved' | 'rejected' }) =>
      api.post(`/submissions/${submissionId}/reviewers/${id}/request-extension`, { action }),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['coordinator-pending-actions'] })
      toast.success(
        vars.action === 'approved' ? 'Extension approved' : 'Extension rejected',
        vars.action === 'approved' ? "The reviewer's deadline has been extended." : 'The reviewer has been notified.',
      )
    },
    onError: (e: any) => toast.error('Failed', e?.response?.data?.message ?? 'Could not process request.'),
  })

  return (
    <div className="bg-white rounded-xl border-2 border-blue-200 shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-blue-100 bg-blue-50 text-blue-700">
        <CalendarDays size={15} />
        <span className="text-sm font-semibold">Pending Extension Requests</span>
        <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{items.length}</span>
      </div>

      {items.length === 0 ? (
        <div className="flex items-center gap-2 px-5 py-6 text-sm text-gray-400">
          <CheckCircle2 size={16} className="text-green-400" />
          No pending extension requests.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[750px]">
            <thead>
              <tr className="border-b border-gray-100 bg-gray-50/80">
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Reviewer</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Submission</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Stage</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Reason</th>
                <th className="text-right text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Days Req.</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Current Due</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Requested</th>
                <th className="px-4 py-2.5 w-32" />
              </tr>
            </thead>
            <tbody>
              {items.map((ext) => (
                <tr key={ext.id} className="border-b border-gray-50 hover:bg-blue-50/20">
                  <td className="px-4 py-3">
                    <p className="text-sm font-medium text-gray-900">{ext.reviewer_name ?? '—'}</p>
                    <p className="text-xs text-gray-400">{ext.reviewer_email ?? ''}</p>
                  </td>
                  <td className="px-4 py-3 max-w-[180px]">
                    <p className="text-sm text-gray-800 truncate" title={ext.submission_title ?? ''}>{ext.submission_title ?? '—'}</p>
                    <p className="text-xs text-gray-400">{ext.submission_type ?? ''}</p>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{ext.stage_name ?? '—'}</td>
                  <td className="px-4 py-3 max-w-[200px]">
                    <p className="text-xs text-gray-600 line-clamp-2">{ext.extension_reason ?? '—'}</p>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <span className="text-sm font-semibold text-blue-600">+{ext.extension_requested_days ?? 0}d</span>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    {ext.due_at ? new Date(ext.due_at).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    {ext.extension_requested_at ? new Date(ext.extension_requested_at).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 justify-end">
                      <button
                        onClick={() => resolve.mutate({ id: ext.id, submissionId: ext.submission_id, action: 'approved' })}
                        disabled={resolve.isPending}
                        className="px-2.5 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium disabled:opacity-50 transition-colors"
                      >Approve</button>
                      <button
                        onClick={() => resolve.mutate({ id: ext.id, submissionId: ext.submission_id, action: 'rejected' })}
                        disabled={resolve.isPending}
                        className="px-2.5 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium disabled:opacity-50 transition-colors"
                      >Reject</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

// ── Conflict of Interest Panel ────────────────────────────────────────────────

function ConflictPanel({ items }: { items: ConflictDeclaration[] }) {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const toast = useToastHelpers()

  const resolve = useMutation({
    mutationFn: ({ id, submissionId, action }: { id: string; submissionId: string; action: 'continue' | 'reassign' }) =>
      api.post(`/submissions/${submissionId}/reviewers/${id}/resolve-conflict`, { action }),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['coordinator-pending-actions'] })
      toast.success(
        vars.action === 'continue' ? 'Reviewer cleared' : 'Reviewer removed',
        vars.action === 'continue'
          ? 'The conflict flag has been removed. Reviewer may continue.'
          : 'The reviewer has been removed. You can now assign a replacement.',
      )
    },
    onError: (e: any) => toast.error('Failed', e?.response?.data?.message ?? 'Could not process request.'),
  })

  return (
    <div className="bg-white rounded-xl border-2 border-red-200 shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-red-100 bg-red-50 text-red-700">
        <UserX size={15} />
        <span className="text-sm font-semibold">Conflict of Interest Declarations</span>
        <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{items.length}</span>
      </div>

      {items.length === 0 ? (
        <div className="flex items-center gap-2 px-5 py-6 text-sm text-gray-400">
          <CheckCircle2 size={16} className="text-green-400" />
          No conflict declarations.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[820px]">
            <thead>
              <tr className="border-b border-gray-100 bg-gray-50/80">
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Reviewer</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Submission</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Stage</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Conflict Reason</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Flagged</th>
                <th className="px-4 py-2.5 w-48" />
              </tr>
            </thead>
            <tbody>
              {items.map((c) => (
                <tr key={c.id} className="border-b border-gray-50 hover:bg-red-50/20">
                  <td className="px-4 py-3">
                    <p className="text-sm font-medium text-gray-900">{c.reviewer_name ?? '—'}</p>
                    <p className="text-xs text-gray-400">{c.reviewer_email ?? ''}</p>
                  </td>
                  <td className="px-4 py-3 max-w-[180px]">
                    <button
                      onClick={() => navigate(`/submissions/${c.submission_id}`)}
                      className="text-sm text-indigo-600 hover:underline truncate block max-w-full text-left"
                      title={c.submission_title ?? ''}
                    >
                      {c.submission_title ?? '—'}
                    </button>
                    <p className="text-xs text-gray-400">{c.submission_type ?? ''}</p>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{c.stage_name ?? '—'}</td>
                  <td className="px-4 py-3 max-w-[220px]">
                    <p className="text-xs text-gray-600 line-clamp-2">{c.conflict_reason ?? '—'}</p>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    {c.conflict_flagged_at ? new Date(c.conflict_flagged_at).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 justify-end">
                      <button
                        onClick={() => resolve.mutate({ id: c.id, submissionId: c.submission_id, action: 'continue' })}
                        disabled={resolve.isPending}
                        title="Allow reviewer to continue"
                        className="px-2.5 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium disabled:opacity-50 transition-colors whitespace-nowrap"
                      >Keep Reviewer</button>
                      <button
                        onClick={() => resolve.mutate({ id: c.id, submissionId: c.submission_id, action: 'reassign' })}
                        disabled={resolve.isPending}
                        title="Remove reviewer and assign a replacement"
                        className="px-2.5 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium disabled:opacity-50 transition-colors whitespace-nowrap"
                      >Remove &amp; Reassign</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

// ── Incoming Submissions Panel ────────────────────────────────────────────────

function IncomingPanel({ items }: { items: IncomingSubmission[] }) {
  const navigate = useNavigate()
  const STATUS_BADGE: Record<string, string> = {
    SUBMITTED:          'bg-blue-100 text-blue-700',
    AWAITING_REVIEWERS: 'bg-amber-100 text-amber-700',
  }
  return (
    <div className="bg-white rounded-xl border-2 border-amber-200 shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-amber-100 bg-amber-50 text-amber-700">
        <InboxIcon size={15} />
        <span className="text-sm font-semibold">Incoming Submissions — Awaiting Assignment</span>
        <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{items.length}</span>
      </div>

      {items.length === 0 ? (
        <div className="flex items-center gap-2 px-5 py-6 text-sm text-gray-400">
          <CheckCircle2 size={16} className="text-green-400" />
          No incoming submissions awaiting assignment.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[600px]">
            <thead>
              <tr className="border-b border-gray-100 bg-gray-50/80">
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Title</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Type</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Submitted By</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Status</th>
                <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Date</th>
                <th className="px-4 py-2.5 w-16" />
              </tr>
            </thead>
            <tbody>
              {items.map((s) => (
                <tr
                  key={s.id}
                  className="border-b border-gray-50 hover:bg-amber-50/30 cursor-pointer group"
                  onClick={() => navigate(`/submissions/${s.id}`)}
                >
                  <td className="px-4 py-3 max-w-[240px]">
                    <p className="text-sm font-medium text-gray-900 truncate" title={s.title}>{s.title}</p>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{s.type ?? '—'}</td>
                  <td className="px-4 py-3 text-xs text-gray-600">{s.submitter ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex text-[10px] px-2 py-0.5 rounded-full font-medium ${STATUS_BADGE[s.status] ?? 'bg-gray-100 text-gray-600'}`}>
                      {s.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    {new Date(s.created_at).toLocaleDateString()}
                  </td>
                  <td className="px-4 py-3">
                    <ChevronRight size={14} className="text-gray-300 group-hover:text-amber-500" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function ReviewManagementPage() {
  const toast = useToastHelpers()

  const { data, isLoading, isError, refetch } = useQuery<{
    extensions: PendingExtension[]
    conflicts: ConflictDeclaration[]
    unassigned: IncomingSubmission[]
  }>({
    queryKey: ['coordinator-pending-actions'],
    queryFn: () => api.get('/admin/reviewer-pending-actions').then((r) => r.data),
    staleTime: 30_000,
  })

  const extensions  = data?.extensions  ?? []
  const conflicts   = data?.conflicts   ?? []
  const unassigned  = data?.unassigned  ?? []

  const totalPending = extensions.length + conflicts.length

  return (
    <div className="p-6 max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Review Management</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Manage reviewer extension requests, conflict declarations, and incoming submissions
          </p>
        </div>
        <div className="flex items-center gap-3">
          {totalPending > 0 && (
            <span className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 bg-red-100 text-red-700 rounded-full">
              <AlertCircle size={13} />
              {totalPending} action{totalPending !== 1 ? 's' : ''} required
            </span>
          )}
          <button
            onClick={() => { refetch(); toast.success('Refreshed') }}
            className="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors"
          >
            Refresh
          </button>
        </div>
      </div>

      {isError && (
        <div className="bg-red-50 border border-red-200 rounded-xl px-5 py-4 text-sm text-red-700">
          Failed to load pending actions. Please refresh.
        </div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center py-20 gap-2 text-gray-400">
          <Loader2 size={18} className="animate-spin" />
          <span className="text-sm">Loading…</span>
        </div>
      ) : (
        <div className="space-y-6">
          <ExtensionPanel   items={extensions} />
          <ConflictPanel    items={conflicts} />
          <IncomingPanel    items={unassigned} />
        </div>
      )}
    </div>
  )
}
