import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, useRef, useEffect } from 'react'
import { Bell, Sun, Moon, Palette } from 'lucide-react'
import { useAuthStore } from '../../stores/authStore'
import { useThemeStore } from '../../stores/themeStore'
import api from '../../lib/axios'
import cityuLogo from '../../assets/city-university-logo.svg'
import type { PublicOrgInfo } from '../../types/organization'
import type { AppNotification, NotificationsResponse } from '../../types/notifications'

const ACCENT_PRESETS = [
  { label: 'Default',  color: null },
  { label: 'Indigo',   color: '#4f46e5' },
  { label: 'Blue',     color: '#2563eb' },
  { label: 'Teal',     color: '#0d9488' },
  { label: 'Green',    color: '#16a34a' },
  { label: 'Purple',   color: '#7c3aed' },
  { label: 'Rose',     color: '#e11d48' },
  { label: 'Orange',   color: '#ea580c' },
  { label: 'Amber',    color: '#d97706' },
]

export default function TopBar() {
  const user = useAuthStore((s) => s.user)
  const clearAuth = useAuthStore((s) => s.clearAuth)
  const openProfile = useAuthStore((s) => s.openProfile)
  const { isDark, toggleDark, accentColor, setAccentColor } = useThemeStore()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [bellOpen, setBellOpen] = useState(false)
  const [paletteOpen, setPaletteOpen] = useState(false)
  const bellRef = useRef<HTMLDivElement>(null)
  const paletteRef = useRef<HTMLDivElement>(null)

  const { data: orgInfo } = useQuery<PublicOrgInfo>({
    queryKey: ['org-public'],
    queryFn: () => api.get('/system/public').then((r) => r.data),
    staleTime: 5 * 60 * 1000,
  })

  const { data: notifData } = useQuery<NotificationsResponse>({
    queryKey: ['notifications'],
    queryFn: () => api.get('/notifications').then((r) => r.data),
    refetchInterval: 30_000,
    enabled: !!user,
  })

  const unreadCount = notifData?.unread_count ?? 0
  const recentNotifs = (notifData?.data ?? []).slice(0, 8)

  const markAllRead = useMutation({
    mutationFn: () => api.post('/notifications/read'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  })

  // Apply CSS variable for org primary colour
  useEffect(() => {
    const colour = orgInfo?.primary_color
    if (colour) {
      document.documentElement.style.setProperty('--color-primary', colour)
    }
  }, [orgInfo?.primary_color])

  // Close dropdowns when clicking outside
  useEffect(() => {
    function handler(e: MouseEvent) {
      if (bellRef.current && !bellRef.current.contains(e.target as Node)) setBellOpen(false)
      if (paletteRef.current && !paletteRef.current.contains(e.target as Node)) setPaletteOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const portalName = orgInfo?.portal_name || 'Research Review Portal'
  const brandColour = accentColor || orgInfo?.primary_color || '#0d1f3c'
  const logoSrc = orgInfo?.logo_url || cityuLogo

  const logoutMutation = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSettled: () => {
      clearAuth()
      navigate('/login', { replace: true })
    },
  })

  return (
    <header style={{
      height: 56, background: brandColour, borderBottom: '1px solid rgba(255,255,255,0.07)',
      display: 'flex', alignItems: 'center', flexShrink: 0,
      padding: '0 1.5rem',
    }}>
      {/* Left: logo + title */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
        <img
          src={logoSrc}
          alt="Organisation logo"
          style={{ height: 28, width: 'auto', objectFit: 'contain', filter: 'brightness(0) invert(1)', flexShrink: 0 }}
        />
        <div style={{ width: 1, height: 24, background: 'rgba(255,255,255,0.2)', flexShrink: 0 }} />
        <span style={{ fontWeight: 700, fontSize: '1rem', color: '#fff', letterSpacing: '-0.2px', whiteSpace: 'nowrap' }}>
          {portalName}
        </span>
      </div>

      {/* Spacer */}
      <div style={{ flex: 1 }} />

      {/* Right: color picker + dark toggle + notifications bell + user info + logout */}
      {user && (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>

          {/* Portal color personalization */}
          <div ref={paletteRef} style={{ position: 'relative' }}>
            <button
              onClick={() => setPaletteOpen((o) => !o)}
              title="Personalize portal color"
              style={{
                background: accentColor ? 'rgba(255,255,255,0.15)' : 'transparent',
                border: 'none', cursor: 'pointer',
                padding: '4px 6px', borderRadius: 4,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                transition: 'background 0.15s',
              }}
              onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.15)')}
              onMouseLeave={(e) => (e.currentTarget.style.background = accentColor ? 'rgba(255,255,255,0.15)' : 'transparent')}
            >
              <Palette size={18} color="rgba(255,255,255,0.8)" />
            </button>

            {paletteOpen && (
              <div style={{
                position: 'absolute', top: '110%', right: 0, zIndex: 300,
                width: 220, background: '#fff', borderRadius: 10,
                boxShadow: '0 8px 30px rgba(0,0,0,0.18)', border: '1px solid #e5e7eb',
                padding: '10px 12px',
              }}>
                <p style={{ fontSize: '0.75rem', fontWeight: 600, color: '#6b7280', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                  Portal Color
                </p>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                  {ACCENT_PRESETS.map((preset) => (
                    <button
                      key={preset.label}
                      onClick={() => { setAccentColor(preset.color); setPaletteOpen(false) }}
                      title={preset.label}
                      style={{
                        width: 28, height: 28, borderRadius: '50%',
                        background: preset.color ?? '#e5e7eb',
                        border: accentColor === preset.color ? '2px solid #111827' : '2px solid transparent',
                        cursor: 'pointer',
                        outline: 'none',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                      }}
                    >
                      {preset.color === null && (
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                          <path d="M1 1l10 10M11 1L1 11" stroke="#6b7280" strokeWidth="2" strokeLinecap="round"/>
                        </svg>
                      )}
                    </button>
                  ))}
                </div>
                <p style={{ fontSize: '0.68rem', color: '#9ca3af', marginTop: 8 }}>
                  Applies to nav bar color only. Resets to organization default when cleared.
                </p>
              </div>
            )}
          </div>

          {/* Dark mode toggle */}
          <button
            onClick={toggleDark}
            title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            style={{
              background: 'transparent', border: 'none', cursor: 'pointer',
              padding: '4px 6px', borderRadius: 4,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              transition: 'background 0.15s',
            }}
            onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
            onMouseLeave={(e) => (e.currentTarget.style.background = 'transparent')}
          >
            {isDark
              ? <Sun size={20} color="rgba(255,255,255,0.8)" />
              : <Moon size={20} color="rgba(255,255,255,0.8)" />
            }
          </button>

          {/* Bell icon with unread badge */}
          <div ref={bellRef} style={{ position: 'relative' }}>
            <button
              onClick={() => setBellOpen((o) => !o)}
              style={{
                background: 'transparent', border: 'none', cursor: 'pointer',
                padding: '4px 6px', borderRadius: 4, position: 'relative',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                transition: 'background 0.15s',
              }}
              onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
              onMouseLeave={(e) => (e.currentTarget.style.background = 'transparent')}
              title="Notifications"
              aria-label="Notifications"
            >
              <Bell size={20} color="rgba(255,255,255,0.8)" />
              {unreadCount > 0 && (
                <span style={{
                  position: 'absolute', top: 0, right: 0,
                  background: '#ef4444', color: '#fff',
                  borderRadius: '50%', minWidth: 16, height: 16,
                  fontSize: '0.6rem', fontWeight: 700,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  lineHeight: 1, padding: '0 2px',
                }}>
                  {unreadCount > 99 ? '99+' : unreadCount}
                </span>
              )}
            </button>

            {/* Dropdown */}
            {bellOpen && (
              <div style={{
                position: 'absolute', top: '110%', right: 0, zIndex: 200,
                width: 340, background: '#fff', borderRadius: 8,
                boxShadow: '0 8px 30px rgba(0,0,0,0.18)', border: '1px solid #e5e7eb',
                overflow: 'hidden',
              }}>
                <div style={{
                  padding: '10px 14px', borderBottom: '1px solid #f3f4f6',
                  display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                }}>
                  <span style={{ fontWeight: 700, fontSize: '0.9rem', color: '#111827' }}>
                    Notifications {unreadCount > 0 && (
                      <span style={{
                        marginLeft: 6, background: '#ef4444', color: '#fff',
                        borderRadius: 10, fontSize: '0.7rem', padding: '1px 6px',
                      }}>{unreadCount}</span>
                    )}
                  </span>
                  <div style={{ display: 'flex', gap: 8 }}>
                    {unreadCount > 0 && (
                      <button
                        onClick={() => markAllRead.mutate()}
                        style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: '0.75rem', color: '#6366f1', fontWeight: 500 }}
                      >
                        Mark all read
                      </button>
                    )}
                    <button
                      onClick={() => { setBellOpen(false); navigate('/notifications') }}
                      style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: '0.75rem', color: '#6b7280' }}
                    >
                      See all
                    </button>
                  </div>
                </div>

                {recentNotifs.length === 0 ? (
                  <div style={{ padding: '24px 0', textAlign: 'center', color: '#9ca3af', fontSize: '0.85rem' }}>
                    No notifications yet
                  </div>
                ) : (
                  <div style={{ maxHeight: 360, overflowY: 'auto' }}>
                    {recentNotifs.map((n: AppNotification) => (
                      <button
                        key={n.id}
                        onClick={() => {
                          setBellOpen(false)
                          if (n.link) navigate(n.link)
                          if (!n.read_at) {
                            api.post('/notifications/read', { ids: [n.id] })
                              .then(() => queryClient.invalidateQueries({ queryKey: ['notifications'] }))
                          }
                        }}
                        style={{
                          display: 'block', width: '100%', textAlign: 'left',
                          padding: '10px 14px', background: n.read_at ? 'transparent' : '#eef2ff',
                          border: 'none', borderBottom: '1px solid #f9fafb',
                          cursor: 'pointer', transition: 'background 0.1s',
                        }}
                        onMouseEnter={(e) => (e.currentTarget.style.background = '#f3f4f6')}
                        onMouseLeave={(e) => (e.currentTarget.style.background = n.read_at ? 'transparent' : '#eef2ff')}
                      >
                        <div style={{ fontWeight: n.read_at ? 400 : 600, fontSize: '0.82rem', color: '#111827', marginBottom: 2, lineHeight: 1.35 }}>
                          {n.title}
                        </div>
                        <div style={{ fontSize: '0.77rem', color: '#6b7280', lineHeight: 1.4 }}>
                          {n.body}
                        </div>
                        <div style={{ fontSize: '0.7rem', color: '#9ca3af', marginTop: 4 }}>
                          {new Date(n.created_at).toLocaleString()}
                        </div>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          <button
            onClick={openProfile}
            style={{
              background: 'transparent', border: 'none', padding: '4px 8px',
              cursor: 'pointer', borderRadius: 4, transition: 'background 0.15s',
              display: 'flex', alignItems: 'center', gap: '0.5rem',
            }}
            onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
            onMouseLeave={(e) => (e.currentTarget.style.background = 'transparent')}
            title="Edit profile"
          >
            <div style={{
              width: 28, height: 28, borderRadius: '50%',
              background: 'rgba(255,255,255,0.15)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: '0.8rem', fontWeight: 700, color: '#fff', flexShrink: 0,
            }}>
              {user.name.charAt(0).toUpperCase()}
            </div>
            <span style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.72)', whiteSpace: 'nowrap' }}>
              <strong style={{ color: '#fff' }}>{user.name}</strong>
            </span>
          </button>
          <button
            onClick={() => logoutMutation.mutate()}
            disabled={logoutMutation.isPending}
            style={{
              background: 'transparent', color: '#fff',
              border: '1px solid rgba(255,255,255,0.4)', borderRadius: 4,
              padding: '4px 14px', fontSize: '0.8rem', fontWeight: 500,
              cursor: 'pointer', whiteSpace: 'nowrap',
              transition: 'background 0.15s',
            }}
            onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
            onMouseLeave={(e) => (e.currentTarget.style.background = 'transparent')}
          >
            {logoutMutation.isPending ? 'Logging out…' : 'Logout'}
          </button>
        </div>
      )}
    </header>
  )
}
