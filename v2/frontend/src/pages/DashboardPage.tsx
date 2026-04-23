import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { FileText, ShieldCheck, Clock, CheckCircle, AlertTriangle, PenLine, Ban, Hourglass, Users, UserCheck } from 'lucide-react'
import { useAuthStore } from '../stores/authStore'
import api from '../lib/axios'

interface StatItem {
  key: string
  label: string
  value: number
}

interface DashboardStats {
  role: string
  stats: StatItem[]
}

const ICON_MAP: Record<string, React.ElementType> = {
  total_assigned:       Users,
  my_submissions:       FileText,
  draft:                PenLine,
  awaiting_reviewers:   Hourglass,
  under_review:         Clock,
  revision_required:    AlertTriangle,
  accepted:             CheckCircle,
  withdrawn:            Ban,
  pending_reviews:      Clock,
  overdue:              AlertTriangle,
  completed:            CheckCircle,
  active_submissions:   FileText,
  pending_assignment:   Clock,
  completed_this_month: CheckCircle,
  total_users:          ShieldCheck,
  // coordinator-specific
  total_reviews:        Users,
  completed_reviews:    CheckCircle,
  cancelled:            Ban,
  active_reviewers:     UserCheck,
}

const COLOR_MAP: Record<string, string> = {
  total_assigned:       'text-blue-600',
  my_submissions:       'text-blue-600',
  draft:                'text-slate-500',
  awaiting_reviewers:   'text-purple-500',
  under_review:         'text-amber-600',
  revision_required:    'text-orange-600',
  accepted:             'text-green-600',
  withdrawn:            'text-gray-400',
  pending_reviews:      'text-amber-600',
  overdue:              'text-red-600',
  completed:            'text-green-600',
  active_submissions:   'text-blue-600',
  pending_assignment:   'text-purple-600',
  completed_this_month: 'text-green-600',
  total_users:          'text-purple-600',
  // coordinator-specific
  total_reviews:        'text-blue-500',
  completed_reviews:    'text-green-600',
  cancelled:            'text-gray-400',
  active_reviewers:     'text-indigo-600',
}

export default function DashboardPage() {
  const user    = useAuthStore((s) => s.user)
  const navigate = useNavigate()

  const { data, isLoading } = useQuery<DashboardStats>({
    queryKey: ['dashboard-stats'],
    queryFn: () => api.get('/dashboard/stats').then(r => r.data),
    staleTime: 60_000,
  })

  if (!user) return null

  const primaryRole = user.roles[0] ?? 'student'

  const greeting = (() => {
    const hour = new Date().getHours()
    if (hour < 12) return 'Good morning'
    if (hour < 17) return 'Good afternoon'
    return 'Good evening'
  })()

  const stats = data?.stats ?? []

  return (
    <div className="space-y-6">
      {/* Welcome */}
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">
          {greeting}, {user.name.split(' ')[0]}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          Here's what's happening with your{' '}
          {primaryRole === 'student' ? 'submissions' : 'portal'} today.
        </p>
      </div>

      {/* Stats grid */}
      {isLoading ? (
        <div className="flex flex-wrap justify-center gap-4">
          {[1, 2, 3, 4, 5, 6].map(i => (
            <div key={i} className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm animate-pulse w-44">
              <div className="h-4 bg-gray-100 rounded w-2/3 mb-4" />
              <div className="h-8 bg-gray-100 rounded w-1/3 mb-2" />
              <div className="h-3 bg-gray-100 rounded w-1/2" />
            </div>
          ))}
        </div>
      ) : (
        <div className="flex flex-wrap justify-center gap-4">
          {stats.map((stat) => {
            const Icon  = ICON_MAP[stat.key] ?? FileText
            const color = COLOR_MAP[stat.key] ?? 'text-gray-600'
            const isAlert = (stat.key === 'overdue' || stat.key === 'revision_required') && stat.value > 0
            return (
              <div
                key={stat.key}
                className={`bg-white rounded-xl border shadow-sm p-5 min-w-[160px] flex-1 max-w-[220px] ${
                  isAlert ? 'border-red-200' : stat.key === 'withdrawn' ? 'border-gray-100 opacity-80' : 'border-gray-200'
                }`}
              >
                <div className="flex items-center justify-between">
                  <p className="text-sm font-medium text-gray-500">{stat.label}</p>
                  <Icon className={`w-5 h-5 ${color}`} />
                </div>
                <p className={`mt-3 text-3xl font-bold ${
                  isAlert ? 'text-red-600' : stat.key === 'withdrawn' ? 'text-gray-400' : 'text-gray-900'
                }`}>
                  {stat.value}
                </p>
              </div>
            )
          })}
        </div>
      )}

      {/* Quick actions */}
      {primaryRole === 'student' ? (
        <div className="flex flex-wrap justify-center gap-4">
          <QuickAction title="New Submission" description="Start submitting your research"
            icon={FileText} onClick={() => navigate('/submissions/new')} color="blue" />
          <QuickAction title="My Submissions" description="Track the status of your work"
            icon={Clock} onClick={() => navigate('/submissions')} color="amber" />
        </div>
      ) : primaryRole === 'reviewer' ? (
        <div className="flex flex-wrap justify-center gap-4">
          <div className="w-full max-w-[280px]">
            <QuickAction title="My Review Queue" description="View submissions awaiting your decision"
              icon={Clock} onClick={() => navigate('/reviews')} color="amber" />
          </div>
          <div className="w-full max-w-[280px]">
            <QuickAction title="My Submissions" description="View your own submitted work"
              icon={FileText} onClick={() => navigate('/submissions')} color="blue" />
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {(primaryRole === 'admin' || primaryRole === 'coordinator') && (
            <>
              <QuickAction title="All Submissions" description="Manage and track all submissions"
                icon={FileText} onClick={() => navigate('/submissions')} color="blue" />
              <QuickAction title="User Management" description="Manage accounts and roles"
                icon={ShieldCheck} onClick={() => navigate('/users')} color="purple" />
              <QuickAction title="Review Queue" description="See who needs reviewing"
                icon={Clock} onClick={() => navigate('/reviews')} color="amber" />
            </>
          )}
        </div>
      )}
    </div>
  )
}

function QuickAction({
  title, description, icon: Icon, onClick, color,
}: {
  title: string
  description: string
  icon: React.ElementType
  onClick: () => void
  color: 'blue' | 'amber' | 'purple' | 'green'
}) {
  const colors = {
    blue:   'bg-blue-50 text-blue-600 hover:bg-blue-100',
    amber:  'bg-amber-50 text-amber-600 hover:bg-amber-100',
    purple: 'bg-purple-50 text-purple-600 hover:bg-purple-100',
    green:  'bg-green-50 text-green-600 hover:bg-green-100',
  }
  return (
    <button
      onClick={onClick}
      className="flex items-center gap-4 p-4 rounded-xl border border-gray-200 bg-white hover:shadow-sm transition-shadow text-left w-full"
    >
      <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${colors[color]}`}>
        <Icon className="w-5 h-5" />
      </div>
      <div>
        <p className="text-sm font-semibold text-gray-900">{title}</p>
        <p className="text-xs text-gray-500 mt-0.5">{description}</p>
      </div>
    </button>
  )
}
