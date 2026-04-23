import { useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import TopBar from './TopBar'
import ProfileModal from './ProfileModal'
import BottomNav from './BottomNav'
import { ToastProvider } from '../../lib/toast'
import { useThemeStore } from '../../stores/themeStore'

export default function AppShell() {
  const isDark = useThemeStore((s) => s.isDark)
  const accentColor = useThemeStore((s) => s.accentColor)

  // Sync dark class on <html>
  useEffect(() => {
    document.documentElement.classList.toggle('dark', isDark)
  }, [isDark])

  // Sync user accent color CSS variable
  useEffect(() => {
    if (accentColor) {
      document.documentElement.style.setProperty('--color-user-accent', accentColor)
    } else {
      document.documentElement.style.removeProperty('--color-user-accent')
    }
  }, [accentColor])

  return (
    <ToastProvider>
      <div className="flex flex-col h-screen overflow-hidden">
        {/* Full-width dark nav header */}
        <TopBar />

        {/* Below header: sidebar + content */}
        <div className="flex flex-1 overflow-hidden">
          <div className="hidden md:flex">
            <Sidebar />
          </div>
          <main className="flex-1 overflow-y-auto overflow-x-hidden bg-gray-50 dark:bg-gray-900 p-4 md:p-6 pb-20 md:pb-6">
            <Outlet />
          </main>
        </div>

        {/* Mobile bottom nav */}
        <BottomNav />

        {/* Profile / password modal — rendered here so it sits above all content */}
        <ProfileModal />
      </div>
    </ToastProvider>
  )
}
