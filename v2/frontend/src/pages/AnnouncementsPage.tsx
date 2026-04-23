import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Megaphone, Plus, Trash2, Send, Loader2, CheckCircle, AlertTriangle,
  Info, Bell, X, Edit2, RefreshCw,
} from 'lucide-react'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import { useToastHelpers } from '../lib/toast'

interface Announcement {
  id: string
  title: string
  body: string
  type: 'info' | 'warning' | 'success' | 'danger'
  target: string
  is_active: boolean
  expires_at: string | null
  created_at: string
  creator: { id: string; name: string } | null
}

interface PaginatedAnnouncements {
  data: Announcement[]
  total: number
  current_page: number
  last_page: number
}

const TYPE_CONFIG = {
  info:    { label: 'Info',    cls: 'bg-blue-100 text-blue-700 border-blue-200',   icon: Info },
  warning: { label: 'Warning', cls: 'bg-amber-100 text-amber-700 border-amber-200', icon: AlertTriangle },
  success: { label: 'Success', cls: 'bg-green-100 text-green-700 border-green-200', icon: CheckCircle },
  danger:  { label: 'Danger',  cls: 'bg-red-100 text-red-700 border-red-200',       icon: AlertTriangle },
}

const TARGET_OPTIONS = [
  { value: 'all',             label: 'All users' },
  { value: 'role:student',    label: 'Students only' },
  { value: 'role:reviewer',   label: 'Reviewers only' },
  { value: 'role:coordinator', label: 'Coordinators only' },
]

const EMPTY_FORM: { title: string; body: string; type: 'info' | 'warning' | 'success' | 'danger'; target: string; expires_at: string; broadcast: boolean } = { title: '', body: '', type: 'info', target: 'all', expires_at: '', broadcast: true }

export default function AnnouncementsPage() {
  const user = useAuthStore((s) => s.user)
  const qc = useQueryClient()
  const toast = useToastHelpers()

  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formError, setFormError] = useState('')

  const isAdminOrCoord = user?.roles?.some((r) => r === 'admin' || r === 'coordinator')

  const { data, isLoading } = useQuery<PaginatedAnnouncements>({
    queryKey: ['announcements'],
    queryFn: () => api.get('/admin/announcements').then((r) => r.data),
    enabled: !!isAdminOrCoord,
  })

  const createMutation = useMutation({
    mutationFn: () => api.post('/admin/announcements', {
      title: form.title,
      body: form.body,
      type: form.type,
      target: form.target,
      expires_at: form.expires_at || null,
      broadcast: form.broadcast,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['announcements'] })
      setShowForm(false)
      setForm(EMPTY_FORM)
      setFormError('')
      toast.success('Announcement created', form.broadcast ? 'Notification sent to target users.' : 'Saved as draft.')
    },
    onError: (e: any) => setFormError(e?.response?.data?.message ?? 'Failed to create announcement.'),
  })

  const updateMutation = useMutation({
    mutationFn: () => api.patch(`/admin/announcements/${editId}`, {
      title: form.title,
      body: form.body,
      type: form.type,
      target: form.target,
      expires_at: form.expires_at || null,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['announcements'] })
      setEditId(null)
      setShowForm(false)
      setForm(EMPTY_FORM)
      setFormError('')
      toast.success('Announcement updated.')
    },
    onError: (e: any) => setFormError(e?.response?.data?.message ?? 'Failed to update.'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/announcements/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['announcements'] })
      toast.success('Announcement deleted.')
    },
  })

  const broadcastMutation = useMutation({
    mutationFn: (id: string) => api.post(`/admin/announcements/${id}/broadcast`),
    onSuccess: () => {
      toast.success('Announcement broadcasted.', 'Notifications sent to target users.')
    },
    onError: (e: any) => toast.error('Broadcast failed', e?.response?.data?.message ?? 'Error'),
  })

  const toggleActiveMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: string; is_active: boolean }) =>
      api.patch(`/admin/announcements/${id}`, { is_active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['announcements'] }),
  })

  const startEdit = (ann: Announcement) => {
    setEditId(ann.id)
    setForm({
      title: ann.title,
      body: ann.body,
      type: ann.type,
      target: ann.target,
      expires_at: ann.expires_at ? new Date(ann.expires_at).toISOString().slice(0, 16) : '',
      broadcast: false,
    })
    setFormError('')
    setShowForm(true)
  }

  const cancelForm = () => {
    setShowForm(false)
    setEditId(null)
    setForm(EMPTY_FORM)
    setFormError('')
  }

  const announcements = data?.data ?? []

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
          <Megaphone className="w-5 h-5 text-indigo-600" />
          <div>
            <h1 className="text-xl font-bold text-gray-900">Announcements</h1>
            <p className="text-sm text-gray-500">Broadcast announcements to portal users.</p>
          </div>
        </div>
        {!showForm && (
          <button
            onClick={() => { setEditId(null); setForm(EMPTY_FORM); setFormError(''); setShowForm(true) }}
            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
          >
            <Plus className="w-4 h-4" /> New Announcement
          </button>
        )}
      </div>

      {/* Form */}
      {showForm && (
        <div className="bg-white rounded-xl border border-indigo-200 shadow-sm p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold text-gray-900">
              {editId ? 'Edit Announcement' : 'New Announcement'}
            </h2>
            <button onClick={cancelForm} className="p-1 text-gray-400 hover:text-gray-600">
              <X className="w-4 h-4" />
            </button>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Title *</label>
              <input
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Announcement title"
                value={form.title}
                onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                maxLength={200}
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Message *</label>
              <textarea
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
                rows={4}
                placeholder="Announcement body text..."
                value={form.body}
                onChange={(e) => setForm((f) => ({ ...f, body: e.target.value }))}
                maxLength={5000}
              />
              <p className="text-xs text-gray-400 mt-1">{form.body.length}/5000</p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Type</label>
                <select
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                  value={form.type}
                  onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as typeof form.type }))}
                >
                  {Object.entries(TYPE_CONFIG).map(([k, v]) => (
                    <option key={k} value={k}>{v.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Target audience</label>
                <select
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                  value={form.target}
                  onChange={(e) => setForm((f) => ({ ...f, target: e.target.value }))}
                >
                  {TARGET_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </select>
              </div>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Expires at (optional)</label>
              <input
                type="datetime-local"
                className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                value={form.expires_at}
                onChange={(e) => setForm((f) => ({ ...f, expires_at: e.target.value }))}
              />
            </div>

            {!editId && (
              <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form.broadcast}
                  onChange={(e) => setForm((f) => ({ ...f, broadcast: e.target.checked }))}
                  className="rounded"
                />
                Send as notification to target users immediately
              </label>
            )}

            {formError && <p className="text-sm text-red-600">{formError}</p>}

            <div className="flex gap-3 justify-end">
              <button onClick={cancelForm} className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                Cancel
              </button>
              <button
                onClick={() => editId ? updateMutation.mutate() : createMutation.mutate()}
                disabled={createMutation.isPending || updateMutation.isPending || !form.title.trim() || !form.body.trim()}
                className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
              >
                {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                {editId ? 'Save Changes' : form.broadcast ? <><Send className="w-3.5 h-3.5" /> Publish & Broadcast</> : 'Save'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* List */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="w-6 h-6 animate-spin text-gray-300" />
        </div>
      ) : announcements.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
          <Megaphone className="w-10 h-10 text-gray-200 mx-auto mb-3" />
          <p className="text-sm text-gray-500">No announcements yet. Create one to notify users.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {announcements.map((ann) => {
            const cfg = TYPE_CONFIG[ann.type] ?? TYPE_CONFIG.info
            const Icon = cfg.icon
            const isExpired = ann.expires_at && new Date(ann.expires_at) < new Date()
            return (
              <div
                key={ann.id}
                className={`bg-white rounded-xl border shadow-sm overflow-hidden ${!ann.is_active || isExpired ? 'opacity-60' : ''}`}
              >
                <div className={`flex items-start gap-3 px-5 py-3.5 border-b ${cfg.cls}`}>
                  <Icon className="w-4 h-4 flex-shrink-0 mt-0.5" />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-sm">{ann.title}</p>
                    <div className="flex items-center gap-3 mt-0.5 flex-wrap text-xs opacity-70">
                      <span>To: {TARGET_OPTIONS.find((t) => t.value === ann.target)?.label ?? ann.target}</span>
                      {ann.expires_at && <span>Expires: {new Date(ann.expires_at).toLocaleDateString()}</span>}
                      {!ann.is_active && <span className="font-semibold">(Inactive)</span>}
                      {isExpired && <span className="font-semibold">(Expired)</span>}
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5 flex-shrink-0">
                    <button
                      onClick={() => toggleActiveMutation.mutate({ id: ann.id, is_active: !ann.is_active })}
                      title={ann.is_active ? 'Deactivate' : 'Activate'}
                      className="p-1.5 rounded hover:bg-white/50 text-current"
                    >
                      {toggleActiveMutation.isPending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RefreshCw className="w-3.5 h-3.5" />}
                    </button>
                    <button
                      onClick={() => broadcastMutation.mutate(ann.id)}
                      disabled={broadcastMutation.isPending}
                      title="Re-broadcast as notification"
                      className="p-1.5 rounded hover:bg-white/50"
                    >
                      <Bell className="w-3.5 h-3.5" />
                    </button>
                    <button onClick={() => startEdit(ann)} title="Edit" className="p-1.5 rounded hover:bg-white/50">
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => deleteMutation.mutate(ann.id)}
                      disabled={deleteMutation.isPending}
                      title="Delete"
                      className="p-1.5 rounded hover:bg-white/50"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
                <div className="px-5 py-3">
                  <p className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{ann.body}</p>
                  <p className="text-xs text-gray-400 mt-2">
                    Posted by {ann.creator?.name ?? 'Unknown'} · {new Date(ann.created_at).toLocaleDateString()}
                  </p>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
