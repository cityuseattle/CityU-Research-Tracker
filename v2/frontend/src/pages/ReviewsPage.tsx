import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import {
  ShieldCheck, Hourglass, CheckCircle2, Archive,
  AlertCircle, CalendarDays, Ban, X,
  Loader2, AlertTriangle, Clock, ChevronRight, Gavel,
  UserX, InboxIcon,
} from 'lucide-react'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import { useToastHelpers } from '../lib/toast'
import type { SubmissionStatus } from '../types/submissions'

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

interface ReviewAssignment {
  assignment_id: string
  assignment_status: 'pending' | 'accepted' | 'declined' | 'completed'
  due_at: string | null
  decision: 'approve' | 'reject' | 'revise' | null
  decision_at: string | null
  comments: string | null
  is_overdue: boolean
  is_due_soon: boolean
  extension_status: 'pending' | 'approved' | 'rejected' | null
  extension_reason: string | null
  extension_requested_days: number | null
  extension_requested_at: string | null
  conflict_flagged: boolean
  conflict_reason: string | null
  stage: { id: string; name: string; role: string } | null
  submission: {
    id: string
    title: string
    status: SubmissionStatus
    current_version: number
    submission_type: { id: string; slug: string; label: string } | null
    submitter: { id: string; name: string; email: string }
    created_at: string
    program?: { name: string } | null
  }
  previous_stage_decision?: string | null
  previous_stage_decision_at?: string | null
}

interface GatedItem {
  id: string
  title: string
  status: string
  submitter_name: string | null
  submission_type: string | null
  program: string | null
  current_version: number
  updated_at: string
  pending_gatekeeper_stage_name: string | null
  pending_gatekeeper_stage_outcome: string | null
}

const DECISION_LABELS: Record<string, string> = {
  approve: 'Approved',
  reject:  'Rejected',
  revise:  'Revision Requested',
}

const DECISION_COLORS: Record<string, string> = {
  approve: 'bg-green-100 text-green-700',
  reject:  'bg-red-100 text-red-600',
  revise:  'bg-orange-100 text-orange-700',
}

const TERMINAL_STATUSES: SubmissionStatus[] = [
  'WITHDRAWN', 'REJECTED', 'ACCEPTED', 'CONDITIONALLY_ACCEPTED',
]

function classifyItem(item: ReviewAssignment): 'awaiting' | 'completed' | 'others' {
  if (item.decision) return 'completed'
  if (TERMINAL_STATUSES.includes(item.submission.status)) return 'others'
  return 'awaiting'
}

// ── Coordinator: Pending Extension Requests Panel ─────────────────────────────

function CoordExtensionPanel({ items }: { items: PendingExtension[] }) {
  const qc = useQueryClient()
  const toast = useToastHelpers()

  const resolve = useMutation({
    mutationFn: ({ id, submissionId, action }: { id: string; submissionId: string; action: 'approved' | 'rejected' }) =>
      api.post(`/submissions/${submissionId}/reviewers/${id}/request-extension`, { action }),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['coordinator-pending-actions'] })
      toast.success(
        vars.action === 'approved' ? 'Extension approved' : 'Extension rejected',
        vars.action === 'approved' ? 'The reviewer\'s deadline has been extended.' : 'The reviewer has been notified.',
      )
    },
    onError: (e: any) => {
      toast.error('Failed', e?.response?.data?.message ?? 'Could not process request.')
    },
  })

  return (
    <div className="bg-white rounded-xl border-2 border-blue-200 shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-blue-100 bg-blue-50 text-blue-700">
        <CalendarDays size={15} />
        <span className="text-sm font-semibold">Pending Extension Requests</span>
        <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{items.length}</span>
      </div>
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
              <th className="px-4 py-2.5 w-32" />
            </tr>
          </thead>
          <tbody>
            {items.map((ext) => (
              <tr key={ext.id} className="border-b border-gray-50 hover:bg-gray-50">
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
    </div>
  )
}

// ── Coordinator: Conflict of Interest Declarations Panel ──────────────────────

function CoordConflictPanel({ items }: { items: ConflictDeclaration[] }) {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const toast = useToastHelpers()

  const resolve = useMutation({
    mutationFn: ({ id, submissionId, action }: { id: string; submissionId: string; action: 'continue' | 'reassign' }) =>
      api.post(`/submissions/${submissionId}/reviewers/${id}/resolve-conflict`, { action }),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['coordinator-pending-actions'] })
      if (vars.action === 'continue') {
        toast.success('Reviewer cleared', 'The conflict flag has been removed. Reviewer may continue.')
      } else {
        toast.success('Reviewer removed', 'The reviewer has been removed. You can now assign a replacement.')
      }
    },
    onError: (e: any) => {
      toast.error('Failed', e?.response?.data?.message ?? 'Could not process request.')
    },
  })

  return (
    <div className="bg-white rounded-xl border-2 border-red-200 shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3 border-b border-red-100 bg-red-50 text-red-700">
        <UserX size={15} />
        <span className="text-sm font-semibold">Conflict of Interest Declarations</span>
        <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{items.length}</span>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[800px]">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50/80">
              <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Reviewer</th>
              <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Submission</th>
              <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Stage</th>
              <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Conflict Reason</th>
              <th className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Flagged</th>
              <th className="px-4 py-2.5 w-44" />
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
                      title="Allow reviewer to continue despite declared conflict"
                      className="px-2.5 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium disabled:opacity-50 transition-colors whitespace-nowrap"
                    >
                      Keep Reviewer
                    </button>
                    <button
                      onClick={() => resolve.mutate({ id: c.id, submissionId: c.submission_id, action: 'reassign' })}
                      disabled={resolve.isPending}
                      title="Remove reviewer and assign a replacement"
                      className="px-2.5 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium disabled:opacity-50 transition-colors whitespace-nowrap"
                    >
                      Remove &amp; Reassign
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

// ── Coordinator: Incoming (Unassigned) Submissions Panel ──────────────────────

function CoordIncomingPanel({ items }: { items: IncomingSubmission[] }) {
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
              <tr key={s.id} className="border-b border-gray-50 hover:bg-amber-50/30 cursor-pointer group" onClick={() => navigate(`/submissions/${s.id}`)}>
                <td className="px-4 py-3 max-w-[220px]">
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
    </div>
  )
}

// ── Table Row ─────────────────────────────────────────────────────────────────

function AssignmentTableRow({
  item,
  onClick,
  onExtension,
  onConflict,
}: {
  item: ReviewAssignment
  onClick: () => void
  onExtension: (item: ReviewAssignment) => void
  onConflict: (item: ReviewAssignment) => void
}) {
  const sub = item.submission
  const isAwaiting = classifyItem(item) === 'awaiting'

  const dueDateEl = item.due_at ? (
    <span
      className={`flex items-center gap-1 text-xs whitespace-nowrap ${
        item.is_overdue
          ? 'text-red-600 font-semibold'
          : item.is_due_soon
          ? 'text-amber-600 font-medium'
          : 'text-gray-500'
      }`}
    >
      {item.is_overdue ? <AlertTriangle size={11} /> : item.is_due_soon ? <Clock size={11} /> : null}
      {new Date(item.due_at).toLocaleDateString()}
    </span>
  ) : (
    <span className="text-xs text-gray-300">—</span>
  )

  return (
    <tr
      className="border-b border-gray-50 hover:bg-gray-50 cursor-pointer group"
      onClick={onClick}
    >
      {/* Title */}
      <td className="px-4 py-3 max-w-[220px]">
        <p className="text-sm font-medium text-gray-900 truncate" title={sub.title}>
          {sub.title}
        </p>
        <div className="flex flex-wrap gap-1 mt-1">
          {item.extension_status === 'pending' && (
            <span className="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
              <CalendarDays size={9} />Ext. pending
            </span>
          )}
          {item.conflict_flagged && (
            <span className="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">
              <AlertCircle size={9} />Conflict
            </span>
          )}
        </div>
      </td>

      {/* Type */}
      <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
        {sub.submission_type?.label ?? '—'}
      </td>

      {/* Current Stage */}
      <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
        {item.stage?.name ?? '—'}
      </td>

      {/* Submission Date */}
      <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
        {new Date(sub.created_at).toLocaleDateString()}
      </td>

      {/* Previous Stage Decision Date */}
      <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
        {item.previous_stage_decision_at
          ? new Date(item.previous_stage_decision_at).toLocaleDateString()
          : <span className="text-gray-300">—</span>}
      </td>

      {/* Previous Stage Decision */}
      <td className="px-4 py-3">
        {item.previous_stage_decision ? (
          <span className={`inline-flex text-[10px] px-2 py-0.5 rounded-full font-medium ${DECISION_COLORS[item.previous_stage_decision] ?? 'bg-gray-100 text-gray-600'}`}>
            {DECISION_LABELS[item.previous_stage_decision] ?? item.previous_stage_decision}
          </span>
        ) : item.decision ? (
          <span className={`inline-flex text-[10px] px-2 py-0.5 rounded-full font-medium ${DECISION_COLORS[item.decision]}`}>
            {DECISION_LABELS[item.decision]}
          </span>
        ) : (
          <span className="text-xs text-gray-300">—</span>
        )}
      </td>

      {/* Due Date */}
      <td className="px-4 py-3">{dueDateEl}</td>

      {/* Actions */}
      <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center gap-1">
          {isAwaiting && !item.conflict_flagged && (
            <>
              {(!item.extension_status || item.extension_status === 'rejected') && (
                <button
                  onClick={() => onExtension(item)}
                  title="Request extension"
                  className="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                >
                  <CalendarDays size={14} />
                </button>
              )}
              <button
                onClick={() => onConflict(item)}
                title="Flag conflict of interest"
                className="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
              >
                <Ban size={14} />
              </button>
            </>
          )}
          <ChevronRight size={14} className="text-gray-300 group-hover:text-gray-500" />
        </div>
      </td>
    </tr>
  )
}

// ── Section Table ─────────────────────────────────────────────────────────────

function SectionTable({
  title,
  count,
  icon: Icon,
  headerClass,
  items,
  onExtension,
  onConflict,
  navigate,
}: {
  title: string
  count: number
  icon: React.ElementType
  headerClass: string
  items: ReviewAssignment[]
  onExtension: (item: ReviewAssignment) => void
  onConflict: (item: ReviewAssignment) => void
  navigate: ReturnType<typeof useNavigate>
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div className={`flex items-center gap-2 px-5 py-3 border-b border-gray-100 ${headerClass}`}>
        <Icon size={15} />
        <span className="text-sm font-semibold">{title}</span>
        <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{count}</span>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[900px]">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50/80">
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Title</th>
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Type</th>
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Your Stage</th>
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Submitted</th>
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Prev. Decision Date</th>
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Prev. Decision</th>
              <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Due Date</th>
              <th scope="col" className="px-4 py-2.5 w-24" />
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <AssignmentTableRow
                key={item.assignment_id}
                item={item}
                onClick={() => navigate(`/submissions/${item.submission.id}`)}
                onExtension={onExtension}
                onConflict={onConflict}
              />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

// ── Extension Request Modal ───────────────────────────────────────────────────

function ExtensionModal({ item, onClose }: { item: ReviewAssignment; onClose: () => void }) {
  const [reason, setReason] = useState('')
  const [days, setDays] = useState(7)
  const qc = useQueryClient()
  const toast = useToastHelpers()

  const mutation = useMutation({
    mutationFn: () =>
      api.post(
        `/submissions/${item.submission.id}/reviewers/${item.assignment_id}/request-extension`,
        { reason, requested_days: days },
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      toast.success('Extension requested', 'The coordinator will be notified.')
      onClose()
    },
    onError: (e: any) => {
      toast.error('Failed', e?.response?.data?.message ?? 'Could not submit request.')
    },
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-base font-semibold text-gray-900">Request Deadline Extension</h3>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X size={16} />
          </button>
        </div>
        <p className="text-sm text-gray-600 mb-4">
          For: <strong>{item.submission.title}</strong>
          {item.due_at && <> &middot; Current due: {new Date(item.due_at).toLocaleDateString()}</>}
        </p>
        <div className="space-y-4">
          <div>
            <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">
              Reason for extension
            </label>
            <textarea
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"
              rows={3}
              placeholder="Explain why you need more time…"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              maxLength={2000}
            />
          </div>
          <div>
            <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">
              Additional days needed
            </label>
            <input
              type="number"
              min={1}
              max={90}
              value={days}
              onChange={(e) => setDays(Number(e.target.value))}
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            />
          </div>
        </div>
        <div className="flex gap-3 justify-end mt-6">
          <button
            onClick={onClose}
            className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={!reason.trim() || mutation.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
          >
            {mutation.isPending && <Loader2 size={13} className="animate-spin" />}
            Submit Request
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Conflict of Interest Modal ────────────────────────────────────────────────

function ConflictModal({ item, onClose }: { item: ReviewAssignment; onClose: () => void }) {
  const [reason, setReason] = useState('')
  const qc = useQueryClient()
  const toast = useToastHelpers()

  const mutation = useMutation({
    mutationFn: () =>
      api.post(
        `/submissions/${item.submission.id}/reviewers/${item.assignment_id}/flag-conflict`,
        { reason },
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      toast.success('Conflict flagged', 'The coordinator will be notified to reassign a reviewer.')
      onClose()
    },
    onError: (e: any) => {
      toast.error('Failed', e?.response?.data?.message ?? 'Could not flag conflict.')
    },
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <AlertCircle size={18} className="text-red-500" />
            <h3 className="text-base font-semibold text-gray-900">Flag Conflict of Interest</h3>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X size={16} />
          </button>
        </div>
        <div className="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
          <p className="text-sm text-red-800">
            By flagging a conflict of interest, you are requesting that a different reviewer be assigned.
            The coordinator or admin will be notified immediately.
          </p>
        </div>
        <p className="text-sm text-gray-600 mb-4">
          Submission: <strong>{item.submission.title}</strong>
        </p>
        <div>
          <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">
            Reason for conflict
          </label>
          <textarea
            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
            rows={3}
            placeholder="Describe the conflict (e.g., personal relationship, professional interest)…"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            maxLength={2000}
          />
        </div>
        <div className="flex gap-3 justify-end mt-6">
          <button
            onClick={onClose}
            className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={!reason.trim() || mutation.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
          >
            {mutation.isPending && <Loader2 size={13} className="animate-spin" />}
            Flag Conflict
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function ReviewsPage() {
  const user = useAuthStore((s) => s.user)
  const navigate = useNavigate()
  const [extensionItem, setExtensionItem] = useState<ReviewAssignment | null>(null)
  const [conflictItem, setConflictItem] = useState<ReviewAssignment | null>(null)

  const isAdminOrCoord = user?.roles?.some((r: string) => r === 'admin' || r === 'coordinator')

  const { data: gatedData } = useQuery<{ pending: GatedItem[] }>({    queryKey: ['gated-reviews'],
    queryFn: () => api.get('/admin/gated-reviews').then((r) => r.data),
    staleTime: 30_000,
  })
  const gatedPending = gatedData?.pending ?? []

  // Coordinator pending actions (extensions, conflicts, incoming submissions)
  const { data: coordData } = useQuery<{
    extensions: PendingExtension[]
    conflicts: ConflictDeclaration[]
    unassigned: IncomingSubmission[]
  }>({
    queryKey: ['coordinator-pending-actions'],
    queryFn: () => api.get('/admin/reviewer-pending-actions').then((r) => r.data),
    staleTime: 30_000,
    enabled: !!isAdminOrCoord,
  })
  const pendingExtensions = coordData?.extensions ?? []
  const conflictDeclarations = coordData?.conflicts ?? []
  const incomingSubmissions = coordData?.unassigned ?? []

  const { data, isLoading } = useQuery<{ data: ReviewAssignment[] }>({
    queryKey: ['my-reviews', 'assignments'],
    queryFn: () => api.get('/submissions/my-reviews', { params: { mode: 'assignments' } }).then((r) => r.data),
    staleTime: 30_000,
  })

  const items = data?.data ?? []

  const awaiting  = items.filter((i) => classifyItem(i) === 'awaiting')
  const completed = items.filter((i) => classifyItem(i) === 'completed')
  const others    = items.filter((i) => classifyItem(i) === 'others')

  const awaitingSorted = [
    ...awaiting.filter((i) => i.is_overdue),
    ...awaiting.filter((i) => i.is_due_soon && !i.is_overdue),
    ...awaiting.filter((i) => !i.is_overdue && !i.is_due_soon),
  ]

  return (
    <>
    <div>
      {/* Coordinator: pending extension requests */}
      {isAdminOrCoord && pendingExtensions.length > 0 && (
        <div className="mb-6">
          <CoordExtensionPanel items={pendingExtensions} />
        </div>
      )}

      {/* Coordinator: conflict of interest declarations */}
      {isAdminOrCoord && conflictDeclarations.length > 0 && (
        <div className="mb-6">
          <CoordConflictPanel items={conflictDeclarations} />
        </div>
      )}

      {/* Coordinator: incoming / unassigned submissions */}
      {isAdminOrCoord && incomingSubmissions.length > 0 && (
        <div className="mb-6">
          <CoordIncomingPanel items={incomingSubmissions} />
        </div>
      )}

      {/* Gated reviews section — shown if user has pending gatekeeper decisions */}
      {gatedPending.length > 0 && (
        <div className="mb-6 bg-white rounded-xl border-2 border-purple-200 shadow-sm overflow-hidden">
          <div className="flex items-center gap-2 px-5 py-3 border-b border-purple-100 bg-purple-50 text-purple-700">
            <Gavel size={15} />
            <span className="text-sm font-semibold">Gated Reviews — Awaiting Your Decision</span>
            <span className="ml-1 text-xs bg-white/60 px-2 py-0.5 rounded-full font-medium">{gatedPending.length}</span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[700px]">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50/80">
                  <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Title</th>
                  <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Type</th>
                  <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Program</th>
                  <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Stage Requiring Decision</th>
                  <th scope="col" className="text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-2.5">Stage Outcome</th>
                  <th scope="col" className="px-4 py-2.5 w-16" />
                </tr>
              </thead>
              <tbody>
                {gatedPending.map((item) => (
                  <tr
                    key={item.id}
                    className="border-b border-gray-50 hover:bg-purple-50/40 cursor-pointer group"
                    onClick={() => navigate(`/submissions/${item.id}`)}
                  >
                    <td className="px-4 py-3">
                      <p className="text-sm font-medium text-gray-900 truncate max-w-[200px]" title={item.title}>{item.title}</p>
                      {item.submitter_name && <p className="text-xs text-gray-400 mt-0.5">{item.submitter_name}</p>}
                    </td>
                    <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{item.submission_type ?? '—'}</td>
                    <td className="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{item.program ?? '—'}</td>
                    <td className="px-4 py-3 text-xs text-gray-700 font-medium">{item.pending_gatekeeper_stage_name ?? '—'}</td>
                    <td className="px-4 py-3">
                      {item.pending_gatekeeper_stage_outcome ? (
                        <span className={`inline-flex text-[10px] px-2 py-0.5 rounded-full font-medium ${
                          item.pending_gatekeeper_stage_outcome === 'FAILED'
                            ? 'bg-red-100 text-red-700'
                            : 'bg-amber-100 text-amber-700'
                        }`}>
                          {item.pending_gatekeeper_stage_outcome === 'FAILED' ? 'Rejected' : 'Revision Requested'}
                        </span>
                      ) : <span className="text-gray-300 text-xs">—</span>}
                    </td>
                    <td className="px-4 py-3">
                      <ChevronRight size={14} className="text-gray-300 group-hover:text-purple-500" />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Assignments</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          {isAdminOrCoord
            ? 'Manage peer review assignments, extensions, and decisions'
            : 'Submissions assigned to you for peer review'}
        </p>
      </div>

      {isLoading ? (
        <div className="space-y-5">
          {[1, 2].map((i) => (
            <div key={i} className="bg-white rounded-xl border border-gray-200 overflow-hidden animate-pulse">
              <div className="h-10 bg-gray-50 border-b border-gray-100" />
              {Array.from({ length: 3 }).map((_, j) => (
                <div key={j} className="flex items-center gap-4 px-4 py-3 border-b border-gray-50">
                  <div className="h-3 w-2/5 bg-gray-100 rounded" />
                  <div className="h-3 w-1/6 bg-gray-100 rounded" />
                  <div className="h-3 w-1/6 bg-gray-100 rounded" />
                </div>
              ))}
            </div>
          ))}
        </div>
      ) : items.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div className="flex flex-col items-center justify-center py-24 text-center">
            <ShieldCheck className="w-12 h-12 text-gray-200 mb-4" />
            <p className="text-base font-medium text-gray-500">No reviews assigned</p>
            <p className="text-sm text-gray-400 mt-1">
              Submissions assigned to you for review will appear here.
            </p>
          </div>
        </div>
      ) : (
        <div className="space-y-5">
          {awaitingSorted.length > 0 && (
            <SectionTable
              title="Waiting for My Review"
              count={awaitingSorted.length}
              icon={Hourglass}
              headerClass="bg-blue-50 text-blue-700"
              items={awaitingSorted}
              onExtension={setExtensionItem}
              onConflict={setConflictItem}
              navigate={navigate}
            />
          )}

          {completed.length > 0 && (
            <SectionTable
              title="Reviewed by Me"
              count={completed.length}
              icon={CheckCircle2}
              headerClass="bg-green-50 text-green-700"
              items={completed}
              onExtension={setExtensionItem}
              onConflict={setConflictItem}
              navigate={navigate}
            />
          )}

          {others.length > 0 && (
            <SectionTable
              title="Others (Withdrawn / Cancelled)"
              count={others.length}
              icon={Archive}
              headerClass="bg-gray-50 text-gray-600"
              items={others}
              onExtension={setExtensionItem}
              onConflict={setConflictItem}
              navigate={navigate}
            />
          )}
        </div>
      )}

      {extensionItem && (
        <ExtensionModal item={extensionItem} onClose={() => setExtensionItem(null)} />
      )}
      {conflictItem && (
        <ConflictModal item={conflictItem} onClose={() => setConflictItem(null)} />
      )}
    </div>
    </>  )
}
