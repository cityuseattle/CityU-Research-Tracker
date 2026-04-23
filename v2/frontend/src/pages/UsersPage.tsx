import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Edit2, Trash2, UserCheck, UserX, Key, Users, FolderOpen, X, Check, Lock, Unlock, RefreshCw, Eye, EyeOff, Copy, CheckCheck, Layers } from 'lucide-react'
import { clsx } from 'clsx'
import api from '../lib/axios'
import { useToastHelpers } from '../lib/toast'
import { useAuthStore } from '../stores/authStore'
import type {
  User, Group, CreateUserRequest, UpdateUserRequest,
  GroupMember, PaginatedResponse, GroupType,
} from '../types/users'
import type { Role } from '../types/auth'

// ── Utilities ────────────────────────────────────────────────────────────────

function generatePassword(length = 16): string {
  const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  const lower = 'abcdefghijklmnopqrstuvwxyz'
  const digits = '0123456789'
  const special = '!@#$%^&*()_+-='
  const all = upper + lower + digits + special
  const arr = new Uint8Array(length)
  crypto.getRandomValues(arr)
  // Guarantee at least one of each required class
  const pwd: string[] = [
    upper[arr[0] % upper.length],
    lower[arr[1] % lower.length],
    digits[arr[2] % digits.length],
    special[arr[3] % special.length],
  ]
  for (let i = 4; i < length; i++) pwd.push(all[arr[i] % all.length])
  // Fisher-Yates shuffle using fresh random bytes
  const s = new Uint8Array(length)
  crypto.getRandomValues(s)
  for (let i = pwd.length - 1; i > 0; i--) {
    const j = s[i] % (i + 1);[pwd[i], pwd[j]] = [pwd[j], pwd[i]]
  }
  return pwd.join('')
}

// ── Constants ─────────────────────────────────────────────────────────────────

const ROLES: Role[] = ['admin', 'coordinator', 'reviewer', 'student']
const GROUP_TYPES: GroupType[] = ['department', 'faculty', 'school', 'custom']

const roleColor: Record<Role, string> = {
  admin:       'bg-red-100 text-red-700',
  coordinator: 'bg-purple-100 text-purple-700',
  reviewer:    'bg-blue-100 text-blue-700',
  student:     'bg-green-100 text-green-700',
}

// ── Small reusable components ─────────────────────────────────────────────────

function RoleBadge({ role }: { role: Role }) {
  return (
    <span className={clsx('inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize', roleColor[role])}>
      {role}
    </span>
  )
}

function StatusDot({ active }: { active: boolean }) {
  return <span className={clsx('inline-block w-2 h-2 rounded-full', active ? 'bg-green-500' : 'bg-gray-300')} />
}

function EmptyState({ icon: Icon, title, body }: { icon: React.ElementType; title: string; body: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <Icon className="w-12 h-12 text-gray-200 mb-4" />
      <p className="text-base font-medium text-gray-500">{title}</p>
      <p className="text-sm text-gray-400 mt-1">{body}</p>
    </div>
  )
}

function Modal({ title, onClose, children, size = 'md' }: { title: string; onClose: () => void; children: React.ReactNode; size?: 'md' | 'lg' }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className={clsx('relative bg-white rounded-xl shadow-2xl w-full max-h-[90vh] overflow-y-auto', size === 'lg' ? 'max-w-3xl' : 'max-w-lg')}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 className="text-base font-semibold text-gray-900">{title}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><X className="w-5 h-5" /></button>
        </div>
        <div className="px-6 py-5">{children}</div>
      </div>
    </div>
  )
}

function FormField({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {children}
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  )
}

const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-transparent'
const btnPrimary = 'inline-flex items-center gap-2 px-4 py-2 bg-brand-800 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition-colors disabled:opacity-50'
const btnSecondary = 'inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors'

// ── User form modal ───────────────────────────────────────────────────────────

function UserFormModal({
  initial,
  onClose,
  onSaved,
  actorIsAdmin = true,
}: {
  initial?: User | null
  onClose: () => void
  onSaved: () => void
  actorIsAdmin?: boolean
}) {
  const isEdit = !!initial
  // Coordinator in edit mode sees only org / is_active fields
  const coordinatorEditMode = !actorIsAdmin && isEdit

  const [form, setForm] = useState({
    first_name:            initial?.first_name ?? '',
    last_name:             initial?.last_name ?? '',
    email:                 initial?.email ?? '',
    organization:          initial?.organization ?? '',
    org_role:              initial?.org_role ?? '',
    program_id:            initial?.program_id ?? '',
    password:              '', password_confirmation: '',
    roles:                 (initial?.roles ?? ['student']) as Role[],
    is_active:             initial?.is_active ?? true,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const [showPwd, setShowPwd] = useState(false)
  const [copied, setCopied] = useState(false)

  // Programs list (for dropdown)
  const { data: programsData } = useQuery<{ data?: Array<{ id: string; name: string }> } | Array<{ id: string; name: string }>>({
    queryKey: ['programs-picker'],
    queryFn: () => api.get('/programs').then(r => r.data),
  })
  const programs: Array<{ id: string; name: string }> = Array.isArray(programsData)
    ? programsData
    : (programsData?.data ?? [])

  // Coordinator group scope state (admin only, edit only)
  const isCoordinator = form.roles.includes('coordinator')
  const [coordGroupErr, setCoordGroupErr] = useState('')
  const [addingGroup, setAddingGroup] = useState(false)
  const [selectedGroupId, setSelectedGroupId] = useState('')

  const { data: coordGroups, refetch: refetchCoordGroups } = useQuery<{ data: Array<{id: string; name: string; type: string; assigned_at: string}> }>({
    queryKey: ['coordinator-groups', initial?.id],
    queryFn: () => api.get(`/users/${initial!.id}/coordinator-groups`).then(r => r.data),
    enabled: actorIsAdmin && isEdit && isCoordinator && !!initial?.id,
  })

  const { data: allGroupsData } = useQuery<{ data: Group[] }>({
    queryKey: ['groups-all-mini'],
    queryFn: () => api.get('/groups?per_page=200').then(r => r.data),
    enabled: actorIsAdmin && isEdit && isCoordinator,
  })

  const assignedGroupIds = new Set((coordGroups?.data ?? []).map(g => g.id))
  const availableGroups = (allGroupsData?.data ?? []).filter(g => !assignedGroupIds.has(g.id))

  const addGroup = async () => {
    if (!selectedGroupId) return
    setCoordGroupErr('')
    setAddingGroup(true)
    try {
      await api.post(`/users/${initial!.id}/coordinator-groups`, { group_id: selectedGroupId })
      setSelectedGroupId('')
      refetchCoordGroups()
    } catch {
      setCoordGroupErr('Failed to add group.')
    } finally {
      setAddingGroup(false)
    }
  }

  const removeGroup = async (groupId: string) => {
    setCoordGroupErr('')
    try {
      await api.delete(`/users/${initial!.id}/coordinator-groups/${groupId}`)
      refetchCoordGroups()
    } catch {
      setCoordGroupErr('Failed to remove group.')
    }
  }

  const handleGenerate = () => {
    const p = generatePassword()
    setForm(f => ({ ...f, password: p, password_confirmation: p }))
    setShowPwd(true)
    setCopied(false)
  }

  const handleCopy = () => {
    navigator.clipboard.writeText(form.password)
    setCopied(true)
    setTimeout(() => setCopied(false), 2500)
  }

  const toggleRole = (role: Role) =>
    setForm(f => ({ ...f, roles: f.roles.includes(role) ? f.roles.filter(r => r !== role) : [...f.roles, role] }))

  const submit = async () => {
    setErrors({})
    if (coordinatorEditMode) {
      // Coordinator editing: only send allowed fields
      setSaving(true)
      try {
        await api.patch(`/users/${initial!.id}`, {
          organization: form.organization || null,
          org_role:     form.org_role || null,
          program_id:   form.program_id || null,
          is_active:    form.is_active,
        })
        onSaved(); onClose()
      } catch (err: any) {
        const msg = err?.response?.data?.message ?? 'Failed to update user.'
        setErrors({ _general: msg })
      } finally { setSaving(false) }
      return
    }

    if (!form.first_name.trim()) return setErrors({ first_name: 'First name is required.' })
    if (!form.last_name.trim()) return setErrors({ last_name: 'Last name is required.' })
    if (!form.email.trim()) return setErrors({ email: 'Email (UPN) is required.' })
    if (form.roles.length === 0) return setErrors({ roles: 'At least one role is required.' })
    if (!isEdit && !form.password) return setErrors({ password: 'Password is required.' })
    if (!isEdit && form.password !== form.password_confirmation) return setErrors({ password_confirmation: 'Passwords do not match.' })
    setSaving(true)
    try {
      if (isEdit) {
        await api.patch(`/users/${initial!.id}`, {
          first_name: form.first_name, last_name: form.last_name,
          email: form.email, organization: form.organization || null,
          org_role: form.org_role || null, roles: form.roles, is_active: form.is_active,
          program_id: form.program_id || null,
        } as UpdateUserRequest)
      } else {
        await api.post('/users', {
          first_name: form.first_name, last_name: form.last_name,
          email: form.email, organization: form.organization || null,
          org_role: form.org_role || null,
          password: form.password, password_confirmation: form.password_confirmation,
          roles: form.roles, is_active: form.is_active,
          program_id: form.program_id || null,
        } as CreateUserRequest)
      }
      onSaved(); onClose()
    } catch (err: any) {
      const errs = err?.response?.data?.errors ?? {}
      const flat: Record<string, string> = {}
      Object.entries(errs).forEach(([k, v]) => { flat[k] = Array.isArray(v) ? (v as string[])[0] : String(v) })
      if (err?.response?.data?.message && !Object.keys(flat).length) flat._general = err.response.data.message
      setErrors(flat)
    } finally { setSaving(false) }
  }

  return (
    <Modal title={isEdit ? 'Edit User' : 'Create User'} onClose={onClose}>
      <div className="space-y-4">
        {errors._general && <p className="text-sm text-red-600 bg-red-50 rounded px-3 py-2">{errors._general}</p>}

        {/* Name + email — hidden for coordinator in edit mode */}
        {!coordinatorEditMode && (
          <>
            <div className="grid grid-cols-2 gap-3">
              <FormField label="First Name" error={errors.first_name}>
                <input className={inputCls} value={form.first_name} placeholder="First name"
                  onChange={e => setForm(f => ({ ...f, first_name: e.target.value }))} />
              </FormField>
              <FormField label="Last Name" error={errors.last_name}>
                <input className={inputCls} value={form.last_name} placeholder="Last name"
                  onChange={e => setForm(f => ({ ...f, last_name: e.target.value }))} />
              </FormField>
            </div>
            <FormField label="Email Address (UPN)" error={errors.email}>
              <input className={inputCls} type="email" value={form.email} placeholder="user@domain.com"
                onChange={e => setForm(f => ({ ...f, email: e.target.value }))} />
            </FormField>
          </>
        )}

        <FormField label="Organization" error={errors.organization}>
          <input className={inputCls} value={form.organization} placeholder="e.g. City University of Hong Kong"
            onChange={e => setForm(f => ({ ...f, organization: e.target.value }))} />
        </FormField>
        <FormField label="Role in Organization" error={errors.org_role}>
          <input className={inputCls} value={form.org_role} placeholder="e.g. Professor, Research Associate"
            onChange={e => setForm(f => ({ ...f, org_role: e.target.value }))} />
        </FormField>

        {/* Program */}
        <FormField label="Program" error={errors.program_id}>
          <select
            className={inputCls}
            value={form.program_id}
            onChange={e => setForm(f => ({ ...f, program_id: e.target.value }))}
          >
            <option value="">— No program —</option>
            {programs.map(p => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
        </FormField>

        {/* Password — only shown in create mode */}
        {!isEdit && (
          <>
            <FormField label="Password" error={errors.password}>
              <div className="flex gap-2">
                <div className="relative flex-1">
                  <input className={inputCls + ' pr-10'} type={showPwd ? 'text' : 'password'} value={form.password}
                    onChange={e => setForm(f => ({ ...f, password: e.target.value }))} />
                  <button type="button" onClick={() => setShowPwd(s => !s)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
                <button type="button" title="Auto-generate strong password" onClick={handleGenerate}
                  className="px-2.5 py-2 border border-gray-300 rounded-lg text-gray-500 hover:text-brand-700 hover:border-brand-400 transition-colors">
                  <RefreshCw className="w-4 h-4" />
                </button>
                {form.password && (
                  <button type="button" title={copied ? 'Copied!' : 'Copy password'} onClick={handleCopy}
                    className={clsx('px-2.5 py-2 border rounded-lg transition-colors', copied ? 'border-green-500 text-green-600 bg-green-50' : 'border-gray-300 text-gray-500 hover:text-brand-700 hover:border-brand-400')}>
                    {copied ? <CheckCheck className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                  </button>
                )}
              </div>
              {showPwd && form.password && (
                <p className="mt-1.5 px-2.5 py-1.5 bg-amber-50 border border-amber-200 rounded text-xs font-mono text-gray-800 break-all select-all">
                  {form.password}
                </p>
              )}
            </FormField>
            <FormField label="Confirm Password" error={errors.password_confirmation}>
              <input className={inputCls} type="password" value={form.password_confirmation}
                onChange={e => setForm(f => ({ ...f, password_confirmation: e.target.value }))} />
            </FormField>
          </>
        )}

        {/* System Roles — hidden for coordinator in edit mode; admin role hidden for coordinator actors */}
        {!coordinatorEditMode && (
          <FormField label="System Roles" error={errors.roles}>
            <div className="flex flex-wrap gap-2 mt-1">
              {ROLES.filter(role => actorIsAdmin || role !== 'admin').map(role => (
                <button key={role} type="button" onClick={() => toggleRole(role)}
                  className={clsx('px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors capitalize',
                    form.roles.includes(role) ? 'border-brand-800 bg-brand-800 text-white' : 'border-gray-300 text-gray-600 hover:border-brand-600')}>
                  {role}
                </button>
              ))}
            </div>
          </FormField>
        )}

        {/* Active toggle — edit only */}
        {isEdit && (
          <FormField label="Status">
            <label className="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" checked={form.is_active} onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))} className="rounded" />
              <span className="text-sm text-gray-700">Active</span>
            </label>
          </FormField>
        )}

        {/* Coordinator group scope — admin only */}
        {actorIsAdmin && isEdit && isCoordinator && (
          <div className="border border-purple-200 rounded-lg p-4 bg-purple-50/50">
            <div className="flex items-center gap-2 mb-3">
              <Layers className="w-4 h-4 text-purple-600" />
              <span className="text-sm font-semibold text-purple-800">Coordinator Group Scope</span>
            </div>
            <p className="text-xs text-purple-600 mb-3">
              Restrict this coordinator to manage submissions from specific groups only. If none are assigned, they can view all groups.
            </p>
            {coordGroupErr && <p className="text-xs text-red-600 mb-2">{coordGroupErr}</p>}
            <div className="space-y-1 mb-3">
              {(coordGroups?.data ?? []).length === 0 ? (
                <p className="text-xs text-gray-400 italic">No groups assigned — coordinator sees all groups.</p>
              ) : (
                (coordGroups?.data ?? []).map(g => (
                  <div key={g.id} className="flex items-center justify-between bg-white border border-purple-100 rounded px-3 py-1.5 text-sm">
                    <span className="text-gray-700 capitalize">{g.name} <span className="text-gray-400 text-xs">({g.type})</span></span>
                    <button onClick={() => removeGroup(g.id)} className="text-gray-400 hover:text-red-500 ml-2" title="Remove">
                      <X className="w-3.5 h-3.5" />
                    </button>
                  </div>
                ))
              )}
            </div>
            {availableGroups.length > 0 && (
              <div className="flex gap-2">
                <select
                  value={selectedGroupId}
                  onChange={e => setSelectedGroupId(e.target.value)}
                  className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                >
                  <option value="">Select group to add…</option>
                  {availableGroups.map(g => (
                    <option key={g.id} value={g.id}>{g.name} ({g.type})</option>
                  ))}
                </select>
                <button
                  onClick={addGroup}
                  disabled={!selectedGroupId || addingGroup}
                  className="px-3 py-1.5 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 disabled:opacity-50 transition-colors"
                >
                  {addingGroup ? '…' : 'Add'}
                </button>
              </div>
            )}
          </div>
        )}

        <div className="flex justify-end gap-3 pt-2">
          <button className={btnSecondary} onClick={onClose}>Cancel</button>
          <button className={btnPrimary} onClick={submit} disabled={saving}>{saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create User'}</button>
        </div>
      </div>
    </Modal>
  )
}

// ── Reset password modal ──────────────────────────────────────────────────────

function ResetPasswordModal({ user, onClose }: { user: User; onClose: () => void }) {
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [done, setDone] = useState(false)
  const [showPwd, setShowPwd] = useState(false)
  const [copied, setCopied] = useState(false)

  const handleGenerate = () => {
    const p = generatePassword()
    setPassword(p); setConfirm(p); setShowPwd(true); setCopied(false)
  }

  const handleCopy = () => {
    navigator.clipboard.writeText(password)
    setCopied(true)
    setTimeout(() => setCopied(false), 2500)
  }

  const submit = async () => {
    setError('')
    if (!password) return setError('Password is required.')
    if (password !== confirm) return setError('Passwords do not match.')
    setSaving(true)
    try {
      await api.post(`/users/${user.id}/reset-password`, { password, password_confirmation: confirm })
      setDone(true)
    } catch (err: any) {
      setError(err?.response?.data?.message ?? 'Failed to reset password.')
    } finally { setSaving(false) }
  }

  return (
    <Modal title={`Reset Password — ${user.name}`} onClose={onClose}>
      {done ? (
        <div className="flex flex-col items-center py-6 gap-3">
          <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
            <Check className="w-6 h-6 text-green-600" />
          </div>
          <p className="font-medium text-gray-900">Password reset successfully</p>
          <button className={btnPrimary} onClick={onClose}>Done</button>
        </div>
      ) : (
        <div className="space-y-4">
          {error && <p className="text-sm text-red-600 bg-red-50 rounded px-3 py-2">{error}</p>}
          <FormField label="New Password">
            <div className="flex gap-2">
              <div className="relative flex-1">
                <input className={inputCls + ' pr-10'} type={showPwd ? 'text' : 'password'} value={password}
                  onChange={e => setPassword(e.target.value)} />
                <button type="button" onClick={() => setShowPwd(s => !s)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              <button type="button" title="Auto-generate strong password" onClick={handleGenerate}
                className="px-2.5 py-2 border border-gray-300 rounded-lg text-gray-500 hover:text-brand-700 hover:border-brand-400 transition-colors">
                <RefreshCw className="w-4 h-4" />
              </button>
              {password && (
                <button type="button" title={copied ? 'Copied!' : 'Copy password'} onClick={handleCopy}
                  className={clsx('px-2.5 py-2 border rounded-lg transition-colors', copied ? 'border-green-500 text-green-600 bg-green-50' : 'border-gray-300 text-gray-500 hover:text-brand-700 hover:border-brand-400')}>
                  {copied ? <CheckCheck className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                </button>
              )}
            </div>
            {showPwd && password && (
              <p className="mt-1.5 px-2.5 py-1.5 bg-amber-50 border border-amber-200 rounded text-xs font-mono text-gray-800 break-all select-all">
                {password}
              </p>
            )}
          </FormField>
          <FormField label="Confirm Password">
            <input className={inputCls} type="password" value={confirm} onChange={e => setConfirm(e.target.value)} />
          </FormField>
          <p className="text-xs text-gray-500">Min 12 chars, uppercase, number, and special character required.</p>
          <div className="flex justify-end gap-3 pt-2">
            <button className={btnSecondary} onClick={onClose}>Cancel</button>
            <button className={btnPrimary} onClick={submit} disabled={saving}>{saving ? 'Resetting…' : 'Reset Password'}</button>
          </div>
        </div>
      )}
    </Modal>
  )
}

// ── Users tab ─────────────────────────────────────────────────────────────────

function UsersTab() {
  const qc = useQueryClient()
  const toast = useToastHelpers()
  const actorUser = useAuthStore(s => s.user)
  const actorIsAdmin = actorUser?.roles?.includes('admin') ?? false
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  const [page, setPage] = useState(1)
  const [showCreate, setShowCreate] = useState(false)
  const [editUser, setEditUser] = useState<User | null>(null)
  const [resetUser, setResetUser] = useState<User | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['users', search, roleFilter, page],
    queryFn: async () => {
      const p = new URLSearchParams({ page: String(page), per_page: '20' })
      if (search) p.set('search', search)
      if (roleFilter) p.set('role', roleFilter)
      const res = await api.get<PaginatedResponse<User>>(`/users?${p}`)
      return res.data
    },
  })

  const toggleActive = useMutation({
    mutationFn: (user: User) => user.is_active ? api.delete(`/users/${user.id}`) : api.post(`/users/${user.id}/activate`),
    onSuccess: (_r, user) => {
      qc.invalidateQueries({ queryKey: ['users'] })
      toast.success(user.is_active ? 'User deactivated.' : 'User activated.')
    },
    onError: () => toast.error('Action failed.'),
  })

  const unlockUser = useMutation({
    mutationFn: (user: User) => api.post(`/users/${user.id}/unlock`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['users'] }); toast.success('Account unlocked.') },
    onError: () => toast.error('Unlock failed.'),
  })

  const refresh = () => qc.invalidateQueries({ queryKey: ['users'] })

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600"
            placeholder="Search name or email…" value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} />
        </div>
        <select className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600"
          value={roleFilter} onChange={e => { setRoleFilter(e.target.value); setPage(1) }}>
          <option value="">All Roles</option>
          {ROLES.map(r => <option key={r} value={r} className="capitalize">{r}</option>)}
        </select>
        <button className={btnPrimary} onClick={() => setShowCreate(true)}><Plus className="w-4 h-4" /> Add User</button>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <div className="py-20 text-center text-sm text-gray-400">Loading users…</div>
        ) : !data?.data.length ? (
          <EmptyState icon={Users} title="No users found" body="Try adjusting your search or filters." />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-100">
                <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  <tr>
                    <th className="px-5 py-3 text-left">User</th>
                    <th className="px-5 py-3 text-left">Organization</th>
                    <th className="px-5 py-3 text-left">Roles</th>
                    <th className="px-5 py-3 text-left">Status</th>
                    <th className="px-5 py-3 text-left">Last Login</th>
                    <th className="px-5 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {data.data.map(user => (
                    <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-5 py-3">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-brand-800 text-white flex items-center justify-center text-xs font-semibold flex-shrink-0">
                            {user.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="text-sm font-medium text-gray-900">{user.name}</p>
                            <p className="text-xs text-gray-500">{user.email}</p>
                            {user.is_emergency_admin && (
                              <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700 mt-0.5">Emergency Admin</span>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-3">
                        <p className="text-sm text-gray-700">{user.organization ?? '—'}</p>
                        {user.org_role && <p className="text-xs text-gray-400">{user.org_role}</p>}
                      </td>
                      <td className="px-5 py-3"><div className="flex flex-wrap gap-1">{user.roles.map(r => <RoleBadge key={r} role={r} />)}</div></td>
                      <td className="px-5 py-3">
                        <div className="flex flex-col gap-0.5">
                          <div className="flex items-center gap-1.5">
                            <StatusDot active={user.is_active && !user.locked_at} />
                            <span className="text-sm text-gray-600">
                              {user.locked_at ? 'Locked' : user.is_active ? 'Active' : 'Inactive'}
                            </span>
                            {user.locked_at && <Lock className="w-3 h-3 text-red-500" />}
                          </div>
                          {user.failed_login_attempts > 0 && !user.locked_at && (
                            <span className="text-xs text-amber-600">{user.failed_login_attempts} failed attempt{user.failed_login_attempts > 1 ? 's' : ''}</span>
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-3">
                        <div className="text-sm text-gray-500">
                          {user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : '—'}
                        </div>
                        {user.last_login_attempt_at && (
                          <div className="flex items-center gap-1 text-xs mt-0.5">
                            <span className={user.last_login_success ? 'text-green-600' : 'text-red-500'}>
                              {user.last_login_success ? '✓' : '✗'} {new Date(user.last_login_attempt_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            </span>
                          </div>
                        )}
                      </td>
                      <td className="px-5 py-3">
                        <div className="flex items-center justify-end gap-1">
                          {!user.is_emergency_admin && (
                            <button title="Edit" onClick={() => setEditUser(user)} className="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-900"><Edit2 className="w-4 h-4" /></button>
                          )}
                          {actorIsAdmin && (
                            <button title="Reset Password" onClick={() => setResetUser(user)} className="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-900"><Key className="w-4 h-4" /></button>
                          )}
                          {user.locked_at ? (
                            <button title="Unlock Account" onClick={() => unlockUser.mutate(user)}
                              className="p-1.5 rounded hover:bg-green-50 text-amber-600 hover:text-green-700">
                              <Unlock className="w-4 h-4" />
                            </button>
                          ) : !user.is_emergency_admin ? (
                            <button title={user.is_active ? 'Deactivate' : 'Activate'} onClick={() => toggleActive.mutate(user)}
                              className={clsx('p-1.5 rounded hover:bg-gray-100', user.is_active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800')}>
                              {user.is_active ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                            </button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {data.meta.last_page > 1 && (
              <div className="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm text-gray-500">
                <span>{data.meta.total} total users</span>
                <div className="flex items-center gap-2">
                  <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className={btnSecondary + ' py-1 px-3 text-xs disabled:opacity-40'}>Prev</button>
                  <span>{page} / {data.meta.last_page}</span>
                  <button disabled={page === data.meta.last_page} onClick={() => setPage(p => p + 1)} className={btnSecondary + ' py-1 px-3 text-xs disabled:opacity-40'}>Next</button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {showCreate && <UserFormModal onClose={() => setShowCreate(false)} onSaved={refresh} actorIsAdmin={actorIsAdmin} />}
      {editUser   && <UserFormModal initial={editUser} onClose={() => setEditUser(null)} onSaved={refresh} actorIsAdmin={actorIsAdmin} />}
      {resetUser  && <ResetPasswordModal user={resetUser} onClose={() => setResetUser(null)} />}
    </div>
  )
}

// ── Group form modal ──────────────────────────────────────────────────────────

function GroupFormModal({ initial, groups, onClose, onSaved }: { initial?: Group | null; groups: Group[]; onClose: () => void; onSaved: () => void }) {
  const isEdit = !!initial
  const [form, setForm] = useState({ name: initial?.name ?? '', type: (initial?.type ?? 'department') as GroupType, parent_id: initial?.parent_id ?? '', is_active: initial?.is_active ?? true })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const submit = async () => {
    setErrors({})
    if (!form.name.trim()) return setErrors({ name: 'Name is required.' })
    setSaving(true)
    try {
      const payload = { name: form.name, type: form.type, is_active: form.is_active, parent_id: form.parent_id || null }
      if (isEdit) { await api.patch(`/groups/${initial!.id}`, payload) } else { await api.post('/groups', payload) }
      onSaved(); onClose()
    } catch (err: any) {
      const errs = err?.response?.data?.errors ?? {}
      const flat: Record<string, string> = {}
      Object.entries(errs).forEach(([k, v]) => { flat[k] = Array.isArray(v) ? (v as string[])[0] : String(v) })
      if (err?.response?.data?.message && !Object.keys(flat).length) flat._general = err.response.data.message
      setErrors(flat)
    } finally { setSaving(false) }
  }

  return (
    <Modal title={isEdit ? 'Edit Group' : 'Create Group'} onClose={onClose}>
      <div className="space-y-4">
        {errors._general && <p className="text-sm text-red-600 bg-red-50 rounded px-3 py-2">{errors._general}</p>}
        <FormField label="Group Name" error={errors.name}>
          <input className={inputCls} value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} />
        </FormField>
        <FormField label="Type">
          <select className={inputCls} value={form.type} onChange={e => setForm(f => ({ ...f, type: e.target.value as GroupType }))}>
            {GROUP_TYPES.map(t => <option key={t} value={t} className="capitalize">{t}</option>)}
          </select>
        </FormField>
        <FormField label="Parent Group (optional)">
          <select className={inputCls} value={form.parent_id} onChange={e => setForm(f => ({ ...f, parent_id: e.target.value }))}>
            <option value="">— None —</option>
            {groups.filter(g => g.id !== initial?.id).map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
          </select>
        </FormField>
        <FormField label="Status">
          <label className="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" checked={form.is_active} onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))} className="rounded" />
            <span className="text-sm text-gray-700">Active</span>
          </label>
        </FormField>
        <div className="flex justify-end gap-3 pt-2">
          <button className={btnSecondary} onClick={onClose}>Cancel</button>
          <button className={btnPrimary} onClick={submit} disabled={saving}>{saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Group'}</button>
        </div>
      </div>
    </Modal>
  )
}

// ── Manage members modal ──────────────────────────────────────────────────────

function ManageMembersModal({ group, onClose }: { group: Group; onClose: () => void }) {
  const qc = useQueryClient()
  const [userSearch, setUserSearch] = useState('')
  const [addSearch, setAddSearch] = useState('')

  const { data: members, isLoading } = useQuery({
    queryKey: ['group-members', group.id],
    queryFn: async () => {
      const res = await api.get<{ data: GroupMember[] }>(`/groups/${group.id}/members`)
      return res.data.data
    },
  })

  const { data: allUsers } = useQuery({
    queryKey: ['users-picker', addSearch],
    queryFn: async () => {
      const p = new URLSearchParams({ per_page: '30' })
      if (addSearch) p.set('search', addSearch)
      const res = await api.get<PaginatedResponse<User>>(`/users?${p}`)
      return res.data.data
    },
    enabled: addSearch.length > 0,
  })

  const addMember = useMutation({
    mutationFn: (userId: string) => api.post(`/groups/${group.id}/members`, { user_ids: [userId] }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['group-members', group.id] }),
  })

  const removeMember = useMutation({
    mutationFn: (userId: string) => api.delete(`/groups/${group.id}/members/${userId}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['group-members', group.id] }),
  })

  const memberIds = new Set(members?.map(m => m.id) ?? [])
  const filtered = (members ?? []).filter(m =>
    !userSearch || m.name.toLowerCase().includes(userSearch.toLowerCase()) || m.email.toLowerCase().includes(userSearch.toLowerCase())
  )

  return (
    <Modal title={`Members — ${group.name}`} onClose={onClose} size="lg">
      <div className="flex gap-5">
        {/* Left: current members */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between mb-3">
            <p className="text-sm font-medium text-gray-700">
              Current Members <span className="ml-1 text-gray-400">({members?.length ?? 0})</span>
            </p>
          </div>
          <div className="relative mb-2">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
            <input className="w-full border border-gray-300 rounded-lg pl-8 pr-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600"
              placeholder="Filter members…" value={userSearch} onChange={e => setUserSearch(e.target.value)} />
          </div>
          {isLoading ? (
            <p className="text-sm text-center text-gray-400 py-10">Loading…</p>
          ) : !filtered.length ? (
            <p className="text-sm text-center text-gray-400 py-10">No members yet.</p>
          ) : (
            <div className="space-y-1 max-h-72 overflow-y-auto pr-1">
              {filtered.map(m => (
                <div key={m.id} className="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 border border-transparent hover:border-gray-200">
                  <div className="flex items-center gap-2 min-w-0">
                    <div className="w-7 h-7 rounded-full bg-brand-800 text-white flex items-center justify-center text-xs font-semibold flex-shrink-0">
                      {m.name.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">{m.name}</p>
                      <p className="text-xs text-gray-500 truncate">{m.email}</p>
                    </div>
                  </div>
                  <button onClick={() => removeMember.mutate(m.id)}
                    className="ml-2 flex-shrink-0 flex items-center gap-1 px-2 py-1 rounded text-xs text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Remove from group">
                    <Trash2 className="w-3.5 h-3.5" /> Remove
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Divider */}
        <div className="w-px bg-gray-200 self-stretch" />

        {/* Right: add users */}
        <div className="w-64 flex-shrink-0">
          <p className="text-sm font-medium text-gray-700 mb-3">Add Users</p>
          <div className="relative mb-2">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
            <input className="w-full border border-gray-300 rounded-lg pl-8 pr-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600"
              placeholder="Search users…" value={addSearch}
              onChange={e => { setAddSearch(e.target.value) }} />
          </div>
          <div className="space-y-1 max-h-72 overflow-y-auto">
            {(allUsers ?? []).filter(u => !memberIds.has(u.id) && u.is_active).length === 0 && addSearch === '' ? (
              <p className="text-xs text-gray-400 text-center py-6">Type to search users</p>
            ) : (allUsers ?? []).filter(u => !memberIds.has(u.id) && u.is_active).length === 0 ? (
              <p className="text-xs text-gray-400 text-center py-6">No users found</p>
            ) : (
              (allUsers ?? []).filter(u => !memberIds.has(u.id) && u.is_active).map(u => (
                <div key={u.id} className="flex items-center justify-between p-2 rounded-lg border border-gray-200 hover:border-brand-300 hover:bg-brand-50 transition-colors">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">{u.name}</p>
                    <p className="text-xs text-gray-500 truncate">{u.email}</p>
                  </div>
                  <button onClick={() => addMember.mutate(u.id)}
                    className="ml-2 flex-shrink-0 flex items-center gap-1 px-2 py-1 rounded text-xs bg-brand-800 text-white hover:bg-brand-700 transition-colors">
                    <Plus className="w-3 h-3" /> Add
                  </button>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      <div className="flex justify-end pt-4 mt-2 border-t border-gray-100">
        <button className={btnSecondary} onClick={onClose}>Close</button>
      </div>
    </Modal>
  )
}

// ── Groups tab ────────────────────────────────────────────────────────────────

function GroupsTab() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [showCreate, setShowCreate] = useState(false)
  const [editGroup, setEditGroup] = useState<Group | null>(null)
  const [manageGroup, setManageGroup] = useState<Group | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['groups', search],
    queryFn: async () => {
      const p = new URLSearchParams({ per_page: '100' })
      if (search) p.set('search', search)
      const res = await api.get<PaginatedResponse<Group>>(`/groups?${p}`)
      return res.data
    },
  })

  const deleteGroup = useMutation({
    mutationFn: (id: string) => api.delete(`/groups/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['groups'] }),
  })

  const refresh = () => qc.invalidateQueries({ queryKey: ['groups'] })
  const allGroups = data?.data ?? []

  const typeColor: Record<string, string> = {
    department: 'bg-blue-100 text-blue-700',
    faculty:    'bg-purple-100 text-purple-700',
    school:     'bg-amber-100 text-amber-700',
    custom:     'bg-gray-100 text-gray-600',
  }

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600"
            placeholder="Search groups…" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <button className={btnPrimary} onClick={() => setShowCreate(true)}><Plus className="w-4 h-4" /> Create Group</button>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <div className="py-20 text-center text-sm text-gray-400">Loading groups…</div>
        ) : !allGroups.length ? (
          <EmptyState icon={FolderOpen} title="No groups yet" body="Create your first department or faculty group." />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-100">
              <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <tr>
                  <th className="px-5 py-3 text-left">Name</th>
                  <th className="px-5 py-3 text-left">Type</th>
                  <th className="px-5 py-3 text-left">Parent</th>
                  <th className="px-5 py-3 text-left">Members</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {allGroups.map(group => (
                  <tr key={group.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-5 py-3">
                      <button className="group flex items-center gap-1 text-sm font-medium text-brand-800 hover:text-brand-600 hover:underline text-left" onClick={() => setManageGroup(group)}>
                        {group.name}
                        <Users className="w-3.5 h-3.5 opacity-0 group-hover:opacity-60 transition-opacity" />
                      </button>
                      <p className="text-xs text-gray-400 font-mono">{group.slug}</p>
                    </td>
                    <td className="px-5 py-3">
                      <span className={clsx('inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize', typeColor[group.type] ?? 'bg-gray-100 text-gray-600')}>{group.type}</span>
                    </td>
                    <td className="px-5 py-3 text-sm text-gray-500">{group.parent?.name ?? '—'}</td>
                    <td className="px-5 py-3">
                      <button className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-brand-700 hover:underline" onClick={() => setManageGroup(group)}>
                        <Users className="w-3.5 h-3.5" />
                        {group.users_count ?? 0} {(group.users_count ?? 0) === 1 ? 'member' : 'members'}
                      </button>
                    </td>
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-1.5">
                        <StatusDot active={group.is_active} />
                        <span className="text-sm text-gray-600">{group.is_active ? 'Active' : 'Inactive'}</span>
                      </div>
                    </td>
                    <td className="px-5 py-3">
                      <div className="flex items-center justify-end gap-1">
                        <button title="Edit Group" onClick={() => setEditGroup(group)} className="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-900"><Edit2 className="w-4 h-4" /></button>
                        <button title="Delete Group" onClick={() => { if (window.confirm(`Delete "${group.name}"?`)) deleteGroup.mutate(group.id) }}
                          className="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600"><Trash2 className="w-4 h-4" /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {showCreate  && <GroupFormModal groups={allGroups} onClose={() => setShowCreate(false)} onSaved={refresh} />}
      {editGroup   && <GroupFormModal initial={editGroup} groups={allGroups} onClose={() => setEditGroup(null)} onSaved={refresh} />}
      {manageGroup && <ManageMembersModal group={manageGroup} onClose={() => setManageGroup(null)} />}
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

type Tab = 'users' | 'groups'

export default function UsersPage() {
  const [tab, setTab] = useState<Tab>('users')

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">User Management</h1>
        <p className="text-sm text-gray-500 mt-1">Manage portal users, groups, roles and access</p>
      </div>

      {/* Tab switcher */}
      <div className="flex gap-1 p-1 bg-gray-100 rounded-lg w-fit mb-6">
        {([
          { key: 'users' as Tab,  label: 'Users',  Icon: Users },
          { key: 'groups' as Tab, label: 'Groups', Icon: FolderOpen },
        ]).map(({ key, label, Icon }) => (
          <button key={key} onClick={() => setTab(key)}
            className={clsx('flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-colors',
              tab === key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700')}>
            <Icon className="w-4 h-4" />{label}
          </button>
        ))}
      </div>

      {tab === 'users'  && <UsersTab />}
      {tab === 'groups' && <GroupsTab />}
    </div>
  )
}
