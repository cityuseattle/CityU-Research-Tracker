import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard,
  FileText,
  Users,
  Settings,
  GitBranch,
  Bell,
  BarChart2,
  ShieldCheck,
  Tag,
  BookOpen,
  BookMarked,
  UserCog,
  Shield,
  Webhook,
  Gavel,
  MessageSquare,
  Calendar,
  TrendingUp,
  ClipboardList,
  Megaphone,
  ShieldAlert,
  type LucideIcon,
} from 'lucide-react'
import { clsx } from 'clsx'
import { useAuthStore } from '../../stores/authStore'
import type { Role } from '../../types/auth'

interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  roles: Role[]
}

interface NavSection {
  heading: string
  items: NavItem[]
}

const navSections: NavSection[] = [
  {
    heading: 'General',
    items: [
      {
        label: 'Dashboard',
        to: '/dashboard',
        icon: LayoutDashboard,
        roles: ['admin', 'coordinator', 'reviewer', 'student'],
      },
      {
        label: 'Submissions',
        to: '/submissions',
        icon: FileText,
        roles: ['admin', 'coordinator', 'reviewer', 'student'],
      },
      {
        label: 'Assignments',
        to: '/reviews',
        icon: ShieldCheck,
        roles: ['admin', 'coordinator', 'reviewer'],
      },
      {
        label: 'My Analytics',
        to: '/reviewer-analytics',
        icon: TrendingUp,
        roles: ['reviewer'],
      },
      {
        label: 'Notifications',
        to: '/notifications',
        icon: Bell,
        roles: ['admin', 'coordinator', 'reviewer', 'student'],
      },
      {
        label: 'Calendar',
        to: '/calendar',
        icon: Calendar,
        roles: ['admin', 'coordinator', 'reviewer', 'student'],
      },
      {
        label: 'Reports',
        to: '/reports',
        icon: BarChart2,
        roles: ['admin', 'coordinator'],
      },
    ],
  },
  {
    heading: 'Configuration',
    items: [
      {
        label: 'Submission Categories',
        to: '/submission-categories',
        icon: Tag,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Workflows',
        to: '/workflows',
        icon: GitBranch,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Reviewer Pool',
        to: '/reviewer-assignments',
        icon: BookOpen,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Researcher Access',
        to: '/researcher-access',
        icon: UserCog,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Programs',
        to: '/programs',
        icon: BookMarked,
        roles: ['admin', 'coordinator'],
      },
    ],
  },
  {
    heading: 'Administration',
    items: [
      {
        label: 'User Management',
        to: '/users',
        icon: Users,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Audit Log',
        to: '/audit-log',
        icon: Shield,
        roles: ['admin'],
      },
      {
        label: 'Gated Reviews',
        to: '/gated-reviews',
        icon: Gavel,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Review Management',
        to: '/review-management',
        icon: ClipboardList,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Appeals',
        to: '/appeals',
        icon: MessageSquare,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Webhooks',
        to: '/webhooks',
        icon: Webhook,
        roles: ['admin'],
      },
      {
        label: 'Announcements',
        to: '/announcements',
        icon: Megaphone,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Custom Roles',
        to: '/custom-roles',
        icon: ShieldAlert,
        roles: ['admin', 'coordinator'],
      },
      {
        label: 'Settings',
        to: '/settings',
        icon: Settings,
        roles: ['admin'],
      },
    ],
  },
]

export default function Sidebar() {
  const user = useAuthStore((s) => s.user)
  const openProfile = useAuthStore((s) => s.openProfile)

  return (
    <aside className="flex flex-col w-56 text-white flex-shrink-0 border-r border-black/20"
      style={{ background: 'var(--color-primary, #0d1f3c)' }}
    >
      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 pt-3 pb-4 space-y-4">
        {navSections.map((section) => {
          const visible = section.items.filter((item) =>
            item.roles.some((r) => user?.roles.includes(r)),
          )
          if (visible.length === 0) return null
          return (
            <div key={section.heading}>
              <p className="px-3 py-1 text-xs font-semibold uppercase tracking-widest text-brand-400 select-none">
                {section.heading}
              </p>
              <div className="space-y-0.5">
                {visible.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    className={({ isActive }) =>
                      clsx(
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive
                          ? 'bg-white/10 text-white'
                          : 'text-brand-200 hover:bg-white/5 hover:text-white',
                      )
                    }
                  >
                    <item.icon className="w-4 h-4 flex-shrink-0" />
                    {item.label}
                  </NavLink>
                ))}
              </div>
            </div>
          )
        })}
      </nav>

      {/* User info at bottom */}
      {user && (
        <button
          onClick={openProfile}
          className="w-full px-4 py-4 border-t border-brand-900 hover:bg-white/5 transition-colors text-left"
          title="Edit profile"
        >
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center text-sm font-semibold flex-shrink-0">
              {user.name.charAt(0).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium truncate">{user.name}</p>
              <p className="text-xs text-brand-300 capitalize">{user.roles[0]}</p>
            </div>
            <svg className="w-3.5 h-3.5 text-brand-400 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-1.414.94l-3.414.946.946-3.414a4 4 0 01.94-1.414z" />
            </svg>
          </div>
        </button>
      )}
    </aside>
  )
}



