import { useState, useRef, useEffect, useCallback } from 'react'
import DOMPurify from 'dompurify'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ChevronLeft, FileText, Download, Upload,
  CheckCircle2, XCircle, AlertTriangle, Loader2,
  Pencil, X, Check, StopCircle,
  Users, UserPlus, Mail, MailCheck, RefreshCw, Trash2,
  Search, UserCheck, Clock, MessageSquare, History,
  ThumbsUp, ThumbsDown, RotateCcw,
  Gavel, CircleAlert, Info, ListChecks,
  Calendar, EyeOff, ExternalLink, Eye, Printer, Plus, Send,
  ChevronUp, ChevronDown,
  CalendarDays, Ban,
} from 'lucide-react'
import { renderAsync } from 'docx-preview'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import { useToastHelpers } from '../lib/toast'
import type {
  Submission, SubmissionStatus, SubmissionAuthor, SubmissionReviewer,
  ActivityEvent, FeedbackItem, Appeal, ReviewProgressStage, SubmissionMeeting,
  DocumentAnnotation, SubmissionMessage, MeetingType, UserMeetingContext,
} from '../types/submissions'
import { STATUS_LABELS, STATUS_COLORS } from '../types/submissions'
import type { SubmissionTypeAdmin } from '../types/admin'

// ── Status Badge ──────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: SubmissionStatus }) {
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${STATUS_COLORS[status]}`}>
      {STATUS_LABELS[status]}
    </span>
  )
}

// ── Tab Navigation ────────────────────────────────────────────────────────────

type Tab = 'overview' | 'documents' | 'feedback' | 'activity' | 'communication'

const TABS: { id: Tab; label: string; icon: React.ElementType }[] = [
  { id: 'overview',       label: 'Overview',       icon: Info },
  { id: 'documents',      label: 'Documents',      icon: FileText },
  { id: 'feedback',       label: 'Feedback',       icon: MessageSquare },
  { id: 'activity',       label: 'Activity',       icon: Clock },
  { id: 'communication',  label: 'Communication',  icon: Send },
]

// ── Confirm Modal ─────────────────────────────────────────────────────────────

function ConfirmModal({
  title, message, confirmLabel, confirmClass, onConfirm, onCancel, loading,
}: {
  title: string
  message: string
  confirmLabel: string
  confirmClass?: string
  onConfirm: () => void
  onCancel: () => void
  loading?: boolean
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
        <h3 className="text-base font-semibold text-gray-900 mb-2">{title}</h3>
        <p className="text-sm text-gray-600 mb-6">{message}</p>
        <div className="flex gap-3 justify-end">
          <button
            onClick={onCancel}
            className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={loading}
            className={`px-4 py-2 rounded-lg text-sm text-white font-medium disabled:opacity-50 flex items-center gap-1.5 ${confirmClass ?? 'bg-red-600 hover:bg-red-700'}`}
          >
            {loading && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Appeal Modal ──────────────────────────────────────────────────────────────

function AppealModal({
  submissionId, onClose, onSubmitted,
}: {
  submissionId: string
  onClose: () => void
  onSubmitted: () => void
}) {
  const [grounds, setGrounds] = useState('')
  const [error, setError] = useState('')

  const appealMutation = useMutation({
    mutationFn: () => api.post(`/submissions/${submissionId}/appeal`, { grounds }),
    onSuccess: () => { onClose(); onSubmitted() },
    onError: (e: any) => setError(e?.response?.data?.message ?? 'Failed to submit appeal.'),
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <Gavel className="w-5 h-5 text-orange-500" />
            <h3 className="text-base font-semibold text-gray-900">Appeal Rejection</h3>
          </div>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
            <X className="w-4 h-4" />
          </button>
        </div>
        <p className="text-sm text-gray-600 mb-4">
          Provide a detailed explanation of the grounds for your appeal. This will be reviewed by the coordinator.
        </p>
        <textarea
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"
          rows={6}
          placeholder="Describe the grounds for your appeal (minimum 20 characters)…"
          value={grounds}
          onChange={e => setGrounds(e.target.value)}
          maxLength={5000}
        />
        <div className="flex items-center justify-between mt-2 mb-4">
          <span className="text-xs text-gray-400">{grounds.length}/5000 characters</span>
          {grounds.length > 0 && grounds.length < 20 && (
            <span className="text-xs text-red-500">At least 20 characters required</span>
          )}
        </div>
        {error && <p className="text-sm text-red-600 mb-4">{error}</p>}
        <div className="flex gap-3 justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            onClick={() => appealMutation.mutate()}
            disabled={appealMutation.isPending || grounds.length < 20}
            className="flex items-center gap-1.5 px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600 disabled:opacity-50"
          >
            {appealMutation.isPending && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
            Submit Appeal
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Review Progress Panel ────────────────────────────────────────────────────

const STAGE_STATUS_CONFIG: Record<string, { label: string; cls: string; numCls: string }> = {
  pending:          { label: 'Not started',        cls: 'bg-gray-100 text-gray-500',     numCls: 'bg-gray-200 text-gray-500' },
  needs_assignment: { label: 'Needs reviewers',     cls: 'bg-purple-100 text-purple-700', numCls: 'bg-purple-500 text-white' },
  assigned:         { label: 'Reviewers assigned',  cls: 'bg-blue-100 text-blue-700',     numCls: 'bg-blue-500 text-white' },
  in_progress:      { label: 'In progress',         cls: 'bg-yellow-100 text-yellow-700', numCls: 'bg-yellow-400 text-white' },
  completed:        { label: 'Completed',           cls: 'bg-green-100 text-green-700',   numCls: 'bg-green-500 text-white' },
}

const OUTCOME_CONFIG: Record<string, { label: string; cls: string }> = {
  approved: { label: 'Approved', cls: 'bg-green-100 text-green-700' },
  revision: { label: 'Revision', cls: 'bg-orange-100 text-orange-700' },
  rejected: { label: 'Rejected', cls: 'bg-red-100 text-red-600' },
}

const MEETING_STATUS_COLORS: Record<string, string> = {
  requested: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-green-100 text-green-700',
  cancelled: 'bg-gray-100 text-gray-500',
  completed: 'bg-blue-100 text-blue-700',
}

function MeetingItem({
  meeting, isAdmin, currentUserId, onCancel, onConfirm, isConfirming,
}: {
  meeting: SubmissionMeeting
  isAdmin: boolean
  currentUserId: string
  onCancel: () => void
  onConfirm: (data: { confirmed_at: string | null; meeting_link: string | null }) => void
  isConfirming: boolean
}) {
  const [showConfirm, setShowConfirm] = useState(false)
  const [confirmLink, setConfirmLink] = useState(meeting.meeting_link ?? '')
  const [confirmTime, setConfirmTime] = useState(
    meeting.confirmed_at ? new Date(meeting.confirmed_at).toISOString().slice(0, 16) : ''
  )
  const canCancel = (meeting.requested_by === currentUserId || isAdmin)
    && ['requested', 'confirmed'].includes(meeting.status)

  return (
    <div className="p-2.5 border border-gray-100 rounded-lg bg-white">
      <div className="flex items-start gap-2">
        <Calendar className="w-3.5 h-3.5 text-blue-400 mt-0.5 flex-shrink-0" />
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <p className="text-xs font-medium text-gray-800">{meeting.title}</p>
            <span className={`text-xs px-1.5 py-0.5 rounded-full font-medium ${MEETING_STATUS_COLORS[meeting.status] ?? 'bg-gray-100 text-gray-600'}`}>
              {meeting.status}
            </span>
          </div>
          {meeting.description && <p className="text-xs text-gray-500 mt-0.5 italic">{meeting.description}</p>}
          {meeting.proposed_at && (
            <p className="text-xs text-gray-400 mt-0.5">Proposed: {new Date(meeting.proposed_at).toLocaleString()}</p>
          )}
          {meeting.confirmed_at && (
            <p className="text-xs text-gray-500 mt-0.5">Confirmed: {new Date(meeting.confirmed_at).toLocaleString()}</p>
          )}
          {meeting.meeting_link && (
            <a href={meeting.meeting_link} target="_blank" rel="noopener noreferrer"
              className="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline mt-0.5">
              <ExternalLink className="w-3 h-3" /> Join Meeting
            </a>
          )}
          <p className="text-xs text-gray-400 mt-0.5">By {meeting.requester?.name ?? 'Unknown'}</p>
        </div>
        <div className="flex items-center gap-1 flex-shrink-0">
          {isAdmin && meeting.status === 'requested' && !showConfirm && (
            <button onClick={() => setShowConfirm(true)}
              className="text-xs px-2 py-0.5 bg-green-600 text-white rounded hover:bg-green-700">
              Confirm
            </button>
          )}
          {canCancel && !showConfirm && (
            <button onClick={onCancel} className="p-1 text-gray-400 hover:text-red-500" title="Cancel meeting">
              <X className="w-3 h-3" />
            </button>
          )}
        </div>
      </div>
      {showConfirm && isAdmin && (
        <div className="mt-2 p-2.5 bg-green-50 rounded-lg border border-green-100 space-y-1.5">
          <p className="text-xs font-semibold text-green-700">Confirm meeting details</p>
          <div>
            <label className="text-xs text-gray-500 mb-0.5 block">Date &amp; time</label>
            <input type="datetime-local"
              className="border border-gray-300 rounded px-2 py-1 text-xs w-full focus:outline-none focus:ring-1 focus:ring-green-400"
              value={confirmTime} onChange={e => setConfirmTime(e.target.value)} />
          </div>
          <div>
            <label className="text-xs text-gray-500 mb-0.5 block">Meeting link (optional)</label>
            <input className="border border-gray-300 rounded px-2 py-1 text-xs w-full focus:outline-none focus:ring-1 focus:ring-green-400"
              placeholder="https://zoom.us/j/..."
              value={confirmLink} onChange={e => setConfirmLink(e.target.value)} />
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => onConfirm({ confirmed_at: confirmTime || null, meeting_link: confirmLink || null })}
              disabled={isConfirming}
              className="flex items-center gap-1 px-2.5 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 disabled:opacity-50">
              {isConfirming ? <Loader2 className="w-3 h-3 animate-spin" /> : <Check className="w-3 h-3" />}
              Save
            </button>
            <button onClick={() => setShowConfirm(false)}
              className="px-2.5 py-1 border border-gray-200 text-xs rounded text-gray-600 hover:bg-gray-50">
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

// ── Similarity Check Panel ────────────────────────────────────────────────────

interface SimilarityMatch {
  id: string
  title: string
  reference_number: string
  submitter_name: string
  status: string
  score: number
}

function SimilarityCheckPanel({ submissionId }: { submissionId: string }) {
  const [enabled, setEnabled] = useState(false)
  const { data, isFetching, refetch } = useQuery<{ data: SimilarityMatch[]; total_compared: number }>({
    queryKey: ['similarity', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/similarity`).then((r) => r.data),
    enabled,
    staleTime: 300_000,
  })

  const scoreBadge = (score: number) => {
    if (score >= 70) return 'bg-red-100 text-red-700 font-semibold'
    if (score >= 40) return 'bg-amber-100 text-amber-700 font-semibold'
    return 'bg-yellow-100 text-yellow-700'
  }

  const scoreLabel = (score: number) => {
    if (score >= 70) return 'High'
    if (score >= 40) return 'Medium'
    return 'Low'
  }

  return (
    <div className="mt-4 border border-gray-200 rounded-xl overflow-hidden">
      <div className="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
        <div className="flex items-center gap-2">
          <span className="text-sm font-semibold text-gray-700">Similarity Check</span>
          {data && <span className="text-xs text-gray-400">({data.total_compared} submissions compared)</span>}
        </div>
        <button
          onClick={() => { setEnabled(true); setTimeout(() => refetch(), 0) }}
          disabled={isFetching}
          className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {isFetching && <Loader2 className="w-3 h-3 animate-spin" />}
          {isFetching ? 'Running…' : 'Run Check'}
        </button>
      </div>
      {data && (
        data.data.length === 0 ? (
          <div className="px-4 py-6 text-center text-sm text-gray-500">No similar submissions found (≥20% threshold).</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <tr>
                  <th className="text-left px-4 py-2">Reference</th>
                  <th className="text-left px-4 py-2">Title</th>
                  <th className="text-left px-4 py-2">Submitter</th>
                  <th className="text-left px-4 py-2">Status</th>
                  <th className="text-right px-4 py-2">Score</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data.data.map((m) => (
                  <tr key={m.id} className="hover:bg-gray-50">
                    <td className="px-4 py-2 text-xs text-gray-500 font-mono whitespace-nowrap">{m.reference_number}</td>
                    <td className="px-4 py-2 text-gray-800 max-w-xs truncate">{m.title}</td>
                    <td className="px-4 py-2 text-gray-600 whitespace-nowrap">{m.submitter_name}</td>
                    <td className="px-4 py-2">
                      <span className="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{m.status}</span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      <span className={`inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full ${scoreBadge(m.score)}`}>
                        {scoreLabel(m.score)} {m.score}%
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}
    </div>
  )
}

function ReviewProgressPanel({
  submissionId,
  isAdmin,
  onAssignClick,
}: {
  submissionId: string
  isAdmin: boolean
  onAssignClick?: () => void
}) {
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const [requestingStageId, setRequestingStageId]   = useState<string | null>(null)
  const [requestingGkMeeting, setRequestingGkMeeting] = useState(false)
  const [meetingForm, setMeetingForm] = useState({ title: '', description: '', proposed_at: '' })

  const { data, isLoading } = useQuery<{
    data: ReviewProgressStage[]
    is_blind: boolean
    is_gated_review: boolean
    allow_meetings: boolean
    user_meeting_context: UserMeetingContext | null
  }>({
    queryKey: ['review-progress', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/review-progress`).then(r => r.data),
  })

  const allowMeetings  = data?.allow_meetings ?? false
  const isGatedReview  = data?.is_gated_review ?? false
  const userMeetingCtx = data?.user_meeting_context ?? null

  const { data: meetingsData } = useQuery<{ data: SubmissionMeeting[] }>({
    queryKey: ['submission-meetings', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/meetings`).then(r => r.data),
    enabled: allowMeetings,
  })

  const requestMeetingMutation = useMutation({
    mutationFn: (payload: {
      stage_id?: string | null
      meeting_type?: MeetingType | null
      title: string
      description: string
      proposed_at: string | null
    }) => api.post(`/submissions/${submissionId}/meetings`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission-meetings', submissionId] })
      setRequestingStageId(null)
      setRequestingGkMeeting(false)
      setMeetingForm({ title: '', description: '', proposed_at: '' })
    },
  })

  const confirmMeetingMutation = useMutation({
    mutationFn: ({ meetingId, ...payload }: { meetingId: string; confirmed_at: string | null; meeting_link: string | null }) =>
      api.patch(`/submissions/${submissionId}/meetings/${meetingId}`, { status: 'confirmed', ...payload }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['submission-meetings', submissionId] }),
  })

  const cancelMeetingMutation = useMutation({
    mutationFn: (meetingId: string) =>
      api.delete(`/submissions/${submissionId}/meetings/${meetingId}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['submission-meetings', submissionId] }),
  })

  const stages  = data?.data ?? []
  const isBlind = data?.is_blind ?? false
  const meetings = meetingsData?.data ?? []
  const meetingsByStage = (stageId: string) => meetings.filter(m => m.stage_id === stageId)
  const hasNeedsAssignment = stages.some(s => s.status === 'needs_assignment')

  // ── Meeting permission helpers ─────────────────────────────────────────────
  // Returns meeting_type the current user is allowed to initiate at a given stage,
  // or null if they cannot initiate any meeting there.
  const getMeetingTypeForStage = (stageId: string): MeetingType | null => {
    if (!allowMeetings || isBlind) return null
    if (isAdmin) return null   // admins use the generic form (no type enforcement)
    if (!isGatedReview) return null  // non-gated: handled separately below
    if (!userMeetingCtx) return null
    const { is_gatekeeper, is_reviewer, reviewer_stage_id, gatekeeper_stage_id } = userMeetingCtx
    if (is_gatekeeper) {
      return stageId === gatekeeper_stage_id ? 'gatekeeper_student' : 'gatekeeper_reviewers'
    }
    if (is_reviewer) {
      return stageId === reviewer_stage_id ? 'reviewer_reviewer' : null
    }
    return null  // submitter has a separate section
  }

  const getMeetingButtonLabel = (stageId: string): string => {
    if (!isGatedReview || !userMeetingCtx || isAdmin) return 'Request Meeting'
    const { is_gatekeeper, gatekeeper_stage_id } = userMeetingCtx
    if (is_gatekeeper) {
      return stageId === gatekeeper_stage_id ? 'Schedule Meeting with Student' : 'Schedule Meeting with Reviewers'
    }
    return 'Request Peer Meeting'
  }

  // Gatekeeper-student meetings (linked to gatekeeper stage) are shown outside the stage list
  const gatekeeperStudentMeetings = meetings.filter(
    m => (m.meeting_type === 'gatekeeper_student' || m.meeting_type === 'student_gatekeeper')
  )

  // ── Meeting request form (reused for stage-level meetings) ─────────────────
  const renderMeetingForm = (
    onSubmit: (form: { title: string; description: string; proposed_at: string | null }) => void,
    onCancel: () => void,
    isPending: boolean,
  ) => (
    <div className="p-3 bg-blue-50 rounded-lg border border-blue-100 space-y-2">
      <input
        className="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="Meeting title *"
        value={meetingForm.title}
        onChange={e => setMeetingForm(f => ({ ...f, title: e.target.value }))}
      />
      <input
        className="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="Description (optional)"
        value={meetingForm.description}
        onChange={e => setMeetingForm(f => ({ ...f, description: e.target.value }))}
      />
      <div>
        <label className="text-xs text-gray-500 mb-0.5 block">Proposed date/time (optional)</label>
        <input type="datetime-local"
          className="border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
          value={meetingForm.proposed_at}
          onChange={e => setMeetingForm(f => ({ ...f, proposed_at: e.target.value }))} />
      </div>
      <div className="flex gap-2">
        <button
          onClick={() => onSubmit({
            title: meetingForm.title,
            description: meetingForm.description,
            proposed_at: meetingForm.proposed_at || null,
          })}
          disabled={isPending || !meetingForm.title.trim()}
          className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {isPending ? <Loader2 className="w-3 h-3 animate-spin" /> : <Calendar className="w-3 h-3" />}
          Submit Request
        </button>
        <button
          onClick={onCancel}
          className="px-3 py-1.5 border border-gray-200 text-xs rounded-lg text-gray-600 hover:bg-gray-50">
          Cancel
        </button>
      </div>
    </div>
  )

  if (isLoading) {
    return (
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
        <div className="flex justify-center py-4">
          <Loader2 className="w-5 h-5 animate-spin text-gray-300" />
        </div>
      </div>
    )
  }

  if (stages.length === 0) return null

  // Submitter in a gated review — show gatekeeper meeting section before the stage list
  const showSubmitterGatekeeperSection =
    allowMeetings && isGatedReview && (userMeetingCtx?.is_submitter ?? false) &&
    !!userMeetingCtx?.gatekeeper_stage_id

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
      <div className="flex items-center gap-2 mb-4">
        <ListChecks className="w-4 h-4 text-gray-500" />
        <h2 className="text-base font-semibold text-gray-900">Review Progress</h2>
        {isBlind && (
          <span className="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full flex items-center gap-1">
            <EyeOff className="w-3 h-3" /> Blind Review
          </span>
        )}
        <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
          {stages.length} stage{stages.length !== 1 ? 's' : ''}
        </span>
        {hasNeedsAssignment && onAssignClick && (
          <button
            onClick={onAssignClick}
            className="ml-auto flex items-center gap-1.5 px-3 py-1 bg-purple-600 text-white text-xs font-semibold rounded-lg hover:bg-purple-700"
          >
            <UserPlus className="w-3 h-3" />
            Assign Reviewers
          </button>
        )}
      </div>

      {/* ── Submitter ↔ Gatekeeper meeting section (gated review only) ── */}
      {showSubmitterGatekeeperSection && (
        <div className="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-4">
          <p className="text-xs font-semibold text-blue-800 flex items-center gap-1.5 mb-2">
            <Calendar className="w-3.5 h-3.5" />
            Meetings with Gatekeeper
          </p>
          {gatekeeperStudentMeetings.length > 0 && (
            <div className="space-y-2 mb-2">
              {gatekeeperStudentMeetings.map(m => (
                <MeetingItem
                  key={m.id}
                  meeting={m}
                  isAdmin={isAdmin}
                  currentUserId={user?.id ?? ''}
                  onCancel={() => cancelMeetingMutation.mutate(m.id)}
                  onConfirm={(d) => confirmMeetingMutation.mutate({ meetingId: m.id, ...d })}
                  isConfirming={confirmMeetingMutation.isPending}
                />
              ))}
            </div>
          )}
          {requestingGkMeeting
            ? renderMeetingForm(
                (form) => requestMeetingMutation.mutate({
                  meeting_type: 'student_gatekeeper',
                  stage_id: userMeetingCtx!.gatekeeper_stage_id,
                  ...form,
                }),
                () => { setRequestingGkMeeting(false); setMeetingForm({ title: '', description: '', proposed_at: '' }) },
                requestMeetingMutation.isPending,
              )
            : (
              <button
                onClick={() => setRequestingGkMeeting(true)}
                className="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700"
              >
                <Calendar className="w-3.5 h-3.5" />
                Request Meeting with Gatekeeper
              </button>
            )
          }
        </div>
      )}

      <ol className="space-y-3">
        {stages.map((stage, idx) => {
          const cfg = STAGE_STATUS_CONFIG[stage.status] ?? STAGE_STATUS_CONFIG.pending
          const outcomeCfg = stage.outcome ? OUTCOME_CONFIG[stage.outcome] : null
          const stageMeetings = allowMeetings ? meetingsByStage(stage.id) : []
          const isActiveStage = ['assigned', 'in_progress', 'needs_assignment'].includes(stage.status)
          const meetingType = getMeetingTypeForStage(stage.id)
          // Non-gated review: anyone can request
          const canRequestNonGated = allowMeetings && !isBlind && !isGatedReview && (isActiveStage || isAdmin)
          // Gated review + admin: can request on any stage
          const canRequestGatedAdmin = allowMeetings && !isBlind && isGatedReview && isAdmin
          // Show meeting section if user has a type for this stage, or admin, or existing meetings
          const showMeetingSection =
            allowMeetings && !isBlind &&
            (canRequestNonGated || canRequestGatedAdmin || meetingType !== null || (isAdmin && stageMeetings.length > 0) || stageMeetings.length > 0)

          return (
            <li key={stage.id} className="border border-gray-100 rounded-xl overflow-hidden">
              {/* Stage header */}
              <div className="flex items-center gap-3 px-4 py-2.5 bg-gray-50">
                <span className={`w-6 h-6 flex-shrink-0 rounded-full flex items-center justify-center text-xs font-bold ${cfg.numCls}`}>
                  {stage.status === 'completed' ? <Check className="w-3 h-3" /> : idx + 1}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-800">
                    {stage.name}
                    {stage.is_gatekeeper && (
                      <span className="ml-1.5 text-xs text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded-full">Gatekeeper</span>
                    )}
                  </p>
                  <div className="flex items-center gap-3 flex-wrap">
                    {stage.role_label && <span className="text-xs text-gray-400">{stage.role_label}</span>}
                    {stage.due_days ? (
                      <span className="flex items-center gap-1 text-xs text-gray-400">
                        <Clock className="w-3 h-3" /> {stage.due_days}d review window
                      </span>
                    ) : null}
                    {stage.stage_due_at ? (
                      <span className={`flex items-center gap-1 text-xs font-medium ${
                        new Date(stage.stage_due_at) < new Date()
                          ? 'text-red-500'
                          : new Date(stage.stage_due_at) <= new Date(Date.now() + 3 * 86400000)
                            ? 'text-amber-500'
                            : 'text-emerald-600'
                      }`}>
                        <Calendar className="w-3 h-3" />
                        Due {new Date(stage.stage_due_at).toLocaleDateString()}
                        {new Date(stage.stage_due_at) < new Date() && ' (overdue)'}
                      </span>
                    ) : null}
                  </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                  <span className="text-xs text-gray-400 tabular-nums">
                    {stage.reviewers_count > 0
                      ? `${stage.completed_count}/${stage.reviewers_count} reviewed`
                      : <span className="text-gray-300">0 assigned</span>}
                  </span>
                  <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${cfg.cls}`}>{cfg.label}</span>
                  {outcomeCfg && (
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${outcomeCfg.cls}`}>{outcomeCfg.label}</span>
                  )}
                </div>
              </div>

              {/* Reviewer names — shown when not blind review (or admin override) */}
              {stage.reviewers !== null && (
                <div className="px-4 py-2 border-t border-gray-100 bg-white">
                  {stage.reviewers.length === 0 ? (
                    <p className="text-xs text-gray-400 italic">No reviewers assigned yet.</p>
                  ) : (
                    <div className="flex items-center gap-1.5 flex-wrap">
                      <span className="text-xs text-gray-400 flex-shrink-0">Reviewers:</span>
                      {stage.reviewers.map(r => (
                        <span key={r.id}
                          className={`flex items-center gap-1 text-xs px-2 py-0.5 rounded-full ${REVIEWER_STATUS_STYLES[r.status] ?? 'bg-gray-100 text-gray-600'}`}>
                          {r.name ?? 'Anonymous'}
                          {r.due_at && <span className="opacity-60">· due {new Date(r.due_at).toLocaleDateString()}</span>}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Meetings section */}
              {showMeetingSection && (
                <div className="px-4 py-2.5 border-t border-gray-100 bg-white space-y-2">
                  {stageMeetings.length > 0 && (
                    <div className="space-y-2">
                      {stageMeetings.map(m => (
                        <MeetingItem
                          key={m.id}
                          meeting={m}
                          isAdmin={isAdmin}
                          currentUserId={user?.id ?? ''}
                          onCancel={() => cancelMeetingMutation.mutate(m.id)}
                          onConfirm={(d) => confirmMeetingMutation.mutate({ meetingId: m.id, ...d })}
                          isConfirming={confirmMeetingMutation.isPending}
                        />
                      ))}
                    </div>
                  )}
                  {requestingStageId === stage.id
                    ? renderMeetingForm(
                        (form) => requestMeetingMutation.mutate({
                          stage_id: stage.id,
                          meeting_type: meetingType ?? undefined,
                          ...form,
                        }),
                        () => { setRequestingStageId(null); setMeetingForm({ title: '', description: '', proposed_at: '' }) },
                        requestMeetingMutation.isPending,
                      )
                    : (
                      <button
                        onClick={() => setRequestingStageId(stage.id)}
                        className="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700"
                      >
                        <Calendar className="w-3.5 h-3.5" />
                        {getMeetingButtonLabel(stage.id)}
                      </button>
                    )
                  }
                </div>
              )}
            </li>
          )
        })}
      </ol>
    </div>
  )
}

// ── Authors Panel ─────────────────────────────────────────────────────────────

function AuthorsPanel({ submissionId, canEdit }: { submissionId: string; canEdit: boolean }) {
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', email: '', affiliation: '' })
  const [formError, setFormError] = useState('')

  const { data, isLoading } = useQuery<{ data: SubmissionAuthor[] }>({
    queryKey: ['submission-authors', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/authors`).then(r => r.data),
  })

  const authors = data?.data ?? []

  const addMutation = useMutation({
    mutationFn: (payload: typeof form) =>
      api.post(`/submissions/${submissionId}/authors`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission-authors', submissionId] })
      setForm({ name: '', email: '', affiliation: '' })
      setShowForm(false)
      setFormError('')
    },
    onError: (e: any) => setFormError(e?.response?.data?.message ?? 'Failed to add author.'),
  })

  const removeMutation = useMutation({
    mutationFn: (authorId: string) =>
      api.delete(`/submissions/${submissionId}/authors/${authorId}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['submission-authors', submissionId] }),
  })

  const resendMutation = useMutation({
    mutationFn: (authorId: string) =>
      api.post(`/submissions/${submissionId}/authors/${authorId}/resend-invite`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['submission-authors', submissionId] }),
  })

  const reorderMutation = useMutation({
    mutationFn: (orderedIds: string[]) =>
      api.patch(`/submissions/${submissionId}/authors/reorder`, { order: orderedIds }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['submission-authors', submissionId] }),
  })

  const moveAuthor = (index: number, direction: 'up' | 'down') => {
    const nonCorresponding = authors.filter(a => !a.is_corresponding)
    const corresponding = authors.filter(a => a.is_corresponding)
    const ncIdx = nonCorresponding.findIndex(a => a.id === authors[index].id)
    if (ncIdx === -1) return
    const swapIdx = direction === 'up' ? ncIdx - 1 : ncIdx + 1
    if (swapIdx < 0 || swapIdx >= nonCorresponding.length) return
    const reordered = [...nonCorresponding]
    ;[reordered[ncIdx], reordered[swapIdx]] = [reordered[swapIdx], reordered[ncIdx]]
    // Corresponding author always comes first in the final order
    const finalOrder = [...corresponding, ...reordered].map(a => a.id)
    reorderMutation.mutate(finalOrder)
  }

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Users className="w-4 h-4 text-gray-500" />
          <h2 className="text-base font-semibold text-gray-900">Authors</h2>
          <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{authors.length}</span>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => qc.invalidateQueries({ queryKey: ['submission-authors', submissionId] })} className="p-1.5 text-gray-400 hover:text-gray-600">
            <RefreshCw className="w-3.5 h-3.5" />
          </button>
          {canEdit && (
            <button
              onClick={() => setShowForm(v => !v)}
              className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50"
            >
              <UserPlus className="w-3.5 h-3.5" />
              Add Co-Author
            </button>
          )}
        </div>
      </div>

      {/* Add form */}
      {showForm && (
        <div className="mb-4 p-4 bg-blue-50 rounded-xl border border-blue-100 space-y-2">
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-xs font-medium text-gray-600 mb-1 block">Full name *</label>
              <input
                className="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Dr. Jane Smith"
                value={form.name}
                onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
              />
            </div>
            <div>
              <label className="text-xs font-medium text-gray-600 mb-1 block">Email *</label>
              <input
                type="email"
                className="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="jane@university.edu"
                value={form.email}
                onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
              />
            </div>
          </div>
          <div>
            <label className="text-xs font-medium text-gray-600 mb-1 block">Affiliation</label>
            <input
              className="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="City University of Hong Kong"
              value={form.affiliation}
              onChange={e => setForm(f => ({ ...f, affiliation: e.target.value }))}
            />
          </div>
          {formError && <p className="text-xs text-red-600">{formError}</p>}
          <div className="flex gap-2">
            <button
              onClick={() => addMutation.mutate(form)}
              disabled={addMutation.isPending || !form.name.trim() || !form.email.trim()}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {addMutation.isPending ? <Loader2 className="w-3 h-3 animate-spin" /> : <UserPlus className="w-3 h-3" />}
              Add Author
            </button>
            <button
              onClick={() => { setShowForm(false); setFormError('') }}
              className="px-3 py-1.5 border border-gray-200 text-xs rounded-lg text-gray-600 hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
          <p className="text-xs text-gray-400">
            If this person doesn't have an account, they'll receive an invitation email.
          </p>
        </div>
      )}

      {isLoading ? (
        <div className="flex justify-center py-6"><Loader2 className="w-5 h-5 animate-spin text-gray-300" /></div>
      ) : authors.length === 0 ? (
        <p className="text-sm text-gray-400 italic text-center py-4">No authors listed yet.</p>
      ) : (
        <ul className="space-y-2">
          {authors.map((a, idx) => {
            const nonCorr = authors.filter(x => !x.is_corresponding)
            const nonCorrIdx = nonCorr.findIndex(x => x.id === a.id)
            const canMoveUp   = canEdit && !a.is_corresponding && nonCorrIdx > 0
            const canMoveDown = canEdit && !a.is_corresponding && nonCorrIdx < nonCorr.length - 1
            return (
            <li key={a.id} className="flex items-center gap-3 p-3 border border-gray-100 rounded-xl group">
              {/* Reorder controls */}
              {canEdit && !a.is_corresponding && (
                <div className="flex flex-col gap-0.5">
                  <button
                    onClick={() => moveAuthor(idx, 'up')}
                    disabled={!canMoveUp || reorderMutation.isPending}
                    className={`p-0.5 rounded transition-colors ${canMoveUp ? 'text-gray-400 hover:text-gray-700' : 'text-gray-200 cursor-not-allowed'}`}
                    title="Move up"
                  >
                    <ChevronUp className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => moveAuthor(idx, 'down')}
                    disabled={!canMoveDown || reorderMutation.isPending}
                    className={`p-0.5 rounded transition-colors ${canMoveDown ? 'text-gray-400 hover:text-gray-700' : 'text-gray-200 cursor-not-allowed'}`}
                    title="Move down"
                  >
                    <ChevronDown className="w-3.5 h-3.5" />
                  </button>
                </div>
              )}
              <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-semibold text-blue-700 flex-shrink-0">
                {a.name[0]?.toUpperCase() ?? '?'}
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <p className="text-sm font-medium text-gray-900">{a.name}</p>
                  {a.is_corresponding && (
                    <span className="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">Corresponding</span>
                  )}
                  {a.has_account ? (
                    <span className="flex items-center gap-1 text-xs text-green-600">
                      <MailCheck className="w-3 h-3" /> Has account
                    </span>
                  ) : a.invite_pending ? (
                    <span className="flex items-center gap-1 text-xs text-amber-600">
                      <Mail className="w-3 h-3" /> Invite pending
                    </span>
                  ) : null}
                </div>
                <p className="text-xs text-gray-500">{a.email}</p>
                {a.affiliation && <p className="text-xs text-gray-400">{a.affiliation}</p>}
              </div>
              {canEdit && !a.is_corresponding && (
                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  {a.invite_pending && (
                    <button
                      onClick={() => resendMutation.mutate(a.id)}
                      disabled={resendMutation.isPending}
                      className="p-1.5 text-amber-500 hover:text-amber-700"
                      title="Resend invite"
                    >
                      <RefreshCw className="w-3.5 h-3.5" />
                    </button>
                  )}
                  <button
                    onClick={() => removeMutation.mutate(a.id)}
                    disabled={removeMutation.isPending}
                    className="p-1.5 text-gray-300 hover:text-red-500"
                    title="Remove author"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              )}
            </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}

// ── Reviewers Panel (admin/coordinator only) ──────────────────────────────────

const REVIEWER_STATUS_STYLES: Record<string, string> = {
  pending:   'bg-gray-100 text-gray-600',
  accepted:  'bg-blue-100 text-blue-700',
  declined:  'bg-red-100 text-red-600',
  completed: 'bg-green-100 text-green-700',
}

const DECISION_STYLES: Record<string, string> = {
  approve: 'bg-green-100 text-green-700',
  reject:  'bg-red-100 text-red-600',
  revise:  'bg-orange-100 text-orange-700',
}

function ReviewerSearchAdder({
  submissionId, stageId, existingUserIds, onAdded,
}: {
  submissionId: string
  stageId: string
  existingUserIds: string[]
  onAdded: () => void
}) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [showSuggestions, setShowSuggestions] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  const { data: searchData, isFetching } = useQuery<{ data: Array<{ id: string; name: string; email: string; org_role: string | null }> }>({
    queryKey: ['reviewer-search', query],
    queryFn: () =>
      api.get('/users', { params: { search: query, role: 'reviewer', per_page: 10, is_active: true } }).then(r => r.data),
    enabled: query.length >= 2,
    placeholderData: prev => prev,
  })

  const { data: poolData } = useQuery<{ data: Array<{ user_id: string; user: { id: string; name: string; email: string; org_role: string | null } }> }>({
    queryKey: ['pool-suggestions', submissionId, stageId],
    queryFn: () =>
      api.get(`/submissions/${submissionId}/reviewer-pool-suggestions`, { params: { stage_id: stageId } }).then(r => r.data),
    enabled: showSuggestions,
  })

  const addMutation = useMutation({
    mutationFn: (userId: string) =>
      api.post(`/submissions/${submissionId}/reviewers`, { stage_id: stageId, user_id: userId }),
    onSuccess: () => { onAdded(); setQuery(''); setOpen(false) },
  })

  const results = (searchData?.data ?? []).filter(u => !existingUserIds.includes(u.id))
  const suggestions = (poolData?.data ?? []).filter(p => !existingUserIds.includes(p.user_id))

  return (
    <div ref={ref} className="space-y-2">
      {/* Pool suggestions */}
      {suggestions.length > 0 && (
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-xs text-gray-400">From pool:</span>
          {suggestions.map(s => (
            <button
              key={s.user_id}
              onClick={() => addMutation.mutate(s.user_id)}
              disabled={addMutation.isPending}
              className="flex items-center gap-1 px-2 py-1 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 hover:bg-blue-100"
            >
              <UserPlus className="w-3 h-3" />
              {s.user.name}
            </button>
          ))}
        </div>
      )}

      {/* Search input */}
      <div className="relative">
        <div
          className="flex items-center gap-1.5 border border-dashed border-blue-300 rounded-lg px-2.5 py-1.5 bg-blue-50 cursor-text"
          onClick={() => { setOpen(true); setShowSuggestions(true) }}
        >
          <Search className="w-3.5 h-3.5 text-blue-400 flex-shrink-0" />
          <input
            className="flex-1 text-xs bg-transparent outline-none text-blue-700 placeholder:text-blue-400 min-w-0"
            placeholder="Search reviewers by name or email…"
            value={query}
            onChange={e => { setQuery(e.target.value); setOpen(true) }}
            onFocus={() => { setOpen(true); setShowSuggestions(true) }}
          />
          {isFetching && <Loader2 className="w-3 h-3 animate-spin text-blue-400" />}
        </div>
        {open && query.length >= 2 && (
          <div className="absolute top-full left-0 right-0 z-[9999] mt-1 bg-white rounded-xl shadow-lg border border-gray-200 max-h-48 overflow-y-auto">
            {results.length === 0 ? (
              <p className="text-xs text-gray-400 text-center py-3">No reviewers found</p>
            ) : (
              results.map(u => (
                <button
                  key={u.id}
                  onClick={() => addMutation.mutate(u.id)}
                  className="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-blue-50 text-left"
                >
                  <div className="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-semibold text-blue-700 flex-shrink-0">
                    {u.name[0]?.toUpperCase()}
                  </div>
                  <div className="min-w-0">
                    <p className="text-xs font-medium text-gray-900 truncate">{u.name}</p>
                    <p className="text-xs text-gray-400 truncate">{u.email}</p>
                  </div>
                </button>
              ))
            )}
          </div>
        )}
      </div>
    </div>
  )
}

function ReviewersPanel({ submissionId, submissionTypeId }: { submissionId: string; submissionTypeId: string }) {
  const qc = useQueryClient()
  const [isEditing, setIsEditing] = useState(false)

  const { data: reviewersData, isLoading } = useQuery<{ data: SubmissionReviewer[] }>({
    queryKey: ['submission-reviewers', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/reviewers`).then(r => r.data),
  })

  const { data: typeData } = useQuery<{ data: SubmissionTypeAdmin }>({
    queryKey: ['admin-type', submissionTypeId],
    queryFn: () => api.get(`/admin/submission-types/${submissionTypeId}`).then(r => r.data),
    enabled: !!submissionTypeId,
  })

  const removeMutation = useMutation({
    mutationFn: (reviewerId: string) =>
      api.delete(`/submissions/${submissionId}/reviewers/${reviewerId}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['submission-reviewers', submissionId] }),
  })

  const reviewers = reviewersData?.data ?? []
  const stages = typeData?.data?.workflow?.stages ?? []

  const reviewersByStage = (stageId: string) => reviewers.filter(r => r.stage_id === stageId)
  const existingUserIds = (stageId: string) => reviewersByStage(stageId).map(r => r.user_id)

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
      <div className="flex items-center gap-2 mb-4">
        <UserCheck className="w-4 h-4 text-gray-500" />
        <h2 className="text-base font-semibold text-gray-900">Reviewer Assignments</h2>
        <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{reviewers.length} total</span>
        <div className="ml-auto">
          {isEditing ? (
            <button
              onClick={() => setIsEditing(false)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700"
            >
              <Check className="w-3.5 h-3.5" /> Done
            </button>
          ) : (
            <button
              onClick={() => setIsEditing(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-50"
            >
              <Pencil className="w-3.5 h-3.5" /> Edit
            </button>
          )}
        </div>
      </div>

      {isEditing && (
        <div className="mb-3 p-2.5 bg-blue-50 border border-blue-100 rounded-lg">
          <p className="text-xs text-blue-700">Edit mode — remove existing reviewers or add new ones below. Click <strong>Done</strong> when finished.</p>
        </div>
      )}

      {isLoading ? (
        <div className="flex justify-center py-6"><Loader2 className="w-5 h-5 animate-spin text-gray-300" /></div>
      ) : stages.length === 0 ? (
        <p className="text-sm text-gray-400 italic">No workflow stages configured for this submission type.</p>
      ) : (
        <div className="space-y-4">
          {stages.map((stage, idx) => {
            const stageReviewers = reviewersByStage(stage.id)
            return (
              <div key={stage.id} className="border border-gray-100 rounded-xl">
                <div className="flex items-center gap-3 px-4 py-2.5 bg-gray-50 rounded-t-xl border-b border-gray-100">
                  <span className="w-5 h-5 flex items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold flex-shrink-0">
                    {idx + 1}
                  </span>
                  <p className="text-sm font-semibold text-gray-800">{stage.name}</p>
                  <span className="text-xs text-gray-400 font-mono">{stage.stage_role_label}</span>
                  <span className="ml-auto text-xs text-gray-400">{stageReviewers.length} assigned</span>
                </div>
                <div className="px-4 py-3 space-y-3">
                  {stageReviewers.length === 0 ? (
                    <p className="text-xs text-gray-400 italic">No reviewers assigned.</p>
                  ) : (
                    <ul className="space-y-2">
                      {stageReviewers.map(r => (
                        <li key={r.id} className="flex items-center gap-2.5">
                          <div className="w-7 h-7 rounded-full bg-green-100 flex items-center justify-center text-xs font-semibold text-green-700 flex-shrink-0">
                            {r.user?.name?.[0]?.toUpperCase() ?? '?'}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                              <p className="text-xs font-medium text-gray-900">{r.user?.name}</p>
                              {r.decision && (
                                <span className={`text-xs px-1.5 py-0.5 rounded ${DECISION_STYLES[r.decision]}`}>
                                  {r.decision}
                                </span>
                              )}
                              {r.due_at && (
                                <span className="text-xs text-gray-400">Due {new Date(r.due_at).toLocaleDateString()}</span>
                              )}
                            </div>
                            <p className="text-xs text-gray-400">{r.user?.email}</p>
                            {r.comments && (
                              <p className="text-xs text-gray-500 mt-0.5 italic">"{r.comments}"</p>
                            )}
                          </div>
                          {isEditing && (
                            <button
                              onClick={() => removeMutation.mutate(r.id)}
                              className="p-1 text-gray-400 hover:text-red-500 flex-shrink-0"
                              title="Remove reviewer"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </li>
                      ))}
                    </ul>
                  )}
                  {isEditing && (
                    <ReviewerSearchAdder
                      submissionId={submissionId}
                      stageId={stage.id}
                      existingUserIds={existingUserIds(stage.id)}
                      onAdded={() => qc.invalidateQueries({ queryKey: ['submission-reviewers', submissionId] })}
                    />
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

// ── Inline Document Viewer ────────────────────────────────────────────────────

type ViewingDoc = { submissionId: string; versionNumber: number; filename: string; fileType: 'pdf' | 'docx' }

type PendingAnnotation = { quote: string; positionHint: string; x: number; y: number }

// ── DocViewer helpers ─────────────────────────────────────────────────────────

/** Build a flat text map from all text nodes inside a container */
function buildTextMap(container: HTMLElement) {
  const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT)
  const nodes: { node: Text; start: number }[] = []
  let fullText = ''
  let n: Node | null
  while ((n = walker.nextNode())) {
    nodes.push({ node: n as Text, start: fullText.length })
    fullText += (n as Text).textContent ?? ''
  }
  return { nodes, fullText }
}

/** Collapse all whitespace variants for fuzzy matching */
function normalizeWS(s: string) { return s.replace(/[\s\u00a0]+/g, ' ').trim() }

function InlineDocViewer({ doc, onClose }: { doc: ViewingDoc; onClose: () => void }) {
  const containerRef = useRef<HTMLDivElement>(null)
  const downloadBtnRef = useRef<HTMLDivElement>(null)
  const skipSelectionRef = useRef(false)

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [blobUrl, setBlobUrl] = useState<string | null>(null)

  // Annotation state
  const [annotations, setAnnotations] = useState<DocumentAnnotation[]>([])
  const [showPanel, setShowPanel] = useState(false)
  const [pending, setPending] = useState<PendingAnnotation | null>(null)
  const [newComment, setNewComment] = useState('')
  const [saving, setSaving] = useState(false)
  const [highlightedId, setHighlightedId] = useState<string | null>(null)
  // For PDF — manual "add note" form
  const [pdfNoteOpen, setPdfNoteOpen] = useState(false)
  const [pdfQuote, setPdfQuote] = useState('')
  const [pdfComment, setPdfComment] = useState('')
  // Download
  const [showDownloadMenu, setShowDownloadMenu] = useState(false)

  const { user } = useAuthStore()

  const apiPath = `/submissions/${doc.submissionId}/files/${doc.versionNumber}/${encodeURIComponent(doc.filename)}`

  // ── Download handlers ───────────────────────────────────────────────────────

  const downloadPlain = () => {
    api.get(apiPath, { responseType: 'blob' }).then(res => {
      const url = URL.createObjectURL(res.data)
      const a = document.createElement('a')
      a.href = url; a.download = doc.filename; a.click()
      URL.revokeObjectURL(url)
    })
    setShowDownloadMenu(false)
  }

  const downloadWithAnnotations = () => {
    const container = containerRef.current
    if (!container) return
    const clone = container.cloneNode(true) as HTMLElement

    // Remove any temp highlights from the clone
    clone.querySelectorAll('.ann-active-mark').forEach((el: Element) => {
      const p = el.parentNode as Node
      while (el.firstChild) p.insertBefore(el.firstChild, el)
      p.removeChild(el)
    })

    // Inject [N] superscripts at each annotation's quote position
    annotations.forEach((ann, idx) => {
      if (!ann.quote || ann.quote === '(no excerpt)') return
      const { nodes, fullText } = buildTextMap(clone)

      // Build normText + mapping
      const normToRaw: number[] = []
      let normText = ''
      for (let ri = 0; ri < fullText.length; ri++) {
        const ch = fullText[ri]
        if (/[\s\u00a0]/.test(ch)) {
          if (!normText.length || normText[normText.length - 1] !== ' ') { normToRaw.push(ri); normText += ' ' }
        } else { normToRaw.push(ri); normText += ch }
      }
      const normQuote = normalizeWS(ann.quote).slice(0, 80)
      const nIdx = normText.indexOf(normQuote)
      if (nIdx === -1) return
      const rawIdx = normToRaw[nIdx] ?? -1
      if (rawIdx === -1) return

      const match = nodes.find(({ node, start }) => {
        const end = start + (node.textContent?.length ?? 0)
        return rawIdx >= start && rawIdx < end
      })
      if (!match) return

      const range = document.createRange()
      range.setStart(match.node, rawIdx - match.start)
      range.setEnd(match.node, rawIdx - match.start)
      const sup = document.createElement('sup')
      sup.style.cssText = 'color:#2563eb;font-size:0.7em;font-weight:700;margin-left:1px;'
      sup.textContent = `[${idx + 1}]`
      range.insertNode(sup)
    })

    const styles = Array.from(document.querySelectorAll('style')).map(s => s.outerHTML).join('\n')
    const annHtml = annotations.length === 0
      ? '<p style="color:#9ca3af;font-size:0.875rem;">No annotations.</p>'
      : annotations.map((ann, i) => `
        <div style="margin-bottom:14px;padding:10px 12px;background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;">
          <div style="font-weight:600;color:#1e40af;margin-bottom:4px;font-size:0.8rem;">[${i + 1}] ${ann.annotator.name}</div>
          ${ann.quote && ann.quote !== '(no excerpt)'
            ? `<blockquote style="color:#6b7280;font-style:italic;margin:4px 0 8px;padding-left:8px;border-left:2px solid #d1d5db;font-size:0.78rem;">"${ann.quote.slice(0, 300)}"</blockquote>`
            : ''}
          <div style="color:#374151;font-size:0.82rem;">${ann.comment}</div>
          <div style="color:#9ca3af;font-size:0.72rem;margin-top:4px;">${new Date(ann.created_at).toLocaleDateString()}</div>
        </div>`).join('')

    const html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>${doc.filename} — Annotated</title>${styles}
<style>*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;display:flex;min-height:100vh}
#doc-main{flex:1;min-width:0;padding:32px 48px;background:#fff}
#ann-sidebar{width:300px;flex-shrink:0;border-left:1px solid #e5e7eb;padding:20px 14px;background:#f9fafb;position:sticky;top:0;height:100vh;overflow-y:auto}
#ann-sidebar h2{font-size:.9rem;font-weight:600;color:#1f2937;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #e5e7eb}
@media print{#ann-sidebar{display:none}}</style>
</head><body>
<div id="doc-main">${clone.innerHTML}</div>
<div id="ann-sidebar"><h2>Annotations (${annotations.length})</h2>${annHtml}</div>
</body></html>`

    const blob = new Blob([html], { type: 'text/html' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = doc.filename.replace(/\.docx$/i, '') + '_annotated.html'; a.click()
    URL.revokeObjectURL(url)
    setShowDownloadMenu(false)
  }

  const downloadPdfReport = () => {
    const rows = annotations.map((ann, i) => `
      <tr>
        <td style="padding:8px 12px;font-weight:600;color:#1e40af;width:32px;vertical-align:top;">${i + 1}</td>
        <td style="padding:8px 12px;color:#6b7280;font-style:italic;vertical-align:top;">${ann.quote && ann.quote !== '(no excerpt)' ? `"${ann.quote}"` : '—'}</td>
        <td style="padding:8px 12px;color:#374151;vertical-align:top;">${ann.comment}</td>
        <td style="padding:8px 12px;color:#9ca3af;white-space:nowrap;vertical-align:top;">${ann.annotator.name}</td>
        <td style="padding:8px 12px;color:#9ca3af;white-space:nowrap;vertical-align:top;">${new Date(ann.created_at).toLocaleDateString()}</td>
      </tr>`).join('')
    const html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>${doc.filename} — Annotations</title>
<style>body{font-family:Arial,sans-serif;padding:32px 48px;max-width:960px;margin:0 auto}
h1{font-size:1.2rem;color:#1f2937;margin-bottom:4px}p.sub{color:#6b7280;font-size:.85rem;margin:0 0 24px}
table{width:100%;border-collapse:collapse;font-size:.87rem}
thead th{background:#f3f4f6;padding:8px 12px;text-align:left;font-weight:600;color:#374151;border-bottom:2px solid #e5e7eb}
tbody tr{border-bottom:1px solid #e5e7eb}tbody tr:hover{background:#f9fafb}</style></head>
<body><h1>${doc.filename} — Annotation Report</h1>
<p class="sub">Version ${doc.versionNumber} &bull; ${annotations.length} annotation${annotations.length !== 1 ? 's' : ''}</p>
<table><thead><tr><th>#</th><th>Referenced Text</th><th>Comment</th><th>By</th><th>Date</th></tr></thead>
<tbody>${rows}</tbody></table></body></html>`
    const blob = new Blob([html], { type: 'text/html' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = doc.filename.replace(/\.pdf$/i, '') + '_annotations.html'; a.click()
    URL.revokeObjectURL(url)
    setShowDownloadMenu(false)
  }

  // ── Annotation highlight ────────────────────────────────────────────────────

  const clearHighlight = useCallback(() => {
    containerRef.current?.querySelectorAll('.ann-active-mark').forEach((mark: Element) => {
      const p = mark.parentNode
      if (!p) return
      if (mark.tagName === 'MARK') {
        while (mark.firstChild) p.insertBefore(mark.firstChild, mark)
        p.removeChild(mark)
        ;(p as Element).normalize?.()
      } else {
        mark.classList.remove('ann-active-mark')
      }
    })
  }, [])

  /**
   * Find the annotation's quoted text in the DOCX preview, wrap it in a
   * <mark class="ann-active-mark"> and scroll into view.
   * Three-tier search: exact → whitespace-normalised → first-40-chars fallback.
   */
  const jumpToAnnotation = useCallback((ann: DocumentAnnotation) => {
    setHighlightedId(ann.id)
    setShowPanel(true)
    if (doc.fileType !== 'docx' || !containerRef.current) return
    if (!ann.quote || ann.quote === '(no excerpt)') return

    clearHighlight()
    const container = containerRef.current
    const { nodes, fullText } = buildTextMap(container)

    // ── Search ──────────────────────────────────────────────────────────────
    let rawIdx = -1
    let matchLen = 0

    // 1. Exact
    rawIdx = fullText.indexOf(ann.quote)
    if (rawIdx !== -1) matchLen = ann.quote.length

    // 2. Whitespace-normalised (handles cross-paragraph selections)
    if (rawIdx === -1) {
      const normToRaw: number[] = []
      let normText = ''
      for (let ri = 0; ri < fullText.length; ri++) {
        const ch = fullText[ri]
        if (/[\s\u00a0]/.test(ch)) {
          if (!normText.length || normText[normText.length - 1] !== ' ') { normToRaw.push(ri); normText += ' ' }
        } else { normToRaw.push(ri); normText += ch }
      }
      const normQuote = normalizeWS(ann.quote).slice(0, 120)
      const nIdx = normText.indexOf(normQuote)
      if (nIdx !== -1) {
        rawIdx = normToRaw[nIdx] ?? -1
        const endNIdx = Math.min(nIdx + normQuote.length, normToRaw.length - 1)
        matchLen = (normToRaw[endNIdx] ?? rawIdx) - rawIdx
      }
    }

    // 3. First 40 chars
    if (rawIdx === -1) {
      const short = ann.quote.slice(0, 40).trim()
      rawIdx = fullText.indexOf(short)
      if (rawIdx !== -1) matchLen = short.length
    }

    if (rawIdx === -1 || matchLen === 0) return

    // ── Map to text nodes and create Range ─────────────────────────────────
    const endIdx = rawIdx + matchLen
    let startNode: Text | null = null, startOffset = 0
    let endNode: Text | null = null, endOffset = 0

    for (const { node, start } of nodes) {
      const len = node.textContent?.length ?? 0
      const end = start + len
      if (!startNode && rawIdx >= start && rawIdx < end) { startNode = node; startOffset = rawIdx - start }
      if (startNode && !endNode && endIdx > start && endIdx <= end) { endNode = node; endOffset = endIdx - start; break }
    }
    if (!startNode) return
    if (!endNode) { endNode = startNode; endOffset = startNode.textContent?.length ?? 0 }

    const range = document.createRange()
    range.setStart(startNode, startOffset)
    range.setEnd(endNode, endOffset)

    const mark = document.createElement('mark')
    mark.className = 'ann-active-mark'

    skipSelectionRef.current = true
    try {
      if (startNode.parentElement === endNode.parentElement) {
        range.surroundContents(mark)
      } else {
        mark.appendChild(range.extractContents())
        range.insertNode(mark)
      }
      mark.scrollIntoView({ behavior: 'smooth', block: 'center' })
    } catch {
      // Fallback: highlight containing block
      const block = startNode.parentElement?.closest('p,div,li,td,h1,h2,h3,h4') ?? startNode.parentElement
      if (block) { block.classList.add('ann-active-mark'); block.scrollIntoView({ behavior: 'smooth', block: 'center' }) }
    } finally {
      setTimeout(() => { skipSelectionRef.current = false }, 200)
    }
  }, [doc.fileType, clearHighlight])

  // Load document file
  useEffect(() => {
    let objectUrl: string | null = null
    setLoading(true); setError(null); setBlobUrl(null)

    api.get(apiPath, { responseType: 'arraybuffer' })
      .then(res => {
        const buf = res.data as ArrayBuffer
        if (doc.fileType === 'pdf') {
          const blob = new Blob([buf], { type: 'application/pdf' })
          objectUrl = URL.createObjectURL(blob)
          setBlobUrl(objectUrl)
          setLoading(false)
        } else {
          const container = containerRef.current
          if (!container) { setLoading(false); return }
          renderAsync(buf, container, undefined, {
            className: 'docx-viewer',
            inWrapper: false,
            ignoreLastRenderedPageBreak: true,
          }).then(() => setLoading(false)).catch(() => {
            setError('Could not render document. Please use Download instead.')
            setLoading(false)
          })
        }
      })
      .catch(() => { setError('Failed to load document.'); setLoading(false) })

    return () => { if (objectUrl) URL.revokeObjectURL(objectUrl) }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiPath, doc.fileType])

  // Load annotations
  useEffect(() => {
    api.get(`/submissions/${doc.submissionId}/annotations`, {
      params: { version: doc.versionNumber, filename: doc.filename },
    }).then(res => setAnnotations(res.data.data ?? [])).catch(() => {})
  }, [doc.submissionId, doc.versionNumber, doc.filename])

  // Keyboard shortcuts
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        if (pending) { setPending(null); return }
        if (pdfNoteOpen) { setPdfNoteOpen(false); return }
        if (showDownloadMenu) { setShowDownloadMenu(false); return }
        if (highlightedId) { clearHighlight(); setHighlightedId(null); return }
        onClose()
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [onClose, pending, pdfNoteOpen, showDownloadMenu, highlightedId, clearHighlight])

  // DOCX text-selection handler
  const handleMouseUp = useCallback(() => {
    if (doc.fileType !== 'docx') return
    if (skipSelectionRef.current) return
    const sel = window.getSelection()
    if (!sel || sel.isCollapsed) return
    const text = sel.toString().trim()
    if (text.length < 3) return
    const range = sel.getRangeAt(0)
    const container = containerRef.current
    if (!container?.contains(range.commonAncestorContainer)) return

    const rect = range.getBoundingClientRect()
    let hint = ''
    let el: Element | null = (range.startContainer instanceof Element ? range.startContainer : range.startContainer.parentElement)
    let count = 0
    while (el && el !== container) {
      let sib = el.previousElementSibling
      while (sib) { count++; sib = sib.previousElementSibling }
      el = el.parentElement
    }
    if (count > 0) hint = `paragraph ${count + 1}`

    setPending({
      quote: text.slice(0, 500),
      positionHint: hint,
      x: Math.min(Math.max(rect.left, 8), window.innerWidth - 328),
      y: Math.min(rect.bottom + 6, window.innerHeight - 200),
    })
    setNewComment('')
    setHighlightedId(null)
  }, [doc.fileType])

  const saveAnnotation = async (quote: string, comment: string, positionHint: string) => {
    if (!comment.trim()) return
    setSaving(true)
    try {
      const res = await api.post(`/submissions/${doc.submissionId}/annotations`, {
        version_number: doc.versionNumber,
        filename: doc.filename,
        quote,
        comment: comment.trim(),
        position_hint: positionHint || null,
      })
      setAnnotations(prev => [...prev, res.data])
      setShowPanel(true)
      setPending(null); setPdfNoteOpen(false); setPdfQuote(''); setPdfComment('')
    } finally {
      setSaving(false)
    }
  }

  const deleteAnnotation = async (id: string) => {
    await api.delete(`/submissions/${doc.submissionId}/annotations/${id}`)
    setAnnotations(prev => prev.filter(a => a.id !== id))
    if (highlightedId === id) { clearHighlight(); setHighlightedId(null) }
  }

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col bg-gray-900/80 backdrop-blur-sm"
      onClick={(e) => {
        if (showDownloadMenu && downloadBtnRef.current && !downloadBtnRef.current.contains(e.target as Node)) {
          setShowDownloadMenu(false)
        }
      }}
    >
      {/* Toolbar */}
      <div className="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 flex-shrink-0">
        <FileText className="w-4 h-4 text-gray-500 flex-shrink-0" />
        <p className="text-sm font-medium text-gray-800 flex-1 truncate">{doc.filename}</p>
        {doc.fileType === 'pdf' && !loading && !error && (
          <button
            onClick={() => { setPdfNoteOpen(true); setShowPanel(true) }}
            className="flex items-center gap-1.5 px-3 py-1.5 border border-blue-200 text-blue-700 bg-blue-50 rounded-lg text-xs hover:bg-blue-100"
          >
            <Plus className="w-3.5 h-3.5" /> Add Note
          </button>
        )}
        <button
          onClick={() => setShowPanel(v => !v)}
          className={`relative flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs transition-colors ${
            showPanel ? 'border-blue-300 text-blue-700 bg-blue-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'
          }`}
        >
          <MessageSquare className="w-3.5 h-3.5" />
          Notes
          {annotations.length > 0 && (
            <span className="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-600 text-white text-[10px] font-bold">
              {annotations.length}
            </span>
          )}
        </button>

        {/* Download button with dropdown */}
        <div ref={downloadBtnRef} className="relative">
          <button
            onClick={() => annotations.length > 0 ? setShowDownloadMenu(v => !v) : downloadPlain()}
            className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50"
          >
            <Download className="w-3.5 h-3.5" /> Download
            {annotations.length > 0 && <ChevronLeft className="w-3 h-3 rotate-[-90deg] ml-0.5 text-gray-400" />}
          </button>
          {showDownloadMenu && (
            <div className="absolute right-0 top-full mt-1 w-58 bg-white border border-gray-200 rounded-xl shadow-lg z-[70] overflow-hidden" style={{ minWidth: '220px' }}>
              <div className="px-3 py-2 border-b border-gray-100 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                Download Options
              </div>
              <button
                onClick={downloadPlain}
                className="w-full text-left flex items-start gap-2.5 px-4 py-3 hover:bg-gray-50 transition-colors"
              >
                <Download className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-sm font-medium text-gray-800">Plain File</p>
                  <p className="text-xs text-gray-400">Original {doc.fileType.toUpperCase()} without annotations</p>
                </div>
              </button>
              <button
                onClick={doc.fileType === 'docx' ? downloadWithAnnotations : downloadPdfReport}
                className="w-full text-left flex items-start gap-2.5 px-4 py-3 hover:bg-blue-50 transition-colors border-t border-gray-100"
              >
                <MessageSquare className="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-sm font-medium text-blue-700">With Annotations</p>
                  <p className="text-xs text-gray-400">
                    {doc.fileType === 'docx' ? 'HTML with inline markers + notes sidebar' : 'Annotation report as HTML'}
                  </p>
                </div>
              </button>
            </div>
          )}
        </div>

        <button
          onClick={() => { clearHighlight(); onClose() }}
          className="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"
          title="Close viewer (Esc)"
        >
          <X className="w-4 h-4" />
        </button>
      </div>

      {/* Body */}
      <div className="flex-1 flex overflow-hidden">
        {/* Document area */}
        <div className="flex-1 overflow-auto bg-gray-100 flex flex-col" onMouseUp={handleMouseUp}>
          {loading && (
            <div className="flex items-center justify-center flex-1">
              <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
            </div>
          )}
          {error && (
            <div className="flex items-center justify-center flex-1">
              <div className="text-center">
                <AlertTriangle className="w-8 h-8 text-red-400 mx-auto mb-2" />
                <p className="text-sm text-gray-600">{error}</p>
                <button onClick={downloadPlain} className="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                  <Download className="w-4 h-4" /> Download Instead
                </button>
              </div>
            </div>
          )}
          {doc.fileType === 'pdf' && blobUrl && (
            <iframe src={blobUrl} title={doc.filename} className="flex-1 w-full" style={{ minHeight: 'calc(100vh - 57px)', border: 'none' }} />
          )}
          {doc.fileType === 'docx' && (
            <div
              ref={containerRef}
              className="flex-1 bg-white mx-auto my-4 shadow-sm rounded select-text"
              style={{
                maxWidth: showPanel ? '760px' : '900px',
                width: '100%',
                padding: '2rem 3rem',
                overflowY: 'auto',
                display: loading ? 'none' : 'block',
              }}
            />
          )}
          {doc.fileType === 'docx' && !loading && !error && (
            <p className="text-center text-xs text-gray-400 pb-3">
              Select any text in the document and release to add an inline annotation.
            </p>
          )}
        </div>

        {/* Annotations panel */}
        {showPanel && (
          <div className="w-80 flex-shrink-0 flex flex-col bg-white border-l border-gray-200 overflow-hidden">
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 flex-shrink-0">
              <h3 className="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <MessageSquare className="w-4 h-4 text-blue-600" />
                Annotations
                <span className="text-xs text-gray-400 font-normal">({annotations.length})</span>
              </h3>
              {highlightedId && doc.fileType === 'docx' && (
                <button
                  onClick={() => { clearHighlight(); setHighlightedId(null) }}
                  className="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1"
                >
                  <X className="w-3 h-3" /> Clear highlight
                </button>
              )}
            </div>

            {/* PDF add-note form */}
            {pdfNoteOpen && (
              <div className="p-4 border-b border-blue-100 bg-blue-50 flex-shrink-0">
                <p className="text-xs font-medium text-blue-700 mb-2">New annotation</p>
                <textarea value={pdfQuote} onChange={e => setPdfQuote(e.target.value)}
                  placeholder="Paste or type the text you are referencing…" rows={2}
                  className="w-full text-xs border border-blue-200 rounded-lg px-3 py-2 mb-2 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"
                />
                <textarea value={pdfComment} onChange={e => setPdfComment(e.target.value)}
                  placeholder="Your comment or recommendation…" rows={3}
                  className="w-full text-xs border border-blue-200 rounded-lg px-3 py-2 mb-2 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"
                />
                <div className="flex gap-2">
                  <button onClick={() => saveAnnotation(pdfQuote.trim() || '(no excerpt)', pdfComment, '')}
                    disabled={saving || !pdfComment.trim()}
                    className="flex-1 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    {saving ? 'Saving…' : 'Save'}
                  </button>
                  <button onClick={() => setPdfNoteOpen(false)} className="flex-1 py-1.5 border border-gray-200 text-gray-600 text-xs rounded-lg hover:bg-gray-50">
                    Cancel
                  </button>
                </div>
              </div>
            )}

            {/* Annotation list */}
            <div className="flex-1 overflow-y-auto divide-y divide-gray-100">
              {annotations.length === 0 && !pdfNoteOpen && (
                <div className="flex flex-col items-center justify-center py-10 px-4 text-center">
                  <MessageSquare className="w-6 h-6 text-gray-300 mb-2" />
                  <p className="text-xs text-gray-400">
                    {doc.fileType === 'docx' ? 'Select text in the document to add an annotation.' : 'Click "Add Note" to annotate a section of this PDF.'}
                  </p>
                </div>
              )}
              {annotations.map((ann, i) => {
                const isActive = highlightedId === ann.id
                const hasQuote = !!ann.quote && ann.quote !== '(no excerpt)'
                const canJump = doc.fileType === 'docx' && hasQuote
                return (
                  <div
                    key={ann.id}
                    onClick={() => canJump ? jumpToAnnotation(ann) : setHighlightedId(ann.id)}
                    title={canJump ? 'Click to locate this text in the document' : undefined}
                    className={`p-3 group border-l-2 transition-colors ${
                      isActive
                        ? 'bg-amber-50 border-amber-400 cursor-default'
                        : canJump
                          ? 'border-transparent hover:bg-gray-50 cursor-pointer'
                          : 'border-transparent'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2 mb-1">
                      <div className="flex items-center gap-1.5 min-w-0">
                        <span className={`inline-flex items-center justify-center w-4 h-4 rounded-full text-[9px] font-bold flex-shrink-0 ${isActive ? 'bg-amber-400 text-white' : 'bg-blue-100 text-blue-700'}`}>
                          {i + 1}
                        </span>
                        <span className="text-[11px] font-medium text-gray-700 truncate">{ann.annotator.name}</span>
                        {canJump && !isActive && (
                          <ExternalLink className="w-2.5 h-2.5 text-blue-400 flex-shrink-0" />
                        )}
                      </div>
                      {(ann.is_mine || user?.roles?.includes('admin')) && (
                        <button
                          onClick={(e) => { e.stopPropagation(); deleteAnnotation(ann.id) }}
                          className="opacity-0 group-hover:opacity-100 p-0.5 rounded text-gray-300 hover:text-red-500 transition-opacity flex-shrink-0"
                          title="Delete annotation"
                        >
                          <Trash2 className="w-3 h-3" />
                        </button>
                      )}
                    </div>
                    {hasQuote && (
                      <blockquote className={`text-[11px] italic border-l-2 px-2 py-1 mb-1.5 rounded-r line-clamp-3 ${
                        isActive ? 'text-amber-900 bg-amber-50 border-amber-300' : 'text-gray-500 bg-gray-50 border-blue-300'
                      }`}>
                        "{ann.quote}"
                      </blockquote>
                    )}
                    {doc.fileType === 'pdf' && isActive && hasQuote && (
                      <p className="text-[10px] text-amber-700 bg-amber-50 rounded px-2 py-1 mb-1">
                        Use Ctrl+F in the PDF viewer to search for the quoted text.
                      </p>
                    )}
                    <p className="text-xs text-gray-700 leading-relaxed">{ann.comment}</p>
                    <p className="text-[10px] text-gray-400 mt-1">{new Date(ann.created_at).toLocaleDateString()}</p>
                  </div>
                )
              })}
            </div>
          </div>
        )}
      </div>

      {/* DOCX selection popover */}
      {pending && (
        <div
          className="fixed z-[60] w-80 bg-white border border-gray-200 rounded-xl shadow-xl p-4"
          style={{ left: pending.x, top: pending.y }}
        >
          <p className="text-xs font-medium text-gray-700 mb-1.5">Add annotation</p>
          {pending.quote && (
            <blockquote className="text-[11px] text-gray-500 italic bg-gray-50 border-l-2 border-blue-300 px-2 py-1 mb-2 rounded-r line-clamp-2">
              "{pending.quote}"
            </blockquote>
          )}
          <textarea
            autoFocus value={newComment} onChange={e => setNewComment(e.target.value)}
            placeholder="Your comment or recommendation…" rows={3}
            className="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
          />
          <div className="flex gap-2">
            <button
              onClick={() => saveAnnotation(pending.quote, newComment, pending.positionHint)}
              disabled={saving || !newComment.trim()}
              className="flex-1 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {saving ? 'Saving…' : 'Save'}
            </button>
            <button onClick={() => setPending(null)} className="flex-1 py-1.5 border border-gray-200 text-gray-600 text-xs rounded-lg hover:bg-gray-50">
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

// ── Document Compare Viewer ───────────────────────────────────────────────────

/** 
 * Pure-frontend side-by-side DOCX comparison with diff highlighting.
 * Uses docx-preview for rendering and a custom LCS-based text differ.
 */
interface DocFile {
  versionNumber: number
  filename: string
  label: string
}

// Simple word-tokenised Longest Common Subsequence diff
function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function computeDiff(textA: string, textB: string) {
  const wordsA = textA.split(/\s+/).filter(Boolean)
  const wordsB = textB.split(/\s+/).filter(Boolean)
  const m = wordsA.length
  const n = wordsB.length
  // Build LCS table (cap at 4000 words each for performance)
  const cap = 4000
  const wa = wordsA.slice(0, cap)
  const wb = wordsB.slice(0, cap)
  const dp: number[][] = Array.from({ length: wa.length + 1 }, () => new Array(wb.length + 1).fill(0))
  for (let i = 1; i <= wa.length; i++) {
    for (let j = 1; j <= wb.length; j++) {
      dp[i][j] = wa[i-1] === wb[j-1] ? dp[i-1][j-1] + 1 : Math.max(dp[i-1][j], dp[i][j-1])
    }
  }
  // Trace back
  const removes = new Set<number>()
  const adds    = new Set<number>()
  let i = wa.length, j = wb.length
  while (i > 0 && j > 0) {
    if (wa[i-1] === wb[j-1]) { i--; j-- }
    else if (dp[i-1][j] >= dp[i][j-1]) { removes.add(i - 1); i-- }
    else { adds.add(j - 1); j-- }
  }
  while (i > 0) { removes.add(i - 1); i-- }
  while (j > 0) { adds.add(j - 1); j-- }

  const removedCount = removes.size
  const addedCount   = adds.size
  const totalTokens  = Math.max(m + n, 1)
  const similarity   = Math.round(((totalTokens - removedCount - addedCount) / totalTokens) * 100)

  // Build highlighted HTML for side A (removed words highlighted red)
  const htmlA = wa.map((w, idx) =>
    removes.has(idx) ? `<mark class="bg-red-200 text-red-900 rounded px-0.5">${escapeHtml(w)}</mark>` : escapeHtml(w)
  ).join(' ')

  // Build highlighted HTML for side B (added words highlighted green)
  const htmlB = wb.map((w, idx) =>
    adds.has(idx) ? `<mark class="bg-green-200 text-green-900 rounded px-0.5">${escapeHtml(w)}</mark>` : escapeHtml(w)
  ).join(' ')

  return { htmlA, htmlB, removedCount, addedCount, similarity }
}

function DocCompareViewer({
  submissionId,
  docxFiles,
  onClose,
}: {
  submissionId: string
  docxFiles: DocFile[]
  onClose: () => void
}) {
  const [leftIdx,  setLeftIdx]  = useState(0)
  const [rightIdx, setRightIdx] = useState(Math.min(1, docxFiles.length - 1))
  const [diffHtmlA, setDiffHtmlA]  = useState<string | null>(null)
  const [diffHtmlB, setDiffHtmlB]  = useState<string | null>(null)
  const [similarity, setSimilarity] = useState<number | null>(null)
  const [stats, setStats] = useState<{ removed: number; added: number } | null>(null)
  const [loadingLeft,  setLoadingLeft]  = useState(false)
  const [loadingRight, setLoadingRight] = useState(false)
  const [mode, setMode] = useState<'render' | 'diff'>('render')

  const leftContainerRef  = useRef<HTMLDivElement>(null)
  const rightContainerRef = useRef<HTMLDivElement>(null)

  const loadAndRender = useCallback(async (
    file: DocFile,
    containerRef: React.RefObject<HTMLDivElement | null>,
    setLoading: (v: boolean) => void
  ) => {
    if (!containerRef.current) return
    setLoading(true)
    containerRef.current.innerHTML = ''
    try {
      const res = await api.get(
        `/submissions/${submissionId}/files/${file.versionNumber}/${encodeURIComponent(file.filename)}`,
        { responseType: 'blob' }
      )
      await renderAsync(res.data, containerRef.current, undefined, {
        className: 'docx-preview-container',
        inWrapper: false,
        ignoreWidth: true,
      })
    } catch {
      if (containerRef.current) {
        containerRef.current.innerHTML = '<p class="text-sm text-red-500 p-4">Failed to load document.</p>'
      }
    } finally {
      setLoading(false)
    }
  }, [submissionId])

  const runDiff = useCallback(async () => {
    const fileA = docxFiles[leftIdx]
    const fileB = docxFiles[rightIdx]
    setLoadingLeft(true); setLoadingRight(true)
    try {
      const [resA, resB] = await Promise.all([
        api.get(`/submissions/${submissionId}/files/${fileA.versionNumber}/${encodeURIComponent(fileA.filename)}`, { responseType: 'blob' }),
        api.get(`/submissions/${submissionId}/files/${fileB.versionNumber}/${encodeURIComponent(fileB.filename)}`, { responseType: 'blob' }),
      ])
      // Extract text using docx-preview's text render into hidden divs
      const tmpA = document.createElement('div'); document.body.appendChild(tmpA)
      const tmpB = document.createElement('div'); document.body.appendChild(tmpB)
      await Promise.all([
        renderAsync(resA.data, tmpA, undefined, { inWrapper: false, ignoreWidth: true }),
        renderAsync(resB.data, tmpB, undefined, { inWrapper: false, ignoreWidth: true }),
      ])
      const textA = tmpA.innerText ?? ''
      const textB = tmpB.innerText ?? ''
      document.body.removeChild(tmpA)
      document.body.removeChild(tmpB)

      const result = computeDiff(textA, textB)
      setDiffHtmlA(result.htmlA)
      setDiffHtmlB(result.htmlB)
      setSimilarity(result.similarity)
      setStats({ removed: result.removedCount, added: result.addedCount })
    } catch {
      setDiffHtmlA('<p class="text-red-500">Failed to compute diff.</p>')
      setDiffHtmlB('<p class="text-red-500">Failed to compute diff.</p>')
    } finally {
      setLoadingLeft(false); setLoadingRight(false)
    }
  }, [submissionId, docxFiles, leftIdx, rightIdx])

  // Initial render of both documents
  useEffect(() => {
    if (mode === 'render') {
      loadAndRender(docxFiles[leftIdx], leftContainerRef, setLoadingLeft)
      loadAndRender(docxFiles[rightIdx], rightContainerRef, setLoadingRight)
    } else {
      runDiff()
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [leftIdx, rightIdx, mode])

  const SimilarityBadge = () => {
    if (similarity === null) return null
    const color = similarity >= 80 ? 'bg-green-100 text-green-800 border-green-200'
                : similarity >= 50 ? 'bg-amber-100 text-amber-800 border-amber-200'
                : 'bg-red-100 text-red-800 border-red-200'
    return (
      <div className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-medium ${color}`}>
        <RefreshCw className="w-3.5 h-3.5" />
        Similarity: {similarity}%
        {stats && (
          <span className="font-normal text-xs ml-1">
            ({stats.removed} removed, {stats.added} added)
          </span>
        )}
      </div>
    )
  }

  return (
    <div className="fixed inset-0 z-50 bg-gray-50 flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between px-5 py-3 bg-white border-b border-gray-200 gap-3 flex-wrap">
        <div className="flex items-center gap-3">
          <button onClick={onClose} className="text-gray-500 hover:text-gray-700">
            <X className="w-5 h-5" />
          </button>
          <h2 className="text-base font-semibold text-gray-900">Compare Versions</h2>
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          {/* Mode toggle */}
          <div className="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
            <button
              onClick={() => setMode('render')}
              className={`px-3 py-1.5 ${mode === 'render' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
            >
              Side-by-Side
            </button>
            <button
              onClick={() => setMode('diff')}
              className={`px-3 py-1.5 ${mode === 'diff' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
            >
              Diff + Similarity
            </button>
          </div>
          {mode === 'diff' && <SimilarityBadge />}
        </div>

        <div className="flex items-center gap-3">
          <button onClick={onClose} className="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
            Close
          </button>
        </div>
      </div>

      {/* Version selectors */}
      <div className="flex bg-white border-b border-gray-200">
        <div className="flex-1 flex items-center gap-2 px-5 py-2 border-r border-gray-200">
          <span className="text-xs text-gray-500 font-medium flex-shrink-0">Left:</span>
          <select
            value={leftIdx}
            onChange={e => setLeftIdx(Number(e.target.value))}
            className="flex-1 text-sm border border-gray-200 rounded-lg px-2 py-1 text-gray-700 bg-white"
          >
            {docxFiles.map((f, idx) => (
              <option key={idx} value={idx} disabled={idx === rightIdx}>{f.label}</option>
            ))}
          </select>
        </div>
        <div className="flex-1 flex items-center gap-2 px-5 py-2">
          <span className="text-xs text-gray-500 font-medium flex-shrink-0">Right:</span>
          <select
            value={rightIdx}
            onChange={e => setRightIdx(Number(e.target.value))}
            className="flex-1 text-sm border border-gray-200 rounded-lg px-2 py-1 text-gray-700 bg-white"
          >
            {docxFiles.map((f, idx) => (
              <option key={idx} value={idx} disabled={idx === leftIdx}>{f.label}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-hidden flex">
        {/* Left pane */}
        <div className="flex-1 flex flex-col overflow-hidden border-r border-gray-200">
          <div className="px-4 py-2 bg-red-50 border-b border-red-100 text-xs font-medium text-red-700 flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-red-400 inline-block" /> {docxFiles[leftIdx]?.label}
            {mode === 'diff' && <span className="ml-auto text-red-500">Removed words highlighted</span>}
          </div>
          <div className="flex-1 overflow-auto p-4 relative">
            {(loadingLeft) && (
              <div className="absolute inset-0 flex items-center justify-center bg-white/80">
                <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
              </div>
            )}
            {mode === 'render' ? (
              <div ref={leftContainerRef} className="docx-compare-pane text-sm leading-relaxed" />
            ) : (
              <div
                className="text-sm leading-relaxed text-gray-800 whitespace-pre-wrap"
                dangerouslySetInnerHTML={{ __html: diffHtmlA ?? '' }}
              />
            )}
          </div>
        </div>

        {/* Right pane */}
        <div className="flex-1 flex flex-col overflow-hidden">
          <div className="px-4 py-2 bg-green-50 border-b border-green-100 text-xs font-medium text-green-700 flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-green-400 inline-block" /> {docxFiles[rightIdx]?.label}
            {mode === 'diff' && <span className="ml-auto text-green-600">Added words highlighted</span>}
          </div>
          <div className="flex-1 overflow-auto p-4 relative">
            {(loadingRight) && (
              <div className="absolute inset-0 flex items-center justify-center bg-white/80">
                <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
              </div>
            )}
            {mode === 'render' ? (
              <div ref={rightContainerRef} className="docx-compare-pane text-sm leading-relaxed" />
            ) : (
              <div
                className="text-sm leading-relaxed text-gray-800 whitespace-pre-wrap"
                dangerouslySetInnerHTML={{ __html: diffHtmlB ?? '' }}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

// ── Documents Tab ─────────────────────────────────────────────────────────────

function DocumentsTab({ sub, canEdit }: { sub: Submission; canEdit: boolean }) {
  const qc = useQueryClient()
  const [viewingDoc, setViewingDoc] = useState<ViewingDoc | null>(null)
  const [showCompare, setShowCompare] = useState(false)
  const [showUploadForm, setShowUploadForm] = useState(false)
  const [uploadFiles, setUploadFiles] = useState<File[]>([])
  const [changeSummary, setChangeSummary] = useState('')
  const [uploadError, setUploadError] = useState('')
  const [isUploading, setIsUploading] = useState(false)

  const allowedExts = sub.submission_type?.allowed_extensions ?? ['pdf', 'docx']
  const maxSizeMb   = sub.submission_type?.max_file_size_mb ?? 8
  const maxFiles    = sub.submission_type?.max_files ?? 5

  const handleUpload = async () => {
    if (uploadFiles.length === 0) return
    setUploadError('')
    setIsUploading(true)
    try {
      const fd = new FormData()
      uploadFiles.forEach(f => fd.append('files[]', f))
      if (changeSummary.trim()) fd.append('change_summary', changeSummary.trim())
      await api.post(`/submissions/${sub.id}/versions`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      qc.invalidateQueries({ queryKey: ['submission', sub.id] })
      setShowUploadForm(false)
      setUploadFiles([])
      setChangeSummary('')
    } catch (e: any) {
      setUploadError(e?.response?.data?.message ?? 'Upload failed. Please try again.')
    } finally {
      setIsUploading(false)
    }
  }

  // Collect all .docx files across all versions for comparison
  const docxFiles: { versionNumber: number; filename: string; label: string }[] = []
  sub.versions.forEach(v => {
    v.document_paths.forEach(p => {
      const fn = p.split('/').pop() ?? ''
      if (fn.toLowerCase().endsWith('.docx')) {
        docxFiles.push({
          versionNumber: v.version_number,
          filename: fn,
          label: v.version_number === 0 ? `Original — ${fn}` : `Rev ${v.version_number} — ${fn}`,
        })
      }
    })
  })
  const canCompare = docxFiles.length >= 2

  return (
    <>
      {viewingDoc && (
        <InlineDocViewer doc={viewingDoc} onClose={() => setViewingDoc(null)} />
      )}
      {showCompare && (
        <DocCompareViewer
          submissionId={sub.id}
          docxFiles={docxFiles}
          onClose={() => setShowCompare(false)}
        />
      )}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <History className="w-4 h-4 text-gray-500" />
            <h2 className="text-base font-semibold text-gray-900">Documents</h2>
            <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{sub.versions.length} version{sub.versions.length !== 1 ? 's' : ''}</span>
          </div>
          <div className="flex items-center gap-2">
            {canCompare && (
              <button
                onClick={() => setShowCompare(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 border border-indigo-200 text-indigo-700 bg-indigo-50 rounded-lg text-sm hover:bg-indigo-100"
              >
                <RefreshCw className="w-4 h-4" />
                Compare Versions
              </button>
            )}
            {canEdit && (
              <button
                onClick={() => setShowUploadForm(v => !v)}
                className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
              >
                <Upload className="w-4 h-4" />
                Upload New Version
              </button>
            )}
          </div>
        </div>

        {/* Inline upload form */}
        {showUploadForm && (
          <div className="mb-4 p-4 bg-blue-50 rounded-xl border border-blue-100 space-y-3">
            <p className="text-sm font-semibold text-blue-800">Upload New Version</p>
            <div>
              <label className="block text-xs text-gray-600 mb-1">
                Files * — allowed: {allowedExts.join(', ')} · max {maxSizeMb} MB each · up to {maxFiles} file{maxFiles !== 1 ? 's' : ''}
              </label>
              <input
                type="file"
                multiple
                accept={allowedExts.map(e => `.${e}`).join(',')}
                onChange={e => setUploadFiles(Array.from(e.target.files ?? []))}
                className="block w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 cursor-pointer"
              />
            </div>
            {uploadFiles.length > 0 && (
              <ul className="space-y-1">
                {uploadFiles.map((f, i) => (
                  <li key={i} className="flex items-center gap-2 text-xs text-gray-600">
                    <FileText className="w-3 h-3 flex-shrink-0" /> {f.name}
                  </li>
                ))}
              </ul>
            )}
            <div>
              <label className="block text-xs text-gray-600 mb-1">Change summary (optional)</label>
              <textarea
                value={changeSummary}
                onChange={e => setChangeSummary(e.target.value)}
                rows={2}
                placeholder="Describe what changed in this revision…"
                className="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              />
            </div>
            {uploadError && <p className="text-xs text-red-600">{uploadError}</p>}
            <div className="flex gap-2">
              <button
                onClick={handleUpload}
                disabled={isUploading || uploadFiles.length === 0}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {isUploading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Upload className="w-4 h-4" />}
                Upload
              </button>
              <button
                onClick={() => { setShowUploadForm(false); setUploadFiles([]); setChangeSummary(''); setUploadError('') }}
                className="px-3 py-1.5 border border-gray-200 text-sm rounded-lg text-gray-600 hover:bg-gray-50"
              >
                Cancel
              </button>
            </div>
          </div>
        )}

        {sub.versions.length === 0 ? (
          <div className="flex flex-col items-center py-10 text-center">
            <FileText className="w-10 h-10 text-gray-200 mb-3" />
            <p className="text-sm text-gray-500">No files uploaded yet.</p>
            {canEdit && (
              <button
                onClick={() => setShowUploadForm(true)}
                className="mt-3 flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
              >
                <Upload className="w-4 h-4" /> Upload Document
              </button>
            )}
          </div>
        ) : (
          <ul className="space-y-3">
            {sub.versions.map(v => {
              const isLatest = v.version_number === sub.current_version
              return (
                <li key={v.id} className={`border rounded-xl overflow-hidden ${isLatest ? 'border-blue-200' : 'border-gray-100'}`}>
                  {/* Version header */}
                  <div className={`flex items-center gap-3 px-4 py-3 ${isLatest ? 'bg-blue-50 border-b border-blue-100' : 'bg-gray-50 border-b border-gray-100'}`}>
                    <FileText className={`w-4 h-4 flex-shrink-0 ${isLatest ? 'text-blue-500' : 'text-gray-400'}`} />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-semibold text-gray-800">
                          {v.version_number === 0 ? 'Original Submission' : `Revision ${v.version_number}`}
                        </p>
                        {isLatest && (
                          <span className="text-xs bg-green-500 text-white px-1.5 py-0.5 rounded-full font-medium">Latest</span>
                        )}
                      </div>
                      <p className="text-xs text-gray-500 mt-0.5">
                        Uploaded {new Date(v.submitted_at).toLocaleString('en-US', { year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                      </p>
                    </div>
                  </div>

                  {v.change_summary && (
                    <div className="px-4 py-2 bg-white border-b border-gray-100">
                      <p className="text-xs text-gray-500">
                        <span className="font-medium text-gray-700">Change note: </span>
                        {v.change_summary}
                      </p>
                    </div>
                  )}

                  {/* Files */}
                  <div className="px-4 py-3 bg-white space-y-2">
                    {v.document_paths.map((path, pi) => {
                      const filename = path.split('/').pop() ?? 'file'
                      const ext = filename.split('.').pop()?.toLowerCase() ?? ''
                      const isPdf = ext === 'pdf'
                      const isDocx = ext === 'docx'
                      const canView = isPdf || isDocx
                      const apiDownloadPath = `/submissions/${sub.id}/files/${v.version_number}/${encodeURIComponent(filename)}`
                      const handleFileDownload = () => {
                        api.get(apiDownloadPath, { responseType: 'blob' }).then(res => {
                          const url = URL.createObjectURL(res.data)
                          const a = document.createElement('a')
                          a.href = url
                          a.download = filename
                          a.click()
                          URL.revokeObjectURL(url)
                        })
                      }
                      return (
                        <div key={pi} className="flex items-center gap-3 p-2.5 border border-gray-100 rounded-lg bg-gray-50">
                          <FileText className="w-4 h-4 text-gray-400 flex-shrink-0" />
                          <p className="flex-1 text-sm text-gray-700 truncate min-w-0">{filename}</p>
                          <div className="flex items-center gap-2 flex-shrink-0">
                            {canView && (
                              <button
                                onClick={() => setViewingDoc({
                                  submissionId: sub.id,
                                  versionNumber: v.version_number,
                                  filename,
                                  fileType: isPdf ? 'pdf' : 'docx',
                                })}
                                className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-700 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700 transition-colors"
                              >
                                <Eye className="w-3.5 h-3.5" /> View
                              </button>
                            )}
                            <button
                              onClick={handleFileDownload}
                              className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition-colors"
                            >
                              <Download className="w-3.5 h-3.5" /> Download
                            </button>
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </>
  )
}

// ── Feedback Tab ──────────────────────────────────────────────────────────────

const DECISION_ICON: Record<string, React.ElementType> = {
  approve:                ThumbsUp,
  reject:                 ThumbsDown,
  revise:                 RotateCcw,
  ACCEPTED:               CheckCircle2,
  CONDITIONALLY_ACCEPTED: CheckCircle2,
  REVISION_REQUIRED:      RotateCcw,
  REJECTED:               XCircle,
}

const DECISION_COLOR: Record<string, string> = {
  approve:                'text-green-600 bg-green-50 border-green-200',
  reject:                 'text-red-600 bg-red-50 border-red-200',
  revise:                 'text-orange-600 bg-orange-50 border-orange-200',
  ACCEPTED:               'text-green-700 bg-green-50 border-green-200',
  CONDITIONALLY_ACCEPTED: 'text-teal-700 bg-teal-50 border-teal-200',
  REVISION_REQUIRED:      'text-orange-600 bg-orange-50 border-orange-200',
  REJECTED:               'text-red-700 bg-red-50 border-red-200',
}

const DECISION_LABEL: Record<string, string> = {
  approve:                'Approved',
  reject:                 'Rejected',
  revise:                 'Revision Requested',
  ACCEPTED:               'Accepted',
  CONDITIONALLY_ACCEPTED: 'Conditionally Accepted',
  REVISION_REQUIRED:      'Revision Required',
  REJECTED:               'Rejected',
}

const APPEAL_STATUS_COLORS: Record<string, string> = {
  PENDING:      'bg-yellow-100 text-yellow-700',
  UNDER_REVIEW: 'bg-blue-100 text-blue-700',
  UPHELD:       'bg-green-100 text-green-700',
  DISMISSED:    'bg-red-100 text-red-700',
}

// ── Gated Release Panel (for the assigned gatekeeper) ─────────────────────────

function GatedReleasePanel({
  submissionId,
  pendingGatekeeperStage,
}: {
  submissionId: string
  pendingGatekeeperStage?: { name: string | null; outcome: string | null } | null
}) {
  const qc = useQueryClient()
  const toast = useToastHelpers()
  const [mode,          setMode]          = useState<'feedback' | 'recheck'>('feedback')
  const [feedbackText,  setFeedbackText]  = useState('')
  const [recheckReason, setRecheckReason] = useState('')
  const [showConfirm,   setShowConfirm]   = useState(false)
  const [submitError,   setSubmitError]   = useState('')

  const releaseMutation = useMutation({
    mutationFn: () =>
      api.post(`/submissions/${submissionId}/gated-release`, {
        decision: 'REVISION_REQUIRED',
        feedback: feedbackText.trim(),
      }),
    onSuccess: () => {
      setShowConfirm(false)
      qc.invalidateQueries({ queryKey: ['submission', submissionId] })
      qc.invalidateQueries({ queryKey: ['submission-feedback', submissionId] })
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      qc.invalidateQueries({ queryKey: ['gated-reviews'] })
      qc.invalidateQueries({ queryKey: ['dashboard-stats'] })
      toast.success('Feedback sent to submitter.', 'The submitter has been notified to revise and resubmit.')
    },
    onError: (e: any) => {
      const msg = e?.response?.data?.message ?? 'Failed to send feedback.'
      setSubmitError(msg)
      setShowConfirm(false)
      toast.error('Failed to send feedback.', msg)
    },
  })

  const recheckMutation = useMutation({
    mutationFn: () =>
      api.post(`/admin/gated-reviews/${submissionId}/recheck`, { reason: recheckReason }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission', submissionId] })
      qc.invalidateQueries({ queryKey: ['submission-feedback', submissionId] })
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      qc.invalidateQueries({ queryKey: ['gated-reviews'] })
      toast.success('Stage returned for re-review.', 'Reviewers have been notified to reconsider.')
    },
    onError: (e: any) => {
      const msg = e?.response?.data?.message ?? 'Failed to send back for re-review.'
      toast.error('Re-review failed.', msg)
    },
  })

  const stageName    = pendingGatekeeperStage?.name ?? null
  const stageOutcome = pendingGatekeeperStage?.outcome ?? null

  return (
    <div className="mb-5 bg-white rounded-xl border-2 border-purple-200 shadow-sm overflow-hidden">
      {/* Header */}
      <div className="flex items-center gap-2 px-5 py-3.5 bg-purple-50 border-b border-purple-100">
        <Gavel className="w-4 h-4 text-purple-600" />
        <div>
          <p className="text-sm font-semibold text-purple-900">Gatekeeper Action Required</p>
          <p className="text-xs text-purple-600 mt-0.5">Read reviewer feedback, then send consolidated feedback to the submitter or return the stage for re-review.</p>
        </div>
      </div>

      {/* Stage context banner */}
      {stageName && (
        <div className="px-5 pt-4">
          <div className="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm">
            <p className="font-medium text-amber-800">
              Stage &ldquo;{stageName}&rdquo; &mdash;{' '}
              {stageOutcome === 'FAILED' ? 'Rejected by reviewers' : 'Revision requested by reviewers'}
            </p>
            <p className="text-amber-700 mt-0.5 text-xs">
              Review the individual feedback below. You can consolidate it and send to the submitter, or return this stage for re-review if you disagree with the reviewers.
            </p>
          </div>
        </div>
      )}

      {/* Mode tabs */}
      <div className="flex gap-2 px-5 pt-4">
        <button
          onClick={() => setMode('feedback')}
          className={`px-3 py-1.5 text-xs rounded-md font-medium transition-colors ${mode === 'feedback' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
        >
          Send Feedback to Submitter
        </button>
        {stageName && (
          <button
            onClick={() => setMode('recheck')}
            className={`px-3 py-1.5 text-xs rounded-md font-medium transition-colors ${mode === 'recheck' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
          >
            Return Stage for Re-review
          </button>
        )}
      </div>

      <div className="p-5 space-y-4">
        {mode === 'feedback' ? (
          <>
            <div className="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-700">
              Write consolidated feedback for the submitter. They will see only this message — not the individual reviewer comments.
            </div>

            <div>
              <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">
                Consolidated feedback <span className="text-red-500">*</span>{' '}
                <span className="text-gray-400 normal-case font-normal">(visible to submitter)</span>
              </label>
              <textarea
                className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none"
                rows={5}
                placeholder="Summarise the reviewer feedback and explain what the submitter needs to address in their revision…"
                value={feedbackText}
                onChange={e => { setFeedbackText(e.target.value); setSubmitError('') }}
                maxLength={5000}
              />
              <p className="text-xs text-gray-400 mt-1">{feedbackText.length}/5000</p>
            </div>

            {submitError && <p className="text-sm text-red-600">{submitError}</p>}

            <div className="flex items-center gap-3">
              <button
                onClick={() => setShowConfirm(true)}
                disabled={!feedbackText.trim()}
                className="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg bg-orange-500 hover:bg-orange-600 text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                <RotateCcw className="w-4 h-4" />
                Send Feedback &amp; Request Revision
              </button>
            </div>
          </>
        ) : (
          <>
            <div className="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm">
              <p className="font-medium text-gray-800">Return &ldquo;{stageName}&rdquo; for re-review</p>
              <p className="text-gray-600 mt-0.5 text-xs">
                This resets all reviewer decisions at this stage and notifies them to reconsider.
                The submission returns to <strong>In Review</strong> status.
              </p>
            </div>
            <div>
              <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">
                Reason for re-review <span className="text-red-500">*</span>
              </label>
              <textarea
                className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none"
                rows={4}
                placeholder={`Explain why the reviewers' decision on "${stageName}" should be reconsidered…`}
                value={recheckReason}
                onChange={e => setRecheckReason(e.target.value)}
                maxLength={2000}
              />
              <p className="text-xs text-gray-400 mt-1">{recheckReason.length}/2000</p>
            </div>
            <button
              onClick={() => recheckMutation.mutate()}
              disabled={!recheckReason.trim() || recheckMutation.isPending}
              className="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              {recheckMutation.isPending
                ? <Loader2 className="w-4 h-4 animate-spin" />
                : <RotateCcw className="w-4 h-4" />}
              Return to Stage for Re-review
            </button>
          </>
        )}
      </div>

      {showConfirm && (
        <ConfirmModal
          title="Send Feedback to Submitter"
          message="Your consolidated feedback will be sent to the submitter. They will be asked to revise and resubmit. Individual reviewer comments will not be visible to them."
          confirmLabel="Yes, Send Feedback"
          confirmClass="bg-orange-500 hover:bg-orange-600"
          onConfirm={() => releaseMutation.mutate()}
          onCancel={() => setShowConfirm(false)}
          loading={releaseMutation.isPending}
        />
      )}
    </div>
  )
}

function FeedbackTab({
  submissionId, submissionStatus, isAdmin, isGatedReview, isGatekeeper, pendingGatekeeperStage,
}: {
  submissionId: string
  submissionStatus: SubmissionStatus
  isAdmin: boolean
  isGatedReview?: boolean
  isGatekeeper?: boolean
  pendingGatekeeperStage?: { name: string | null; outcome: string | null } | null
}) {
  const qc = useQueryClient()
  const [showAppeal, setShowAppeal] = useState(false)

  const { data, isLoading } = useQuery<{ data: FeedbackItem[]; appeal: Appeal | null }>({
    queryKey: ['submission-feedback', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/feedback`).then(r => r.data),
  })

  const feedbackItems = data?.data ?? []
  const appeal = data?.appeal ?? null
  const isDraft = submissionStatus === 'DRAFT'

  if (isDraft) {
    return (
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
        <MessageSquare className="w-10 h-10 text-gray-200 mx-auto mb-3" />
        <p className="text-sm text-gray-500">Reviewer feedback will appear here after your submission is reviewed.</p>
      </div>
    )
  }

  if (isLoading) {
    return (
      <div className="flex justify-center py-12">
        <Loader2 className="w-5 h-5 animate-spin text-gray-300" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Gatekeeper release panel — only shown to the assigned gatekeeper */}
      {isGatedReview && isGatekeeper && submissionStatus === 'PENDING_RELEASE' && (
        <GatedReleasePanel submissionId={submissionId} pendingGatekeeperStage={pendingGatekeeperStage} />
      )}

      {feedbackItems.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
          <MessageSquare className="w-10 h-10 text-gray-200 mx-auto mb-3" />
          <p className="text-sm text-gray-500">No reviewer feedback yet.</p>
          <p className="text-xs text-gray-400 mt-1">Feedback will appear once a reviewer submits their decision.</p>
        </div>
      ) : (
        feedbackItems.map((item) => {
          const Icon = DECISION_ICON[item.decision] ?? Info
          const colorClass = DECISION_COLOR[item.decision] ?? 'text-gray-600 bg-gray-50 border-gray-200'
          return (
            <div key={item.id} className={`bg-white rounded-xl shadow-sm overflow-hidden ${item.is_gated_release ? 'border-2 border-purple-200' : 'border border-gray-200'}`}>
              <div className={`flex items-center gap-3 px-5 py-3.5 border-b ${colorClass}`}>
                <Icon className="w-4 h-4 flex-shrink-0" />
                <div className="flex-1">
                  <p className="text-sm font-semibold">
                    {item.is_gated_release ? 'Release Decision: ' : ''}{DECISION_LABEL[item.decision] ?? item.decision}
                  </p>
                  {item.stage && <p className="text-xs opacity-70">{item.stage.name}</p>}
                  {item.is_gated_release && <p className="text-xs opacity-70">Official gated release decision</p>}
                </div>
                <div className="text-right">
                  {item.reviewer && <p className="text-xs font-medium">{item.reviewer.name}</p>}
                  <p className="text-xs opacity-60">{new Date(item.decision_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</p>
                </div>
              </div>
              <div className="px-5 py-4">
                {item.comments ? (
                  <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{item.comments}</p>
                ) : (
                  <p className="text-sm text-gray-400 italic">No comments provided.</p>
                )}
              </div>
            </div>
          )
        })
      )}

      {/* Gated release: pending notice for non-gatekeeper viewers */}
      {isGatedReview && submissionStatus === 'PENDING_RELEASE' && !isGatekeeper && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-amber-800">Awaiting Release Decision</p>
            <p className="text-sm text-amber-700 mt-0.5">
              All review stages are complete. The gatekeeper will issue the final release decision.
            </p>
          </div>
        </div>
      )}

      {/* Legacy pending notice for non-gated reviews */}
      {!isGatedReview && submissionStatus === 'PENDING_RELEASE' && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-amber-800">Awaiting Release Decision</p>
            <p className="text-sm text-amber-700 mt-0.5">
              All review stages are complete. A coordinator will issue the final release decision.
            </p>
          </div>
        </div>
      )}

      {/* Gated release: decision received */}
      {(submissionStatus === 'ACCEPTED' || submissionStatus === 'CONDITIONALLY_ACCEPTED') && !isAdmin && (
        <div className="bg-green-50 border border-green-200 rounded-xl p-5 flex items-start gap-3">
          <CheckCircle2 className="h-5 w-5 text-green-600 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-green-800">
              {submissionStatus === 'ACCEPTED' ? 'Submission Accepted' : 'Conditionally Accepted'}
            </p>
            <p className="text-sm text-green-700 mt-0.5">
              A formal release decision has been issued. Check feedback below for any conditions.
            </p>
          </div>
        </div>
      )}

      {/* Appeal section */}
      {(submissionStatus === 'REJECTED' || submissionStatus === 'APPEAL_PENDING') && !isAdmin && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <div className="flex items-center gap-2 mb-3">
            <Gavel className="w-4 h-4 text-orange-500" />
            <h3 className="text-sm font-semibold text-gray-900">Appeal Decision</h3>
          </div>

          {appeal ? (
            <div className="space-y-3">
              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${APPEAL_STATUS_COLORS[appeal.status] ?? 'bg-gray-100 text-gray-600'}`}>
                  Appeal {appeal.status.replace('_', ' ')}
                </span>
                <span className="text-xs text-gray-400">{new Date(appeal.created_at).toLocaleDateString()}</span>
              </div>
              <div className="bg-gray-50 rounded-lg p-3">
                <p className="text-xs font-medium text-gray-600 mb-1">Your grounds:</p>
                <p className="text-sm text-gray-700 whitespace-pre-wrap">{appeal.grounds}</p>
              </div>
              {appeal.resolution_note && (
                <div className="bg-blue-50 border border-blue-100 rounded-lg p-3">
                  <p className="text-xs font-medium text-blue-700 mb-1">Coordinator response:</p>
                  <p className="text-sm text-blue-800 whitespace-pre-wrap">{appeal.resolution_note}</p>
                </div>
              )}
            </div>
          ) : (
            <div>
              <p className="text-sm text-gray-600 mb-3">
                If you believe this rejection was made in error, you may submit a formal appeal for coordinator review.
              </p>
              <button
                onClick={() => setShowAppeal(true)}
                className="flex items-center gap-1.5 px-4 py-2 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600"
              >
                <Gavel className="w-4 h-4" /> Submit Appeal
              </button>
            </div>
          )}
        </div>
      )}

      {showAppeal && (
        <AppealModal
          submissionId={submissionId}
          onClose={() => setShowAppeal(false)}
          onSubmitted={() => {
            qc.invalidateQueries({ queryKey: ['submission-feedback', submissionId] })
          }}
        />
      )}
    </div>
  )
}

// ── Activity Tab ──────────────────────────────────────────────────────────────

const EVENT_ICON: Record<string, React.ElementType> = {
  created:          CheckCircle2,
  version_uploaded: Upload,
  review_decision:  UserCheck,
  system:           Info,
}

const EVENT_COLOR: Record<string, string> = {
  created:          'bg-green-100 text-green-700',
  version_uploaded: 'bg-blue-100 text-blue-700',
  review_decision:  'bg-purple-100 text-purple-700',
  system:           'bg-gray-100 text-gray-600',
}

function ActivityTab({ submissionId }: { submissionId: string }) {
  const { data, isLoading } = useQuery<{ data: ActivityEvent[] }>({
    queryKey: ['submission-activity', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/activity`).then(r => r.data),
  })

  const events = data?.data ?? []

  if (isLoading) {
    return (
      <div className="flex justify-center py-12">
        <Loader2 className="w-5 h-5 animate-spin text-gray-300" />
      </div>
    )
  }

  if (events.length === 0) {
    return (
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
        <Clock className="w-10 h-10 text-gray-200 mx-auto mb-3" />
        <p className="text-sm text-gray-500">No activity recorded yet.</p>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <div className="relative">
        {/* Timeline line */}
        <div className="absolute left-4 top-0 bottom-0 w-px bg-gray-100" />

        <ul className="space-y-5">
          {events.map(event => {
            const Icon = EVENT_ICON[event.type] ?? Info
            const colorClass = EVENT_COLOR[event.type] ?? 'bg-gray-100 text-gray-600'
            return (
              <li key={event.id} className="relative flex gap-4 pl-10">
                {/* Dot */}
                <div className={`absolute left-0 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${colorClass}`}>
                  <Icon className="w-3.5 h-3.5" />
                </div>

                <div className="flex-1 min-w-0 pb-1">
                  <div className="flex items-start justify-between gap-2 flex-wrap">
                    <p className="text-sm font-medium text-gray-800">{event.label}</p>
                    <time className="text-xs text-gray-400 flex-shrink-0">
                      {new Date(event.date).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric',
                        hour: '2-digit', minute: '2-digit',
                      })}
                    </time>
                  </div>
                  {event.actor && (
                    <p className="text-xs text-gray-500 mt-0.5">by {event.actor}</p>
                  )}
                  {event.stage && (
                    <p className="text-xs text-gray-400">Stage: {event.stage}</p>
                  )}
                  {event.note && (
                    <p className="text-xs text-gray-500 mt-1 italic">"{event.note}"</p>
                  )}
                </div>
              </li>
            )
          })}
        </ul>
      </div>
    </div>
  )
}

// ── Communication Tab ─────────────────────────────────────────────────────────

/**
 * Native rich-text editor + threaded message list.
 * Uses document.execCommand (no 3rd-party libraries).
 */
function CommunicationTab({ submissionId }: { submissionId: string }) {
  const qc = useQueryClient()
  const toast = useToastHelpers()
  const editorRef = useRef<HTMLDivElement>(null)
  const bottomRef = useRef<HTMLDivElement>(null)
  const [sending, setSending] = useState(false)

  const { data, isLoading, refetch } = useQuery<{ data: SubmissionMessage[] }>({
    queryKey: ['submission-messages', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/messages`).then(r => r.data),
    refetchInterval: 15_000,
  })

  const messages = data?.data ?? []

  // Scroll to bottom when new messages arrive
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages.length])

  // Focus when tab becomes visible
  useEffect(() => {
    editorRef.current?.focus()
  }, [])

  const exec = (cmd: string, val?: string) => {
    editorRef.current?.focus()
    document.execCommand(cmd, false, val ?? '')
  }

  const sendMessage = async () => {
    const html = editorRef.current?.innerHTML?.trim() ?? ''
    const text = editorRef.current?.innerText?.trim() ?? ''
    if (!text) return
    setSending(true)
    try {
      await api.post(`/submissions/${submissionId}/messages`, { body_html: html })
      if (editorRef.current) editorRef.current.innerHTML = ''
      await refetch()
      qc.invalidateQueries({ queryKey: ['submission-messages', submissionId] })
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Failed to send message.')
    } finally {
      setSending(false)
    }
  }

  const deleteMessage = async (msgId: string) => {
    try {
      await api.delete(`/submissions/${submissionId}/messages/${msgId}`)
      qc.invalidateQueries({ queryKey: ['submission-messages', submissionId] })
    } catch {
      toast.error('Failed to delete message.')
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault()
      sendMessage()
    }
  }

  const ToolBtn = ({ cmd, val, title, children }: {
    cmd: string; val?: string; title: string; children: React.ReactNode
  }) => (
    <button
      type="button"
      onMouseDown={(e) => { e.preventDefault(); exec(cmd, val) }}
      title={title}
      className="w-7 h-7 flex items-center justify-center rounded text-gray-600 hover:bg-gray-100 text-xs font-medium"
    >
      {children}
    </button>
  )

  return (
    <div className="flex flex-col gap-4">
      {/* Messages list */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader2 className="w-5 h-5 animate-spin text-gray-300" />
          </div>
        ) : messages.length === 0 ? (
          <div className="flex flex-col items-center py-12 text-center">
            <Send className="w-10 h-10 text-gray-200 mb-3" />
            <p className="text-sm text-gray-500">No messages yet. Start the conversation.</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-50 max-h-[520px] overflow-y-auto px-5 py-4 space-y-4">
            {messages.map(msg => (
              <div key={msg.id} className={`flex gap-3 pt-4 first:pt-0 ${msg.is_mine ? 'flex-row-reverse' : ''}`}>
                {/* Avatar */}
                <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold text-white
                  ${msg.is_mine ? 'bg-indigo-600' : 'bg-gray-400'}`}>
                  {msg.sender.name.slice(0, 1).toUpperCase()}
                </div>
                {/* Bubble */}
                <div className={`flex-1 min-w-0 max-w-[78%] ${msg.is_mine ? 'items-end' : 'items-start'} flex flex-col`}>
                  <div className={`flex items-center gap-2 mb-1 ${msg.is_mine ? 'flex-row-reverse' : ''}`}>
                    <span className="text-xs font-semibold text-gray-700">{msg.sender.name}</span>
                    <span className="text-xs text-gray-400">
                      {new Date(msg.created_at).toLocaleString('en-US', {
                        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
                      })}
                    </span>
                    {msg.is_mine && (
                      <button
                        onClick={() => deleteMessage(msg.id)}
                        className="text-gray-300 hover:text-red-400 transition-colors"
                        title="Delete message"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    )}
                  </div>
                  <div
                    className={`prose prose-sm max-w-none px-3.5 py-2.5 rounded-xl text-sm leading-relaxed
                      ${msg.is_mine
                        ? 'bg-indigo-600 text-white rounded-tr-none prose-invert'
                        : 'bg-gray-100 text-gray-800 rounded-tl-none'}`}
                    dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(msg.body_html) }}
                  />
                </div>
              </div>
            ))}
            <div ref={bottomRef} />
          </div>
        )}
      </div>

      {/* Compose area */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {/* Formatting toolbar */}
        <div className="flex items-center gap-0.5 px-3 py-2 border-b border-gray-100 bg-gray-50">
          <ToolBtn cmd="bold" title="Bold (Ctrl+B)"><strong>B</strong></ToolBtn>
          <ToolBtn cmd="italic" title="Italic (Ctrl+I)"><em>I</em></ToolBtn>
          <ToolBtn cmd="underline" title="Underline (Ctrl+U)"><u>U</u></ToolBtn>
          <ToolBtn cmd="strikeThrough" title="Strikethrough"><s>S</s></ToolBtn>
          <span className="mx-1 h-4 border-r border-gray-200" />
          <ToolBtn cmd="insertUnorderedList" title="Bullet list">•—</ToolBtn>
          <ToolBtn cmd="insertOrderedList" title="Numbered list">1.</ToolBtn>
          <span className="mx-1 h-4 border-r border-gray-200" />
          <ToolBtn cmd="formatBlock" val="blockquote" title="Quote">"</ToolBtn>
          <ToolBtn cmd="formatBlock" val="h4" title="Heading">H</ToolBtn>
          <ToolBtn cmd="removeFormat" title="Clear formatting">✕</ToolBtn>
        </div>
        {/* Editor */}
        <div
          ref={editorRef}
          contentEditable
          suppressContentEditableWarning
          onKeyDown={handleKeyDown}
          className="min-h-[96px] max-h-48 overflow-y-auto px-4 py-3 text-sm text-gray-800
            focus:outline-none prose prose-sm max-w-none
            [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5
            [&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-3 [&_blockquote]:italic [&_blockquote]:text-gray-500"
          data-placeholder="Type a message... (Ctrl+Enter to send)"
        />
        {/* Send bar */}
        <div className="flex items-center justify-between px-3 py-2 border-t border-gray-100">
          <p className="text-xs text-gray-400">Ctrl+Enter to send</p>
          <button
            onClick={sendMessage}
            disabled={sending}
            className="flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          >
            {sending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Send className="w-3.5 h-3.5" />}
            Send
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Reviewer Decision Panel ───────────────────────────────────────────────────

const DECISION_OPTIONS = [
  {
    value: 'approve',
    label: 'Approve',
    description: 'The submission meets the required standards.',
    color: 'border-green-300 bg-green-50 text-green-800',
    selectedColor: 'border-green-500 bg-green-100 ring-2 ring-green-400',
    icon: ThumbsUp,
  },
  {
    value: 'revise',
    label: 'Request Revision',
    description: 'Changes are needed before approval.',
    color: 'border-orange-300 bg-orange-50 text-orange-800',
    selectedColor: 'border-orange-500 bg-orange-100 ring-2 ring-orange-400',
    icon: RotateCcw,
  },
  {
    value: 'reject',
    label: 'Reject',
    description: 'The submission does not meet requirements.',
    color: 'border-red-300 bg-red-50 text-red-800',
    selectedColor: 'border-red-500 bg-red-100 ring-2 ring-red-400',
    icon: ThumbsDown,
  },
]

function ReviewerDecisionPanel({
  submissionId,
  submissionStatus,
}: {
  submissionId: string
  submissionStatus: SubmissionStatus
}) {
  const user = useAuthStore((s) => s.user)
  const qc = useQueryClient()
  const toast = useToastHelpers()
  const [selectedDecision, setSelectedDecision] = useState<string | null>(null)
  const [comments, setComments] = useState('')
  const [showConfirm, setShowConfirm] = useState(false)
  const [submitError, setSubmitError] = useState('')

  // Extension request state
  const [showExtension, setShowExtension] = useState(false)
  const [extReason, setExtReason] = useState('')
  const [extDays, setExtDays] = useState(7)

  // Conflict of interest state
  const [showConflict, setShowConflict] = useState(false)
  const [conflictReason, setConflictReason] = useState('')

  const { data: reviewersData } = useQuery<{ data: SubmissionReviewer[] }>({
    queryKey: ['submission-reviewers', submissionId],
    queryFn: () => api.get(`/submissions/${submissionId}/reviewers`).then(r => r.data),
    enabled: !!user,
  })

  const myAssignment = reviewersData?.data?.find(r => r.user_id === user?.id) ?? null

  const decisionMutation = useMutation({
    mutationFn: () =>
      api.patch(`/submissions/${submissionId}/reviewers/${myAssignment!.id}`, {
        decision: selectedDecision,
        comments: comments.trim() || null,
      }),
    onSuccess: () => {
      setShowConfirm(false)
      qc.invalidateQueries({ queryKey: ['submission', submissionId] })
      qc.invalidateQueries({ queryKey: ['submission-reviewers', submissionId] })
      qc.invalidateQueries({ queryKey: ['review-progress', submissionId] })
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      qc.invalidateQueries({ queryKey: ['dashboard-stats'] })
      toast.success('Decision submitted.', 'Your review has been recorded.')
    },
    onError: (e: any) => {
      const msg = e?.response?.data?.message ?? 'Failed to submit decision.'
      setSubmitError(msg)
      setShowConfirm(false)
      toast.error('Decision failed.', msg)
    },
  })

  const extensionMutation = useMutation({
    mutationFn: () =>
      api.post(`/submissions/${submissionId}/reviewers/${myAssignment!.id}/request-extension`, {
        reason: extReason,
        requested_days: extDays,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission-reviewers', submissionId] })
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      toast.success('Extension requested', 'The coordinator will review your request.')
      setShowExtension(false)
      setExtReason('')
      setExtDays(7)
    },
    onError: (e: any) => toast.error('Failed', e?.response?.data?.message ?? 'Could not submit request.'),
  })

  const conflictMutation = useMutation({
    mutationFn: () =>
      api.post(`/submissions/${submissionId}/reviewers/${myAssignment!.id}/flag-conflict`, {
        reason: conflictReason,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission-reviewers', submissionId] })
      qc.invalidateQueries({ queryKey: ['my-reviews'] })
      toast.success('Conflict declared', 'The coordinator has been notified to reassign a reviewer.')
      setShowConflict(false)
      setConflictReason('')
    },
    onError: (e: any) => toast.error('Failed', e?.response?.data?.message ?? 'Could not flag conflict.'),
  })

  // Only show when user is an assigned reviewer and hasn't decided yet
  if (!myAssignment || myAssignment.decision !== null) return null
  if (submissionStatus !== 'IN_REVIEW') return null
  if (myAssignment.status === 'completed' && myAssignment.decision) return null

  // If reviewer already flagged conflict, show a notice instead
  if (myAssignment.conflict_flagged) {
    return (
      <div className="mb-5 bg-red-50 rounded-xl border-2 border-red-200 px-5 py-4 flex items-start gap-3">
        <Ban className="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
        <div>
          <p className="text-sm font-semibold text-red-800">Conflict of Interest Declared</p>
          <p className="text-sm text-red-700 mt-0.5">
            You have declared a conflict of interest for this submission. The coordinator has been notified and will reassign a reviewer.
          </p>
          {myAssignment.conflict_reason && (
            <p className="text-xs text-red-600 mt-1 italic">"{myAssignment.conflict_reason}"</p>
          )}
        </div>
      </div>
    )
  }

  const selectedOption = DECISION_OPTIONS.find(o => o.value === selectedDecision)
  const hasPendingExtension = myAssignment.extension_status === 'pending'
  const canRequestExtension = !hasPendingExtension && myAssignment.extension_status !== 'approved'

  return (
    <>
    <div className="mb-5 bg-white rounded-xl border-2 border-blue-200 shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3.5 bg-blue-50 border-b border-blue-100">
        <Gavel className="w-4 h-4 text-blue-600" />
        <div>
          <p className="text-sm font-semibold text-blue-900">Your Review Decision</p>
          {myAssignment.stage && (
            <p className="text-xs text-blue-600 mt-0.5">
              Stage: {myAssignment.stage.name} · {myAssignment.stage.stage_role_label}
            </p>
          )}
        </div>
        {myAssignment.due_at && (
          <span className="ml-auto text-xs text-blue-500">
            Due {new Date(myAssignment.due_at).toLocaleDateString()}
          </span>
        )}
      </div>

      <div className="p-5 space-y-4">
        {/* Extension pending notice */}
        {hasPendingExtension && (
          <div className="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2.5">
            <CalendarDays className="w-4 h-4 text-blue-500 shrink-0" />
            <p className="text-xs text-blue-700">
              <strong>Extension request pending</strong> — awaiting coordinator approval (+{myAssignment.extension_requested_days} days requested).
            </p>
          </div>
        )}

        {/* Decision options */}
        <div>
          <p className="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Select your decision</p>
          <div className="grid grid-cols-3 gap-2">
            {DECISION_OPTIONS.map(opt => {
              const Icon = opt.icon
              const isSelected = selectedDecision === opt.value
              return (
                <button
                  key={opt.value}
                  onClick={() => setSelectedDecision(opt.value)}
                  className={`flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 transition-all text-center ${isSelected ? opt.selectedColor : 'border-gray-200 bg-white hover:border-gray-300'}`}
                >
                  <Icon className={`w-5 h-5 ${isSelected ? '' : 'text-gray-400'}`} />
                  <span className={`text-xs font-semibold ${isSelected ? '' : 'text-gray-600'}`}>{opt.label}</span>
                </button>
              )
            })}
          </div>
        </div>

        {/* Comments */}
        <div>
          <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">
            Comments <span className="text-gray-400 normal-case font-normal">(optional)</span>
          </label>
          <textarea
            className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"
            rows={4}
            placeholder="Provide detailed feedback to the author…"
            value={comments}
            onChange={e => setComments(e.target.value)}
            maxLength={10000}
          />
          <p className="text-xs text-gray-400 mt-1">{comments.length}/10000</p>
        </div>

        {submitError && (
          <p className="text-sm text-red-600">{submitError}</p>
        )}

        {/* Submit + secondary actions */}
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <button
            onClick={() => setShowConfirm(true)}
            disabled={!selectedDecision}
            className={`flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg disabled:opacity-40 disabled:cursor-not-allowed transition-colors ${
              selectedDecision === 'approve'
                ? 'bg-green-600 hover:bg-green-700 text-white'
                : selectedDecision === 'reject'
                ? 'bg-red-600 hover:bg-red-700 text-white'
                : selectedDecision === 'revise'
                ? 'bg-orange-500 hover:bg-orange-600 text-white'
                : 'bg-blue-600 hover:bg-blue-700 text-white'
            }`}
          >
            <Gavel className="w-4 h-4" />
            Submit Decision
          </button>

          {/* Extension & Conflict buttons */}
          <div className="flex items-center gap-2 ml-auto">
            {canRequestExtension && (
              <button
                onClick={() => setShowExtension(true)}
                className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
                title="Request a deadline extension"
              >
                <CalendarDays className="w-3.5 h-3.5" />
                Request Extension
              </button>
            )}
            <button
              onClick={() => setShowConflict(true)}
              className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
              title="Declare a conflict of interest"
            >
              <Ban className="w-3.5 h-3.5" />
              Declare Conflict
            </button>
          </div>
        </div>
      </div>

      {/* Confirm dialog */}
      {showConfirm && selectedOption && (
        <ConfirmModal
          title={`Confirm: ${selectedOption.label}`}
          message={`${selectedOption.description} This decision is permanent and cannot be changed after submission.`}
          confirmLabel={`Yes, ${selectedOption.label}`}
          confirmClass={
            selectedDecision === 'approve'
              ? 'bg-green-600 hover:bg-green-700'
              : selectedDecision === 'reject'
              ? 'bg-red-600 hover:bg-red-700'
              : 'bg-orange-500 hover:bg-orange-600'
          }
          onConfirm={() => decisionMutation.mutate()}
          onCancel={() => setShowConfirm(false)}
          loading={decisionMutation.isPending}
        />
      )}
    </div>

    {/* Extension Request Modal */}
    {showExtension && (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <CalendarDays className="w-5 h-5 text-blue-500" />
              <h3 className="text-base font-semibold text-gray-900">Request Deadline Extension</h3>
            </div>
            <button onClick={() => setShowExtension(false)} className="text-gray-400 hover:text-gray-600"><X className="w-4 h-4" /></button>
          </div>
          {myAssignment.due_at && (
            <p className="text-sm text-gray-500 mb-4">Current due date: <strong>{new Date(myAssignment.due_at).toLocaleDateString()}</strong></p>
          )}
          <div className="space-y-4">
            <div>
              <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">Reason for extension</label>
              <textarea
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"
                rows={3}
                placeholder="Explain why you need more time…"
                value={extReason}
                onChange={e => setExtReason(e.target.value)}
                maxLength={2000}
              />
            </div>
            <div>
              <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">Additional days needed</label>
              <input
                type="number"
                min={1}
                max={90}
                value={extDays}
                onChange={e => setExtDays(Number(e.target.value))}
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              />
            </div>
          </div>
          <div className="flex gap-3 justify-end mt-6">
            <button onClick={() => setShowExtension(false)} className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
            <button
              onClick={() => extensionMutation.mutate()}
              disabled={!extReason.trim() || extensionMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
            >
              {extensionMutation.isPending && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
              Submit Request
            </button>
          </div>
        </div>
      </div>
    )}

    {/* Conflict of Interest Modal */}
    {showConflict && (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Ban className="w-5 h-5 text-red-500" />
              <h3 className="text-base font-semibold text-gray-900">Declare Conflict of Interest</h3>
            </div>
            <button onClick={() => setShowConflict(false)} className="text-gray-400 hover:text-gray-600"><X className="w-4 h-4" /></button>
          </div>
          <div className="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <p className="text-sm text-red-800">
              Declaring a conflict of interest will notify the coordinator immediately. They will decide whether to keep you or assign a replacement reviewer.
            </p>
          </div>
          <div>
            <label className="text-xs font-semibold text-gray-600 mb-1 block uppercase tracking-wide">Reason for conflict</label>
            <textarea
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
              rows={3}
              placeholder="Describe the conflict (e.g., personal relationship, prior collaboration)…"
              value={conflictReason}
              onChange={e => setConflictReason(e.target.value)}
              maxLength={2000}
            />
          </div>
          <div className="flex gap-3 justify-end mt-6">
            <button onClick={() => setShowConflict(false)} className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
            <button
              onClick={() => conflictMutation.mutate()}
              disabled={!conflictReason.trim() || conflictMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
            >
              {conflictMutation.isPending && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
              Declare Conflict
            </button>
          </div>
        </div>
      </div>
    )}
    </>
  )
}

export default function SubmissionDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const user = useAuthStore((s) => s.user)
  const qc = useQueryClient()
  const [tab, setTab] = useState<Tab>('overview')
  const [editing, setEditing] = useState(false)
  const [editTitle, setEditTitle] = useState('')
  const [editAbstract, setEditAbstract] = useState('')
  const [editError, setEditError] = useState('')
  const [showWithdrawConfirm, setShowWithdrawConfirm] = useState(false)
  const reviewersRef = useRef<HTMLDivElement>(null)

  const isAdmin = user?.roles?.some((r: string) => ['admin', 'coordinator'].includes(r))

  const { data, isLoading, isError } = useQuery<{ data: Submission }>({
    queryKey: ['submission', id],
    queryFn: () => api.get(`/submissions/${id}`).then((r) => r.data),
    enabled: !!id,
  })

  const sub = data?.data

  // Fetch reviewer assignments to determine if the current user is the gatekeeper or assigned reviewer
  const { data: myReviewerData } = useQuery<{ data: SubmissionReviewer[] }>({
    queryKey: ['submission-reviewers', id],
    queryFn: () => api.get(`/submissions/${id}/reviewers`).then(r => r.data),
    enabled: !!id && !!user,
  })
  const myAssignmentMain = myReviewerData?.data?.find(r => r.user_id === user?.id) ?? null
  const isGatekeeper = !!myAssignmentMain?.stage?.is_gatekeeper
  // True when the current user has an active (non-declined) reviewer assignment,
  // regardless of whether they also hold admin/coordinator roles.
  const isAssignedReviewer = !!myAssignmentMain && myAssignmentMain.status !== 'declined'
  const isGatedReview = !!sub?.submission_type?.is_gated_review

  const pendingGatekeeperStage = (sub?.status === 'PENDING_RELEASE' && sub?.metadata)
    ? {
        name:    (sub.metadata['pending_gatekeeper_stage_name']    as string | null) ?? null,
        outcome: (sub.metadata['pending_gatekeeper_stage_outcome'] as string | null) ?? null,
      }
    : null

  const toast = useToastHelpers()

  const updateMutation = useMutation({
    mutationFn: (payload: { title?: string; abstract?: string }) =>
      api.patch(`/submissions/${id}`, payload),
    onSuccess: (r) => {
      qc.setQueryData(['submission', id], (old: { data: Submission } | undefined) =>
        old ? { data: { ...old.data, ...r.data.data } } : old,
      )
      setEditing(false)
      toast.success('Submission updated.')
    },
    onError: () => { setEditError('Update failed.'); toast.error('Update failed.') },
  })

  const submitMutation = useMutation({
    mutationFn: () => api.post(`/submissions/${id}/submit`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission', id] })
      qc.invalidateQueries({ queryKey: ['submissions'] })
      toast.success('Submission submitted successfully.', 'It will be assigned for review shortly.')
    },
    onError: (e: any) => toast.error('Submit failed.', e?.response?.data?.message),
  })

  const withdrawMutation = useMutation({
    mutationFn: () => api.post(`/submissions/${id}/withdraw`),
    onSuccess: () => {
      setShowWithdrawConfirm(false)
      qc.invalidateQueries({ queryKey: ['submission', id] })
      qc.invalidateQueries({ queryKey: ['submissions'] })
      toast.success('Submission withdrawn.')
    },
    onError: (e: any) => toast.error('Withdraw failed.', e?.response?.data?.message),
  })

  const advanceReviewMutation = useMutation({
    mutationFn: () => api.post(`/submissions/${id}/advance-review`),
    onSuccess: (r) => {
      qc.setQueryData(['submission', id], (old: { data: Submission } | undefined) =>
        old ? { data: { ...old.data, ...r.data.data } } : old,
      )
      qc.invalidateQueries({ queryKey: ['submissions'] })
      qc.invalidateQueries({ queryKey: ['review-progress', id] })
      toast.success('Review advanced to next stage.')
    },
    onError: (e: any) => toast.error('Advance failed.', e?.response?.data?.message),
  })

  const startEdit = () => {
    if (!sub) return
    setEditTitle(sub.title)
    setEditAbstract(sub.abstract ?? '')
    setEditError('')
    setEditing(true)
  }

  if (isLoading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
      </div>
    )
  }

  if (isError || !sub) {
    return (
      <div className="flex flex-col items-center py-20 text-center">
        <XCircle className="w-10 h-10 text-red-300 mb-3" />
        <p className="text-gray-500">Submission not found or access denied.</p>
        <button onClick={() => navigate('/submissions')} className="mt-3 text-blue-600 text-sm hover:underline">
          Back to submissions
        </button>
      </div>
    )
  }

  const canEdit    = (sub.status === 'DRAFT' || sub.status === 'REVISION_REQUIRED') && !sub.is_locked
  const canSubmit  = (sub.status === 'DRAFT' || sub.status === 'REVISION_REQUIRED') && !sub.is_locked && sub.versions.length > 0
  const canWithdraw = (sub.status === 'DRAFT' || sub.status === 'SUBMITTED' || sub.status === 'RESUBMITTED' || sub.status === 'AWAITING_REVIEWERS') && !sub.is_locked

  return (
    <div className="max-w-3xl">
      {/* Back */}
      <div className="flex items-center justify-between mb-5">
        <button
          onClick={() => navigate('/submissions')}
          className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700"
        >
          <ChevronLeft className="w-4 h-4" />
          Back to submissions
        </button>
        <button
          onClick={() => window.print()}
          className="no-print flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          title="Print / Save as PDF"
        >
          <Printer className="w-4 h-4" />
          Print / PDF
        </button>
      </div>

      {/* Header card */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
        <div className="flex items-start justify-between gap-4 mb-4">
          {editing ? (
            <input
              className="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-lg font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={editTitle}
              onChange={(e) => setEditTitle(e.target.value)}
              maxLength={500}
            />
          ) : (
            <h1 className="text-xl font-bold text-gray-900 flex-1">{sub.title}</h1>
          )}
          <div className="flex items-center gap-2 flex-shrink-0">
            <StatusBadge status={sub.status} />
            {canEdit && !editing && (
              <button
                onClick={startEdit}
                className="flex items-center gap-1 px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50"
              >
                <Pencil className="w-3 h-3" /> Edit
              </button>
            )}
          </div>
        </div>

        {/* Meta grid */}
        <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm mb-4">
          <div className="flex gap-2">
            <span className="text-gray-500 w-24">Type</span>
            <span className="font-medium text-gray-800">{sub.submission_type?.label ?? '—'}</span>
          </div>
          <div className="flex gap-2">
            <span className="text-gray-500 w-24">Program</span>
            <span className="font-medium text-gray-800">{sub.program?.name ?? '—'}</span>
          </div>
          <div className="flex gap-2">
            <span className="text-gray-500 w-24">Version</span>
            <span className="font-medium text-gray-800">
              {sub.current_version > 0 ? `v${sub.current_version}` : 'Draft (unsubmitted)'}
            </span>
          </div>
          {isAdmin && (
            <div className="flex gap-2">
              <span className="text-gray-500 w-24">Submitter</span>
              <span className="font-medium text-gray-800">{sub.submitter.name}</span>
            </div>
          )}
          <div className="flex gap-2">
            <span className="text-gray-500 w-24">Created</span>
            <span className="text-gray-600">{new Date(sub.created_at).toLocaleDateString()}</span>
          </div>
          <div className="flex gap-2">
            <span className="text-gray-500 w-24">Updated</span>
            <span className="text-gray-600">{new Date(sub.updated_at).toLocaleDateString()}</span>
          </div>
        </div>

        {/* Abstract */}
        {editing ? (
          <div className="mb-4">
            <label className="text-xs font-medium text-gray-600 mb-1 block">Abstract</label>
            <textarea
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              rows={4}
              value={editAbstract}
              onChange={(e) => setEditAbstract(e.target.value)}
              maxLength={10000}
            />
            {editError && <p className="text-xs text-red-600 mt-1">{editError}</p>}
          </div>
        ) : sub.abstract ? (
          <div>
            <p className="text-xs font-medium text-gray-500 mb-1">Abstract</p>
            <p className="text-sm text-gray-700 leading-relaxed">{sub.abstract}</p>
          </div>
        ) : null}

        {/* Edit actions */}
        {editing && (
          <div className="flex gap-2 mt-2">
            <button
              onClick={() => updateMutation.mutate({ title: editTitle.trim(), abstract: editAbstract.trim() || undefined })}
              disabled={updateMutation.isPending}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {updateMutation.isPending ? <Loader2 className="w-3 h-3 animate-spin" /> : <Check className="w-3 h-3" />}
              Save
            </button>
            <button
              onClick={() => setEditing(false)}
              className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-sm rounded-lg text-gray-600 hover:bg-gray-50"
            >
              <X className="w-3 h-3" /> Cancel
            </button>
          </div>
        )}
      </div>

      {/* Status banners */}
      {/* Admin: accept submitted submission for review */}
      {sub.status === 'SUBMITTED' && isAdmin && (
        <div className="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
          <CheckCircle2 className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-semibold text-blue-800">Submission Ready for Review</p>
            <p className="text-sm text-blue-700 mt-0.5">
              Accept this submission to begin assigning reviewers to each stage.
            </p>
            <button
              onClick={() => advanceReviewMutation.mutate()}
              disabled={advanceReviewMutation.isPending}
              className="mt-2 flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {advanceReviewMutation.isPending
                ? <Loader2 className="w-3 h-3 animate-spin" />
                : <CheckCircle2 className="w-3 h-3" />}
              Accept for Review
            </button>
          </div>
        </div>
      )}

      {/* Revision resubmitted — shown to submitter and reviewers */}
      {sub.status === 'RESUBMITTED' && !isAdmin && (
        <div className="mb-5 p-4 bg-indigo-50 border border-indigo-200 rounded-xl flex items-start gap-3">
          <CheckCircle2 className="w-5 h-5 text-indigo-600 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-semibold text-indigo-800">Revision Submitted — Awaiting Reviewer Assignment</p>
            <p className="text-sm text-indigo-700 mt-0.5">
              Your revised submission has been received and is being processed. A coordinator will assign reviewers shortly.
            </p>
          </div>
        </div>
      )}

      {/* Admin: resubmitted revision needs reviewer assignment */}
      {sub.status === 'RESUBMITTED' && isAdmin && (
        <div className="mb-5 p-4 bg-indigo-50 border border-indigo-200 rounded-xl flex items-start gap-3">
          <RotateCcw className="w-5 h-5 text-indigo-600 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-semibold text-indigo-800">Revised Submission — Assign Reviewers</p>
            <p className="text-sm text-indigo-700 mt-0.5">
              The submitter has uploaded a revision. Assign reviewers to the stages below, then start the review.
            </p>
            <div className="flex gap-2 mt-2 flex-wrap">
              <button
                onClick={() => {
                  setTab('overview')
                  setTimeout(() => reviewersRef.current?.scrollIntoView({ behavior: 'smooth' }), 50)
                }}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700"
              >
                <UserPlus className="w-3 h-3" />
                Assign Reviewers
              </button>
              <button
                onClick={() => advanceReviewMutation.mutate()}
                disabled={advanceReviewMutation.isPending}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {advanceReviewMutation.isPending
                  ? <Loader2 className="w-3 h-3 animate-spin" />
                  : <CheckCircle2 className="w-3 h-3" />}
                Start Review
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Awaiting reviewers banner */}
      {sub.status === 'AWAITING_REVIEWERS' && (
        <div className="mb-5 p-4 bg-purple-50 border border-purple-200 rounded-xl flex items-start gap-3">
          <Users className="w-5 h-5 text-purple-600 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            {isAdmin ? (
              <>
                <p className="text-sm font-semibold text-purple-800">Awaiting Reviewer Assignment</p>
                <p className="text-sm text-purple-700 mt-0.5">
                  Assign reviewers to the stages in the Overview tab, then start the review.
                </p>
                <div className="flex gap-2 mt-2 flex-wrap">
                  <button
                    onClick={() => {
                      setTab('overview')
                      setTimeout(() => reviewersRef.current?.scrollIntoView({ behavior: 'smooth' }), 50)
                    }}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 text-white text-xs font-semibold rounded-lg hover:bg-purple-700"
                  >
                    <UserPlus className="w-3 h-3" />
                    Assign Reviewers
                  </button>
                  <button
                    onClick={() => advanceReviewMutation.mutate()}
                    disabled={advanceReviewMutation.isPending}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
                  >
                    {advanceReviewMutation.isPending
                      ? <Loader2 className="w-3 h-3 animate-spin" />
                      : <CheckCircle2 className="w-3 h-3" />}
                    Start Review
                  </button>
                </div>
              </>
            ) : (
              <>
                <p className="text-sm font-semibold text-purple-800">Awaiting Reviewer Assignment</p>
                <p className="text-sm text-purple-700 mt-0.5">
                  Your submission has been accepted for review. Reviewers will be assigned shortly.
                </p>
              </>
            )}
          </div>
        </div>
      )}

      {sub.status === 'REVISION_REQUIRED' && (
        <div className="mb-5 p-4 bg-orange-50 border border-orange-200 rounded-xl flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-semibold text-orange-800">Revision Required</p>
            <p className="text-sm text-orange-700 mt-0.5">
              Reviewers have requested changes. Upload a revised document and re-submit.{' '}
              <button onClick={() => setTab('feedback')} className="underline font-medium">View feedback →</button>
            </p>
          </div>
        </div>
      )}

      {(sub.status === 'ACCEPTED' || sub.status === 'CONDITIONALLY_ACCEPTED') && (
        <div className="mb-5 p-4 bg-green-50 border border-green-200 rounded-xl flex items-start gap-3">
          <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
          <div>
            <p className="text-sm font-semibold text-green-800">
              {sub.status === 'ACCEPTED' ? 'Submission Accepted' : 'Conditionally Accepted'}
            </p>
            {sub.status === 'CONDITIONALLY_ACCEPTED' && (
              <p className="text-sm text-green-700 mt-0.5">
                Please review the conditions in the{' '}
                <button onClick={() => setTab('feedback')} className="underline font-medium">Feedback tab</button>.
              </p>
            )}
          </div>
        </div>
      )}

      {sub.status === 'REJECTED' && (
        <div className="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3">
          <CircleAlert className="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-semibold text-red-800">Submission Rejected</p>
            <p className="text-sm text-red-700 mt-0.5">
              Your submission was not approved.{' '}
              <button onClick={() => setTab('feedback')} className="underline font-medium">View feedback and appeal options →</button>
            </p>
          </div>
        </div>
      )}

      {/* Reviewer decision panel — visible to any assigned reviewer, including multi-role users */}
      {isAssignedReviewer && sub.status === 'IN_REVIEW' && (
        <ReviewerDecisionPanel
          submissionId={sub.id}
          submissionStatus={sub.status}
        />
      )}

      {/* Tab bar */}
      <div className="flex border-b border-gray-200 mb-5">
        {TABS.map(t => {
          const Icon = t.icon
          return (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors ${
                tab === t.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              <Icon className="w-3.5 h-3.5" />
              {t.label}
            </button>
          )
        })}
      </div>

      {/* Tab content */}
      {tab === 'overview' && (
        <div>
          <AuthorsPanel submissionId={sub.id} canEdit={canEdit} />
          <ReviewProgressPanel
            submissionId={sub.id}
            isAdmin={!!isAdmin}
            onAssignClick={isAdmin ? () => {
              setTimeout(() => reviewersRef.current?.scrollIntoView({ behavior: 'smooth' }), 50)
            } : undefined}
          />
          {isAdmin && (
            <SimilarityCheckPanel submissionId={sub.id} />
          )}
          {isAdmin && sub.submission_type && (
            <div ref={reviewersRef}>
              <ReviewersPanel submissionId={sub.id} submissionTypeId={sub.submission_type.id} />
            </div>
          )}
        </div>
      )}

      {tab === 'documents' && (
        <DocumentsTab sub={sub} canEdit={canEdit} />
      )}

      {tab === 'feedback' && (
        <FeedbackTab
          submissionId={sub.id}
          submissionStatus={sub.status}
          isAdmin={!!isAdmin}
          isGatedReview={isGatedReview}
          isGatekeeper={isGatekeeper}
          pendingGatekeeperStage={pendingGatekeeperStage}
        />
      )}

      {tab === 'activity' && (
        <ActivityTab submissionId={sub.id} />
      )}

      {tab === 'communication' && (
        <CommunicationTab submissionId={sub.id} />
      )}

      {/* Action bar */}
      {(canSubmit || canWithdraw) && (
        <div className="flex items-center gap-3 mt-5">
          {canSubmit && (
            <button
              onClick={() => submitMutation.mutate()}
              disabled={submitMutation.isPending}
              className="flex items-center gap-2 px-5 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              {submitMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
              Submit for Review
            </button>
          )}
          {canWithdraw && (
            <button
              onClick={() => setShowWithdrawConfirm(true)}
              className="flex items-center gap-2 px-4 py-2.5 border border-red-200 text-red-600 text-sm rounded-lg hover:bg-red-50"
            >
              <StopCircle className="w-4 h-4" />
              Withdraw
            </button>
          )}
        </div>
      )}

      {/* Withdraw confirm modal */}
      {showWithdrawConfirm && (
        <ConfirmModal
          title="Withdraw Submission"
          message="Are you sure you want to withdraw this submission? The coordinator will be notified and the submission will be locked."
          confirmLabel="Yes, Withdraw"
          confirmClass="bg-red-600 hover:bg-red-700"
          onConfirm={() => withdrawMutation.mutate()}
          onCancel={() => setShowWithdrawConfirm(false)}
          loading={withdrawMutation.isPending}
        />
      )}
    </div>
  )
}
