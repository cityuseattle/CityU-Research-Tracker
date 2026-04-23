import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Plus, Pencil, Trash2, Search, RefreshCw, Loader2,
  CheckCircle2, XCircle, GitBranch, ChevronDown, ChevronUp,
  ArrowUp, ArrowDown,
} from 'lucide-react'
import api from '../lib/axios'
import type {
  WorkflowDefinition, WorkflowFormData, StageDefinition,
  ExecutionType, ApprovalStrategy,
} from '../types/admin'
import { DECISION_OPTIONS_DEFAULT } from '../types/admin'

// ── Toggle ────────────────────────────────────────────────────────────────────

function Toggle({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex items-center gap-2 cursor-pointer select-none">
      <button type="button" role="switch" aria-checked={checked} onClick={() => onChange(!checked)}
        className={`relative w-9 h-5 rounded-full transition-colors ${checked ? 'bg-blue-600' : 'bg-gray-200'}`}>
        <span className={`absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${checked ? 'translate-x-4' : ''}`} />
      </button>
      <span className="text-sm text-gray-700">{label}</span>
    </label>
  )
}

// ── Stage Editor ──────────────────────────────────────────────────────────────

const DEFAULT_STAGE: Omit<StageDefinition, 'id' | 'workflow_id'> = {
  name: '', order: 1, stage_role_label: 'reviewer',
  is_gatekeeper: false, execution_type: 'PARALLEL', approval_strategy: 'ALL',
  min_approvals: 1, is_anonymous: false, due_days: 7,
  decision_options: DECISION_OPTIONS_DEFAULT, auto_assignment: { strategy: 'MANUAL' },
}

function StageEditor({
  stages, onChange,
}: {
  stages: Omit<StageDefinition, 'id' | 'workflow_id'>[]
  onChange: (s: Omit<StageDefinition, 'id' | 'workflow_id'>[]) => void
}) {
  // Load custom roles for the role label datalist
  const { data: customRolesData } = useQuery<{ data: { id: string; name: string }[] }>({
    queryKey: ['custom-roles'],
    queryFn: () => api.get('/admin/custom-roles').then((r) => r.data),
    staleTime: 60_000,
  })
  const customRoles = customRolesData?.data ?? []
  const SYSTEM_ROLE_LABELS = ['reviewer', 'coordinator', 'admin', 'committee member', 'external reviewer']

  const add = () => onChange([...stages, { ...DEFAULT_STAGE, order: stages.length + 1 }])
  const remove = (i: number) => onChange(stages.filter((_, idx) => idx !== i).map((s, idx) => ({ ...s, order: idx + 1 })))
  const update = (i: number, patch: Partial<Omit<StageDefinition, 'id' | 'workflow_id'>>) =>
    onChange(stages.map((s, idx) => idx === i ? { ...s, ...patch } : s))
  const moveUp = (i: number) => {
    if (i === 0) return
    const arr = [...stages]
    ;[arr[i - 1], arr[i]] = [arr[i], arr[i - 1]]
    onChange(arr.map((s, idx) => ({ ...s, order: idx + 1 })))
  }
  const moveDown = (i: number) => {
    if (i === stages.length - 1) return
    const arr = [...stages]
    ;[arr[i], arr[i + 1]] = [arr[i + 1], arr[i]]
    onChange(arr.map((s, idx) => ({ ...s, order: idx + 1 })))
  }

  const gatekeeperCount = stages.filter((s) => s.is_gatekeeper).length

  return (
    <div>
      <div className="flex items-center justify-between mb-1">
        <h4 className="text-sm font-semibold text-gray-700">Stages</h4>
        <button type="button" onClick={add}
          className="flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-100">
          <Plus className="w-3 h-3" /> Add Stage
        </button>
      </div>

      {/* Stage concept help */}
      <div className="mb-3 rounded-lg bg-gray-100 px-3 py-2 space-y-1">
        <p className="text-xs text-gray-500">
          <span className="font-semibold text-gray-700">Gatekeeper:</span>{' '}
          One designated reviewer manages all submitter communication, consolidates feedback from other approvers, and makes the final advance-or-return decision. Only one stage can be the gatekeeper.
        </p>
        <p className="text-xs text-gray-500">
          <span className="font-semibold text-gray-700">Anonymous reviewers:</span>{' '}
          Reviewer identities are hidden from the submitter and from each other. Only admins and coordinators can see who reviewed.
        </p>
      </div>

      {/* Multi-gatekeeper warning */}
      {gatekeeperCount > 1 && (
        <div className="mb-3 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
          <span className="text-amber-500 mt-0.5 text-sm">⚠</span>
          <p className="text-xs text-amber-700 font-medium">
            Only one stage should be the gatekeeper. You currently have {gatekeeperCount} gatekeeper stages selected — please uncheck all but one.
          </p>
        </div>
      )}

      {stages.length === 0 && (
        <p className="text-xs text-gray-400 italic py-2">No stages yet. Add at least one stage.</p>
      )}
      <div className="space-y-3">
        {stages.map((s, i) => (
          <div key={i} className={`border rounded-xl p-3 bg-gray-50 ${s.is_gatekeeper && gatekeeperCount > 1 ? 'border-amber-300' : 'border-gray-200'}`}>
            <div className="flex items-center gap-2 mb-3">
              <span className="w-6 h-6 flex items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold flex-shrink-0">
                {i + 1}
              </span>
              <input
                className="flex-1 border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                placeholder="Stage name (e.g. Program Director Review)"
                value={s.name}
                onChange={(e) => update(i, { name: e.target.value })}
              />
              <button onClick={() => moveUp(i)} disabled={i === 0} className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"><ArrowUp className="w-3.5 h-3.5" /></button>
              <button onClick={() => moveDown(i)} disabled={i === stages.length - 1} className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"><ArrowDown className="w-3.5 h-3.5" /></button>
              <button onClick={() => remove(i)} className="p-1 text-gray-400 hover:text-red-500"><XCircle className="w-4 h-4" /></button>
            </div>
            <div className="grid grid-cols-2 gap-2 mb-2">
              <div>
                <label className="text-xs text-gray-500 mb-0.5 block">Role label</label>
                <input
                  className="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                  placeholder="e.g. reviewer, committee"
                  list={`role-options-${i}`}
                  value={s.stage_role_label}
                  onChange={(e) => update(i, { stage_role_label: e.target.value })}
                />
                <datalist id={`role-options-${i}`}>
                  {SYSTEM_ROLE_LABELS.map((r) => <option key={r} value={r} />)}
                  {customRoles.map((r) => <option key={r.id} value={r.name} />)}
                </datalist>
              </div>
              <div>
                <label className="text-xs text-gray-500 mb-0.5 block">Due days</label>
                <input type="number" min={1}
                  className="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                  value={s.due_days}
                  onChange={(e) => update(i, { due_days: Number(e.target.value) })}
                />
              </div>
              <div>
                <label className="text-xs text-gray-500 mb-0.5 block">Execution</label>
                <select
                  className="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                  value={s.execution_type}
                  onChange={(e) => update(i, { execution_type: e.target.value as ExecutionType })}
                >
                  <option value="PARALLEL">Parallel (all at once)</option>
                  <option value="SEQUENTIAL">Sequential (one by one)</option>
                </select>
              </div>
              <div>
                <label className="text-xs text-gray-500 mb-0.5 block">Approval strategy</label>
                <select
                  className="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                  value={s.approval_strategy}
                  onChange={(e) => update(i, { approval_strategy: e.target.value as ApprovalStrategy })}
                >
                  <option value="ALL">All reviewers must approve</option>
                  <option value="MAJORITY">Majority must approve</option>
                  <option value="ANY">Any single reviewer</option>
                </select>
              </div>
              <div>
                <label className="text-xs text-gray-500 mb-0.5 block">
                  Min. approvals required
                  <span className="ml-1 text-gray-400 font-normal">(single = 1, multiple &gt; 1)</span>
                </label>
                <input type="number" min={1}
                  className="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                  value={s.min_approvals}
                  onChange={(e) => update(i, { min_approvals: Math.max(1, Number(e.target.value)) })}
                />
              </div>
            </div>
            <div className="flex flex-wrap gap-3">
              <label className="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" checked={s.is_gatekeeper}
                  onChange={(e) => update(i, { is_gatekeeper: e.target.checked })} className="rounded" />
                <span className={s.is_gatekeeper && gatekeeperCount > 1 ? 'text-amber-700 font-medium' : ''}>
                  Gatekeeper stage
                </span>
              </label>
              <label className="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" checked={s.is_anonymous}
                  onChange={(e) => update(i, { is_anonymous: e.target.checked })} className="rounded" />
                Anonymous reviewers
              </label>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

// ── Workflow Form Modal ───────────────────────────────────────────────────────

function WorkflowModal({
  initial, onClose, onSave,
}: {
  initial?: WorkflowDefinition | null
  onClose: () => void
  onSave: (data: WorkflowFormData) => void
}) {
  const [form, setForm] = useState<WorkflowFormData>(
    initial
      ? {
          name: initial.name,
          revision_restart_policy: initial.revision_restart_policy,
          final_status_on_pass: initial.final_status_on_pass,
          is_active: initial.is_active,
          stages: initial.stages.map(({ id: _id, workflow_id: _wid, ...rest }) => rest),
        }
      : {
          name: '', revision_restart_policy: 'FULL_RESTART',
          final_status_on_pass: 'ACCEPTED', is_active: true, stages: [],
        },
  )

  const setField = <K extends keyof WorkflowFormData>(k: K, v: WorkflowFormData[K]) =>
    setForm((f) => ({ ...f, [k]: v }))

  const isValid = form.name.trim() && form.stages.length > 0
    && form.stages.every((s) => s.name.trim() && s.stage_role_label.trim())

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 className="text-base font-semibold text-gray-900">{initial ? 'Edit Workflow' : 'New Workflow'}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><XCircle className="w-5 h-5" /></button>
        </div>
        <div className="px-6 py-5 space-y-4">
          {/* Name */}
          <div>
            <label className="text-xs font-medium text-gray-600 mb-1 block">Workflow Name *</label>
            <input
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="e.g. Standard Dissertation Review"
              value={form.name}
              onChange={(e) => setField('name', e.target.value)}
            />
          </div>

          {/* Policy */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-gray-600 mb-1 block">On Revision Required</label>
              <select
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                value={form.revision_restart_policy}
                onChange={(e) => setField('revision_restart_policy', e.target.value as any)}
              >
                <option value="FULL_RESTART">Full restart from stage 1</option>
                <option value="RESUME_FROM_REVISION">Resume from revision stage</option>
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-gray-600 mb-1 block">Final status on pass</label>
              <select
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                value={form.final_status_on_pass}
                onChange={(e) => setField('final_status_on_pass', e.target.value as any)}
              >
                <option value="ACCEPTED">Accepted</option>
                <option value="CONDITIONALLY_ACCEPTED">Conditionally Accepted</option>
              </select>
            </div>
          </div>

          <Toggle checked={form.is_active} onChange={(v) => setField('is_active', v)} label="Active (used for new submissions of this category)" />

          {/* Stages */}
          <div className="pt-2 border-t border-gray-100">
            <StageEditor stages={form.stages} onChange={(s) => setField('stages', s)} />
          </div>
        </div>
        <div className="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
          <button onClick={onClose} className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
          <button
            onClick={() => isValid && onSave(form)}
            disabled={!isValid}
            className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {initial ? 'Save Changes' : 'Create Workflow'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Page ──────────────────────────────────────────────────────────────────────

interface WorkflowListResponse {
  data: WorkflowDefinition[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}

export default function WorkflowsPage() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [showModal, setShowModal] = useState(false)
  const [editing, setEditing] = useState<WorkflowDefinition | null>(null)
  const [expandedId, setExpandedId] = useState<string | null>(null)
  const [deleteConfirm, setDeleteConfirm] = useState<string | null>(null)

  const { data, isLoading, isError, refetch } = useQuery<WorkflowListResponse>({
    queryKey: ['admin-workflows', page, search],
    queryFn: () =>
      api.get('/admin/workflows', { params: { page, per_page: 20, search: search || undefined } })
        .then((r) => r.data),
  })

  const createMutation = useMutation({
    mutationFn: (body: WorkflowFormData) => api.post('/admin/workflows', body),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-workflows'] }); setShowModal(false) },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, body }: { id: string; body: WorkflowFormData }) =>
      api.patch(`/admin/workflows/${id}`, body),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-workflows'] }); setEditing(null) },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/workflows/${id}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-workflows'] }); setDeleteConfirm(null) },
  })

  const handleSave = (form: WorkflowFormData) => {
    if (editing) updateMutation.mutate({ id: editing.id, body: form })
    else createMutation.mutate(form)
  }

  const workflows = data?.data ?? []

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Workflows</h1>
          <p className="text-sm text-gray-500 mt-0.5">Define review stage sequences for each submission category</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => refetch()} className="p-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
            <RefreshCw className="w-4 h-4" />
          </button>
          <button
            onClick={() => setShowModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
          >
            <Plus className="w-4 h-4" /> New Workflow
          </button>
        </div>
      </div>

      <div className="relative mb-5 max-w-xs">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input
          className="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Search workflows…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1) }}
        />
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <div className="flex justify-center py-20"><Loader2 className="w-6 h-6 animate-spin text-gray-400" /></div>
        ) : isError ? (
          <div className="flex flex-col items-center py-20">
            <XCircle className="w-8 h-8 text-red-300 mb-2" />
            <button onClick={() => refetch()} className="mt-2 text-blue-600 text-sm hover:underline">Retry</button>
          </div>
        ) : workflows.length === 0 ? (
          <div className="flex flex-col items-center py-20">
            <GitBranch className="w-10 h-10 text-gray-200 mb-3" />
            <p className="text-gray-500 font-medium">No workflows yet.</p>
            <button onClick={() => setShowModal(true)} className="mt-3 flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
              <Plus className="w-4 h-4" /> Create First Workflow
            </button>
          </div>
        ) : (
          <>
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50">
                  <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Workflow</th>
                  <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Stages</th>
                  <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">On Revision</th>
                  <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Status</th>
                  <th className="px-4 py-3 w-20" />
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {workflows.map((w) => (
                  <>
                    <tr key={w.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <p className="text-sm font-semibold text-gray-900">{w.name}</p>
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-600">{w.stages?.length ?? 0} stage{w.stages?.length !== 1 ? 's' : ''}</td>
                      <td className="px-4 py-3">
                        <span className="text-xs text-gray-500">
                          {w.revision_restart_policy === 'FULL_RESTART' ? 'Full restart' : 'Resume'}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {w.is_active
                          ? <span className="flex items-center gap-1 text-xs text-green-700"><CheckCircle2 className="w-3 h-3" /> Active</span>
                          : <span className="flex items-center gap-1 text-xs text-gray-400"><XCircle className="w-3 h-3" /> Inactive</span>}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1 justify-end">
                          <button onClick={() => setExpandedId(expandedId === w.id ? null : w.id)}
                            className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded">
                            {expandedId === w.id ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                          </button>
                          <button onClick={() => setEditing(w)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded">
                            <Pencil className="w-4 h-4" />
                          </button>
                          <button onClick={() => setDeleteConfirm(w.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded">
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                    {expandedId === w.id && (
                      <tr key={`${w.id}-stages`} className="bg-gray-50">
                        <td colSpan={5} className="px-4 py-3">
                          {!w.stages?.length ? (
                            <p className="text-xs text-gray-400 italic">No stages defined.</p>
                          ) : (
                            <div className="flex items-center gap-2 flex-wrap">
                              {w.stages.map((s, i) => (
                                <div key={s.id} className="flex items-center gap-1">
                                  <div className="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs">
                                    <span className="font-medium text-gray-700">{i + 1}. {s.name}</span>
                                    <span className="ml-1.5 text-gray-400">· {s.due_days}d · {s.approval_strategy}</span>
                                    {s.is_gatekeeper && <span className="ml-1 bg-purple-100 text-purple-700 px-1 rounded">gate</span>}
                                    {s.is_anonymous && <span className="ml-1 bg-yellow-100 text-yellow-700 px-1 rounded">blind</span>}
                                  </div>
                                  {i < w.stages.length - 1 && <span className="text-gray-300">→</span>}
                                </div>
                              ))}
                            </div>
                          )}
                        </td>
                      </tr>
                    )}
                  </>
                ))}
              </tbody>
            </table>
            {(data?.meta?.last_page ?? 1) > 1 && (
              <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
                <p className="text-sm text-gray-500">Page {data!.meta.current_page} of {data!.meta.last_page} · {data!.meta.total} total</p>
                <div className="flex gap-2">
                  <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 border border-gray-200 rounded text-sm disabled:opacity-40">Previous</button>
                  <button disabled={page >= (data?.meta?.last_page ?? 1)} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 border border-gray-200 rounded text-sm disabled:opacity-40">Next</button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Delete confirm */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
            <h3 className="text-base font-semibold text-gray-900 mb-2">Delete workflow?</h3>
            <p className="text-sm text-gray-600 mb-5">If the workflow has active runs it will be deactivated instead.</p>
            <div className="flex justify-end gap-2">
              <button onClick={() => setDeleteConfirm(null)} className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
              <button onClick={() => deleteMutation.mutate(deleteConfirm)} disabled={deleteMutation.isPending}
                className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50">
                {deleteMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin inline" /> : 'Confirm'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Form modal */}
      {(showModal || editing) && (
        <WorkflowModal
          initial={editing}
          onClose={() => { setShowModal(false); setEditing(null) }}
          onSave={handleSave}
        />
      )}
    </div>
  )
}

