import { useState, useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { X, User, Lock, Loader2, CheckCircle, AlertCircle, LinkIcon, Unlink } from 'lucide-react'
import { useAuthStore } from '../../stores/authStore'
import api from '../../lib/axios'
import type { AuthUser } from '../../types/auth'

type Tab = 'profile' | 'password' | 'accounts'

interface SsoIdentity {
  id: string
  provider_id: string
  provider_name: string
  provider_email: string | null
  linked_at: string
}

export default function ProfileModal() {
  const user = useAuthStore((s) => s.user)
  const profileOpen = useAuthStore((s) => s.profileOpen)
  const closeProfile = useAuthStore((s) => s.closeProfile)
  const updateUser = useAuthStore((s) => s.updateUser)
  useQueryClient()

  const [tab, setTab] = useState<Tab>('profile')

  // Profile fields
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [displayName, setDisplayName] = useState('')
  const [organization, setOrganization] = useState('')
  const [orgRole, setOrgRole] = useState('')

  // Password fields
  const [currentPw, setCurrentPw] = useState('')
  const [newPw, setNewPw] = useState('')
  const [confirmPw, setConfirmPw] = useState('')

  const [successMsg, setSuccessMsg] = useState('')
  const [errorMsg, setErrorMsg] = useState('')

  // Sync form whenever modal opens or user changes
  useEffect(() => {
    if (profileOpen && user) {
      setFirstName(user.first_name ?? '')
      setLastName(user.last_name ?? '')
      setDisplayName(user.name ?? '')
      setOrganization(user.organization ?? '')
      setOrgRole(user.org_role ?? '')
      setCurrentPw('')
      setNewPw('')
      setConfirmPw('')
      setSuccessMsg('')
      setErrorMsg('')
      setTab('profile')
    }
  }, [profileOpen])

  const profileMutation = useMutation({
    mutationFn: () =>
      api.patch<AuthUser>('/auth/profile', {
        first_name: firstName || null,
        last_name: lastName || null,
        name: displayName || null,
        organization: organization || null,
        org_role: orgRole || null,
      }).then((r) => r.data),
    onSuccess: (updated) => {
      updateUser(updated as AuthUser)
      setSuccessMsg('Profile updated successfully.')
      setErrorMsg('')
    },
    onError: (err: any) => {
      setErrorMsg(err?.response?.data?.message ?? 'Failed to update profile.')
      setSuccessMsg('')
    },
  })

  const passwordMutation = useMutation({
    mutationFn: () =>
      api.patch('/auth/password', {
        current_password: currentPw,
        password: newPw,
        password_confirmation: confirmPw,
      }).then((r) => r.data),
    onSuccess: () => {
      setSuccessMsg('Password changed successfully.')
      setErrorMsg('')
      setCurrentPw('')
      setNewPw('')
      setConfirmPw('')
    },
    onError: (err: any) => {
      setErrorMsg(err?.response?.data?.message ?? 'Failed to change password.')
      setSuccessMsg('')
    },
  })

  // SSO identities — always fetch when modal opens so we can check if password should be disabled
  const { data: identitiesData, refetch: refetchIdentities } = useQuery<{ data: SsoIdentity[] }>({
    queryKey: ['sso-identities'],
    queryFn: () => api.get('/auth/sso/identities').then((r) => r.data),
    enabled: profileOpen,
  })
  const identities = identitiesData?.data ?? []
  const hasSso = identities.length > 0

  const [unlinkErr, setUnlinkErr] = useState('')
  const [unlinking, setUnlinking] = useState<string | null>(null)

  const unlinkIdentity = async (id: string) => {
    setUnlinkErr('')
    setUnlinking(id)
    try {
      await api.delete(`/auth/sso/identities/${id}`)
      refetchIdentities()
    } catch {
      setUnlinkErr('Failed to unlink account.')
    } finally {
      setUnlinking(null)
    }
  }

  if (!profileOpen || !user) return null

  const initials = ((user.name || user.email) || '?')[0].toUpperCase()

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={closeProfile} />

      {/* Modal */}
      <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        {/* Header */}
        <div className="bg-brand-800 px-6 py-5 flex items-center gap-4">
          <div className="w-12 h-12 rounded-full bg-brand-600 flex items-center justify-center text-lg font-bold text-white flex-shrink-0">
            {initials}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-white font-semibold truncate">{user.name}</p>
            <p className="text-brand-300 text-sm truncate">{user.email}</p>
          </div>
          <button onClick={closeProfile} className="text-brand-300 hover:text-white p-1 flex-shrink-0">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-gray-100">
          <button
            onClick={() => { setTab('profile'); setSuccessMsg(''); setErrorMsg('') }}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium transition-colors ${
              tab === 'profile'
                ? 'text-blue-600 border-b-2 border-blue-600'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            <User className="w-4 h-4" /> My Profile
          </button>
          <button
            onClick={() => { if (!hasSso) { setTab('password'); setSuccessMsg(''); setErrorMsg('') } }}
            disabled={hasSso}
            title={hasSso ? 'Password login is disabled — this account uses SSO' : undefined}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium transition-colors ${
              tab === 'password'
                ? 'text-blue-600 border-b-2 border-blue-600'
                : hasSso
                  ? 'text-gray-300 cursor-not-allowed'
                  : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            <Lock className="w-4 h-4" /> Change Password
          </button>
          <button
            onClick={() => { setTab('accounts'); setSuccessMsg(''); setErrorMsg(''); setUnlinkErr('') }}
            className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium transition-colors ${
              tab === 'accounts'
                ? 'text-blue-600 border-b-2 border-blue-600'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            <LinkIcon className="w-4 h-4" /> Accounts
          </button>
        </div>

        {/* Body */}
        <div className="px-6 py-5 space-y-4">
          {/* Feedback */}
          {successMsg && (
            <div className="flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
              <CheckCircle className="w-4 h-4 flex-shrink-0" />
              {successMsg}
            </div>
          )}
          {errorMsg && (
            <div className="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              <AlertCircle className="w-4 h-4 flex-shrink-0" />
              {errorMsg}
            </div>
          )}

          {tab === 'profile' ? (
            <>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                  <input
                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value={firstName}
                    onChange={(e) => setFirstName(e.target.value)}
                    placeholder="First name"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                  <input
                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value={lastName}
                    onChange={(e) => setLastName(e.target.value)}
                    placeholder="Last name"
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Display Name</label>
                <input
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value={displayName}
                  onChange={(e) => setDisplayName(e.target.value)}
                  placeholder="How your name appears"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Email</label>
                <input
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-400 cursor-not-allowed"
                  value={user.email}
                  disabled
                />
                <p className="text-xs text-gray-400 mt-1">Contact an administrator to change your email.</p>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Organization</label>
                <input
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value={organization}
                  onChange={(e) => setOrganization(e.target.value)}
                  placeholder="Your institution or department"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Title / Role</label>
                <input
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value={orgRole}
                  onChange={(e) => setOrgRole(e.target.value)}
                  placeholder="e.g. Associate Professor"
                />
              </div>
              <div className="flex justify-end pt-1">
                <button
                  onClick={() => profileMutation.mutate()}
                  disabled={profileMutation.isPending}
                  className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                >
                  {profileMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
                  Save Changes
                </button>
              </div>
            </>
          ) : tab === 'password' ? (
            hasSso ? (
              <div className="text-center py-8">
                <Lock className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                <p className="text-sm font-medium text-gray-700 mb-1">Password login is disabled</p>
                <p className="text-xs text-gray-400">
                  This account uses SSO for authentication. To change your password, please use your identity provider's settings.
                </p>
                <div className="mt-4 space-y-2">
                  {identities.map((id) => (
                    <div key={id.id} className="flex items-center justify-center gap-2 text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                      <LinkIcon className="w-3.5 h-3.5" />
                      Linked via {id.provider_name}{id.provider_email ? ` (${id.provider_email})` : ''}
                    </div>
                  ))}
                </div>
              </div>
            ) : (
            <>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Current Password</label>
                <input
                  type="password"
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value={currentPw}
                  onChange={(e) => setCurrentPw(e.target.value)}
                  placeholder="Enter current password"
                  autoComplete="current-password"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">New Password</label>
                <input
                  type="password"
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value={newPw}
                  onChange={(e) => setNewPw(e.target.value)}
                  placeholder="New password"
                  autoComplete="new-password"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Confirm New Password</label>
                <input
                  type="password"
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  value={confirmPw}
                  onChange={(e) => setConfirmPw(e.target.value)}
                  placeholder="Repeat new password"
                  autoComplete="new-password"
                />
              </div>
              <div className="flex justify-end pt-1">
                <button
                  onClick={() => passwordMutation.mutate()}
                  disabled={passwordMutation.isPending || !currentPw || !newPw || !confirmPw}
                  className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                >
                  {passwordMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
                  Change Password
                </button>
              </div>
            </>
            )
          ) : tab === 'accounts' ? (
            <div>
              <p className="text-sm text-gray-600 mb-4">
                Linked SSO accounts let you sign in via your institution's identity provider.
              </p>
              {unlinkErr && (
                <div className="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">
                  <AlertCircle className="w-4 h-4 flex-shrink-0" />
                  {unlinkErr}
                </div>
              )}
              {identities.length === 0 ? (
                <p className="text-sm text-gray-400 text-center py-6">No linked SSO accounts.</p>
              ) : (
                <div className="space-y-2">
                  {identities.map((identity) => (
                    <div key={identity.id} className="flex items-center justify-between border border-gray-200 rounded-lg px-4 py-3">
                      <div>
                        <p className="text-sm font-medium text-gray-800">{identity.provider_name}</p>
                        {identity.provider_email && (
                          <p className="text-xs text-gray-400">{identity.provider_email}</p>
                        )}
                        <p className="text-xs text-gray-400">
                          Linked {new Date(identity.linked_at).toLocaleDateString()}
                        </p>
                      </div>
                      <button
                        onClick={() => unlinkIdentity(identity.id)}
                        disabled={unlinking === identity.id}
                        className="flex items-center gap-1.5 px-3 py-1.5 border border-red-200 text-red-600 hover:bg-red-50 rounded-lg text-xs font-medium transition-colors disabled:opacity-50"
                        title="Unlink this SSO account"
                      >
                        {unlinking === identity.id
                          ? <Loader2 className="w-3.5 h-3.5 animate-spin" />
                          : <Unlink className="w-3.5 h-3.5" />
                        }
                        Unlink
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  )
}
