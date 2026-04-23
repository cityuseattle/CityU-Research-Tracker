import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Shield, Plus, Trash2, Edit2, X, Loader2, Info } from 'lucide-react'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import { useToastHelpers } from '../lib/toast'

interface CustomRole {
  id: string
  name: string
  description: string | null
  color: string
  created_at: string
  creator: { id: string; name: string } | null
}

const COLOR_PRESETS = [
  '#6366f1', '#2563eb', '#0d9488', '#16a34a', '#ca8a04',
  '#ea580c', '#e11d48', '#7c3aed', '#0891b2', '#374151',
]

const SYSTEM_ROLES = [
  { name: 'Reviewer',    description: 'Standard peer reviewer role' },
  { name: 'Coordinator', description: 'Process coordinator role' },
  { name: 'Student',     description: 'Submitting researcher role' },
  { name: 'Admin',       description: 'System administrator role' },
]

const EMPTY_FORM = { name: '', description: '', color: COLOR_PRESETS[0] }

export default function CustomRolesPage() {
  const user = useAuthStore((s) => s.user)
  const qc = useQueryClient()
  const toast = useToastHelpers()

  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formError, setFormError] = useState('')

  const isAdminOrCoord = user?.roles?.some((r) => r === 'admin' || r === 'coordinator')

  const { data, isLoading } = useQuery<{ data: CustomRole[] }>({
    queryKey: ['custom-roles'],
    queryFn: () => api.get('/admin/custom-roles').then((r) => r.data),
    enabled: !!isAdminOrCoord,
  })

  const createMutation = useMutation({
    mutationFn: () => api.post('/admin/custom-roles', form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['custom-roles'] })
      setShowForm(false)
      setForm(EMPTY_FORM)
      setFormError('')
      toast.success('Custom role created.')
    },
    onError: (e: any) => setFormError(e?.response?.data?.message ?? 'Failed to create role.'),
  })

  const updateMutation = useMutation({
    mutationFn: () => api.patch(`/admin/custom-roles/${editId}`, form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['custom-roles'] })
      setEditId(null)
      setShowForm(false)
      setForm(EMPTY_FORM)
      setFormError('')
      toast.success('Custom role updated.')
    },
    onError: (e: any) => setFormError(e?.response?.data?.message ?? 'Failed to update role.'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/custom-roles/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['custom-roles'] })
      toast.success('Custom role deleted.')
    },
  })

  const startEdit = (role: CustomRole) => {
    setEditId(role.id)
    setForm({ name: role.name, description: role.description ?? '', color: role.color })
    setFormError('')
    setShowForm(true)
  }

  const cancelForm = () => {
    setShowForm(false)
    setEditId(null)
    setForm(EMPTY_FORM)
    setFormError('')
  }

  const customRoles = data?.data ?? []

  if (!isAdminOrCoord) {
    return (
      <div className="flex items-center justify-center h-64">
        <p className="text-gray-400">Access restricted to coordinators and administrators.</p>
      </div>
    )
  }

  return (
    <div className="max-w-3xl mx-auto space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Shield className="w-5 h-5 text-purple-600" />
          <div>
            <h1 className="text-xl font-bold text-gray-900">Custom Roles</h1>
            <p className="text-sm text-gray-500">Define custom reviewer roles for use in workflow stages.</p>
          </div>
        </div>
        {!showForm && (
          <button
            onClick={() => { setEditId(null); setForm(EMPTY_FORM); setFormError(''); setShowForm(true) }}
            className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700"
          >
            <Plus className="w-4 h-4" /> New Role
          </button>
        )}
      </div>

      {/* Info banner */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
        <Info className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
        <p className="text-sm text-blue-700">
          Custom roles are used as <strong>stage role labels</strong> in workflows. When editing a workflow stage, 
          you can select a custom role to define the type of reviewer needed for that stage.
        </p>
      </div>

      {/* Create/Edit form */}
      {showForm && (
        <div className="bg-white rounded-xl border border-purple-200 shadow-sm p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold text-gray-900">
              {editId ? 'Edit Role' : 'New Custom Role'}
            </h2>
            <button onClick={cancelForm} className="p-1 text-gray-400 hover:text-gray-600">
              <X className="w-4 h-4" />
            </button>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Role name *</label>
              <input
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                placeholder="e.g. Domain Expert, External Reviewer"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                maxLength={80}
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Description</label>
              <input
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                placeholder="Optional description of this role's responsibilities"
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                maxLength={300}
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-600 mb-2">Color badge</label>
              <div className="flex items-center gap-2 flex-wrap">
                {COLOR_PRESETS.map((c) => (
                  <button
                    key={c}
                    onClick={() => setForm((f) => ({ ...f, color: c }))}
                    style={{ background: c, border: form.color === c ? '3px solid #111827' : '2px solid transparent' }}
                    className="w-7 h-7 rounded-full transition-transform hover:scale-110"
                    title={c}
                  />
                ))}
                <input
                  type="color"
                  value={form.color}
                  onChange={(e) => setForm((f) => ({ ...f, color: e.target.value }))}
                  className="w-7 h-7 rounded cursor-pointer border border-gray-300"
                  title="Custom color"
                />
                <span className="text-xs text-gray-400 ml-1">Preview:</span>
                <span
                  className="text-xs px-2 py-0.5 rounded-full text-white font-medium"
                  style={{ background: form.color }}
                >
                  {form.name || 'Role Name'}
                </span>
              </div>
            </div>

            {formError && <p className="text-sm text-red-600">{formError}</p>}

            <div className="flex gap-3 justify-end">
              <button onClick={cancelForm} className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                Cancel
              </button>
              <button
                onClick={() => editId ? updateMutation.mutate() : createMutation.mutate()}
                disabled={createMutation.isPending || updateMutation.isPending || !form.name.trim()}
                className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 disabled:opacity-50"
              >
                {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                {editId ? 'Save Changes' : 'Create Role'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* System roles (reference) */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-3">Built-in System Roles</h3>
        <div className="flex items-center gap-2 flex-wrap">
          {SYSTEM_ROLES.map((r) => (
            <span key={r.name} title={r.description} className="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 font-medium border border-gray-200">
              {r.name}
            </span>
          ))}
        </div>
        <p className="text-xs text-gray-400 mt-2">
          System roles cannot be modified. Custom roles below are available alongside these in workflow stage configuration.
        </p>
      </div>

      {/* Custom roles list */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="w-6 h-6 animate-spin text-gray-300" />
        </div>
      ) : customRoles.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
          <Shield className="w-10 h-10 text-gray-200 mx-auto mb-3" />
          <p className="text-sm text-gray-500">No custom roles yet. Create your first one above.</p>
        </div>
      ) : (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="px-5 py-3 bg-gray-50 border-b border-gray-100">
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Custom Roles ({customRoles.length})</p>
          </div>
          <ul className="divide-y divide-gray-100">
            {customRoles.map((role) => (
              <li key={role.id} className="flex items-center gap-4 px-5 py-3.5">
                <span
                  className="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                  style={{ background: role.color }}
                >
                  {role.name.slice(0, 2).toUpperCase()}
                </span>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="text-sm font-medium text-gray-900">{role.name}</p>
                    <span
                      className="text-xs px-2 py-0.5 rounded-full text-white font-medium"
                      style={{ background: role.color }}
                    >
                      {role.name}
                    </span>
                  </div>
                  {role.description && <p className="text-xs text-gray-500 mt-0.5">{role.description}</p>}
                  <p className="text-xs text-gray-400">
                    Created by {role.creator?.name ?? 'Unknown'} · {new Date(role.created_at).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex items-center gap-1.5 flex-shrink-0">
                  <button
                    onClick={() => startEdit(role)}
                    className="p-1.5 text-gray-400 hover:text-purple-600 rounded"
                    title="Edit"
                  >
                    <Edit2 className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => deleteMutation.mutate(role.id)}
                    disabled={deleteMutation.isPending}
                    className="p-1.5 text-gray-400 hover:text-red-500 rounded"
                    title="Delete"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
