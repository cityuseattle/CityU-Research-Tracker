import { useState, useMemo } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Edit2, Trash2, BookOpen, X, ChevronDown, ChevronUp, School } from 'lucide-react'
import { clsx } from 'clsx'
import api from '../lib/axios'
import { useToastHelpers } from '../lib/toast'
import { useAuthStore } from '../stores/authStore'

// ── Types ─────────────────────────────────────────────────────────────────────

interface Program {
  id: string
  name: string
  school: string | null
  description: string | null
  is_active: boolean
  program_director_id: string | null
  group_id: string | null
  created_at: string
}

interface ProgramForm {
  name: string
  school: string
  description: string
  is_active: boolean
}

const EMPTY_FORM: ProgramForm = {
  name: '',
  school: '',
  description: '',
  is_active: true,
}

// ── Program Form Modal ────────────────────────────────────────────────────────

function ProgramFormModal({
  initial,
  onClose,
  onSave,
  saving,
}: {
  initial: ProgramForm
  onClose: () => void
  onSave: (data: ProgramForm) => void
  saving: boolean
}) {
  const [form, setForm] = useState<ProgramForm>(initial)

  const set = (field: keyof ProgramForm, value: string | boolean) =>
    setForm(f => ({ ...f, [field]: value }))

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b">
          <div className="flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-indigo-600" />
            <h2 className="text-lg font-semibold text-gray-900">
              {initial.name ? 'Edit Program' : 'New Program'}
            </h2>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <div className="px-6 py-5 space-y-4">
          {/* Name */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Program Name <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={form.name}
              onChange={e => set('name', e.target.value)}
              placeholder="e.g. PhD Programme in Computer Science"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          {/* School */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">School / Department</label>
            <input
              type="text"
              value={form.school}
              onChange={e => set('school', e.target.value)}
              placeholder="e.g. Department of Computer Science"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea
              value={form.description}
              onChange={e => set('description', e.target.value)}
              rows={3}
              placeholder="Optional description for this program"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
            />
          </div>

          {/* Active */}
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => set('is_active', !form.is_active)}
              className={clsx(
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                form.is_active ? 'bg-green-500' : 'bg-gray-300',
              )}
            >
              <span
                className={clsx(
                  'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
                  form.is_active ? 'translate-x-6' : 'translate-x-1',
                )}
              />
            </button>
            <span className="text-sm text-gray-700">Active</span>
          </div>
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-xl">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={() => onSave(form)}
            disabled={saving || !form.name.trim()}
            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          >
            {saving ? 'Saving…' : 'Save Program'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function ProgramsPage() {
  const toast = useToastHelpers()
  const qc    = useQueryClient()
  const user  = useAuthStore(s => s.user)
  const isAdmin = user?.roles?.includes('admin') ?? false

  const [search,      setSearch]      = useState('')
  const [modalOpen,   setModalOpen]   = useState(false)
  const [editTarget,  setEditTarget]  = useState<Program | null>(null)
  const [expandedId,  setExpandedId]  = useState<string | null>(null)
  const [deleteId,    setDeleteId]    = useState<string | null>(null)

  // ── Queries ────────────────────────────────────────────────────────────────

  const { data: programs = [], isLoading } = useQuery<Program[]>({
    queryKey: ['programs'],
    queryFn: async () => {
      const res = await api.get('/programs')
      return res.data.data ?? res.data
    },
  })

  // ── Mutations ──────────────────────────────────────────────────────────────

  const createMutation = useMutation({
    mutationFn: (data: ProgramForm) => api.post('/programs', data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['programs'] })
      toast.success('Program created')
      setModalOpen(false)
    },
    onError: (err: any) => toast.error(err?.response?.data?.message ?? 'Failed to create program'),
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: ProgramForm }) =>
      api.patch(`/programs/${id}`, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['programs'] })
      toast.success('Program updated')
      setEditTarget(null)
    },
    onError: (err: any) => toast.error(err?.response?.data?.message ?? 'Failed to update program'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/programs/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['programs'] })
      toast.success('Program deleted')
      setDeleteId(null)
    },
    onError: (err: any) => toast.error(err?.response?.data?.message ?? 'Failed to delete program'),
  })

  // ── Derived ────────────────────────────────────────────────────────────────

  const filtered = programs.filter(p =>
    p.name.toLowerCase().includes(search.toLowerCase()) ||
    (p.school ?? '').toLowerCase().includes(search.toLowerCase()),
  )

  // Group filtered programs by school, sorted alphabetically
  const groupedBySchool = useMemo(() => {
    const groups: Record<string, Program[]> = {}
    filtered.forEach(p => {
      const key = p.school ?? 'No School'
      if (!groups[key]) groups[key] = []
      groups[key].push(p)
    })
    return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
  }, [filtered])

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Programs</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {programs.length} programs across {[...new Set(programs.map(p => p.school).filter(Boolean))].length} schools
          </p>
        </div>
        <button
          onClick={() => setModalOpen(true)}
          className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
        >
          <Plus className="w-4 h-4" />
          New Program
        </button>
      </div>

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input
          type="text"
          placeholder="Search by name or school…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      {/* List */}
      {isLoading ? (
        <div className="text-center py-12 text-gray-400">Loading programs…</div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-12 text-gray-400">
          {search ? 'No programs match your search.' : 'No programs yet. Create one to get started.'}
        </div>
      ) : (
        <div className="space-y-6">
          {groupedBySchool.map(([school, schoolPrograms]) => (
            <div key={school}>
              {/* School header */}
              <div className="flex items-center gap-2 mb-3">
                <School className="w-4 h-4 text-indigo-500 shrink-0" />
                <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">{school}</h2>
                <span className="ml-1 text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                  {schoolPrograms.length} {schoolPrograms.length === 1 ? 'program' : 'programs'}
                </span>
                <div className="flex-1 h-px bg-gray-200 ml-2" />
              </div>

              {/* Programs in this school */}
              <div className="space-y-2">
                {schoolPrograms.map(program => (
                  <div
                    key={program.id}
                    className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden"
                  >
                    {/* Row */}
                    <div className="flex items-center px-5 py-3.5 gap-4">
                      <BookOpen className="w-4 h-4 text-indigo-400 shrink-0" />

                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="font-medium text-gray-900 text-sm">{program.name}</span>
                          <span
                            className={clsx(
                              'text-xs px-2 py-0.5 rounded-full font-medium',
                              program.is_active
                                ? 'bg-green-100 text-green-700'
                                : 'bg-gray-100 text-gray-500',
                            )}
                          >
                            {program.is_active ? 'Active' : 'Inactive'}
                          </span>
                        </div>
                        {program.description && (
                          <p className="text-xs text-gray-400 mt-0.5 truncate max-w-lg">{program.description}</p>
                        )}
                      </div>

                      {/* Actions */}
                      <div className="flex items-center gap-1 shrink-0">
                        {program.description && (
                          <button
                            onClick={() => setExpandedId(expandedId === program.id ? null : program.id)}
                            className="p-1.5 text-gray-400 hover:text-gray-600"
                            title="View description"
                          >
                            {expandedId === program.id
                              ? <ChevronUp className="w-4 h-4" />
                              : <ChevronDown className="w-4 h-4" />}
                          </button>
                        )}
                        <button
                          onClick={() => setEditTarget(program)}
                          className="p-1.5 text-gray-400 hover:text-indigo-600"
                          title="Edit"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        {isAdmin && (
                          <button
                            onClick={() => setDeleteId(program.id)}
                            className="p-1.5 text-gray-400 hover:text-red-600"
                            title="Delete"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Description drawer */}
                    {expandedId === program.id && program.description && (
                      <div className="border-t px-5 py-3 bg-gray-50">
                        <p className="text-sm text-gray-600 whitespace-pre-wrap">{program.description}</p>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Create modal */}
      {modalOpen && (
        <ProgramFormModal
          initial={EMPTY_FORM}
          onClose={() => setModalOpen(false)}
          onSave={data => createMutation.mutate(data)}
          saving={createMutation.isPending}
        />
      )}

      {/* Edit modal */}
      {editTarget && (
        <ProgramFormModal
          initial={{
            name: editTarget.name,
            school: editTarget.school ?? '',
            description: editTarget.description ?? '',
            is_active: editTarget.is_active,
          }}
          onClose={() => setEditTarget(null)}
          onSave={data => updateMutation.mutate({ id: editTarget.id, data })}
          saving={updateMutation.isPending}
        />
      )}

      {/* Delete confirm */}
      {deleteId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
            <h3 className="text-base font-semibold text-gray-900 mb-2">Delete Program</h3>
            <p className="text-sm text-gray-600 mb-5">
              This will permanently delete the program. Users assigned to it will lose their program
              association. This action cannot be undone.
            </p>
            <div className="flex justify-end gap-3">
              <button
                onClick={() => setDeleteId(null)}
                className="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
              >
                Cancel
              </button>
              <button
                onClick={() => deleteMutation.mutate(deleteId)}
                disabled={deleteMutation.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
              >
                {deleteMutation.isPending ? 'Deleting…' : 'Delete'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
