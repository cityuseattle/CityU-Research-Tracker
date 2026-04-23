import { useState, useEffect, useRef } from 'react'
import {
  Building2, Mail, Shield, Bell, Sliders, Key,
  Save, Send, Plus, Pencil, Trash2, CheckCircle2,
  XCircle, Eye, EyeOff, ChevronRight, AlertCircle,
  Upload, Loader2, ToggleLeft, ToggleRight, HardDrive,
} from 'lucide-react'
import api from '../lib/axios'

// ── Types ────────────────────────────────────────────────────────────────────

interface OrgSettings {
  org_name: string
  portal_name: string
  org_short_name: string | null
  primary_color: string
  accent_color: string | null
  timezone: string
  locale: string
  date_format: string
  footer_text: string | null
  support_email: string | null
  allow_public_registration: boolean
  archive_after_days: number | null
  logo_url?: string | null
}

interface EmailSettings {
  id: number
  driver: 'log' | 'smtp' | 'ses' | 'sendmail'
  host: string | null
  port: number
  encryption: string | null
  username: string | null
  password_set: boolean
  from_address: string
  from_name: string
  reply_to: string | null
  is_verified: boolean
  ses_region: string | null
  ses_key_set: boolean
  ses_secret_set: boolean
}

interface PasswordPolicy {
  id: number
  min_length: number
  require_uppercase: boolean
  require_number: boolean
  require_special: boolean
  expiry_days: number | null
  history_count: number
  max_login_attempts: number
  lockout_duration_minutes: number
  session_timeout_minutes: number
  require_2fa: boolean
}

interface FeatureFlags { [key: string]: boolean }

interface NotificationTemplate {
  id: string
  event_type: string
  subject: string
  body_html: string
  body_text: string
  is_active: boolean
  variables: string[]
}

interface SsoProvider {
  id: string
  name: string
  protocol: 'OIDC' | 'OAUTH2' | 'SAML2'
  is_enabled: boolean
  is_default: boolean
  button_label: string | null
  auto_provision_users: boolean
  default_role: string
  config: Record<string, string>
}

// ── Nav tabs ──────────────────────────────────────────────────────────────────

const TABS = [
  { id: 'org',           label: 'Organization',        icon: Building2 },
  { id: 'email',         label: 'Email',               icon: Mail },
  { id: 'sso',           label: 'SSO Providers',        icon: Key },
  { id: 'security',      label: 'Password & Security', icon: Shield },
  { id: 'notifications', label: 'Notifications',       icon: Bell },
  { id: 'flags',         label: 'Feature Flags',       icon: Sliders },
  { id: 'backup',        label: 'Backup & Archive',    icon: HardDrive },
] as const

type TabId = typeof TABS[number]['id']

// ── Helpers ───────────────────────────────────────────────────────────────────

function SaveBar({ saving, saved, onSave }: { saving: boolean; saved: boolean; onSave: () => void }) {
  return (
    <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 mt-6">
      {saved && (
        <span className="flex items-center gap-1.5 text-sm text-green-600">
          <CheckCircle2 className="w-4 h-4" /> Saved
        </span>
      )}
      <button
        onClick={onSave}
        disabled={saving}
        className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60"
      >
        {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
        Save Changes
      </button>
    </div>
  )
}

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="mb-6">
      <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
      {subtitle && <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>}
    </div>
  )
}

function Field({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-1 gap-1">
      <label className="text-sm font-medium text-gray-700">{label}</label>
      {hint && <p className="text-xs text-gray-400 -mt-0.5">{hint}</p>}
      {children}
    </div>
  )
}

const INPUT = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'

// ── Organization Tab ──────────────────────────────────────────────────────────

function OrgTab() {
  const [data, setData] = useState<OrgSettings | null>(null)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState('')
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    api.get('/system/organization').then(r => setData(r.data)).catch(() => setError('Failed to load'))
  }, [])

  const save = async () => {
    if (!data) return
    setSaving(true); setSaved(false); setError('')
    try {
      const r = await api.patch('/system/organization', {
        org_name: data.org_name,
        portal_name: data.portal_name,
        org_short_name: data.org_short_name,
        primary_color: data.primary_color,
        accent_color: data.accent_color,
        timezone: data.timezone,
        locale: data.locale,
        date_format: data.date_format,
        footer_text: data.footer_text,
        support_email: data.support_email,
        allow_public_registration: data.allow_public_registration,
        archive_after_days: data.archive_after_days,
      })
      setData(r.data); setSaved(true)
      setTimeout(() => setSaved(false), 3000)
    } catch { setError('Save failed.') }
    finally { setSaving(false) }
  }

  const uploadLogo = async (file: File) => {
    setUploading(true)
    const form = new FormData()
    form.append('logo', file)
    try {
      const r = await api.post('/system/organization/logo', form, { headers: { 'Content-Type': 'multipart/form-data' } })
      setData(prev => prev ? { ...prev, logo_url: r.data.logo_url } : prev)
    } catch { setError('Logo upload failed.') }
    finally { setUploading(false) }
  }

  if (!data) return <div className="flex justify-center py-20"><Loader2 className="w-6 h-6 animate-spin text-gray-400" /></div>

  const set = (k: keyof OrgSettings, v: unknown) => setData(prev => prev ? { ...prev, [k]: v } : prev)

  return (
    <div>
      <SectionHeader title="Organization Settings" subtitle="Branding, regional settings, and portal behaviour." />
      {error && <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg flex gap-2"><AlertCircle className="w-4 h-4 mt-0.5" />{error}</div>}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        <Field label="Organization Name">
          <input className={INPUT} value={data.org_name} onChange={e => set('org_name', e.target.value)} />
        </Field>
        <Field label="Portal Name" hint="Displayed next to logo in the header (e.g. Research Review Portal)">
          <input className={INPUT} value={data.portal_name ?? ''} onChange={e => set('portal_name', e.target.value)} placeholder="Research Review Portal" />
        </Field>
        <Field label="Short Name / Abbreviation">
          <input className={INPUT} value={data.org_short_name ?? ''} onChange={e => set('org_short_name', e.target.value)} placeholder="e.g. CityU" />
        </Field>
        <Field label="Support Email">
          <input className={INPUT} type="email" value={data.support_email ?? ''} onChange={e => set('support_email', e.target.value)} />
        </Field>
        <Field label="Footer Text">
          <input className={INPUT} value={data.footer_text ?? ''} onChange={e => set('footer_text', e.target.value)} />
        </Field>
        <Field label="Primary Color">
          <div className="flex gap-2 items-center">
            <input type="color" value={data.primary_color} onChange={e => set('primary_color', e.target.value)} className="w-10 h-10 rounded border border-gray-200 p-0.5 cursor-pointer" />
            <input className={INPUT} value={data.primary_color} onChange={e => set('primary_color', e.target.value)} />
          </div>
        </Field>
        <Field label="Accent Color">
          <div className="flex gap-2 items-center">
            <input type="color" value={data.accent_color ?? '#6366f1'} onChange={e => set('accent_color', e.target.value)} className="w-10 h-10 rounded border border-gray-200 p-0.5 cursor-pointer" />
            <input className={INPUT} value={data.accent_color ?? ''} onChange={e => set('accent_color', e.target.value)} />
          </div>
        </Field>
        <Field label="Timezone">
          <select className={INPUT} value={data.timezone} onChange={e => set('timezone', e.target.value)}>
            {['UTC','Asia/Hong_Kong','Asia/Singapore','America/New_York','America/Los_Angeles','Europe/London','Europe/Paris'].map(t => (
              <option key={t} value={t}>{t}</option>
            ))}
          </select>
        </Field>
        <Field label="Date Format">
          <select className={INPUT} value={data.date_format} onChange={e => set('date_format', e.target.value)}>
            {['YYYY-MM-DD','DD/MM/YYYY','MM/DD/YYYY','DD-MMM-YYYY'].map(f => (
              <option key={f} value={f}>{f}</option>
            ))}
          </select>
        </Field>
        <Field label="Archive submissions after (days)" hint="Leave empty to never auto-archive.">
          <input className={INPUT} type="number" min={1} value={data.archive_after_days ?? ''} onChange={e => set('archive_after_days', e.target.value ? parseInt(e.target.value) : null)} />
        </Field>
        <Field label="Allow Public Registration">
          <label className="flex items-center gap-2 mt-1 cursor-pointer">
            <input type="checkbox" checked={data.allow_public_registration} onChange={e => set('allow_public_registration', e.target.checked)} className="w-4 h-4 rounded" />
            <span className="text-sm text-gray-600">Enable self-registration for students</span>
          </label>
        </Field>
      </div>

      {/* Logo upload */}
      <div className="mt-5">
        <p className="text-sm font-medium text-gray-700 mb-2">Organization Logo</p>
        <div className="flex items-center gap-4">
          {data.logo_url && <img src={data.logo_url} alt="Logo" className="h-12 w-auto border rounded object-contain p-1" />}
          <button
            onClick={() => fileRef.current?.click()}
            disabled={uploading}
            className="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 disabled:opacity-60"
          >
            {uploading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Upload className="w-4 h-4" />}
            Upload Logo
          </button>
          <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={e => e.target.files?.[0] && uploadLogo(e.target.files[0])} />
          <span className="text-xs text-gray-400">JPG, PNG, SVG, WebP · max 2 MB</span>
        </div>
      </div>

      <SaveBar saving={saving} saved={saved} onSave={save} />
    </div>
  )
}

// ── Email Tab ─────────────────────────────────────────────────────────────────

function EmailTab() {
  const [data, setData] = useState<EmailSettings | null>(null)
  const [password, setPassword] = useState('')
  const [showPwd, setShowPwd] = useState(false)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ ok: boolean; msg: string } | null>(null)
  const [testTo, setTestTo] = useState('')
  const [error, setError] = useState('')

  useEffect(() => { api.get('/system/email').then(r => setData(r.data)).catch(() => setError('Failed to load')) }, [])

  const save = async () => {
    if (!data) return
    setSaving(true); setSaved(false); setError('')
    try {
      const payload: Record<string, unknown> = {
        driver: data.driver, host: data.host, port: data.port,
        encryption: data.encryption, username: data.username,
        from_address: data.from_address, from_name: data.from_name,
        reply_to: data.reply_to, ses_region: data.ses_region,
      }
      if (password) payload['password_enc'] = password
      const r = await api.patch('/system/email', payload)
      setData(r.data); setPassword(''); setSaved(true)
      setTimeout(() => setSaved(false), 3000)
    } catch { setError('Save failed.') }
    finally { setSaving(false) }
  }

  const test = async () => {
    setTesting(true); setTestResult(null)
    try {
      const r = await api.post('/system/email/test', testTo ? { to: testTo } : {})
      setTestResult({ ok: true, msg: r.data.message })
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Test failed.'
      setTestResult({ ok: false, msg })
    } finally { setTesting(false) }
  }

  if (!data) return <div className="flex justify-center py-20"><Loader2 className="w-6 h-6 animate-spin text-gray-400" /></div>

  const set = (k: keyof EmailSettings, v: unknown) => setData(prev => prev ? { ...prev, [k]: v } : prev)

  return (
    <div>
      <SectionHeader title="Email Configuration" subtitle="Configure outbound mail delivery for notifications and invites." />
      {error && <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{error}</div>}

      {data.is_verified && (
        <div className="mb-4 flex items-center gap-2 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
          <CheckCircle2 className="w-4 h-4" /> Email configuration verified — test was successful.
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        <Field label="Driver">
          <select className={INPUT} value={data.driver} onChange={e => set('driver', e.target.value)}>
            <option value="log">Log (development only)</option>
            <option value="smtp">SMTP</option>
            <option value="ses">Amazon SES</option>
            <option value="sendmail">Sendmail</option>
          </select>
        </Field>
        <Field label="From Name">
          <input className={INPUT} value={data.from_name} onChange={e => set('from_name', e.target.value)} />
        </Field>
        <Field label="From Address">
          <input className={INPUT} type="email" value={data.from_address} onChange={e => set('from_address', e.target.value)} />
        </Field>
        <Field label="Reply-To">
          <input className={INPUT} type="email" value={data.reply_to ?? ''} onChange={e => set('reply_to', e.target.value)} />
        </Field>
      </div>

      {data.driver === 'smtp' && (
        <div className="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
          <Field label="SMTP Host">
            <input className={INPUT} value={data.host ?? ''} onChange={e => set('host', e.target.value)} placeholder="smtp.example.com" />
          </Field>
          <Field label="Port">
            <input className={INPUT} type="number" value={data.port} onChange={e => set('port', parseInt(e.target.value) || 587)} />
          </Field>
          <Field label="Encryption">
            <select className={INPUT} value={data.encryption ?? ''} onChange={e => set('encryption', e.target.value)}>
              <option value="">None</option>
              <option value="tls">TLS (STARTTLS)</option>
              <option value="ssl">SSL</option>
            </select>
          </Field>
          <Field label="Username">
            <input className={INPUT} value={data.username ?? ''} onChange={e => set('username', e.target.value)} />
          </Field>
          <Field label="Password" hint={data.password_set ? 'Leave blank to keep existing password.' : ''}>
            <div className="relative">
              <input
                className={INPUT + ' pr-10'}
                type={showPwd ? 'text' : 'password'}
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder={data.password_set ? '••••••••' : 'Enter password'}
              />
              <button type="button" onClick={() => setShowPwd(p => !p)} className="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </Field>
        </div>
      )}

      {data.driver === 'ses' && (
        <div className="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
          <Field label="AWS Region">
            <input className={INPUT} value={data.ses_region ?? ''} onChange={e => set('ses_region', e.target.value)} placeholder="us-east-1" />
          </Field>
          <Field label="Access Key" hint={data.ses_key_set ? 'Leave blank to keep existing key.' : ''}>
            <input className={INPUT} placeholder={data.ses_key_set ? '[set — leave blank to keep]' : 'AKIA...'} />
          </Field>
          <Field label="Secret Key" hint={data.ses_secret_set ? 'Leave blank to keep existing secret.' : ''}>
            <input className={INPUT} type="password" placeholder={data.ses_secret_set ? '[set — leave blank to keep]' : 'Enter secret'} />
          </Field>
        </div>
      )}

      {/* Test send */}
      <div className="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-xl">
        <p className="text-sm font-medium text-gray-700 mb-3">Send Test Email</p>
        <div className="flex gap-2">
          <input className={INPUT + ' flex-1'} type="email" value={testTo} onChange={e => setTestTo(e.target.value)} placeholder="recipient@example.com (leave blank = your account)" />
          <button onClick={test} disabled={testing} className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-60 whitespace-nowrap">
            {testing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
            Send Test
          </button>
        </div>
        {testResult && (
          <div className={`mt-2 flex items-center gap-2 text-sm ${testResult.ok ? 'text-green-700' : 'text-red-700'}`}>
            {testResult.ok ? <CheckCircle2 className="w-4 h-4" /> : <XCircle className="w-4 h-4" />}
            {testResult.msg}
          </div>
        )}
      </div>

      <SaveBar saving={saving} saved={saved} onSave={save} />
    </div>
  )
}

// ── SSO Tab ───────────────────────────────────────────────────────────────────

type SsoEditing = {
  name: string; protocol: 'OIDC' | 'OAUTH2' | 'SAML2'; is_enabled: boolean; is_default: boolean;
  button_label: string | null; auto_provision_users: boolean; default_role: string;
  config: Record<string, string>;
}

const EMPTY_SSO: SsoEditing = {
  name: '', protocol: 'OIDC', is_enabled: false, is_default: false,
  button_label: '', auto_provision_users: true, default_role: 'student',
  config: { tenant_id: '', client_id: '', client_secret: '', scopes: 'openid email profile' },
}

function SsoTab() {
  const [providers, setProviders] = useState<SsoProvider[]>([])
  const [editing, setEditing] = useState<SsoEditing | null>(null)
  const [editId, setEditId] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const load = () => api.get('/system/sso').then(r => setProviders(r.data.data)).catch(() => setError('Failed to load'))
  useEffect(() => { load() }, [])

  const save = async () => {
    if (!editing) return
    setSaving(true); setError('')
    try {
      if (editId) {
        await api.patch(`/system/sso/${editId}`, editing)
      } else {
        await api.post('/system/sso', editing)
      }
      await load(); setEditing(null); setEditId(null)
    } catch (e: unknown) {
      setError((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Save failed.')
    } finally { setSaving(false) }
  }

  const del = async (id: string) => {
    if (!confirm('Delete this SSO provider?')) return
    await api.delete(`/system/sso/${id}`).catch(() => {})
    load()
  }

  const edit = (p: SsoProvider) => { setEditing({ ...p }); setEditId(p.id) }
  const add  = () => { setEditing({ ...EMPTY_SSO, config: { ...EMPTY_SSO.config } }); setEditId(null) }

  const setField = (k: string, v: unknown) => setEditing(prev => prev ? { ...prev, [k]: v } : prev)
  const setCfg   = (k: string, v: string)  => setEditing(prev => prev ? { ...prev, config: { ...prev.config, [k]: v } } : prev)

  return (
    <div>
      <SectionHeader title="SSO Providers" subtitle="Configure OIDC/OAuth2 providers for single sign-on." />
      {error && <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{error}</div>}

      {!editing && (
        <>
          <div className="space-y-3 mb-4">
            {providers.length === 0 && <p className="text-sm text-gray-400 py-8 text-center">No SSO providers configured.</p>}
            {providers.map(p => (
              <div key={p.id} className="flex items-center justify-between p-4 border border-gray-200 rounded-xl bg-white">
                <div className="flex items-center gap-3">
                  <div className={`w-2 h-2 rounded-full ${p.is_enabled ? 'bg-green-500' : 'bg-gray-300'}`} />
                  <div>
                    <p className="text-sm font-medium text-gray-900">{p.name}</p>
                    <p className="text-xs text-gray-400">{p.protocol}{p.is_default ? ' · Default' : ''}</p>
                  </div>
                </div>
                <div className="flex gap-2">
                  <button onClick={() => edit(p)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><Pencil className="w-4 h-4" /></button>
                  <button onClick={() => del(p.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"><Trash2 className="w-4 h-4" /></button>
                </div>
              </div>
            ))}
          </div>
          <button onClick={add} className="flex items-center gap-2 px-4 py-2 border border-blue-300 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50">
            <Plus className="w-4 h-4" /> Add Provider
          </button>
          {providers.length > 0 && (
            <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
              <strong>Callback URL pattern:</strong>{' '}
              <code className="bg-blue-100 px-1 rounded">{window.location.origin}/api/sso/{'{'}<em>provider-id</em>{'}'}/callback</code>
              {' '}— register this in your IdP application settings.
            </div>
          )}
        </>
      )}

      {editing && (
        <div className="border border-gray-200 rounded-xl p-5 bg-gray-50">
          <h3 className="font-medium text-gray-900 mb-4">{editId ? 'Edit Provider' : 'New Provider'}</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Display Name">
              <input className={INPUT} value={editing.name} onChange={e => setField('name', e.target.value)} placeholder="Microsoft Entra" />
            </Field>
            <Field label="Protocol">
              <select className={INPUT} value={editing.protocol} onChange={e => setField('protocol', e.target.value)}>
                <option value="OIDC">OIDC</option>
                <option value="OAUTH2">OAuth2</option>
                <option value="SAML2">SAML2</option>
              </select>
            </Field>
            <Field label="Button Label">
              <input className={INPUT} value={editing.button_label ?? ''} onChange={e => setField('button_label', e.target.value)} placeholder="Sign in with Microsoft" />
            </Field>
            <Field label="Default Role for Auto-Provisioned Users">
              <select className={INPUT} value={editing.default_role} onChange={e => setField('default_role', e.target.value)}>
                <option value="student">Student</option>
                <option value="reviewer">Reviewer</option>
                <option value="coordinator">Coordinator</option>
              </select>
            </Field>
          </div>

          {(editing.protocol === 'OIDC' || editing.protocol === 'OAUTH2') && (
            <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Tenant ID" hint="For Microsoft Entra: your tenant UUID or domain.">
                <input className={INPUT} value={editing.config.tenant_id ?? ''} onChange={e => setCfg('tenant_id', e.target.value)} placeholder="organizations or your-tenant.onmicrosoft.com" />
              </Field>
              <Field label="Client ID">
                <input className={INPUT} value={editing.config.client_id ?? ''} onChange={e => setCfg('client_id', e.target.value)} />
              </Field>
              <Field label="Client Secret" hint={editing.config.client_secret === '[set]' ? 'Leave blank to keep existing.' : ''}>
                <input className={INPUT} type="password"
                  value={editing.config.client_secret === '[set]' ? '' : editing.config.client_secret ?? ''}
                  onChange={e => setCfg('client_secret', e.target.value)}
                  placeholder={editing.config.client_secret === '[set]' ? '[set — leave blank to keep]' : ''}
                />
              </Field>
              <Field label="Scopes">
                <input className={INPUT} value={editing.config.scopes ?? 'openid email profile'} onChange={e => setCfg('scopes', e.target.value)} />
              </Field>
            </div>
          )}

          <div className="mt-4 flex gap-6">
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={editing.is_enabled} onChange={e => setField('is_enabled', e.target.checked)} className="w-4 h-4 rounded" />
              Enabled
            </label>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={editing.is_default} onChange={e => setField('is_default', e.target.checked)} className="w-4 h-4 rounded" />
              Default provider
            </label>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={editing.auto_provision_users} onChange={e => setField('auto_provision_users', e.target.checked)} className="w-4 h-4 rounded" />
              Auto-provision new users
            </label>
          </div>

          <div className="flex gap-3 mt-5">
            <button onClick={save} disabled={saving} className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60">
              {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />} Save
            </button>
            <button onClick={() => { setEditing(null); setEditId(null) }} className="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

// ── Security / Password Policy Tab ────────────────────────────────────────────

function SecurityTab() {
  const [data, setData] = useState<PasswordPolicy | null>(null)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => { api.get('/system/password-policy').then(r => setData(r.data)).catch(() => setError('Failed to load')) }, [])

  const save = async () => {
    if (!data) return
    setSaving(true); setSaved(false)
    try {
      const r = await api.patch('/system/password-policy', data)
      setData(r.data); setSaved(true)
      setTimeout(() => setSaved(false), 3000)
    } catch { setError('Save failed.') }
    finally { setSaving(false) }
  }

  if (!data) return <div className="flex justify-center py-20"><Loader2 className="w-6 h-6 animate-spin text-gray-400" /></div>
  const set = (k: keyof PasswordPolicy, v: unknown) => setData(prev => prev ? { ...prev, [k]: v } : prev)

  const BoolRow = ({ k, label }: { k: keyof PasswordPolicy; label: string }) => (
    <label className="flex items-center justify-between py-3 border-b border-gray-100 cursor-pointer">
      <span className="text-sm text-gray-700">{label}</span>
      <input type="checkbox" checked={data[k] as boolean} onChange={e => set(k, e.target.checked)} className="w-4 h-4 rounded" />
    </label>
  )

  const NumRow = ({ k, label, min, max }: { k: keyof PasswordPolicy; label: string; min?: number; max?: number }) => (
    <div className="flex items-center justify-between py-3 border-b border-gray-100">
      <span className="text-sm text-gray-700">{label}</span>
      <input className="w-24 border border-gray-200 rounded-lg px-2 py-1 text-sm text-right" type="number" min={min} max={max}
        value={(data[k] as number | null) ?? ''} onChange={e => set(k, e.target.value ? parseInt(e.target.value) : null)} />
    </div>
  )

  return (
    <div>
      <SectionHeader title="Password & Security Policy" subtitle="Rules enforced on all password changes and new accounts." />
      {error && <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{error}</div>}
      <div className="max-w-xl">
        <NumRow k="min_length"               label="Minimum password length"           min={8} max={128} />
        <BoolRow k="require_uppercase"        label="Require uppercase letter" />
        <BoolRow k="require_number"           label="Require number" />
        <BoolRow k="require_special"          label="Require special character" />
        <NumRow k="expiry_days"               label="Password expires after (days, blank = never)" min={0} />
        <NumRow k="history_count"             label="Prevent reuse of last N passwords" min={0} max={24} />
        <NumRow k="max_login_attempts"        label="Max failed login attempts before lockout" min={1} max={20} />
        <NumRow k="lockout_duration_minutes"  label="Lockout duration (minutes)" min={1} />
        <NumRow k="session_timeout_minutes"   label="Session timeout (minutes)" min={5} />
        <BoolRow k="require_2fa"              label="Require 2FA (coming soon)" />
      </div>
      <SaveBar saving={saving} saved={saved} onSave={save} />
    </div>
  )
}

// ── Notifications Tab ─────────────────────────────────────────────────────────

const EVENT_LABELS: Record<string, string> = {
  SUBMISSION_RECEIVED: 'Submission Received',
  STAGE_ASSIGNED:      'Review Assignment',
  REVISION_REQUIRED:   'Revision Required',
  SUBMISSION_ACCEPTED: 'Submission Accepted',
  SUBMISSION_REJECTED: 'Submission Rejected',
  STAGE_OVERDUE:       'Review Overdue',
  APPEAL_SUBMITTED:    'Appeal Submitted',
}

function NotificationsTab() {
  const [templates, setTemplates] = useState<NotificationTemplate[]>([])
  const [selected, setSelected] = useState<NotificationTemplate | null>(null)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => { api.get('/system/notification-templates').then(r => setTemplates(r.data.data)).catch(() => setError('Failed to load')) }, [])

  const pick = (t: NotificationTemplate) => setSelected({ ...t })
  const setF  = (k: keyof NotificationTemplate, v: unknown) => setSelected(prev => prev ? { ...prev, [k]: v } : prev)

  const save = async () => {
    if (!selected) return
    setSaving(true); setSaved(false)
    try {
      const r = await api.patch(`/system/notification-templates/${selected.id}`, {
        subject: selected.subject, body_html: selected.body_html, body_text: selected.body_text, is_active: selected.is_active,
      })
      setTemplates(prev => prev.map(t => t.id === selected.id ? r.data : t))
      setSelected(r.data)
      setSaved(true); setTimeout(() => setSaved(false), 3000)
    } catch { setError('Save failed.') }
    finally { setSaving(false) }
  }

  return (
    <div className="flex gap-0 -mx-6 -mb-6 min-h-[480px]">
      {/* Left list */}
      <div className="w-56 flex-shrink-0 border-r border-gray-200 pt-1">
        {error && <p className="text-xs text-red-600 px-3 py-2">{error}</p>}
        {templates.map(t => (
          <button key={t.id} onClick={() => pick(t)}
            className={`w-full text-left px-4 py-3 border-b border-gray-100 flex items-center gap-2 hover:bg-gray-50 ${selected?.id === t.id ? 'bg-blue-50 border-l-2 border-l-blue-500' : ''}`}>
            <span className={`w-2 h-2 rounded-full flex-shrink-0 ${t.is_active ? 'bg-green-500' : 'bg-gray-300'}`} />
            <span className="text-xs text-gray-700 font-medium">{EVENT_LABELS[t.event_type] ?? t.event_type}</span>
          </button>
        ))}
      </div>

      {/* Right editor */}
      <div className="flex-1 px-6 py-4">
        {!selected ? (
          <div className="flex flex-col items-center justify-center h-64 text-gray-400 text-sm">
            <Bell className="w-8 h-8 mb-2 text-gray-200" /> Select a template to edit
          </div>
        ) : (
          <>
            <div className="mb-4 flex items-center justify-between">
              <h3 className="font-medium text-gray-900">{EVENT_LABELS[selected.event_type] ?? selected.event_type}</h3>
              <label className="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" checked={selected.is_active} onChange={e => setF('is_active', e.target.checked)} className="w-4 h-4 rounded" />
                Active
              </label>
            </div>

            {/* Available variables */}
            <div className="mb-3">
              <p className="text-xs text-gray-400 mb-1">Click to copy variable:</p>
              <div className="flex flex-wrap gap-1">
                {selected.variables.map(v => (
                  <code key={v} className="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 cursor-pointer hover:bg-gray-200"
                    onClick={() => navigator.clipboard.writeText(v)}>
                    {v}
                  </code>
                ))}
              </div>
            </div>

            <div className="space-y-4">
              <Field label="Subject line">
                <input className={INPUT} value={selected.subject} onChange={e => setF('subject', e.target.value)} />
              </Field>
              <Field label="HTML Body" hint="Full HTML email body. Use variables above.">
                <textarea className={INPUT + ' h-36 font-mono text-xs'} value={selected.body_html} onChange={e => setF('body_html', e.target.value)} />
              </Field>
              <Field label="Plain Text Body" hint="Fallback for email clients that don't render HTML.">
                <textarea className={INPUT + ' h-20 font-mono text-xs'} value={selected.body_text} onChange={e => setF('body_text', e.target.value)} />
              </Field>
            </div>

            <SaveBar saving={saving} saved={saved} onSave={save} />
          </>
        )}
      </div>
    </div>
  )
}

// ── Feature Flags Tab ─────────────────────────────────────────────────────────

const FLAG_LABELS: Record<string, { label: string; hint: string }> = {
  sso_enabled:           { label: 'SSO Login Button',          hint: 'Show SSO sign-in button on the login screen.' },
  public_registration:   { label: 'Public Registration',       hint: 'Allow students to self-register.' },
  webhooks_enabled:      { label: 'Webhooks',                  hint: 'Send webhook events on submission state changes.' },
  allow_appeals:         { label: 'Appeal Requests',           hint: 'Allow authors to appeal rejected submissions.' },
  allow_meetings:        { label: 'Meeting Scheduling',        hint: 'Enable coordinator-scheduled review meetings.' },
  realtime_notifications:{ label: 'Real-Time Notifications',   hint: 'WebSocket push notifications (requires Reverb).' },
  reviewer_pool_enabled: { label: 'Reviewer Pool',             hint: 'Enable pool-based reviewer assignment.' },
  audit_log_enabled:     { label: 'Audit Logging',             hint: 'Log all system events to the audit trail.' },
  file_storage_s3:       { label: 'S3 File Storage',           hint: 'Store uploaded files in S3 instead of local disk.' },
}

function FlagsTab() {
  const [flags, setFlags] = useState<FeatureFlags>({})
  const [saving, setSaving] = useState<Record<string, boolean>>({})
  const [saved, setSaved]   = useState<Record<string, boolean>>({})
  const [error, setError]   = useState('')

  useEffect(() => { api.get('/system/feature-flags').then(r => setFlags(r.data)).catch(() => setError('Failed to load')) }, [])

  const toggle = async (key: string) => {
    const newVal = !flags[key]
    setFlags(prev => ({ ...prev, [key]: newVal }))
    setSaving(prev => ({ ...prev, [key]: true }))
    try {
      await api.patch(`/system/feature-flags/${key}`, { value: newVal })
      setSaved(prev => ({ ...prev, [key]: true }))
      setTimeout(() => setSaved(prev => ({ ...prev, [key]: false })), 2000)
    } catch {
      setFlags(prev => ({ ...prev, [key]: !newVal })) // revert
      setError('Failed to update flag.')
    } finally { setSaving(prev => ({ ...prev, [key]: false })) }
  }

  return (
    <div>
      <SectionHeader title="Feature Flags" subtitle="Enable or disable portal features. Changes take effect immediately." />
      {error && <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{error}</div>}
      <div className="space-y-1 max-w-2xl">
        {Object.entries(flags).map(([key, value]) => {
          const meta = FLAG_LABELS[key] ?? { label: key, hint: '' }
          return (
            <div key={key} className="flex items-center justify-between py-4 border-b border-gray-100">
              <div>
                <p className="text-sm font-medium text-gray-800">{meta.label}</p>
                {meta.hint && <p className="text-xs text-gray-400 mt-0.5">{meta.hint}</p>}
              </div>
              <div className="flex items-center gap-2 flex-shrink-0 ml-4">
                {saved[key] && <CheckCircle2 className="w-4 h-4 text-green-500" />}
                <button onClick={() => toggle(key)} disabled={saving[key]} className="disabled:opacity-50">
                  {value
                    ? <ToggleRight className="w-9 h-9 text-blue-600" />
                    : <ToggleLeft className="w-9 h-9 text-gray-300" />}
                </button>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

// ── Main Settings Page ────────────────────────────────────────────────────────

interface BackupEntry {
  filename: string
  path: string
  size_bytes: number
  created_at: string
}

function BackupTab() {
  const [backups, setBackups]   = useState<BackupEntry[]>([])
  const [loading, setLoading]   = useState(true)
  const [running, setRunning]   = useState(false)
  const [error, setError]       = useState('')
  const [successMsg, setSuccess] = useState('')

  const load = () => {
    setLoading(true)
    api.get('/system/backup')
      .then((r) => setBackups(r.data.data ?? []))
      .catch(() => setError('Failed to load backup list.'))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  const runBackup = async () => {
    setRunning(true)
    setError('')
    setSuccess('')
    try {
      await api.post('/system/backup')
      setSuccess('Backup completed successfully.')
      load()
    } catch {
      setError('Backup failed. Check server logs.')
    } finally {
      setRunning(false)
    }
  }

  function fmtBytes(bytes: number) {
    if (bytes < 1024)       return `${bytes} B`
    if (bytes < 1024 ** 2)  return `${(bytes / 1024).toFixed(1)} KB`
    if (bytes < 1024 ** 3)  return `${(bytes / (1024 ** 2)).toFixed(1)} MB`
    return `${(bytes / (1024 ** 3)).toFixed(2)} GB`
  }

  return (
    <div>
      <SectionHeader
        title="Backup & Archive"
        subtitle="Create on-demand backups of the database and uploaded files."
      />

      {error   && <div className="mb-4 p-3 bg-red-50   border border-red-200   text-red-700   text-sm rounded-lg">{error}</div>}
      {successMsg && <div className="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{successMsg}</div>}

      <div className="flex items-center justify-between mb-6">
        <p className="text-sm text-gray-500">{backups.length} backup{backups.length !== 1 ? 's' : ''} stored</p>
        <button
          onClick={runBackup}
          disabled={running}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {running ? <Loader2 className="w-4 h-4 animate-spin" /> : <HardDrive className="w-4 h-4" />}
          {running ? 'Running backup…' : 'Run Backup Now'}
        </button>
      </div>

      {loading ? (
        <div className="flex justify-center py-12"><Loader2 className="w-6 h-6 animate-spin text-gray-400" /></div>
      ) : backups.length === 0 ? (
        <div className="text-center py-12 text-gray-400 text-sm">No backups found.</div>
      ) : (
        <div className="border border-gray-200 rounded-lg overflow-hidden">
          <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="text-left px-4 py-2 text-gray-500 font-medium">Filename</th>
                <th className="text-right px-4 py-2 text-gray-500 font-medium">Size</th>
                <th className="text-right px-4 py-2 text-gray-500 font-medium">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {backups.map((b) => (
                <tr key={b.filename} className="hover:bg-gray-50">
                  <td className="px-4 py-2.5 font-mono text-xs text-gray-700">{b.filename}</td>
                  <td className="px-4 py-2.5 text-right text-gray-500">{fmtBytes(b.size_bytes)}</td>
                  <td className="px-4 py-2.5 text-right text-gray-500">
                    {new Date(b.created_at).toLocaleString()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        </div>
      )}
    </div>
  )
}

export default function SettingsPage() {
  const [tab, setTab] = useState<TabId>('org')

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">System Configuration</h1>
        <p className="text-sm text-gray-500 mt-1">Organization settings, email, SSO, and feature management.</p>
      </div>

      <div className="flex gap-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {/* Sidebar nav */}
        <nav className="w-52 flex-shrink-0 border-r border-gray-200 py-2">
          {TABS.map(({ id, label, icon: Icon }) => (
            <button
              key={id}
              onClick={() => setTab(id)}
              className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm font-medium transition-colors ${
                tab === id
                  ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-500'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              }`}
            >
              <Icon className="w-4 h-4 flex-shrink-0" />
              {label}
              {tab === id && <ChevronRight className="w-3 h-3 ml-auto" />}
            </button>
          ))}
        </nav>

        {/* Content panel */}
        <div className={`flex-1 overflow-auto ${tab === 'notifications' ? '' : 'p-6'}`}>
          {tab === 'org'           && <OrgTab />}
          {tab === 'email'         && <EmailTab />}
          {tab === 'sso'           && <SsoTab />}
          {tab === 'security'      && <SecurityTab />}
          {tab === 'notifications' && <NotificationsTab />}
          {tab === 'flags'         && <FlagsTab />}
          {tab === 'backup'        && <BackupTab />}
        </div>
      </div>
    </div>
  )
}

