import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ChevronLeft, ChevronRight, Calendar, Clock, CheckCircle2, Loader2, Flag } from 'lucide-react'
import api from '../lib/axios'
import type { CalendarDeadline } from '../types/submissions'

// ── helpers ───────────────────────────────────────────────────────────────────

function daysInMonth(year: number, month: number) {
  return new Date(year, month + 1, 0).getDate()
}

function firstDayOfMonth(year: number, month: number) {
  return new Date(year, month, 1).getDay() // 0=Sun
}

// ── CalendarPage ──────────────────────────────────────────────────────────────

export default function CalendarPage() {
  const navigate = useNavigate()
  const today = new Date()
  const [year,  setYear]  = useState(today.getFullYear())
  const [month, setMonth] = useState(today.getMonth())
  const [selected, setSelected] = useState<string | null>(null)

  const { data, isLoading } = useQuery<{ data: CalendarDeadline[] }>({
    queryKey: ['calendar-deadlines'],
    queryFn: () => api.get('/calendar/deadlines').then(r => r.data),
    staleTime: 5 * 60_000,
  })

  const deadlines = data?.data ?? []

  // Group by date string
  const byDate: Record<string, CalendarDeadline[]> = {}
  deadlines.forEach(d => {
    if (!byDate[d.date]) byDate[d.date] = []
    byDate[d.date].push(d)
  })

  const prevMonth = () => {
    if (month === 0) { setYear(y => y - 1); setMonth(11) }
    else setMonth(m => m - 1)
    setSelected(null)
  }
  const nextMonth = () => {
    if (month === 11) { setYear(y => y + 1); setMonth(0) }
    else setMonth(m => m + 1)
    setSelected(null)
  }
  const goToday = () => {
    setYear(today.getFullYear())
    setMonth(today.getMonth())
    setSelected(null)
  }

  const totalDays  = daysInMonth(year, month)
  const startDay   = firstDayOfMonth(year, month)
  const cells: (number | null)[] = [
    ...Array(startDay).fill(null),
    ...Array.from({ length: totalDays }, (_, i) => i + 1),
  ]
  // Pad to complete last row
  while (cells.length % 7 !== 0) cells.push(null)

  const monthLabel = new Date(year, month, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' })

  const selectedEvents = selected ? (byDate[selected] ?? []) : []

  return (
    <div className="max-w-5xl mx-auto px-4 py-6 space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Deadline Calendar</h1>
          <p className="text-sm text-gray-500 mt-0.5">Review deadlines and submission milestones</p>
        </div>
        {isLoading && <Loader2 className="w-5 h-5 animate-spin text-gray-400" />}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Calendar grid */}
        <div className="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          {/* Month navigation */}
          <div className="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
            <button onClick={prevMonth} className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500">
              <ChevronLeft className="w-4 h-4" />
            </button>
            <div className="flex items-center gap-3">
              <h2 className="text-sm font-semibold text-gray-900">{monthLabel}</h2>
              <button
                onClick={goToday}
                className="text-xs text-indigo-600 hover:underline font-medium"
              >
                Today
              </button>
            </div>
            <button onClick={nextMonth} className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500">
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>

          {/* Day headers */}
          <div className="grid grid-cols-7 border-b border-gray-100">
            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => (
              <div key={d} className="py-2 text-center text-xs font-medium text-gray-400">{d}</div>
            ))}
          </div>

          {/* Day cells */}
          <div className="grid grid-cols-7">
            {cells.map((day, idx) => {
              if (day === null) {
                return <div key={`empty-${idx}`} className="border-b border-r border-gray-50 min-h-[64px]" />
              }
              const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
              const events = byDate[dateStr] ?? []
              const isToday = dateStr === today.toISOString().slice(0, 10)
              const isSelected = selected === dateStr
              const hasEvents = events.length > 0
              const allDone = hasEvents && events.every(e => e.is_completed)
              const hasStageComplete = hasEvents && events.some(e => e.type === 'stage_complete')
              const onlyStageComplete = hasEvents && events.every(e => e.type === 'stage_complete')

              return (
                <div
                  key={dateStr}
                  onClick={() => setSelected(isSelected ? null : dateStr)}
                  className={`relative border-b border-r border-gray-50 min-h-[64px] p-1.5 cursor-pointer transition-colors
                    ${isSelected ? 'bg-indigo-50 border-indigo-200' : 'hover:bg-gray-50'}
                    ${isToday ? 'ring-2 ring-inset ring-indigo-300' : ''}`}
                >
                  <span className={`text-xs font-medium flex items-center justify-center w-5 h-5 rounded-full
                    ${isToday ? 'bg-indigo-600 text-white' : 'text-gray-700'}`}>
                    {day}
                  </span>
                  {hasEvents && (
                    <div className="mt-1 space-y-0.5">
                      {events.slice(0, 2).map((e, i) => (
                        <div
                          key={i}
                          className={`text-[10px] rounded px-1 py-0.5 truncate leading-tight
                            ${e.is_completed
                              ? 'bg-gray-100 text-gray-400 line-through'
                              : e.type === 'stage_complete'
                                ? 'bg-purple-100 text-purple-700'
                                : dateStr < today.toISOString().slice(0, 10)
                                  ? 'bg-red-100 text-red-700'
                                  : 'bg-amber-100 text-amber-800'}`}
                          title={e.stage ? `${e.title} — ${e.stage}` : e.title}
                        >
                          {e.type === 'stage_complete' && !e.is_completed ? '● ' : ''}{e.title}
                        </div>
                      ))}
                      {events.length > 2 && (
                        <div className="text-[10px] text-gray-400 px-1">+{events.length - 2} more</div>
                      )}
                    </div>
                  )}
                  {hasStageComplete && !allDone && (
                    <div className="absolute top-1 right-1">
                      <div className={`w-1.5 h-1.5 rounded-full ${onlyStageComplete ? 'bg-purple-400' : 'bg-purple-300'}`} />
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </div>

        {/* Event sidebar */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden self-start">
          <div className="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
            <Calendar className="w-4 h-4 text-gray-500" />
            <h3 className="text-sm font-semibold text-gray-900">
              {selected
                ? new Date(selected + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
                : 'Select a date'}
            </h3>
          </div>

          {!selected ? (
            <div className="flex flex-col items-center py-12 text-center px-4">
              <Calendar className="w-10 h-10 text-gray-200 mb-3" />
              <p className="text-sm text-gray-400">Click a date to see deadlines</p>
            </div>
          ) : selectedEvents.length === 0 ? (
            <div className="flex flex-col items-center py-12 text-center px-4">
              <CheckCircle2 className="w-10 h-10 text-gray-200 mb-3" />
              <p className="text-sm text-gray-400">No deadlines on this date</p>
            </div>
          ) : (
            <ul className="divide-y divide-gray-50">
              {selectedEvents.map((e, i) => {
                const isOverdue = !e.is_completed && e.date < today.toISOString().slice(0, 10)
                return (
                  <li
                    key={i}
                    onClick={() => navigate(`/submissions/${e.submission_id}`)}
                    className="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <p className={`text-sm font-medium leading-snug ${e.is_completed ? 'text-gray-400 line-through' : 'text-gray-800'}`}>
                        {e.title}
                      </p>
                      {e.is_completed ? (
                        <CheckCircle2 className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                      ) : e.type === 'stage_complete' ? (
                        <Flag className="w-4 h-4 text-purple-400 flex-shrink-0 mt-0.5" />
                      ) : isOverdue ? (
                        <Clock className="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" />
                      ) : (
                        <Clock className="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" />
                      )}
                    </div>
                    {e.stage && (
                      <p className="text-xs text-gray-400 mt-0.5">{e.stage}</p>
                    )}
                    {e.reviewer && (
                      <p className="text-xs text-indigo-500 mt-0.5">Reviewer: {e.reviewer}</p>
                    )}
                    <span className={`inline-flex items-center mt-1.5 text-[10px] font-medium px-1.5 py-0.5 rounded-full
                      ${e.is_completed
                        ? 'bg-gray-100 text-gray-400'
                        : e.type === 'stage_complete'
                          ? 'bg-purple-100 text-purple-700'
                          : isOverdue
                            ? 'bg-red-100 text-red-600'
                            : 'bg-amber-100 text-amber-700'}`}>
                      {e.is_completed ? 'Completed' : e.type === 'stage_complete' ? 'Stage deadline' : isOverdue ? 'Overdue' : 'Pending'}
                    </span>
                  </li>
                )
              })}
            </ul>
          )}
        </div>
      </div>

      {/* Upcoming deadlines list */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 className="text-sm font-semibold text-gray-900 mb-3">Upcoming Deadlines (next 30 days)</h3>
        {deadlines.filter(d => {
          const dDate = new Date(d.date)
          const diff = (dDate.getTime() - today.getTime()) / 86400000
          return diff >= 0 && diff <= 30 && !d.is_completed
        }).length === 0 ? (
          <p className="text-sm text-gray-400 py-4 text-center">No upcoming deadlines in the next 30 days.</p>
        ) : (
          <ul className="space-y-2">
            {deadlines
              .filter(d => {
                const dDate = new Date(d.date)
                const diff = (dDate.getTime() - today.getTime()) / 86400000
                return diff >= 0 && diff <= 30 && !d.is_completed
              })
              .map((d, i) => {
                const diff = Math.round((new Date(d.date).getTime() - today.getTime()) / 86400000)
                return (
                  <li
                    key={i}
                    onClick={() => navigate(`/submissions/${d.submission_id}`)}
                    className="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors"
                  >
                    <div className={`w-9 h-9 rounded-lg flex flex-col items-center justify-center flex-shrink-0 text-white text-xs font-bold
                      ${diff <= 3 ? 'bg-red-500' : diff <= 7 ? 'bg-amber-500' : 'bg-indigo-500'}`}>
                      <span>{new Date(d.date + 'T00:00:00').toLocaleString('en-US', { month: 'short' })}</span>
                      <span>{new Date(d.date + 'T00:00:00').getDate()}</span>
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-800 truncate">{d.title}</p>
                      {d.stage && <p className="text-xs text-gray-400">{d.stage}</p>}
                    </div>
                    <span className={`text-xs font-medium flex-shrink-0
                      ${diff === 0 ? 'text-red-600' : diff <= 3 ? 'text-amber-600' : 'text-gray-500'}`}>
                      {diff === 0 ? 'Today' : diff === 1 ? 'Tomorrow' : `${diff}d`}
                    </span>
                  </li>
                )
              })}
          </ul>
        )}
      </div>
    </div>
  )
}
