import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle, Clock, RefreshCw, AlertTriangle, ChevronRight, X } from 'lucide-react'
import api from '../lib/axios'
import { useToastHelpers } from '../lib/toast'

// ── Types ────────────────────────────────────────────────────────────────────

interface GatedSubmission {
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

interface GatedList {
  pending: GatedSubmission[]
  recently_decided: GatedSubmission[]
}

interface ReviewStage {
  stage_id: string
  stage_name: string
  stage_role: string
  order: number
  reviewers: {
    reviewer_id: string
    reviewer_name: string
    decision: string | null
    decision_at: string | null
    comments: string | null
    status: string
  }[]
}

interface GatedDetail {
  id: string
  title: string
  abstract: string | null
  status: string
  current_version: number
  submitter: { id: string; name: string; email: string; organization: string | null } | null
  submission_type: { id: string; label: string; is_gated_review: boolean } | null
  program: { id: string; name: string } | null
  versions: { version_number: number; filename: string; submitted_at: string }[]
  review_stages: ReviewStage[]
  gated_release: {
    id: string
    decision: string
    feedback: string | null
    released_by: string | null
    released_at: string
  } | null
  pending_gatekeeper_stage: {
    id: string
    name: string | null
    outcome: string | null   // 'FAILED' | 'REVISION_REQUIRED'
  } | null
}

// ── Status badge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    PENDING_RELEASE:         'bg-amber-100 text-amber-800',
    ACCEPTED:                'bg-green-100 text-green-800',
    CONDITIONALLY_ACCEPTED:  'bg-blue-100  text-blue-800',
    REJECTED:                'bg-red-100   text-red-800',
    REVISION_REQUIRED:       'bg-orange-100 text-orange-800',
    IN_REVIEW:               'bg-indigo-100 text-indigo-800',
    STAGE_RECHECK:           'bg-purple-100 text-purple-800',
  }
  const label: Record<string, string> = {
    PENDING_RELEASE:         'Pending Gatekeeper',
    ACCEPTED:                'Accepted',
    CONDITIONALLY_ACCEPTED:  'Cond. Accepted',
    REJECTED:                'Rejected',
    REVISION_REQUIRED:       'Revision Required',
    IN_REVIEW:               'In Review',
    STAGE_RECHECK:           'Stage Recheck',
  }
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${styles[status] ?? 'bg-gray-100 text-gray-700'}`}>
      {label[status] ?? status}
    </span>
  )
}

// ── Decision form modal ───────────────────────────────────────────────────────

function ReleaseModal({
  submission,
  onClose,
}: {
  submission: GatedDetail
  onClose: () => void
}) {
  const qc = useQueryClient()
  const toast = useToastHelpers()
  const [decision, setDecision]   = useState<string>('REVISION_REQUIRED')
  const [feedback, setFeedback]   = useState('')
  const [reason,   setReason]     = useState('')
  const [mode,     setMode]       = useState<'release' | 'recheck'>('release')

  const stageName    = submission.pending_gatekeeper_stage?.name ?? null
  const stageOutcome = submission.pending_gatekeeper_stage?.outcome ?? null

  const releaseMut = useMutation({
    mutationFn: (body: object) => api.post('/admin/gated-reviews', body),
    onSuccess: () => {
      toast.success('Release decision issued.')
      qc.invalidateQueries({ queryKey: ['gated-reviews'] })
      onClose()
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Failed to issue release.'),
  })

  const recheckMut = useMutation({
    mutationFn: (body: object) => api.post(`/admin/gated-reviews/${submission.id}/recheck`, body),
    onSuccess: () => {
      toast.success('Recheck requested. Submission returned to in-review.')
      qc.invalidateQueries({ queryKey: ['gated-reviews'] })
      onClose()
    },
    onError: (e: any) => toast.error(e?.response?.data?.message ?? 'Failed to request recheck.'),
  })

  const handleSubmit = () => {
    if (mode === 'release') {
      releaseMut.mutate({ submission_id: submission.id, decision, feedback: feedback || undefined })
    } else {
      if (!reason.trim()) { toast.error('Reason is required.'); return }
      recheckMut.mutate({ reason })
    }
  }

  const isPending = releaseMut.isPending || recheckMut.isPending

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-lg">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b">
          <h2 className="text-lg font-semibold text-gray-900">Gated Release Decision</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
        </div>

        <div className="p-5 space-y-4">
          <p className="text-sm text-gray-500 truncate">
            <span className="font-medium text-gray-700">{submission.title}</span>
            {' '} — {submission.submitter?.name}
          </p>

          {/* Stage context banner */}
          {stageName && (
            <div className="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm">
              <p className="font-medium text-amber-800">
                Stage “{stageName}” requires your decision
              </p>
              <p className="text-amber-700 mt-0.5">
                Reviewers {stageOutcome === 'FAILED' ? 'rejected the submission' : 'requested changes'}.
                Review their comments below and choose an action.
              </p>
            </div>
          )}

          {/* Mode tabs */}
          <div className="flex gap-2">
            <button
              onClick={() => setMode('release')}
              className={`px-3 py-1.5 text-sm rounded-md font-medium ${mode === 'release' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
            >
              Issue Decision
            </button>
            <button
              onClick={() => setMode('recheck')}
              className={`px-3 py-1.5 text-sm rounded-md font-medium ${mode === 'recheck' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
            >
              Return Stage for Re-review
            </button>
          </div>

          {mode === 'release' ? (
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Decision</label>
                <select
                  value={decision}
                  onChange={e => setDecision(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="REVISION_REQUIRED">Request Revision from Submitter (agree with reviewers)</option>
                  <option value="REJECTED">Reject Submission (agree with reviewers)</option>
                  <option value="ACCEPTED">Accept Submission (override reviewers)</option>
                  <option value="CONDITIONALLY_ACCEPTED">Conditionally Accept (override reviewers)</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Feedback <span className="text-gray-400">(optional)</span></label>
                <textarea
                  value={feedback}
                  onChange={e => setFeedback(e.target.value)}
                  rows={4}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Provide feedback to the submitter…"
                />
              </div>
            </>
          ) : (
            <div className="space-y-3">
              {stageName && (
                <p className="text-sm text-gray-600">
                  This will clear reviewer decisions at stage “<span className="font-medium">{stageName}</span>”
                  {' '}and return the submission to that stage for re-review.
                </p>
              )}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Reason for re-review <span className="text-red-500">*</span></label>
                <textarea
                  value={reason}
                  onChange={e => setReason(e.target.value)}
                  rows={3}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Explain why the reviewers\u2019 decision should be reconsidered…"
                />
              </div>
            </div>
          )}
        </div>

        <div className="flex justify-end gap-3 px-5 pb-5">
          <button onClick={onClose} className="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={isPending}
            className="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          >
            {isPending ? 'Saving…' : mode === 'release' ? 'Issue Decision' : 'Return to Stage'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Detail panel ─────────────────────────────────────────────────────────────

function GatedDetailPanel({
  submissionId,
  onRelease,
}: {
  submissionId: string
  onRelease: (sub: GatedDetail) => void
}) {
  const { data, isLoading } = useQuery<GatedDetail>({
    queryKey: ['gated-detail', submissionId],
    queryFn: () => api.get(`/admin/gated-reviews/${submissionId}`).then(r => r.data),
  })

  if (isLoading) return (
    <div className="p-6 space-y-3">
      {[...Array(4)].map((_, i) => (
        <div key={i} className="h-4 bg-gray-200 rounded animate-pulse" />
      ))}
    </div>
  )

  if (!data) return <div className="p-6 text-sm text-gray-500">Not found.</div>

  const canRelease = data.status === 'PENDING_RELEASE'

  return (
    <div className="overflow-y-auto max-h-[calc(100vh-200px)] divide-y divide-gray-100">
      {/* Meta */}
      <div className="p-5">
        <h3 className="font-semibold text-gray-900 mb-1">{data.title}</h3>
        <p className="text-sm text-gray-500 mb-3">{data.submitter?.name} · {data.submission_type?.label}</p>
        <StatusBadge status={data.status} />
        {data.abstract && (
          <p className="mt-3 text-sm text-gray-600 line-clamp-3">{data.abstract}</p>
        )}
      </div>

      {/* Review stages */}
      <div className="p-5">
        <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Review Stages</h4>
        {data.review_stages.length === 0 && (
          <p className="text-sm text-gray-400">No reviewer assignments found.</p>
        )}
        {data.review_stages.map(stage => {
          const isTriggering = data.pending_gatekeeper_stage?.id === stage.stage_id
          return (
            <div
              key={stage.stage_id}
              className={`mb-4 rounded-lg p-3 ${
                isTriggering ? 'bg-amber-50 border border-amber-200' : 'bg-transparent'
              }`}
            >
              <div className="flex items-center gap-2 mb-2">
                <p className="text-sm font-medium text-gray-800">{stage.stage_name}</p>
                {isTriggering && (
                  <span className="text-xs font-medium px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">
                    {data.pending_gatekeeper_stage?.outcome === 'FAILED' ? 'Rejected' : 'Revision Requested'}
                  </span>
                )}
              </div>
              <div className="space-y-1.5">
                {stage.reviewers.map(r => (
                  <div key={r.reviewer_id} className="space-y-0.5">
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-gray-600">{r.reviewer_name}</span>
                      {r.decision ? (
                        <span className={`font-medium text-xs px-2 py-0.5 rounded ${
                          r.decision === 'approve' || r.decision === 'APPROVE' ? 'bg-green-50 text-green-700' :
                          r.decision === 'reject'  || r.decision === 'REJECT'  ? 'bg-red-50 text-red-700' :
                          'bg-amber-50 text-amber-700'
                        }`}>{r.decision}</span>
                      ) : (
                        <span className="text-gray-400 text-xs">Pending</span>
                      )}
                    </div>
                    {r.comments && (
                      <p className="text-xs text-gray-500 italic ml-0 pl-0 truncate" title={r.comments}>
                        “{r.comments}”
                      </p>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )
        })}
      </div>

      {/* Existing release */}
      {data.gated_release && (
        <div className="p-5 bg-gray-50">
          <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Gated Release</h4>
          <div className="text-sm space-y-1">
            <p><span className="font-medium">Decision:</span> {data.gated_release.decision}</p>
            <p><span className="font-medium">By:</span> {data.gated_release.released_by}</p>
            <p><span className="font-medium">At:</span> {new Date(data.gated_release.released_at).toLocaleString()}</p>
            {data.gated_release.feedback && (
              <p className="mt-2 text-gray-600 italic">"{data.gated_release.feedback}"</p>
            )}
          </div>
        </div>
      )}

      {/* Actions */}
      {canRelease && (
        <div className="p-5">
          <button
            onClick={() => onRelease(data)}
            className="w-full py-2.5 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 flex items-center justify-center gap-2"
          >
            <CheckCircle className="h-4 w-4" />
            {data.pending_gatekeeper_stage ? 'Make Gatekeeper Decision' : 'Issue Release Decision'}
          </button>
        </div>
      )}
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function GatedReviewsPage() {
  const [selected,       setSelected]       = useState<string | null>(null)
  const [releasingItem,  setReleasingItem]   = useState<GatedDetail | null>(null)

  const { data, isLoading, refetch } = useQuery<GatedList>({
    queryKey: ['gated-reviews'],
    queryFn: () => api.get('/admin/gated-reviews').then(r => r.data),
    refetchInterval: 30_000,
  })

  return (
    <div className="flex h-full min-h-0">
      {/* ── Left: list ── */}
      <div className="w-80 shrink-0 border-r border-gray-200 flex flex-col">
        <div className="p-4 border-b border-gray-200 flex items-center justify-between">
          <h1 className="text-lg font-semibold text-gray-900">Gated Reviews</h1>
          <button onClick={() => refetch()} className="p-1.5 rounded hover:bg-gray-100">
            <RefreshCw className="h-4 w-4 text-gray-500" />
          </button>
        </div>

        <div className="overflow-y-auto flex-1">
          {isLoading && (
            <div className="p-4 space-y-3">
              {[...Array(5)].map((_, i) => (
                <div key={i} className="h-16 bg-gray-100 rounded-lg animate-pulse" />
              ))}
            </div>
          )}

          {/* Pending release */}
          {(data?.pending?.length ?? 0) > 0 && (
            <div>
              <div className="px-4 pt-4 pb-1">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-1.5">
                  <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                  Awaiting Decision ({data!.pending.length})
                </p>
              </div>
              {data!.pending.map(s => (
                <SubmissionRow
                  key={s.id}
                  item={s}
                  selected={selected === s.id}
                  onClick={() => setSelected(s.id)}
                />
              ))}
            </div>
          )}

          {/* Recently decided */}
          {(data?.recently_decided?.length ?? 0) > 0 && (
            <div>
              <div className="px-4 pt-4 pb-1">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5 text-gray-400" />
                  Recently Decided
                </p>
              </div>
              {data!.recently_decided.map(s => (
                <SubmissionRow
                  key={s.id}
                  item={s}
                  selected={selected === s.id}
                  onClick={() => setSelected(s.id)}
                />
              ))}
            </div>
          )}

          {!isLoading && !data?.pending.length && !data?.recently_decided.length && (
            <div className="p-8 text-center text-sm text-gray-400">
              No gated submissions found.
            </div>
          )}
        </div>
      </div>

      {/* ── Right: detail ── */}
      <div className="flex-1 overflow-hidden">
        {selected ? (
          <GatedDetailPanel
            key={selected}
            submissionId={selected}
            onRelease={setReleasingItem}
          />
        ) : (
          <div className="flex items-center justify-center h-full text-sm text-gray-400">
            Select a submission to review
          </div>
        )}
      </div>

      {/* Release modal */}
      {releasingItem && (
        <ReleaseModal
          submission={releasingItem}
          onClose={() => setReleasingItem(null)}
        />
      )}
    </div>
  )
}

function SubmissionRow({
  item,
  selected,
  onClick,
}: {
  item: GatedSubmission
  selected: boolean
  onClick: () => void
}) {
  return (
    <button
      onClick={onClick}
      className={`w-full text-left px-4 py-3 hover:bg-gray-50 flex items-start gap-3 border-b border-gray-100 ${selected ? 'bg-indigo-50' : ''}`}
    >
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">{item.title}</p>
        <p className="text-xs text-gray-500 truncate mt-0.5">
          {item.submitter_name} · {item.submission_type}
        </p>
        {item.pending_gatekeeper_stage_name && (
          <p className="text-xs text-amber-600 truncate mt-0.5">
            Stage: {item.pending_gatekeeper_stage_name}
            {item.pending_gatekeeper_stage_outcome === 'FAILED' ? ' — Rejected' : ' — Revision'}
          </p>
        )}
        <div className="mt-1.5">
          <StatusBadge status={item.status} />
        </div>
      </div>
      <ChevronRight className="h-4 w-4 text-gray-400 shrink-0 mt-1" />
    </button>
  )
}
